<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/_logging.php';

function verify_csrf(): void
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

    json_error('Invalid CSRF token.', 403, [], ['request_id' => request_id()]);
}
