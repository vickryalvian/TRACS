<?php
declare(strict_types=1);

function disposable_preflight_env(string $key, ?string $default = null): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default ?? '';
    }
    return (string)$value;
}

function disposable_preflight_safe_db(string $database): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_]*(test|local|dev|disposable|staging)[A-Za-z0-9_]*$/i', $database);
}

function disposable_preflight_run(array $command): array
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes);
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

$environment = strtolower(disposable_preflight_env('TRACS_ENV'));
$allowMutations = disposable_preflight_env('TRACS_ALLOW_MUTATION_TESTS');
$database = disposable_preflight_env('TRACS_TEST_DB_NAME', 'tracs_phase31_test');
$sourceDatabase = disposable_preflight_env('TRACS_TEST_SCHEMA_SOURCE', 'tracs_db');
$host = disposable_preflight_env('TRACS_TEST_DB_HOST', '127.0.0.1');
$port = (int)disposable_preflight_env('TRACS_TEST_DB_PORT', '3307');
$user = disposable_preflight_env('TRACS_TEST_DB_USER', 'root');
$pass = disposable_preflight_env('TRACS_TEST_DB_PASS', 'root_secret');
$container = disposable_preflight_env('TRACS_TEST_DB_CONTAINER', 'tracs_db');

$errors = [];
if ($environment !== 'test') {
    $errors[] = 'TRACS_ENV must be exactly test.';
}
if ($allowMutations !== '1') {
    $errors[] = 'TRACS_ALLOW_MUTATION_TESTS=1 is required.';
}
if (!disposable_preflight_safe_db($database)) {
    $errors[] = 'TRACS_TEST_DB_NAME must contain test/local/dev/disposable/staging.';
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $sourceDatabase) || $sourceDatabase === $database) {
    $errors[] = 'TRACS_TEST_SCHEMA_SOURCE must be a distinct safe database identifier.';
}
if (in_array($environment, ['prod', 'production'], true)) {
    $errors[] = 'Production environment label is refused.';
}

$dockerVersion = disposable_preflight_run(['docker', 'version']);
if ($dockerVersion['exit_code'] !== 0) {
    $errors[] = 'Docker daemon/socket is unavailable: ' . trim($dockerVersion['stderr'] ?: $dockerVersion['stdout']);
}

$dockerPs = disposable_preflight_run([
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

mysqli_report(MYSQLI_REPORT_OFF);
$admin = @new mysqli($host, $user, $pass, '', $port);
if ($admin->connect_errno) {
    $errors[] = "MySQL is unavailable at {$host}:{$port}: " . $admin->connect_error;
} else {
    $admin->set_charset('utf8mb4');
    $source = $admin->query("SHOW DATABASES LIKE '" . $admin->real_escape_string($sourceDatabase) . "'");
    if (!$source || $source->num_rows < 1) {
        $errors[] = "Source schema {$sourceDatabase} is not available.";
    }
    $target = $admin->query("SHOW DATABASES LIKE '" . $admin->real_escape_string($database) . "'");
    if ($target && $target->num_rows > 0) {
        $admin->query("DROP DATABASE `{$database}`");
        $target = $admin->query("SHOW DATABASES LIKE '" . $admin->real_escape_string($database) . "'");
        if ($target && $target->num_rows > 0) {
            $errors[] = "Unable to clean existing disposable database {$database}.";
        }
    }
    if ($source instanceof mysqli_result) {
        $source->free();
    }
    if ($target instanceof mysqli_result) {
        $target->free();
    }
    $admin->close();
}

if ($errors) {
    fwrite(STDERR, "TRACS disposable DB preflight failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "TRACS disposable DB preflight passed for {$database} using {$container} at {$host}:{$port}.\n";
