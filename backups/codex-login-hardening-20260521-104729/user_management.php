<?php
/**
 * TRACS user, role, permission, and profile helpers.
 */

require_once __DIR__ . '/creator_tracking.php';

function tracs_table_exists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function tracs_user_management_schema_ready(mysqli $conn): bool {
    return tracs_table_exists($conn, 'tracs_roles')
        && tracs_table_exists($conn, 'tracs_permissions')
        && tracs_table_exists($conn, 'tracs_role_permissions')
        && tracs_table_exists($conn, 'tracs_divisions')
        && tracs_table_exists($conn, 'tracs_user_activity_logs')
        && tracs_column_exists($conn, 'tracs_users', 'role_id')
        && tracs_column_exists($conn, 'tracs_users', 'status')
        && tracs_column_exists($conn, 'tracs_users', 'username');
}

function tracs_permission_catalog(): array {
    return [
        'Dashboard' => [
            'dashboard.view' => 'View operational dashboard',
        ],
        'Users' => [
            'users.view' => 'View users and team structure',
            'users.create' => 'Create new users',
            'users.update' => 'Update user identity and access fields',
            'users.delete' => 'Soft-delete or permanently remove users',
            'users.suspend' => 'Suspend user login access',
            'users.activate' => 'Restore user login access',
            'users.reset_password' => 'Reset user passwords',
            'users.view_activity' => 'View user activity records',
        ],
        'Profile' => [
            'profile.view_own' => 'View own profile',
            'profile.update_own' => 'Update own profile',
            'profile.change_password_own' => 'Change own password',
            'profile.update_preferences_own' => 'Update own preferences',
        ],
        'Divisions' => [
            'divisions.view' => 'View divisions',
            'divisions.create' => 'Create divisions',
            'divisions.update' => 'Update divisions',
            'divisions.archive' => 'Archive divisions',
            'divisions.manage_members' => 'Move users between divisions',
        ],
        'Roles' => [
            'roles.view' => 'View roles and permission matrix',
            'roles.create' => 'Create roles',
            'roles.update' => 'Update roles',
            'roles.delete' => 'Delete roles',
            'roles.manage_permissions' => 'Change role permissions',
        ],
        'Cases' => [
            'cases.view' => 'View cases',
            'cases.manage' => 'Create and update cases',
        ],
        'Reminders' => [
            'reminders.view' => 'View reminders',
            'reminders.manage' => 'Create and update reminders',
        ],
        'Checklist' => [
            'checklist.view' => 'View checklist',
            'checklist.manage' => 'Create and update checklist items',
        ],
        'Tasks' => [
            'tasks.view_own' => 'View assigned tasks',
            'tasks.update_own' => 'Update assigned task progress',
            'tasks.create' => 'Create and assign tasks',
            'tasks.monitor' => 'View task monitoring dashboard',
            'tasks.review' => 'Review assigned task completion',
        ],
        'Finance' => [
            'finance.view' => 'View finance records',
            'finance.manage' => 'Create and update finance records',
        ],
        'Domains' => [
            'domains.view' => 'View domain records',
            'domains.manage' => 'Create and update domain records',
        ],
        'MoM' => [
            'moms.view' => 'View meeting minutes',
            'moms.manage' => 'Create and update meeting minutes',
        ],
        'Reports' => [
            'reports.view' => 'View reports',
            'reports.create' => 'Create reports',
            'reports.update' => 'Update reports',
            'reports.export' => 'Export reports',
        ],
        'Cancellation Feedback' => [
            'cancellation_feedback.view' => 'View cancellation feedback',
            'cancellation_feedback.manage' => 'Create and update cancellation feedback',
        ],
        'Settings' => [
            'settings.manage' => 'Manage sensitive system settings',
        ],
    ];
}

function tracs_all_permission_keys(): array {
    $keys = [];
    foreach (tracs_permission_catalog() as $permissions) {
        $keys = array_merge($keys, array_keys($permissions));
    }
    return array_values(array_unique($keys));
}

