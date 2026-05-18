<?php
require_once __DIR__ . '/../../core/security/csrf.php';
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
