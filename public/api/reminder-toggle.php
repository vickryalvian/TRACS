<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/ShiftActivityService.php';

$id=(int)($body['id']??0); if(!$id) fail('ID required');
$done=(int)(bool)($body['is_completed']??0);

function reminderColumnExists(mysqli $conn, string $column): bool {
    $stmt=$conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tracs_reminders'
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
if(reminderColumnExists($conn,'completed_at')) $sets[]=$done ? "completed_at=NOW()" : "completed_at=NULL";
if(reminderColumnExists($conn,'archived_at')) $sets[]="archived_at=NULL";
if(reminderColumnExists($conn,'reset_at')) $sets[]="reset_at=NULL";
if(reminderColumnExists($conn,'completed_by')) $sets[]=$done ? "completed_by={$uid}" : "completed_by=NULL";

$reminderTitle = "reminder #{$id}";
$remStmt = $conn->prepare("SELECT title FROM tracs_reminders WHERE id=? AND user_id=? LIMIT 1");
if($remStmt){
    $remStmt->bind_param('ii',$id,$uid);
    $remStmt->execute();
    $remRow=$remStmt->get_result()->fetch_assoc();
    if($remRow && !empty($remRow['title'])) $reminderTitle=$remRow['title'];
    $remStmt->close();
}

$stmt=$conn->prepare("UPDATE tracs_reminders SET ".implode(',',$sets)." WHERE id=? AND user_id=?");
$stmt->bind_param('iii',$done,$id,$uid);
if(!$stmt->execute()||$stmt->affected_rows===0) fail('Not found',404);
$stmt->close();
$act=$done?'completed':'uncompleted';
logAct($conn,$uid,$act,'Reminders',"Reminder marked as ".($done?'complete':'incomplete'),$id);
if ($done) {
    $shiftActivity = new ShiftActivityService($conn, $uid);
    $shiftActivity->logActivity('reminder', $id, "Reminder completed: {$reminderTitle}", null, 'completed');
    tickerEvent($conn, $uid, "Reminder completed: {$reminderTitle}", 'success', 'reminders', $id);
}
ok(null,'Updated');
