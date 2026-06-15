<?php
declare(strict_types=1);

function integration_fail(string $message): never
{
    throw new RuntimeException($message);
}

function integration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        integration_fail($message);
    }
}

function integration_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function integration_safe_database_name(string $database): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1
        && preg_match('/(?:test|local|dev|disposable|staging)/i', $database) === 1;
}

function integration_run(array $command, string $input = ''): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        integration_fail('Unable to start integration subprocess.');
    }

    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

function integration_request(
    string $method,
    int $userId,
    string $csrfMode,
    array $query = [],
    array $body = [],
    string $resource = 'assignments'
): array {
    $command = [
        PHP_BINARY,
        __DIR__ . '/fixtures/shift-assignment-api-request.php',
        $method,
        (string)$userId,
        $csrfMode,
        json_encode($query, JSON_THROW_ON_ERROR),
        base64_encode($body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR)),
        $resource,
    ];
    $result = integration_run($command);
    integration_assert(
        $result['exit_code'] === 0,
        'API harness failed: ' . trim($result['stderr'])
    );
    preg_match('/PHASE15_STATUS:(\d+)/', $result['stderr'], $statusMatch);
    $status = isset($statusMatch[1]) ? (int)$statusMatch[1] : 0;
    integration_assert($status > 0, 'API harness did not report an HTTP status.');
    $payload = json_decode(trim($result['stdout']), true);
    integration_assert(
        is_array($payload),
        'API harness returned invalid JSON: ' . trim($result['stdout'])
    );
    $payload['_test_status'] = $status;
    return $payload;
}

function integration_scalar(mysqli $conn, string $sql): mixed
{
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        integration_fail('Query failed during integration assertion.');
    }
    $row = $result->fetch_row();
    $result->free();
    return $row[0] ?? null;
}

