<?php
require_once __DIR__ . '/_bootstrap.php';

const TRACS_HOLIDAY_PRIMARY = 'libur.deno.dev';
const TRACS_HOLIDAY_TTL = 43200;

function holiday_cache_dir(): string {
    return __DIR__ . '/../cache/holidays';
}

function holiday_cache_file(int $year): string {
    return holiday_cache_dir() . '/indonesia-' . $year . '.json';
}

function holiday_fallback_file(): string {
    return __DIR__ . '/../assets/data/indonesia-holidays-fallback.json';
}

function holiday_type_label(string $type): string {
    return match ($type) {
        'holiday' => 'National Holiday',
        'leave' => 'Cuti Bersama',
        default => 'Observance',
    };
}

function holiday_normalize(array $row, string $source): ?array {
    $date = trim((string)($row['date'] ?? ''));
    $name = trim((string)($row['name'] ?? ($row['description'] ?? '')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $name === '') {
        return null;
    }

    $lower = mb_strtolower($name);
    $type = 'observance';
    if (array_key_exists('type', $row)) {
        $raw = (string)$row['type'];
        $type = $raw === 'leave' ? 'leave' : ($raw === 'holiday' ? 'holiday' : 'observance');
    } elseif (array_key_exists('is_national_holiday', $row)) {
        $type = !empty($row['is_national_holiday']) ? 'holiday' : 'leave';
    } elseif (str_contains($lower, 'cuti bersama')) {
        $type = 'leave';
    } else {
        $type = 'holiday';
    }

    return [
        'date' => $date,
        'name' => $name,
        'type' => $type,
        'source' => $source,
    ];
}

function holiday_http_json(string $url): ?array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'TRACS-TV-Mode/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) return null;
    } else {
        $context = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: TRACS-TV-Mode/1.0\r\n"]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) return null;
    }

    $json = json_decode((string)$body, true);
    return is_array($json) ? $json : null;
}

function holiday_read_cache(int $year, bool $allowStale = false): ?array {
    $file = holiday_cache_file($year);
    if (!is_file($file)) return null;
    if (!$allowStale && (time() - filemtime($file)) > TRACS_HOLIDAY_TTL) return null;
    $json = json_decode((string)@file_get_contents($file), true);
    return is_array($json) && isset($json['data']) && is_array($json['data']) ? $json : null;
}

function holiday_write_cache(int $year, array $items): void {
    $dir = holiday_cache_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return;
    @file_put_contents(holiday_cache_file($year), json_encode([
        'source' => TRACS_HOLIDAY_PRIMARY,
        'fetched_at' => date('c'),
        'data' => $items,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function holiday_read_fallback_year(int $year): array {
    $file = holiday_fallback_file();
    if (!is_file($file)) return [];
    $json = json_decode((string)@file_get_contents($file), true);
    $rows = $json['years'][(string)$year] ?? [];
    if (!is_array($rows)) return [];
    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $item = holiday_normalize($row, 'static-fallback');
        if ($item) $items[] = $item;
    }
    return $items;
}

function holiday_fetch_year(int $year, bool $forceFallback, array &$sources): array {
    if (!$forceFallback) {
        $cached = holiday_read_cache($year, false);
        if ($cached) {
            $sources[] = 'cache:' . ($cached['source'] ?? TRACS_HOLIDAY_PRIMARY) . ':' . $year;
            return $cached['data'];
        }

        $rows = holiday_http_json('https://libur.deno.dev/api?year=' . $year);
        if (is_array($rows) && array_is_list($rows)) {
            $items = [];
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $item = holiday_normalize($row, TRACS_HOLIDAY_PRIMARY);
                if ($item) $items[] = $item;
            }
            if ($items) {
                holiday_write_cache($year, $items);
                $sources[] = TRACS_HOLIDAY_PRIMARY . ':' . $year;
                return $items;
            }
        }

        $stale = holiday_read_cache($year, true);
        if ($stale) {
            $sources[] = 'stale-cache:' . ($stale['source'] ?? TRACS_HOLIDAY_PRIMARY) . ':' . $year;
            return $stale['data'];
        }
    }

    $fallback = holiday_read_fallback_year($year);
    if ($fallback) $sources[] = 'static-fallback:' . $year;
    return $fallback;
}

function holiday_pick(array $items, DateTimeImmutable $today): ?array {
    usort($items, fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
    $todayKey = $today->format('Y-m-d');
    foreach ($items as $item) {
        if (($item['date'] ?? '') < $todayKey) continue;
        return $item;
    }
    return null;
}

if (!empty($_GET['force_error'])) {
    fail('Simulated holiday API error', 503);
}

$tz = new DateTimeZone('Asia/Jakarta');
$dateParam = trim((string)($_GET['date'] ?? ''));
if ($dateParam !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    fail('Invalid date format. Use YYYY-MM-DD.', 400);
}
$today = $dateParam !== ''
    ? new DateTimeImmutable($dateParam . ' 00:00:00', $tz)
    : new DateTimeImmutable('today', $tz);

$year = (int)$today->format('Y');
$sources = [];
$forceFallback = !empty($_GET['force_fallback']);
$items = array_merge(
    holiday_fetch_year($year, $forceFallback, $sources),
    holiday_fetch_year($year + 1, $forceFallback, $sources)
);

$selected = holiday_pick($items, $today);
if (!$selected) {
    ok([
        'status' => 'empty',
        'date' => null,
        'name' => 'No upcoming tanggal merah',
        'type' => 'observance',
        'typeLabel' => 'Observance',
        'isToday' => false,
        'daysUntil' => null,
        'source' => implode(', ', array_unique($sources)) ?: 'none',
        'generatedAt' => date('c'),
    ]);
}

$holidayDate = new DateTimeImmutable($selected['date'] . ' 00:00:00', $tz);
$daysUntil = (int)$today->diff($holidayDate)->format('%r%a');
$type = (string)($selected['type'] ?? 'observance');

ok([
    'status' => $daysUntil === 0 ? 'today' : 'upcoming',
    'date' => $selected['date'],
    'name' => $selected['name'],
    'type' => $type,
    'typeLabel' => holiday_type_label($type),
    'isToday' => $daysUntil === 0,
    'daysUntil' => $daysUntil,
    'source' => $selected['source'] ?? (implode(', ', array_unique($sources)) ?: TRACS_HOLIDAY_PRIMARY),
    'sourceDetail' => array_values(array_unique($sources)),
    'generatedAt' => date('c'),
]);
