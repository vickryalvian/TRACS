<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/dobby_events.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$endpoint = trim((string)($_ENV['DOBBY_INGEST_URL'] ?? getenv('DOBBY_INGEST_URL') ?: ''));
$secret = trim((string)($_ENV['DOBBY_INGEST_SECRET'] ?? getenv('DOBBY_INGEST_SECRET') ?: ''));
if ($endpoint === '' || $secret === '') {
    fwrite(STDOUT, "DOBBY delivery skipped: endpoint or secret is not configured.\n");
    exit(0);
}

if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !in_array(parse_url($endpoint, PHP_URL_SCHEME), ['https', 'http'], true)) {
    fwrite(STDERR, "DOBBY delivery skipped: invalid endpoint.\n");
    exit(0);
}

$limit = max(1, min(50, (int)($_ENV['DOBBY_DELIVERY_LIMIT'] ?? getenv('DOBBY_DELIVERY_LIMIT') ?: 25)));
$stmt = $conn->prepare("
    SELECT *
    FROM tracs_dobby_event_outbox
    WHERE status IN ('queued','failed')
      AND next_attempt_at <= NOW()
    ORDER BY id ASC
    LIMIT ?
");
if (!$stmt) {
    fwrite(STDERR, "DOBBY delivery skipped: outbox query unavailable.\n");
    exit(0);
}
$stmt->bind_param('i', $limit);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($events as $row) {
    $payload = [
        'id' => (string)$row['event_id'],
        'source' => (string)$row['source'],
        'type' => (string)$row['event_type'],
        'category' => (string)$row['category'],
        'severity' => (string)$row['severity'],
        'resource' => (string)$row['resource'],
        'actor' => json_decode((string)($row['actor_json'] ?? '{}'), true) ?: null,
        'summary' => (string)$row['summary'],
        'metadata' => json_decode((string)($row['metadata_json'] ?? '{}'), true) ?: [],
        'occurredAt' => date(DATE_ATOM, strtotime((string)$row['occurred_at'])),
        'schemaVersion' => (int)$row['schema_version'],
        'correlationId' => $row['correlation_id'] !== null ? (string)$row['correlation_id'] : null,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        mark_failed($conn, (int)$row['id'], 'json_encode_failed', (int)$row['attempts']);
        continue;
    }
    $timestamp = (string)round(microtime(true) * 1000);
    $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Dobby-Timestamp: ' . $timestamp,
            'X-Dobby-Signature: ' . $signature,
        ],
    ]);
    curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        mark_sent($conn, (int)$row['id']);
        fwrite(STDOUT, "Delivered DOBBY event {$payload['id']} ({$payload['type']}).\n");
    } else {
        mark_failed($conn, (int)$row['id'], $error !== '' ? $error : "HTTP {$status}", (int)$row['attempts']);
    }
}

exit(0);

function mark_sent(mysqli $conn, int $id): void
{
    $stmt = $conn->prepare("UPDATE tracs_dobby_event_outbox SET status='sent', delivered_at=NOW(), last_error=NULL, updated_at=NOW() WHERE id=?");
    if (!$stmt) return;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

function mark_failed(mysqli $conn, int $id, string $error, int $attempts): void
{
    $nextAttempts = $attempts + 1;
    $delay = min(3600, 30 * (2 ** min(6, $attempts)));
    $safeError = tracs_dobby_text($error, 240);
    $stmt = $conn->prepare("UPDATE tracs_dobby_event_outbox SET status='failed', attempts=?, next_attempt_at=DATE_ADD(NOW(), INTERVAL ? SECOND), last_error=?, updated_at=NOW() WHERE id=?");
    if (!$stmt) return;
    $stmt->bind_param('iisi', $nextAttempts, $delay, $safeError, $id);
    $stmt->execute();
    $stmt->close();
}
