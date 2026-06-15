<?php
declare(strict_types=1);

$database = (string)(getenv('TRACS_TEST_DB_NAME') ?: '');
$databaseHost = (string)(getenv('TRACS_TEST_DB_HOST') ?: '127.0.0.1');
$databasePort = (string)(getenv('TRACS_TEST_DB_PORT') ?: '3307');
$databaseUser = (string)(getenv('TRACS_TEST_DB_USER') ?: 'root');
$databasePass = (string)(getenv('TRACS_TEST_DB_PASS') ?: '');
$method = strtoupper((string)($argv[1] ?? 'GET'));
$userId = (int)($argv[2] ?? 0);
$csrfMode = (string)($argv[3] ?? 'valid');
$query = json_decode((string)($argv[4] ?? '{}'), true);
$requestBody = base64_decode((string)($argv[5] ?? ''), true);

if (getenv('TRACS_ENV') !== 'test'
    || getenv('TRACS_ALLOW_MUTATION_TESTS') !== '1'
    || !preg_match('/(?:test|local|dev|disposable)/i', $database)) {
    fwrite(STDERR, "Unsafe integration request harness environment.\n");
    exit(2);
}

final class Phase15PhpInputStream
{
    public static string $body = '';
    private int $position = 0;

    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath
    ): bool {
        return $path === 'php://input';
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$body);
    }

    public function stream_stat(): array
    {
        return [];
    }
}

Phase15PhpInputStream::$body = is_string($requestBody) ? $requestBody : '';
if (!stream_wrapper_unregister('php')
    || !stream_wrapper_register('php', Phase15PhpInputStream::class)) {
    fwrite(STDERR, "Unable to install the disposable request-body stream.\n");
    exit(2);
}
register_shutdown_function(static function (): void {
    $status = http_response_code();
    fwrite(STDERR, "\nPHASE15_STATUS:" . (is_int($status) ? $status : 200) . "\n");
});

$_ENV['DB_HOST'] = $databaseHost;
$_ENV['DB_PORT'] = $databasePort;
$_ENV['DB_USER'] = $databaseUser;
$_ENV['DB_PASS'] = $databasePass;
$_ENV['DB_NAME'] = $database;
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = '/api/v1/shift-assignment/assignments.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TRACS Phase 15 Integration Test';
$_GET = is_array($query) ? $query : [];

require_once __DIR__ . '/../../core/security/csrf.php';
\tracs_start_session();
$_SESSION = [];

if ($userId > 0) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = 'fixture-' . $userId . '@tracs.test';
    $_SESSION['tracs_auth_state'] = 'full';
    $_SESSION['tracs_last_seen_at'] = time();
    $_SESSION['csrf_token'] = 'phase15-valid-csrf-token';

    if ($csrfMode === 'valid') {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'phase15-valid-csrf-token';
    } elseif ($csrfMode === 'invalid') {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'phase15-invalid-csrf-token';
    }
}

require __DIR__ . '/../../public/api/v1/shift-assignment/assignments.php';
