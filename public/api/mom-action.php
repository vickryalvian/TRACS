<?php
require '_bootstrap.php';
require_once __DIR__.'/../modules/mom/controller.php';

$MC = new MOMController($conn, $uid);
$input = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);
$action = $input['action'] ?? '';

function mom_legacy_ok(array $data=[]): void {
  echo json_encode(array_merge(['success'=>true], $data));
  exit;
}

function mom_legacy_fail(string $error='Error', int $code=400): void {
  http_response_code($code);
  echo json_encode(['success'=>false, 'error'=>$error]);
  exit;
}

if(!$MC->isInstalled()) {
  mom_legacy_fail('MOM database tables are not installed. Run config/mom_database_schema.sql first.', 503);
}

try {
  switch($action) {
    case 'create_meeting':
      $id = $MC->createMOM(
        trim($input['title'] ?? 'Untitled Meeting'),
        trim($input['meeting_type'] ?? 'weekly'),
        trim($input['objective'] ?? ''),
        trim($input['participants'] ?? ''),
        trim($input['meeting_at'] ?? ''),
        trim($input['meeting_url'] ?? '')
      );
      $id ? mom_legacy_ok(['meeting_id'=>$id]) : mom_legacy_fail('Failed to create meeting');

    case 'update_meeting':
      $id = (int)($input['mid'] ?? 0);
      $mom = $MC->getMOM($id);
      if(!$mom) mom_legacy_fail('Meeting not found', 404);
      $ok = $MC->updateMOM(
        $id,
        trim($input['title'] ?? $mom['title']),
        trim($input['objective'] ?? $mom['objective']),
        trim($input['participants'] ?? $mom['participants']),
        trim($input['meeting_type'] ?? $mom['type']),
        trim($input['status'] ?? $mom['status']),
        trim($input['meeting_at'] ?? ($mom['meeting_at'] ?? '')),
        trim($input['meeting_url'] ?? ($mom['meeting_url'] ?? ''))
      );
      $ok ? mom_legacy_ok() : mom_legacy_fail('Failed to update meeting');

    case 'delete_meeting':
      $MC->deleteMOM((int)($input['mid'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to delete meeting');

    case 'add_note':
      $id = $MC->addDiscussionNote((int)($input['mid'] ?? 0), trim($input['note_text'] ?? ''), trim($input['note_type'] ?? 'discussion'));
      $id ? mom_legacy_ok(['note_id'=>$id]) : mom_legacy_fail('Failed to add note');

    case 'delete_note':
      $MC->deleteNote((int)($input['noteid'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to delete note');

    case 'add_decision':
      $id = $MC->addDecision((int)($input['mid'] ?? 0), trim($input['decision_text'] ?? ''));
      $id ? mom_legacy_ok(['decision_id'=>$id]) : mom_legacy_fail('Failed to add decision');

    case 'delete_decision':
      $MC->deleteDecision((int)($input['did'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to delete decision');

    case 'add_action':
      $id = $MC->addActionItem(
        (int)($input['mid'] ?? 0),
        trim($input['action_text'] ?? ''),
        trim($input['description'] ?? ''),
        trim($input['assigned_to'] ?? ''),
        trim($input['priority'] ?? 'medium'),
        !empty($input['due_date']) ? $input['due_date'] : null
      );
      $id ? mom_legacy_ok(['action_id'=>$id]) : mom_legacy_fail('Failed to add action');

    case 'update_action_status':
      $completed = ($input['status'] ?? 'pending') === 'completed';
      $MC->completeAction((int)($input['aid'] ?? 0), $completed) ? mom_legacy_ok() : mom_legacy_fail('Failed to update action');

    case 'delete_action':
      $MC->deleteActionItem((int)($input['aid'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to delete action');

    case 'add_agenda_item':
      $id = $MC->addAgendaItem((int)($input['mid'] ?? 0), trim($input['item_text'] ?? ''));
      $id ? mom_legacy_ok(['agenda_id'=>$id]) : mom_legacy_fail('Failed to add agenda item');

    case 'update_agenda_status':
      $MC->updateAgendaItem((int)($input['agendaid'] ?? 0), '', '', trim($input['status'] ?? 'pending')) ? mom_legacy_ok() : mom_legacy_fail('Failed to update agenda item');

    case 'delete_agenda_item':
      $MC->deleteAgendaItem((int)($input['agendaid'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to delete agenda item');

    case 'add_reminder_from_action':
      $id = $MC->createReminderFromAction((int)($input['aid'] ?? 0));
      $id ? mom_legacy_ok(['reminder_id'=>$id]) : mom_legacy_fail('Failed to create reminder');

    case 'link_case':
      $MC->linkCaseToMOM((int)($input['mid'] ?? 0), (int)($input['case_id'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to link case');

    default:
      mom_legacy_fail('Unknown action');
  }
} catch(Throwable $e) {
  error_log('TRACS legacy MOM action failed: ' . $e->getMessage());
  mom_legacy_fail('Request failed', 500);
}
