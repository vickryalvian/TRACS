<?php
/**
 * TRACS CSRF/session helpers.
 */

require_once __DIR__ . '/auth_hardening.php';

function tracs_is_https(): bool {
    $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $trustedForwardedHttps = $forwardedProto === 'https'
        && function_exists('tracs_auth_remote_addr_is_trusted')
        && tracs_auth_remote_addr_is_trusted();

    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443) ||
        $trustedForwardedHttps
    );
}

function tracs_send_security_headers(): void {
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
    if (tracs_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function tracs_start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        tracs_send_security_headers();
        return;
    }

    tracs_send_security_headers();
    $secure = tracs_is_https();
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $params['lifetime'] ?? 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function tracs_rotate_csrf_token(): void {
    tracs_start_session();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
