<?php
/**
 * currency-rate.php
 *
 * Dashboard "Currency Converter" widget backend. Serves the realtime pair
 * rate the widget polls on an interval — always answered from a short-lived
 * file cache (mirrors public/api/holiday-indonesia.php's pattern) so the
 * background auto-refresh never re-hits Frankfurter more often than the TTL,
 * regardless of how many browser tabs are open. Never writes to
 * tracs_currency_history — that table is for actual user conversions only.
 */

require '_bootstrap.php';

const CURRENCY_RATE_TTL = 300; // 5 minutes
const CURRENCY_RATE_ALLOWED = ['IDR', 'USD', 'SGD'];

function currency_rate_cache_dir(): string {
    return __DIR__ . '/../cache/currency-rates';
}

function currency_rate_cache_file(string $from, string $to): string {
    return currency_rate_cache_dir() . '/' . strtolower($from) . '-' . strtolower($to) . '.json';
}

function currency_rate_read_cache(string $from, string $to, bool $allowStale = false): ?array {
    $file = currency_rate_cache_file($from, $to);
    if (!is_file($file)) return null;
    if (!$allowStale && (time() - filemtime($file)) > CURRENCY_RATE_TTL) return null;
    $json = json_decode((string)@file_get_contents($file), true);
    return is_array($json) && isset($json['rate']) ? $json : null;
}

function currency_rate_write_cache(string $from, string $to, float $rate): void {
    $dir = currency_rate_cache_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return;
    @file_put_contents(currency_rate_cache_file($from, $to), json_encode([
        'from' => $from,
        'to' => $to,
        'rate' => $rate,
        'fetched_at' => date('c'),
    ]));
}

$from = strtoupper(trim((string)($_GET['from'] ?? 'USD')));
$to   = strtoupper(trim((string)($_GET['to'] ?? 'IDR')));
if (!in_array($from, CURRENCY_RATE_ALLOWED, true) || !in_array($to, CURRENCY_RATE_ALLOWED, true) || $from === $to) {
    fail('Unsupported currency pair.', 422);
}

$cached = currency_rate_read_cache($from, $to, false);
if ($cached) {
    ok(['from' => $from, 'to' => $to, 'rate' => (float)$cached['rate'], 'fetched_at' => $cached['fetched_at'], 'stale' => false], 'Rate loaded');
}

$url = 'https://api.frankfurter.dev/v1/latest?amount=1&from=' . urlencode($from) . '&to=' . urlencode($to);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = $response ? json_decode((string)$response, true) : null;
if ($httpCode === 200 && is_array($decoded) && isset($decoded['rates'][$to])) {
    $rate = (float)$decoded['rates'][$to];
    currency_rate_write_cache($from, $to, $rate);
    ok(['from' => $from, 'to' => $to, 'rate' => $rate, 'fetched_at' => date('c'), 'stale' => false], 'Rate loaded');
}

$stale = currency_rate_read_cache($from, $to, true);
if ($stale) {
    ok(['from' => $from, 'to' => $to, 'rate' => (float)$stale['rate'], 'fetched_at' => $stale['fetched_at'], 'stale' => true], 'Rate loaded (cached)');
}

fail('Could not load the realtime exchange rate.', 502);
