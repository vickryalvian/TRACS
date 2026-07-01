<?php
/**
 * TRACS object access helpers.
 *
 * These helpers intentionally return a generic 404 for missing records and
 * unauthorized records so direct URL guessing does not reveal object existence.
 */

require_once __DIR__ . '/user_management.php';

function tracs_is_positive_int(mixed $value): bool {
    if (is_int($value)) {
        return $value > 0;
    }
    $value = trim((string)$value);
    return $value !== '' && ctype_digit($value) && (int)$value > 0;
}

function tracs_request_int(array $source, string $key): ?int {
    if (!array_key_exists($key, $source) || !tracs_is_positive_int($source[$key])) {
        return null;
    }
    return (int)$source[$key];
}

function tracs_abort_404(): never {
    http_response_code(404);
    if (!defined('TRACS_RENDERING_404')) {
        define('TRACS_RENDERING_404', true);
    }

    $page = __DIR__ . '/../public/404.php';
    if (is_file($page)) {
        require $page;
    } else {
        echo '404 - Page Not Found';
    }
    exit;
}

function tracs_require_any_page_permission(mysqli $conn, array $permissions): void {
    foreach ($permissions as $permission) {
        if (is_string($permission) && $permission !== '' && tracs_user_can($conn, $permission)) {
            return;
        }
    }

    if (function_exists('tracs_auth_log_event')) {
        tracs_auth_log_event(
            $conn,
            'permission_denied',
            'blocked',
            (string)($_SESSION['user_email'] ?? ''),
            (int)($_SESSION['user_id'] ?? 0) ?: null,
            implode(',', array_map('strval', $permissions))
        );
    }
    tracs_abort_404();
}

function tracs_require_page_permission(mysqli $conn, string $permission): void {
    tracs_require_any_page_permission($conn, [$permission]);
}

/**
 * True for supervisor-tier roles and above (supervisor, admin, super_admin),
 * hard-coded by role slug rather than an editable permission flag, so a
 * misconfigured role-permission grant cannot widen access for agents/interns.
 */
function tracs_is_supervisor_or_above(mysqli $conn, ?int $userId = null): bool {
    $uid = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    $user = tracs_get_user_by_id($conn, $uid);
    if (!$user || !tracs_user_can_login($user)) {
        return false;
    }
    $role = (string)($user['role_slug'] ?? '');
    return in_array($role, ['super_admin', 'admin', 'supervisor'], true);
}

/**
 * Case deletion is restricted to supervisor-tier roles and above, hard-coded
 * by role slug rather than the editable cases.delete permission flag, so a
 * misconfigured role-permission grant cannot let an agent/intern delete cases.
 */
function tracs_user_can_delete_cases(mysqli $conn, ?int $userId = null): bool {
    return tracs_is_supervisor_or_above($conn, $userId);
}

function tracs_require_super_admin_page(mysqli $conn): array {
    $user = tracs_get_user_by_id($conn, (int)($_SESSION['user_id'] ?? 0));
    if ($user && tracs_user_can_login($user) && (string)($user['role_slug'] ?? '') === 'super_admin') {
        return $user;
    }

    if (function_exists('tracs_auth_log_event')) {
        tracs_auth_log_event(
            $conn,
            'permission_denied',
            'blocked',
            (string)($_SESSION['user_email'] ?? ''),
            (int)($_SESSION['user_id'] ?? 0) ?: null,
            'super_admin_only'
        );
    }
    tracs_abort_404();
}

function tracs_actor_can_oversee_user(mysqli $conn, array $actor, int $ownerId): bool {
    if ($ownerId <= 0) {
        return false;
    }

    $actorId = (int)($actor['id'] ?? 0);
    if ($actorId === $ownerId) {
        return true;
    }

    $role = (string)($actor['role_slug'] ?? '');
    if (in_array($role, ['super_admin', 'admin'], true)) {
        return true;
    }

    if ($role !== 'supervisor') {
        return false;
    }

    $actorDivision = (int)($actor['division_id'] ?? 0);
    if ($actorDivision <= 0) {
        return false;
    }

    $owner = tracs_get_user_by_id($conn, $ownerId);
    return $owner && (int)($owner['division_id'] ?? 0) === $actorDivision;
}

