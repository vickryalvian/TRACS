<?php
declare(strict_types=1);

function copy_commit_preflight_fail(array $errors): never
{
    fwrite(STDERR, "TRACS Shift Assignment copy commit environment preflight failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

function copy_commit_preflight_env(string $key, ?string $default = null): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default ?? '';
    }

    return (string)$value;
}

function copy_commit_preflight_safe_db(string $database): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_]*(test|local|dev|disposable|staging)[A-Za-z0-9_]*$/i', $database);
}

function copy_commit_preflight_repo_file(string $path): string
{
    return dirname(__DIR__) . '/' . $path;
}

function copy_commit_preflight_read(string $path, array &$errors): string
{
    $fullPath = copy_commit_preflight_repo_file($path);
    $source = is_file($fullPath) ? file_get_contents($fullPath) : false;
    if ($source === false) {
        $errors[] = "Required file is missing or unreadable: {$path}.";
        return '';
    }

    return $source;
}

function copy_commit_preflight_run(array $command, ?string $cwd = null): array
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $cwd);
    if (!is_resource($process)) {
        return ['exit_code' => 127, 'stdout' => '', 'stderr' => 'Unable to start process.'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit_code' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

function copy_commit_preflight_scan(string $directory, array $extensions): string
{
    $root = realpath(copy_commit_preflight_repo_file($directory));
    if ($root === false) {
        return '';
    }

    $contents = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }
        $contents .= "\n" . (file_get_contents($file->getPathname()) ?: '');
    }

    return $contents;
}

$errors = [];
$environment = strtolower(copy_commit_preflight_env('TRACS_ENV'));
$appEnvironment = strtolower(copy_commit_preflight_env('APP_ENV'));
$allowMutations = copy_commit_preflight_env('TRACS_ALLOW_MUTATION_TESTS');
$database = copy_commit_preflight_env('TRACS_TEST_DB_NAME', 'tracs_phase43_test');
$sourceDatabase = copy_commit_preflight_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = copy_commit_preflight_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)copy_commit_preflight_env('TRACS_TEST_DB_PORT', '3307');
$user = copy_commit_preflight_env('TRACS_TEST_DB_USER', 'root');
$pass = copy_commit_preflight_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = copy_commit_preflight_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');

if ($environment !== 'test') {
    $errors[] = 'TRACS_ENV must be exactly test.';
}
if ($appEnvironment !== '' && in_array($appEnvironment, ['prod', 'production'], true)) {
    $errors[] = 'APP_ENV production labels are refused.';
}
if ($allowMutations !== '1') {
    $errors[] = 'TRACS_ALLOW_MUTATION_TESTS=1 is required.';
}
if (!copy_commit_preflight_safe_db($database)) {
    $errors[] = 'TRACS_TEST_DB_NAME must contain test/local/dev/disposable/staging.';
}
if (preg_match('/prod|production/i', $database) || in_array($database, ['tracs', 'tracs_db'], true)) {
    $errors[] = "Refusing unsafe database name {$database}.";
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $sourceDatabase) || $sourceDatabase === $database) {
    $errors[] = 'TRACS_TEST_SCHEMA_SOURCE must be a distinct safe database identifier.';
}

foreach ([
    'tests/disposable-db-preflight.php',
    'tests/shift-assignment-copy-commit-contract-gate.php',
    'tests/shift-assignment-copy-preview-integration.php',
    'tests/shift-assignment-template-commit-rollback-drill.php',
    'tests/shift-assignment-template-commit-race-conflict-drill.php',
    'frontend/tests/shift-template-apply-browser.mjs',
    'frontend/tests/shift-copy-preview-browser.mjs',
    'public/__test/shift-assignment-auth-session.php',
    'ROLLBACK.md',
] as $requiredFile) {
    if (!is_file(copy_commit_preflight_repo_file($requiredFile))) {
        $errors[] = "Required Phase 43 readiness file is missing: {$requiredFile}.";
    }
}

$packageJson = copy_commit_preflight_read('frontend/package.json', $errors);
foreach ([
    '"playwright"',
    '"test:e2e:shift-template-apply"',
    '"test:e2e:shift-copy-preview"',
] as $requiredPackageNeedle) {
    if (!str_contains($packageJson, $requiredPackageNeedle)) {
        $errors[] = "frontend/package.json is missing {$requiredPackageNeedle}.";
    }
}

$authHarness = copy_commit_preflight_read('public/__test/shift-assignment-auth-session.php', $errors);
foreach ([
    'TRACS_ENV',
    'TRACS_ALLOW_MUTATION_TESTS',
    'TRACS_TEST_DB_NAME',
    'test|local|dev|disposable|staging',
    'tracs_auth_state',
] as $requiredHarnessNeedle) {
    if (!str_contains($authHarness, $requiredHarnessNeedle)) {
        $errors[] = "Test auth harness is missing {$requiredHarnessNeedle}.";
    }
}

