<?php
/**
 * TRACS MOM API Endpoint
 * Secure JSON API for Minutes of Meeting operations
 * 
 * Security:
 * - Session validation required
 * - User ownership verification
 * - Input sanitization
 * - CSRF protection via session
 * - Rate limiting ready
 */

require '_bootstrap.php';

// Load controllers
require_once __DIR__.'/../modules/mom/controller.php';
require_once __DIR__.'/../../modules/reminder/controller.php';
require_once __DIR__.'/../../modules/case/controller.php';

$MC = new MOMController($conn, $uid);
$RC = new ReminderController($conn, $uid);
$CC = new CaseController($conn, $uid);

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = trim($input['action'] ?? '');

// Response helper
function respond($ok, $data = [], $msg = '') {
  header('Content-Type: application/json');
  echo json_encode(array_merge(['ok' => (bool)$ok, 'msg' => $msg], $data));
  exit;
}

if(!$MC->isInstalled()) {
  http_response_code(503);
  respond(false, [], 'MOM storage is not available.');
}

// ═══════════════════════════════════════════════════════════════
// CORE MOM OPERATIONS
// ═══════════════════════════════════════════════════════════════

if($action === 'create_mom') {
  $title = trim($input['title'] ?? '');
  $type = trim($input['type'] ?? 'weekly');
  $objective = trim($input['objective'] ?? '');
  $participants = trim($input['participants'] ?? '');
  $meeting_at = trim($input['meeting_at'] ?? '');
  $meeting_url = trim($input['meeting_url'] ?? '');
  
  if(!$title) {
    respond(false, [], 'Title is required');
  }
  
  if(!in_array($type, ['weekly', 'training', 'coordination', 'urgent'])) {
    $type = 'weekly';
  }
  
  $mom_id = $MC->createMOM($title, $type, $objective, $participants, $meeting_at, $meeting_url);
  if($mom_id) {
    respond(true, ['mom_id' => $mom_id], 'Meeting created');
  } else {
    respond(false, [], 'Failed to create meeting');
  }
}

else if($action === 'update_mom') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $title = trim($input['title'] ?? '');
  $type = trim($input['type'] ?? 'weekly');
  $meeting_at = trim($input['meeting_at'] ?? '');
  $meeting_url = trim($input['meeting_url'] ?? '');
  
  if(!$mom_id || !$title) {
    respond(false, [], 'Invalid parameters');
  }
  
  // Verify ownership
  $mom = $MC->getMOM($mom_id);
  if(!$mom) {
    respond(false, [], 'Meeting not found');
  }
  
  if($MC->updateMOM($mom_id, $title, 
    $input['objective'] ?? '', 
    $input['participants'] ?? '', 
    $type,
    $mom['status'] ?? 'upcoming',
    $meeting_at,
    $meeting_url)) {
    respond(true, [], 'Meeting updated');
  } else {
    respond(false, [], 'Failed to update meeting');
  }
}

else if($action === 'update_objective') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $objective = trim($input['objective'] ?? '');
  
  $mom = $MC->getMOM($mom_id);
  if(!$mom) {
    respond(false, [], 'Meeting not found');
  }
  
  if($MC->updateMOM($mom_id, $mom['title'], $objective, $mom['participants'], $mom['type'], $mom['status'] ?? 'upcoming', $mom['meeting_at'] ?? null, $mom['meeting_url'] ?? null)) {
    respond(true, [], 'Objective updated');
  } else {
    respond(false, [], 'Failed to update objective');
  }
}

else if($action === 'update_participants') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $participants = trim($input['participants'] ?? '');
  
  $mom = $MC->getMOM($mom_id);
  if(!$mom) {
    respond(false, [], 'Meeting not found');
  }
  
  if($MC->updateMOM($mom_id, $mom['title'], $mom['objective'], $participants, $mom['type'], $mom['status'] ?? 'upcoming', $mom['meeting_at'] ?? null, $mom['meeting_url'] ?? null)) {
    respond(true, [], 'Participants updated');
  } else {
    respond(false, [], 'Failed to update participants');
  }
}

else if($action === 'close_mom') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  
  $mom = $MC->getMOM($mom_id);
  if(!$mom) {
    respond(false, [], 'Meeting not found');
  }
  
  if($MC->closeMOM($mom_id)) {
    respond(true, [], 'Meeting completed');
  } else {
    respond(false, [], 'Failed to close meeting');
  }
}

else if($action === 'start_mom') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  if($MC->startMOM($mom_id)) {
    respond(true, [], 'Meeting started');
  }
  respond(false, [], 'Failed to start meeting');
}

else if($action === 'cancel_mom') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  if($MC->cancelMOM($mom_id)) {
    respond(true, [], 'Meeting cancelled');
  }
  respond(false, [], 'Failed to cancel meeting');
}

