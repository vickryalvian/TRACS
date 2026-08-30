<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_logging.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/_permissions.php';

function require_method(string ...$methods): void
{
    $allowed = array_values(array_unique(array_map('strtoupper', $methods)));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($allowed !== [] && !in_array($method, $allowed, true)) {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));
        }
        json_error('Method not allowed.', 405, [], ['request_id' => request_id()]);
    }
}

function bootstrap(
    \mysqli $conn,
    array $methods = [],
    array $permissions = [],
    bool $requireCsrfForMutation = true
): array {
    if ($methods !== []) {
        require_method(...$methods);
    }

    $user = require_auth($conn);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($requireCsrfForMutation && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        verify_csrf($conn, $user);
    }

    foreach ($permissions as $permission) {
        require_permission($conn, (string)$permission, $user);
    }

    return [
        'user' => $user,
        'user_id' => (int)$user['id'],
        'request_id' => request_id(),
    ];
}
