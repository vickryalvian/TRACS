<?php
/**
 * Dobby Telegram notification persona for TRACS operational events.
 * Reuses the configured Telegram bot token/chat; missing config is a safe no-op.
 */

declare(strict_types=1);

require_once __DIR__ . '/dobby_events.php';

function tracs_dobby_env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
}

function tracs_dobby_telegram_config(): array
{
    $token = tracs_dobby_env('DOBBY_TELEGRAM_BOT_TOKEN')
        ?: tracs_dobby_env('TRACS_TELEGRAM_BOT_TOKEN')
        ?: tracs_dobby_env('TELEGRAM_BOT_TOKEN');
    $chatId = tracs_dobby_env('DOBBY_TELEGRAM_CHAT_ID')
        ?: tracs_dobby_env('TRACS_TELEGRAM_CHAT_ID')
        ?: tracs_dobby_env('TELEGRAM_CHAT_ID');
    return ['token' => $token, 'chat_id' => $chatId];
}

function tracs_dobby_telegram_enabled(): bool
{
    $config = tracs_dobby_telegram_config();
    return $config['token'] !== '' && $config['chat_id'] !== '';
}

function tracs_dobby_wib_time(?string $timestamp = null): string
{
    try {
        $time = $timestamp && trim($timestamp) !== '' ? new DateTimeImmutable($timestamp) : new DateTimeImmutable('now');
        return $time->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('H:i') . ' WIB';
    } catch (Throwable $e) {
        return date('H:i') . ' WIB';
    }
}

function tracs_dobby_http_post_json(string $url, array $payload, int $timeout = 6): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl_unavailable'];
    }
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return ['ok' => false, 'error' => 'json_encode_failed'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false || $status < 200 || $status >= 300) {
        return ['ok' => false, 'error' => $error ?: 'http_' . $status];
    }
    return ['ok' => true, 'status' => $status];
}

function tracs_dobby_telegram_send(string $message): bool
{
    $config = tracs_dobby_telegram_config();
    if ($config['token'] === '' || $config['chat_id'] === '') {
        return false;
    }
    $url = 'https://api.telegram.org/bot' . $config['token'] . '/sendMessage';
    $result = tracs_dobby_http_post_json($url, [
        'chat_id' => $config['chat_id'],
        'text' => $message,
        'disable_web_page_preview' => true,
    ]);
    if (!$result['ok']) {
        error_log('Dobby Telegram notification skipped: ' . (string)($result['error'] ?? 'unknown'));
    }
    return (bool)$result['ok'];
}

function tracs_dobby_notify(array $event): bool
{
    static $sent = [];
    $type = tracs_dobby_text($event['event'] ?? $event['type'] ?? '', 120);
    if ($type === '') {
        return false;
    }
    $dedupeKey = tracs_dobby_text($event['deduplicationKey'] ?? $event['dedupe_key'] ?? $type . ':' . ($event['title'] ?? ''), 190);
    if (isset($sent[$dedupeKey])) {
        return false;
    }
    $sent[$dedupeKey] = true;

    $message = tracs_dobby_format_notification($event + ['event' => $type]);
    if ($message === '') {
        return false;
    }
    return tracs_dobby_telegram_send($message);
}

