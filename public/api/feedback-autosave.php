<?php
/**
 * TRACS — API: Feedback Auto-Save (partial field patch)
 *
 * Accepts a JSON POST body: { "id": int, "field": string, "value": mixed }
 * Updates only the specified field on the given feedback record.
 * Returns: { "success": bool, "message": string, "updated_at": string|null }
 *
 * This endpoint is intentionally narrow — it never rewrites an entire record.
 * Full-record updates still go through feedback-update.php.
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';
require_once __DIR__ . '/_realtime_payloads.php';

// ── Parse JSON body ───────────────────────────────────────────────────────────
$id    = (int)($body['id']    ?? 0);
$field = trim((string)($body['field'] ?? ''));
$value = $body['value'] ?? '';

// ── Basic sanity checks ───────────────────────────────────────────────────────
if ($id <= 0) {
    fail('Missing or invalid feedback ID.', 400);
}
if ($field === '') {
    fail('Field name is required.', 400);
}

// ── Field-level validation ────────────────────────────────────────────────────
$allowedFields = [
    'cancelled_service',
    'cancellation_reason',
    'additional_details',
    'whmcs_reference',
    'email_address',
    'payment_resolution',
];

if (!in_array($field, $allowedFields, true)) {
    fail('Unknown or disallowed field: ' . $field, 400);
}

// Multi-value fields (service, reason) arrive as JSON arrays; encode for storage.
if ($field === 'cancelled_service') {
    $services = cf_filter_allowed_values($value, cf_allowed_services());
    if (empty($services)) {
        fail('At least one valid service must be selected.', 422);
    }
    $value = cf_encode_multi_value($services);
}

if ($field === 'cancellation_reason') {
    $reasons = cf_filter_allowed_values($value, cf_allowed_reasons());
    if (empty($reasons)) {
        fail('At least one valid reason must be selected.', 422);
    }
    $value = cf_encode_multi_value($reasons);
}

if ($field === 'payment_resolution') {
    $value = trim((string)$value);
    if ($value !== '' && !in_array($value, cf_allowed_resolutions(), true)) {
        fail('Invalid payment resolution value.', 422);
    }
}

if ($field === 'email_address') {
    $value = trim((string)$value);
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        fail('Email address is not valid.', 422);
    }
}

if (in_array($field, ['additional_details', 'whmcs_reference'], true)) {
    $value = trim((string)$value);
}

// ── Access control — confirm record exists and user can access it ──────────────
if (!tracs_can_view_feedback($conn, $id)) {
    fail('Feedback record not found.', 404);
}

// ── Perform the patch ─────────────────────────────────────────────────────────
$controller = new CancellationFeedbackController($conn, $uid);
$result     = $controller->patchFeedbackField($id, $field, $value);

if ($result === false) {
    // Log the server-side failure for debugging
    error_log("[TRACS] feedback-autosave: patchFeedbackField failed — id={$id} field={$field} uid={$uid}");
    fail('Failed to save field. Please try again.', 500);
}

ok([
    'updated_at' => $result['updated_at'],
    'record' => tracs_realtime_feedback($conn, $id),
], 'Saved');
