<?php

require_once __DIR__ . '/_common.php';
calendar_require_method('POST', 'DELETE');
calendar_require_manage();
if (!calendar_ensure_schema($conn)) fail('Calendar storage is unavailable.', 503);

$id = (int)($body['id'] ?? 0);
if ($id <= 0) calendar_validation_fail(['id' => 'Schedule ID is required.']);
$scope = calendar_management_scope('calendar_events');
$stmt = $conn->prepare("UPDATE calendar_events SET deleted_at=NOW(),updated_at=NOW() WHERE id=? AND deleted_at IS NULL {$scope[0]}");
if (!$stmt) fail('Unable to prepare calendar delete.', 500);
$types = 'i' . $scope[1];
$params = [$id, ...$scope[2]];
$stmt->bind_param($types, ...$params);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();
if ($deleted < 1) fail_not_found();
logAct($conn, $uid, 'deleted', 'Calendar', 'Deleted manual calendar schedule', $id);
ok(['id' => $id], 'Schedule deleted.');
