<?php
declare(strict_types=1);

function copy_preview_integration_fail(string $message): never
{
    throw new RuntimeException($message);
}

function copy_preview_integration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        copy_preview_integration_fail($message);
    }
}

function copy_preview_integration_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function copy_preview_integration_safe_database(string $database): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1
        && preg_match('/(?:test|local|dev|disposable|staging)/i', $database) === 1;
}

function copy_preview_integration_run(array $command): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        copy_preview_integration_fail('Unable to start integration subprocess.');
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

function copy_preview_integration_request(
    string $method,
    int $userId,
    string $csrfMode,
    array $body
): array {
    $result = copy_preview_integration_run([
        PHP_BINARY,
        __DIR__ . '/fixtures/shift-assignment-api-request.php',
        $method,
        (string)$userId,
        $csrfMode,
        '{}',
        base64_encode(json_encode($body, JSON_THROW_ON_ERROR)),
        'templates/copy-preview',
    ]);
    copy_preview_integration_assert(
        $result['exit_code'] === 0,
        'Copy-preview API harness failed: ' . trim($result['stderr'])
    );
    preg_match('/PHASE15_STATUS:(\d+)/', $result['stderr'], $statusMatch);
    $status = isset($statusMatch[1]) ? (int)$statusMatch[1] : 0;
    copy_preview_integration_assert($status > 0, 'Copy-preview API harness did not report a status.');
    $payload = json_decode(trim($result['stdout']), true);
    copy_preview_integration_assert(
        is_array($payload),
        'Copy-preview API returned invalid JSON: ' . trim($result['stdout'])
    );
    $payload['_test_status'] = $status;
    return $payload;
}

function copy_preview_integration_counts(mysqli $conn): array
{
    $tables = [
        'shift_assignments',
        'shift_warnings',
        'holiday_coverage_assignments',
        'shift_monthly_templates',
        'shift_monthly_template_items',
        'assignment_audit_logs',
        'tracs_user_activity_logs',
    ];
    $counts = [];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) FROM `{$table}`");
        copy_preview_integration_assert($result instanceof mysqli_result, "Unable to count {$table}.");
        $row = $result->fetch_row();
        $result->free();
        $counts[$table] = (int)($row[0] ?? 0);
    }
    return $counts;
}

function copy_preview_payload(array $overrides = []): array
{
    return array_replace_recursive([
        'source_start_date' => '2026-07-01',
        'source_end_date' => '2026-07-03',
        'target_start_date' => '2026-08-01',
        'target_end_date' => '2026-08-03',
        'scope' => [
            'agent_ids' => [9303],
            'division_ids' => [9201],
            'role_ids' => [],
        ],
        'options' => [
            'include_holidays' => true,
            'include_warnings' => true,
            'strict_conflict_check' => true,
        ],
    ], $overrides);
}

$environment = strtolower(copy_preview_integration_env('TRACS_ENV'));
$allowMutations = copy_preview_integration_env('TRACS_ALLOW_MUTATION_TESTS');
$database = copy_preview_integration_env('TRACS_TEST_DB_NAME', 'tracs_phase39_test');
$sourceDatabase = copy_preview_integration_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = copy_preview_integration_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)copy_preview_integration_env('TRACS_TEST_DB_PORT', '3307');
$user = copy_preview_integration_env('TRACS_TEST_DB_USER', 'root');
$pass = copy_preview_integration_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = copy_preview_integration_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');

