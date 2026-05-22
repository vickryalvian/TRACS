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
    tracs_auth_destroy_current_session();
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
function api_require_permissions(array $permissions): void {
    global $conn, $uid;
    foreach ($permissions as $permission) {
        if (!is_string($permission) || $permission === '') {
            continue;
        }
        if (!tracs_user_can($conn, $permission, $uid)) {
            tracs_auth_log_event($conn, 'permission_denied', 'blocked', (string)($_SESSION['user_email'] ?? ''), $uid ?: null, $permission);
            fail('Forbidden', 403);
        }
    }
}
function api_require_any_permission(array $permissions): void {
    global $conn, $uid;
    $checked = [];
    foreach ($permissions as $permission) {
        if (!is_string($permission) || $permission === '') {
            continue;
        }
        $checked[] = $permission;
        if (tracs_user_can($conn, $permission, $uid)) {
            return;
        }
    }
    tracs_auth_log_event($conn, 'permission_denied', 'blocked', (string)($_SESSION['user_email'] ?? ''), $uid ?: null, implode('|', $checked));
    fail('Forbidden', 403);
}
function api_require_tv_mode_access(): void {
    global $authUser, $conn, $uid;
    if (in_array((string)($authUser['role_slug'] ?? ''), ['super_admin', 'admin', 'supervisor'], true)) {
        return;
    }
    tracs_auth_log_event($conn, 'permission_denied', 'blocked', (string)($_SESSION['user_email'] ?? ''), $uid ?: null, 'tv_mode_access');
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

$script = basename((string)(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: ''));
$apiPermissionMap = [
    'api_mom.php' => ['moms.manage'],
    'bt-create.php' => ['finance.manage'],
    'bt-delete.php' => ['finance.manage'],
    'bt-update.php' => ['finance.manage'],
    'case-create.php' => ['cases.manage'],
    'case-delete.php' => ['cases.manage'],
    'case-get.php' => ['cases.view'],
    'case-update.php' => ['cases.manage'],
    'currency.php' => ['dashboard.view'],
    'currency-converter.php' => ['dashboard.view'],
    'domain-create.php' => ['domains.manage'],
    'domain-delete.php' => ['domains.manage'],
    'domain-update.php' => ['domains.manage'],
    'domain-price-matrix.php' => ['domain_price.manage'],
    'domain-price-workflow.php' => ['domain_price.manage'],
    'feedback-create.php' => ['cancellation_feedback.manage'],
    'feedback-delete.php' => ['cancellation_feedback.manage'],
    'feedback-list.php' => ['cancellation_feedback.view'],
    'feedback-update.php' => ['cancellation_feedback.manage'],
    'finance-create.php' => ['finance.manage'],
    'finance-delete.php' => ['finance.manage'],
    'mom-action.php' => ['moms.manage'],
    'reminder-create.php' => ['reminders.manage'],
    'reminder-delete.php' => ['reminders.manage'],
    'reminder-get.php' => ['reminders.view'],
    'reminder-toggle.php' => ['reminders.manage'],
    'reminder-update.php' => ['reminders.manage'],
    'shift-create.php' => ['reports.create'],
    'shift-delete.php' => ['reports.update'],
    'shift-history.php' => ['reports.view'],
    'shift-list.php' => ['reports.view'],
    'shift-resolve.php' => ['reports.update'],
    'shift-update.php' => ['reports.update'],
    'task-create.php' => ['checklist.manage'],
    'task-delete.php' => ['checklist.manage'],
    'task-toggle.php' => ['checklist.manage'],
    'task-update.php' => ['checklist.manage'],
    'ticker-create.php' => ['dashboard.view'],
    'ticker-delete.php' => ['dashboard.view'],
    'ticker-list.php' => ['dashboard.view'],
];
if (isset($apiPermissionMap[$script])) {
    api_require_permissions($apiPermissionMap[$script]);
}
if (in_array($script, ['holiday-indonesia.php', 'tv-mode-summary.php'], true)) {
    api_require_tv_mode_access();
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
