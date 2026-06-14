<?php

require_once __DIR__ . '/_common.php';
calendar_require_method('POST', 'PATCH');

calendar_require_manage();
if (!calendar_ensure_schema($conn)) fail('Calendar storage is unavailable.', 503);
$id = (int)($body['id'] ?? 0);
if ($id <= 0) calendar_validation_fail(['id' => 'Schedule ID is required.']);
$input = calendar_validate_payload($body);
$scope = calendar_management_scope('calendar_events');
calendar_validate_assignee_scope($conn, $input['assigned_user_id']);
$stmt = $conn->prepare(
    "UPDATE calendar_events SET
       title=?,event_type=?,event_date=?,start_time=?,end_time=?,status=?,assigned_user_id=?,
       source_module=?,source_id=?,notes=?,visibility=?,reminder_minutes=?,recurrence_rule=?,updated_at=NOW()
     WHERE id=? AND deleted_at IS NULL {$scope[0]}"
);
if (!$stmt) fail('Unable to prepare calendar update.', 500);
$types = 'ssssssisissisi' . $scope[1];
$params = [
    $input['title'],
    $input['event_type'],
    $input['event_date'],
    $input['start_time'],
    $input['end_time'],
    $input['status'],
    $input['assigned_user_id'],
    $input['source_module'],
    $input['source_id'],
    $input['notes'],
    $input['visibility'],
    $input['reminder_minutes'],
    $input['recurrence_rule'],
    $id,
    ...$scope[2],
];
$stmt->bind_param(
    $types,
    ...$params
);
$executed = $stmt->execute();
$updated = $stmt->affected_rows;
$stmt->close();
if (!$executed) fail('Unable to update calendar schedule.', 500);
if ($updated < 1) {
    $checkSql = "SELECT id FROM calendar_events WHERE id=? AND deleted_at IS NULL {$scope[0]} LIMIT 1";
    $check = $conn->prepare($checkSql);
    if (!$check) fail('Unable to verify calendar schedule.', 500);
    $checkTypes = 'i' . $scope[1];
    $checkParams = [$id, ...$scope[2]];
    $check->bind_param($checkTypes, ...$checkParams);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if (!$exists) fail_not_found();
}
logAct($conn, $uid, 'updated', 'Calendar', 'Updated manual calendar schedule: ' . $input['title'], $id);
ok(['id' => $id], 'Schedule updated successfully.');
