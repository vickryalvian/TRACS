<?php
require_once __DIR__ . '/_export_helpers.php';
require_once __DIR__ . '/../../core/user_management.php';

if (!tracs_user_can($conn, 'domain_price.view')) {
    export_fail('Forbidden', 403);
}

foreach (['domain_price_months', 'domain_price_entries', 'domain_price_tlds'] as $table) {
    if (!export_table_exists($conn, $table)) {
        export_fail('Domain Price Crosscheck database schema is not installed.', 500);
    }
}

function dpc_export_month_param(string $key): ?string {
    $value = trim((string)($_GET[$key] ?? ''));
    if ($value === '') return null;
    if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
        export_fail('Invalid ' . str_replace('_', ' ', $key) . '. Use YYYY-MM.');
    }
    [$year, $month] = array_map('intval', explode('-', $value));
    if ($year < 2000 || $month < 1 || $month > 12) {
        export_fail('Invalid ' . str_replace('_', ' ', $key) . '.');
    }
    return sprintf('%04d-%02d', $year, $month);
}

function dpc_export_filename(string $from, string $to): string {
    if ($from === $to) {
        return 'domain-price-crosscheck-' . $from . '.csv';
    }
    return 'domain-price-crosscheck-' . $from . '-to-' . $to . '.csv';
}

$scope = trim((string)($_GET['export_scope'] ?? 'single'));
if ($scope === 'range') {
    $fromMonth = dpc_export_month_param('from_month');
    $toMonth = dpc_export_month_param('to_month');
    if (!$fromMonth || !$toMonth) {
        export_fail('From Month and To Month are required.');
    }
    if ($toMonth < $fromMonth) {
        export_fail('To Month cannot be earlier than From Month.');
    }
} else {
    $fromMonth = dpc_export_month_param('month');
    if (!$fromMonth) {
        export_fail('Month is required.');
    }
    $toMonth = $fromMonth;
}

$where = ['m.month >= ?', 'm.month <= ?'];
$types = 'ss';
$params = [$fromMonth, $toMonth];

$roleSlug = (string)($_SESSION['user_role_slug'] ?? '');
if ($roleSlug === 'intern') {
    if (!export_table_exists($conn, 'domain_price_task_links')) {
        export_fail('Domain Price task access schema is not installed.', 500);
    }
    $where[] = 'EXISTS (
        SELECT 1
        FROM domain_price_task_links dptl
        WHERE dptl.month_id = m.id
          AND dptl.assigned_to = ?
    )';
    $types .= 'i';
    $params[] = $uid;
}

$sql = 'SELECT
            m.month,
            m.status AS month_status,
            m.exchange_rate_usd_idr,
            t.tld_name,
            t.tld_category,
            COALESCE(s.source_name, "Selling Price") AS source_name,
            COALESCE(s.source_type, "internal") AS source_type,
            e.price_type,
            e.currency,
            e.original_value,
            e.usd_value,
            e.idr_value,
            e.calculated_from_kurs,
            e.comparison_status,
            e.created_at,
            e.updated_at
        FROM domain_price_entries e
        INNER JOIN domain_price_months m ON e.month_id = m.id
        INNER JOIN domain_price_tlds t ON e.tld_id = t.id
        LEFT JOIN domain_price_sources s ON e.source_id = s.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY m.month ASC, t.tld_category ASC, t.sort_order ASC, t.tld_name ASC, s.sort_order ASC, s.source_name ASC, e.price_type ASC';

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    dpc_export_filename($fromMonth, $toMonth),
    [
        'Month',
        'Month Status',
        'Exchange Rate USD IDR',
        'TLD',
        'TLD Category',
        'Source',
        'Source Type',
        'Price Type',
        'Currency',
        'Original Value',
        'USD Value',
        'IDR Value',
        'Calculated From Kurs',
        'Comparison Status',
        'Created At',
        'Updated At',
    ],
    $result,
    fn(array $row) => [
        $row['month'] ?? '',
        $row['month_status'] ?? '',
        $row['exchange_rate_usd_idr'] ?? '',
        $row['tld_name'] ?? '',
        $row['tld_category'] ?? '',
        $row['source_name'] ?? '',
        $row['source_type'] ?? '',
        $row['price_type'] ?? '',
        $row['currency'] ?? '',
        $row['original_value'] ?? '',
        $row['usd_value'] ?? '',
        $row['idr_value'] ?? '',
        $row['calculated_from_kurs'] ?? '',
        $row['comparison_status'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
