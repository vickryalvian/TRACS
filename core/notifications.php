<?php
/**
 * TRACS notification storage, dedupe, and scheduler helpers.
 */

require_once __DIR__ . '/user_management.php';
require_once __DIR__ . '/shift_config.php';

function tracs_notifications_ensure_schema(mysqli $conn): bool {
    static $ready = false;
    if ($ready) return true;

    $ddl = [
        "CREATE TABLE IF NOT EXISTS `tracs_notifications` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `notification_type` VARCHAR(80) NOT NULL,
          `target_user_id` INT UNSIGNED NOT NULL,
          `related_module` VARCHAR(80) NOT NULL DEFAULT '',
          `related_entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
          `trigger_type` VARCHAR(60) NOT NULL DEFAULT 'created',
          `dedupe_key` VARCHAR(190) NOT NULL,
          `title` VARCHAR(180) NOT NULL,
          `message` VARCHAR(500) NOT NULL,
          `related_url` VARCHAR(255) DEFAULT NULL,
          `actor_user_id` INT UNSIGNED DEFAULT NULL,
          `is_read` TINYINT(1) NOT NULL DEFAULT 0,
          `read_at` DATETIME DEFAULT NULL,
          `push_status` ENUM('pending','sent','failed','unavailable','skipped') NOT NULL DEFAULT 'pending',
          `push_attempted_at` DATETIME DEFAULT NULL,
          `push_sent_at` DATETIME DEFAULT NULL,
          `push_error` VARCHAR(255) DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `scheduled_at` DATETIME DEFAULT NULL,
          `sent_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_tracs_notification_dedupe` (`dedupe_key`),
          INDEX `idx_tracs_notifications_user_unread` (`target_user_id`, `is_read`, `sent_at`),
          INDEX `idx_tracs_notifications_push` (`target_user_id`, `push_status`, `sent_at`),
          INDEX `idx_tracs_notifications_related` (`related_module`, `related_entity_id`, `trigger_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `tracs_notification_triggers` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `dedupe_key` VARCHAR(190) NOT NULL,
          `target_user_id` INT UNSIGNED NOT NULL,
          `related_module` VARCHAR(80) NOT NULL DEFAULT '',
          `related_entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
          `trigger_type` VARCHAR(60) NOT NULL,
          `notification_id` BIGINT UNSIGNED DEFAULT NULL,
          `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_tracs_notification_trigger` (`dedupe_key`),
          INDEX `idx_tracs_notification_trigger_lookup` (`target_user_id`, `related_module`, `related_entity_id`, `trigger_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `tracs_notification_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `notification_id` BIGINT UNSIGNED DEFAULT NULL,
          `target_user_id` INT UNSIGNED DEFAULT NULL,
          `status` VARCHAR(40) NOT NULL,
          `message` VARCHAR(500) NOT NULL,
          `context_json` TEXT DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_tracs_notification_logs_notification` (`notification_id`, `created_at`),
          INDEX `idx_tracs_notification_logs_status` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    try {
        foreach ($ddl as $sql) {
            if ($conn->query($sql) !== true) {
                error_log('TRACS notification schema failed: ' . $conn->error);
                return false;
            }
        }
        $ready = tracs_table_exists($conn, 'tracs_notifications')
            && tracs_table_exists($conn, 'tracs_notification_triggers')
            && tracs_table_exists($conn, 'tracs_notification_logs');
        return $ready;
    } catch (Throwable $e) {
        error_log('TRACS notification schema exception: ' . $e->getMessage());
        return false;
    }
}

function tracs_notification_clean_text(mixed $value, int $max): string {
    $value = trim(strip_tags((string)$value));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function tracs_notification_log(mysqli $conn, string $status, string $message, ?int $notificationId = null, ?int $targetUserId = null, array $context = []): void {
    if (!tracs_notifications_ensure_schema($conn)) {
        error_log('TRACS notification log unavailable: ' . $status . ' ' . $message);
        return;
    }
    $status = tracs_notification_clean_text($status, 40) ?: 'info';
    $message = tracs_notification_clean_text($message, 500) ?: $status;
    $json = $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare("
        INSERT INTO tracs_notification_logs (notification_id, target_user_id, status, message, context_json, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) return;
    $stmt->bind_param('iisss', $notificationId, $targetUserId, $status, $message, $json);
    $stmt->execute();
    $stmt->close();
}

function tracs_notification_module_permission(string $module): ?array {
    return match ($module) {
        'cases' => ['cases.view', 'cases.manage'],
        'reminders' => ['reminders.view', 'reminders.manage'],
        'tasks' => ['tasks.view_own', 'tasks.monitor'],
        'mom', 'meeting' => ['moms.view', 'moms.manage'],
        'shift', 'shift-reports' => ['reports.view', 'reports.create', 'reports.update'],
        'shifting-assignment' => ['shifts.view', 'shifts.manage'],
        'dashboard' => ['dashboard.view'],
        default => null,
    };
}

function tracs_notification_user_can_receive(mysqli $conn, int $userId, string $module): bool {
    $permissions = tracs_notification_module_permission($module);
    if ($permissions === null) return true;
    foreach ($permissions as $permission) {
        if (tracs_user_can($conn, $permission, $userId)) {
            return true;
        }
    }
    return false;
}

function tracs_notification_url(string $module, int $entityId = 0): ?string {
    return match ($module) {
        'cases' => 'cases.php',
        'reminders' => 'reminders.php',
        'tasks' => 'tasks.php',
        'mom', 'meeting' => $entityId > 0 ? 'mom.php?mom_id=' . $entityId : 'mom.php',
        'shift', 'shift-reports' => 'shift-reports.php',
        'shifting-assignment' => 'shifting-assignment.php',
        'dashboard' => 'index.php',
        default => null,
    };
}

function tracs_notification_dedupe_key(int $targetUserId, string $type, string $module, int $entityId, string $triggerType): string {
    return hash('sha256', implode('|', [$targetUserId, $type, $module, $entityId, $triggerType]));
}

function tracs_create_notification(mysqli $conn, array $payload): ?int {
    if (!tracs_notifications_ensure_schema($conn)) return null;

    $targetUserId = (int)($payload['target_user_id'] ?? 0);
    if ($targetUserId <= 0) return null;

    $type = tracs_notification_clean_text($payload['notification_type'] ?? 'system', 80) ?: 'system';
    $module = tracs_notification_clean_text($payload['related_module'] ?? '', 80);
    $entityId = max(0, (int)($payload['related_entity_id'] ?? 0));
    $triggerType = tracs_notification_clean_text($payload['trigger_type'] ?? 'created', 60) ?: 'created';

    if ($module !== '' && !tracs_notification_user_can_receive($conn, $targetUserId, $module)) {
        tracs_notification_log($conn, 'failed', 'Notification target lacks module permission.', null, $targetUserId, [
            'module' => $module,
            'entity_id' => $entityId,
            'trigger_type' => $triggerType,
        ]);
        return null;
    }

    $title = tracs_notification_clean_text($payload['title'] ?? 'TRACS notification', 180) ?: 'TRACS notification';
    $message = tracs_notification_clean_text($payload['message'] ?? '', 500) ?: 'Open TRACS for details.';
    $relatedUrl = tracs_notification_clean_text($payload['related_url'] ?? (tracs_notification_url($module, $entityId) ?: ''), 255);
    $relatedUrl = $relatedUrl !== '' ? $relatedUrl : null;
    $actorUserId = (int)($payload['actor_user_id'] ?? 0);
    $actorUserId = $actorUserId > 0 ? $actorUserId : null;
    $scheduledAt = null;
    if (!empty($payload['scheduled_at']) && strtotime((string)$payload['scheduled_at']) !== false) {
        $scheduledAt = date('Y-m-d H:i:s', strtotime((string)$payload['scheduled_at']));
    }
    $dedupeKey = tracs_notification_clean_text(
        $payload['dedupe_key'] ?? tracs_notification_dedupe_key($targetUserId, $type, $module, $entityId, $triggerType),
        190
    );

    $stmt = $conn->prepare("
        INSERT IGNORE INTO tracs_notifications
          (notification_type, target_user_id, related_module, related_entity_id, trigger_type, dedupe_key,
           title, message, related_url, actor_user_id, is_read, push_status, created_at, scheduled_at, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending', NOW(), ?, NOW())
    ");
    if (!$stmt) {
        tracs_notification_log($conn, 'failed', 'Notification insert prepare failed.', null, $targetUserId);
        return null;
    }
    $stmt->bind_param(
        'sisisssssis',
        $type,
        $targetUserId,
        $module,
        $entityId,
        $triggerType,
        $dedupeKey,
        $title,
        $message,
        $relatedUrl,
        $actorUserId,
        $scheduledAt
    );
    if (!$stmt->execute()) {
        $stmt->close();
        tracs_notification_log($conn, 'failed', 'Notification insert failed.', null, $targetUserId);
        return null;
    }
    if ($stmt->affected_rows < 1) {
        $stmt->close();
        tracs_notification_log($conn, 'skipped_duplicate', 'Skipped duplicate notification trigger.', null, $targetUserId, [
            'dedupe_key' => $dedupeKey,
            'trigger_type' => $triggerType,
        ]);
        return null;
    }
    $notificationId = (int)$stmt->insert_id;
    $stmt->close();

    $trigger = $conn->prepare("
        INSERT INTO tracs_notification_triggers
          (dedupe_key, target_user_id, related_module, related_entity_id, trigger_type, notification_id, triggered_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE notification_id = VALUES(notification_id)
    ");
    if ($trigger) {
        $trigger->bind_param('sisisi', $dedupeKey, $targetUserId, $module, $entityId, $triggerType, $notificationId);
        $trigger->execute();
        $trigger->close();
    }

    tracs_notification_log($conn, 'success', 'Notification queued.', $notificationId, $targetUserId, [
        'type' => $type,
        'module' => $module,
        'entity_id' => $entityId,
        'trigger_type' => $triggerType,
    ]);
    return $notificationId;
}

function tracs_notification_recent(mysqli $conn, int $userId, int $limit = 20): array {
    if ($userId <= 0 || !tracs_notifications_ensure_schema($conn)) {
        return ['items' => [], 'unread_count' => 0, 'pending_push_count' => 0];
    }

    $limit = max(1, min(50, $limit));
    $stmt = $conn->prepare("
        SELECT id, notification_type, related_module, related_entity_id, trigger_type,
               title, message, related_url, is_read, read_at, push_status,
               created_at, scheduled_at, sent_at
        FROM tracs_notifications
        WHERE target_user_id = ? AND sent_at IS NOT NULL AND sent_at <= NOW()
        ORDER BY sent_at DESC, id DESC
        LIMIT ?
    ");
    if (!$stmt) {
        return ['items' => [], 'unread_count' => 0, 'pending_push_count' => 0];
    }
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $countStmt = $conn->prepare("
        SELECT
          SUM(is_read = 0) AS unread_count,
          SUM(push_status = 'pending') AS pending_push_count
        FROM tracs_notifications
        WHERE target_user_id = ? AND sent_at IS NOT NULL AND sent_at <= NOW()
    ");
    $unread = 0;
    $pending = 0;
    if ($countStmt) {
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $row = $countStmt->get_result()->fetch_assoc() ?: [];
        $countStmt->close();
        $unread = (int)($row['unread_count'] ?? 0);
        $pending = (int)($row['pending_push_count'] ?? 0);
    }

    return ['items' => $items, 'unread_count' => $unread, 'pending_push_count' => $pending];
}

function tracs_notification_mark_read(mysqli $conn, int $userId, array $ids = []): int {
    if ($userId <= 0 || !tracs_notifications_ensure_schema($conn)) return 0;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    if (!$ids) {
        $stmt = $conn->prepare("UPDATE tracs_notifications SET is_read=1, read_at=COALESCE(read_at, NOW()) WHERE target_user_id=? AND is_read=0");
        if (!$stmt) return 0;
        $stmt->bind_param('i', $userId);
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE tracs_notifications SET is_read=1, read_at=COALESCE(read_at, NOW()) WHERE target_user_id=? AND id IN ($placeholders)");
        if (!$stmt) return 0;
        $types = 'i' . str_repeat('i', count($ids));
        $stmt->bind_param($types, $userId, ...$ids);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return max(0, $affected);
}

function tracs_notification_claim_push(mysqli $conn, int $userId, array $ids, string $permission): array {
    if ($userId <= 0 || !tracs_notifications_ensure_schema($conn)) return [];
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0))), 0, 10);
    if (!$ids) return [];

    $claimed = [];
    $status = $permission === 'granted' ? 'sent' : 'unavailable';
    $message = $permission === 'granted' ? 'Browser notification handed to client.' : 'Browser notification permission unavailable.';
    $stmt = $conn->prepare("
        UPDATE tracs_notifications
        SET push_status=?, push_attempted_at=NOW(), push_sent_at=IF(?='sent', NOW(), push_sent_at), push_error=IF(?='sent', NULL, ?)
        WHERE id=? AND target_user_id=? AND push_status='pending' AND sent_at IS NOT NULL AND sent_at <= NOW()
    ");
    if (!$stmt) return [];
    foreach ($ids as $id) {
        $error = $status === 'sent' ? null : $permission;
        $stmt->bind_param('ssssii', $status, $status, $status, $error, $id, $userId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $claimed[] = $id;
            tracs_notification_log($conn, $status === 'sent' ? 'success' : 'permission_unavailable', $message, $id, $userId);
        }
    }
    $stmt->close();
    return $claimed;
}

function tracs_notification_set_push_status(mysqli $conn, int $userId, array $ids, string $status, ?string $error = null): int {
    if ($userId <= 0 || !tracs_notifications_ensure_schema($conn)) return 0;
    if (!in_array($status, ['sent', 'failed', 'unavailable', 'skipped'], true)) return 0;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    if (!$ids) return 0;
    $error = tracs_notification_clean_text($error ?? '', 255) ?: null;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("
        UPDATE tracs_notifications
        SET push_status=?, push_attempted_at=COALESCE(push_attempted_at, NOW()),
            push_sent_at=IF(?='sent', COALESCE(push_sent_at, NOW()), push_sent_at),
            push_error=?
        WHERE target_user_id=? AND id IN ($placeholders)
    ");
    if (!$stmt) return 0;
    $types = 'sssi' . str_repeat('i', count($ids));
    $stmt->bind_param($types, $status, $status, $error, $userId, ...$ids);
    $stmt->execute();
    $affected = max(0, $stmt->affected_rows);
    $stmt->close();
    foreach ($ids as $id) {
        tracs_notification_log($conn, $status === 'failed' ? 'failed' : $status, 'Browser notification status updated.', $id, $userId, ['error' => $error]);
    }
    return $affected;
}

function tracs_notify_case_created(mysqli $conn, int $caseId, int $targetUserId, string $caseTitle, int $actorUserId): ?int {
    return tracs_create_notification($conn, [
        'notification_type' => 'case_created',
        'target_user_id' => $targetUserId,
        'related_module' => 'cases',
        'related_entity_id' => $caseId,
        'trigger_type' => 'created',
        'title' => 'New case created',
        'message' => tracs_notification_clean_text($caseTitle, 120),
        'actor_user_id' => $actorUserId,
    ]);
}

function tracs_notify_reminder_created(mysqli $conn, int $reminderId, int $targetUserId, string $reminderTitle, ?string $dueAt, int $actorUserId): ?int {
    $due = ($dueAt && strtotime($dueAt)) ? ' Due ' . date('d M H:i', strtotime($dueAt)) . '.' : '';
    return tracs_create_notification($conn, [
        'notification_type' => 'reminder_created',
        'target_user_id' => $targetUserId,
        'related_module' => 'reminders',
        'related_entity_id' => $reminderId,
        'trigger_type' => 'created',
        'title' => 'Reminder created',
        'message' => tracs_notification_clean_text($reminderTitle, 130) . $due,
        'scheduled_at' => $dueAt,
        'actor_user_id' => $actorUserId,
    ]);
}

function tracs_notify_task_assigned(mysqli $conn, int $taskId, int $assignmentId, int $targetUserId, string $taskTitle, ?string $dueAt, int $actorUserId): ?int {
    $due = ($dueAt && strtotime($dueAt)) ? ' Due ' . date('d M H:i', strtotime($dueAt)) . '.' : '';
    return tracs_create_notification($conn, [
        'notification_type' => 'task_assigned',
        'target_user_id' => $targetUserId,
        'related_module' => 'tasks',
        'related_entity_id' => $assignmentId,
        'trigger_type' => 'assigned',
        'title' => 'Task assigned',
        'message' => tracs_notification_clean_text($taskTitle, 130) . $due,
        'actor_user_id' => $actorUserId,
    ]);
}

function tracs_notifications_run_scheduler(mysqli $conn): array {
    if (!tracs_notifications_ensure_schema($conn)) return ['created' => 0, 'status' => 'schema_unavailable'];
    $locked = false;
    $lockResult = $conn->query("SELECT GET_LOCK('tracs_notification_scheduler', 0) AS locked");
    if ($lockResult) {
        $locked = (int)(($lockResult->fetch_assoc()['locked'] ?? 0)) === 1;
    }
    if (!$locked) {
        tracs_notification_log($conn, 'skipped_duplicate', 'Notification scheduler already running.');
        return ['created' => 0, 'status' => 'locked'];
    }

    $created = 0;
    try {
        $created += tracs_notifications_schedule_reminders($conn);
        $created += tracs_notifications_schedule_meetings($conn);
        $created += tracs_notifications_schedule_shift_handover($conn);
        tracs_notification_log($conn, 'success', 'Notification scheduler completed.', null, null, ['created' => $created]);
    } catch (Throwable $e) {
        tracs_notification_log($conn, 'failed', 'Notification scheduler failed.', null, null, ['error' => $e->getMessage()]);
    } finally {
        $conn->query("SELECT RELEASE_LOCK('tracs_notification_scheduler')");
    }

    return ['created' => $created, 'status' => 'ok'];
}

function tracs_notifications_schedule_reminders(mysqli $conn): int {
    if (!tracs_table_exists($conn, 'tracs_reminders')) return 0;
    $created = 0;
    $plans = [
        ['before_15_min', 'reminder_before_due', "due_date > DATE_ADD(NOW(), INTERVAL 10 MINUTE) AND due_date <= DATE_ADD(NOW(), INTERVAL 15 MINUTE)", 'Reminder due in 15 minutes'],
        ['before_10_min', 'reminder_before_due', "due_date > NOW() AND due_date <= DATE_ADD(NOW(), INTERVAL 10 MINUTE)", 'Reminder due in 10 minutes'],
        ['due', 'reminder_due', "due_date <= NOW() AND due_date >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)", 'Reminder due now'],
    ];
    foreach ($plans as [$trigger, $type, $where, $title]) {
        $sql = "
            SELECT id, user_id, title, due_date
            FROM tracs_reminders
            WHERE is_completed=0 AND due_date IS NOT NULL AND {$where}
            ORDER BY due_date ASC
            LIMIT 250
        ";
        $result = $conn->query($sql);
        if (!$result) continue;
        while ($row = $result->fetch_assoc()) {
            $id = (int)$row['id'];
            $nid = tracs_create_notification($conn, [
                'notification_type' => $type,
                'target_user_id' => (int)$row['user_id'],
                'related_module' => 'reminders',
                'related_entity_id' => $id,
                'trigger_type' => $trigger,
                'title' => $title,
                'message' => tracs_notification_clean_text($row['title'] ?? 'Reminder', 140),
                'scheduled_at' => $row['due_date'] ?? null,
            ]);
            if ($nid) $created++;
        }
    }
    return $created;
}

function tracs_notifications_schedule_meetings(mysqli $conn): int {
    if (!tracs_table_exists($conn, 'tracs_moms') || !tracs_column_exists($conn, 'tracs_moms', 'meeting_at')) return 0;
    $created = 0;
    $plans = [
        ['before_15_min', "meeting_at > DATE_ADD(NOW(), INTERVAL 10 MINUTE) AND meeting_at <= DATE_ADD(NOW(), INTERVAL 15 MINUTE)", 'Meeting starts in 15 minutes'],
        ['before_10_min', "meeting_at > NOW() AND meeting_at <= DATE_ADD(NOW(), INTERVAL 10 MINUTE)", 'Meeting starts in 10 minutes'],
        ['due', "meeting_at <= NOW() AND meeting_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)", 'Meeting starts now'],
    ];
    foreach ($plans as [$trigger, $where, $title]) {
        $sql = "
            SELECT id, created_by, title, meeting_at
            FROM tracs_moms
            WHERE created_by IS NOT NULL
              AND meeting_at IS NOT NULL
              AND status IN ('upcoming','ongoing')
              AND {$where}
            ORDER BY meeting_at ASC
            LIMIT 200
        ";
        $result = $conn->query($sql);
        if (!$result) continue;
        while ($row = $result->fetch_assoc()) {
            $nid = tracs_create_notification($conn, [
                'notification_type' => 'meeting_reminder',
                'target_user_id' => (int)$row['created_by'],
                'related_module' => 'mom',
                'related_entity_id' => (int)$row['id'],
                'trigger_type' => $trigger,
                'title' => $title,
                'message' => tracs_notification_clean_text($row['title'] ?? 'Meeting', 140),
                'scheduled_at' => $row['meeting_at'] ?? null,
            ]);
            if ($nid) $created++;
        }
    }
    return $created;
}

function tracs_notifications_schedule_shift_handover(mysqli $conn): int {
    if (!tracs_table_exists($conn, 'tracs_users')) return 0;
    $meta = tracs_next_shift_change();
    if ((int)$meta['seconds_until'] <= 0 || (int)$meta['seconds_until'] > 15 * 60) {
        return 0;
    }

    $currentShift = (string)$meta['current_shift'];
    if (tracs_table_exists($conn, 'tracs_shift_reports')) {
        $stmt = $conn->prepare("
            SELECT
              COUNT(*) AS total_items,
              SUM(CASE WHEN status IN ('active', 'on_hold') THEN 1 ELSE 0 END) AS actionable_items
            FROM tracs_shift_reports
            WHERE active_date = CURDATE() AND shift_name = ?
        ");
        if ($stmt) {
            $stmt->bind_param('s', $currentShift);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($row['total_items'] ?? 0) > 0 && (int)($row['actionable_items'] ?? 0) === 0) {
                return 0;
            }
        }
    }
    $changeAt = $meta['change_at'] instanceof DateTimeInterface
        ? $meta['change_at']
        : new DateTimeImmutable((string)$meta['change_at']);
    $entityId = (int)$changeAt->format('U');
    $scheduledAt = $changeAt->modify('-15 minutes')->format('Y-m-d H:i:s');

    $select = "SELECT u.id FROM tracs_users u WHERE 1=1";
    if (tracs_column_exists($conn, 'tracs_users', 'is_active')) {
        $select .= " AND u.is_active=1";
    }
    if (tracs_column_exists($conn, 'tracs_users', 'status')) {
        $select .= " AND COALESCE(u.status, 'active')='active'";
    }
    if (tracs_column_exists($conn, 'tracs_users', 'shift_preference')) {
        $safeShift = $conn->real_escape_string($currentShift);
        $select .= " AND (u.shift_preference IS NULL OR TRIM(u.shift_preference)='' OR LOWER(TRIM(u.shift_preference))=LOWER('{$safeShift}'))";
    }
    $select .= " LIMIT 500";

    $created = 0;
    $result = $conn->query($select);
    if (!$result) return 0;
    while ($row = $result->fetch_assoc()) {
        $nid = tracs_create_notification($conn, [
            'notification_type' => 'shift_handover_reminder',
            'target_user_id' => (int)$row['id'],
            'related_module' => 'shift',
            'related_entity_id' => $entityId,
            'trigger_type' => 'before_15_min',
            'title' => 'Shift handover soon',
            'message' => 'Shift handover is coming up. Please complete your shift report.',
            'scheduled_at' => $scheduledAt,
        ]);
        if ($nid) $created++;
    }
    return $created;
}
