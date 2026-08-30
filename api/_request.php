<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);

final class RequestValidationException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly array $errors = []
    ) {
        parent::__construct($message);
    }
}

function get_request_json(?string $rawBody = null): array
{
    $raw = $rawBody ?? file_get_contents('php://input');
    if ($raw === false) {
        throw new RequestValidationException('Unable to read the request body.');
    }

    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    if (!str_starts_with($raw, '{')) {
        throw new RequestValidationException('The JSON request body must be an object.');
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        throw new RequestValidationException('The request body must contain valid JSON.');
    }

    if (!is_array($decoded)) {
        throw new RequestValidationException('The JSON request body must be an object.');
    }

    return $decoded;
}

function validate_required_fields(array $input, array $fields): array
{
    $errors = [];

    foreach ($fields as $key => $label) {
        if (is_int($key)) {
            $key = (string)$label;
            $label = str_replace('_', ' ', $key);
        }

        $value = $input[$key] ?? null;
        $missing = $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);

        if ($missing) {
            $errors[$key] = ucfirst((string)$label) . ' is required.';
        }
    }

    return $errors;
}

function safe_date_parse(
    mixed $value,
    string $format = 'Y-m-d',
    string $timezone = 'Asia/Jakarta'
): ?\DateTimeImmutable {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    try {
        $zone = new \DateTimeZone($timezone);
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
    } catch (\Throwable) {
        return null;
    }

    $errors = \DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date->format($format) === $value ? $date : null;
}
