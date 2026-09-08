<?php
declare(strict_types=1);

function tracs_dobby_supported_event_types(): array
{
    return [
        'client.created' => true,
        'client.updated' => true,
        'client.billing.created' => true,
        'case.created' => true,
        'case.resolved' => true,
        'deployment.started' => true,
        'deployment.backup.started' => true,
        'deployment.backup.completed' => true,
        'deployment.migration.started' => true,
        'deployment.migration.completed' => true,
        'deployment.healthcheck.started' => true,
        'deployment.completed' => true,
        'deployment.failed' => true,
        'rollback.started' => true,
        'rollback.completed' => true,
    ];
}

function tracs_dobby_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function tracs_dobby_text(mixed $value, int $max): string
{
    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value) ?? '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}

function tracs_dobby_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function tracs_dobby_actor(int $actorId, string $displayName): array
{
    return [
        'type' => $actorId > 0 ? 'user' : 'system',
        'id' => $actorId > 0 ? (string)$actorId : null,
        'displayName' => tracs_dobby_text($displayName !== '' ? $displayName : 'TRACS', 120),
    ];
}

function tracs_dobby_client_event_type(string $activityEvent): ?string
{
    return match ($activityEvent) {
        'client.created' => 'client.created',
        'client.updated', 'client.owner_changed' => 'client.updated',
        'client.billing_added',
        'client.payment_marked_paid',
        'client.tax_invoice_sent',
        'client.invoice_sent' => 'client.billing.created',
        default => null,
    };
}

function tracs_dobby_event_enqueue(mysqli $conn, array $event): bool
{
    try {
        if (!tracs_dobby_table_exists($conn, 'tracs_dobby_event_outbox')) {
            return false;
        }

        $type = tracs_dobby_text($event['type'] ?? '', 120);
        if (!isset(tracs_dobby_supported_event_types()[$type])) {
            return false;
        }

        $eventId = tracs_dobby_text($event['id'] ?? tracs_dobby_uuid(), 36);
        $source = tracs_dobby_text($event['source'] ?? 'tracs', 80);
        $category = tracs_dobby_text($event['category'] ?? 'business', 40);
        $severity = tracs_dobby_text($event['severity'] ?? 'info', 20);
        $resource = tracs_dobby_text($event['resource'] ?? 'tracs.activity', 160);
        $summary = tracs_dobby_text($event['summary'] ?? $type, 255);
        $correlationId = isset($event['correlationId']) ? tracs_dobby_text($event['correlationId'], 120) : null;
        $occurredAt = date('Y-m-d H:i:s');
        if (!empty($event['occurredAt'])) {
            $timestamp = strtotime((string)$event['occurredAt']);
            if ($timestamp !== false) {
                $occurredAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $actorJson = json_encode($event['actor'] ?? ['type' => 'system', 'displayName' => 'TRACS'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $metadataJson = json_encode($event['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($actorJson === false || $metadataJson === false) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO tracs_dobby_event_outbox
              (event_id, source, event_type, category, severity, resource, actor_json, summary, metadata_json, occurred_at, schema_version, correlation_id, status, next_attempt_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'queued', NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssssssssss', $eventId, $source, $type, $category, $severity, $resource, $actorJson, $summary, $metadataJson, $occurredAt, $correlationId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('TRACS DOBBY outbox enqueue skipped: ' . $e->getMessage());
        return false;
    }
}

function tracs_dobby_enqueue_client_activity(mysqli $conn, int $clientId, int $actorId, string $actorName, string $activityEvent, string $summary): bool
{
    $type = tracs_dobby_client_event_type($activityEvent);
    if ($type === null) {
        return false;
    }
    return tracs_dobby_event_enqueue($conn, [
        'source' => 'tracs',
        'type' => $type,
        'category' => 'business',
        'severity' => 'info',
        'resource' => 'tracs.activity',
        'actor' => tracs_dobby_actor($actorId, $actorName),
        'summary' => $type === 'client.billing.created' ? 'Client billing activity recorded' : tracs_dobby_text($summary, 180),
        'metadata' => [
            'clientId' => $clientId,
            'originalEvent' => $activityEvent,
            'privacy' => $type === 'client.billing.created' ? 'restricted' : 'normal',
        ],
        'occurredAt' => date(DATE_ATOM),
    ]);
}

function tracs_dobby_enqueue_case_event(mysqli $conn, string $type, int $caseId, int $actorId, string $actorName, string $title): bool
{
    return tracs_dobby_event_enqueue($conn, [
        'source' => 'tracs',
        'type' => $type,
        'category' => 'business',
        'severity' => $type === 'case.resolved' ? 'info' : 'warning',
        'resource' => 'tracs.activity',
        'actor' => tracs_dobby_actor($actorId, $actorName),
        'summary' => $type === 'case.resolved' ? 'Case resolved' : 'New case created',
        'metadata' => [
            'caseId' => $caseId,
            'titlePreview' => tracs_dobby_text($title, 120),
            'privacy' => 'normal',
        ],
        'occurredAt' => date(DATE_ATOM),
    ]);
}
