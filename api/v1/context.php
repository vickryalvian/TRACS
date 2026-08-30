<?php
declare(strict_types=1);

namespace TRACS\Api\V1;

require_once __DIR__ . '/../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);

function context_data(
    array $user,
    array $permissions,
    string $csrfToken
): array {
    $name = trim((string)($user['display_name'] ?? $user['name'] ?? ''));
    $name = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name) ?? '';
    if ($name === '' || filter_var($name, FILTER_VALIDATE_EMAIL)) {
        $name = 'User';
    }
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);

    $permissions = array_values(array_unique(array_filter(
        array_map(static fn(mixed $permission): string => trim((string)$permission), $permissions),
        static fn(string $permission): bool => $permission !== ''
    )));
    sort($permissions, SORT_STRING);

    return [
        'user' => [
            'id' => (int)($user['id'] ?? 0),
            'name' => $name,
            'role' => [
                'slug' => (string)($user['role_slug'] ?? ''),
                'name' => (string)($user['role_name'] ?? ''),
            ],
        ],
        'permissions' => $permissions,
        'csrf' => [
            'token' => $csrfToken,
            'header' => 'X-CSRF-Token',
        ],
    ];
}
