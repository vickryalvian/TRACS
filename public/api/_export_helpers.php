<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../core/security/csrf.php';
tracs_start_session();
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/user_management.php';

if (!tracs_is_fully_authenticated()) {
    if (tracs_auth_pending_user_id() > 0) {
        $reason = tracs_auth_pending_expired() ? 'pending_two_factor_expired' : 'pending_two_factor';
        tracs_auth_log_event($conn, 'suspicious_access_attempt', 'blocked', tracs_auth_pending_identifier(), tracs_auth_pending_user_id(), $reason);
        if (tracs_auth_pending_expired()) {
            tracs_auth_clear_pending_2fa();
        }
    }
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

$lastSeen = (int)($_SESSION['tracs_last_seen_at'] ?? time());
if ((time() - $lastSeen) > tracs_auth_idle_timeout_seconds()) {
    $expiredUserId = (int)($_SESSION['user_id'] ?? 0);
    tracs_auth_destroy_current_session();
    tracs_auth_log_event($conn, 'session_timeout', 'expired', '', $expiredUserId ?: null, 'idle_timeout');
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired';
    exit;
}
$_SESSION['tracs_last_seen_at'] = time();

$uid = (int) $_SESSION['user_id'];
$authUser = tracs_get_user_by_id($conn, $uid);
if (!$authUser || !tracs_user_can_login($authUser)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}
tracs_sync_session_user($authUser);
tracs_touch_user_activity($conn, $uid);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

function export_fail(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function export_require_permissions(array $permissions): void {
    global $conn, $uid;
    foreach ($permissions as $permission) {
        if (!is_string($permission) || $permission === '') {
            continue;
        }
        if (!tracs_user_can($conn, $permission, $uid)) {
            tracs_auth_log_event($conn, 'permission_denied', 'blocked', (string)($_SESSION['user_email'] ?? ''), $uid ?: null, $permission);
            export_fail('Forbidden', 403);
        }
    }
}

$script = basename((string)(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: ''));
$exportPermissionMap = [
    'export-activity.php' => ['reports.export', 'users.view_activity'],
    'export-cases.php' => ['reports.export', 'cases.view'],
    'export-domain-price-crosscheck.php' => ['reports.export', 'domain_price.view'],
    'export-domains.php' => ['reports.export', 'domains.view'],
    'export-feedback.php' => ['reports.export', 'cancellation_feedback.view'],
    'export-finance.php' => ['reports.export', 'finance.view'],
    'export-moms.php' => ['reports.export', 'moms.view'],
    'export-shift-reports.php' => ['reports.export', 'reports.view'],
];
if (isset($exportPermissionMap[$script])) {
    export_require_permissions($exportPermissionMap[$script]);
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
