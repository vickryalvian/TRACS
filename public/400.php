<?php
if (!headers_sent()) {
    http_response_code(400);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    400,
    'Bad Request',
    'Bad Request',
    'Dobby could not understand that request.',
    'The request looked malformed or incomplete. Check the link or form you used and try again.',
    [
        ['href' => '/index.php', 'label' => 'Back to Dashboard', 'primary' => true],
        ['onclick' => "history.length > 1 ? history.back() : location.href='/index.php'", 'label' => 'Go Back'],
    ]
);
