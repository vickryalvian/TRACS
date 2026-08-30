<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../core/infrastructure_servers.php';

api_require_permissions(['dashboard.view']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    fail('GET required.', 405);
}

ok(tracs_infra_server_list_active_for_json($conn));
