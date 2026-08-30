<?php
if (!headers_sent()) {
    http_response_code(404);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    404,
    'Not Found',
    'Page Not Found',
    'Dobby could not find what you are looking for.',
    'The page may not exist, may have been moved, or you may not have permission to access it.',
    [
        ['href' => '/index.php', 'label' => 'Back to Dashboard', 'primary' => true],
        ['onclick' => "history.length > 1 ? history.back() : location.href='/index.php'", 'label' => 'Go Back'],
    ]
);
