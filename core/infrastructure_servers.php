<?php

/*
 * Persistence for Infrastructure Pulse's real (non-mock) server registry.
 * Mock/demo entries are intentionally never written here — they stay
 * session-only client state, per public/assets/infrastructure-pulse.js.
 */

const TRACS_INFRA_SERVER_METHODS = ['icmp', 'tcp', 'http'];

// The 9 built-in demo datacenters seeded client-side in
// public/assets/infrastructure-pulse-data.js. Kept in sync manually — this
// whitelist exists so infrastructure_hidden_seeds only ever stores codes
// for the known seed list, not arbitrary ad-hoc "Demo Data" entries.
const TRACS_INFRA_SEED_CODES = ['DCI', 'IDB', 'CY1', 'BCD', 'BTI', 'DR3', 'SG3', 'EGH', 'NDS'];

/**
 * Binds an ordered list of [type_char, value] pairs and executes.
 * Avoids hand-counted bind_param() type strings, a real source of
 * silent data corruption (a string bound as 'i' gets int-cast).
 */
function tracs_infra_bind_execute(mysqli_stmt $stmt, array $fields): bool {
    $types = '';
    $refs = [];
    foreach ($fields as $index => $field) {
        $types .= $field[0];
        $refs[$index] = &$fields[$index][1];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
    return $stmt->execute();
}

function tracs_infra_server_list_active(mysqli $conn): array {
    $sql = "SELECT * FROM `infrastructure_servers` WHERE `deleted_at` IS NULL ORDER BY `created_at` ASC";
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function tracs_infra_iso(?string $mysqlDatetime): ?string {
    if (!$mysqlDatetime) {
        return null;
    }
    try {
        return (new DateTime($mysqlDatetime))->format(DateTime::ATOM);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Shared by every surface that renders real servers client-side
 * (public/infrastructure-pulse.php, public/index.php dashboard widget,
 * public/tv-mode.php) so the registry, dashboard widget, and TV Mode widget
 * all read from the same persisted rows instead of drifting independently.
 */
function tracs_infra_server_list_active_for_json(mysqli $conn): array {
    return array_map(static function (array $row): array {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'region' => $row['region'],
            'country' => $row['country'],
            'provider' => $row['provider'],
            'method' => $row['method'],
            'target_host' => $row['target_host'],
            'target_port' => $row['target_port'],
            'health_url' => $row['health_url'],
            'expected_status' => $row['expected_status'],
            'expected_keyword' => $row['expected_keyword'],
            'packet_count' => $row['packet_count'],
            'timeout_seconds' => $row['timeout_seconds'],
            'interval_seconds' => $row['interval_seconds'],
            'last_status' => $row['last_status'],
            'last_latency_ms' => $row['last_latency_ms'] !== null ? (float)$row['last_latency_ms'] : null,
            'last_packet_loss_percent' => $row['last_packet_loss_percent'] !== null ? (float)$row['last_packet_loss_percent'] : null,
            'last_checked_at' => tracs_infra_iso($row['last_checked_at']),
            'created_at' => tracs_infra_iso($row['created_at']),
            'updated_at' => tracs_infra_iso($row['updated_at']),
        ];
    }, tracs_infra_server_list_active($conn));
}

/**
 * Inserts a new active row, or reactivates/overwrites an existing row with
 * the same code (including a previously soft-deleted one), matching the
 * upsert-by-code behavior already used by the client-side registry.
 */
function tracs_infra_server_upsert(mysqli $conn, array $data, int $createdBy): array|false {
    $code = strtoupper(trim((string)($data['code'] ?? '')));
    if ($code === '' || mb_strlen($code) > 8) {
        return false;
    }
    $method = in_array($data['method'] ?? '', TRACS_INFRA_SERVER_METHODS, true) ? $data['method'] : 'icmp';

    $name = trim((string)($data['name'] ?? $code));
    $region = trim((string)($data['region'] ?? ''));
    $country = trim((string)($data['country'] ?? ''));
    $provider = trim((string)($data['provider'] ?? ''));
    $targetHost = trim((string)($data['target_host'] ?? ''));
    $targetPort = isset($data['target_port']) && $data['target_port'] !== '' ? (int)$data['target_port'] : null;
    $healthUrl = trim((string)($data['health_url'] ?? ''));
    $expectedStatus = isset($data['expected_status']) && $data['expected_status'] !== '' ? (int)$data['expected_status'] : null;
    $expectedKeyword = trim((string)($data['expected_keyword'] ?? ''));
    $packetCount = isset($data['packet_count']) && $data['packet_count'] !== '' ? (int)$data['packet_count'] : null;
    $timeoutSeconds = isset($data['timeout_seconds']) && $data['timeout_seconds'] !== '' ? (int)$data['timeout_seconds'] : null;
    $intervalSeconds = isset($data['interval_seconds']) && $data['interval_seconds'] !== '' ? (int)$data['interval_seconds'] : null;

    $stmt = $conn->prepare('SELECT id FROM `infrastructure_servers` WHERE `code` = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Column order below must match the SQL's placeholder order exactly —
    // enforced by keeping each [type, value] pair on the same line as its column.
    if ($existing) {
        $sql = "UPDATE `infrastructure_servers` SET
            `name` = ?, `region` = ?, `country` = ?, `provider` = ?, `method` = ?,
            `target_host` = ?, `target_port` = ?, `health_url` = ?, `expected_status` = ?,
            `expected_keyword` = ?, `packet_count` = ?, `timeout_seconds` = ?, `interval_seconds` = ?,
            `deleted_at` = NULL, `last_status` = NULL, `last_latency_ms` = NULL,
            `last_packet_loss_percent` = NULL, `last_checked_at` = NULL
            WHERE `id` = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $ok = tracs_infra_bind_execute($stmt, [
            ['s', $name],
            ['s', $region],
            ['s', $country],
            ['s', $provider],
            ['s', $method],
            ['s', $targetHost],
            ['i', $targetPort],
            ['s', $healthUrl],
            ['i', $expectedStatus],
            ['s', $expectedKeyword],
            ['i', $packetCount],
            ['i', $timeoutSeconds],
            ['i', $intervalSeconds],
            ['i', (int)$existing['id']],
        ]);
    } else {
        $sql = "INSERT INTO `infrastructure_servers`
            (`code`, `name`, `region`, `country`, `provider`, `method`, `target_host`, `target_port`,
             `health_url`, `expected_status`, `expected_keyword`, `packet_count`, `timeout_seconds`,
             `interval_seconds`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $ok = tracs_infra_bind_execute($stmt, [
            ['s', $code],
            ['s', $name],
            ['s', $region],
            ['s', $country],
            ['s', $provider],
            ['s', $method],
            ['s', $targetHost],
            ['i', $targetPort],
            ['s', $healthUrl],
            ['i', $expectedStatus],
            ['s', $expectedKeyword],
            ['i', $packetCount],
            ['i', $timeoutSeconds],
            ['i', $intervalSeconds],
            ['i', $createdBy],
        ]);
    }

    $stmt->close();
    if (!$ok) {
        return false;
    }
    return tracs_infra_server_find_by_code($conn, $code);
}

function tracs_infra_server_find_by_code(mysqli $conn, string $code): array|false {
    $stmt = $conn->prepare('SELECT * FROM `infrastructure_servers` WHERE `code` = ? AND `deleted_at` IS NULL LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: false;
}

function tracs_infra_server_soft_delete(mysqli $conn, string $code): bool {
    $stmt = $conn->prepare('UPDATE `infrastructure_servers` SET `deleted_at` = NOW() WHERE `code` = ? AND `deleted_at` IS NULL');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $code);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $ok && $affected > 0;
}

function tracs_infra_hidden_seed_codes(mysqli $conn): array {
    $result = $conn->query('SELECT `code` FROM `infrastructure_hidden_seeds`');
    if (!$result) {
        return [];
    }
    $codes = [];
    while ($row = $result->fetch_assoc()) {
        $codes[] = $row['code'];
    }
    return $codes;
}

function tracs_infra_seed_hide(mysqli $conn, string $code, int $hiddenBy): bool {
    if (!in_array($code, TRACS_INFRA_SEED_CODES, true)) {
        return false;
    }
    $stmt = $conn->prepare('INSERT IGNORE INTO `infrastructure_hidden_seeds` (`code`, `hidden_by`) VALUES (?, ?)');
    if (!$stmt) {
        return false;
    }
    $ok = tracs_infra_bind_execute($stmt, [
        ['s', $code],
        ['i', $hiddenBy],
    ]);
    $stmt->close();
    return $ok;
}

function tracs_infra_seed_unhide(mysqli $conn, string $code): bool {
    $stmt = $conn->prepare('DELETE FROM `infrastructure_hidden_seeds` WHERE `code` = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $code);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $ok && $affected > 0;
}

function tracs_infra_server_record_check(
    mysqli $conn,
    string $code,
    string $status,
    ?float $latencyMs,
    ?float $packetLossPercent,
    string $checkedAt
): void {
    try {
        $checkedAtSql = (new DateTime($checkedAt))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        $checkedAtSql = date('Y-m-d H:i:s');
    }
    $stmt = $conn->prepare(
        'UPDATE `infrastructure_servers` SET `last_status` = ?, `last_latency_ms` = ?, `last_packet_loss_percent` = ?, `last_checked_at` = ?
         WHERE `code` = ? AND `deleted_at` IS NULL'
    );
    if (!$stmt) {
        return;
    }
    tracs_infra_bind_execute($stmt, [
        ['s', $status],
        ['d', $latencyMs],
        ['d', $packetLossPercent],
        ['s', $checkedAtSql],
        ['s', $code],
    ]);
    $stmt->close();

    $historyStmt = $conn->prepare(
        'INSERT INTO `infrastructure_monitoring_results` (`server_code`, `status`, `latency_ms`, `packet_loss_percent`, `checked_at`)
         VALUES (?, ?, ?, ?, ?)'
    );
    if ($historyStmt) {
        tracs_infra_bind_execute($historyStmt, [
            ['s', $code],
            ['s', $status],
            ['d', $latencyMs],
            ['d', $packetLossPercent],
            ['s', $checkedAtSql],
        ]);
        $historyStmt->close();
    }
}

/**
 * Most recent samples for one server, oldest first (chart-ready order).
 */
function tracs_infra_server_history(mysqli $conn, string $code, int $limit = 60): array {
    $limit = max(1, min(500, $limit));
    $stmt = $conn->prepare(
        'SELECT `status`, `latency_ms`, `packet_loss_percent`, `checked_at`
         FROM `infrastructure_monitoring_results`
         WHERE `server_code` = ?
         ORDER BY `checked_at` DESC, `id` DESC
         LIMIT ?'
    );
    if (!$stmt) {
        return [];
    }
    tracs_infra_bind_execute($stmt, [
        ['s', $code],
        ['i', $limit],
    ]);
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return array_reverse($rows);
}

/**
 * Deletes samples older than $days. Called from the cron worker so history
 * doesn't grow unbounded; not needed from request-time code paths.
 */
function tracs_infra_prune_history(mysqli $conn, int $days = 30): int {
    $days = max(1, $days);
    $stmt = $conn->prepare('DELETE FROM `infrastructure_monitoring_results` WHERE `checked_at` < (NOW() - INTERVAL ? DAY)');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    return max(0, $deleted);
}
