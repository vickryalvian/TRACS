<?php require '_bootstrap.php';
require_once __DIR__ . '/screenshot-history-lib.php';

$rows = screenshot_history_list_recent($conn, TRACS_SCREENSHOT_HISTORY_KEEP);

$items = array_map(static function (array $row): array {
    return [
        'id' => (int)$row['id'],
        'host' => (string)$row['host'],
        'raw_input' => (string)$row['raw_input'],
        'region' => (string)$row['region'],
        'region_label' => (string)$row['region_label'],
        'width' => $row['width'] !== null ? (int)$row['width'] : null,
        'height' => $row['height'] !== null ? (int)$row['height'] : null,
        'file_size_bytes' => $row['file_size_bytes'] !== null ? (int)$row['file_size_bytes'] : null,
        'meta' => [
            'load' => $row['load_ms'] !== null ? (int)$row['load_ms'] : null,
            'dns' => $row['dns_ms'] !== null ? (int)$row['dns_ms'] : null,
            'tcp' => $row['tcp_ms'] !== null ? (int)$row['tcp_ms'] : null,
            'ssl' => $row['ssl_ms'] !== null ? (int)$row['ssl_ms'] : null,
            'ttfb' => $row['ttfb_ms'] !== null ? (int)$row['ttfb_ms'] : null,
        ],
        'status' => (string)$row['status'],
        'captured_by' => (string)($row['captured_by_name'] ?? 'System'),
        'created_at' => (string)$row['created_at'],
        'image_url' => (string)$row['image_url'],
        'thumbnail_url' => (string)$row['thumbnail_url'],
    ];
}, $rows);

ok(['items' => $items], 'Screenshot history loaded');