else if($action === 'save_summary') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $summary = trim($input['summary'] ?? '');
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  if($MC->saveSummary($mom_id, $summary)) {
    respond(true, [], 'MOM summary saved');
  }
  respond(false, [], 'Failed to save MOM summary');
}

else if($action === 'delete_mom') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  
  $mom = $MC->getMOM($mom_id);
  if(!$mom) {
    respond(false, [], 'Meeting not found');
  }
  
  if($MC->deleteMOM($mom_id)) {
    respond(true, [], 'Meeting deleted');
  }
  respond(false, [], 'Failed to delete meeting');
}

// ═══════════════════════════════════════════════════════════════
// AGENDA
// ═══════════════════════════════════════════════════════════════

else if($action === 'add_agenda_item') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $topic = trim($input['topic'] ?? '');
  
  if(!$mom_id || !$topic) {
    respond(false, [], 'Topic is required');
  }
  
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  
  $item_id = $MC->addAgendaItem($mom_id, $topic);
  if($item_id) {
    respond(true, ['item_id' => $item_id], 'Agenda item added');
  } else {
    respond(false, [], 'Failed to add agenda item');
  }
}

else if($action === 'update_agenda_item') {
  $item_id = (int)($input['item_id'] ?? 0);
  $status = trim($input['status'] ?? 'pending');
  
  if(!in_array($status, ['pending', 'completed'])) {
    $status = 'pending';
  }
  
  if($MC->updateAgendaItem($item_id, '', '', $status)) {
    respond(true, [], 'Agenda item updated');
  } else {
    respond(false, [], 'Failed to update agenda item');
  }
}

else if($action === 'delete_agenda_item') {
  $item_id = (int)($input['item_id'] ?? 0);
  if($MC->deleteAgendaItem($item_id)) {
    respond(true, [], 'Agenda item deleted');
  }
  respond(false, [], 'Not found');
}

// ═══════════════════════════════════════════════════════════════
// DISCUSSION NOTES
// ═══════════════════════════════════════════════════════════════

else if($action === 'add_discussion_note') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $content = trim($input['content'] ?? '');
  $note_type = trim($input['note_type'] ?? 'discussion');
  
  if(!$mom_id || !$content) {
    respond(false, [], 'Content is required');
  }
  
  if(!in_array($note_type, ['discussion', 'decision', 'action', 'insight'])) {
    $note_type = 'discussion';
  }
  
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  
  $note_id = $MC->addDiscussionNote($mom_id, $content, $note_type);
  if($note_id) {
    respond(true, ['note_id' => $note_id], 'Note added');
  } else {
    respond(false, [], 'Failed to add note');
  }
}

else if($action === 'delete_note') {
  $note_id = (int)($input['note_id'] ?? 0);
  if($MC->deleteNote($note_id)) {
    respond(true, [], 'Note deleted');
  }
  respond(false, [], 'Not found');
}

// ═══════════════════════════════════════════════════════════════
// DECISIONS
// ═══════════════════════════════════════════════════════════════

else if($action === 'add_decision') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $decision = trim($input['decision'] ?? '');
  $rationale = trim($input['rationale'] ?? '');
  $owner = trim($input['owner'] ?? '');
  
  if(!$mom_id || !$decision) {
    respond(false, [], 'Decision text is required');
  }
  
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  
  $decision_id = $MC->addDecision($mom_id, $decision, $rationale, $owner);
  if($decision_id) {
    respond(true, ['decision_id' => $decision_id], 'Decision recorded');
  } else {
    respond(false, [], 'Failed to add decision');
  }
}

else if($action === 'delete_decision') {
  $decision_id = (int)($input['decision_id'] ?? 0);
  if($MC->deleteDecision($decision_id)) {
    respond(true, [], 'Decision deleted');
  }
  respond(false, [], 'Not found');
}

// ═══════════════════════════════════════════════════════════════
// ACTION ITEMS
// ═══════════════════════════════════════════════════════════════

else if($action === 'add_action_item') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $title = trim($input['title'] ?? '');
  $description = trim($input['description'] ?? '');
  $assigned_to = trim($input['assigned_to'] ?? '');
  $priority = trim($input['priority'] ?? 'medium');
  $due_date = trim($input['due_date'] ?? '');
  
  if(!$mom_id || !$title) {
    respond(false, [], 'Title is required');
  }
  
  if(!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
    $priority = 'medium';
  }
  
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  
  $action_id = $MC->addActionItem($mom_id, $title, $description, $assigned_to, $priority, 
    $due_date ? $due_date : null);
  
  if($action_id) {
    respond(true, ['action_id' => $action_id], 'Action item created');
  } else {
    respond(false, [], 'Failed to create action');
  }
}

