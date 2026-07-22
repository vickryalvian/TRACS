<?php

const TRACS_INSIGHT_SSL_TIMEOUT = 5;
const TRACS_INSIGHT_SSL_WARNING_DAYS = 14;

function tracs_insight_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function tracs_insight_ssl_expiry(): ?array {
    if (!tracs_insight_is_https()) {
        return null;
    }
    $host = tracs_insight_ssl_host();
    if ($host === null) {
        return null;
    }

    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $client = @stream_socket_client(
        'ssl://' . $host . ':443',
        $errno,
        $errstr,
        TRACS_INSIGHT_SSL_TIMEOUT,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$client) {
        return null;
    }

    $params = stream_context_get_params($client);
    $cert = $params['options']['ssl']['peer_certificate'] ?? null;
    fclose($client);
    if (!$cert) {
        return null;
    }

    $info = openssl_x509_parse($cert);
    $expiresAt = $info['validTo_time_t'] ?? null;
    if (!is_int($expiresAt)) {
        return null;
    }

    return [
        'host' => $host,
        'expires_at' => $expiresAt,
        'days_left' => (int)floor(($expiresAt - time()) / 86400),
    ];
}

function tracs_insight_ssl_host(): ?string {
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        $host = (string)parse_url((string)($_ENV['APP_URL'] ?? ''), PHP_URL_HOST);
    }
    $host = trim(explode(':', $host)[0]);
    return $host !== '' ? $host : null;
}
