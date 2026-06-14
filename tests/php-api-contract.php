<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/v1/context.php';

function contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$data = \TRACS\Api\V1\context_data(
    [
        'id' => 7,
        'display_name' => 'TRACS Test User',
        'email' => 'must-not-leak@example.test',
        'password' => 'must-not-leak',
        'role_slug' => 'supervisor',
        'role_name' => 'Supervisor / Leader',
        'division_id' => 3,
        'status' => 'active',
        'two_factor_secret' => 'must-not-leak',
    ],
    ['shifts.view', 'dashboard.view', 'shifts.view', '', ' profile.view_own '],
    'test-csrf-token'
);

$payload = \TRACS\Api\response_payload(
    true,
    'Context loaded.',
    $data,
    [],
    ['request_id' => 'pilot-request-id']
);
$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$decoded = json_decode((string)$json, true);

contract_assert(
    array_keys($decoded) === ['success', 'message', 'data', 'errors', 'meta'],
    'Pilot response envelope keys changed.'
);
contract_assert($decoded['success'] === true, 'Pilot success flag changed.');
contract_assert($decoded['message'] === 'Context loaded.', 'Pilot message changed.');
contract_assert(
    $decoded['data']['permissions'] === ['dashboard.view', 'profile.view_own', 'shifts.view'],
    'Permissions must be sorted, unique, and non-empty.'
);
contract_assert(
    $decoded['data']['csrf'] === [
        'token' => 'test-csrf-token',
        'header' => 'X-CSRF-Token',
    ],
    'CSRF contract changed.'
);
contract_assert(
    $decoded['meta']['request_id'] === 'pilot-request-id',
    'Request ID metadata is missing.'
);

$user = $decoded['data']['user'];
contract_assert(
    array_keys($user) === ['id', 'name', 'role'],
    'User context exposed an unapproved field.'
);
contract_assert(
    array_keys($user['role']) === ['slug', 'name'],
    'Role context exposed an unapproved field.'
);

foreach (['email', 'password', 'status', 'division_id', 'two_factor_secret'] as $sensitiveKey) {
    contract_assert(
        !str_contains((string)$json, '"' . $sensitiveKey . '"'),
        "Sensitive field {$sensitiveKey} leaked into the context contract."
    );
}

$emailFallback = \TRACS\Api\V1\context_data(
    ['id' => 9, 'display_name' => 'private@example.test'],
    [],
    'test-csrf-token'
);
contract_assert(
    $emailFallback['user']['name'] === 'User',
    'Email fallback leaked through the display name.'
);

$route = file_get_contents(__DIR__ . '/../public/api/v1/context.php');
contract_assert($route !== false, 'Pilot public route is unreadable.');
contract_assert(
    str_contains($route, "\\TRACS\\Api\\bootstrap(\$conn, methods: ['GET'])"),
    'Pilot route no longer uses the Phase 5 authenticated GET bootstrap.'
);
contract_assert(
    str_contains($route, '\\TRACS\\Api\\json_success('),
    'Pilot route no longer uses the standard response helper.'
);

echo "TRACS pilot API contract checks passed.\n";
