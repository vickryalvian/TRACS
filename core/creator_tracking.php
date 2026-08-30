<?php
/**
 * Shared creator tracking and compact creator metadata rendering.
 *
 * TRACS Operations System: creator-aware workflow direction by Vickry.
 */

function tracs_identifier(string $identifier): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Invalid SQL identifier');
    }
    return "`{$identifier}`";
}

function tracs_column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function tracs_ensure_creator_columns(mysqli $conn, string $table, ?string $sourceColumn = null): void {
    $tableSql = tracs_identifier($table);

    if (!tracs_column_exists($conn, $table, 'created_by')) {
        $conn->query("ALTER TABLE {$tableSql} ADD COLUMN `created_by` INT UNSIGNED NULL DEFAULT NULL");
        $conn->query("ALTER TABLE {$tableSql} ADD INDEX `idx_{$table}_created_by` (`created_by`)");
    }

    if (!tracs_column_exists($conn, $table, 'created_by_name')) {
        $conn->query("ALTER TABLE {$tableSql} ADD COLUMN `created_by_name` VARCHAR(150) NULL DEFAULT NULL");
    }

    if ($sourceColumn && tracs_column_exists($conn, $table, $sourceColumn)) {
        $sourceSql = tracs_identifier($sourceColumn);
        $conn->query("UPDATE {$tableSql} SET `created_by` = {$sourceSql} WHERE `created_by` IS NULL AND {$sourceSql} IS NOT NULL");
    }
}

function tracs_ensure_case_status_values(mysqli $conn): void {
    if (!tracs_column_exists($conn, 'tracs_cases', 'status')) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tracs_cases'
          AND COLUMN_NAME = 'status'
        LIMIT 1
    ");
    if (!$stmt) {
        return;
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (str_contains((string)($row['COLUMN_TYPE'] ?? ''), "'in_progress'")) {
        return;
    }

    $conn->query("
        ALTER TABLE `tracs_cases`
        MODIFY COLUMN `status` ENUM('active','pending','in_progress','stuck','on_hold','completed') NOT NULL DEFAULT 'active'
    ");
}

/**
 * Ensure the manual Workflow Board ordering column exists on tracs_cases.
 * Self-healing (mirrors tracs_ensure_case_status_values) so the drag & drop
 * board works on any environment without a separate manual migration step.
 * On first creation the column is backfilled per status so existing cases keep
 * a stable, sensible order (by next_check, then recency).
 */
function tracs_ensure_case_board_order(mysqli $conn): void {
    if (!tracs_column_exists($conn, 'tracs_cases', 'status')) {
        return;
    }
    if (tracs_column_exists($conn, 'tracs_cases', 'board_order')) {
        return;
    }

    $conn->query("ALTER TABLE `tracs_cases` ADD COLUMN `board_order` INT NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE `tracs_cases` ADD INDEX `idx_cases_board_order` (`status`, `board_order`)");

    // Backfill a deterministic manual order per status column so nothing starts
    // at a tied 0. Uses a session variable window instead of window functions
    // for MariaDB 10.1+/MySQL 5.7 compatibility.
    $conn->query("SET @tracs_bo := 0, @tracs_bo_status := ''");
    $conn->query("
        UPDATE `tracs_cases` c
        JOIN (
            SELECT id,
                   (@tracs_bo := IF(@tracs_bo_status = status, @tracs_bo + 1, 0)) AS ord,
                   (@tracs_bo_status := status) AS s
            FROM `tracs_cases`
            ORDER BY status, next_check_at IS NULL, next_check_at ASC, updated_at DESC, id DESC
        ) ranked ON ranked.id = c.id
        SET c.board_order = ranked.ord
    ");
}

function tracs_current_user_display(mysqli $conn): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $uid = (int)($_SESSION['user_id'] ?? 0);
    $fallback = trim((string)($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? ''));
    if ($uid > 0) {
        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(name,''), email) AS display_name FROM tracs_users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row['display_name'])) {
                $cached = (string)$row['display_name'];
                $_SESSION['user_name'] = $cached;
                return $cached;
            }
        }
    }

    $cached = $fallback !== '' ? $fallback : 'System';
    return $cached;
}

function tracs_creator_label(array $row): string {
    $name = trim((string)($row['creator_name'] ?? $row['created_by_name'] ?? ''));
    return $name !== '' ? $name : 'System';
}

function tracs_creator_meta(array $row, ?string $timestamp = null, bool $prefix = true): string {
    $name = tracs_creator_label($row);
    $createdAt = $timestamp ?? ($row['created_at'] ?? null);
    $time = ($createdAt && strtotime((string)$createdAt)) ? date('d M Y', strtotime((string)$createdAt)) : '';
    $text = ($prefix ? 'Created by ' : 'by ') . $name;
    if ($time !== '') {
        $text .= ' · ' . $time;
    }

    return '<span class="tracs-creator-meta" title="' . esc($text) . '">'
        . '<i data-lucide="user" class="icon-xs"></i>'
        . '<span>' . esc($text) . '</span>'
        . '</span>';
}
