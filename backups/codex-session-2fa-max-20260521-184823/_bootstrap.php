<?php
require_once __DIR__.'/../../core/security/csrf.php';
tracs_start_session();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/creator_tracking.php';
require_once __DIR__.'/../../core/user_management.php';
require_once __DIR__.'/../../core/access_control.php';
require_once __DIR__.'/../../modules/activity-log/controller.php';

if (!tracs_is_fully_authenticated()) {
    if (tracs_auth_pending_user_id() > 0) {
        $reason = tracs_auth_pending_expired() ? 'pending_two_factor_expired' : 'pending_two_factor';
        tracs_auth_log_event($conn, 'suspicious_access_attempt', 'blocked', tracs_auth_pending_identifier(), tracs_auth_pending_user_id(), $reason);
        if (tracs_auth_pending_expired()) {
            tracs_auth_clear_pending_2fa();
        }
    }
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
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
    tracs_auth_log_event($conn, 'session_timeout', 'expired', '', $expiredUserId ?: null, 'idle_timeout');
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Session expired']);
    exit;
}
$_SESSION['tracs_last_seen_at'] = time();

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    verify_csrf();
}

$uid  = (int)$_SESSION['user_id'];
$authUser = tracs_get_user_by_id($conn, $uid);
if (!$authUser || !tracs_user_can_login($authUser)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Account inactive or suspended']);
    exit;
}
tracs_sync_session_user($authUser);
tracs_touch_user_activity($conn, $uid);
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$creator_name = tracs_current_user_display($conn);

function ok(mixed $data=null, string $msg='Success'): void {
    echo json_encode(['success'=>true,'message'=>$msg,'data'=>$data]);
    exit;
}
function fail(string $msg='Error', int $code=400): void {
    http_response_code($code);
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}
function fail_not_found(): void {
    fail('Not found', 404);
}
function logAct(mysqli $conn, int $uid, string $action, string $module, string $desc, mixed $ref=null): void {
    try {
        $AC = new ActivityLogController($conn, $uid);
        $AC->logActivity($action, $module, $desc, $ref);
    } catch(Exception $e) { /* non-fatal */ }
}
function tickerEvent(mysqli $conn, int $uid, string $msg, string $type='info', ?string $module=null, ?int $ref=null): void {
    try {
        require_once __DIR__.'/../../modules/ticker-events/controller.php';
        (new TickerEventController($conn))->create($uid, $msg, $type, $module, $ref);
    } catch(Exception $e) { /* non-fatal */ }
}
