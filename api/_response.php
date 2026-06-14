<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../core/security/error_response.php';

function response_payload(
    bool $success,
    string $message,
    mixed $data = null,
    array $errors = [],
    array $meta = []
): array {
    return [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
        'meta' => $meta === [] ? (object)[] : $meta,
    ];
}

function send_json(array $payload, int $status = 200): never
{
    http_response_code($status);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        $json = '{"success":false,"message":"Response encoding failed.","data":null,"errors":[],"meta":{}}';
    }

    echo $json;
    exit;
}

function json_success(
    mixed $data = null,
    string $message = 'Request completed successfully.',
    array $meta = [],
    int $status = 200
): never {
    send_json(response_payload(true, $message, $data ?? (object)[], [], $meta), $status);
}

function json_error(
    string $message = 'The request could not be completed.',
    int $status = 400,
    array $errors = [],
    array $meta = []
): never {
    $fallback = $status >= 500
        ? 'The server could not complete the request.'
        : 'The request could not be completed.';
    $safeMessage = \tracs_public_error_message($message, $fallback);

    send_json(response_payload(false, $safeMessage, null, $errors, $meta), $status);
}
