<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../core/infrastructure_servers.php';
require_once __DIR__ . '/../../core/infrastructure_ping.php';

api_require_permissions(['dashboard.view']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail('POST required.', 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$code = strtoupper(trim((string)($payload['code'] ?? '')));
if ($code === '' || mb_strlen($code) > 8 || !preg_match('/^[A-Z0-9_-]+$/', $code)) {
    fail('Invalid server code.', 422);
}

$method = (string)($payload['method'] ?? 'icmp');
if (!in_array($method, TRACS_INFRA_SERVER_METHODS, true)) {
    fail('Invalid monitoring method.', 422);
}

$name = trim((string)($payload['name'] ?? ''));
$region = trim((string)($payload['region'] ?? ''));
$country = trim((string)($payload['country'] ?? ''));
$provider = trim((string)($payload['provider'] ?? ''));
if ($name === '' || $region === '' || $country === '' || $provider === '') {
    fail('Name, region, country, and provider are required.', 422);
}

$targetHost = trim((string)($payload['target_host'] ?? ''));
$healthUrl = trim((string)($payload['health_url'] ?? ''));

if (in_array($method, ['icmp', 'tcp'], true)) {
    if (!tracs_infra_ping_host_is_valid($targetHost)) {
        fail('Invalid IP address or hostname.', 422);
    }
}
if ($method === 'tcp') {
    $port = (int)($payload['target_port'] ?? 0);
    if ($port < 1 || $port > 65535) {
        fail('Port must be between 1 and 65535.', 422);
    }
}
if ($method === 'http') {
    if ($healthUrl === '' || !preg_match('#^https?://#i', $healthUrl) || mb_strlen($healthUrl) > 512) {
        fail('A valid http:// or https:// health check URL is required.', 422);
    }
}

$packetCount = max(1, min(10, (int)($payload['packet_count'] ?? 4)));
$timeoutSeconds = max(1, min(30, (int)($payload['timeout_seconds'] ?? 5)));
$intervalSeconds = max(30, min(3600, (int)($payload['interval_seconds'] ?? 60)));
$expectedStatus = isset($payload['expected_status']) && $payload['expected_status'] !== ''
    ? max(100, min(599, (int)$payload['expected_status']))
    : null;

$row = tracs_infra_server_upsert($conn, [
    'code' => $code,
    'name' => $name,
    'region' => $region,
    'country' => $country,
    'provider' => $provider,
    'method' => $method,
    'target_host' => $targetHost,
    'target_port' => $method === 'tcp' ? (int)($payload['target_port'] ?? 0) : null,
    'health_url' => $method === 'http' ? $healthUrl : '',
    'expected_status' => $method === 'http' ? $expectedStatus : null,
    'expected_keyword' => $method === 'http' ? trim((string)($payload['expected_keyword'] ?? '')) : '',
    'packet_count' => $method === 'icmp' ? $packetCount : null,
    'timeout_seconds' => $timeoutSeconds,
    'interval_seconds' => $intervalSeconds,
], $uid);

if (!$row) {
    fail('The server could not be saved.', 500);
}

logAct($conn, $uid, 'created', 'Infrastructure Pulse', "Registered monitoring target {$code} ({$method})");
ok($row, 'Server added to monitoring.');