function tracs_dobby_format_notification(array $event): string
{
    $type = (string)($event['event'] ?? 'system.warning');
    $meta = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
    $title = tracs_dobby_text($event['title'] ?? '', 160);
    $message = tracs_dobby_text($event['message'] ?? '', 500);
    $time = tracs_dobby_wib_time($event['timestamp'] ?? null);

    if (str_starts_with($type, 'monitor.')) {
        $name = tracs_dobby_text($meta['name'] ?? $title ?: 'Monitored resource', 140);
        $status = tracs_dobby_text($meta['status'] ?? '', 40);
        $source = tracs_dobby_text($event['source'] ?? 'TRACS Monitoring', 120);
        $latency = isset($meta['latency_ms']) && $meta['latency_ms'] !== null ? (string)$meta['latency_ms'] . 'ms' : 'Unavailable';
        $loss = isset($meta['packet_loss_percent']) && $meta['packet_loss_percent'] !== null ? (string)$meta['packet_loss_percent'] . '%' : 'Unavailable';
        if ($type === 'monitor.recovered') {
            return "🐾 Dobby Update\n\n🟢 {$name} is back online.\n\nRecovered: {$time}\nLatency: {$latency}\nSource: {$source}\n\nEverything looks healthy again.";
        }
        $icon = $status === 'degraded' ? '🟠' : '🔴';
        return "🐾 Dobby detected a problem\n\n{$icon} {$name} is currently " . strtoupper($status ?: 'unhealthy') . ".\n\nLatency: {$latency}\nPacket loss: {$loss}\nDetected: {$time}\nSource: {$source}\n\nDobby will keep watching it.";
    }

    if (str_starts_with($type, 'deployment.') || str_starts_with($type, 'rollback.')) {
        $branch = tracs_dobby_text($meta['branch'] ?? '', 120);
        $commit = tracs_dobby_text($meta['commit'] ?? '', 64);
        $environment = tracs_dobby_text($meta['environment'] ?? $event['environment'] ?? 'Production', 80);
        $stage = tracs_dobby_text($meta['stage'] ?? '', 120);
        $status = str_contains($type, 'failed') ? '❌ Failed' : (str_contains($type, 'completed') ? '✅ Success' : 'In progress');
        $lines = [
            '🐾 Dobby noticed a TRACS update',
            '',
            str_contains($type, 'rollback') ? '↩️ Rollback activity was reported.' : '🚀 Deployment activity was reported.',
            '',
        ];
        if ($branch !== '') $lines[] = 'Branch: ' . $branch;
        if ($commit !== '') $lines[] = 'Commit: ' . substr($commit, 0, 12);
        if ($stage !== '') $lines[] = 'Stage: ' . $stage;
        $lines[] = 'Environment: ' . $environment;
        $lines[] = 'Time: ' . $time;
        $lines[] = '';
        $lines[] = 'Deployment status: ' . $status;
        if ($message !== '') $lines[] = $message;
        return implode("\n", $lines);
    }

    $heading = match ((string)($event['severity'] ?? 'info')) {
        'critical' => '❌ Dobby reports a critical TRACS event',
        'warning' => '⚠️ Dobby reports a TRACS warning',
        default => '🐾 Dobby reports a TRACS update',
    };
    return $heading . "\n\n" . ($title ?: $type) . "\n" . ($message ?: 'Open TRACS for details.') . "\n\nTime: {$time}";
}

function tracs_dobby_notify_monitoring_transition(mysqli $conn, array $server, ?string $previousStatus, array $result): bool
{
    unset($conn);
    $status = (string)($result['status'] ?? 'unknown');
    if (!in_array($status, ['healthy', 'degraded', 'critical'], true) || $status === (string)$previousStatus) {
        return false;
    }
    $wasUnhealthy = in_array((string)$previousStatus, ['degraded', 'critical'], true);
    if ($status === 'healthy' && !$wasUnhealthy) {
        return false;
    }
    $code = tracs_dobby_text($server['code'] ?? '', 20);
    $name = tracs_dobby_text($server['name'] ?? $code ?: 'Monitored resource', 140);
    $event = $status === 'healthy' ? 'monitor.recovered' : ($status === 'degraded' ? 'monitor.warning' : 'monitor.down');
    return tracs_dobby_notify([
        'event' => $event,
        'severity' => $status === 'critical' ? 'critical' : ($status === 'degraded' ? 'warning' : 'info'),
        'source' => 'TRACS Monitoring',
        'title' => $name,
        'timestamp' => (string)($result['checked_at'] ?? date(DATE_ATOM)),
        'metadata' => [
            'code' => $code,
            'name' => $name,
            'status' => $status,
            'target' => $server['target_host'] ?? null,
            'latency_ms' => $result['latency_ms'] ?? null,
            'packet_loss_percent' => $result['packet_loss_percent'] ?? null,
        ],
        'deduplicationKey' => 'monitor:' . $code . ':' . $status . ':' . date('YmdHi', strtotime((string)($result['checked_at'] ?? 'now')) ?: time()),
    ]);
}
