<?php
/**
 * checklist-sync.php
 *
 * Lightweight change-signature for the shared Operational Checklist. The client
 * polls this and only re-fetches the full list when the signature changes, so
 * checklist create/update/complete/delete by any user propagates to everyone
 * without a manual refresh (efficient polling — no full list transferred here).
 *
 * Method/permission enforced in _bootstrap.php (GET, checklist.view).
 */

require '_bootstrap.php';
require_once __DIR__ . '/../../modules/checklist/controller.php';

$KC = new ChecklistController($conn, $uid);
ok(['signature' => $KC->getSignature()], 'ok');