function tracs_default_role_permissions(string $roleSlug): array {
    $all = tracs_all_permission_keys();
    $profile = [
        'profile.view_own',
        'profile.update_own',
        'profile.change_password_own',
        'profile.update_preferences_own',
    ];

    return match ($roleSlug) {
        'super_admin' => $all,
        'admin' => array_values(array_diff($all, [
            'roles.delete',
            'settings.manage',
        ])),
        'supervisor' => array_values(array_unique(array_merge($profile, [
            'users.view',
            'users.update',
            'users.suspend',
            'users.activate',
            'users.reset_password',
            'users.view_activity',
            'divisions.view',
            'divisions.manage_members',
            'cases.view',
            'cases.manage',
            'reminders.view',
            'reminders.manage',
            'checklist.view',
            'checklist.manage',
            'tasks.view_own',
            'tasks.update_own',
            'tasks.create',
            'tasks.monitor',
            'tasks.review',
            'reports.view',
            'reports.create',
            'reports.update',
            'reports.export',
            'domains.view',
            'domains.manage',
            'moms.view',
            'moms.manage',
            'cancellation_feedback.view',
            'cancellation_feedback.manage',
        ]))),
        'viewer' => array_values(array_unique(array_merge($profile, [
            'users.view',
            'divisions.view',
            'roles.view',
            'users.view_activity',
            'dashboard.view',
            'cases.view',
            'reminders.view',
            'checklist.view',
            'finance.view',
            'domains.view',
            'moms.view',
            'reports.view',
            'cancellation_feedback.view',
        ]))),
        'intern' => array_values(array_unique(array_merge($profile, [
            'dashboard.view',
            'checklist.view',
            'tasks.view_own',
            'tasks.update_own',
        ]))),
        default => array_values(array_unique(array_merge($profile, [
            'dashboard.view',
            'cases.view',
            'cases.manage',
            'reminders.view',
            'reminders.manage',
            'checklist.view',
            'checklist.manage',
            'tasks.view_own',
            'tasks.update_own',
            'domains.view',
            'domains.manage',
            'moms.view',
            'moms.manage',
            'reports.view',
            'cancellation_feedback.view',
            'cancellation_feedback.manage',
        ]))),
    };
}

function tracs_legacy_role_slug(?string $legacyRole): string {
    return match ($legacyRole) {
        'admin' => 'admin',
        'viewer' => 'viewer',
        default => 'agent',
    };
}

function tracs_role_fallback_meta(string $roleSlug): array {
    return match ($roleSlug) {
        'super_admin' => ['name' => 'Super Admin', 'level' => 100],
        'admin' => ['name' => 'Admin', 'level' => 80],
        'supervisor' => ['name' => 'Supervisor / Leader', 'level' => 60],
        'intern' => ['name' => 'Intern', 'level' => 30],
        'viewer' => ['name' => 'Viewer / Auditor', 'level' => 20],
        default => ['name' => 'Agent', 'level' => 40],
    };
}

function tracs_select_existing_user_columns(mysqli $conn, string $prefix = 'u'): array {
    $base = [
        'id',
        'email',
        'password',
        'name',
        'created_at',
    ];
    $optional = [
        'role',
        'is_active',
        'last_login_at',
        'updated_at',
        'username',
        'phone',
        'position',
        'status',
        'division_id',
        'role_id',
        'shift_preference',
        'avatar_path',
        'avatar_initials_color',
        'created_by',
        'updated_by',
        'last_activity_at',
        'last_password_change_at',
    ];

    $columns = [];
    foreach (array_merge($base, $optional) as $column) {
        if (in_array($column, $base, true) || tracs_column_exists($conn, 'tracs_users', $column)) {
            $alias = $column === 'role' ? 'legacy_role' : $column;
            $columns[] = "{$prefix}.`{$column}` AS `{$alias}`";
        }
    }
    return $columns;
}

function tracs_normalize_user_row(array $user): array {
    $legacyRole = (string)($user['legacy_role'] ?? $user['role'] ?? 'operator');
    $roleSlug = (string)($user['role_slug'] ?? '');
    if ($roleSlug === '') {
        $roleSlug = tracs_legacy_role_slug($legacyRole);
    }
    $meta = tracs_role_fallback_meta($roleSlug);

    $user['username'] = trim((string)($user['username'] ?? '')) ?: strtok((string)($user['email'] ?? ''), '@');
    $status = trim((string)($user['status'] ?? ''));
    if ($status === '') {
        $status = array_key_exists('is_active', $user) ? (!empty($user['is_active']) ? 'active' : 'inactive') : 'active';
    }
    $user['status'] = $status;
    $user['role_slug'] = $roleSlug;
    $user['role_name'] = trim((string)($user['role_name'] ?? '')) ?: $meta['name'];
    $user['hierarchy_level'] = (int)($user['hierarchy_level'] ?? $meta['level']);
    $user['division_name'] = trim((string)($user['division_name'] ?? ''));
    $user['division_code'] = trim((string)($user['division_code'] ?? ''));
    $user['display_name'] = trim((string)($user['name'] ?? '')) ?: (string)($user['email'] ?? 'User');
    $user['avatar_path'] = tracs_user_avatar_path($user);
    $user['avatar_url'] = tracs_user_avatar_url($user);

    return $user;
}

