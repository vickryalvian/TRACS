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
        && preg_match('/(?:test|local|dev|disposable)/i', $database) === 1;
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
    array $body = []
): array {
    $command = [
        PHP_BINARY,
        __DIR__ . '/fixtures/shift-assignment-api-request.php',
        $method,
        (string)$userId,
        $csrfMode,
        json_encode($query, JSON_THROW_ON_ERROR),
        base64_encode($body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR)),
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

$environment = strtolower(integration_env('TRACS_ENV'));
$allowMutations = integration_env('TRACS_ALLOW_MUTATION_TESTS');
$database = integration_env('TRACS_TEST_DB_NAME', 'tracs_phase15_test');
$sourceDatabase = integration_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = integration_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)integration_env('TRACS_TEST_DB_PORT', '3307');
$user = integration_env('TRACS_TEST_DB_USER', 'root');
$pass = integration_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = integration_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');

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
            "SELECT COUNT(*) FROM tracs_auth_events
             WHERE event_type IN ('csrf_validation_failed','permission_denied')"
        ) >= 3,
        'Security denials were not audit logged.'
    );

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
