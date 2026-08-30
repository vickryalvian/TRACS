<?php
if (!headers_sent()) {
    http_response_code(419);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    419,
    'Session Expired',
    'Page Expired',
    'Dobby says your session took a nap.',
    'Your session or security token expired. Refresh the page and sign in again if needed.',
    [
        ['onclick' => 'location.reload()', 'label' => 'Refresh', 'primary' => true],
        ['href' => '/login.php', 'label' => 'Log In'],
    ]
);
