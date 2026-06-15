<?php
declare(strict_types=1);

function preview_integration_fail(string $message): never
{
    throw new RuntimeException($message);
}

function preview_integration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        preview_integration_fail($message);
    }
}

function preview_integration_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function preview_integration_safe_database(string $database): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1
        && preg_match('/(?:test|local|dev|disposable|staging)/i', $database) === 1;
}

function preview_integration_run(array $command): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        preview_integration_fail('Unable to start integration subprocess.');
    }
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

function preview_integration_request(
    string $method,
    int $userId,
    string $csrfMode,
    array $body
): array {
    $result = preview_integration_run([
        PHP_BINARY,
        __DIR__ . '/fixtures/shift-assignment-api-request.php',
        $method,
        (string)$userId,
        $csrfMode,
        '{}',
        base64_encode(json_encode($body, JSON_THROW_ON_ERROR)),
        'templates/preview',
    ]);
    preview_integration_assert(
        $result['exit_code'] === 0,
        'Preview API harness failed: ' . trim($result['stderr'])
    );
    preg_match('/PHASE15_STATUS:(\d+)/', $result['stderr'], $statusMatch);
    $status = isset($statusMatch[1]) ? (int)$statusMatch[1] : 0;
    preview_integration_assert($status > 0, 'Preview API harness did not report a status.');
    $payload = json_decode(trim($result['stdout']), true);
    preview_integration_assert(
        is_array($payload),
        'Preview API returned invalid JSON: ' . trim($result['stdout'])
    );
    $payload['_test_status'] = $status;
    return $payload;
}

function preview_integration_counts(mysqli $conn): array
{
    $tables = [
        'shift_assignments',
        'shift_warnings',
        'holiday_coverage_assignments',
        'shift_monthly_templates',
        'shift_monthly_template_items',
        'assignment_audit_logs',
    ];
    $counts = [];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) FROM `{$table}`");
        preview_integration_assert($result instanceof mysqli_result, "Unable to count {$table}.");
        $row = $result->fetch_row();
        $result->free();
        $counts[$table] = (int)($row[0] ?? 0);
    }
    return $counts;
}

function preview_integration_payload(): array
{
    return [
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
        'agents' => [9303],
        'pattern' => [
            'type' => 'weekly_rotation',
            'items' => [
                [
                    'date' => '2026-07-01',
                    'shift_template_id' => 9401,
                    'shift_type' => 'regular_shift',
                    'start_time' => '00:00',
                    'end_time' => '08:00',
                ],
                [
                    'date' => '2026-07-02',
                    'shift_template_id' => 9402,
                    'shift_type' => 'regular_shift',
                    'start_time' => '08:00',
                    'end_time' => '16:00',
                ],
                [
                    'date' => '2026-07-03',
                    'shift_template_id' => 9403,
                    'shift_type' => 'regular_shift',
                    'start_time' => '16:00',
                    'end_time' => '24:00',
                ],
            ],
        ],
        'options' => [
            'include_holidays' => true,
            'include_warnings' => true,
            'strict_conflict_check' => true,
        ],
    ];
}

$environment = strtolower(preview_integration_env('TRACS_ENV'));
$allowMutations = preview_integration_env('TRACS_ALLOW_MUTATION_TESTS');
$database = preview_integration_env('TRACS_TEST_DB_NAME', 'tracs_phase28_test');
$sourceDatabase = preview_integration_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = preview_integration_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)preview_integration_env('TRACS_TEST_DB_PORT', '3307');
$user = preview_integration_env('TRACS_TEST_DB_USER', 'root');
$pass = preview_integration_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = preview_integration_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');

