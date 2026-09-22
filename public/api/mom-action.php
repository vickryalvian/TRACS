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

function mom_legacy_require_mom(MOMController $MC, array $input): int {
  $momId = (int)($input['mid'] ?? 0);
  if($momId <= 0 || !$MC->getMOM($momId)) {
    mom_legacy_fail('Meeting or item not found', 404);
  }
  return $momId;
}

if(!$MC->isInstalled()) {
  mom_legacy_fail('MOM storage is not available.', 503);
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
      // Deletion is restricted to supervisor-tier roles and above — see
      // tracs_user_can_delete_moms(). This legacy endpoint has no frontend
      // caller today but must not be a bypass around the api_mom.php check.
      if(!tracs_user_can_delete_moms($conn, $uid)) {
        mom_legacy_fail('You do not have permission to delete meeting minutes.', 403);
      }
      $legacy_mom_id = (int)($input['mid'] ?? 0);
      if(!$MC->getMOM($legacy_mom_id)) {
        mom_legacy_fail('Meeting not found', 404);
      }
      $MC->deleteMOM($legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Failed to delete meeting');

    case 'add_note':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $id = $MC->addDiscussionNote($legacy_mom_id, trim($input['note_text'] ?? ''), trim($input['note_type'] ?? 'discussion'));
      $id ? mom_legacy_ok(['note_id'=>$id]) : mom_legacy_fail('Failed to add note');

    case 'delete_note':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $MC->deleteNote((int)($input['noteid'] ?? 0), $legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Meeting or item not found', 404);

    case 'add_decision':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $id = $MC->addDecision($legacy_mom_id, trim($input['decision_text'] ?? ''));
      $id ? mom_legacy_ok(['decision_id'=>$id]) : mom_legacy_fail('Failed to add decision');

    case 'delete_decision':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $MC->deleteDecision((int)($input['did'] ?? 0), $legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Meeting or item not found', 404);

    case 'add_action':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $id = $MC->addActionItem(
        $legacy_mom_id,
        trim($input['action_text'] ?? ''),
        trim($input['description'] ?? ''),
        trim($input['assigned_to'] ?? ''),
        trim($input['priority'] ?? 'medium'),
        !empty($input['due_date']) ? $input['due_date'] : null
      );
      $id ? mom_legacy_ok(['action_id'=>$id]) : mom_legacy_fail('Failed to add action');

    case 'update_action_status':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $completed = ($input['status'] ?? 'pending') === 'completed';
      $MC->completeAction((int)($input['aid'] ?? 0), $completed, $legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Meeting or item not found', 404);

    case 'delete_action':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $MC->deleteActionItem((int)($input['aid'] ?? 0), $legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Meeting or item not found', 404);

    case 'add_agenda_item':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $id = $MC->addAgendaItem($legacy_mom_id, trim($input['item_text'] ?? ''));
      $id ? mom_legacy_ok(['agenda_id'=>$id]) : mom_legacy_fail('Failed to add agenda item');

    case 'update_agenda_status':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $MC->updateAgendaItem((int)($input['agendaid'] ?? 0), null, null, trim($input['status'] ?? 'pending'), $legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Meeting or item not found', 404);

    case 'delete_agenda_item':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $MC->deleteAgendaItem((int)($input['agendaid'] ?? 0), $legacy_mom_id) ? mom_legacy_ok() : mom_legacy_fail('Meeting or item not found', 404);

    case 'add_reminder_from_action':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $legacy_action_id = (int)($input['aid'] ?? 0);
      $legacy_action = $MC->getActionItem($legacy_action_id);
      if(!$legacy_action || (int)$legacy_action['mom_id'] !== $legacy_mom_id) mom_legacy_fail('Meeting or item not found', 404);
      $id = $MC->createReminderFromAction($legacy_action_id);
      $id ? mom_legacy_ok(['reminder_id'=>$id]) : mom_legacy_fail('Failed to create reminder');

    case 'link_case':
      $legacy_mom_id = mom_legacy_require_mom($MC, $input);
      $MC->linkCaseToMOM($legacy_mom_id, (int)($input['case_id'] ?? 0)) ? mom_legacy_ok() : mom_legacy_fail('Failed to link case');

    default:
      mom_legacy_fail('Unknown action');
  }
} catch(Throwable $e) {
  error_log('TRACS legacy MOM action failed: ' . $e->getMessage());
  mom_legacy_fail('Request failed', 500);
}
