<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_realtime_balance_transfer(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("
        SELECT bt.*,
               COALESCE(NULLIF(bt.created_by_name,''), NULLIF(u.name,''), u.email, NULLIF(bt.admin_name,''), 'System') AS creator_name
        FROM balance_transfers bt
        LEFT JOIN tracs_users u ON bt.created_by = u.id
        WHERE bt.id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $row['id'] = (int)$row['id'];
    $row['amount'] = (float)$row['amount'];
    return $row;
}

function tracs_realtime_domain_transfer(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("
        SELECT dt.*,
               COALESCE(NULLIF(dt.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
        FROM domain_transfers dt
        LEFT JOIN tracs_users u ON dt.created_by = u.id
        WHERE dt.id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $row['id'] = (int)$row['id'];
    return $row;
}

function tracs_realtime_feedback(mysqli $conn, int $id): ?array {
    require_once __DIR__ . '/../../modules/cancellation-feedback/model.php';
    $model = new CancellationFeedbackModel($conn);
    $row = $model->getById($id);
    if (!$row) return null;

    $services = cf_decode_multi_value($row['cancelled_service'] ?? '');
    $reasons = cf_decode_multi_value($row['cancellation_reason'] ?? '');
    $row['id'] = (int)$row['id'];
    $row['cancelled_services'] = $services;
    $row['cancelled_service_display'] = implode(', ', $services);
    $row['cancellation_reasons'] = $reasons;
    $row['cancellation_reason_display'] = implode(', ', $reasons);
    $row['submitter_display'] = $row['submitter_display'] ?? $row['creator_name'] ?? $row['submitter_name'] ?? 'System';
    return $row;
}
