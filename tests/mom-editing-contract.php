<?php

$root = dirname(__DIR__);
$api = file_get_contents($root . '/public/api/api_mom.php');
$controller = file_get_contents($root . '/public/modules/mom/controller.php');
$javascript = file_get_contents($root . '/public/assets/mom-functions.js');
$page = file_get_contents($root . '/public/mom.php');

function mom_contract_assert(bool $condition, string $message): void {
  if(!$condition) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
  }
}

foreach(['update_agenda_item', 'update_discussion_note', 'update_decision', 'update_action_item'] as $action) {
  mom_contract_assert(str_contains($api, "action === '{$action}'"), "Missing {$action} API action");
}

foreach([
  'updateAgendaItem($item_id, $topic, $notes, $status, $mom_id)',
  'updateDiscussionNote($note_id, $content, $note_type, $mom_id)',
  'updateDecision($decision_id, $decision, $rationale, $owner, $status, $mom_id)',
  'updateActionItem($action_id, $title, $description, $assigned_to, $priority, $due_date ?: null, $mom_id)',
] as $call) {
  mom_contract_assert(str_contains($api, $call), "Mutation must validate its item against mom_id: {$call}");
}

foreach(['getAgendaItem', 'getDiscussionNote', 'getDecision', 'getActionItem'] as $getter) {
  mom_contract_assert(str_contains($controller, "function {$getter}"), "Missing canonical item getter {$getter}");
}

foreach(['editAgendaItem', 'editDiscussionNote', 'editDecision', 'editActionItem'] as $editor) {
  mom_contract_assert(str_contains($javascript, "function {$editor}"), "Missing browser editor {$editor}");
}

foreach(['data-topic=', 'data-content=', 'data-decision=', 'data-description='] as $attribute) {
  mom_contract_assert(str_contains($page, $attribute), "Missing editable item state {$attribute}");
}

mom_contract_assert(str_contains($javascript, "editingId ? 'update_agenda_item' : 'add_agenda_item'"), 'Agenda save must distinguish existing items');
mom_contract_assert(str_contains($javascript, "editingId ? 'update_discussion_note' : 'add_discussion_note'"), 'Note save must distinguish existing items');
mom_contract_assert(str_contains($javascript, "editingId ? 'update_decision' : 'add_decision'"), 'Decision save must distinguish existing items');
mom_contract_assert(str_contains($javascript, "editingId ? 'update_action_item' : 'add_action_item'"), 'Action save must distinguish existing items');

echo "PASS: MoM item editing and parent-validation contracts are present.\n";
