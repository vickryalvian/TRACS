<?php
declare(strict_types=1);

function browser_env_fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function browser_env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function browser_env_safe_database(string $database): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1
        && preg_match('/(?:test|local|dev|disposable|staging)/i', $database) === 1;
}

function browser_env_run(array $command): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        browser_env_fail('Unable to start browser environment subprocess.');
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

function browser_env_scalar(mysqli $conn, string $sql): mixed
{
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        browser_env_fail('Disposable browser assertion query failed.');
    }
    $row = $result->fetch_row();
    $result->free();
    return $row[0] ?? null;
}

$action = strtolower((string)($argv[1] ?? ''));
$environment = strtolower(browser_env_value('TRACS_ENV'));
$allowMutations = browser_env_value('TRACS_ALLOW_MUTATION_TESTS');
$database = browser_env_value('TRACS_TEST_DB_NAME', 'tracs_phase17_test');
$sourceDatabase = browser_env_value('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = browser_env_value('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)browser_env_value('TRACS_TEST_DB_PORT', '3307');
$user = browser_env_value('TRACS_TEST_DB_USER', 'root');
$pass = browser_env_value('TRACS_TEST_DB_PASS', 'root_secret');
$container = browser_env_value('TRACS_TEST_DB_CONTAINER', 'tracs_db');

if (!in_array($action, ['setup', 'verify', 'cleanup'], true)) {
    browser_env_fail('Usage: php tests/shift-assignment-create-ui-browser-environment.php setup|verify|cleanup', 2);
}
if ($environment !== 'test') {
    browser_env_fail('REFUSED: TRACS_ENV must be exactly test.', 3);
}
if ($allowMutations !== '1') {
    browser_env_fail('REFUSED: TRACS_ALLOW_MUTATION_TESTS=1 is required.', 3);
}
if (!browser_env_safe_database($database)) {
    browser_env_fail('REFUSED: browser database name is not safely marked.', 4);
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $sourceDatabase) || $sourceDatabase === $database) {
    browser_env_fail('REFUSED: unsafe source/target database configuration.', 4);
}

$admin = new mysqli($host, $user, $pass, '', $port);
if ($admin->connect_error) {
    browser_env_fail('Disposable MySQL is unavailable.', 3);
}
$admin->set_charset('utf8mb4');

if ($action === 'cleanup') {
    $admin->query("DROP DATABASE IF EXISTS `{$database}`");
    $exists = (int)browser_env_scalar(
        $admin,
        "SELECT COUNT(*) FROM information_schema.SCHEMATA
         WHERE SCHEMA_NAME='" . $admin->real_escape_string($database) . "'"
    );
    $admin->close();
    if ($exists !== 0) {
        browser_env_fail('Disposable browser database cleanup failed.', 5);
    }
    echo "TRACS disposable browser database removed.\n";
    exit(0);
}

