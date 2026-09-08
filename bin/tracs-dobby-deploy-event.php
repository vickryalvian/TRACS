<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/dobby_events.php';
require_once __DIR__ . '/../core/dobby_notifications.php';

$args = getopt('', ['type:', 'correlation:', 'stage::', 'summary::', 'environment::', 'host::', 'commit::']);
$type = tracs_dobby_text($args['type'] ?? '', 120);
if ($type === '') {
    fwrite(STDERR, "Missing --type.\n");
    exit(0);
}

$stage = tracs_dobby_text($args['stage'] ?? $type, 120);
$environment = tracs_dobby_text($args['environment'] ?? 'production', 80);
$host = tracs_dobby_text($args['host'] ?? php_uname('n'), 120);
$commit = tracs_dobby_text($args['commit'] ?? '', 64);
$summary = tracs_dobby_text($args['summary'] ?? $stage, 255);
$correlation = tracs_dobby_text($args['correlation'] ?? '', 120);

$event = [
    'source' => 'tracs.deployment',
    'type' => $type,
    'category' => 'deployment',
    'severity' => str_contains($type, 'failed') ? 'critical' : 'info',
    'resource' => 'deployment.tracs.production',
    'actor' => ['type' => 'system', 'displayName' => 'TRACS deploy script'],
    'summary' => $summary,
    'metadata' => [
        'application' => 'TRACS',
        'environment' => $environment,
        'host' => $host,
        'stage' => $stage,
        'commit' => $commit !== '' ? $commit : null,
        'privacy' => 'operational',
    ],
    'occurredAt' => date(DATE_ATOM),
    'correlationId' => $correlation !== '' ? $correlation : null,
];

tracs_dobby_event_enqueue($conn, $event);
tracs_dobby_notify([
    'event' => $type,
    'severity' => $event['severity'],
    'source' => 'TRACS Deployment',
    'title' => $summary,
    'message' => $summary,
    'timestamp' => $event['occurredAt'],
    'environment' => $environment,
    'metadata' => $event['metadata'] + ['branch' => getenv('BRANCH') ?: null],
    'deduplicationKey' => 'deployment:' . ($correlation !== '' ? $correlation : date('YmdHi')) . ':' . $type,
]);

exit(0);
