<?php
require_once __DIR__ . '/../../core/security/csrf.php';
tracs_start_session();
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$uid = (int) $_SESSION['user_id'];

function export_fail(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function export_date_param(string $key): ?string {
    $value = trim((string)($_GET[$key] ?? ''));
    if ($value === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        export_fail('Invalid ' . $key . ' date. Use YYYY-MM-DD.');
    }
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    if (!checkdate($month, $day, $year)) {
        export_fail('Invalid ' . $key . ' date.');
    }
    return $value;
}

function export_date_range(): array {
    $from = export_date_param('from');
    $to = export_date_param('to');
    if ($from && $to && $from > $to) {
        export_fail('From Date cannot be later than To Date.');
    }
    return [$from, $to];
}

function export_add_date_filter(array &$where, string &$types, array &$params, string $column, ?string $from, ?string $to, bool $dateTime = false): void {
    if ($from !== null) {
        $where[] = "$column >= ?";
        $types .= 's';
        $params[] = $dateTime ? $from . ' 00:00:00' : $from;
    }
    if ($to !== null) {
        $where[] = "$column <= ?";
        $types .= 's';
        $params[] = $dateTime ? $to . ' 23:59:59' : $to;
    }
}

function export_clean_filename_part(?string $value): string {
    $value = trim((string)$value);
    $value = $value === '' ? 'all' : $value;
    return preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
}

function export_filename(string $page, ?string $from, ?string $to): string {
    $range = ($from || $to)
        ? export_clean_filename_part($from ?: 'start') . '_to_' . export_clean_filename_part($to ?: 'latest')
        : 'all';
    return 'tracs_' . export_clean_filename_part($page) . '_report_' . $range . '.csv';
}

function export_send_csv(string $filename, array $headers, mysqli_result $result, callable $mapRow): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, $mapRow($row));
    }
    fclose($out);
    exit;
}

function export_query(mysqli $conn, string $sql, string $types, array $params): mysqli_result {
    $stmt = $conn->prepare($sql);
    if (!$stmt) export_fail('Unable to prepare export query.', 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) export_fail('Unable to run export query.', 500);
    return $stmt->get_result();
}

function export_table_exists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function export_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}
