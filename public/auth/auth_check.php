<?php
require_once __DIR__ . '/../../core/security/csrf.php';
require_once __DIR__ . '/../../core/security/auth_hardening.php';
tracs_start_session();

$uri = $_SERVER['REQUEST_URI'];

// allow login pages
if (
    strpos($uri, '/login.php') !== false ||
    strpos($uri, '/auth/login.php') !== false ||
    strpos($uri, '/two-factor-setup.php') !== false ||
    strpos($uri, '/two-factor-verify.php') !== false
) {
    return;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!tracs_is_fully_authenticated()) {
    if (tracs_auth_pending_user_id() > 0) {
        if (tracs_auth_pending_expired()) {
            $expiredUserId = tracs_auth_pending_user_id();
            tracs_auth_clear_pending_2fa();
            $_SESSION['login_error'] = TRACS_2FA_SESSION_EXPIRED;
            $_SESSION['login_show_help'] = true;
            if (isset($conn) && $conn instanceof mysqli) {
                tracs_auth_log_event($conn, 'two_factor_session_expired', 'expired', '', $expiredUserId ?: null, 'pending_timeout');
            }
            header('Location: /login.php');
            exit;
        }
        if (isset($conn) && $conn instanceof mysqli) {
            tracs_auth_log_event($conn, 'suspicious_access_attempt', 'blocked', tracs_auth_pending_identifier(), tracs_auth_pending_user_id(), 'pending_two_factor');
            header('Location: ' . tracs_auth_pending_redirect_path($conn));
            exit;
        }
    }
    if (!empty($_SESSION['user_id']) && (string)($_SESSION['tracs_auth_state'] ?? '') !== 'full') {
        if (isset($conn) && $conn instanceof mysqli) {
            tracs_auth_log_event($conn, 'suspicious_access_attempt', 'blocked', (string)($_SESSION['user_email'] ?? ''), (int)$_SESSION['user_id'], 'stale_or_partial_session');
        }
        $_SESSION = ['login_error' => 'Please sign in again.', 'login_show_help' => true];
        session_regenerate_id(true);
    }
    header('Location: /login.php');
    exit;
}

$lastSeen = (int)($_SESSION['tracs_last_seen_at'] ?? time());
if ((time() - $lastSeen) > tracs_auth_idle_timeout_seconds()) {
    $expiredUserId = (int)($_SESSION['user_id'] ?? 0);
    tracs_auth_destroy_current_session();
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
