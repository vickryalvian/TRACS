<?php
declare(strict_types=1);

function commit_integration_fail(string $message): never
{
    throw new RuntimeException($message);
}

function commit_integration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        commit_integration_fail($message);
    }
}

function commit_integration_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function commit_integration_safe_database(string $database): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1
        && preg_match('/(?:test|local|dev|disposable|staging)/i', $database) === 1;
}

function commit_integration_run(array $command): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        commit_integration_fail('Unable to start integration subprocess.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [
        'exit_code' => proc_close($process),
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

function commit_integration_request(
    string $method,
    int $userId,
    string $csrfMode,
    array $body,
    string $resource = 'templates/commit',
    array $query = []
): array {
    $result = commit_integration_run([
        PHP_BINARY,
        __DIR__ . '/fixtures/shift-assignment-api-request.php',
        $method,
        (string)$userId,
        $csrfMode,
        json_encode($query, JSON_THROW_ON_ERROR),
        base64_encode(json_encode($body, JSON_THROW_ON_ERROR)),
        $resource,
    ]);
    commit_integration_assert(
        $result['exit_code'] === 0,
        'Commit API harness failed: ' . trim($result['stderr'])
    );
    preg_match('/PHASE15_STATUS:(\d+)/', $result['stderr'], $statusMatch);
    $status = isset($statusMatch[1]) ? (int)$statusMatch[1] : 0;
    commit_integration_assert($status > 0, 'Commit API harness did not report a status.');
    $payload = json_decode(trim($result['stdout']), true);
    commit_integration_assert(
        is_array($payload),
        'Commit API returned invalid JSON: ' . trim($result['stdout'])
    );
    $payload['_test_status'] = $status;
    return $payload;
}

function commit_integration_scalar(mysqli $conn, string $sql): mixed
{
    $result = $conn->query($sql);
    commit_integration_assert($result instanceof mysqli_result, 'Query failed: ' . $sql);
    $row = $result->fetch_row();
    $result->free();
    return $row[0] ?? null;
}

function commit_integration_counts(mysqli $conn): array
{
    $counts = [];
    foreach ([
        'shift_assignments',
        'shift_warnings',
        'holiday_coverage_assignments',
        'shift_monthly_templates',
        'shift_monthly_template_items',
        'assignment_audit_logs',
        'tracs_user_activity_logs',
    ] as $table) {
        $counts[$table] = (int)commit_integration_scalar($conn, "SELECT COUNT(*) FROM `{$table}`");
    }
    return $counts;
}

function commit_integration_payload(array $dates): array
{
    $items = [];
    $templates = [
        ['date' => $dates[0], 'id' => 9401, 'start' => '00:00', 'end' => '08:00'],
        ['date' => $dates[1], 'id' => 9402, 'start' => '08:00', 'end' => '16:00'],
        ['date' => $dates[2], 'id' => 9403, 'start' => '16:00', 'end' => '24:00'],
    ];
    foreach ($templates as $template) {
        $items[] = [
            'date' => $template['date'],
            'shift_template_id' => $template['id'],
            'shift_type' => 'regular_shift',
            'start_time' => $template['start'],
            'end_time' => $template['end'],
        ];
    }

    return [
        'preview_payload' => [
            'start_date' => $dates[0],
            'end_date' => $dates[2],
            'agents' => [9303],
            'pattern' => [
                'type' => 'weekly_rotation',
                'items' => $items,
            ],
            'options' => [
                'include_holidays' => true,
                'include_warnings' => true,
                'strict_conflict_check' => true,
            ],
        ],
        'confirmation' => 'APPLY TEMPLATE',
        'options' => [
            'conflict_policy' => 'block',
        ],
    ];
}

$environment = strtolower(commit_integration_env('TRACS_ENV'));
$allowMutations = commit_integration_env('TRACS_ALLOW_MUTATION_TESTS');
$database = commit_integration_env('TRACS_TEST_DB_NAME', 'tracs_phase32_test');
$sourceDatabase = commit_integration_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = commit_integration_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)commit_integration_env('TRACS_TEST_DB_PORT', '3307');
$user = commit_integration_env('TRACS_TEST_DB_USER', 'root');
$pass = commit_integration_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = commit_integration_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');

