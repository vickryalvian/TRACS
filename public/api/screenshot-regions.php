<?php
/**
 * screenshot-regions.php
 *
 * Dashboard "Website Screenshot" widget backend. Proxies PageFleets'
 * GET /api/v1/regions so the frontend never hardcodes region codes — the
 * dropdown always reflects whatever regions PageFleets currently serves.
 * Short-lived file cache absorbs an outage or rate limit without falling
 * back to a stale hardcoded list.
 *
 * Method/permission are enforced in _bootstrap.php (GET, dashboard.view).
 */

require '_bootstrap.php';

const PAGEFLEETS_REGIONS_ENDPOINT = 'https://api.pagefleets.com/api/v1/regions';
const SCREENSHOT_REGIONS_TTL = 600; // 10 minutes

function screenshot_regions_cache_file(): string {
    return __DIR__ . '/../cache/screenshot-regions.json';
}

function screenshot_regions_read_cache(bool $allowStale = false): ?array {
    $file = screenshot_regions_cache_file();
    if (!is_file($file)) return null;
    if (!$allowStale && (time() - filemtime($file)) > SCREENSHOT_REGIONS_TTL) return null;
    $json = json_decode((string)@file_get_contents($file), true);
    return is_array($json) && isset($json['regions']) && is_array($json['regions']) ? $json : null;
}

function screenshot_regions_write_cache(array $regions): void {
    $dir = dirname(screenshot_regions_cache_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return;
    @file_put_contents(screenshot_regions_cache_file(), json_encode([
        'fetched_at' => date('c'),
        'regions' => $regions,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function screenshot_regions_normalize(array $raw): array {
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) continue;
        $code = trim((string)($row['code'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        if ($code === '' || $name === '') continue;
        $out[] = ['code' => $code, 'name' => $name];
    }
    return $out;
}

$cached = screenshot_regions_read_cache(false);
if ($cached) {
    ok(['regions' => $cached['regions'], 'stale' => false, 'source' => 'cache'], 'Regions loaded');
}

$apiKey = (string)($_ENV['PAGEFLEETS_API_KEY'] ?? getenv('PAGEFLEETS_API_KEY') ?: '');
if ($apiKey === '' || !function_exists('curl_init')) {
    $stale = screenshot_regions_read_cache(true);
    if ($stale) {
        ok(['regions' => $stale['regions'], 'stale' => true, 'source' => 'stale-cache'], 'Regions loaded (cached)');
    }
    fail('Screenshot service is not configured.', 503);
}

$ch = curl_init(PAGEFLEETS_REGIONS_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'TRACS-Dashboard/1.0',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
]);
$body = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = $body !== false ? json_decode((string)$body, true) : null;
if ($body !== false && $status >= 200 && $status < 300 && is_array($decoded) && array_is_list($decoded)) {
    $regions = screenshot_regions_normalize($decoded);
    if ($regions) {
        screenshot_regions_write_cache($regions);
        ok(['regions' => $regions, 'stale' => false, 'source' => 'live'], 'Regions loaded');
    }
}

$stale = screenshot_regions_read_cache(true);
if ($stale) {
    ok(['regions' => $stale['regions'], 'stale' => true, 'source' => 'stale-cache'], 'Regions loaded (cached)');
}

fail('Could not load capture regions from the screenshot service.', 502);
