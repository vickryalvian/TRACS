<?php
if (!headers_sent()) {
    http_response_code(401);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/includes/error_page_render.php';

tracs_render_error_page(
    401,
    'Sign In Required',
    'Unauthorized',
    'Dobby needs you to sign in first.',
    'Your session may have ended or you have not logged in yet. Sign in to continue.',
    [
        ['href' => '/login.php', 'label' => 'Log In', 'primary' => true],
        ['onclick' => "history.length > 1 ? history.back() : location.href='/login.php'", 'label' => 'Go Back'],
    ]
);