if ($environment !== 'test') {
    fwrite(STDERR, "SKIPPED: TRACS_ENV must be exactly test.\n");
    exit(3);
}
if ($allowMutations !== '1') {
    fwrite(STDERR, "SKIPPED: TRACS_ALLOW_MUTATION_TESTS=1 is required.\n");
    exit(3);
}
if (!copy_preview_integration_safe_database($database)
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

    $clone = copy_preview_integration_run([
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
    copy_preview_integration_assert(
        $clone['exit_code'] === 0,
        'Unable to clone disposable schema: ' . trim($clone['stderr'])
    );

    $conn = new mysqli($host, $user, $pass, $database, $port);
    copy_preview_integration_assert(!$conn->connect_error, 'Unable to connect to disposable database.');
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    $conn->query("
        INSERT INTO tracs_roles
          (id,name,slug,description,hierarchy_level,is_system_role)
        VALUES
          (9001,'Fixture Super Admin','super_admin','Phase 39 fixture',100,1),
          (9002,'Fixture Admin','admin','Phase 39 fixture',80,1)
    ");
    $conn->query("
        INSERT INTO tracs_permissions
          (id,permission_key,category,description)
        VALUES
          (9101,'shifts.view','Workforce Schedule','Phase 39 fixture'),
          (9102,'shifts.manage','Workforce Schedule','Phase 39 fixture')
    ");
    $conn->query("
        INSERT INTO tracs_role_permissions (role_id,permission_id)
        VALUES (9001,9101),(9001,9102),(9002,9101),(9002,9102)
    ");
    $conn->query("
        INSERT INTO tracs_divisions
          (id,name,code,description,status)
        VALUES (9201,'Phase 39 Operations','P39','Disposable fixture','active')
    ");
    $password = password_hash('Phase39-only-password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO tracs_users
          (id,email,password,name,username,role,is_active,status,division_id,role_id,
           two_factor_enabled,two_factor_reset_required)
        VALUES
          (9301,'phase39-super@tracs.test',?,'Phase 39 Super','phase39-super','admin',1,'active',9201,9001,1,0),
          (9302,'phase39-admin@tracs.test',?,'Phase 39 Admin','phase39-admin','admin',1,'active',9201,9002,1,0),
          (9303,'phase39-agent@tracs.test',?,'Phase 39 Agent','phase39-agent','operator',1,'active',9201,9002,1,0)
    ");
    copy_preview_integration_assert($stmt instanceof mysqli_stmt, 'Unable to prepare fixture users.');
    $stmt->bind_param('sss', $password, $password, $password);
    copy_preview_integration_assert($stmt->execute(), 'Unable to seed fixture users.');
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
        VALUES (9601,'2026-08-03','Phase 39 Target Holiday','custom',1)
    ");
    $conn->query("
        INSERT INTO shift_assignments
          (id,user_id,division_id,shift_template_id,assignment_date,start_datetime,
           end_datetime,is_cross_day,break_minutes,calculated_duration_minutes,
           assignment_type,status,is_overtime,is_holiday_assignment,approval_status,
           source,created_by,updated_by,notes)
        VALUES
          (9701,9303,9201,9401,'2026-07-01','2026-07-01 00:00:00',
           '2026-07-01 08:00:00',0,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301,'Shift 1 source note'),
          (9702,9303,9201,9402,'2026-07-02','2026-07-02 08:00:00',
           '2026-07-02 16:00:00',0,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301,NULL),
          (9703,9303,9201,9403,'2026-07-03','2026-07-03 16:00:00',
           '2026-07-04 00:00:00',1,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301,NULL)
    ");

    $payload = copy_preview_payload();
    $unsupportedMethod = copy_preview_integration_request('GET', 9301, 'valid', $payload);
    copy_preview_integration_assert(
        ($unsupportedMethod['success'] ?? null) === false && $unsupportedMethod['_test_status'] === 405,
        'Unsupported copy-preview method did not return 405.'
    );
    $unauthenticated = copy_preview_integration_request('POST', 0, 'missing', $payload);
    copy_preview_integration_assert(
        ($unauthenticated['success'] ?? null) === false && $unauthenticated['_test_status'] === 401,
        'Unauthenticated copy-preview did not return 401.'
    );
    $missingCsrf = copy_preview_integration_request('POST', 9301, 'missing', $payload);
    copy_preview_integration_assert(
        ($missingCsrf['success'] ?? null) === false && $missingCsrf['_test_status'] === 403,
        'Missing copy-preview CSRF did not return 403.'
    );
    $invalidCsrf = copy_preview_integration_request('POST', 9301, 'invalid', $payload);
    copy_preview_integration_assert(
        ($invalidCsrf['success'] ?? null) === false && $invalidCsrf['_test_status'] === 403,
        'Invalid copy-preview CSRF did not return 403.'
    );
    $wrongRole = copy_preview_integration_request('POST', 9302, 'valid', $payload);
    copy_preview_integration_assert(
        ($wrongRole['success'] ?? null) === false && $wrongRole['_test_status'] === 403,
        'Non-Super Admin copy-preview did not return 403.'
    );

    foreach ([
        'invalid date' => copy_preview_payload(['source_start_date' => '01-07-2026']),
        'same range' => copy_preview_payload(['target_start_date' => '2026-07-01', 'target_end_date' => '2026-07-03']),
        'length mismatch' => copy_preview_payload(['target_end_date' => '2026-08-04']),
        'range too long' => copy_preview_payload(['source_end_date' => '2026-08-15', 'target_end_date' => '2026-09-15']),
        'unsupported role scope' => copy_preview_payload(['scope' => ['role_ids' => [9002]]]),
    ] as $label => $invalidPayload) {
        $invalid = copy_preview_integration_request('POST', 9301, 'valid', $invalidPayload);
        copy_preview_integration_assert(
            ($invalid['success'] ?? null) === false && $invalid['_test_status'] === 422,
            "Invalid copy-preview payload did not return 422 for {$label}."
        );
    }

    $before = copy_preview_integration_counts($conn);
    $preview = copy_preview_integration_request('POST', 9301, 'valid', $payload);
    $after = copy_preview_integration_counts($conn);
    copy_preview_integration_assert(
        $before === $after,
        'Copy-preview mutated persisted table counts: '
            . json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_SLASHES)
    );
    copy_preview_integration_assert(
        ($preview['success'] ?? null) === true && $preview['_test_status'] === 200,
        'Valid copy-preview failed: ' . json_encode($preview, JSON_UNESCAPED_SLASHES)
    );
    copy_preview_integration_assert(
        (int)($preview['data']['summary']['source_assignments'] ?? 0) === 3
            && (int)($preview['data']['summary']['preview_assignments'] ?? 0) === 3,
        'Copy-preview did not return three Shift 1/2/3 preview items.'
    );
    $displayRanges = array_map(
        static fn(array $item): string => (string)($item['shift']['display_range'] ?? ''),
        $preview['data']['items'] ?? []
    );
    copy_preview_integration_assert(
        in_array('00:00-08:00', $displayRanges, true)
            && in_array('08:00-16:00', $displayRanges, true)
            && in_array('16:00-24:00', $displayRanges, true),
        'Copy-preview did not preserve Shift 1/2/3 display ranges.'
    );
    copy_preview_integration_assert(
        min(array_map(static fn(array $item): int => (int)($item['preview_id'] ?? 0), $preview['data']['items'] ?? [])) < 0
            && in_array(9701, array_map(static fn(array $item): int => (int)($item['source_assignment_id'] ?? 0), $preview['data']['items'] ?? []), true),
        'Copy-preview reused source assignment IDs or omitted source assignment references.'
    );
    copy_preview_integration_assert(
        count($preview['data']['warnings'] ?? []) > 0,
        'Copy-preview did not return holiday/workload/copied-note warning data.'
    );

    $conn->query("
        INSERT INTO shift_assignments
          (id,user_id,division_id,shift_template_id,assignment_date,start_datetime,
           end_datetime,is_cross_day,break_minutes,calculated_duration_minutes,
           assignment_type,status,is_overtime,is_holiday_assignment,approval_status,
           source,created_by,updated_by)
        VALUES
          (9799,9303,9201,9401,'2026-08-01','2026-08-01 00:00:00',
           '2026-08-01 08:00:00',0,0,480,'regular_shift','assigned',0,0,
           'not_required','manual',9301,9301)
    ");
    $beforeConflict = copy_preview_integration_counts($conn);
    $conflict = copy_preview_integration_request('POST', 9301, 'valid', $payload);
    $afterConflict = copy_preview_integration_counts($conn);
    copy_preview_integration_assert(
        $beforeConflict === $afterConflict,
        'Conflicting copy-preview mutated persisted table counts.'
    );
    copy_preview_integration_assert(
        ($conflict['success'] ?? null) === true
            && (int)($conflict['data']['summary']['conflicts'] ?? 0) > 0
            && (int)($conflict['data']['summary']['blocked_items'] ?? 0) > 0,
        'Copy-preview did not return target overlap conflicts and blocked items.'
    );

    $conn->close();
    echo "TRACS Shift Assignment copy-preview disposable integration checks passed.\n";
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