function integration_restore_assignment(
    mysqli $conn,
    array $snapshot,
    int $actorId,
    int $deleteAuditId
): void {
    $columns = [
        'id',
        'user_id',
        'division_id',
        'shift_template_id',
        'assignment_date',
        'start_datetime',
        'end_datetime',
        'is_cross_day',
        'break_minutes',
        'calculated_duration_minutes',
        'assignment_type',
        'status',
        'is_overtime',
        'is_holiday_assignment',
        'is_manual_duration_override',
        'approval_status',
        'source',
        'monthly_template_id',
        'approved_by',
        'approved_at',
        'notes',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];
    foreach ($columns as $column) {
        integration_assert(
            array_key_exists($column, $snapshot),
            "Before-delete snapshot is missing {$column}."
        );
    }

    $conn->begin_transaction();
    try {
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $conn->prepare(
            'INSERT INTO shift_assignments (`'
            . implode('`,`', $columns)
            . "`) VALUES ({$placeholders})"
        );
        integration_assert($stmt instanceof mysqli_stmt, 'Unable to prepare exact restore.');

        $types = str_repeat('s', count($columns));
        $values = array_map(
            static fn(string $column): mixed => $snapshot[$column],
            $columns
        );
        $params = [$types];
        foreach ($values as &$value) {
            $params[] = &$value;
        }
        integration_assert(
            call_user_func_array([$stmt, 'bind_param'], $params),
            'Unable to bind exact restore values.'
        );
        integration_assert($stmt->execute(), 'Exact restore INSERT failed.');
        $stmt->close();

        $afterJson = json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $reason = "Restored from delete audit {$deleteAuditId} in disposable drill.";
        $restoredId = (int)$snapshot['id'];
        $activity = $conn->prepare("
            INSERT INTO tracs_user_activity_logs
              (actor_user_id,target_type,target_id,action,before_data,after_data,
               reason,ip_address,user_agent,created_at)
            VALUES
              (?,'shift_assignment',?,'shift_assignment.restore',NULL,?,?,?,
               'TRACS Phase 23 Restoration Drill',NOW())
        ");
        integration_assert(
            $activity instanceof mysqli_stmt,
            'Unable to prepare restoration audit.'
        );
        $ip = '127.0.0.1';
        $activity->bind_param(
            'iisss',
            $actorId,
            $restoredId,
            $afterJson,
            $reason,
            $ip
        );
        integration_assert($activity->execute(), 'Unable to write restoration audit.');
        $activity->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

$environment = strtolower(integration_env('TRACS_ENV'));
$allowMutations = integration_env('TRACS_ALLOW_MUTATION_TESTS');
$database = integration_env('TRACS_TEST_DB_NAME', 'tracs_phase15_test');
$sourceDatabase = integration_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = integration_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)integration_env('TRACS_TEST_DB_PORT', '3307');
$user = integration_env('TRACS_TEST_DB_USER', 'root');
$pass = integration_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = integration_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');
$includeDelete = integration_env('TRACS_TEST_INCLUDE_DELETE') === '1';
$includeRestore = integration_env('TRACS_TEST_INCLUDE_RESTORE') === '1';

if ($environment !== 'test') {
    fwrite(STDERR, "SKIPPED: TRACS_ENV must be exactly test.\n");
    exit(3);
}
if ($allowMutations !== '1') {
    fwrite(STDERR, "SKIPPED: TRACS_ALLOW_MUTATION_TESTS=1 is required.\n");
    exit(3);
}
if (!integration_safe_database_name($database)) {
    fwrite(STDERR, "REFUSED: disposable database name is not safely marked.\n");
    exit(4);
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $sourceDatabase)
    || $sourceDatabase === $database
    || in_array(strtolower($environment), ['prod', 'production'], true)) {
    fwrite(STDERR, "REFUSED: unsafe source/target database configuration.\n");
    exit(4);
}

foreach ([
    'TRACS_TEST_DB_NAME' => $database,
    'TRACS_TEST_DB_HOST' => $host,
    'TRACS_TEST_DB_PORT' => (string)$port,
    'TRACS_TEST_DB_USER' => $user,
    'TRACS_TEST_DB_PASS' => $pass,
] as $key => $value) {
    putenv($key . '=' . $value);
}

$admin = new mysqli($host, $user, $pass, '', $port);
if ($admin->connect_error) {
    fwrite(STDERR, "SKIPPED: disposable MySQL is unavailable.\n");
    exit(3);
}
$admin->set_charset('utf8mb4');

$databaseCreated = false;
try {
    $admin->query("DROP DATABASE IF EXISTS `{$database}`");
    $admin->query(
        "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $databaseCreated = true;

    $clone = integration_run([
        'docker',
        'exec',
        $container,
        'sh',
        '-lc',
        'mysqldump -u' . escapeshellarg($user)
            . ' -p' . escapeshellarg($pass)
            . ' --no-data --routines --triggers '
            . escapeshellarg($sourceDatabase)
            . ' | mysql -u' . escapeshellarg($user)
            . ' -p' . escapeshellarg($pass) . ' '
            . escapeshellarg($database),
    ]);
    integration_assert(
        $clone['exit_code'] === 0,
        'Unable to clone disposable schema: ' . trim($clone['stderr'])
    );

    $conn = new mysqli($host, $user, $pass, $database, $port);
    integration_assert(!$conn->connect_error, 'Unable to connect to disposable database.');
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    $conn->query("
        INSERT INTO tracs_roles
          (id,name,slug,description,hierarchy_level,is_system_role)
        VALUES
          (9001,'Fixture Super Admin','super_admin','Phase 15 fixture',100,1),
          (9002,'Fixture Admin','admin','Phase 15 fixture',80,1)
    ");
    $conn->query("
        INSERT INTO tracs_permissions
          (id,permission_key,category,description)
        VALUES
          (9101,'shifts.view','Workforce Schedule','Phase 15 fixture'),
          (9102,'shifts.manage','Workforce Schedule','Phase 15 fixture')
    ");
    $conn->query("
        INSERT INTO tracs_role_permissions (role_id,permission_id)
        VALUES (9001,9101),(9001,9102),(9002,9101),(9002,9102)
    ");
    $conn->query("
        INSERT INTO tracs_divisions
          (id,name,code,description,status)
        VALUES (9201,'Phase 15 Operations','P15','Disposable fixture','active')
    ");
    $password = password_hash('Phase15-only-password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO tracs_users
          (id,email,password,name,username,role,is_active,status,division_id,role_id,
           two_factor_enabled,two_factor_reset_required)
        VALUES
          (9301,'phase15-super@tracs.test',?,'Phase 15 Super','phase15-super','admin',1,'active',9201,9001,1,0),
          (9302,'phase15-admin@tracs.test',?,'Phase 15 Admin','phase15-admin','admin',1,'active',9201,9002,1,0),
          (9303,'phase15-agent@tracs.test',?,'Phase 15 Agent','phase15-agent','operator',1,'active',9201,9002,1,0)
    ");
    integration_assert($stmt !== false, 'Unable to prepare fixture users.');
    $stmt->bind_param('sss', $password, $password, $password);
    integration_assert($stmt->execute(), 'Unable to seed fixture users.');
    $stmt->close();

    $conn->query("
        INSERT INTO shift_assignment_types
          (type_name,type_slug,count_as_work_hour,count_as_overtime,
           count_as_holiday_hour,color_label,is_active)
        VALUES
          ('Regular Shift','regular_shift',1,0,0,'#4f46e5',1),
          ('Lembur','lembur',1,1,0,'#f59e0b',1)
    ");
    $conn->query("
        INSERT INTO shift_templates
          (id,shift_name,start_time,end_time,duration_minutes,default_break_minutes,
           is_cross_day,color_label,default_assignment_type,count_as_work_hour,is_active)
        VALUES
          (9401,'Shift 3','16:00:00','00:00:00',480,0,1,'#4f46e5','regular_shift',1,1)
    ");
    $conn->query("
        INSERT INTO shift_workload_settings
          (division_id,weekly_target_minutes,max_weekly_minutes,max_daily_minutes,
           overtime_threshold_minutes,normal_working_days_per_week,
           minimum_rest_between_shifts_minutes,timeline_snap_minutes,minimum_shift_minutes)
        VALUES (9201,2400,2880,960,2700,5,480,15,60)
    ");

    $basePayload = [
        'agent_id' => 9303,
        'assignment_date' => '2026-07-06',
        'shift_type' => 'regular_shift',
        'shift_template_id' => 9401,
        'start_time' => '16:00',
        'end_time' => '24:00',
        'break_minutes' => 0,
        'status' => 'assigned',
        'notes' => 'Disposable Phase 15 integration fixture',
    ];

    $unauthenticated = integration_request('POST', 0, 'missing', [], $basePayload);
    integration_assert(($unauthenticated['success'] ?? null) === false, 'Unauthenticated create was accepted.');
    integration_assert($unauthenticated['_test_status'] === 401, 'Unauthenticated create did not return 401.');
    integration_assert(
        str_contains((string)($unauthenticated['message'] ?? ''), 'Authentication'),
        'Unauthenticated create did not return the expected denial.'
    );

    $missingCsrf = integration_request('POST', 9301, 'missing', [], $basePayload);
    integration_assert(($missingCsrf['success'] ?? null) === false, 'Missing CSRF was accepted.');
    integration_assert($missingCsrf['_test_status'] === 403, 'Missing CSRF did not return 403.');

    $invalidCsrf = integration_request('POST', 9301, 'invalid', [], $basePayload);
    integration_assert(($invalidCsrf['success'] ?? null) === false, 'Invalid CSRF was accepted.');
    integration_assert($invalidCsrf['_test_status'] === 403, 'Invalid CSRF did not return 403.');

    $wrongRole = integration_request('POST', 9302, 'valid', [], $basePayload);
    integration_assert(($wrongRole['success'] ?? null) === false, 'Non-Super Admin create was accepted.');
    integration_assert($wrongRole['_test_status'] === 403, 'Non-Super Admin create did not return 403.');

    $conn->query("DELETE FROM tracs_role_permissions WHERE role_id=9001 AND permission_id=9102");
    $missingPermission = integration_request('POST', 9301, 'valid', [], $basePayload);
    integration_assert(($missingPermission['success'] ?? null) === false, 'Missing shifts.manage was accepted.');
    integration_assert($missingPermission['_test_status'] === 403, 'Missing shifts.manage did not return 403.');
    $conn->query("INSERT INTO tracs_role_permissions (role_id,permission_id) VALUES (9001,9102)");

    $invalidPayload = integration_request('POST', 9301, 'valid', [], [
        'agent_id' => 9303,
        'assignment_date' => '06-07-2026',
        'shift_type' => 'regular_shift',
        'start_time' => '16:00',
        'end_time' => '24:00',
    ]);
    integration_assert(($invalidPayload['success'] ?? null) === false, 'Invalid payload was accepted.');
    integration_assert($invalidPayload['_test_status'] === 422, 'Invalid payload did not return 422.');
    $invalidFields = array_column($invalidPayload['errors'] ?? [], 'field');
    integration_assert(
        in_array('assignment_date', $invalidFields, true),
        'Invalid date did not return a field error: '
            . json_encode($invalidPayload, JSON_UNESCAPED_SLASHES)
    );

    $created = integration_request('POST', 9301, 'valid', [], $basePayload);
    integration_assert(
        ($created['success'] ?? null) === true,
        'Valid create did not succeed: ' . json_encode($created, JSON_UNESCAPED_SLASHES)
    );
    integration_assert($created['_test_status'] === 201, 'Valid create did not return 201.');
    $assignmentId = (int)($created['data']['assignment']['id'] ?? 0);
    integration_assert($assignmentId > 0, 'Valid create did not return an assignment ID.');
    integration_assert(
        ($created['data']['assignment']['display_range'] ?? '') === '16:00-24:00'
            && ($created['data']['assignment']['is_cross_day'] ?? null) === true,
        'Shift 3 cross-day response changed.'
    );
    integration_assert(
        integration_scalar($conn, "SELECT COUNT(*) FROM shift_assignments WHERE id={$assignmentId}") === '1',
        'Created assignment was not persisted.'
    );

    $read = integration_request('GET', 9301, 'valid', [
        'view' => 'daily',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-06',
        'agent_id' => '9303',
    ]);
    integration_assert(($read['success'] ?? null) === true, 'Read API failed after create.');
    integration_assert($read['_test_status'] === 200, 'Read API did not return 200.');
    $readIds = array_map(
        static fn(array $row): int => (int)($row['id'] ?? 0),
        $read['data']['assignments'] ?? []
    );
    integration_assert(in_array($assignmentId, $readIds, true), 'Created assignment is missing from GET.');

    $overlap = integration_request('POST', 9301, 'valid', [], $basePayload);
    integration_assert(($overlap['success'] ?? null) === false, 'Overlapping create was accepted.');
    integration_assert($overlap['_test_status'] === 409, 'Overlap did not return 409.');
    integration_assert(
        integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM shift_assignments
             WHERE user_id=9303 AND assignment_date='2026-07-06'"
        ) === '1',
        'Overlap attempt created a second assignment.'
    );

    $updatePayload = [
        'assignment_date' => '2026-07-07',
        'start_time' => '16:00',
        'end_time' => '24:00',
        'status' => 'confirmed',
    ];
    $updateQuery = ['id' => (string)$assignmentId];

    $updateUnauthenticated = integration_request(
        'PATCH',
        0,
        'missing',
        $updateQuery,
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateUnauthenticated['success'] ?? null) === false
            && $updateUnauthenticated['_test_status'] === 401,
        'Unauthenticated update did not return 401.'
    );
    $updateMissingCsrf = integration_request(
        'PATCH',
        9301,
        'missing',
        $updateQuery,
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateMissingCsrf['success'] ?? null) === false
            && $updateMissingCsrf['_test_status'] === 403,
        'Missing update CSRF did not return 403.'
    );
    $updateInvalidCsrf = integration_request(
        'PATCH',
        9301,
        'invalid',
        $updateQuery,
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateInvalidCsrf['success'] ?? null) === false
            && $updateInvalidCsrf['_test_status'] === 403,
        'Invalid update CSRF did not return 403.'
    );
    $updateWrongRole = integration_request(
        'PATCH',
        9302,
        'valid',
        $updateQuery,
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateWrongRole['success'] ?? null) === false
            && $updateWrongRole['_test_status'] === 403,
        'Non-Super Admin update did not return 403.'
    );

    $conn->query("DELETE FROM tracs_role_permissions WHERE role_id=9001 AND permission_id=9102");
    $updateMissingPermission = integration_request(
        'PATCH',
        9301,
        'valid',
        $updateQuery,
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateMissingPermission['success'] ?? null) === false
            && $updateMissingPermission['_test_status'] === 403,
        'Update without shifts.manage did not return 403.'
    );
    $conn->query("INSERT INTO tracs_role_permissions (role_id,permission_id) VALUES (9001,9102)");

    $updateMissingId = integration_request(
        'PATCH',
        9301,
        'valid',
        [],
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateMissingId['success'] ?? null) === false
            && $updateMissingId['_test_status'] === 422,
        'Update without an assignment ID did not return 422.'
    );
    $updateNotFound = integration_request(
        'PATCH',
        9301,
        'valid',
        ['id' => '99999999'],
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updateNotFound['success'] ?? null) === false
            && $updateNotFound['_test_status'] === 404,
        'Missing assignment update did not return 404.'
    );
    $updateInvalidPayload = integration_request(
        'PATCH',
        9301,
        'valid',
        $updateQuery,
        ['assignment_date' => '07-07-2026', 'source' => 'fixture'],
        'assignment'
    );
    integration_assert(
        ($updateInvalidPayload['success'] ?? null) === false
            && $updateInvalidPayload['_test_status'] === 422,
        'Invalid update payload did not return 422.'
    );

    $updated = integration_request(
        'PATCH',
        9301,
        'valid',
        $updateQuery,
        $updatePayload,
        'assignment'
    );
    integration_assert(
        ($updated['success'] ?? null) === true && $updated['_test_status'] === 200,
        'Valid update did not succeed: ' . json_encode($updated, JSON_UNESCAPED_SLASHES)
    );
    integration_assert(
        ($updated['data']['assignment']['id'] ?? 0) === $assignmentId
            && ($updated['data']['assignment']['assignment_date'] ?? '') === '2026-07-07'
            && ($updated['data']['assignment']['display_range'] ?? '') === '16:00-24:00'
            && ($updated['data']['assignment']['status'] ?? '') === 'confirmed',
        'Valid update response changed the Shift 3 or assignment contract.'
    );
    integration_assert(
        integration_scalar(
            $conn,
            "SELECT DATE_FORMAT(assignment_date,'%Y-%m-%d')
             FROM shift_assignments WHERE id={$assignmentId}"
        ) === '2026-07-07',
        'Valid update was not persisted.'
    );

    $updatedRead = integration_request('GET', 9301, 'valid', [
        'view' => 'daily',
        'start_date' => '2026-07-07',
        'end_date' => '2026-07-07',
        'agent_id' => '9303',
    ]);
    $updatedReadIds = array_map(
        static fn(array $row): int => (int)($row['id'] ?? 0),
        $updatedRead['data']['assignments'] ?? []
    );
    integration_assert(
        ($updatedRead['success'] ?? null) === true
            && in_array($assignmentId, $updatedReadIds, true),
        'Updated assignment is missing from GET.'
    );

    $blocking = integration_request('POST', 9301, 'valid', [], [
        'agent_id' => 9303,
        'assignment_date' => '2026-07-08',
        'shift_type' => 'regular_shift',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'status' => 'assigned',
    ]);
    integration_assert(($blocking['success'] ?? null) === true, 'Unable to create overlap fixture.');
    $conflict = integration_request(
        'PATCH',
        9301,
        'valid',
        $updateQuery,
        [
            'assignment_date' => '2026-07-08',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ],
        'assignment'
    );
    integration_assert(
        ($conflict['success'] ?? null) === false && $conflict['_test_status'] === 409,
        'Overlapping update did not return 409.'
    );
    integration_assert(
        integration_scalar(
            $conn,
            "SELECT DATE_FORMAT(assignment_date,'%Y-%m-%d')
             FROM shift_assignments WHERE id={$assignmentId}"
        ) === '2026-07-07',
        'Conflict attempt mutated the assignment.'
    );

    integration_assert(
        (int)integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM assignment_audit_logs
             WHERE assignment_id={$assignmentId} AND action='created'"
        ) >= 1,
        'Assignment audit success record is missing.'
    );
    integration_assert(
        (int)integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM tracs_user_activity_logs
             WHERE actor_user_id=9301 AND action='shift_assignment.create'
               AND target_id={$assignmentId}"
        ) >= 1,
        'Phase 5 create activity audit record is missing.'
    );
    integration_assert(
        (int)integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM assignment_audit_logs
             WHERE assignment_id={$assignmentId} AND action='updated'
               AND before_snapshot IS NOT NULL AND after_snapshot IS NOT NULL"
        ) >= 1,
        'Assignment update before/after audit record is missing.'
    );
    integration_assert(
        (int)integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM tracs_user_activity_logs
             WHERE actor_user_id=9301 AND action='shift_assignment.update'
               AND target_id={$assignmentId}
               AND before_data IS NOT NULL AND after_data IS NOT NULL"
        ) >= 1,
        'Controlled update activity audit record is missing.'
    );
    integration_assert(
        (int)integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM tracs_auth_events
             WHERE event_type IN ('csrf_validation_failed','permission_denied')"
        ) >= 3,
        'Security denials were not audit logged.'
    );

    if ($includeDelete) {
        $deleteQuery = ['id' => (string)$assignmentId];
        $deleteUnauthenticated = integration_request(
            'DELETE',
            0,
            'missing',
            $deleteQuery,
            [],
            'assignment'
        );
        integration_assert(
            ($deleteUnauthenticated['success'] ?? null) === false
                && $deleteUnauthenticated['_test_status'] === 401,
            'Unauthenticated delete did not return 401.'
        );
        $deleteMissingCsrf = integration_request(
            'DELETE',
            9301,
            'missing',
            $deleteQuery,
            [],
            'assignment'
        );
        integration_assert(
            ($deleteMissingCsrf['success'] ?? null) === false
                && $deleteMissingCsrf['_test_status'] === 403,
            'Missing delete CSRF did not return 403.'
        );
        $deleteInvalidCsrf = integration_request(
            'DELETE',
            9301,
            'invalid',
            $deleteQuery,
            [],
            'assignment'
        );
        integration_assert(
            ($deleteInvalidCsrf['success'] ?? null) === false
                && $deleteInvalidCsrf['_test_status'] === 403,
            'Invalid delete CSRF did not return 403.'
        );
        $deleteWrongRole = integration_request(
            'DELETE',
            9302,
            'valid',
            $deleteQuery,
            [],
            'assignment'
        );
        integration_assert(
            ($deleteWrongRole['success'] ?? null) === false
                && $deleteWrongRole['_test_status'] === 403,
            'Non-Super Admin delete did not return 403.'
        );

        $conn->query("DELETE FROM tracs_role_permissions WHERE role_id=9001 AND permission_id=9102");
        $deleteMissingPermission = integration_request(
            'DELETE',
            9301,
            'valid',
            $deleteQuery,
            [],
            'assignment'
        );
        integration_assert(
            ($deleteMissingPermission['success'] ?? null) === false
                && $deleteMissingPermission['_test_status'] === 403,
            'Delete without shifts.manage did not return 403.'
        );
        $conn->query("INSERT INTO tracs_role_permissions (role_id,permission_id) VALUES (9001,9102)");

        $deleteMissingId = integration_request(
            'DELETE',
            9301,
            'valid',
            [],
            [],
            'assignment'
        );
        integration_assert(
            ($deleteMissingId['success'] ?? null) === false
                && $deleteMissingId['_test_status'] === 422,
            'Delete without an assignment ID did not return 422.'
        );
        $deleteNotFound = integration_request(
            'DELETE',
            9301,
            'valid',
            ['id' => '99999999'],
            [],
            'assignment'
        );
        integration_assert(
            ($deleteNotFound['success'] ?? null) === false
                && $deleteNotFound['_test_status'] === 404,
            'Missing assignment delete did not return 404.'
        );

        $protected = integration_request('POST', 9301, 'valid', [], [
            'agent_id' => 9303,
            'assignment_date' => '2026-07-09',
            'shift_type' => 'regular_shift',
            'start_time' => '00:00',
            'end_time' => '08:00',
            'status' => 'assigned',
        ]);
        integration_assert(($protected['success'] ?? null) === true, 'Unable to create protected delete fixture.');
        $protectedId = (int)($protected['data']['assignment']['id'] ?? 0);
        $conn->query(
            "UPDATE shift_assignments
             SET source='monthly_template',monthly_template_id=88001
             WHERE id={$protectedId}"
        );
        $protectedDelete = integration_request(
            'DELETE',
            9301,
            'valid',
            ['id' => (string)$protectedId],
            [],
            'assignment'
        );
        integration_assert(
            ($protectedDelete['success'] ?? null) === false
                && $protectedDelete['_test_status'] === 409,
            'Template-owned assignment delete was not blocked.'
        );
        integration_assert(
            integration_scalar($conn, "SELECT COUNT(*) FROM shift_assignments WHERE id={$protectedId}") === '1',
            'Protected assignment was deleted.'
        );

        $conn->query("
            INSERT INTO shift_warnings
              (shift_assignment_id,user_id,affected_date,warning_type,warning_message,severity)
            VALUES
              ({$assignmentId},9303,'2026-07-07','jumpshift','Disposable delete warning','warning')
        ");
        $deleted = integration_request(
            'DELETE',
            9301,
            'valid',
            $deleteQuery,
            [],
            'assignment'
        );
        integration_assert(
            ($deleted['success'] ?? null) === true
                && $deleted['_test_status'] === 200
                && (int)($deleted['data']['assignment_id'] ?? 0) === $assignmentId,
            'Valid delete did not succeed: ' . json_encode($deleted, JSON_UNESCAPED_SLASHES)
        );
        integration_assert(
            integration_scalar($conn, "SELECT COUNT(*) FROM shift_assignments WHERE id={$assignmentId}") === '0',
            'Deleted assignment still exists.'
        );
        integration_assert(
            integration_scalar($conn, "SELECT COUNT(*) FROM shift_warnings WHERE shift_assignment_id={$assignmentId}") === '0',
            'Deleted assignment warning was not cleaned up.'
        );

        $deletedRead = integration_request('GET', 9301, 'valid', [
            'view' => 'daily',
            'start_date' => '2026-07-07',
            'end_date' => '2026-07-07',
            'agent_id' => '9303',
        ]);
        $deletedReadIds = array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $deletedRead['data']['assignments'] ?? []
        );
        integration_assert(
            ($deletedRead['success'] ?? null) === true
                && !in_array($assignmentId, $deletedReadIds, true),
            'Deleted assignment remains visible through GET.'
        );
        integration_assert(
            (int)integration_scalar(
                $conn,
                "SELECT COUNT(*) FROM assignment_audit_logs
                 WHERE assignment_id IS NULL AND action='deleted'
                   AND JSON_EXTRACT(before_snapshot,'$.id')={$assignmentId}"
            ) >= 1,
            'Before-delete assignment audit snapshot is missing.'
        );
        integration_assert(
            (int)integration_scalar(
                $conn,
                "SELECT COUNT(*) FROM tracs_user_activity_logs
                 WHERE actor_user_id=9301 AND action='shift_assignment.delete'
                   AND target_id={$assignmentId}
                   AND before_data IS NOT NULL"
            ) >= 1,
            'Controlled delete activity audit is missing.'
        );

        if ($includeRestore) {
            $auditResult = $conn->query(
                "SELECT id,before_snapshot
                 FROM assignment_audit_logs
                 WHERE assignment_id IS NULL AND action='deleted'
                   AND JSON_EXTRACT(before_snapshot,'$.id')={$assignmentId}
                 ORDER BY id DESC LIMIT 1"
            );
            integration_assert(
                $auditResult instanceof mysqli_result && $auditResult->num_rows === 1,
                'Delete audit snapshot could not be loaded for restoration.'
            );
            $auditRow = $auditResult->fetch_assoc();
            $auditResult->free();
            $deleteAuditId = (int)($auditRow['id'] ?? 0);
            $snapshot = json_decode(
                (string)($auditRow['before_snapshot'] ?? ''),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            integration_assert(
                $deleteAuditId > 0 && is_array($snapshot),
                'Delete audit snapshot is invalid.'
            );

            integration_restore_assignment($conn, $snapshot, 9301, $deleteAuditId);

            $restoredRead = integration_request('GET', 9301, 'valid', [
                'view' => 'daily',
                'start_date' => '2026-07-07',
                'end_date' => '2026-07-07',
                'agent_id' => '9303',
            ]);
            $restoredRows = array_values(array_filter(
                $restoredRead['data']['assignments'] ?? [],
                static fn(array $row): bool => (int)($row['id'] ?? 0) === $assignmentId
            ));
            integration_assert(
                ($restoredRead['success'] ?? null) === true
                    && count($restoredRows) === 1,
                'Exactly restored assignment is missing or duplicated in GET.'
            );

            $restoredResult = $conn->query(
                "SELECT * FROM shift_assignments WHERE id={$assignmentId} LIMIT 1"
            );
            integration_assert(
                $restoredResult instanceof mysqli_result
                    && $restoredResult->num_rows === 1,
                'Restored assignment row is missing.'
            );
            $restored = $restoredResult->fetch_assoc();
            $restoredResult->free();
            foreach ([
                'id',
                'user_id',
                'division_id',
                'shift_template_id',
                'assignment_date',
                'start_datetime',
                'end_datetime',
                'is_cross_day',
                'break_minutes',
                'calculated_duration_minutes',
                'assignment_type',
                'status',
                'is_overtime',
                'is_holiday_assignment',
                'is_manual_duration_override',
                'approval_status',
                'source',
                'monthly_template_id',
                'approved_by',
                'approved_at',
                'notes',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ] as $field) {
                integration_assert(
                    (string)($restored[$field] ?? '') === (string)($snapshot[$field] ?? ''),
                    "Restored assignment field {$field} does not match the snapshot."
                );
            }
            integration_assert(
                integration_scalar(
                    $conn,
                    "SELECT COUNT(*) FROM shift_assignments WHERE id={$assignmentId}"
                ) === '1',
                'Exact restoration created an unintended duplicate.'
            );
            integration_assert(
                (int)integration_scalar(
                    $conn,
                    "SELECT COUNT(*) FROM tracs_user_activity_logs
                     WHERE actor_user_id=9301
                       AND action='shift_assignment.restore'
                       AND target_id={$assignmentId}
                       AND after_data IS NOT NULL
                       AND reason LIKE '%{$deleteAuditId}%'"
                ) === 1,
                'Exact restoration audit is missing or duplicated.'
            );
        }

        $auditFailureFixture = integration_request('POST', 9301, 'valid', [], [
            'agent_id' => 9303,
            'assignment_date' => '2026-07-10',
            'shift_type' => 'regular_shift',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'assigned',
        ]);
        integration_assert(
            ($auditFailureFixture['success'] ?? null) === true,
            'Unable to create audit-failure delete fixture.'
        );
        $auditFailureId = (int)($auditFailureFixture['data']['assignment']['id'] ?? 0);
        $conn->query('DROP TABLE assignment_audit_logs');
        $auditFailureDelete = integration_request(
            'DELETE',
            9301,
            'valid',
            ['id' => (string)$auditFailureId],
            [],
            'assignment'
        );
        integration_assert(
            ($auditFailureDelete['success'] ?? null) === false
                && $auditFailureDelete['_test_status'] === 500,
            'Delete did not fail closed when assignment audit storage was unavailable.'
        );
        integration_assert(
            integration_scalar(
                $conn,
                "SELECT COUNT(*) FROM shift_assignments WHERE id={$auditFailureId}"
            ) === '1',
            'Assignment was deleted without its required before-delete audit.'
        );
    }

    $conn->close();
    echo "TRACS Shift Assignment disposable DB integration checks passed.\n";
} finally {
    if ($databaseCreated) {
        $admin->query("DROP DATABASE IF EXISTS `{$database}`");
    }
    $stillExists = $admin->query(
        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
         WHERE SCHEMA_NAME='" . $admin->real_escape_string($database) . "'"
    );
    if ($stillExists instanceof mysqli_result && $stillExists->num_rows > 0) {
        fwrite(STDERR, "Disposable database cleanup failed.\n");
        $stillExists->free();
        $admin->close();
        exit(5);
    }
    if ($stillExists instanceof mysqli_result) {
        $stillExists->free();
    }
    $admin->close();
}
