<?php
if (!headers_sent()) {
    http_response_code(405);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    405,
    'Method Not Allowed',
    'Method Not Allowed',
    "Dobby says that request method won't work here.",
    'The way this request was sent is not supported on this page. Go back and try the action again.',
    [
        ['href' => '/index.php', 'label' => 'Back to Dashboard', 'primary' => true],
        ['onclick' => "history.length > 1 ? history.back() : location.href='/index.php'", 'label' => 'Go Back'],
    ]
);
