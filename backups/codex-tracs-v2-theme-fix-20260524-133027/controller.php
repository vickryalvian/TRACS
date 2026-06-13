<?php

require_once __DIR__ . '/model.php';

class UserManagementController {
    private mysqli $conn;
    private UserManagementModel $model;
    private int $actorId;

    public function __construct(mysqli $connection, int $actorId) {
        $this->conn = $connection;
        $this->model = new UserManagementModel($connection);
        $this->actorId = $actorId;
    }

    public function schemaReady(): bool {
        return $this->model->schemaReady();
    }

    public function stats(): array {
        return $this->model->getStats();
    }

    public function roles(): array {
        return $this->model->getRoles();
    }

    public function permissions(): array {
        return $this->model->getPermissions();
    }

    public function rolePermissionMap(): array {
        return $this->model->getRolePermissionMap();
    }

    public function users(array $filters): array {
        tracs_require_permission($this->conn, 'users.view');
        $actor = $this->actor();
        return $this->model->listUsers($filters, $actor ?? []);
    }

    public function divisions(): array {
        tracs_require_permission($this->conn, 'divisions.view');
        return $this->model->getDivisions();
    }

    public function divisionMembers(): array {
        tracs_require_permission($this->conn, 'divisions.view');
        return $this->model->getDivisionMemberMap();
    }

    public function activity(array $filters, int $limit = 100): array {
        tracs_require_permission($this->conn, 'users.view_activity');
        return $this->model->getActivityLogs($filters, $limit);
    }

    public function ownActivity(int $limit = 30): array {
        return $this->model->getActivityLogs([
            'target_user_id' => $this->actorId,
        ], $limit);
    }

    public function actionOptions(): array {
        return $this->model->distinctActivityActions();
    }

    public function userOptions(bool $includeInactive = false): array {
        return $this->model->getUserOptions($includeInactive);
    }

    public function mentorOptions(): array {
        return $this->model->getMentorOptions();
    }

    public function internUniversities(): array {
        return $this->model->getInternUniversities();
    }

    public function getUser(int $userId): ?array {
        return $this->model->getUserById($userId);
    }

    public function preferences(int $userId): array {
        return $this->model->getPreferences($userId);
    }

    private function actor(): ?array {
        return $this->model->getUserById($this->actorId);
    }

    private function cleanText(mixed $value, int $max = 255): string {
        $value = trim((string)($value ?? ''));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function cleanNullableText(mixed $value, int $max = 255): ?string {
        $value = $this->cleanText($value, $max);
        return $value === '' ? null : $value;
    }

    private function cleanLongText(mixed $value, int $max = 2000): string {
        $value = trim((string)($value ?? ''));
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function normalizeUsername(mixed $value): string {
        $username = strtolower($this->cleanText($value, 80));
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            throw new InvalidArgumentException('Username must be 3-80 characters and use only letters, numbers, dot, underscore, or hyphen.');
        }
        return $username;
    }

    private function normalizeEmail(mixed $value): string {
        $email = strtolower($this->cleanText($value, 255));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        return $email;
    }

    private function normalizeStatus(mixed $value): string {
        $status = (string)($value ?? 'active');
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            throw new InvalidArgumentException('Invalid account status.');
        }
        return $status;
    }

    private function normalizeDivisionId(mixed $value): ?int {
        $id = (int)($value ?? 0);
        return $id > 0 ? $id : null;
    }

    private function normalizeRoleId(mixed $value): int {
        $id = (int)($value ?? 0);
        if ($id <= 0 || !tracs_role_by_id($this->conn, $id)) {
            throw new InvalidArgumentException('A valid role is required.');
        }
        return $id;
    }

    private function roleSlugForId(int $roleId): string {
        $role = tracs_role_by_id($this->conn, $roleId);
        return (string)($role['slug'] ?? '');
    }

