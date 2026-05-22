<?php
require_once __DIR__ . '/../../core/security/csrf.php';
require_once __DIR__ . '/../../core/security/auth_hardening.php';
tracs_start_session();

$uri = $_SERVER['REQUEST_URI'];

// allow login pages
if (
    strpos($uri, '/login.php') !== false ||
    strpos($uri, '/auth/login.php') !== false
) {
    return;
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$lastSeen = (int)($_SESSION['tracs_last_seen_at'] ?? time());
if ((time() - $lastSeen) > tracs_auth_idle_timeout_seconds()) {
    $expiredUserId = (int)($_SESSION['user_id'] ?? 0);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    tracs_start_session();
    $_SESSION['login_error'] = 'Your session expired. Please sign in again.';
    if (isset($conn) && $conn instanceof mysqli) {
        tracs_auth_log_event($conn, 'session_timeout', 'expired', '', $expiredUserId ?: null, 'idle_timeout');
    }
    header('Location: /login.php');
    exit;
}
$_SESSION['tracs_last_seen_at'] = time();

if (isset($conn) && $conn instanceof mysqli) {
    require_once __DIR__ . '/../../core/user_management.php';
    $authUser = tracs_get_user_by_id($conn, (int)$_SESSION['user_id']);
    if (!$authUser || !tracs_user_can_login($authUser)) {
        $_SESSION = ['login_error' => 'Your session ended because the account is inactive or suspended.'];
        session_regenerate_id(true);
        header('Location: /login.php');
        exit;
    }
    tracs_sync_session_user($authUser);
    tracs_touch_user_activity($conn, (int)$authUser['id']);
}
