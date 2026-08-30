<?php
if (!headers_sent()) {
    http_response_code(403);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    403,
    'Access Denied',
    'Forbidden',
    'Dobby is not able to let you into this one.',
    'You do not have permission to access this resource. Contact an administrator if you believe this is a mistake.',
    [
        ['href' => '/index.php', 'label' => 'Back to Dashboard', 'primary' => true],
        ['onclick' => "history.length > 1 ? history.back() : location.href='/index.php'", 'label' => 'Go Back'],
    ]
);
