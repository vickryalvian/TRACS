<?php

require_once __DIR__ . '/_common.php';
calendar_require_method('POST');
calendar_require_manage();
if (!calendar_ensure_schema($conn)) fail('Calendar storage is unavailable.', 503);

$input = calendar_validate_payload($body);
calendar_validate_assignee_scope($conn, $input['assigned_user_id']);
$stmt = $conn->prepare(
    "INSERT INTO calendar_events
      (title,event_type,event_date,start_time,end_time,status,assigned_user_id,source_module,source_id,
       notes,visibility,reminder_minutes,recurrence_rule,created_by,created_at,updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
);
if (!$stmt) fail('Unable to prepare calendar schedule.', 500);
$stmt->bind_param(
    'ssssssisissisi',
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
    $uid
);
if (!$stmt->execute()) {
    $stmt->close();
    fail('Unable to create calendar schedule.', 500);
}
$id = (int)$stmt->insert_id;
$stmt->close();
logAct($conn, $uid, 'created', 'Calendar', 'Created manual calendar schedule: ' . $input['title'], $id);
ok(['id' => $id], 'Schedule booked successfully.');
