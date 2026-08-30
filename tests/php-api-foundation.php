<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/_request.php';

use TRACS\Api\RequestValidationException;

if (($argv[1] ?? '') === 'success-probe') {
    \TRACS\Api\json_success();
}

if (($argv[1] ?? '') === 'unauthenticated-probe') {
    require_once __DIR__ . '/../api/_logging.php';
    require_once __DIR__ . '/../api/_auth.php';
    $_SESSION = [];
    \TRACS\Api\require_auth();
}

if (($argv[1] ?? '') === 'invalid-csrf-probe') {
    require_once __DIR__ . '/../api/_csrf.php';
    \tracs_start_session();
    $_SESSION['csrf_token'] = 'expected-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong-token';
    \TRACS\Api\verify_csrf();
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$payload = \TRACS\Api\response_payload(
    true,
    'Request completed successfully.',
    ['id' => 7],
    [],
    ['page' => 1]
);
assert_same(
    ['success', 'message', 'data', 'errors', 'meta'],
    array_keys($payload),
    'Response envelope keys changed.'
);
assert_same(true, $payload['success'], 'Success response flag is incorrect.');

$successCommand = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(__FILE__)
    . ' success-probe';
$successOutput = [];
$successExitCode = 0;
exec($successCommand, $successOutput, $successExitCode);
$successJson = implode(PHP_EOL, $successOutput);
assert_same(0, $successExitCode, 'Success response probe did not exit normally.');
assert_same(
    true,
    str_contains($successJson, '"data":{}') && str_contains($successJson, '"meta":{}'),
    'Empty success data and metadata must serialize as objects.'
);

$request = \TRACS\Api\get_request_json('{"name":"TRACS","enabled":true}');
assert_same('TRACS', $request['name'] ?? null, 'JSON request parsing failed.');
assert_same([], \TRACS\Api\get_request_json('{}'), 'Empty JSON object was rejected.');

$errors = \TRACS\Api\validate_required_fields(
    ['title' => ' ', 'date' => '2026-06-15'],
    ['title' => 'title', 'date' => 'date']
);
assert_same(['title' => 'Title is required.'], $errors, 'Required-field validation changed.');

$date = \TRACS\Api\safe_date_parse('2026-06-15');
assert_same('2026-06-15', $date?->format('Y-m-d'), 'Valid ISO date was rejected.');
assert_same(null, \TRACS\Api\safe_date_parse('15-06-2026'), 'UI date format entered the backend contract.');
assert_same(null, \TRACS\Api\safe_date_parse('2026-02-31'), 'Invalid calendar date was accepted.');

try {
    \TRACS\Api\get_request_json('{invalid');
    fwrite(STDERR, 'Malformed JSON was accepted.' . PHP_EOL);
    exit(1);
} catch (RequestValidationException) {
}

try {
    \TRACS\Api\get_request_json('["not","an","object"]');
    fwrite(STDERR, 'JSON list was accepted as a request object.' . PHP_EOL);
    exit(1);
} catch (RequestValidationException) {
}

$command = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(__FILE__)
    . ' unauthenticated-probe';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);
$unauthenticated = json_decode(implode(PHP_EOL, $output), true);
assert_same(0, $exitCode, 'Unauthenticated probe did not exit normally.');
assert_same(false, $unauthenticated['success'] ?? null, 'Unauthenticated response flag changed.');
assert_same(true, array_key_exists('data', $unauthenticated), 'Unauthenticated response omitted data.');
assert_same(null, $unauthenticated['data'], 'Unauthenticated response exposed data.');
assert_same([], $unauthenticated['errors'] ?? null, 'Unauthenticated response errors shape changed.');
assert_same(
    true,
    is_string($unauthenticated['meta']['request_id'] ?? null)
        && strlen($unauthenticated['meta']['request_id']) >= 8,
    'Unauthenticated response request ID is missing.'
);

$csrfCommand = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(__FILE__)
    . ' invalid-csrf-probe';
$csrfOutput = [];
$csrfExitCode = 0;
exec($csrfCommand, $csrfOutput, $csrfExitCode);
$invalidCsrf = json_decode(implode(PHP_EOL, $csrfOutput), true);
assert_same(0, $csrfExitCode, 'Invalid CSRF probe did not exit normally.');
assert_same(false, $invalidCsrf['success'] ?? null, 'Invalid CSRF response flag changed.');
assert_same(
    'The request could not be completed.',
    $invalidCsrf['message'] ?? null,
    'Invalid CSRF public message is not sanitized.'
);
assert_same(
    ['success', 'message', 'data', 'errors', 'meta'],
    array_keys($invalidCsrf),
    'Invalid CSRF envelope keys changed.'
);
assert_same(
    true,
    is_string($invalidCsrf['meta']['request_id'] ?? null)
        && strlen($invalidCsrf['meta']['request_id']) >= 8,
    'Invalid CSRF request ID is missing.'
);

echo "TRACS PHP API foundation checks passed.\n";