if ($action === 'setup') {
    $admin->query("DROP DATABASE IF EXISTS `{$database}`");
    $admin->query(
        "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $clone = browser_env_run([
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
    if ($clone['exit_code'] !== 0) {
        $admin->query("DROP DATABASE IF EXISTS `{$database}`");
        $admin->close();
        browser_env_fail('Unable to clone disposable schema: ' . trim($clone['stderr']));
    }

    $conn = new mysqli($host, $user, $pass, $database, $port);
    if ($conn->connect_error) {
        $admin->query("DROP DATABASE IF EXISTS `{$database}`");
        $admin->close();
        browser_env_fail('Unable to connect to disposable browser database.');
    }
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    $conn->query("
        INSERT INTO tracs_roles
          (id,name,slug,description,hierarchy_level,is_system_role)
        VALUES
          (9701,'Phase 17 Super Admin','super_admin','Disposable browser fixture',100,1),
          (9702,'Phase 17 Admin','admin','Disposable browser fixture',80,1)
    ");
    $conn->query("
        INSERT INTO tracs_permissions
          (id,permission_key,category,description)
        VALUES
          (9711,'shifts.view','Workforce Schedule','Disposable browser fixture'),
          (9712,'shifts.manage','Workforce Schedule','Disposable browser fixture')
    ");
    $conn->query("
        INSERT INTO tracs_role_permissions (role_id,permission_id)
        VALUES (9701,9711),(9701,9712),(9702,9711),(9702,9712)
    ");
    $conn->query("
        INSERT INTO tracs_divisions
          (id,name,code,description,status)
        VALUES (9721,'Phase 17 Operations','P17','Disposable browser fixture','active')
    ");

    $password = password_hash('Phase17-browser-only!', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO tracs_users
          (id,email,password,name,username,role,is_active,status,division_id,role_id,
           two_factor_enabled,two_factor_reset_required)
        VALUES
          (9731,'phase17-super@tracs.test',?,'Phase 17 Super','phase17-super','admin',1,'active',9721,9701,0,1),
          (9732,'phase17-admin@tracs.test',?,'Phase 17 Admin','phase17-admin','admin',1,'active',9721,9702,0,1),
          (9733,'phase17-agent@tracs.test',?,'Phase 17 Agent','phase17-agent','operator',1,'active',9721,9702,0,1)
    ");
    if (!$stmt) {
        browser_env_fail('Unable to prepare disposable browser users.');
    }
    $stmt->bind_param('sss', $password, $password, $password);
    if (!$stmt->execute()) {
        browser_env_fail('Unable to seed disposable browser users.');
    }
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
          (9741,'Shift 3','16:00:00','00:00:00',480,0,1,'#4f46e5','regular_shift',1,1)
    ");
    $conn->query("
        INSERT INTO shift_workload_settings
          (division_id,weekly_target_minutes,max_weekly_minutes,max_daily_minutes,
           overtime_threshold_minutes,normal_working_days_per_week,
           minimum_rest_between_shifts_minutes,timeline_snap_minutes,minimum_shift_minutes)
        VALUES (9721,2400,2880,960,2700,5,480,15,60)
    ");

    $conn->close();
    $admin->close();
    echo json_encode([
        'database' => $database,
        'super_admin' => [
            'username' => 'phase17-super',
            'password' => 'Phase17-browser-only!',
        ],
        'admin' => [
            'username' => 'phase17-admin',
            'password' => 'Phase17-browser-only!',
        ],
        'agent_id' => 9733,
        'assignment_date' => '2026-07-13',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$conn = new mysqli($host, $user, $pass, $database, $port);
if ($conn->connect_error) {
    $admin->close();
    browser_env_fail('Disposable browser database is unavailable for verification.');
}
$assignmentCount = (int)browser_env_scalar(
    $conn,
    "SELECT COUNT(*) FROM shift_assignments
     WHERE user_id=9733 AND assignment_date='2026-07-13'"
);
$assignmentId = (int)browser_env_scalar(
    $conn,
    "SELECT COALESCE(MAX(id),0) FROM shift_assignments
     WHERE user_id=9733 AND assignment_date='2026-07-13'"
);
$assignmentAudit = $assignmentId > 0
    ? (int)browser_env_scalar(
        $conn,
        "SELECT COUNT(*) FROM assignment_audit_logs
         WHERE assignment_id={$assignmentId} AND action='created'"
    )
    : 0;
$activityAudit = $assignmentId > 0
    ? (int)browser_env_scalar(
        $conn,
        "SELECT COUNT(*) FROM tracs_user_activity_logs
         WHERE actor_user_id=9731 AND action='shift_assignment.create'
           AND target_id={$assignmentId}"
    )
    : 0;

$result = [
    'database' => $database,
    'assignment_count' => $assignmentCount,
    'assignment_id' => $assignmentId,
    'assignment_audit_count' => $assignmentAudit,
    'activity_audit_count' => $activityAudit,
];
$conn->close();
$admin->close();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