if ($environment !== 'test') {
    fwrite(STDERR, "SKIPPED: TRACS_ENV must be exactly test.\n");
    exit(3);
}
if ($allowMutations !== '1') {
    fwrite(STDERR, "SKIPPED: TRACS_ALLOW_MUTATION_TESTS=1 is required.\n");
    exit(3);
}
if (!preview_integration_safe_database($database)
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
    $admin->query(
        "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $databaseCreated = true;

    $clone = preview_integration_run([
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
    preview_integration_assert(
        $clone['exit_code'] === 0,
        'Unable to clone disposable schema: ' . trim($clone['stderr'])
    );

    $conn = new mysqli($host, $user, $pass, $database, $port);
    preview_integration_assert(!$conn->connect_error, 'Unable to connect to disposable database.');
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    $conn->query("
        INSERT INTO tracs_roles
          (id,name,slug,description,hierarchy_level,is_system_role)
        VALUES
          (9001,'Fixture Super Admin','super_admin','Phase 28 fixture',100,1),
          (9002,'Fixture Admin','admin','Phase 28 fixture',80,1)
    ");
    $conn->query("
        INSERT INTO tracs_permissions
          (id,permission_key,category,description)
        VALUES
          (9101,'shifts.view','Workforce Schedule','Phase 28 fixture'),
          (9102,'shifts.manage','Workforce Schedule','Phase 28 fixture')
    ");
    $conn->query("
        INSERT INTO tracs_role_permissions (role_id,permission_id)
        VALUES (9001,9101),(9001,9102),(9002,9101),(9002,9102)
    ");
    $conn->query("
        INSERT INTO tracs_divisions
          (id,name,code,description,status)
        VALUES (9201,'Phase 28 Operations','P28','Disposable fixture','active')
    ");
    $password = password_hash('Phase28-only-password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO tracs_users
          (id,email,password,name,username,role,is_active,status,division_id,role_id,
           two_factor_enabled,two_factor_reset_required)
        VALUES
          (9301,'phase28-super@tracs.test',?,'Phase 28 Super','phase28-super','admin',1,'active',9201,9001,1,0),
          (9302,'phase28-admin@tracs.test',?,'Phase 28 Admin','phase28-admin','admin',1,'active',9201,9002,1,0),
          (9303,'phase28-agent@tracs.test',?,'Phase 28 Agent','phase28-agent','operator',1,'active',9201,9002,1,0)
    ");
    preview_integration_assert($stmt instanceof mysqli_stmt, 'Unable to prepare fixture users.');
    $stmt->bind_param('sss', $password, $password, $password);
    preview_integration_assert($stmt->execute(), 'Unable to seed fixture users.');
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
        VALUES (9601,'2026-07-03','Phase 28 Holiday','custom',1)
    ");
    $conn->query("
        INSERT INTO shift_assignments
          (id,user_id,division_id,shift_template_id,assignment_date,start_datetime,
           end_datetime,is_cross_day,break_minutes,calculated_duration_minutes,
           assignment_type,status,is_overtime,is_holiday_assignment,approval_status,
           source,created_by,updated_by)
        VALUES
          (9701,9303,9201,9401,'2026-07-01','2026-07-01 00:00:00',
           '2026-07-01 08:00:00',0,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301)
    ");

    $payload = preview_integration_payload();
    $unauthenticated = preview_integration_request('POST', 0, 'missing', $payload);
    preview_integration_assert(
        ($unauthenticated['success'] ?? null) === false && $unauthenticated['_test_status'] === 401,
        'Unauthenticated preview did not return 401.'
    );
    $missingCsrf = preview_integration_request('POST', 9301, 'missing', $payload);
    preview_integration_assert(
        ($missingCsrf['success'] ?? null) === false && $missingCsrf['_test_status'] === 403,
        'Missing preview CSRF did not return 403.'
    );
    $invalidCsrf = preview_integration_request('POST', 9301, 'invalid', $payload);
    preview_integration_assert(
        ($invalidCsrf['success'] ?? null) === false && $invalidCsrf['_test_status'] === 403,
        'Invalid preview CSRF did not return 403.'
    );
    $wrongRole = preview_integration_request('POST', 9302, 'valid', $payload);
    preview_integration_assert(
        ($wrongRole['success'] ?? null) === false && $wrongRole['_test_status'] === 403,
        'Non-Super Admin preview did not return 403.'
    );
    $invalidPayload = $payload;
    $invalidPayload['start_date'] = '01-07-2026';
    $invalid = preview_integration_request('POST', 9301, 'valid', $invalidPayload);
    preview_integration_assert(
        ($invalid['success'] ?? null) === false && $invalid['_test_status'] === 422,
        'Invalid preview payload did not return 422.'
    );

    $before = preview_integration_counts($conn);
    $preview = preview_integration_request('POST', 9301, 'valid', $payload);
    $after = preview_integration_counts($conn);
    preview_integration_assert(
        $before === $after,
        'Template preview mutated persisted table counts: '
            . json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_SLASHES)
    );
    preview_integration_assert(
        ($preview['success'] ?? null) === true && $preview['_test_status'] === 200,
        'Valid template preview failed: ' . json_encode($preview, JSON_UNESCAPED_SLASHES)
    );
    preview_integration_assert(
        (int)($preview['data']['summary']['total_assignments'] ?? 0) === 3,
        'Template preview did not return three Shift 1/2/3 items.'
    );
    preview_integration_assert(
        (int)($preview['data']['summary']['conflicts'] ?? 0) > 0
            && (int)($preview['data']['summary']['blocked_items'] ?? 0) > 0,
        'Template preview did not report the expected overlap conflict.'
    );
    $displayRanges = array_map(
        static fn(array $item): string => (string)($item['shift']['display_range'] ?? ''),
        $preview['data']['items'] ?? []
    );
    preview_integration_assert(
        in_array('00:00-08:00', $displayRanges, true)
            && in_array('08:00-16:00', $displayRanges, true)
            && in_array('16:00-24:00', $displayRanges, true),
        'Template preview did not preserve Shift 1/2/3 display ranges.'
    );
    preview_integration_assert(
        count($preview['data']['warnings'] ?? []) > 0,
        'Template preview did not return warning or holiday/workload advisory data.'
    );

    $conn->close();
    echo "TRACS Shift Assignment template preview disposable integration checks passed.\n";
} finally {
    if ($databaseCreated) {
        $cleanup = new mysqli($host, $user, $pass, '', $port);
        if (!$cleanup->connect_error) {
            $cleanup->query("DROP DATABASE IF EXISTS `{$database}`");
            $cleanup->close();
        }
    }
    $admin->close();
}
