<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/dobby_notifications.php';

$down = tracs_dobby_format_notification([
    'event' => 'monitor.down',
    'source' => 'TRACS Monitoring',
    'timestamp' => '2026-09-08T20:41:00+07:00',
    'metadata' => ['name' => 'example.com', 'status' => 'critical', 'latency_ms' => null, 'packet_loss_percent' => 100],
]);
assert(str_contains($down, 'Dobby detected a problem'));
assert(str_contains($down, 'example.com'));
assert(str_contains($down, '20:41 WIB'));

$recovered = tracs_dobby_format_notification([
    'event' => 'monitor.recovered',
    'timestamp' => '2026-09-08T20:48:00+07:00',
    'metadata' => ['name' => 'example.com', 'status' => 'healthy', 'latency_ms' => 21.5],
]);
assert(str_contains($recovered, 'back online'));
assert(str_contains($recovered, 'Everything looks healthy again'));

$deploy = tracs_dobby_format_notification([
    'event' => 'deployment.completed',
    'message' => 'TRACS deployment completed.',
    'timestamp' => '2026-09-08T20:52:00+07:00',
    'metadata' => ['branch' => 'main', 'commit' => 'a82f33c1234567890', 'environment' => 'production', 'stage' => 'complete'],
]);
assert(str_contains($deploy, 'Dobby noticed a TRACS update'));
assert(str_contains($deploy, 'Commit: a82f33c12345'));
assert(str_contains($deploy, 'Deployment status: ✅ Success'));

echo "Dobby notification formatter checks passed.\n";
