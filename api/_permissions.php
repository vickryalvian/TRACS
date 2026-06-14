<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../core/user_management.php';
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/_logging.php';

function require_permission(\mysqli $conn, string $permission, ?array $user = null): void
{
    $permission = trim($permission);
    $userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);

    if ($permission !== '' && $userId > 0 && \tracs_user_can($conn, $permission, $userId)) {
        return;
    }

    if (function_exists('tracs_auth_log_event')) {
        \tracs_auth_log_event(
            $conn,
            'permission_denied',
            'blocked',
            (string)($_SESSION['user_email'] ?? ''),
            $userId ?: null,
            $permission !== '' ? $permission : 'invalid_permission'
        );
    }

    json_error('Forbidden.', 403, [], ['request_id' => request_id()]);
}

function require_any_permission(\mysqli $conn, array $permissions, ?array $user = null): void
{
    $userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
    $checked = [];

    foreach ($permissions as $permission) {
        $permission = trim((string)$permission);
        if ($permission === '') {
            continue;
        }

        $checked[] = $permission;
        if ($userId > 0 && \tracs_user_can($conn, $permission, $userId)) {
            return;
        }
    }

    if (function_exists('tracs_auth_log_event')) {
        \tracs_auth_log_event(
            $conn,
            'permission_denied',
            'blocked',
            (string)($_SESSION['user_email'] ?? ''),
            $userId ?: null,
            implode('|', $checked) ?: 'missing_permission'
        );
    }

    json_error('Forbidden.', 403, [], ['request_id' => request_id()]);
}
