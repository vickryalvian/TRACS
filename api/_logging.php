<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../core/user_management.php';

function request_id(): string
{
    static $requestId = null;
    if (is_string($requestId)) {
        return $requestId;
    }

    $candidate = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]{8,80}$/', $candidate)) {
        return $requestId = $candidate;
    }

    try {
        return $requestId = bin2hex(random_bytes(12));
    } catch (\Throwable) {
        return $requestId = substr(hash('sha256', uniqid('tracs-api-', true)), 0, 24);
    }
}

function write_audit_log(
    \mysqli $conn,
    int $actorId,
    string $action,
    string $targetType,
    ?int $targetId = null,
    mixed $before = null,
    mixed $after = null,
    ?string $reason = null
): void {
    try {
        \tracs_log_user_event(
            $conn,
            $actorId,
            $action,
            $targetType,
            $targetId,
            $before,
            $after,
            $reason
        );
    } catch (\Throwable $error) {
        write_error_log('API audit logging failed.', $error, [
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
    }
}

function write_error_log(
    string $message,
    ?\Throwable $error = null,
    array $context = []
): void {
    $safeContext = function_exists('tracs_scrub_sensitive')
        ? \tracs_scrub_sensitive($context)
        : $context;
    $entry = [
        'request_id' => request_id(),
        'message' => trim($message),
        'exception' => $error ? get_class($error) : null,
        'error' => $error?->getMessage(),
        'context' => $safeContext,
    ];

    error_log('TRACS API: ' . json_encode(
        $entry,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    ));
}