    private function normalizeEnum(mixed $value, array $allowed, string $default, string $label): string {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return $default;
        }
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }
        return $value;
    }

    private function normalizeDate(mixed $value, string $label): string {
        $value = trim((string)($value ?? ''));
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !strtotime($value)) {
            throw new InvalidArgumentException("{$label} is required.");
        }
        return $value;
    }

    private function normalizeInternProfile(array $input, bool $required): ?array {
        if (!$required) {
            return null;
        }
        if (!$this->model->internProfilesReady()) {
            throw new RuntimeException('Run the intern user management migration before saving intern profiles.');
        }

        $start = $this->normalizeDate($input['internship_start_date'] ?? '', 'Internship start date');
        $end = $this->normalizeDate($input['internship_end_date'] ?? '', 'Internship end date');
        if (strtotime($end) <= strtotime($start)) {
            throw new InvalidArgumentException('Internship end date must be after the start date.');
        }

        $mentorId = $this->normalizeDivisionId($input['mentor_user_id'] ?? null);
        if ($mentorId !== null) {
            $mentor = $this->model->getUserById($mentorId);
            if (!$mentor || !tracs_user_can_login($mentor) || !in_array((string)($mentor['role_slug'] ?? ''), ['super_admin', 'admin', 'supervisor'], true)) {
                throw new InvalidArgumentException('Mentor must be an active supervisor or admin user.');
            }
        }

        $university = $this->cleanText($input['university_name'] ?? '', 160);
        if ($university === '') {
            throw new InvalidArgumentException('University / campus name is required for interns.');
        }

        return [
            'university_name' => $university,
            'study_program' => $this->cleanNullableText($input['study_program'] ?? null, 160),
            'internship_start_date' => $start,
            'internship_end_date' => $end,
            'mentor_user_id' => $mentorId,
            'internship_status' => $this->normalizeEnum($input['internship_status'] ?? null, ['upcoming','active','ending_soon','completed','extended','terminated'], 'active', 'internship status'),
            'evaluation_status' => $this->normalizeEnum($input['evaluation_status'] ?? null, ['not_started','in_review','passed','needs_improvement','failed'], 'not_started', 'evaluation status'),
            'skill_level' => $this->normalizeEnum($input['skill_level'] ?? null, ['beginner','basic','intermediate','advanced'], 'beginner', 'skill level'),
            'allowed_task_scope' => $this->normalizeEnum($input['allowed_task_scope'] ?? null, ['', 'observation_only','simple_case_handling','checklist_task','reminder_followup','shift_report_draft','supervised_customer_response','internal_documentation','qa_assistance'], '', 'allowed task scope') ?: null,
            'special_notes' => $this->cleanNullableText($input['special_notes'] ?? null, 2000),
            'actor_id' => $this->actorId,
        ];
    }

    private function internProfileChanges(?array $before, ?array $after): array {
        return $this->changedFields($before ?? [], $after ?? [], [
            'university_name', 'study_program', 'internship_start_date', 'internship_end_date',
            'mentor_user_id', 'mentor_name', 'internship_status', 'evaluation_status',
            'skill_level', 'allowed_task_scope', 'special_notes',
        ]);
    }

    private function logInternProfileChanges(int $userId, ?array $before, ?array $after): void {
        if (!$before && $after) {
            tracs_log_user_event($this->conn, $this->actorId, 'intern_profile_created', 'user', $userId, null, $after);
            return;
        }
        $changes = $this->internProfileChanges($before, $after);
        if (!$changes) {
            return;
        }
        tracs_log_user_event($this->conn, $this->actorId, 'intern_profile_updated', 'user', $userId, $before, $changes);
        $actionMap = [
            'internship_start_date' => 'internship_date_changed',
            'internship_end_date' => 'internship_end_date_changed',
            'mentor_user_id' => 'intern_mentor_changed',
            'university_name' => 'intern_university_updated',
            'skill_level' => 'intern_skill_level_updated',
            'allowed_task_scope' => 'intern_task_scope_updated',
            'evaluation_status' => 'intern_evaluation_status_changed',
            'special_notes' => 'intern_special_notes_updated',
            'internship_status' => 'internship_status_changed',
        ];
        foreach ($actionMap as $field => $action) {
            if (isset($changes[$field])) {
                tracs_log_user_event($this->conn, $this->actorId, $action, 'user', $userId, [$field => $changes[$field]['before'] ?? null], [$field => $changes[$field]['after'] ?? null]);
            }
        }
        if (isset($changes['internship_end_date']) && strtotime((string)($changes['internship_end_date']['after'] ?? '')) > strtotime((string)($changes['internship_end_date']['before'] ?? ''))) {
            tracs_log_user_event($this->conn, $this->actorId, 'internship_extended', 'user', $userId, ['internship_end_date' => $changes['internship_end_date']['before']], ['internship_end_date' => $changes['internship_end_date']['after']]);
        }
    }

    private function assertCanManageUser(array $target): void {
        $actor = $this->actor();
        if (!$actor) {
            throw new RuntimeException('Session user could not be loaded.');
        }
        if (($actor['role_slug'] ?? '') === 'super_admin') {
            return;
        }
        if ((int)($target['hierarchy_level'] ?? 0) > (int)($actor['hierarchy_level'] ?? 0)) {
            throw new RuntimeException('You cannot manage a user with higher authority than your account.');
        }
        if (($actor['role_slug'] ?? '') === 'supervisor') {
            $actorDivision = (int)($actor['division_id'] ?? 0);
            $targetDivision = (int)($target['division_id'] ?? 0);
            if ($actorDivision <= 0 || $actorDivision !== $targetDivision) {
                throw new RuntimeException('Supervisors can only manage users in their own division.');
            }
        }
    }

    private function assertRoleAssignable(int $roleId): void {
        if (!tracs_can_assign_role($this->conn, $this->actorId, $roleId)) {
            throw new RuntimeException('You cannot assign a role higher than your own authority.');
        }
    }

    private function changedFields(array $before, array $after, array $fields): array {
        $changed = [];
        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ((string)$old !== (string)$new) {
                $changed[$field] = ['before' => $old, 'after' => $new];
            }
        }
        return $changed;
    }

    public function createUser(array $input): array {
        tracs_require_permission($this->conn, 'users.create');

        $roleId = $this->normalizeRoleId($input['role_id'] ?? null);
        $this->assertRoleAssignable($roleId);
        $roleSlug = $this->roleSlugForId($roleId);
        $internProfile = $this->normalizeInternProfile($input, $roleSlug === 'intern');

        $email = $this->normalizeEmail($input['email'] ?? '');
        $username = $this->normalizeUsername($input['username'] ?? '');
        if ($this->model->emailExists($email)) {
            throw new InvalidArgumentException('Email is already used by another account.');
        }
        if ($this->model->usernameExists($username)) {
            throw new InvalidArgumentException('Username is already used by another account.');
        }

        $password = (string)($input['password'] ?? '');
        $generated = false;
        if ($password === '') {
            $password = tracs_generate_temporary_password();
            $generated = true;
        }
        $errors = tracs_password_policy_errors($password);
        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $data = [
            'name' => $this->cleanText($input['name'] ?? '', 100),
            'username' => $username,
            'email' => $email,
            'phone' => $this->cleanNullableText($input['phone'] ?? null, 50),
            'position' => $this->cleanNullableText($input['position'] ?? null, 120),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $roleId,
            'division_id' => $this->normalizeDivisionId($input['division_id'] ?? null),
            'status' => $this->normalizeStatus($input['status'] ?? 'active'),
            'shift_preference' => $this->cleanNullableText($input['shift_preference'] ?? null, 60),
            'avatar_initials_color' => $this->cleanNullableText($input['avatar_initials_color'] ?? null, 20),
            'actor_id' => $this->actorId,
        ];
        if ($data['name'] === '') {
            throw new InvalidArgumentException('Full name is required.');
        }
        $actor = $this->actor();
        if (($actor['role_slug'] ?? '') === 'supervisor' && (int)($data['division_id'] ?? 0) !== (int)($actor['division_id'] ?? 0)) {
            throw new RuntimeException('Supervisors can only create users in their own division.');
        }

        $id = $this->model->createUser($data);
        $after = $this->model->getUserById($id);
        tracs_log_user_event($this->conn, $this->actorId, 'create_user', 'user', $id, null, $after);
        if ($internProfile) {
            $profileChange = $this->model->upsertInternProfile($id, $internProfile);
            $this->logInternProfileChanges($id, $profileChange['before'], $profileChange['after']);
            tracs_log_user_event($this->conn, $this->actorId, 'intern_user_created', 'user', $id, null, ['user' => $this->model->getUserById($id), 'intern_profile' => $profileChange['after']]);
        }

        return [
            'message' => 'User created successfully.',
            'user_id' => $id,
            'temporary_password' => $generated ? $password : null,
            'temporary_password_for' => $after['display_name'] ?? $data['email'],
        ];
    }

    public function updateUser(int $userId, array $input): array {
        tracs_require_permission($this->conn, 'users.update');
        $before = $this->model->getUserById($userId);
        if (!$before) {
            throw new RuntimeException('User not found.');
        }
        $this->assertCanManageUser($before);

        $roleId = $this->normalizeRoleId($input['role_id'] ?? $before['role_id'] ?? null);
        $nextRoleSlug = $this->roleSlugForId($roleId);
        $divisionId = $this->normalizeDivisionId($input['division_id'] ?? null);
        $status = $this->normalizeStatus($input['status'] ?? ($before['status'] ?? 'active'));
        $internProfile = $this->normalizeInternProfile($input, $nextRoleSlug === 'intern');

        if ((int)$roleId !== (int)($before['role_id'] ?? 0)) {
            tracs_require_permission($this->conn, 'roles.update');
            $this->assertRoleAssignable($roleId);
            if (tracs_is_last_active_super_admin($this->conn, $userId)) {
                throw new RuntimeException('The last active Super Admin cannot be demoted.');
            }
        }
        if ((int)($divisionId ?? 0) !== (int)($before['division_id'] ?? 0)) {
            tracs_require_permission($this->conn, 'divisions.manage_members');
        }
        if ($status !== (string)($before['status'] ?? 'active')) {
            if ($status === 'active') {
                tracs_require_permission($this->conn, 'users.activate');
            } else {
                tracs_require_permission($this->conn, 'users.suspend');
            }
            if (tracs_is_last_active_super_admin($this->conn, $userId)) {
                throw new RuntimeException('The last active Super Admin cannot be suspended or deactivated.');
            }
        }
        $reason = $this->cleanLongText($input['reason'] ?? '', 500);
        if ($status !== (string)($before['status'] ?? 'active') && $status !== 'active' && $reason === '') {
            throw new InvalidArgumentException('A reason is required for suspension or deactivation.');
        }

        $email = $this->normalizeEmail($input['email'] ?? '');
        $username = $this->normalizeUsername($input['username'] ?? '');
        if ($this->model->emailExists($email, $userId)) {
            throw new InvalidArgumentException('Email is already used by another account.');
        }
        if ($this->model->usernameExists($username, $userId)) {
            throw new InvalidArgumentException('Username is already used by another account.');
        }

        $data = [
            'name' => $this->cleanText($input['name'] ?? '', 100),
            'username' => $username,
            'email' => $email,
            'phone' => $this->cleanNullableText($input['phone'] ?? null, 50),
            'position' => $this->cleanNullableText($input['position'] ?? null, 120),
            'role_id' => $roleId,
            'division_id' => $divisionId,
            'status' => $status,
            'shift_preference' => $this->cleanNullableText($input['shift_preference'] ?? null, 60),
            'avatar_initials_color' => $this->cleanNullableText($input['avatar_initials_color'] ?? null, 20),
            'actor_id' => $this->actorId,
        ];
        if ($data['name'] === '') {
            throw new InvalidArgumentException('Full name is required.');
        }

        $this->model->updateUser($userId, $data);
        if ($internProfile) {
            $profileChange = $this->model->upsertInternProfile($userId, $internProfile);
            $this->logInternProfileChanges($userId, $profileChange['before'], $profileChange['after']);
        } elseif (($before['role_slug'] ?? '') === 'intern' && $nextRoleSlug !== 'intern') {
            $existingProfile = $this->model->getInternProfile($userId);
            if ($existingProfile && !in_array((string)($existingProfile['internship_status'] ?? ''), ['completed', 'terminated'], true)) {
                $existingProfile['internship_status'] = 'completed';
                $existingProfile['actor_id'] = $this->actorId;
                $profileChange = $this->model->upsertInternProfile($userId, $existingProfile);
                $this->logInternProfileChanges($userId, $profileChange['before'], $profileChange['after']);
            }
        }
        $after = $this->model->getUserById($userId);
        $changes = $this->changedFields($before, $after ?? [], [
            'name', 'username', 'email', 'phone', 'position', 'role_id', 'role_name',
            'division_id', 'division_name', 'status', 'shift_preference', 'avatar_initials_color',
        ]);
        tracs_log_user_event($this->conn, $this->actorId, 'update_user', 'user', $userId, $before, $changes, $reason);

        return ['message' => 'User updated successfully.'];
    }

    public function setUserStatus(int $userId, string $status, string $reason): array {
        $status = $this->normalizeStatus($status);
        if ($status === 'active') {
            tracs_require_permission($this->conn, 'users.activate');
        } else {
            tracs_require_permission($this->conn, 'users.suspend');
        }
        $before = $this->model->getUserById($userId);
        if (!$before) {
            throw new RuntimeException('User not found.');
        }
        $this->assertCanManageUser($before);
        if ($userId === $this->actorId && $status !== 'active') {
            throw new RuntimeException('You cannot suspend or deactivate your own account.');
        }
        if (tracs_is_last_active_super_admin($this->conn, $userId) && $status !== 'active') {
            throw new RuntimeException('The last active Super Admin cannot be suspended or deactivated.');
        }
        $reason = $this->cleanLongText($reason, 500);
        if ($status !== 'active' && $reason === '') {
            throw new InvalidArgumentException('A reason is required for suspension or deactivation.');
        }

        $this->model->updateUserStatus($userId, $status, $this->actorId);
        $after = $this->model->getUserById($userId);
        tracs_log_user_event($this->conn, $this->actorId, $status === 'active' ? 'activate_user' : 'suspend_user', 'user', $userId, $before, $after, $reason);

        return ['message' => $status === 'active' ? 'User activated successfully.' : 'User access updated successfully.'];
    }

    public function resetPassword(int $userId, string $reason = ''): array {
        tracs_require_permission($this->conn, 'users.reset_password');
        $target = $this->model->getUserById($userId);
        if (!$target) {
            throw new RuntimeException('User not found.');
        }
        $this->assertCanManageUser($target);
        $password = tracs_generate_temporary_password();
        $this->model->updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT), $this->actorId);
        tracs_log_user_event($this->conn, $this->actorId, 'reset_password', 'user', $userId, ['last_password_change_at' => $target['last_password_change_at'] ?? null], ['temporary_password_generated' => true], $this->cleanLongText($reason, 500));

        return [
            'message' => 'Temporary password generated. It is visible once below.',
            'temporary_password' => $password,
            'temporary_password_for' => $target['display_name'] ?? $target['email'],
        ];
    }

    public function resetTwoFactor(int $userId, string $reason = ''): array {
        $actor = $this->actor();
        if (($actor['role_slug'] ?? '') !== 'super_admin') {
            tracs_auth_log_event($this->conn, 'permission_denied', 'blocked', '', $this->actorId, 'two_factor_reset_requires_super_admin');
            throw new RuntimeException('Only Super Admin can reset two-factor authentication.');
        }
        $target = $this->model->getUserById($userId);
        if (!$target) {
            throw new RuntimeException('User not found.');
        }
        $this->assertCanManageUser($target);
        $this->model->resetTwoFactor($userId);
        tracs_log_user_event(
            $this->conn,
            $this->actorId,
            'reset_two_factor',
            'user',
            $userId,
            ['two_factor_enabled' => (int)($target['two_factor_enabled'] ?? 0), 'two_factor_confirmed_at' => $target['two_factor_confirmed_at'] ?? null],
            ['two_factor_reset_required' => true],
            $this->cleanLongText($reason, 500)
        );
        tracs_auth_log_event($this->conn, 'two_factor_reset', 'success', (string)($target['email'] ?? ''), $userId, 'reset_by_super_admin');
        return ['message' => 'Two-factor authentication reset. The user must set it up again on next login.'];
    }

    public function createDivision(array $input): array {
        tracs_require_permission($this->conn, 'divisions.create');
        $code = strtoupper($this->cleanText($input['code'] ?? '', 40));
        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            throw new InvalidArgumentException('Division code must use 2-40 letters, numbers, underscores, or hyphens.');
        }
        if ($this->model->divisionCodeExists($code)) {
            throw new InvalidArgumentException('Division code is already used.');
        }
        $data = [
            'name' => $this->cleanText($input['name'] ?? '', 120),
            'code' => $code,
            'description' => $this->cleanLongText($input['description'] ?? '', 1500),
            'supervisor_id' => $this->normalizeDivisionId($input['supervisor_id'] ?? null),
            'actor_id' => $this->actorId,
        ];
        if ($data['name'] === '') {
            throw new InvalidArgumentException('Division name is required.');
        }
        $id = $this->model->createDivision($data);
        $after = $this->model->getDivisionById($id);
        tracs_log_user_event($this->conn, $this->actorId, 'create_division', 'division', $id, null, $after);
        return ['message' => 'Division created successfully.'];
    }

    public function updateDivision(int $divisionId, array $input): array {
        tracs_require_permission($this->conn, 'divisions.update');
        $before = $this->model->getDivisionById($divisionId);
        if (!$before) {
            throw new RuntimeException('Division not found.');
        }
        $code = strtoupper($this->cleanText($input['code'] ?? '', 40));
        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            throw new InvalidArgumentException('Division code must use 2-40 letters, numbers, underscores, or hyphens.');
        }
        if ($this->model->divisionCodeExists($code, $divisionId)) {
            throw new InvalidArgumentException('Division code is already used.');
        }
        $status = (string)($input['status'] ?? 'active');
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new InvalidArgumentException('Invalid division status.');
        }
        if ($status === 'archived' && (string)($before['status'] ?? '') !== 'archived') {
            tracs_require_permission($this->conn, 'divisions.archive');
        }

        $data = [
            'name' => $this->cleanText($input['name'] ?? '', 120),
            'code' => $code,
            'description' => $this->cleanLongText($input['description'] ?? '', 1500),
            'supervisor_id' => $this->normalizeDivisionId($input['supervisor_id'] ?? null),
            'status' => $status,
            'actor_id' => $this->actorId,
        ];
        if ($data['name'] === '') {
            throw new InvalidArgumentException('Division name is required.');
        }
        $this->model->updateDivision($divisionId, $data);
        $after = $this->model->getDivisionById($divisionId);
        tracs_log_user_event($this->conn, $this->actorId, 'update_division', 'division', $divisionId, $before, $after);
        return ['message' => 'Division updated successfully.'];
    }

    public function archiveDivision(int $divisionId, bool $confirmed, string $reason): array {
        tracs_require_permission($this->conn, 'divisions.archive');
        $before = $this->model->getDivisionById($divisionId);
        if (!$before) {
            throw new RuntimeException('Division not found.');
        }
        $activeUsers = $this->model->activeUsersInDivision($divisionId);
        $reason = $this->cleanLongText($reason, 500);
        if ($activeUsers > 0 && (!$confirmed || $reason === '')) {
            throw new RuntimeException('This division still has active users. Confirm archive and provide a reason.');
        }
        $this->model->archiveDivision($divisionId, $this->actorId);
        $after = $this->model->getDivisionById($divisionId);
        tracs_log_user_event($this->conn, $this->actorId, 'archive_division', 'division', $divisionId, $before, $after, $reason);
        return ['message' => 'Division archived successfully.'];
    }

    public function updateRolePermissions(array $posted): array {
        tracs_require_permission($this->conn, 'roles.manage_permissions');
        $actor = $this->actor();
        $actorPermissions = tracs_user_permissions($this->conn, $this->actorId);
        $isSuper = ($actor['role_slug'] ?? '') === 'super_admin';
        $permissionIdsByKey = $this->model->getPermissionIdsByKeys(tracs_all_permission_keys());
        $roles = $this->model->getRoles();
        $beforeMap = $this->model->getRolePermissionMap();
        $postedMatrix = is_array($posted['role_permissions'] ?? null) ? $posted['role_permissions'] : [];

        foreach ($roles as $role) {
            $roleId = (int)$role['id'];
            if (($role['slug'] ?? '') === 'super_admin') {
                continue;
            }
            if (!$isSuper && (int)$role['hierarchy_level'] > (int)($actor['hierarchy_level'] ?? 0)) {
                continue;
            }
            if (!$isSuper && $roleId === (int)($actor['role_id'] ?? 0) && array_key_exists($roleId, $postedMatrix)) {
                throw new RuntimeException('You cannot change permissions for your own role.');
            }
            $keys = array_values(array_unique(array_map('strval', $postedMatrix[$roleId] ?? [])));
            $before = $beforeMap[$roleId]['keys'] ?? [];
            if (!$isSuper) {
                $blocked = array_values(array_diff($keys, $actorPermissions));
                if ($blocked) {
                    throw new RuntimeException('You cannot grant permissions that your account does not have.');
                }
                $keys = array_values(array_unique(array_merge($keys, array_diff($before, $actorPermissions))));
            }
            $permissionIds = [];
            foreach ($keys as $key) {
                if (isset($permissionIdsByKey[$key])) {
                    $permissionIds[] = $permissionIdsByKey[$key];
                }
            }
            $this->model->replaceRolePermissions($roleId, $permissionIds);
            tracs_log_user_event($this->conn, $this->actorId, 'change_permissions', 'role', $roleId, ['role' => $role['slug'], 'permissions' => $before], ['role' => $role['slug'], 'permissions' => $keys]);
        }

        return ['message' => 'Role permissions updated successfully.'];
    }

    public function updateSingleRolePermissions(int $roleId, array $posted): array {
        tracs_require_permission($this->conn, 'roles.manage_permissions');

        $actor = $this->actor();
        $role = tracs_role_by_id($this->conn, $roleId);
        if (!$actor || !$role) {
            throw new RuntimeException('Role not found.');
        }
        if (($role['slug'] ?? '') === 'super_admin') {
            throw new RuntimeException('Super Admin permissions are locked.');
        }

        $isSuper = ($actor['role_slug'] ?? '') === 'super_admin';
        if (!$isSuper && (int)($role['hierarchy_level'] ?? 0) > (int)($actor['hierarchy_level'] ?? 0)) {
            throw new RuntimeException('You cannot update permissions for a higher role.');
        }
        if (!$isSuper && $roleId === (int)($actor['role_id'] ?? 0)) {
            throw new RuntimeException('You cannot change permissions for your own role.');
        }

        $actorPermissions = tracs_user_permissions($this->conn, $this->actorId);
        $beforeMap = $this->model->getRolePermissionMap();
        $before = $beforeMap[$roleId]['keys'] ?? [];
        $keys = array_values(array_unique(array_map('strval', is_array($posted['permissions'] ?? null) ? $posted['permissions'] : [])));
        $validIds = $this->model->getPermissionIdsByKeys(tracs_all_permission_keys());

        if (!$isSuper) {
            $blocked = array_values(array_diff($keys, $actorPermissions));
            if ($blocked) {
                throw new RuntimeException('You cannot grant permissions that your account does not have.');
            }
            $keys = array_values(array_unique(array_merge($keys, array_diff($before, $actorPermissions))));
        }

        $permissionIds = [];
        foreach ($keys as $key) {
            if (isset($validIds[$key])) {
                $permissionIds[] = $validIds[$key];
            }
        }

        $this->model->replaceRolePermissions($roleId, $permissionIds);
        tracs_log_user_event($this->conn, $this->actorId, 'change_permissions', 'role', $roleId, ['role' => $role['slug'], 'permissions' => $before], ['role' => $role['slug'], 'permissions' => $keys]);

        return ['message' => 'Role permissions updated successfully.'];
    }

    public function updateOwnProfile(array $input): array {
        tracs_require_permission($this->conn, 'profile.update_own');
        $before = $this->model->getUserById($this->actorId);
        if (!$before) {
            throw new RuntimeException('Profile not found.');
        }
        $email = $this->normalizeEmail($input['email'] ?? '');
        $username = $this->normalizeUsername($input['username'] ?? '');
        if ($this->model->emailExists($email, $this->actorId)) {
            throw new InvalidArgumentException('Email is already used by another account.');
        }
        if ($this->model->usernameExists($username, $this->actorId)) {
            throw new InvalidArgumentException('Username is already used by another account.');
        }
        $data = [
            'name' => $this->cleanText($input['name'] ?? '', 100),
            'username' => $username,
            'email' => $email,
            'phone' => $this->cleanNullableText($input['phone'] ?? null, 50),
            'avatar_initials_color' => $this->cleanNullableText($input['avatar_initials_color'] ?? null, 20),
        ];
        if ($data['name'] === '') {
            throw new InvalidArgumentException('Full name is required.');
        }
        $this->model->updateOwnProfile($this->actorId, $data);
        $after = $this->model->getUserById($this->actorId);
        if ($after) {
            tracs_sync_session_user($after);
        }
        tracs_log_user_event($this->conn, $this->actorId, 'user_updated_own_profile', 'user', $this->actorId, $before, $this->changedFields($before, $after ?? [], ['name', 'username', 'email', 'phone', 'avatar_initials_color']));
        return ['message' => 'Profile updated successfully.'];
    }

    public function updateProfilePicture(int $userId, ?string $avatarPath): array {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user.');
        }
        $before = $this->model->getUserById($userId);
        if (!$before) {
            throw new RuntimeException('User not found.');
        }

        if ($userId === $this->actorId) {
            tracs_require_permission($this->conn, 'profile.update_own');
        } else {
            tracs_require_permission($this->conn, 'users.update');
            $this->assertCanManageUser($before);
        }

        $this->model->updateAvatarPath($userId, $avatarPath, $this->actorId);
        $after = $this->model->getUserById($userId);
        if ($userId === $this->actorId && $after) {
            tracs_sync_session_user($after);
        }
        tracs_log_user_event($this->conn, $this->actorId, $avatarPath ? 'user_avatar_updated' : 'user_avatar_removed', 'user', $userId, ['avatar_path' => $before['avatar_path'] ?? null], ['avatar_path' => $avatarPath]);

        return [
            'message' => $avatarPath ? 'Profile picture updated successfully.' : 'Profile picture removed.',
            'user' => $after,
        ];
    }

    public function changeOwnPassword(array $input): array {
        tracs_require_permission($this->conn, 'profile.change_password_own');
        $user = $this->model->getUserById($this->actorId);
        if (!$user) {
            throw new RuntimeException('Profile not found.');
        }
        $current = (string)($input['current_password'] ?? '');
        $new = (string)($input['new_password'] ?? '');
        $confirm = (string)($input['confirm_password'] ?? '');
        if (!password_verify($current, (string)($user['password'] ?? ''))) {
            throw new InvalidArgumentException('Current password is incorrect.');
        }
        if ($new !== $confirm) {
            throw new InvalidArgumentException('New password and confirmation do not match.');
        }
        $errors = tracs_password_policy_errors($new, (string)$user['password']);
        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        $this->model->updatePasswordHash($this->actorId, password_hash($new, PASSWORD_DEFAULT), $this->actorId);
        tracs_log_user_event($this->conn, $this->actorId, 'user_changed_own_password', 'user', $this->actorId, ['last_password_change_at' => $user['last_password_change_at'] ?? null], ['password_changed' => true]);
        return ['message' => 'Password changed successfully.'];
    }

    public function updateOwnPreferences(array $input): array {
        tracs_require_permission($this->conn, 'profile.update_preferences_own');
        $before = $this->model->getPreferences($this->actorId);
        $theme = (string)($input['theme_preference'] ?? 'auto');
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            $theme = 'auto';
        }
        $visualTheme = tracs_normalize_visual_theme($input['visual_theme'] ?? 'default');
        $notifications = (string)($input['notification_preference'] ?? 'in_app');
        if (!in_array($notifications, ['in_app', 'email', 'both', 'muted'], true)) {
            $notifications = 'in_app';
        }
        $landing = (string)($input['default_landing_page'] ?? 'index.php');
        $allowedLanding = ['index.php', 'cases.php', 'reminders.php', 'checklist.php', 'shift-reports.php', 'mom.php', 'activity.php'];
        if (!in_array($landing, $allowedLanding, true)) {
            $landing = 'index.php';
        }

        $this->model->setPreference($this->actorId, 'theme_preference', $theme);
        $this->model->setPreference($this->actorId, 'visual_theme', $visualTheme);
        $this->model->setPreference($this->actorId, 'notification_preference', $notifications);
        $this->model->setPreference($this->actorId, 'default_landing_page', $landing);
        $after = $this->model->getPreferences($this->actorId);
        tracs_log_user_event($this->conn, $this->actorId, 'user_updated_own_preference', 'user', $this->actorId, $before, $after);
        return ['message' => 'Preferences updated successfully.'];
    }
}