if ($environment !== 'test') {
    fwrite(STDERR, "SKIPPED: TRACS_ENV must be exactly test.\n");
    exit(3);
}
if ($allowMutations !== '1') {
    fwrite(STDERR, "SKIPPED: TRACS_ALLOW_MUTATION_TESTS=1 is required.\n");
    exit(3);
}
if (!commit_integration_safe_database($database)
    || !preg_match('/^[A-Za-z0-9_]+$/', $sourceDatabase)
    || $database === $sourceDatabase
    || in_array($environment, ['prod', 'production'], true)) {
    fwrite(STDERR, "REFUSED: unsafe disposable database configuration.\n");
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
    $admin->query("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $databaseCreated = true;

    $clone = commit_integration_run([
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
    commit_integration_assert(
        $clone['exit_code'] === 0,
        'Unable to clone disposable schema: ' . trim($clone['stderr'])
    );

    $conn = new mysqli($host, $user, $pass, $database, $port);
    commit_integration_assert(!$conn->connect_error, 'Unable to connect to disposable database.');
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    $conn->query("
        INSERT INTO tracs_roles
          (id,name,slug,description,hierarchy_level,is_system_role)
        VALUES
          (9001,'Fixture Super Admin','super_admin','Phase 32 fixture',100,1),
          (9002,'Fixture Admin','admin','Phase 32 fixture',80,1)
    ");
    $conn->query("
        INSERT INTO tracs_permissions
          (id,permission_key,category,description)
        VALUES
          (9101,'shifts.view','Workforce Schedule','Phase 32 fixture'),
          (9102,'shifts.manage','Workforce Schedule','Phase 32 fixture')
    ");
    $conn->query("
        INSERT INTO tracs_role_permissions (role_id,permission_id)
        VALUES (9001,9101),(9001,9102),(9002,9101),(9002,9102)
    ");
    $conn->query("
        INSERT INTO tracs_divisions
          (id,name,code,description,status)
        VALUES (9201,'Phase 32 Operations','P32','Disposable fixture','active')
    ");
    $password = password_hash('Phase32-only-password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO tracs_users
          (id,email,password,name,username,role,is_active,status,division_id,role_id,
           two_factor_enabled,two_factor_reset_required)
        VALUES
          (9301,'phase32-super@tracs.test',?,'Phase 32 Super','phase32-super','admin',1,'active',9201,9001,1,0),
          (9302,'phase32-admin@tracs.test',?,'Phase 32 Admin','phase32-admin','admin',1,'active',9201,9002,1,0),
          (9303,'phase32-agent@tracs.test',?,'Phase 32 Agent','phase32-agent','operator',1,'active',9201,9002,1,0)
    ");
    commit_integration_assert($stmt instanceof mysqli_stmt, 'Unable to prepare fixture users.');
    $stmt->bind_param('sss', $password, $password, $password);
    commit_integration_assert($stmt->execute(), 'Unable to seed fixture users.');
    $stmt->close();

    $conn->query("
        INSERT INTO shift_assignment_types
          (id,type_name,type_slug,count_as_work_hour,count_as_overtime,
           count_as_holiday_hour,color_label,is_active)
        VALUES
          (9501,'Regular Shift','regular_shift',1,0,0,'#4f46e5',1),
          (9502,'Lembur','lembur',1,1,0,'#f59e0b',1)
    ");
    $conn->query("
        INSERT INTO shift_templates
          (id,shift_name,start_time,end_time,duration_minutes,default_break_minutes,
           is_cross_day,color_label,default_assignment_type,count_as_work_hour,is_active)
        VALUES
          (9401,'Shift 1','00:00:00','08:00:00',480,0,0,'#4f46e5','regular_shift',1,1),
          (9402,'Shift 2','08:00:00','16:00:00',480,0,0,'#0ea5e9','regular_shift',1,1),
          (9403,'Shift 3','16:00:00','00:00:00',480,0,1,'#8b5cf6','regular_shift',1,1)
    ");
    $conn->query("
        INSERT INTO shift_workload_settings
          (division_id,weekly_target_minutes,max_weekly_minutes,max_daily_minutes,
           overtime_threshold_minutes,normal_working_days_per_week,
           minimum_rest_between_shifts_minutes,timeline_snap_minutes,minimum_shift_minutes)
        VALUES (9201,2400,2880,960,2700,5,480,15,60)
    ");
    $conn->query("
        INSERT INTO public_holidays
          (id,holiday_date,holiday_name,holiday_type,is_active)
        VALUES (9601,'2026-08-06','Phase 32 Holiday','custom',1)
    ");
    $conn->query("
        INSERT INTO shift_assignments
          (id,user_id,division_id,shift_template_id,assignment_date,start_datetime,
           end_datetime,is_cross_day,break_minutes,calculated_duration_minutes,
           assignment_type,status,is_overtime,is_holiday_assignment,approval_status,
           source,created_by,updated_by)
        VALUES
          (9701,9303,9201,9401,'2026-08-01','2026-08-01 00:00:00',
           '2026-08-01 08:00:00',0,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301)
    ");

    $validBody = commit_integration_payload(['2026-08-02', '2026-08-03', '2026-08-04']);
    $conflictBody = commit_integration_payload(['2026-08-01', '2026-08-03', '2026-08-04']);

    foreach ([
        ['POST', 0, 'missing', $validBody, 401, 'Unauthenticated commit did not return 401.'],
        ['POST', 9301, 'missing', $validBody, 403, 'Missing CSRF commit did not return 403.'],
        ['POST', 9301, 'invalid', $validBody, 403, 'Invalid CSRF commit did not return 403.'],
        ['POST', 9302, 'valid', $validBody, 403, 'Non-Super Admin commit did not return 403.'],
    ] as [$method, $userId, $csrf, $body, $status, $message]) {
        $response = commit_integration_request($method, $userId, $csrf, $body);
        commit_integration_assert(
            ($response['success'] ?? null) === false && $response['_test_status'] === $status,
            $message
        );
    }

    foreach ([
        ['', 'Missing confirmation did not return 422.'],
        ['apply template', 'Lowercase confirmation did not return 422.'],
        ['Apply Template', 'Case-variant confirmation did not return 422.'],
        [' APPLY TEMPLATE ', 'Whitespace confirmation did not return 422.'],
        ['APPLY  TEMPLATE', 'Double-space confirmation did not return 422.'],
        ['APPLY-TEMPLATE', 'Hyphenated confirmation did not return 422.'],
    ] as [$confirmation, $message]) {
        $body = $validBody;
        if ($confirmation === '') {
            unset($body['confirmation']);
        } else {
            $body['confirmation'] = $confirmation;
        }
        $response = commit_integration_request('POST', 9301, 'valid', $body);
        commit_integration_assert(
            ($response['success'] ?? null) === false && $response['_test_status'] === 422,
            $message
        );
    }

    $invalidBody = $validBody;
    $invalidBody['preview_payload']['start_date'] = '02-08-2026';
    $invalid = commit_integration_request('POST', 9301, 'valid', $invalidBody);
    commit_integration_assert(
        ($invalid['success'] ?? null) === false && $invalid['_test_status'] === 422,
        'Invalid commit payload did not return 422.'
    );

    $overwriteBody = $validBody;
    $overwriteBody['options']['conflict_policy'] = 'overwrite';
    $overwrite = commit_integration_request('POST', 9301, 'valid', $overwriteBody);
    commit_integration_assert(
        ($overwrite['success'] ?? null) === false && $overwrite['_test_status'] === 422,
        'Unsupported conflict policy did not return 422.'
    );

    $beforeConflictCount = (int)commit_integration_scalar($conn, 'SELECT COUNT(*) FROM shift_assignments');
    $conflict = commit_integration_request('POST', 9301, 'valid', $conflictBody);
    $afterConflictCount = (int)commit_integration_scalar($conn, 'SELECT COUNT(*) FROM shift_assignments');
    commit_integration_assert(
        ($conflict['success'] ?? null) === false && $conflict['_test_status'] === 409,
        'Conflict commit did not return 409.'
    );
    commit_integration_assert(
        $beforeConflictCount === $afterConflictCount,
        'Conflict commit created assignments.'
    );

    $beforePreview = commit_integration_counts($conn);
    $previewResponse = commit_integration_request(
        'POST',
        9301,
        'valid',
        $validBody['preview_payload'],
        'templates/preview'
    );
    $afterPreview = commit_integration_counts($conn);
    commit_integration_assert(
        ($previewResponse['success'] ?? null) === true
            && $previewResponse['_test_status'] === 200
            && $beforePreview === $afterPreview,
        'Preview API mutated data after commit endpoint addition.'
    );

    $raceBody = commit_integration_payload(['2026-08-10', '2026-08-11', '2026-08-12']);
    $racePreviewCounts = commit_integration_counts($conn);
    $racePreview = commit_integration_request(
        'POST',
        9301,
        'valid',
        $raceBody['preview_payload'],
        'templates/preview'
    );
    commit_integration_assert(
        ($racePreview['success'] ?? null) === true
            && $racePreview['_test_status'] === 200
            && commit_integration_counts($conn) === $racePreviewCounts,
        'Race setup preview did not remain non-mutating.'
    );
    $conn->query("
        INSERT INTO shift_assignments
          (id,user_id,division_id,shift_template_id,assignment_date,start_datetime,
           end_datetime,is_cross_day,break_minutes,calculated_duration_minutes,
           assignment_type,status,is_overtime,is_holiday_assignment,approval_status,
           source,created_by,updated_by)
        VALUES
          (9702,9303,9201,9401,'2026-08-10','2026-08-10 00:00:00',
           '2026-08-10 08:00:00',0,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301)
    ");
    $beforeRaceCommitCount = (int)commit_integration_scalar($conn, 'SELECT COUNT(*) FROM shift_assignments');
    $raceConflict = commit_integration_request('POST', 9301, 'valid', $raceBody);
    $afterRaceCommitCount = (int)commit_integration_scalar($conn, 'SELECT COUNT(*) FROM shift_assignments');
    commit_integration_assert(
        ($raceConflict['success'] ?? null) === false && $raceConflict['_test_status'] === 409,
        'Race conflict commit did not return 409.'
    );
    commit_integration_assert(
        $beforeRaceCommitCount === $afterRaceCommitCount,
        'Race conflict commit created assignments.'
    );
    commit_integration_assert(
        (int)commit_integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM shift_assignments
             WHERE assignment_date BETWEEN '2026-08-10' AND '2026-08-12'
               AND source='monthly_template'"
        ) === 0,
        'Race conflict left template-created assignments behind.'
    );

    $commit = commit_integration_request('POST', 9301, 'valid', $validBody);
    commit_integration_assert(
        ($commit['success'] ?? null) === true && $commit['_test_status'] === 201,
        'Valid template commit failed: ' . json_encode($commit, JSON_UNESCAPED_SLASHES)
    );
    $ids = array_values(array_map('intval', $commit['data']['created_assignment_ids'] ?? []));
    commit_integration_assert(count($ids) === 3, 'Template commit did not create three assignments.');
    commit_integration_assert(
        $ids === array_values(array_map('intval', $commit['data']['rollback']['ids'] ?? [])),
        'Rollback ids do not match created assignment ids.'
    );
    $idList = implode(',', $ids);
    commit_integration_assert(
        (int)commit_integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM shift_assignments WHERE id IN ({$idList}) AND source='monthly_template'"
        ) === 3,
        'Created assignments were not stored as template-created rows.'
    );

    $get = commit_integration_request(
        'GET',
        9301,
        'valid',
        [],
        'assignments',
        [
            'view' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]
    );
    commit_integration_assert(
        ($get['success'] ?? null) === true
            && count(array_intersect(
                $ids,
                array_map(static fn(array $row): int => (int)$row['id'], $get['data']['assignments'] ?? [])
            )) === 3,
        'Committed assignments did not appear in GET assignments.'
    );

    $audit = $conn->query("
        SELECT after_data FROM tracs_user_activity_logs
        WHERE action='shift_assignment.template.commit'
        ORDER BY id DESC LIMIT 1
    ");
    commit_integration_assert($audit instanceof mysqli_result && $audit->num_rows === 1, 'Commit audit was not written.');
    $auditData = json_decode((string)($audit->fetch_assoc()['after_data'] ?? ''), true);
    $audit->free();
    commit_integration_assert(
        is_array($auditData)
            && array_values(array_map('intval', $auditData['created_assignment_ids'] ?? [])) === $ids,
        'Commit audit does not include created assignment ids.'
    );
    commit_integration_assert(
        (int)($auditData['generated_assignments_count'] ?? 0) === 3
            && array_values(array_map('intval', $auditData['rollback']['ids'] ?? [])) === $ids,
        'Commit audit does not include rollback targeting evidence.'
    );

    $rollbackAudit = $conn->prepare("
        INSERT INTO tracs_user_activity_logs
          (actor_user_id, target_type, target_id, action, before_data, after_data, reason, ip_address, user_agent, created_at)
        VALUES (9301, 'shift_assignment_template', NULL, 'shift_assignment.template.rollback_cleanup',
          NULL, ?, 'Disposable rollback targeting drill cleanup.', '127.0.0.1',
          'TRACS Phase 33 Integration Test', NOW())
    ");
    commit_integration_assert($rollbackAudit instanceof mysqli_stmt, 'Unable to prepare rollback cleanup audit.');
    $rollbackAuditJson = json_encode([
        'created_assignment_ids' => $ids,
        'unrelated_assignment_id' => 9701,
        'cleanup_type' => 'created_assignment_ids',
    ], JSON_UNESCAPED_SLASHES);
    commit_integration_assert(is_string($rollbackAuditJson), 'Unable to serialize rollback cleanup audit.');
    $rollbackAudit->bind_param('s', $rollbackAuditJson);
    commit_integration_assert($rollbackAudit->execute(), 'Unable to write rollback cleanup audit.');
    $rollbackAudit->close();
    $conn->query("DELETE FROM shift_assignments WHERE id IN ({$idList})");
    commit_integration_assert(
        (int)commit_integration_scalar($conn, "SELECT COUNT(*) FROM shift_assignments WHERE id IN ({$idList})") === 0,
        'Rollback cleanup did not remove committed assignment ids.'
    );
    commit_integration_assert(
        (int)commit_integration_scalar($conn, 'SELECT COUNT(*) FROM shift_assignments WHERE id=9701') === 1,
        'Rollback cleanup removed unrelated assignment.'
    );
    commit_integration_assert(
        (int)commit_integration_scalar($conn, 'SELECT COUNT(*) FROM shift_assignments WHERE id=9702') === 1,
        'Rollback cleanup removed race-conflict baseline assignment.'
    );
    commit_integration_assert(
        (int)commit_integration_scalar(
            $conn,
            "SELECT COUNT(*) FROM tracs_user_activity_logs
             WHERE action='shift_assignment.template.rollback_cleanup'
               AND after_data LIKE '%created_assignment_ids%'"
        ) === 1,
        'Rollback cleanup audit was not written.'
    );

    echo "TRACS Shift Assignment template commit disposable integration checks passed.\n";
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    if ($databaseCreated) {
        $cleanup = new mysqli($host, $user, $pass, '', $port);
        if (!$cleanup->connect_error) {
            $cleanup->query("DROP DATABASE IF EXISTS `{$database}`");
            $cleanup->close();
        }
    }
    $admin->close();
}
