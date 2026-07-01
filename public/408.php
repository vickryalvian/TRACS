<?php
if (!headers_sent()) {
    http_response_code(408);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    408,
    'Request Timeout',
    'Request Timeout',
    'Dobby waited, but the request took too long.',
    'The connection took too long to complete. Check your network and try again.',
    [
        ['onclick' => 'location.reload()', 'label' => 'Retry', 'primary' => true],
        ['href' => '/index.php', 'label' => 'Back to Dashboard'],
    ]
);