else if($action === 'update_action_item') {
  $action_id = (int)($input['action_id'] ?? 0);
  $title = trim($input['title'] ?? '');
  $description = trim($input['description'] ?? '');
  $assigned_to = trim($input['assigned_to'] ?? '');
  $priority = trim($input['priority'] ?? 'medium');
  $due_date = trim($input['due_date'] ?? '');
  
  if(!$action_id || !$title) {
    respond(false, [], 'Title is required');
  }
  
  if(!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
    $priority = 'medium';
  }

  if($MC->updateActionItem($action_id, $title, $description, $assigned_to, $priority, $due_date ?: null)) {
    respond(true, [], 'Action item updated');
  } else {
    respond(false, [], 'Failed to update action');
  }
}

else if($action === 'complete_action') {
  $action_id = (int)($input['action_id'] ?? 0);
  $completed = filter_var($input['completed'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
  $completed = $completed ?? true;
  
  if(!$action_id) {
    respond(false, [], 'Action ID required');
  }
  
  if($MC->completeAction($action_id, $completed)) {
    respond(true, [], $completed ? 'Action completed' : 'Action reopened');
  } else {
    respond(false, [], 'Failed to complete action');
  }
}

else if($action === 'delete_action_item') {
  $action_id = (int)($input['action_id'] ?? 0);
  if($MC->deleteActionItem($action_id)) {
    respond(true, [], 'Action deleted');
  }
  respond(false, [], 'Not found');
}

// ═══════════════════════════════════════════════════════════════
// REMINDERS
// ═══════════════════════════════════════════════════════════════

else if($action === 'create_reminder_from_action') {
  $action_id = (int)($input['action_id'] ?? 0);
  
  if(!$action_id) {
    respond(false, [], 'Action ID required');
  }
  
  $rem_id = $MC->createReminderFromAction($action_id);
  if($rem_id) {
    respond(true, ['reminder_id' => $rem_id], 'Reminder created');
  } else {
    respond(false, [], 'Failed to create reminder');
  }
}

// ═══════════════════════════════════════════════════════════════
// CASES
// ═══════════════════════════════════════════════════════════════

else if($action === 'link_case') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $case_id = (int)($input['case_id'] ?? 0);
  
  if(!$mom_id || !$case_id) {
    respond(false, [], 'MOM ID and Case ID required');
  }
  
  if(!$MC->getMOM($mom_id)) {
    respond(false, [], 'Meeting not found');
  }
  
  if(!$MC->getCaseForUser($case_id)) {
    respond(false, [], 'Case not found');
  }
  
  if($MC->linkCaseToMOM($mom_id, $case_id)) {
    respond(true, [], 'Case linked');
  } else {
    respond(false, [], 'Failed to link case');
  }
}

else if($action === 'create_case_from_action') {
  $action_id = (int)($input['action_id'] ?? 0);
  
  if(!$action_id) {
    respond(false, [], 'Action ID required');
  }
  
  $case_id = $MC->createCaseFromAction($action_id);
  if($case_id) {
    respond(true, ['case_id' => $case_id], 'Case created and linked');
  } else {
    respond(false, [], 'Failed to create case');
  }
}

else if($action === 'unlink_case') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $case_id = (int)($input['case_id'] ?? 0);
  if(!$mom_id || !$case_id) {
    respond(false, [], 'MOM ID and Case ID required');
  }
  if($MC->unlinkCase($mom_id, $case_id)) {
    respond(true, [], 'Case unlinked');
  }
  respond(false, [], 'Failed to unlink case');
}

else if($action === 'resolve_linked_case') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $case_id = (int)($input['case_id'] ?? 0);
  $status = trim($input['status'] ?? 'completed');
  $note = trim($input['note'] ?? '');
  if(!$mom_id || !$case_id) {
    respond(false, [], 'MOM ID and Case ID required');
  }
  if($MC->resolveLinkedCaseFromMOM($mom_id, $case_id, $status, $note)) {
    respond(true, [], 'Linked case updated');
  }
  respond(false, [], 'Failed to update linked case');
}

else if($action === 'upload_screenshot') {
  $mom_id = (int)($input['mom_id'] ?? 0);
  $image_data = $input['image_data'] ?? '';
  if(!$mom_id || !$image_data) {
    respond(false, [], 'Screenshot is required');
  }
  $shot_id = $MC->attachScreenshot($mom_id, $image_data, 'general', null);
  if($shot_id) {
    respond(true, ['screenshot_id' => $shot_id], 'Screenshot uploaded');
  }
  respond(false, [], 'Failed to upload screenshot');
}

else if($action === 'delete_screenshot') {
  $shot_id = (int)($input['screenshot_id'] ?? 0);
  if(!$shot_id) {
    respond(false, [], 'Screenshot ID required');
  }
  if($MC->deleteScreenshot($shot_id)) {
    respond(true, [], 'Screenshot deleted');
  }
  respond(false, [], 'Failed to delete screenshot');
}

// ═══════════════════════════════════════════════════════════════
// DEFAULT
// ═══════════════════════════════════════════════════════════════

else {
  respond(false, [], 'Unknown action');
}
?>
