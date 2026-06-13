<?php

require_once __DIR__ . '/../../core/user_management.php';
require_once __DIR__ . '/../../core/security/auth_hardening.php';

class UserManagementModel {
    private mysqli $conn;

    public function __construct(mysqli $connection) {
        $this->conn = $connection;
    }

    public function schemaReady(): bool {
        return tracs_user_management_schema_ready($this->conn);
    }

    private function scalar(string $sql, string $types = '', array $params = []): int {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['c'] ?? 0);
    }

    public function getStats(): array {
        $stats = [
            'total_users' => $this->scalar('SELECT COUNT(*) AS c FROM tracs_users'),
            'active_users' => $this->scalar("SELECT COUNT(*) AS c FROM tracs_users WHERE is_active = 1 AND COALESCE(status, 'active') = 'active'"),
            'active_agents' => $this->scalar("
                SELECT COUNT(*) AS c
                FROM tracs_users u
                LEFT JOIN tracs_roles r ON u.role_id = r.id
                WHERE u.is_active = 1
                  AND COALESCE(u.status, 'active') = 'active'
                  AND COALESCE(r.slug, CASE WHEN u.role = 'viewer' THEN 'viewer' WHEN u.role = 'admin' THEN 'admin' ELSE 'agent' END) = 'agent'
            "),
            'suspended_users' => $this->scalar("SELECT COUNT(*) AS c FROM tracs_users WHERE COALESCE(status, 'active') = 'suspended'"),
            'inactive_users' => $this->scalar("SELECT COUNT(*) AS c FROM tracs_users WHERE is_active = 0 OR COALESCE(status, 'active') = 'inactive'"),
            'divisions_count' => $this->scalar("SELECT COUNT(*) AS c FROM tracs_divisions WHERE status = 'active'"),
            'users_without_division' => $this->scalar("SELECT COUNT(*) AS c FROM tracs_users WHERE is_active = 1 AND COALESCE(status, 'active') = 'active' AND division_id IS NULL"),
        ];
        if ($this->internProfilesReady()) {
            $stats['active_interns'] = $this->scalar("
                SELECT COUNT(*) AS c
                FROM tracs_users u
                INNER JOIN tracs_roles r ON r.id = u.role_id AND r.slug = 'intern'
                INNER JOIN user_intern_profiles ip ON ip.user_id = u.id
                WHERE u.is_active = 1
                  AND COALESCE(u.status, 'active') = 'active'
                  AND ip.internship_status IN ('upcoming','active','ending_soon','extended')
            ");
            $stats['interns_ending_soon'] = $this->scalar("
                SELECT COUNT(*) AS c
                FROM tracs_users u
                INNER JOIN tracs_roles r ON r.id = u.role_id AND r.slug = 'intern'
                INNER JOIN user_intern_profiles ip ON ip.user_id = u.id
                WHERE COALESCE(u.status, 'active') = 'active'
                  AND ip.internship_status NOT IN ('completed','terminated')
                  AND ip.internship_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
            ");
            $stats['interns_without_mentor'] = $this->scalar("
                SELECT COUNT(*) AS c
                FROM tracs_users u
                INNER JOIN tracs_roles r ON r.id = u.role_id AND r.slug = 'intern'
                INNER JOIN user_intern_profiles ip ON ip.user_id = u.id
                WHERE COALESCE(u.status, 'active') = 'active'
                  AND ip.mentor_user_id IS NULL
            ");
            $stats['interns_pending_evaluation'] = $this->scalar("
                SELECT COUNT(*) AS c
                FROM tracs_users u
                INNER JOIN tracs_roles r ON r.id = u.role_id AND r.slug = 'intern'
                INNER JOIN user_intern_profiles ip ON ip.user_id = u.id
                WHERE COALESCE(u.status, 'active') = 'active'
                  AND ip.evaluation_status IN ('not_started','in_review','needs_improvement')
            ");
        } else {
            $stats['active_interns'] = 0;
            $stats['interns_ending_soon'] = 0;
            $stats['interns_without_mentor'] = 0;
            $stats['interns_pending_evaluation'] = 0;
        }
        return $stats;
    }

    public function internProfilesReady(): bool {
        return tracs_table_exists($this->conn, 'user_intern_profiles');
    }

    public function getRoles(): array {
        $result = $this->conn->query("
            SELECT
                r.*,
                COUNT(DISTINCT u.id) AS users_count,
                COUNT(DISTINCT rp.permission_id) AS permissions_count
            FROM tracs_roles r
            LEFT JOIN tracs_users u ON u.role_id = r.id
            LEFT JOIN tracs_role_permissions rp ON rp.role_id = r.id
            GROUP BY r.id
            ORDER BY r.hierarchy_level DESC, r.name ASC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getPermissions(): array {
        $result = $this->conn->query("
            SELECT *
            FROM tracs_permissions
            ORDER BY category ASC, permission_key ASC
        ");
        $permissions = [];
        if (!$result) {
            return $permissions;
        }
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['category']][] = $row;
        }
        return $permissions;
    }

    public function getRolePermissionMap(): array {
        $result = $this->conn->query("
            SELECT rp.role_id, p.permission_key, p.id AS permission_id
            FROM tracs_role_permissions rp
            INNER JOIN tracs_permissions p ON p.id = rp.permission_id
        ");
        $map = [];
        if (!$result) {
            return $map;
        }
        while ($row = $result->fetch_assoc()) {
            $roleId = (int)$row['role_id'];
            $map[$roleId]['keys'][] = $row['permission_key'];
            $map[$roleId]['ids'][] = (int)$row['permission_id'];
        }
        return $map;
    }

    public function getUserOptions(bool $includeInactive = false): array {
        $where = $includeInactive ? '1=1' : "u.is_active = 1 AND COALESCE(u.status, 'active') = 'active'";
        $result = $this->conn->query("
            SELECT u.id, u.name, u.email, u.username, u.division_id, u.is_active, u.status, r.slug AS role_slug, r.name AS role_name
            FROM tracs_users u
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            WHERE {$where}
            ORDER BY COALESCE(NULLIF(u.name,''), u.email) ASC
        ");
        return $result ? array_map('tracs_normalize_user_row', $result->fetch_all(MYSQLI_ASSOC)) : [];
    }

    public function listUsers(array $filters, array $actor): array {
        $where = ['1=1'];
        $types = '';
        $params = [];

        $internReady = $this->internProfilesReady();
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $needle = '%' . $q . '%';
            if ($internReady) {
                $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.position LIKE ? OR ip.university_name LIKE ? OR ip.study_program LIKE ? OR ip.special_notes LIKE ? OR mentor.name LIKE ? OR mentor.email LIKE ?)';
                $types .= 'ssssssssss';
                array_push($params, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle);
            } else {
                $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.position LIKE ?)';
                $types .= 'sssss';
                array_push($params, $needle, $needle, $needle, $needle, $needle);
            }
        }
        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['role_id'];
        }
        if (isset($filters['division_id']) && $filters['division_id'] !== '') {
            if ((int)$filters['division_id'] === 0) {
                $where[] = 'u.division_id IS NULL';
            } else {
                $where[] = 'u.division_id = ?';
                $types .= 'i';
                $params[] = (int)$filters['division_id'];
            }
        }
        if (!empty($filters['status'])) {
            $where[] = "COALESCE(u.status, 'active') = ?";
            $types .= 's';
            $params[] = (string)$filters['status'];
        }
        if (!empty($filters['last_active'])) {
            match ((string)$filters['last_active']) {
                'today' => $where[] = 'DATE(u.last_activity_at) = CURDATE()',
                '7d' => $where[] = 'u.last_activity_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                '30d' => $where[] = 'u.last_activity_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                'never' => $where[] = 'u.last_activity_at IS NULL',
                default => null,
            };
        }
        if ($internReady) {
            if (!empty($filters['internship_status'])) {
                $where[] = 'ip.internship_status = ?';
                $types .= 's';
                $params[] = (string)$filters['internship_status'];
            }
            if (!empty($filters['evaluation_status'])) {
                $where[] = 'ip.evaluation_status = ?';
                $types .= 's';
                $params[] = (string)$filters['evaluation_status'];
            }
            if (!empty($filters['university'])) {
                $where[] = 'ip.university_name = ?';
                $types .= 's';
                $params[] = (string)$filters['university'];
            }
            if (!empty($filters['mentor_user_id'])) {
                if ((int)$filters['mentor_user_id'] === -1) {
                    $where[] = 'ip.mentor_user_id IS NULL';
                } else {
                    $where[] = 'ip.mentor_user_id = ?';
                    $types .= 'i';
                    $params[] = (int)$filters['mentor_user_id'];
                }
            }
            if (!empty($filters['intern_monitor'])) {
                match ((string)$filters['intern_monitor']) {
                    'ending_soon' => $where[] = "ip.internship_status NOT IN ('completed','terminated') AND ip.internship_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)",
                    'end_passed' => $where[] = "ip.internship_status NOT IN ('completed','terminated') AND ip.internship_end_date < CURDATE()",
                    'without_mentor' => $where[] = 'ip.mentor_user_id IS NULL',
                    'pending_evaluation' => $where[] = "ip.evaluation_status IN ('not_started','in_review','needs_improvement')",
                    default => null,
                };
            }
        }

        if (($actor['role_slug'] ?? '') === 'supervisor' && !empty($actor['division_id'])) {
            $where[] = 'u.division_id = ?';
            $types .= 'i';
            $params[] = (int)$actor['division_id'];
        }

        $avatarSelect = tracs_column_exists($this->conn, 'tracs_users', 'avatar_path')
            ? 'u.avatar_path,'
            : 'NULL AS avatar_path,';
        $twoFactorSelect = tracs_two_factor_schema_ready($this->conn)
            ? 'u.two_factor_enabled, u.two_factor_confirmed_at, u.two_factor_reset_required, u.two_factor_failed_attempts, u.two_factor_locked_until, u.two_factor_last_verified_at,'
            : '0 AS two_factor_enabled, NULL AS two_factor_confirmed_at, 1 AS two_factor_reset_required, 0 AS two_factor_failed_attempts, NULL AS two_factor_locked_until, NULL AS two_factor_last_verified_at,';
        $internSelect = $internReady ? ",
                ip.university_name, ip.study_program, ip.internship_start_date, ip.internship_end_date,
                ip.mentor_user_id, ip.internship_status, ip.evaluation_status, ip.skill_level,
                ip.allowed_task_scope, ip.special_notes,
                COALESCE(NULLIF(mentor.name,''), mentor.email) AS mentor_name" : "";
        $internJoins = $internReady ? "
            LEFT JOIN user_intern_profiles ip ON ip.user_id = u.id
            LEFT JOIN tracs_users mentor ON mentor.id = ip.mentor_user_id" : "";
        $sql = "
            SELECT
                u.id, u.email, u.phone, u.position, u.name, u.username, u.role AS legacy_role,
                u.is_active, u.status, u.division_id, u.role_id, u.shift_preference,
                {$avatarSelect}
                u.avatar_initials_color, {$twoFactorSelect} u.created_by, u.updated_by, u.last_login_at,
                u.last_activity_at, u.last_password_change_at, u.created_at, u.updated_at,
                r.name AS role_name, r.slug AS role_slug, r.hierarchy_level,
                d.name AS division_name, d.code AS division_code,
                cb.name AS created_by_name,
                ub.name AS updated_by_name
                {$internSelect}
            FROM tracs_users u
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            LEFT JOIN tracs_divisions d ON d.id = u.division_id
            LEFT JOIN tracs_users cb ON cb.id = u.created_by
            LEFT JOIN tracs_users ub ON ub.id = u.updated_by
            {$internJoins}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
              CASE COALESCE(u.status, 'active') WHEN 'active' THEN 0 WHEN 'inactive' THEN 1 ELSE 2 END,
              COALESCE(NULLIF(u.name,''), u.email) ASC
            LIMIT 250
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $this->normalizeUserWithIntern($row);
        }
        $stmt->close();

        $summaries = $this->getUserCreatedSummaries(array_column($users, 'id'));
        foreach ($users as &$user) {
            $user['created_summary'] = $summaries[(int)$user['id']] ?? [];
        }
        unset($user);

        return $users;
    }

    public function getUserCreatedSummaries(array $userIds): array {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $ids = array_values(array_filter($ids, fn($id) => $id > 0));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $summary = [];
        foreach ($ids as $id) {
            $summary[$id] = [];
        }

        $sources = [
            'Cases' => ['tracs_cases', ['created_by', 'user_id']],
            'Reminders' => ['tracs_reminders', ['created_by', 'user_id']],
            'Checklist' => ['tracs_side_tasks', ['created_by', 'user_id']],
            'Shift Reports' => ['tracs_shift_reports', ['created_by']],
            'MoM' => ['tracs_moms', ['created_by']],
            'Finance' => ['balance_transfers', ['created_by']],
            'Domains' => ['tracs_domains', ['created_by', 'user_id']],
            'Cancellation Feedback' => ['tracs_cancellation_feedback', ['created_by']],
        ];

        foreach ($sources as $label => [$table, $columns]) {
            if (!tracs_table_exists($this->conn, $table)) {
                continue;
            }
            $column = null;
            foreach ($columns as $candidate) {
                if (tracs_column_exists($this->conn, $table, $candidate)) {
                    $column = $candidate;
                    break;
                }
            }
            if (!$column) {
                continue;
            }
            $tableSql = tracs_identifier($table);
            $columnSql = tracs_identifier($column);
            $result = $this->conn->query("
                SELECT {$columnSql} AS uid, COUNT(*) AS c
                FROM {$tableSql}
                WHERE {$columnSql} IN ({$in})
                GROUP BY {$columnSql}
            ");
            if (!$result) {
                continue;
            }
            while ($row = $result->fetch_assoc()) {
                $uid = (int)$row['uid'];
                $summary[$uid][$label] = (int)$row['c'];
            }
        }

        return $summary;
    }

    public function getUserById(int $userId): ?array {
        $user = tracs_get_user_by_id($this->conn, $userId);
        if (!$user) {
            return null;
        }
        return $this->mergeInternProfile($user);
    }

    private function normalizeUserWithIntern(array $row): array {
        return $this->decorateInternState(tracs_normalize_user_row($row));
    }

    private function mergeInternProfile(array $user): array {
        if (!$this->internProfilesReady() || empty($user['id'])) {
            return $this->decorateInternState($user);
        }
        $stmt = $this->conn->prepare("
            SELECT ip.*, COALESCE(NULLIF(m.name,''), m.email) AS mentor_name
            FROM user_intern_profiles ip
            LEFT JOIN tracs_users m ON m.id = ip.mentor_user_id
            WHERE ip.user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return $this->decorateInternState($user);
        }
        $userId = (int)$user['id'];
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($profile) {
            foreach (['university_name','study_program','internship_start_date','internship_end_date','mentor_user_id','internship_status','evaluation_status','skill_level','allowed_task_scope','special_notes','mentor_name'] as $field) {
                $user[$field] = $profile[$field] ?? null;
            }
        }
        return $this->decorateInternState($user);
    }

    private function decorateInternState(array $user): array {
        $start = (string)($user['internship_start_date'] ?? '');
        $end = (string)($user['internship_end_date'] ?? '');
        $status = (string)($user['internship_status'] ?? '');
        $today = new DateTimeImmutable('today');
        $daysRemaining = null;
        $monitorState = '';
        if ($end !== '' && strtotime($end)) {
            $endDate = new DateTimeImmutable($end);
            $daysRemaining = (int)$today->diff($endDate)->format('%r%a');
            if (!in_array($status, ['completed', 'terminated'], true)) {
                if ($daysRemaining < 0) {
                    $monitorState = 'end_passed';
                } elseif ($daysRemaining <= 14) {
                    $monitorState = 'ending_soon';
                }
            }
        }
        if ($monitorState === '' && $status !== '') {
            $monitorState = $status;
        }
        $user['internship_days_remaining'] = $daysRemaining;
        $user['internship_monitor_state'] = $monitorState;
        $user['is_intern'] = (($user['role_slug'] ?? '') === 'intern') || $start !== '' || $end !== '';
        return $user;
    }

    public function usernameExists(string $username, ?int $ignoreUserId = null): bool {
        $sql = 'SELECT id FROM tracs_users WHERE username = ?';
        $types = 's';
        $params = [$username];
        if ($ignoreUserId) {
            $sql .= ' AND id <> ?';
            $types .= 'i';
            $params[] = $ignoreUserId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function emailExists(string $email, ?int $ignoreUserId = null): bool {
        $sql = 'SELECT id FROM tracs_users WHERE email = ?';
        $types = 's';
        $params = [$email];
        if ($ignoreUserId) {
            $sql .= ' AND id <> ?';
            $types .= 'i';
            $params[] = $ignoreUserId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createUser(array $data): int {
        $legacyRole = $this->legacyRoleForRoleId((int)$data['role_id']);
        $isActive = $data['status'] === 'active' ? 1 : 0;
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_users
              (name, username, email, phone, position, password, role, is_active, status, role_id, division_id,
               shift_preference, avatar_initials_color, created_by, updated_by, last_password_change_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to create user.');
        }
        $stmt->bind_param(
            'sssssssisiissii',
            $data['name'],
            $data['username'],
            $data['email'],
            $data['phone'],
            $data['position'],
            $data['password_hash'],
            $legacyRole,
            $isActive,
            $data['status'],
            $data['role_id'],
            $data['division_id'],
            $data['shift_preference'],
            $data['avatar_initials_color'],
            $data['actor_id'],
            $data['actor_id']
        );
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to create user.');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateUser(int $userId, array $data): void {
        $legacyRole = $this->legacyRoleForRoleId((int)$data['role_id']);
        $isActive = $data['status'] === 'active' ? 1 : 0;
        $stmt = $this->conn->prepare("
            UPDATE tracs_users
            SET name = ?, username = ?, email = ?, phone = ?, position = ?, role = ?, is_active = ?,
                status = ?, role_id = ?, division_id = ?, shift_preference = ?, avatar_initials_color = ?,
                updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update user.');
        }
        $stmt->bind_param(
            'ssssssisiissii',
            $data['name'],
            $data['username'],
            $data['email'],
            $data['phone'],
            $data['position'],
            $legacyRole,
            $isActive,
            $data['status'],
            $data['role_id'],
            $data['division_id'],
            $data['shift_preference'],
            $data['avatar_initials_color'],
            $data['actor_id'],
            $userId
        );
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update user.');
        }
        $stmt->close();
    }

    public function updateAvatarPath(int $userId, ?string $avatarPath, int $actorId): void {
        if (!tracs_column_exists($this->conn, 'tracs_users', 'avatar_path')) {
            throw new RuntimeException('Run the avatar profile picture migration before saving photos.');
        }
        $stmt = $this->conn->prepare("
            UPDATE tracs_users
            SET avatar_path = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update profile picture.');
        }
        $stmt->bind_param('sii', $avatarPath, $actorId, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update profile picture.');
        }
        $stmt->close();
    }

    private function legacyRoleForRoleId(int $roleId): string {
        $role = tracs_role_by_id($this->conn, $roleId);
        return match ($role['slug'] ?? 'agent') {
            'super_admin', 'admin' => 'admin',
            'viewer' => 'viewer',
            default => 'operator',
        };
    }

    public function upsertInternProfile(int $userId, array $data): array {
        if (!$this->internProfilesReady()) {
            throw new RuntimeException('Intern profile table is not installed. Run the intern user management migration.');
        }
        $before = $this->getInternProfile($userId);
        $stmt = $this->conn->prepare("
            INSERT INTO user_intern_profiles
              (user_id, university_name, study_program, internship_start_date, internship_end_date,
               mentor_user_id, internship_status, evaluation_status, skill_level, allowed_task_scope,
               special_notes, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              university_name = VALUES(university_name),
              study_program = VALUES(study_program),
              internship_start_date = VALUES(internship_start_date),
              internship_end_date = VALUES(internship_end_date),
              mentor_user_id = VALUES(mentor_user_id),
              internship_status = VALUES(internship_status),
              evaluation_status = VALUES(evaluation_status),
              skill_level = VALUES(skill_level),
              allowed_task_scope = VALUES(allowed_task_scope),
              special_notes = VALUES(special_notes),
              updated_by = VALUES(updated_by),
              updated_at = NOW()
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to save intern profile.');
        }
        $stmt->bind_param(
            'issssisssssii',
            $userId,
            $data['university_name'],
            $data['study_program'],
            $data['internship_start_date'],
            $data['internship_end_date'],
            $data['mentor_user_id'],
            $data['internship_status'],
            $data['evaluation_status'],
            $data['skill_level'],
            $data['allowed_task_scope'],
            $data['special_notes'],
            $data['actor_id'],
            $data['actor_id']
        );
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to save intern profile.');
        }
        $stmt->close();
        return ['before' => $before, 'after' => $this->getInternProfile($userId)];
    }

    public function getInternProfile(int $userId): ?array {
        if (!$this->internProfilesReady()) {
            return null;
        }
        $stmt = $this->conn->prepare("
            SELECT ip.*, COALESCE(NULLIF(m.name,''), m.email) AS mentor_name
            FROM user_intern_profiles ip
            LEFT JOIN tracs_users m ON m.id = ip.mentor_user_id
            WHERE ip.user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $profile ?: null;
    }

    public function getInternUniversities(): array {
        if (!$this->internProfilesReady()) {
            return [];
        }
        $result = $this->conn->query("
            SELECT DISTINCT university_name
            FROM user_intern_profiles
            WHERE TRIM(university_name) <> ''
            ORDER BY university_name ASC
        ");
        return $result ? array_map(fn($row) => $row['university_name'], $result->fetch_all(MYSQLI_ASSOC)) : [];
    }

    public function getMentorOptions(): array {
        $result = $this->conn->query("
            SELECT u.id, u.name, u.email, u.username, u.division_id, u.is_active, u.status, r.slug AS role_slug, r.name AS role_name
            FROM tracs_users u
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            WHERE u.is_active = 1
              AND COALESCE(u.status, 'active') = 'active'
              AND COALESCE(r.slug, '') IN ('super_admin','admin','supervisor')
            ORDER BY COALESCE(NULLIF(u.name,''), u.email) ASC
        ");
        return $result ? array_map('tracs_normalize_user_row', $result->fetch_all(MYSQLI_ASSOC)) : [];
    }

    public function updateUserStatus(int $userId, string $status, int $actorId): void {
        $isActive = $status === 'active' ? 1 : 0;
        $stmt = $this->conn->prepare("
            UPDATE tracs_users
            SET status = ?, is_active = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update user status.');
        }
        $stmt->bind_param('siii', $status, $isActive, $actorId, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update user status.');
        }
        $stmt->close();
    }

    public function updatePasswordHash(int $userId, string $hash, int $actorId): void {
        $stmt = $this->conn->prepare("
            UPDATE tracs_users
            SET password = ?, last_password_change_at = NOW(), updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update password.');
        }
        $stmt->bind_param('sii', $hash, $actorId, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update password.');
        }
        $stmt->close();
    }

    public function resetTwoFactor(int $userId): void {
        tracs_two_factor_reset_for_user($this->conn, $userId);
    }

    public function getDivisions(): array {
        $result = $this->conn->query("
            SELECT
                d.*,
                COALESCE(NULLIF(s.name,''), s.email) AS supervisor_name,
                COUNT(u.id) AS users_count,
                SUM(CASE WHEN u.is_active = 1 AND COALESCE(u.status, 'active') = 'active' THEN 1 ELSE 0 END) AS active_users_count
            FROM tracs_divisions d
            LEFT JOIN tracs_users s ON s.id = d.supervisor_id
            LEFT JOIN tracs_users u ON u.division_id = d.id
            GROUP BY d.id
            ORDER BY d.status ASC, d.name ASC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getDivisionMemberMap(): array {
        $result = $this->conn->query("
            SELECT
                u.id, u.name, u.email, u.username, u.division_id, u.status,
                r.name AS role_name, r.slug AS role_slug
            FROM tracs_users u
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            WHERE u.division_id IS NOT NULL
            ORDER BY COALESCE(NULLIF(u.name,''), u.email) ASC
        ");
        $map = [];
        if (!$result) {
            return $map;
        }
        while ($row = $result->fetch_assoc()) {
            $divisionId = (int)$row['division_id'];
            $map[$divisionId][] = tracs_normalize_user_row($row);
        }
        return $map;
    }

    public function getDivisionById(int $divisionId): ?array {
        $stmt = $this->conn->prepare('SELECT * FROM tracs_divisions WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $divisionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function divisionCodeExists(string $code, ?int $ignoreDivisionId = null): bool {
        $sql = 'SELECT id FROM tracs_divisions WHERE code = ?';
        $types = 's';
        $params = [$code];
        if ($ignoreDivisionId) {
            $sql .= ' AND id <> ?';
            $types .= 'i';
            $params[] = $ignoreDivisionId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createDivision(array $data): int {
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_divisions
              (name, code, description, supervisor_id, status, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to create division.');
        }
        $stmt->bind_param('sssiii', $data['name'], $data['code'], $data['description'], $data['supervisor_id'], $data['actor_id'], $data['actor_id']);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to create division.');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateDivision(int $divisionId, array $data): void {
        $stmt = $this->conn->prepare("
            UPDATE tracs_divisions
            SET name = ?, code = ?, description = ?, supervisor_id = ?, status = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update division.');
        }
        $stmt->bind_param('sssisii', $data['name'], $data['code'], $data['description'], $data['supervisor_id'], $data['status'], $data['actor_id'], $divisionId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update division.');
        }
        $stmt->close();
    }

    public function archiveDivision(int $divisionId, int $actorId): void {
        $stmt = $this->conn->prepare("
            UPDATE tracs_divisions
            SET status = 'archived', updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to archive division.');
        }
        $stmt->bind_param('ii', $actorId, $divisionId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to archive division.');
        }
        $stmt->close();
    }

    public function activeUsersInDivision(int $divisionId): int {
        return $this->scalar("
            SELECT COUNT(*) AS c
            FROM tracs_users
            WHERE division_id = ?
              AND is_active = 1
              AND COALESCE(status, 'active') = 'active'
        ", 'i', [$divisionId]);
    }

    public function replaceRolePermissions(int $roleId, array $permissionIds): void {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('DELETE FROM tracs_role_permissions WHERE role_id = ?');
            if (!$stmt) {
                throw new RuntimeException('Unable to update permissions.');
            }
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $stmt->close();

            if ($permissionIds) {
                $stmt = $this->conn->prepare('INSERT IGNORE INTO tracs_role_permissions (role_id, permission_id) VALUES (?, ?)');
                if (!$stmt) {
                    throw new RuntimeException('Unable to update permissions.');
                }
                foreach ($permissionIds as $permissionId) {
                    $stmt->bind_param('ii', $roleId, $permissionId);
                    $stmt->execute();
                }
                $stmt->close();
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getPermissionIdsByKeys(array $keys): array {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        if (!$keys) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = $this->conn->prepare("SELECT id, permission_key FROM tracs_permissions WHERE permission_key IN ({$placeholders})");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$keys);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[$row['permission_key']] = (int)$row['id'];
        }
        $stmt->close();
        return $ids;
    }

    public function getActivityLogs(array $filters, int $limit = 100): array {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['actor_user_id'])) {
            $where[] = 'l.actor_user_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['actor_user_id'];
        }
        if (!empty($filters['target_user_id'])) {
            $where[] = "l.target_type = 'user' AND l.target_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['target_user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'l.action = ?';
            $types .= 's';
            $params[] = (string)$filters['action'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(l.created_at) >= ?';
            $types .= 's';
            $params[] = (string)$filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(l.created_at) <= ?';
            $types .= 's';
            $params[] = (string)$filters['to'];
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $needle = '%' . $q . '%';
            $where[] = '(l.action LIKE ? OR l.reason LIKE ? OR l.target_type LIKE ? OR au.name LIKE ? OR au.email LIKE ? OR tu.name LIKE ? OR tu.email LIKE ? OR td.name LIKE ? OR tr.name LIKE ?)';
            $types .= 'sssssssss';
            array_push($params, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle);
        }

        $limit = max(25, min(250, $limit));
        $sql = "
            SELECT
                l.*,
                COALESCE(NULLIF(au.name,''), au.email, 'System') AS actor_name,
                COALESCE(NULLIF(tu.name,''), tu.email, td.name, tr.name, CONCAT(l.target_type, ' #', l.target_id)) AS target_name
            FROM tracs_user_activity_logs l
            LEFT JOIN tracs_users au ON au.id = l.actor_user_id
            LEFT JOIN tracs_users tu ON l.target_type = 'user' AND tu.id = l.target_id
            LEFT JOIN tracs_divisions td ON l.target_type = 'division' AND td.id = l.target_id
            LEFT JOIN tracs_roles tr ON l.target_type = 'role' AND tr.id = l.target_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.created_at DESC
            LIMIT {$limit}
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $logs;
    }

    public function distinctActivityActions(): array {
        $result = $this->conn->query('SELECT DISTINCT action FROM tracs_user_activity_logs ORDER BY action ASC');
        if (!$result) {
            return [];
        }
        return array_map(fn($row) => $row['action'], $result->fetch_all(MYSQLI_ASSOC));
    }

    public function getPreferences(int $userId): array {
        if (!tracs_table_exists($this->conn, 'tracs_user_preferences')) {
            return [];
        }
        try {
            $stmt = $this->conn->prepare('SELECT preference_key, preference_value FROM tracs_user_preferences WHERE user_id = ?');
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $prefs = [];
            while ($row = $result->fetch_assoc()) {
                $prefs[$row['preference_key']] = $row['preference_value'];
            }
            $stmt->close();
            return $prefs;
        } catch (Throwable) {
            return [];
        }
    }

    public function setPreference(int $userId, string $key, string $value): void {
        if (!tracs_ensure_user_preferences_table($this->conn)) {
            throw new RuntimeException('User preferences storage is unavailable. Please apply config/schema/preferences.sql.');
        }
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_user_preferences (user_id, preference_key, preference_value, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update preferences.');
        }
        $stmt->bind_param('iss', $userId, $key, $value);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update preferences.');
        }
        $stmt->close();
    }

    public function updateOwnProfile(int $userId, array $data): void {
        $stmt = $this->conn->prepare("
            UPDATE tracs_users
            SET name = ?, username = ?, email = ?, phone = ?, avatar_initials_color = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to update profile.');
        }
        $stmt->bind_param('sssssii', $data['name'], $data['username'], $data['email'], $data['phone'], $data['avatar_initials_color'], $userId, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to update profile.');
        }
        $stmt->close();
    }
}
