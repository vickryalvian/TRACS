<?php
/**
 * screenshot-capture.php
 *
 * Dashboard "Website Screenshot" widget backend. Proxies a capture request to
 * the PageFleets API (https://api.pagefleets.com) so the bearer key never
 * reaches the browser, then returns the rendered PNG to the frontend as a
 * base64 data URL alongside the timing metadata PageFleets exposes via headers.
 *
 * Method/permission are enforced in _bootstrap.php (GET, dashboard.view).
 */

require '_bootstrap.php';

const PAGEFLEETS_ENDPOINT = 'https://api.pagefleets.com/api/v1/screenshot';

$rawUrl = trim((string)($_GET['url'] ?? ''));
$region = trim((string)($_GET['region'] ?? ''));
$width  = (int)($_GET['width'] ?? 0);
$height = (int)($_GET['height'] ?? 0);

if ($rawUrl === '') {
    fail('Please enter a domain, URL, or IP address.', 422);
}
if (mb_strlen($rawUrl) > 2048) {
    fail('The address is too long.', 422);
}

// Accept "example.com", "https://example.com/path", or a bare IP. PageFleets
// resolves the scheme itself, but we validate the host shape to avoid relaying
// obvious junk (and to block non-http(s) schemes like file:// or javascript:).
$candidate = preg_match('~^https?://~i', $rawUrl) ? $rawUrl : 'https://' . $rawUrl;
$host = parse_url($candidate, PHP_URL_HOST);
if (!$host || !preg_match('~^[A-Za-z0-9.\-:\[\]]+$~', $host)) {
    fail('That does not look like a valid domain, URL, or IP address.', 422);
}

$apiKey = (string)($_ENV['PAGEFLEETS_API_KEY'] ?? getenv('PAGEFLEETS_API_KEY') ?: '');
if ($apiKey === '') {
    fail('Screenshot service is not configured. Set PAGEFLEETS_API_KEY in the server environment.', 503);
}

$payload = ['url' => $rawUrl];
if ($region !== '') {
    $payload['region'] = $region;
}
if ($width >= 320 && $width <= 2560) {
    $payload['width'] = $width;
}
if ($height >= 240 && $height <= 1600) {
    $payload['height'] = $height;
}

if (!function_exists('curl_init')) {
    fail('Screenshot service is unavailable on this server (cURL missing).', 503);
}

$responseHeaders = [];
$ch = curl_init(PAGEFLEETS_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_USERAGENT      => 'TRACS-Dashboard/1.0',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: image/png, application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return strlen($header);
    },
]);

$body        = curl_exec($ch);
$status      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError   = curl_error($ch);
curl_close($ch);

if ($body === false) {
    fail('Could not reach the screenshot service' . ($curlError ? ': ' . $curlError : '.'), 504);
}

// PageFleets returns the raw PNG on success; anything else is an error envelope
// (usually JSON) we should surface back to the operator.
if ($status < 200 || $status >= 300 || stripos($contentType, 'image/png') === false) {
    $message = 'Screenshot failed (HTTP ' . $status . ').';
    $decoded = json_decode((string)$body, true);
    if (is_array($decoded)) {
        $message = (string)($decoded['message'] ?? $decoded['error'] ?? $message);
    }
    if ($status === 401 || $status === 403) {
        $message = 'Screenshot service rejected the API key.';
    }
    fail($message, $status >= 500 ? 502 : 400);
}

logAct($conn, $uid, 'capture', 'dashboard', 'Captured website screenshot: ' . $host);

ok([
    'image' => 'data:image/png;base64,' . base64_encode($body),
    'host'  => $host,
    'meta'  => [
        'load' => $responseHeaders['x-load-time-ms'] ?? null,
        'dns'  => $responseHeaders['x-dns-time-ms'] ?? null,
        'tcp'  => $responseHeaders['x-tcp-time-ms'] ?? null,
        'ssl'  => $responseHeaders['x-ssl-time-ms'] ?? null,
        'ttfb' => $responseHeaders['x-ttfb-time-ms'] ?? null,
    ],
], 'Screenshot captured');