function tracs_user_avatar_path(array $user): string {
    $path = trim((string)($user['avatar_path'] ?? ''));
    if ($path === '') {
        return '';
    }
    $path = '/' . ltrim($path, '/');
    if (!preg_match('#^/uploads/avatars/[A-Za-z0-9._-]+\.(webp|jpe?g|png)$#i', $path)) {
        return '';
    }
    return $path;
}

function tracs_user_avatar_url(array $user): string {
    return tracs_user_avatar_path($user);
}

function tracs_avatar_img_html(array $user, string $class = '', string $alt = ''): string {
    $url = tracs_user_avatar_url($user);
    if ($url === '') {
        return '';
    }
    $alt = $alt !== '' ? $alt : (($user['display_name'] ?? $user['name'] ?? 'User') . ' profile picture');
    return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string)$alt, ENT_QUOTES, 'UTF-8') . '"' . ($class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '') . ' loading="lazy" decoding="async">';
}

function tracs_get_user_by_id(mysqli $conn, int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }

    $columns = tracs_select_existing_user_columns($conn, 'u');
    $joins = '';
    if (tracs_table_exists($conn, 'tracs_roles') && tracs_column_exists($conn, 'tracs_users', 'role_id')) {
        $columns[] = 'r.name AS role_name';
        $columns[] = 'r.slug AS role_slug';
        $columns[] = 'r.hierarchy_level';
        $columns[] = 'r.is_system_role';
        $joins .= ' LEFT JOIN tracs_roles r ON u.role_id = r.id';
    }
    if (tracs_table_exists($conn, 'tracs_divisions') && tracs_column_exists($conn, 'tracs_users', 'division_id')) {
        $columns[] = 'd.name AS division_name';
        $columns[] = 'd.code AS division_code';
        $joins .= ' LEFT JOIN tracs_divisions d ON u.division_id = d.id';
    }

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM tracs_users u' . $joins . ' WHERE u.id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? tracs_normalize_user_row($row) : null;
}

function tracs_get_user_by_email(mysqli $conn, string $email): ?array {
    $columns = tracs_select_existing_user_columns($conn, 'u');
    $joins = '';
    if (tracs_table_exists($conn, 'tracs_roles') && tracs_column_exists($conn, 'tracs_users', 'role_id')) {
        $columns[] = 'r.name AS role_name';
        $columns[] = 'r.slug AS role_slug';
        $columns[] = 'r.hierarchy_level';
        $joins .= ' LEFT JOIN tracs_roles r ON u.role_id = r.id';
    }
    if (tracs_table_exists($conn, 'tracs_divisions') && tracs_column_exists($conn, 'tracs_users', 'division_id')) {
        $columns[] = 'd.name AS division_name';
        $columns[] = 'd.code AS division_code';
        $joins .= ' LEFT JOIN tracs_divisions d ON u.division_id = d.id';
    }

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM tracs_users u' . $joins . ' WHERE u.email = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? tracs_normalize_user_row($row) : null;
}

function tracs_user_can_login(array $user): bool {
    if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
        return false;
    }
    return ($user['status'] ?? 'active') === 'active';
}

function tracs_sync_session_user(array $user): void {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_name'] = (string)($user['display_name'] ?? $user['name'] ?? $user['email']);
    $_SESSION['user_role_slug'] = (string)($user['role_slug'] ?? '');
    $_SESSION['user_role_name'] = (string)($user['role_name'] ?? '');
    $_SESSION['user_division_id'] = (int)($user['division_id'] ?? 0);
}