$rollback = copy_commit_preflight_read('ROLLBACK.md', $errors);
foreach ([
    'Phase 43 Copy Commit Environment Gate Rollback',
    'DROP DATABASE IF EXISTS tracs_phase43_test',
] as $requiredRollbackNeedle) {
    if (!str_contains($rollback, $requiredRollbackNeedle)) {
        $errors[] = "ROLLBACK.md is missing {$requiredRollbackNeedle}.";
    }
}

foreach ([
    'api/v1/shift-assignment/templates/copy-commit.php',
    'public/api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    if (is_file(copy_commit_preflight_repo_file($forbiddenRoute))) {
        $errors[] = "Copy commit endpoint must not exist in Phase 43: {$forbiddenRoute}.";
    }
}

$frontendSource = copy_commit_preflight_scan('frontend/src/modules/shift-assignment', ['js', 'jsx', 'mjs', 'css']);
foreach ([
    '/api/v1/shift-assignment/templates/copy-commit.php',
    'Apply Copy',
    'Commit Copy',
    'Paste Schedule',
    'APPLY COPY',
    'Rollback Copy',
] as $forbiddenFrontendNeedle) {
    if (str_contains($frontendSource, $forbiddenFrontendNeedle)) {
        $errors[] = "React copy commit/apply UI or caller is present: {$forbiddenFrontendNeedle}.";
    }
}

$dockerVersion = copy_commit_preflight_run(['docker', 'version']);
if ($dockerVersion['exit_code'] !== 0) {
    $errors[] = 'Docker daemon/socket is unavailable: ' . trim($dockerVersion['stderr'] ?: $dockerVersion['stdout']);
}

$dockerPs = copy_commit_preflight_run([
    'docker',
    'ps',
    '--filter',
    'name=^/' . $container . '$',
    '--format',
    '{{.Names}}',
]);
if ($dockerPs['exit_code'] !== 0 || !str_contains($dockerPs['stdout'], $container)) {
    $errors[] = "Docker container {$container} is not running.";
}

$playwrightCheck = copy_commit_preflight_run(
    ['node', '-e', "import('playwright').then(() => console.log('playwright-ok')).catch((error) => { console.error(error.message); process.exit(1); })"],
    copy_commit_preflight_repo_file('frontend')
);
if ($playwrightCheck['exit_code'] !== 0) {
    $errors[] = 'Playwright is unavailable in frontend dev dependencies: ' . trim($playwrightCheck['stderr'] ?: $playwrightCheck['stdout']);
}

mysqli_report(MYSQLI_REPORT_OFF);
$admin = @new mysqli($host, $user, $pass, '', $port);
if ($admin->connect_errno) {
    $errors[] = "MySQL is unavailable at {$host}:{$port}: " . $admin->connect_error;
} else {
    $admin->set_charset('utf8mb4');
    $escapedSource = $admin->real_escape_string($sourceDatabase);
    $source = $admin->query("SHOW DATABASES LIKE '{$escapedSource}'");
    if (!$source || $source->num_rows < 1) {
        $errors[] = "Source schema {$sourceDatabase} is not available.";
    }
    if ($source instanceof mysqli_result) {
        $source->free();
    }

    if (preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        $admin->query("DROP DATABASE IF EXISTS `{$database}`");
        $admin->query("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $created = $admin->query("SHOW DATABASES LIKE '" . $admin->real_escape_string($database) . "'");
        if (!$created || $created->num_rows !== 1) {
            $errors[] = "Unable to create disposable database {$database}.";
        }
        if ($created instanceof mysqli_result) {
            $created->free();
        }
        $admin->query("DROP DATABASE IF EXISTS `{$database}`");
        $removed = $admin->query("SHOW DATABASES LIKE '" . $admin->real_escape_string($database) . "'");
        if ($removed && $removed->num_rows > 0) {
            $errors[] = "Unable to remove disposable database {$database}.";
        }
        if ($removed instanceof mysqli_result) {
            $removed->free();
        }
    }

    $admin->close();
}

if ($errors) {
    copy_commit_preflight_fail($errors);
}

$contractGate = copy_commit_preflight_run(
    ['php', 'tests/shift-assignment-copy-commit-contract-gate.php'],
    dirname(__DIR__)
);
if ($contractGate['exit_code'] !== 0) {
    copy_commit_preflight_fail([
        'Copy commit contract gate failed during environment preflight.',
        trim($contractGate['stderr'] ?: $contractGate['stdout']),
    ]);
}

echo "TRACS Shift Assignment copy commit environment preflight passed for {$database} using {$container} at {$host}:{$port}.\n";
