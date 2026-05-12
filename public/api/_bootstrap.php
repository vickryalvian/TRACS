<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../modules/activity-log/controller.php';

$uid  = (int)$_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];

function ok(mixed $data=null, string $msg='Success'): void {
    echo json_encode(['success'=>true,'message'=>$msg,'data'=>$data]);
    exit;
}
function fail(string $msg='Error', int $code=400): void {
    http_response_code($code);
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}
function logAct(mysqli $conn, int $uid, string $action, string $module, string $desc, mixed $ref=null): void {
    try {
        $AC = new ActivityLogController($conn, $uid);
        $AC->logActivity($action, $module, $desc, $ref);
    } catch(Exception $e) { /* non-fatal */ }
}
function tickerEvent(mysqli $conn, int $uid, string $msg, string $type='info', string $module=null, int $ref=null): void {
    try {
        require_once __DIR__.'/../../modules/ticker-events/controller.php';
        (new TickerEventController($conn))->create($uid, $msg, $type, $module, $ref);
    } catch(Exception $e) { /* non-fatal */ }
}
