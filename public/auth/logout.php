<?php
require_once __DIR__ . '/../../core/security/csrf.php';
require_once __DIR__ . '/../../core/security/auth_hardening.php';
tracs_start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Logout requires POST.';
    exit;
}
verify_csrf();
$logoutUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../../config/database.php';
if (isset($conn) && $conn instanceof mysqli) {
    require_once __DIR__ . '/../../core/user_management.php';
    tracs_auth_log_event($conn, 'logout', 'success', (string)($_SESSION['user_email'] ?? ''), $logoutUserId ?: null);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
header('Location: /login.php');
exit;
