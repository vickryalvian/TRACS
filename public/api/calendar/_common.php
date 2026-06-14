<?php

require_once __DIR__ . '/../../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../modules/calendar/CalendarService.php';

function calendar_require_method(string ...$methods): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $allowed = array_map('strtoupper', $methods);
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        fail('Method not allowed', 405);
    }
}

function calendar_can_manage(): bool
{
    global $authUser;
    return in_array((string)($authUser['role_slug'] ?? ''), ['super_admin', 'admin', 'supervisor'], true);
}

function calendar_is_global_admin(): bool
{
    global $authUser;
    return in_array((string)($authUser['role_slug'] ?? ''), ['super_admin', 'admin'], true);
}

function calendar_require_manage(): void
{
    if (!calendar_can_manage()) {
        fail('Forbidden', 403);
    }
}

function calendar_validation_fail(array $errors, string $message = 'Please correct the highlighted fields.'): never
{
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => null,
        'errors' => $errors,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function calendar_clean_text(mixed $value, int $max): string
{
    $value = preg_replace('/\s+/', ' ', trim(strip_tags((string)$value))) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function calendar_valid_date(mixed $value): ?string
{
    $value = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function calendar_valid_time(mixed $value, bool $required = false): ?string
{
    $value = trim((string)$value);
    if ($value === '' && !$required) {
        return null;
    }
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return null;
    }
    return $value . ':00';
}

function calendar_event_types(): array
{
    return ['case', 'shift', 'meeting', 'reminder', 'task', 'holiday', 'maintenance', 'overtime', 'birthday'];
}

function calendar_statuses(): array
{
    return ['active', 'upcoming', 'done', 'overdue', 'on_hold', 'cancelled', 'holiday', 'maintenance'];
}

function calendar_visibility_values(): array
{
    return ['private', 'team', 'all'];
}

function calendar_recurrence_values(): array
{
    return ['none', 'daily', 'weekly', 'monthly', 'yearly'];
}

function calendar_ensure_schema(mysqli $conn): bool
{
    if (tracs_table_exists($conn, 'calendar_events')) {
        return true;
    }
    return $conn->query(
        "CREATE TABLE IF NOT EXISTS `calendar_events` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `title` VARCHAR(180) NOT NULL,
          `event_type` VARCHAR(40) NOT NULL,
          `event_date` DATE NOT NULL,
          `start_time` TIME DEFAULT NULL,
          `end_time` TIME DEFAULT NULL,
          `status` VARCHAR(40) NOT NULL DEFAULT 'upcoming',
          `assigned_user_id` INT UNSIGNED DEFAULT NULL,
          `source_module` VARCHAR(80) NOT NULL DEFAULT 'calendar',
          `source_id` BIGINT UNSIGNED DEFAULT NULL,
          `notes` TEXT DEFAULT NULL,
          `visibility` ENUM('private','team','all') NOT NULL DEFAULT 'team',
          `reminder_minutes` INT UNSIGNED DEFAULT NULL,
          `recurrence_rule` VARCHAR(80) NOT NULL DEFAULT 'none',
          `created_by` INT UNSIGNED NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `deleted_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          INDEX `idx_calendar_events_range` (`event_date`,`deleted_at`),
          INDEX `idx_calendar_events_assignee` (`assigned_user_id`,`event_date`),
          INDEX `idx_calendar_events_creator` (`created_by`,`event_date`),
          INDEX `idx_calendar_events_source` (`source_module`,`source_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ) === true;
}

function calendar_validate_assignee_scope(mysqli $conn, ?int $assignedUserId): void
{
    global $authUser, $uid;
    if ($assignedUserId === null || calendar_is_global_admin()) {
        return;
    }

    $actorDivision = (int)($authUser['division_id'] ?? 0);
    $stmt = $conn->prepare('SELECT division_id FROM tracs_users WHERE id=? LIMIT 1');
    if (!$stmt) {
        fail('Unable to validate the assigned user.', 500);
    }
    $stmt->bind_param('i', $assignedUserId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || ($assignedUserId !== $uid && ($actorDivision <= 0 || (int)$user['division_id'] !== $actorDivision))) {
        calendar_validation_fail(['assigned_user_id' => 'Select a user in your division.']);
    }
}

function calendar_management_scope(string $alias = 'e'): array
{
    global $authUser, $uid;
    if (calendar_is_global_admin()) {
        return ['', '', []];
    }

    $divisionId = (int)($authUser['division_id'] ?? 0);
    if ($divisionId <= 0) {
        return [
            " AND ({$alias}.created_by=? OR {$alias}.assigned_user_id=?)",
            'ii',
            [$uid, $uid],
        ];
    }

    return [
        " AND (
            {$alias}.created_by=?
            OR {$alias}.assigned_user_id=?
            OR EXISTS (
                SELECT 1 FROM tracs_users calendar_creator
                WHERE calendar_creator.id={$alias}.created_by AND calendar_creator.division_id=?
            )
            OR EXISTS (
                SELECT 1 FROM tracs_users calendar_assignee
                WHERE calendar_assignee.id={$alias}.assigned_user_id AND calendar_assignee.division_id=?
            )
        )",
        'iiii',
        [$uid, $uid, $divisionId, $divisionId],
    ];
}

function calendar_validate_payload(array $input): array
{
    $errors = [];
    $title = calendar_clean_text($input['title'] ?? '', 180);
    $eventDate = calendar_valid_date($input['event_date'] ?? $input['date'] ?? '');
    $startTime = calendar_valid_time($input['start_time'] ?? '', true);
    $endTime = calendar_valid_time($input['end_time'] ?? '', true);
    $eventType = strtolower(trim((string)($input['event_type'] ?? '')));
    $status = strtolower(trim((string)($input['status'] ?? 'upcoming')));
    $visibility = strtolower(trim((string)($input['visibility'] ?? 'team')));
    $recurrence = strtolower(trim((string)($input['recurrence_rule'] ?? 'none')));
    $sourceModule = calendar_clean_text($input['source_module'] ?? 'calendar', 80) ?: 'calendar';
    $assignedUserId = (int)($input['assigned_user_id'] ?? 0);
    $sourceId = (int)($input['source_id'] ?? 0);
    $reminderMinutes = trim((string)($input['reminder_minutes'] ?? ''));

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($eventDate === null) $errors['event_date'] = 'Use a valid date.';
    if ($startTime === null) $errors['start_time'] = 'Start time is required.';
    if ($endTime === null) $errors['end_time'] = 'End time is required.';
    if (!in_array($eventType, calendar_event_types(), true)) $errors['event_type'] = 'Select a valid event type.';
    if (!in_array($status, calendar_statuses(), true)) $errors['status'] = 'Select a valid status.';
    if (!in_array($visibility, calendar_visibility_values(), true)) $errors['visibility'] = 'Select a valid visibility.';
    if (!in_array($recurrence, calendar_recurrence_values(), true)) $errors['recurrence_rule'] = 'Select a valid recurrence.';
    if ($reminderMinutes !== '' && (!ctype_digit($reminderMinutes) || (int)$reminderMinutes > 10080)) {
        $errors['reminder_minutes'] = 'Reminder must be between 0 and 10080 minutes.';
    }

    if ($errors) {
        calendar_validation_fail($errors);
    }

    return [
        'title' => $title,
        'event_type' => $eventType,
        'event_date' => $eventDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'status' => $status,
        'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
        'source_module' => $sourceModule,
        'source_id' => $sourceId > 0 ? $sourceId : null,
        'notes' => trim(strip_tags((string)($input['notes'] ?? ''))),
        'visibility' => $visibility,
        'reminder_minutes' => $reminderMinutes !== '' ? (int)$reminderMinutes : null,
        'recurrence_rule' => $recurrence,
    ];
}
