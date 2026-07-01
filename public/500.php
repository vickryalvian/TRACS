<?php
if (!headers_sent()) {
    http_response_code(500);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    500,
    'Internal Error',
    'Internal Server Error',
    'Dobby tripped over a wire on our side.',
    'Something went wrong while processing your request. Our team has been notified — please try again shortly.',
    [
        ['onclick' => 'location.reload()', 'label' => 'Retry', 'primary' => true],
        ['href' => '/index.php', 'label' => 'Back to Dashboard'],
    ]
);
