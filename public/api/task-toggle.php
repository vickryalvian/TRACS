<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/ShiftActivityService.php';

$id=(int)($body['id']??0); if(!$id) fail('ID required');
$done=(int)(bool)($body['is_completed']??0);

function taskColumnExists(mysqli $conn, string $column): bool {
    $stmt=$conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tracs_side_tasks'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if(!$stmt) return false;
    $stmt->bind_param('s',$column);
    $stmt->execute();
    $exists=$stmt->get_result()->num_rows>0;
    $stmt->close();
    return $exists;
}

$sets=["is_completed=?","updated_at=NOW()"];
if(taskColumnExists($conn,'completed_at')) $sets[]=$done ? "completed_at=NOW()" : "completed_at=NULL";
if(taskColumnExists($conn,'archived_at')) $sets[]=$done ? "archived_at=NOW()" : "archived_at=NULL";
if(taskColumnExists($conn,'reset_at')) $sets[]=$done ? "reset_at=DATE_ADD(CURDATE(), INTERVAL 1 DAY)" : "reset_at=NULL";
if(taskColumnExists($conn,'completed_by')) $sets[]=$done ? "completed_by={$uid}" : "completed_by=NULL";

$taskTitle = "task #{$id}";
$taskStmt = $conn->prepare("SELECT title FROM tracs_side_tasks WHERE id=? AND user_id=? LIMIT 1");
if($taskStmt){
    $taskStmt->bind_param('ii',$id,$uid);
    $taskStmt->execute();
    $taskRow=$taskStmt->get_result()->fetch_assoc();
    if($taskRow && !empty($taskRow['title'])) $taskTitle=$taskRow['title'];
    $taskStmt->close();
}

$stmt=$conn->prepare("UPDATE tracs_side_tasks SET ".implode(',',$sets)." WHERE id=? AND user_id=?");
$stmt->bind_param('iii',$done,$id,$uid);
if(!$stmt->execute()||$stmt->affected_rows===0) fail('Not found',404);
$stmt->close();
$note=$done?'Checklist item completed and archived from active ticker':'Checklist item reopened';
$log=$conn->prepare("INSERT INTO tracs_side_task_logs (task_id,user_id,note,created_at) VALUES (?,?,?,NOW())");
if($log){
    $log->bind_param('iis',$id,$uid,$note);
    $log->execute();
    $log->close();
}
logAct($conn,$uid,($done?'completed':'updated'),'Checklist',"Task marked ".($done?'complete':'incomplete'),$id);
if ($done) {
    $shiftActivity = new ShiftActivityService($conn, $uid);
    $shiftActivity->logActivity('checklist', $id, "Checklist completed: {$taskTitle}", null, 'completed');
    tickerEvent($conn, $uid, "Checklist completed: {$taskTitle}", 'success', 'checklist', $id);
}
ok(null,'Updated');
