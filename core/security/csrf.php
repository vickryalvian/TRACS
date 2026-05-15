<?php
/**
 * TRACS CSRF/session helpers.
 */

function tracs_is_https(): bool {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
}

function tracs_start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $params['lifetime'] ?? 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => tracs_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string {
    tracs_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function csrf_meta_tag(): string {
    return '<meta name="csrf-token" content="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function tracs_is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return str_starts_with(parse_url($uri, PHP_URL_PATH) ?? '', '/api/')
        || stripos($accept, 'application/json') !== false
        || strtolower($requestedWith) === 'xmlhttprequest';
}

function tracs_csrf_candidate(): string {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }

    $field = $_POST['csrf_token'] ?? '';
    return is_string($field) ? $field : '';
}

function verify_csrf(): void {
    tracs_start_session();

    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = tracs_csrf_candidate();
    if (is_string($expected) && $expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
        return;
    }

    http_response_code(403);
    if (tracs_is_api_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: invalid CSRF token.';
    }
    exit;
}
