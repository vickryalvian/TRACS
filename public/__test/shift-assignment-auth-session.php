<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/security/csrf.php';

tracs_start_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function tracs_test_auth_fail(string $message, int $status = 403): never
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
    exit;
}

$environment = strtolower((string)(getenv('TRACS_ENV') ?: ''));
$allowMutations = (string)(getenv('TRACS_ALLOW_MUTATION_TESTS') ?: '');
$database = (string)($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: getenv('TRACS_TEST_DB_NAME') ?: '');

if ($environment !== 'test' || $allowMutations !== '1') {
    tracs_test_auth_fail('Test authenticated session harness is disabled outside guarded test runs.', 404);
}
if (!preg_match('/^[A-Za-z0-9_]*(test|local|dev|disposable|staging)[A-Za-z0-9_]*$/i', $database)) {
    tracs_test_auth_fail('Disposable test database name is required.', 404);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/user_management.php';
require_once __DIR__ . '/../../core/security/auth_hardening.php';

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    tracs_test_auth_fail('Test user id is required.', 422);
}

$user = tracs_get_user_by_id($conn, $userId);
if (!$user || !tracs_user_can_login($user)) {
    tracs_test_auth_fail('Test user is unavailable.', 404);
}

session_regenerate_id(true);
tracs_rotate_csrf_token();
tracs_sync_session_user($user);
$_SESSION['tracs_auth_state'] = 'full';
$_SESSION['tracs_2fa_verified_at'] = time();
$_SESSION['tracs_last_seen_at'] = time();
unset(
    $_SESSION['tracs_pre_2fa_user_id'],
    $_SESSION['tracs_pre_2fa_identifier'],
    $_SESSION['tracs_pre_2fa_started_at'],
    $_SESSION['tracs_pre_2fa_expires_at'],
    $_SESSION['tracs_pre_2fa_landing'],
    $_SESSION['tracs_pre_2fa_mode'],
    $_SESSION['tracs_pending_2fa_secret'],
    $_SESSION['tracs_pending_2fa_setup_logged']
);

echo json_encode([
    'success' => true,
    'message' => 'Test authenticated session established.',
    'data' => [
        'user_id' => (int)$user['id'],
        'role_slug' => (string)($user['role_slug'] ?? ''),
        'csrf_token_present' => csrf_token() !== '',
    ],
]);