function tracs_touch_user_activity(mysqli $conn, int $userId): void {
    if ($userId <= 0 || !tracs_column_exists($conn, 'tracs_users', 'last_activity_at')) {
        return;
    }

    $last = (int)($_SESSION['tracs_last_activity_touch'] ?? 0);
    if ($last > 0 && (time() - $last) < 60) {
        return;
    }
    $_SESSION['tracs_last_activity_touch'] = time();

    $stmt = $conn->prepare('UPDATE tracs_users SET last_activity_at = NOW() WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

function tracs_user_permissions(mysqli $conn, int $userId): array {
    $user = tracs_get_user_by_id($conn, $userId);
    if (!$user || !tracs_user_can_login($user)) {
        return [];
    }
    if (($user['role_slug'] ?? '') === 'super_admin') {
        return tracs_all_permission_keys();
    }

    if (tracs_table_exists($conn, 'tracs_role_permissions') && !empty($user['role_id'])) {
        $stmt = $conn->prepare("
            SELECT p.permission_key
            FROM tracs_role_permissions rp
            INNER JOIN tracs_permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        if ($stmt) {
            $roleId = (int)$user['role_id'];
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[] = (string)$row['permission_key'];
            }
            $stmt->close();
            return array_values(array_unique($permissions));
        }
    }

    return tracs_default_role_permissions((string)($user['role_slug'] ?? 'agent'));
}

function tracs_user_can(mysqli $conn, string $permission, ?int $userId = null): bool {
    $uid = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }

    $user = tracs_get_user_by_id($conn, $uid);
    if (!$user || !tracs_user_can_login($user)) {
        return false;
    }
    if (($user['role_slug'] ?? '') === 'super_admin') {
        return true;
    }

    return in_array($permission, tracs_user_permissions($conn, $uid), true);
}

function tracs_require_permission(mysqli $conn, string $permission): void {
    if (tracs_user_can($conn, $permission)) {
        return;
    }

    http_response_code(403);
    if (function_exists('tracs_is_api_request') && tracs_is_api_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
    } else {
        echo 'Forbidden';
    }
    exit;
}

function tracs_current_user_level(mysqli $conn): int {
    $user = tracs_get_user_by_id($conn, (int)($_SESSION['user_id'] ?? 0));
    return $user ? (int)($user['hierarchy_level'] ?? 0) : 0;
}

function tracs_role_by_id(mysqli $conn, int $roleId): ?array {
    if ($roleId <= 0 || !tracs_table_exists($conn, 'tracs_roles')) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM tracs_roles WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function tracs_can_assign_role(mysqli $conn, int $actorId, int $roleId): bool {
    $actor = tracs_get_user_by_id($conn, $actorId);
    $role = tracs_role_by_id($conn, $roleId);
    if (!$actor || !$role) {
        return false;
    }
    if (($actor['role_slug'] ?? '') === 'super_admin') {
        return true;
    }
    return (int)$role['hierarchy_level'] <= (int)($actor['hierarchy_level'] ?? 0);
}

function tracs_is_last_active_super_admin(mysqli $conn, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }
    if (!tracs_table_exists($conn, 'tracs_roles') || !tracs_column_exists($conn, 'tracs_users', 'role_id')) {
        return false;
    }

    $user = tracs_get_user_by_id($conn, $userId);
    if (!$user || ($user['role_slug'] ?? '') !== 'super_admin') {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM tracs_users u
        INNER JOIN tracs_roles r ON r.id = u.role_id
        WHERE r.slug = 'super_admin'
          AND u.is_active = 1
          AND COALESCE(u.status, 'active') = 'active'
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count <= 1 && tracs_user_can_login($user);
}

function tracs_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        $parts = array_map('trim', explode(',', $value));
        return substr($parts[0] ?? $value, 0, 45);
    }
    return '';
}

function tracs_scrub_sensitive(mixed $value): mixed {
    if (!is_array($value)) {
        return $value;
    }

    $clean = [];
    foreach ($value as $key => $item) {
        $keyString = strtolower((string)$key);
        if (str_contains($keyString, 'password') || str_contains($keyString, 'token') || str_contains($keyString, 'secret')) {
            $clean[$key] = '[redacted]';
            continue;
        }
        $clean[$key] = tracs_scrub_sensitive($item);
    }
    return $clean;
}

