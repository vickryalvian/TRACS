<?php
if (!headers_sent()) {
    http_response_code(429);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    429,
    'Slow Down',
    'Too Many Requests',
    'Dobby needs a moment to catch up.',
    "You've sent too many requests in a short time. Wait a moment and try again.",
    [
        ['onclick' => 'location.reload()', 'label' => 'Retry', 'primary' => true],
        ['href' => '/index.php', 'label' => 'Back to Dashboard'],
    ]
);
