<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/_logging.php';

function verify_csrf(?\mysqli $conn = null, ?array $user = null): void
{
    \tracs_start_session();

    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = \tracs_csrf_candidate();
    if (
        is_string($expected)
        && $expected !== ''
        && $provided !== ''
        && hash_equals($expected, $provided)
    ) {
        return;
    }

    if ($conn instanceof \mysqli && function_exists('tracs_auth_log_event')) {
        \tracs_auth_log_event(
            $conn,
            'csrf_validation_failed',
            'blocked',
            (string)($_SESSION['user_email'] ?? ''),
            (int)($user['id'] ?? $_SESSION['user_id'] ?? 0) ?: null,
            'api_mutation'
        );
    }

    json_error('Invalid CSRF token.', 403, [], ['request_id' => request_id()]);
}