function tracs_json_or_null(mixed $value): ?string {
    if ($value === null || $value === []) {
        return null;
    }
    return json_encode(tracs_scrub_sensitive($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function tracs_log_user_event(
    mysqli $conn,
    int $actorId,
    string $action,
    string $targetType,
    ?int $targetId = null,
    mixed $before = null,
    mixed $after = null,
    ?string $reason = null
): void {
    $beforeJson = tracs_json_or_null($before);
    $afterJson = tracs_json_or_null($after);
    $ip = tracs_client_ip();
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $reason = trim((string)($reason ?? '')) ?: null;

    if (tracs_table_exists($conn, 'tracs_user_activity_logs')) {
        $stmt = $conn->prepare("
            INSERT INTO tracs_user_activity_logs
              (actor_user_id, target_type, target_id, action, before_data, after_data, reason, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param(
                'isissssss',
                $actorId,
                $targetType,
                $targetId,
                $action,
                $beforeJson,
                $afterJson,
                $reason,
                $ip,
                $agent
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    if (tracs_table_exists($conn, 'tracs_activity_logs')) {
        $descTarget = $targetId ? "{$targetType} #{$targetId}" : $targetType;
        $description = trim($action . ' · ' . $descTarget . ($reason ? ' · ' . $reason : ''));
        $module = 'User Management';
        $hasIp = tracs_column_exists($conn, 'tracs_activity_logs', 'ip_address');
        $stmt = $conn->prepare($hasIp
            ? "INSERT INTO tracs_activity_logs (user_id, action, module, description, reference_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
            : "INSERT INTO tracs_activity_logs (user_id, action, module, description, reference_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
        );
        if ($stmt) {
            if ($hasIp) {
                $stmt->bind_param('isssis', $actorId, $action, $module, $description, $targetId, $ip);
            } else {
                $stmt->bind_param('isssi', $actorId, $action, $module, $description, $targetId);
            }
            $stmt->execute();
            $stmt->close();
        }
    }
}

function tracs_user_initials(string $name, string $fallback = 'U'): string {
    $name = trim($name);
    if ($name === '') {
        return strtoupper(substr($fallback, 0, 2));
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    $first = strtoupper(substr((string)($parts[0] ?? ''), 0, 1));
    $last = strtoupper(substr((string)($parts[count($parts) - 1] ?? ''), 0, 1));
    return $first . ($last !== $first ? $last : '');
}

function tracs_user_role_badge_class(string $roleSlug): string {
    return match ($roleSlug) {
        'super_admin' => 'b-critical',
        'admin' => 'b-active',
        'supervisor' => 'b-info',
        'intern' => 'b-warning',
        'viewer' => 'b-done',
        default => 'b-low',
    };
}

function tracs_user_status_badge_class(string $status): string {
    return match ($status) {
        'active' => 'b-active',
        'suspended' => 'b-critical',
        default => 'b-done',
    };
}

function tracs_division_capacity_state(int $activeCount): string {
    if ($activeCount >= 11) {
        return 'overloaded';
    }
    if ($activeCount >= 5) {
        return 'ideal';
    }
    return 'under';
}

function getUserDisplayName(mysqli $conn, int $userId): string {
    $user = tracs_get_user_by_id($conn, $userId);
    return $user['display_name'] ?? 'System';
}

function getUserInitials(mysqli $conn, int $userId): string {
    $user = tracs_get_user_by_id($conn, $userId);
    return tracs_user_initials((string)($user['display_name'] ?? ''), (string)($user['email'] ?? 'U'));
}

function getUserRoleBadge(mysqli $conn, int $userId): string {
    $user = tracs_get_user_by_id($conn, $userId);
    if (!$user) {
        return '<span class="badge b-done">Unknown</span>';
    }
    $class = tracs_user_role_badge_class((string)$user['role_slug']);
    return '<span class="badge ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars((string)$user['role_name'], ENT_QUOTES, 'UTF-8') . '</span>';
}

function getUserDivisionBadge(mysqli $conn, int $userId): string {
    $user = tracs_get_user_by_id($conn, $userId);
    $label = trim((string)($user['division_name'] ?? '')) ?: 'No Division';
    $class = trim((string)($user['division_name'] ?? '')) ? 'b-info' : 'b-done';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function tracs_password_policy_errors(string $password, ?string $currentHash = null): array {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($currentHash && password_verify($password, $currentHash)) {
        $errors[] = 'New password cannot be the same as the current password.';
    }
    return $errors;
}

function tracs_password_strength_score(string $password): int {
    $score = 0;
    if (strlen($password) >= 8) $score++;
    if (preg_match('/[A-Z]/', $password)) $score++;
    if (preg_match('/[a-z]/', $password)) $score++;
    if (preg_match('/[0-9]/', $password)) $score++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;
    return $score;
}

function tracs_generate_temporary_password(int $length = 14): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*?';
    $bytes = random_bytes($length);
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
    }
    return $password;
}