function tracs_record_owner_ids(array $record, array $ownerColumns): array {
    $ids = [];
    foreach ($ownerColumns as $column) {
        $id = (int)($record[$column] ?? 0);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    return array_keys($ids);
}

function tracs_can_view_owned_record(mysqli $conn, string $table, int $id, array $ownerColumns, ?string $permission = null): bool {
    static $allowedTables = [
        'tracs_moms' => true,
        'tracs_cases' => true,
        'tracs_reminders' => true,
        'tracs_side_tasks' => true,
        'tracs_shift_reports' => true,
        'tracs_cancellation_feedback' => true,
        'tracs_domains' => true,
        'tracs_finance_transfers' => true,
        'balance_transfers' => true,
        'tracs_task_assignments' => true,
        'tracs_ticker_messages' => true,
        'ops_status' => true,
    ];

    if ($id <= 0 || empty($allowedTables[$table])) {
        return false;
    }

    $columns = ['id'];
    foreach ($ownerColumns as $column) {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $column) && tracs_column_exists($conn, $table, $column)) {
            $columns[] = $column;
        }
    }
    $columns = array_values(array_unique($columns));

    $sql = 'SELECT `' . implode('`,`', $columns) . "` FROM `$table` WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$record) {
        return false;
    }

    $actor = tracs_get_user_by_id($conn, (int)($_SESSION['user_id'] ?? 0));
    if (!$actor || !tracs_user_can_login($actor)) {
        return false;
    }

    if ($permission !== null && !tracs_user_can($conn, $permission, (int)$actor['id'])) {
        $ownerIds = tracs_record_owner_ids($record, $ownerColumns);
        return in_array((int)$actor['id'], $ownerIds, true);
    }

    $ownerIds = tracs_record_owner_ids($record, $ownerColumns);
    if (!$ownerIds) {
        return in_array((string)($actor['role_slug'] ?? ''), ['super_admin', 'admin', 'supervisor'], true);
    }

    foreach ($ownerIds as $ownerId) {
        if (tracs_actor_can_oversee_user($conn, $actor, (int)$ownerId)) {
            return true;
        }
    }

    return false;
}

/**
 * MoM is a shared, collaborative record: any authenticated user holding
 * moms.view or moms.manage may view any MoM, not just ones they created.
 * created_by remains an immutable audit field, not a view/edit gate.
 */
function tracs_can_view_mom(mysqli $conn, int $momId): bool {
    if ($momId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('SELECT id FROM `tracs_moms` WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $momId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) {
        return false;
    }

    $actor = tracs_get_user_by_id($conn, (int)($_SESSION['user_id'] ?? 0));
    if (!$actor || !tracs_user_can_login($actor)) {
        return false;
    }

    return tracs_user_can($conn, 'moms.view', (int)$actor['id'])
        || tracs_user_can($conn, 'moms.manage', (int)$actor['id']);
}

function tracs_can_view_case(mysqli $conn, int $caseId): bool {
    return tracs_can_view_owned_record($conn, 'tracs_cases', $caseId, ['user_id', 'created_by'], null);
}

function tracs_can_view_report(mysqli $conn, int $reportId): bool {
    return tracs_can_view_owned_record($conn, 'tracs_shift_reports', $reportId, ['created_by'], null);
}

function tracs_can_view_feedback(mysqli $conn, int $feedbackId): bool {
    return tracs_can_view_owned_record($conn, 'tracs_cancellation_feedback', $feedbackId, ['created_by'], 'cancellation_feedback.view');
}

function tracs_can_view_balance_transfer(mysqli $conn, int $transferId): bool {
    return tracs_can_view_owned_record($conn, 'balance_transfers', $transferId, ['created_by'], 'finance.view');
}

function tracs_require_object_access(mysqli $conn, string $type, int $id): void {
    $allowed = match ($type) {
        'mom' => tracs_can_view_mom($conn, $id),
        'case' => tracs_can_view_case($conn, $id),
        'shift_report' => tracs_can_view_report($conn, $id),
        'feedback' => tracs_can_view_feedback($conn, $id),
        'balance_transfer' => tracs_can_view_balance_transfer($conn, $id),
        default => false,
    };

    if (!$allowed) {
        tracs_abort_404();
    }
}
