<?php
require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/../core/security/auth_hardening.php';
require_once __DIR__ . '/../core/build_signature.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
require_once __DIR__ . '/../modules/user-management/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$UM = new UserManagementController($conn, $uid);
$schema_ready = $UM->schemaReady();

$legacy_bootstrap_access = !$schema_ready && ($uid === 1 || strtolower($user_email) === 'admin@tracs.local');
if (!$legacy_bootstrap_access && !tracs_user_can($conn, 'users.view') && !tracs_user_can($conn, 'divisions.view') && !tracs_user_can($conn, 'roles.view')) {
    tracs_abort_404();
}

function um_flash(string $type, string $message): void {
    $_SESSION['tracs_flash'] = ['type' => $type, 'message' => $message];
}

function um_redirect(string $tab = 'users'): never {
    header('Location: /user-management.php?tab=' . urlencode($tab));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$schema_ready) {
        um_flash('error', 'Run the User Management migration before saving changes.');
        um_redirect('users');
    }

    $action = (string)($_POST['action'] ?? '');
    try {
        $result = match ($action) {
            'create_user' => $UM->createUser($_POST),
            'update_user' => $UM->updateUser((int)($_POST['user_id'] ?? 0), $_POST),
            'set_user_status' => $UM->setUserStatus((int)($_POST['user_id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['reason'] ?? '')),
            'reset_password' => $UM->resetPassword((int)($_POST['user_id'] ?? 0), (string)($_POST['reason'] ?? '')),
            'reset_two_factor' => $UM->resetTwoFactor((int)($_POST['user_id'] ?? 0), (string)($_POST['reason'] ?? '')),
            'create_division' => $UM->createDivision($_POST),
            'update_division' => $UM->updateDivision((int)($_POST['division_id'] ?? 0), $_POST),
            'archive_division' => $UM->archiveDivision((int)($_POST['division_id'] ?? 0), !empty($_POST['confirm_archive']), (string)($_POST['reason'] ?? '')),
            'update_permissions' => $UM->updateRolePermissions($_POST),
            'update_role_permissions_one' => $UM->updateSingleRolePermissions((int)($_POST['role_id'] ?? 0), $_POST),
            default => throw new InvalidArgumentException('Unknown action.'),
        };
        if (!empty($result['temporary_password'])) {
            $_SESSION['um_temp_password'] = [
                'password' => $result['temporary_password'],
                'for' => $result['temporary_password_for'] ?? 'User',
            ];
        }
        um_flash('success', $result['message'] ?? 'Saved successfully.');
        $tab = ($action === 'update_permissions') ? 'roles' : 'users';
        um_redirect($tab);
    } catch (Throwable $e) {
        um_flash('error', $e->getMessage());
        $tab = ($action === 'update_permissions') ? 'roles' : 'users';
        um_redirect($tab);
    }
}

$flash = $_SESSION['tracs_flash'] ?? null;
unset($_SESSION['tracs_flash']);
$temp_password = $_SESSION['um_temp_password'] ?? null;
unset($_SESSION['um_temp_password']);

$can_manage_settings = $schema_ready && tracs_user_can($conn, 'settings.manage');
$security_roles = ['super_admin', 'admin', 'supervisor'];
$can_view_auth_security = $schema_ready && in_array((string)($_SESSION['user_role_slug'] ?? ''), $security_roles, true);
$allowed_tabs = ['users', 'roles', 'activity'];
if ($can_manage_settings) {
    $allowed_tabs[] = 'system';
}
if ($can_view_auth_security) {
    $allowed_tabs[] = 'security';
}
$requested_tab = (string)($_GET['tab'] ?? 'users');
$tab = in_array($requested_tab, $allowed_tabs, true) ? $requested_tab : 'users';

$stats = $schema_ready ? $UM->stats() : [];
$roles = $schema_ready ? $UM->roles() : [];
$hidden_role_slugs = ['viewer', 'auditor'];
$visible_roles = array_values(array_filter($roles, function (array $role) use ($hidden_role_slugs): bool {
    $slug = strtolower((string)($role['slug'] ?? ''));
    $name = strtolower(trim((string)($role['name'] ?? '')));
    return !in_array($slug, $hidden_role_slugs, true) && $name !== 'viewer / auditor';
}));
$permissions = $schema_ready ? $UM->permissions() : [];
$role_permission_map = $schema_ready ? $UM->rolePermissionMap() : [];
$divisions = $schema_ready ? $UM->divisions() : [];
$division_members = $schema_ready ? $UM->divisionMembers() : [];
$user_options = $schema_ready ? $UM->userOptions(true) : [];
$mentor_options = $schema_ready ? $UM->mentorOptions() : [];
$users = $schema_ready && tracs_user_can($conn, 'users.view') ? $UM->users($_GET) : [];
$activity_filters = [
    'actor_user_id' => $_GET['actor_user_id'] ?? '',
    'target_user_id' => $_GET['target_user_id'] ?? '',
    'action' => $_GET['activity_action'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'q' => $_GET['activity_q'] ?? '',
];
$activity_logs = $schema_ready && $tab === 'activity' ? $UM->activity($activity_filters, (int)($_GET['limit'] ?? 100)) : [];
$activity_actions = $schema_ready ? $UM->actionOptions() : [];
$auth_events = $schema_ready && $tab === 'security' && $can_view_auth_security ? tracs_auth_recent_events($conn, 50) : [];
$auth_locks = $schema_ready && $tab === 'security' && $can_view_auth_security ? tracs_auth_locked_attempts($conn, 50) : [];

$can_create_user = tracs_user_can($conn, 'users.create');
$can_update_user = tracs_user_can($conn, 'users.update');
$can_suspend_user = tracs_user_can($conn, 'users.suspend');
$can_activate_user = tracs_user_can($conn, 'users.activate');
$can_reset_password = tracs_user_can($conn, 'users.reset_password');
$can_create_division = tracs_user_can($conn, 'divisions.create');
$can_update_division = tracs_user_can($conn, 'divisions.update');
$can_archive_division = tracs_user_can($conn, 'divisions.archive');
$can_manage_permissions = tracs_user_can($conn, 'roles.manage_permissions');
$actor = tracs_get_user_by_id($conn, $uid) ?? [];
$actor_permissions = tracs_user_permissions($conn, $uid);
$is_super_admin = ($actor['role_slug'] ?? '') === 'super_admin';
$can_reset_2fa = $schema_ready && $is_super_admin && tracs_two_factor_schema_ready($conn);
$build_signature = tracs_build_public_payload();

$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'User Management';
$active_page = 'user-management';

function um_dt(mixed $value, string $format = 'd M Y H:i'): string {
    return ($value && strtotime((string)$value)) ? date($format, strtotime((string)$value)) : '—';
}

function um_badge(string $label, string $class): string {
    return '<span class="badge ' . esc($class) . '"><span class="badge-dot"></span>' . esc($label) . '</span>';
}

function um_user_payload(array $user): string {
    $payload = [
        'id' => (int)$user['id'],
        'name' => $user['display_name'] ?? $user['name'] ?? '',
        'username' => $user['username'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'position' => $user['position'] ?? '',
        'role_id' => (int)($user['role_id'] ?? 0),
        'role_name' => $user['role_name'] ?? '',
        'role_slug' => $user['role_slug'] ?? '',
        'division_id' => (int)($user['division_id'] ?? 0),
        'division_name' => $user['division_name'] ?: 'No Division',
        'status' => $user['status'] ?? 'active',
        'shift_preference' => $user['shift_preference'] ?? '',
        'avatar_path' => $user['avatar_path'] ?? '',
        'avatar_url' => tracs_user_avatar_url($user),
        'avatar_initials_color' => $user['avatar_initials_color'] ?? '',
        'two_factor_enabled' => (int)($user['two_factor_enabled'] ?? 0),
        'two_factor_confirmed_at' => um_dt($user['two_factor_confirmed_at'] ?? null),
        'two_factor_reset_required' => (int)($user['two_factor_reset_required'] ?? 1),
        'two_factor_failed_attempts' => (int)($user['two_factor_failed_attempts'] ?? 0),
        'two_factor_locked_until' => um_dt($user['two_factor_locked_until'] ?? null),
        'two_factor_last_verified_at' => um_dt($user['two_factor_last_verified_at'] ?? null),
        'created_at' => um_dt($user['created_at'] ?? null),
        'updated_at' => um_dt($user['updated_at'] ?? null),
        'last_login_at' => um_dt($user['last_login_at'] ?? null),
        'last_activity_at' => um_dt($user['last_activity_at'] ?? null),
        'last_password_change_at' => um_dt($user['last_password_change_at'] ?? null),
        'role_permissions' => $user['role_permissions'] ?? [],
        'is_intern' => !empty($user['is_intern']),
        'university_name' => $user['university_name'] ?? '',
        'study_program' => $user['study_program'] ?? '',
        'internship_start_date' => $user['internship_start_date'] ?? '',
        'internship_end_date' => $user['internship_end_date'] ?? '',
        'mentor_user_id' => (int)($user['mentor_user_id'] ?? 0),
        'mentor_name' => $user['mentor_name'] ?? '',
        'internship_status' => $user['internship_status'] ?? '',
        'evaluation_status' => $user['evaluation_status'] ?? '',
        'skill_level' => $user['skill_level'] ?? '',
        'allowed_task_scope' => $user['allowed_task_scope'] ?? '',
        'special_notes' => $user['special_notes'] ?? '',
        'internship_days_remaining' => $user['internship_days_remaining'] ?? null,
        'internship_monitor_state' => $user['internship_monitor_state'] ?? '',
        'created_summary' => $user['created_summary'] ?? [],
    ];
    return esc(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function um_division_payload(array $division): string {
    $payload = [
        'id' => (int)$division['id'],
        'name' => $division['name'] ?? '',
        'code' => $division['code'] ?? '',
        'description' => $division['description'] ?? '',
        'supervisor_id' => (int)($division['supervisor_id'] ?? 0),
        'status' => $division['status'] ?? 'active',
    ];
    return esc(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$permission_catalog_payload = [];
foreach ($permissions as $category => $items) {
    foreach ($items as $permission) {
        $permission_catalog_payload[$category][] = [
            'key' => (string)$permission['permission_key'],
            'description' => (string)($permission['description'] ?? ''),
        ];
    }
}
$role_permission_payload = [];
foreach ($roles as $role) {
    $roleId = (int)$role['id'];
    $role_permission_payload[$roleId] = [
        'id' => $roleId,
        'name' => (string)$role['name'],
        'slug' => (string)$role['slug'],
        'hierarchy_level' => (int)$role['hierarchy_level'],
        'keys' => array_values($role_permission_map[$roleId]['keys'] ?? []),
    ];
}
foreach ($users as &$umUserForPayload) {
    $umRoleId = (int)($umUserForPayload['role_id'] ?? 0);
    $umUserForPayload['role_permissions'] = $role_permission_payload[$umRoleId]['keys'] ?? [];
}
unset($umUserForPayload);

$users_by_division = [];
$unassigned_users = [];
foreach ($users as $user) {
    $divisionId = (int)($user['division_id'] ?? 0);
    if ($divisionId > 0) {
        $users_by_division[$divisionId][] = $user;
    } else {
        $unassigned_users[] = $user;
    }
}
$active_filter_division = $_GET['division_id'] ?? '';
$dashboard_divisions = array_values(array_filter($divisions, function (array $division) use ($active_filter_division): bool {
    return $active_filter_division === '' || (string)$division['id'] === (string)$active_filter_division;
}));
$has_user_filters = trim((string)($_GET['q'] ?? '')) !== ''
    || (string)($_GET['division_id'] ?? '') !== ''
    || (string)($_GET['role_id'] ?? '') !== ''
    || (string)($_GET['status'] ?? '') !== ''
    || (string)($_GET['last_active'] ?? '') !== '';
$role_count = count($visible_roles);
$permission_count = array_sum(array_map(fn($group) => count($group), $permissions));
$supervisor_count = count(array_filter($users, fn($user) => ($user['role_slug'] ?? '') === 'supervisor'));
$intern_preview_count = (int)($stats['active_interns'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<main class="main"><div class="main-inner user-management-page">

<div class="topbar um-topbar">
  <div class="topbar-left">
    <div class="page-title">User Management</div>
    <div class="page-sub">Manage agents, divisions, roles, and access control.</div>
  </div>
  <div class="topbar-right">
    <?php if($schema_ready && $can_create_user): ?>
      <button type="button" class="btn btn-primary" onclick="umCreateUser()"><i data-lucide="user-plus" class="icon-sm"></i>Add User</button>
    <?php endif; ?>
    <?php if($schema_ready && $can_create_division): ?>
      <button type="button" class="btn btn-ghost" onclick="umCreateDivision()"><i data-lucide="network" class="icon-sm"></i>Add Division</button>
    <?php endif; ?>
  </div>
</div>

<?php if(!$schema_ready): ?>
  <div class="panel um-migration-panel">
    <div class="panel-head"><span class="panel-title">Migration Required</span></div>
    <div class="um-empty-state">
      <div class="empty-ic"><i data-lucide="database"></i></div>
      <div class="empty-t">User Management schema is not installed yet</div>
      <div class="empty-sub">Run <code>config/migrations/2026_05_17_user_management.sql</code>, then reload this page.</div>
    </div>
  </div>
<?php else: ?>

<?php if($temp_password): ?>
  <div class="panel um-once-panel">
    <div class="um-once-copy">
      <div>
        <div class="um-once-title"><i data-lucide="key-round" class="icon-sm"></i> Temporary password for <?=esc($temp_password['for'])?></div>
        <div class="page-sub">Shown once. Share it securely and ask the user to change it after login.</div>
      </div>
      <div class="um-copy-row">
        <input type="text" class="form-input" id="umTempPassword" value="<?=esc($temp_password['password'])?>" readonly>
        <button type="button" class="btn btn-primary" onclick="umCopy('umTempPassword')"><i data-lucide="copy" class="icon-sm"></i>Copy</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<section class="panel um-control-overview" aria-label="User management overview">
  <div class="um-overview-section">
    <div class="um-overview-title"><i data-lucide="users" class="icon-sm"></i>Account Overview</div>
    <div class="um-overview-line"><span>Total users</span><strong><?=esc($stats['total_users'] ?? 0)?></strong></div>
    <div class="um-overview-line"><span>Active accounts</span><strong><?=esc($stats['active_users'] ?? 0)?></strong></div>
    <div class="um-overview-line"><span>Unassigned users</span><strong><?=esc($stats['users_without_division'] ?? 0)?></strong></div>
  </div>
  <div class="um-overview-section">
    <div class="um-overview-title"><i data-lucide="shield-check" class="icon-sm"></i>Role & Permission Overview</div>
    <div class="um-overview-line"><span>Roles</span><strong><?=esc($role_count)?></strong></div>
    <div class="um-overview-line"><span>Permissions</span><strong><?=esc($permission_count)?></strong></div>
    <div class="um-overview-line"><span>Supervisors</span><strong><?=esc($supervisor_count)?></strong></div>
  </div>
  <div class="um-overview-section">
    <div class="um-overview-title"><i data-lucide="network" class="icon-sm"></i>Division / Status Overview</div>
    <div class="um-overview-line"><span>Divisions</span><strong><?=esc($stats['divisions_count'] ?? 0)?></strong></div>
    <div class="um-overview-line"><span>Suspended</span><strong><?=esc($stats['suspended_users'] ?? 0)?></strong></div>
    <div class="um-overview-line"><span>Inactive</span><strong><?=esc($stats['inactive_users'] ?? 0)?></strong></div>
  </div>
  <div class="um-overview-section um-intern-preview">
    <div class="um-overview-title"><i data-lucide="graduation-cap" class="icon-sm"></i>Intern Preview</div>
    <div class="um-overview-line"><span>Active interns</span><strong><?=esc($intern_preview_count)?></strong></div>
    <div class="um-overview-line"><span>Ending soon</span><strong><?=esc($stats['interns_ending_soon'] ?? 0)?></strong></div>
    <div class="um-overview-line"><span>Need review</span><strong><?=esc($stats['interns_pending_evaluation'] ?? 0)?></strong></div>
    <a class="btn btn-ghost btn-sm" href="/intern-management.php"><i data-lucide="arrow-right" class="icon-sm"></i>Open Intern Management</a>
  </div>
</section>

<div class="filter-bar um-tabs" role="tablist">
  <a class="filter-tab <?=$tab==='users'?'active':''?>" href="?tab=users"><i data-lucide="layout-dashboard" class="icon-sm"></i>Dashboard</a>
  <a class="filter-tab <?=$tab==='roles'?'active':''?>" href="?tab=roles"><i data-lucide="shield-check" class="icon-sm"></i>Roles & Permissions</a>
  <a class="filter-tab <?=$tab==='activity'?'active':''?>" href="?tab=activity"><i data-lucide="history" class="icon-sm"></i>Activity Log</a>
  <?php if($can_view_auth_security): ?><a class="filter-tab <?=$tab==='security'?'active':''?>" href="?tab=security"><i data-lucide="shield-alert" class="icon-sm"></i>Login Security</a><?php endif; ?>
  <?php if($can_manage_settings): ?><a class="filter-tab <?=$tab==='system'?'active':''?>" href="?tab=system"><i data-lucide="settings" class="icon-sm"></i>System</a><?php endif; ?>
</div>

<?php if($tab === 'users'): ?>
  <div class="filter-search-row um-filter-row">
    <form method="get" class="um-user-filter" aria-label="Filter users">
      <input type="hidden" name="tab" value="users">
      <div class="um-filter-search">
        <label class="um-filter-label" for="umUserSearch">Search users</label>
        <div class="search-form-wrap">
          <i data-lucide="search" class="search-ic icon-sm"></i>
          <input id="umUserSearch" type="text" name="q" class="search-input" placeholder="Name, username, email, phone, position" value="<?=esc($_GET['q'] ?? '')?>">
        </div>
      </div>
      <fieldset class="um-filter-group">
        <legend>People</legend>
        <select name="division_id" class="form-select compact-select" aria-label="Division">
          <option value="">All Divisions</option>
          <option value="0" <?=($_GET['division_id'] ?? '')==='0'?'selected':''?>>Without Division</option>
          <?php foreach($divisions as $division): ?>
            <option value="<?=$division['id']?>" <?=((string)($_GET['division_id'] ?? '')===(string)$division['id'])?'selected':''?>><?=esc($division['name'])?></option>
          <?php endforeach; ?>
        </select>
        <select name="role_id" class="form-select compact-select" aria-label="Role">
          <option value="">All Roles</option>
          <?php foreach($visible_roles as $role): ?>
            <option value="<?=$role['id']?>" <?=((string)($_GET['role_id'] ?? '')===(string)$role['id'])?'selected':''?>><?=esc($role['name'])?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="form-select compact-select" aria-label="Account status">
          <option value="">All Status</option>
          <?php foreach(['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $value=>$label): ?>
            <option value="<?=$value?>" <?=($_GET['status'] ?? '')===$value?'selected':''?>><?=$label?></option>
          <?php endforeach; ?>
        </select>
        <select name="last_active" class="form-select compact-select" aria-label="Last activity">
          <option value="">Any Activity</option>
          <option value="today" <?=($_GET['last_active'] ?? '')==='today'?'selected':''?>>Today</option>
          <option value="7d" <?=($_GET['last_active'] ?? '')==='7d'?'selected':''?>>Last 7 Days</option>
          <option value="30d" <?=($_GET['last_active'] ?? '')==='30d'?'selected':''?>>Last 30 Days</option>
          <option value="never" <?=($_GET['last_active'] ?? '')==='never'?'selected':''?>>Never</option>
        </select>
      </fieldset>
      <div class="um-filter-actions">
        <a href="/intern-management.php" class="btn btn-ghost"><i data-lucide="graduation-cap" class="icon-sm"></i>Interns</a>
        <?php if($has_user_filters): ?><a href="?tab=users" class="btn btn-ghost"><i data-lucide="x" class="icon-sm"></i>Reset</a><?php endif; ?>
        <button type="submit" class="btn btn-primary"><i data-lucide="filter" class="icon-sm"></i>Apply</button>
      </div>
    </form>
  </div>

  <div class="um-split-layout">
  <div class="um-filebook">
    <?php if(!$divisions): ?>
      <div class="panel um-panel"><div class="um-empty-state"><div class="empty-ic"><i data-lucide="network"></i></div><div class="empty-t">No divisions created</div><div class="empty-sub">Add a division to start grouping operational users.</div></div></div>
    <?php endif; ?>
    <?php foreach($dashboard_divisions as $idx => $division):
      $active = (int)($division['active_users_count'] ?? 0);
      $state = tracs_division_capacity_state($active);
      $statusClass = ($division['status'] ?? '') === 'active' ? 'b-active' : 'b-done';
      $divisionUsers = $users_by_division[(int)$division['id']] ?? [];
      $accent = ['#2563eb', '#0f766e', '#7c3aed', '#b45309', '#be123c', '#047857'][$idx % 6];
    ?>
    <section class="panel um-folder-section" style="--um-accent: <?=$accent?>">
      <div class="um-folder-tab">
        <div>
          <div class="um-folder-title"><?=esc($division['name'])?></div>
          <div class="um-folder-meta"><?=esc($division['code'])?> · <?=esc($division['supervisor_name'] ?: 'No lead assigned')?></div>
        </div>
        <div class="um-folder-facts">
          <?=um_badge(ucfirst($division['status'] ?? 'active'), $statusClass)?>
          <span><i data-lucide="users" class="icon-sm"></i><?=$active?> active · <?=esc($division['users_count'] ?? 0)?> total</span>
          <span><?=ucfirst($state)?> capacity</span>
        </div>
        <div class="um-folder-actions">
          <?php if($can_create_user): ?><button type="button" class="btn btn-ghost btn-sm" onclick="umCreateUserInDivision('<?=$division['id']?>')"><i data-lucide="user-plus" class="icon-sm"></i>Add User</button><?php endif; ?>
          <?php if($can_update_division): ?><button type="button" class="btn btn-ghost btn-sm" onclick="umEditDivision(this)" data-division="<?=um_division_payload($division)?>"><i data-lucide="pencil" class="icon-sm"></i>Edit</button><?php endif; ?>
          <?php if($can_manage_permissions): ?><a class="btn btn-ghost btn-sm" href="?tab=roles"><i data-lucide="shield-check" class="icon-sm"></i>Permissions</a><?php endif; ?>
        </div>
      </div>
      <div class="um-folder-body">
        <div class="um-capacity <?=$state?>">
          <div class="um-capacity-top"><span><?=ucfirst($state)?> capacity</span><span><?=$active?> / 10 ideal</span></div>
          <div class="um-capacity-track"><span style="width:<?=min(100, max(6, ($active / 10) * 100))?>%"></span></div>
        </div>
        <?php if(!$divisionUsers): ?>
          <div class="um-empty-state um-empty-compact"><div class="empty-t"><?= $has_user_filters ? 'No matching users in this division' : 'No users in this division' ?></div></div>
        <?php else: ?>
          <div class="um-user-card-grid">
            <?php $um_show_intern_meta = false; foreach($divisionUsers as $user): include __DIR__ . '/includes/user-management-card.php'; endforeach; unset($um_show_intern_meta); ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <?php if($active_filter_division === '' || $active_filter_division === '0'): ?>
    <section class="panel um-folder-section um-unassigned-section" style="--um-accent: var(--amber)">
      <div class="um-folder-tab">
        <div>
          <div class="um-folder-title">Unassigned Users</div>
          <div class="um-folder-meta">No division · Assign these accounts to a team</div>
        </div>
        <div class="um-folder-facts"><span><i data-lucide="user-x" class="icon-sm"></i><?=count($unassigned_users)?> users</span></div>
      </div>
      <div class="um-folder-body">
        <?php if(!$unassigned_users): ?>
          <div class="um-empty-state um-empty-compact"><div class="empty-t"><?= $has_user_filters ? 'No matching unassigned users' : 'No users without division' ?></div></div>
        <?php else: ?>
          <div class="um-user-card-grid">
            <?php $um_show_intern_meta = false; foreach($unassigned_users as $user): include __DIR__ . '/includes/user-management-card.php'; endforeach; unset($um_show_intern_meta); ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
    <?php if($has_user_filters && !$users): ?>
      <div class="panel um-panel"><div class="um-empty-state"><div class="empty-ic"><i data-lucide="search-x"></i></div><div class="empty-t">No matching users</div><div class="empty-sub">Try removing a filter or searching a different name, username, or email.</div></div></div>
    <?php endif; ?>
  </div>
  <aside class="panel um-side-panel">
    <div class="panel-head"><span class="panel-title">Control Panel</span></div>
    <div class="um-side-actions">
      <?php if($can_create_user): ?><button type="button" class="btn btn-primary" onclick="umCreateUser()"><i data-lucide="user-plus" class="icon-sm"></i>Add User</button><?php endif; ?>
      <a class="btn btn-ghost" href="?tab=roles"><i data-lucide="shield-check" class="icon-sm"></i>Role Matrix</a>
      <a class="btn btn-ghost" href="/intern-management.php"><i data-lucide="graduation-cap" class="icon-sm"></i>Intern Area</a>
    </div>
    <div class="um-side-summary">
      <div><span>Filtered users</span><strong><?=count($users)?></strong></div>
      <div><span>Active divisions</span><strong><?=esc($stats['divisions_count'] ?? 0)?></strong></div>
      <div><span>Permission keys</span><strong><?=esc($permission_count)?></strong></div>
    </div>
    <div class="um-side-note">Select a user card to open the detail drawer with account, permission, and creator-summary information.</div>
  </aside>
  </div>
<?php endif; ?>

<?php if($tab === 'roles'): ?>
  <div class="um-role-grid">
    <?php foreach($visible_roles as $role): ?>
      <div class="panel um-role-card">
        <div class="panel-head"><span class="panel-title"><?=esc($role['slug'])?></span><?=um_badge((string)$role['hierarchy_level'], 'b-info')?></div>
        <div class="um-role-body">
          <div class="um-role-title"><?=esc($role['name'])?></div>
          <div class="um-division-desc"><?=esc($role['description'] ?? '')?></div>
          <div class="um-role-stats"><span><?=esc($role['users_count'])?> users</span><span><?=esc($role['permissions_count'])?> permissions</span></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" class="panel um-permission-panel" onsubmit="return tracsConfirmSubmit(this, {type:'warning', title:'Save permission matrix', message:'Save role permission changes? This affects every user assigned to those roles.', confirmText:'Save changes', destructive:false})">
    <?=csrf_input()?><input type="hidden" name="action" value="update_permissions">
    <div class="panel-head">
      <span class="panel-title">Permission Matrix</span>
      <div class="panel-right">
        <span class="um-sensitive-warning"><i data-lucide="shield-alert" class="icon-sm"></i>Sensitive changes are audited</span>
        <?php if($can_manage_permissions): ?><button type="submit" class="btn btn-primary"><i data-lucide="save" class="icon-sm"></i>Save Changes</button><?php endif; ?>
      </div>
    </div>
    <div class="um-matrix-wrap">
      <table class="tracs-table um-permission-table">
        <thead><tr><th>Permission</th><?php foreach($visible_roles as $role): ?><th><?=esc($role['name'])?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach($permissions as $category=>$items): ?>
          <tr class="um-permission-group"><td colspan="<?=count($visible_roles)+1?>"><?=esc($category)?></td></tr>
          <?php foreach($items as $permission): $key=$permission['permission_key']; ?>
          <tr>
            <td>
              <div class="um-permission-name"><?=esc($key)?></div>
              <div class="um-permission-desc"><?=esc($permission['description'] ?? '')?></div>
            </td>
            <?php foreach($visible_roles as $role):
              $roleId=(int)$role['id'];
              $checked=in_array($key, $role_permission_map[$roleId]['keys'] ?? [], true);
              $editable=$can_manage_permissions && ($role['slug'] !== 'super_admin') && ($is_super_admin || ($roleId !== (int)($actor['role_id'] ?? 0) && (int)$role['hierarchy_level'] <= (int)($actor['hierarchy_level'] ?? 0) && in_array($key, $actor_permissions, true)));
            ?>
              <td class="um-permission-cell">
                <label class="um-check" title="<?=$editable?'Toggle permission':'Locked by hierarchy'?>">
                  <input type="checkbox" name="role_permissions[<?=$roleId?>][]" value="<?=esc($key)?>" <?=$checked?'checked':''?> <?=$editable?'':'disabled'?>>
                  <span></span>
                </label>
              </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
<?php endif; ?>

<?php if($tab === 'activity'): ?>
  <div class="filter-search-row um-filter-row">
    <form method="get" class="um-user-filter">
      <input type="hidden" name="tab" value="activity">
      <select name="actor_user_id" class="form-select compact-select">
        <option value="">All Actors</option>
        <?php foreach($user_options as $u): ?><option value="<?=$u['id']?>" <?=((string)($_GET['actor_user_id'] ?? '')===(string)$u['id'])?'selected':''?>><?=esc($u['display_name'])?></option><?php endforeach; ?>
      </select>
      <select name="target_user_id" class="form-select compact-select">
        <option value="">Any Target</option>
        <?php foreach($user_options as $u): ?><option value="<?=$u['id']?>" <?=((string)($_GET['target_user_id'] ?? '')===(string)$u['id'])?'selected':''?>><?=esc($u['display_name'])?></option><?php endforeach; ?>
      </select>
      <select name="activity_action" class="form-select compact-select">
        <option value="">All Actions</option>
        <?php foreach($activity_actions as $action): ?><option value="<?=esc($action)?>" <?=($_GET['activity_action'] ?? '')===$action?'selected':''?>><?=esc(str_replace('_',' ', $action))?></option><?php endforeach; ?>
      </select>
      <input type="date" name="from" class="form-input" value="<?=esc($_GET['from'] ?? '')?>">
      <input type="date" name="to" class="form-input" value="<?=esc($_GET['to'] ?? '')?>">
      <div class="search-form-wrap"><i data-lucide="search" class="search-ic icon-sm"></i><input type="text" name="activity_q" class="search-input" placeholder="Search audit log" value="<?=esc($_GET['activity_q'] ?? '')?>"></div>
      <button type="submit" class="btn btn-primary"><i data-lucide="filter" class="icon-sm"></i>Apply</button>
    </form>
  </div>

  <div class="panel um-panel">
    <div class="panel-head"><span class="panel-title">User Activity / Audit Log</span><span class="panel-counter"><?=count($activity_logs)?></span></div>
    <?php if(!$activity_logs): ?>
      <div class="um-empty-state"><div class="empty-ic"><i data-lucide="history"></i></div><div class="empty-t">No matching audit entries</div></div>
    <?php else: ?>
      <div class="um-timeline">
        <?php foreach($activity_logs as $log): ?>
          <div class="um-log-row">
            <div class="act-ic"><i data-lucide="history" class="icon-sm"></i></div>
            <div class="flex1">
              <div class="act-text"><strong><?=esc(str_replace('_',' ', $log['action']))?></strong><span>· <?=esc($log['target_name'] ?? $log['target_type'])?></span></div>
              <div class="act-desc">Actor: <?=esc($log['actor_name'] ?? 'System')?><?php if(!empty($log['reason'])): ?> · Reason: <?=esc($log['reason'])?><?php endif; ?></div>
              <div class="act-time"><?=um_dt($log['created_at'])?><?php if(!empty($log['ip_address'])): ?> · <?=esc($log['ip_address'])?><?php endif; ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if($tab === 'security' && $can_view_auth_security): ?>
  <div class="um-security-grid">
    <section class="panel um-panel">
      <div class="panel-head"><span class="panel-title">Active Login Protections</span><span class="panel-counter"><?=count($auth_locks)?></span></div>
      <?php if(!$auth_locks): ?>
        <div class="um-empty-state"><div class="empty-ic"><i data-lucide="shield-check"></i></div><div class="empty-t">No active failed-attempt records</div></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tracs-table">
            <thead><tr><th>Identifier</th><th>IP Address</th><th>Fails</th><th>Lock Until</th><th>CAPTCHA Until</th><th>Last Failed</th></tr></thead>
            <tbody>
              <?php foreach($auth_locks as $lock): ?>
                <tr>
                  <td><?=esc($lock['identifier_display'] ?: 'unknown')?></td>
                  <td><?=esc($lock['ip_address'] ?: 'unknown')?></td>
                  <td><?=esc($lock['failed_attempts'] ?? 0)?></td>
                  <td><?=um_dt($lock['locked_until'] ?? null)?></td>
                  <td><?=um_dt($lock['captcha_required_until'] ?? null)?></td>
                  <td><?=um_dt($lock['last_failed_at'] ?? null)?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel um-panel">
      <div class="panel-head"><span class="panel-title">Recent Authentication Events</span><span class="panel-counter"><?=count($auth_events)?></span></div>
      <?php if(!$auth_events): ?>
        <div class="um-empty-state"><div class="empty-ic"><i data-lucide="history"></i></div><div class="empty-t">No authentication events found</div></div>
      <?php else: ?>
        <div class="um-timeline">
          <?php foreach($auth_events as $event): ?>
            <div class="um-log-row">
              <div class="act-ic"><i data-lucide="shield-alert" class="icon-sm"></i></div>
              <div class="flex1">
                <div class="act-text"><strong><?=esc(str_replace('_',' ', $event['event_type']))?></strong><span>· <?=esc($event['result'])?></span></div>
                <div class="act-desc">Identifier: <?=esc($event['identifier'] ?: 'unknown')?><?php if(!empty($event['user_name'])): ?> · User: <?=esc($event['user_name'])?><?php endif; ?><?php if(!empty($event['reason'])): ?> · Reason: <?=esc($event['reason'])?><?php endif; ?></div>
                <div class="act-time"><?=um_dt($event['created_at'])?><?php if(!empty($event['ip_address'])): ?> · <?=esc($event['ip_address'])?><?php endif; ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>

<?php if($tab === 'system' && $can_manage_settings): ?>
  <section class="panel tracs-build-panel" aria-label="System build information">
    <div class="panel-head">
      <span class="panel-title">System Build</span>
      <button type="button" class="btn btn-ghost btn-sm" onclick="openModal('buildInfo')"><i data-lucide="info" class="icon-sm"></i>Build Info</button>
    </div>
    <div class="tracs-build-copy">
      This internal build identity is kept for deployment history, support traceability, and authorship reference. It is intentionally limited to administrative system settings and does not appear as public-facing branding.
    </div>
    <div class="tracs-build-grid">
      <div><span>Version</span><strong><?=esc($build_signature['version'])?></strong></div>
      <div><span>First deployment</span><strong><?=esc($build_signature['deployedLabel'])?></strong></div>
      <div><span>Build owner</span><strong><?=esc($build_signature['owner'])?></strong></div>
      <div><span>Environment</span><strong><?=esc($build_signature['environment'])?></strong></div>
    </div>
    <div class="um-side-note">Reference document: <code>docs/TRACS_SIGNATURE.md</code></div>
  </section>
<?php endif; ?>

<?php endif; ?>

</div></main>

<?php if($schema_ready): ?>
<div class="modal-overlay hidden" id="userFormModal">
  <form method="post" class="modal modal-lg um-modal" onsubmit="return umUserFormSubmit(this)">
    <?=csrf_input()?>
    <input type="hidden" name="action" id="umUserAction" value="create_user">
    <input type="hidden" name="user_id" id="umUserId" value="">
    <input type="hidden" name="original_status" id="umOriginalStatus" value="">
    <input type="hidden" name="reason" id="umUserReason" value="">
    <div class="modal-head">
      <div><div class="modal-title" id="umUserModalTitle">Add User</div><div class="modal-sub" id="umUserModalSub">Create a secure TRACS account</div></div>
      <button type="button" class="modal-close" onclick="closeModal('userForm')"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <div class="um-form-section"><div class="um-form-section-title">Identity</div>
        <div class="um-avatar-editor" data-avatar-scope>
          <div class="um-avatar-editor-preview tracs-avatar" id="umAvatarPreview" data-avatar-user-id="" data-avatar-initials="U"><span>U</span></div>
          <div class="um-avatar-editor-copy">
            <div class="um-avatar-editor-title">Profile Picture</div>
            <div class="form-hint" id="umAvatarHint">Save the user first, then upload a cropped avatar.</div>
          </div>
          <div class="um-avatar-editor-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="umAvatarChangeBtn" data-avatar-upload data-avatar-user-id="" disabled><i data-lucide="image-plus" class="icon-sm"></i>Change Photo</button>
            <button type="button" class="btn btn-ghost btn-sm" id="umAvatarRemoveBtn" data-avatar-remove data-avatar-user-id="" disabled><i data-lucide="trash-2" class="icon-sm"></i>Remove</button>
          </div>
        </div>
        <div class="form-row"><div class="form-group"><label class="form-label">Full Name</label><input class="form-input" name="name" id="umName" required></div><div class="form-group"><label class="form-label">Username</label><input class="form-input" name="username" id="umUsername" required></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" id="umEmail" required></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" id="umPhone"></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Position</label><input class="form-input" name="position" id="umPosition"></div><div class="form-group"><label class="form-label">Avatar Color</label><input class="form-input" name="avatar_initials_color" id="umAvatarColor" placeholder="#2563eb"></div></div>
      </div>
      <div class="um-form-section"><div class="um-form-section-title">Access</div>
        <div class="form-row"><div class="form-group"><label class="form-label">Role</label><select class="form-select" name="role_id" id="umRoleId" required onchange="umToggleInternSection()"><?php foreach($visible_roles as $role): ?><option value="<?=$role['id']?>" data-role-slug="<?=esc($role['slug'])?>"><?=esc($role['name'])?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Division</label><select class="form-select" name="division_id" id="umDivisionId"><option value="">No Division</option><?php foreach($divisions as $division): ?><option value="<?=$division['id']?>"><?=esc($division['name'])?></option><?php endforeach; ?></select></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status" id="umStatus"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></div><div class="form-group"><label class="form-label">Shift Preference</label><input class="form-input" name="shift_preference" id="umShift" placeholder="Shift 1, Shift 2, Shift 3"></div></div>
      </div>
      <div class="um-form-section um-intern-form-section" id="umInternSection" hidden>
        <div class="um-form-section-title">Internship Information</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">University / Campus</label><input class="form-input" name="university_name" id="umUniversityName" data-intern-required><div class="form-hint">Required only for Intern users.</div></div>
          <div class="form-group"><label class="form-label">Study Program / Major</label><input class="form-input" name="study_program" id="umStudyProgram"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Internship Start Date</label><input class="form-input" type="date" name="internship_start_date" id="umInternStart" data-intern-required></div>
          <div class="form-group"><label class="form-label">Internship End Date</label><input class="form-input" type="date" name="internship_end_date" id="umInternEnd" data-intern-required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Mentor / Supervisor</label><select class="form-select" name="mentor_user_id" id="umMentorUserId"><option value="">No Mentor</option><?php foreach($mentor_options as $mentor): ?><option value="<?=$mentor['id']?>"><?=esc($mentor['display_name'])?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">Internship Status</label><select class="form-select" name="internship_status" id="umInternshipStatus"><option value="upcoming">Upcoming</option><option value="active">Active</option><option value="ending_soon">Ending Soon</option><option value="completed">Completed</option><option value="extended">Extended</option><option value="terminated">Terminated</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Evaluation Status</label><select class="form-select" name="evaluation_status" id="umEvaluationStatus"><option value="not_started">Not Started</option><option value="in_review">In Review</option><option value="passed">Passed</option><option value="needs_improvement">Needs Improvement</option><option value="failed">Failed</option></select></div>
          <div class="form-group"><label class="form-label">Skill Level</label><select class="form-select" name="skill_level" id="umSkillLevel"><option value="beginner">Beginner</option><option value="basic">Basic</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Allowed Task Scope</label><select class="form-select" name="allowed_task_scope" id="umAllowedTaskScope"><option value="">Not Set</option><option value="observation_only">Observation Only</option><option value="simple_case_handling">Simple Case Handling</option><option value="checklist_task">Checklist Task</option><option value="reminder_followup">Reminder Follow-up</option><option value="shift_report_draft">Shift Report Draft</option><option value="supervised_customer_response">Supervised Customer Response</option><option value="internal_documentation">Internal Documentation</option><option value="qa_assistance">QA Assistance</option></select></div>
          <div class="form-group"><label class="form-label">Supervisor Notes</label><textarea class="form-textarea" name="special_notes" id="umSpecialNotes" rows="3"></textarea></div>
        </div>
      </div>
      <div class="um-form-section" id="umSecuritySection"><div class="um-form-section-title">Security</div>
        <div class="form-group"><label class="form-label">Initial Password</label><input class="form-input" type="password" name="password" id="umPassword" placeholder="Leave blank to generate a secure temporary password"><div class="form-hint">Minimum 8 characters. Generated passwords are shown once after save.</div></div>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('userForm')">Cancel</button><button type="submit" class="btn btn-primary"><i data-lucide="check" class="icon-sm"></i>Save User</button></div>
  </form>
</div>

<?php if($can_reset_2fa): ?>
<div class="modal-overlay hidden" id="twoFactorResetModal">
  <form method="post" class="modal" onsubmit="return umConfirmTwoFactorResetSubmit(this)">
    <?=csrf_input()?>
    <input type="hidden" name="action" value="reset_two_factor">
    <input type="hidden" name="user_id" id="umTwoFactorResetUserId" value="">
    <input type="hidden" name="reason" id="umTwoFactorResetReason" value="">
    <div class="modal-head">
      <div><div class="modal-title">Reset two-factor authentication</div><div class="modal-sub" id="umTwoFactorResetSub">This user will need to set up 2FA again.</div></div>
      <button type="button" class="modal-close" onclick="closeModal('twoFactorReset')"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <div class="um-permission-note warning"><i data-lucide="shield-alert" class="icon-sm"></i><span>Resetting 2FA clears the existing authenticator secret and requires the user to complete setup again on their next login. This action is logged.</span></div>
      <div class="form-group">
        <label class="form-label" for="umTwoFactorResetReasonInput">Reason</label>
        <textarea class="form-textarea" id="umTwoFactorResetReasonInput" rows="3" placeholder="Optional operational note"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('twoFactorReset')">Cancel</button>
      <button type="submit" class="btn btn-danger"><i data-lucide="shield-off" class="icon-sm"></i>Reset 2FA</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="modal-overlay hidden" id="divisionFormModal">
  <form method="post" class="modal">
    <?=csrf_input()?>
    <input type="hidden" name="action" id="umDivisionAction" value="create_division">
    <input type="hidden" name="division_id" id="umDivisionFormId" value="">
    <div class="modal-head"><div><div class="modal-title" id="umDivisionModalTitle">Add Division</div><div class="modal-sub">Structure agents into operational teams</div></div><button type="button" class="modal-close" onclick="closeModal('divisionForm')"><i data-lucide="x"></i></button></div>
    <div class="modal-body">
      <div class="form-row"><div class="form-group"><label class="form-label">Division Name</label><input class="form-input" name="name" id="umDivisionName" required></div><div class="form-group"><label class="form-label">Code</label><input class="form-input" name="code" id="umDivisionCode" required></div></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description" id="umDivisionDescription"></textarea></div>
      <div class="form-row"><div class="form-group"><label class="form-label">Supervisor / Lead</label><select class="form-select" name="supervisor_id" id="umDivisionSupervisor"><option value="">No Lead</option><?php foreach($user_options as $u): ?><option value="<?=$u['id']?>"><?=esc($u['display_name'])?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status" id="umDivisionStatus"><option value="active">Active</option><option value="archived">Archived</option></select></div></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('divisionForm')">Cancel</button><button type="submit" class="btn btn-primary"><i data-lucide="check" class="icon-sm"></i>Save Division</button></div>
  </form>
</div>

<aside class="um-drawer" id="umUserDrawer" aria-hidden="true">
  <div class="um-drawer-head">
    <div class="um-drawer-avatar tracs-avatar" id="umDrawerAvatar" data-avatar-user-id="" data-avatar-initials="U"><span>U</span></div>
    <div><div class="um-drawer-title" id="umDrawerName">User</div><div class="um-drawer-sub" id="umDrawerEmail">email</div></div>
    <button class="modal-close" type="button" onclick="umCloseUserDrawer()"><i data-lucide="x"></i></button>
  </div>
  <div class="um-drawer-body">
    <div class="um-badge-row" id="umDrawerBadges"></div>
    <div class="um-detail-grid" id="umDrawerDetails"></div>
    <div class="panel um-drawer-panel">
      <div class="panel-head"><span class="panel-title">Created Items Summary</span></div>
      <div class="um-summary-list" id="umDrawerSummary"></div>
    </div>
    <div class="panel um-drawer-panel um-drawer-intern" id="umDrawerIntern" hidden>
      <div class="panel-head"><span class="panel-title">Internship Information</span></div>
      <div class="um-detail-grid" id="umDrawerInternDetails"></div>
      <div class="um-intern-notes"><span>Supervisor Notes</span><strong id="umDrawerInternNotes">—</strong></div>
      <div class="um-intern-monitor" id="umDrawerInternWarnings"></div>
      <div class="um-intern-placeholder">
        <div><span>Assigned Tasks</span><strong>Task assignment module not installed yet.</strong></div>
        <div><span>Evaluation Summary</span><strong>Evaluation module not installed yet.</strong></div>
      </div>
    </div>
    <div class="um-drawer-actions" id="umDrawerActions"></div>
  </div>
</aside>

<aside class="um-drawer um-permission-drawer" id="umPermissionDrawer" aria-hidden="true">
  <form method="post" onsubmit="return umConfirmPermissionSave(this)">
    <?=csrf_input()?>
    <input type="hidden" name="action" value="update_role_permissions_one">
    <input type="hidden" name="role_id" id="umPermissionRoleId" value="">
    <div class="um-drawer-head">
      <div class="um-drawer-avatar"><i data-lucide="shield-check" class="icon-sm"></i></div>
      <div><div class="um-drawer-title" id="umPermissionTitle">Permissions</div><div class="um-drawer-sub" id="umPermissionSub">Role defaults</div></div>
      <button class="modal-close" type="button" onclick="umCloseDrawers()"><i data-lucide="x"></i></button>
    </div>
    <div class="um-drawer-body">
      <div class="um-permission-note"><i data-lucide="info" class="icon-sm"></i><span>TRACS currently uses role-based permissions. Changes here update the selected role and affect every user with that role.</span></div>
      <div class="um-permission-note warning"><i data-lucide="shield-alert" class="icon-sm"></i><span>User-specific overrides are not enabled, so this drawer does not create direct permission exceptions.</span></div>
      <div id="umPermissionGroups" class="um-permission-groups"></div>
    </div>
    <div class="um-drawer-foot">
      <button type="button" class="btn btn-ghost" onclick="umCloseDrawers()">Cancel</button>
      <?php if($can_manage_permissions): ?><button type="submit" class="btn btn-primary"><i data-lucide="save" class="icon-sm"></i>Save Role Permissions</button><?php endif; ?>
    </div>
  </form>
</aside>
<div class="um-drawer-scrim" id="umDrawerScrim" onclick="umCloseDrawers()"></div>

<div class="modal-overlay hidden" id="avatarCropModal">
  <div class="modal avatar-crop-modal" role="dialog" aria-modal="true" aria-labelledby="avatarCropTitle">
    <div class="modal-head">
      <div><div class="modal-title" id="avatarCropTitle">Crop Profile Picture</div><div class="modal-sub">Square avatar preview before upload</div></div>
      <button type="button" class="modal-close" data-avatar-cancel><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body avatar-crop-body">
      <div class="avatar-crop-grid">
        <div class="avatar-crop-stage"><canvas id="avatarCropCanvas" width="512" height="512" aria-label="Avatar crop area"></canvas></div>
        <div class="avatar-crop-side">
          <canvas id="avatarPreviewCanvas" width="128" height="128" aria-label="Avatar preview"></canvas>
          <label class="form-label" for="avatarZoomRange">Zoom</label>
          <input id="avatarZoomRange" class="avatar-zoom-range" type="range" min="1" max="4" step="0.01" value="1">
          <div class="avatar-crop-actions"><button type="button" class="btn btn-ghost btn-sm" data-avatar-zoom-out><i data-lucide="minus" class="icon-sm"></i></button><button type="button" class="btn btn-ghost btn-sm" data-avatar-zoom-in><i data-lucide="plus" class="icon-sm"></i></button></div>
        </div>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-avatar-cancel>Cancel</button><button type="button" class="btn btn-primary" data-avatar-confirm><i data-lucide="check" class="icon-sm"></i>Save Photo</button></div>
  </div>
</div>

<script>
const UM_PERMISSION_CATALOG = <?=json_encode($permission_catalog_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
const UM_ROLE_PERMISSIONS = <?=json_encode($role_permission_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
const UM_CAN_MANAGE_PERMISSIONS = <?=json_encode($can_manage_permissions)?>;
const UM_CAN_RESET_2FA = <?=json_encode($can_reset_2fa)?>;
const UM_ACTOR_ROLE_ID = <?=json_encode((int)($actor['role_id'] ?? 0))?>;
const UM_IS_SUPER_ADMIN = <?=json_encode($is_super_admin)?>;
function umEsc(value){ return String(value ?? '').replace(/[&<>"']/g, ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch])); }
function umData(btn, key){ try { return JSON.parse(btn.dataset[key] || '{}'); } catch(e) { return {}; } }
function umAllowDialogSubmit(form){
  if(form?.dataset?.tracsConfirmed === '1'){
    delete form.dataset.tracsConfirmed;
    return true;
  }
  return false;
}
function umSubmitAfterDialog(form){
  if(!form) return;
  form.dataset.tracsConfirmed='1';
  if(typeof form.requestSubmit === 'function') form.requestSubmit();
  else form.submit();
}
async function umPromptText(options){
  const value=await tracsPrompt({
    type: options.type || 'info',
    title: options.title || 'Reason required',
    subtitle: options.subtitle || 'Audited user management action',
    message: options.message || '',
    inputLabel: options.inputLabel || 'Reason',
    placeholder: options.placeholder || '',
    required: !!options.required,
    confirmText: options.confirmText || 'Continue',
    cancelText: options.cancelText || 'Cancel'
  });
  return (value || '').trim();
}
function umSetValue(id, value){ const el=document.getElementById(id); if(el) el.value=value ?? ''; }
function umInitials(u){ return (u.name||u.email||'U').split(/\s+/).map(x=>x[0]).join('').slice(0,2).toUpperCase(); }
function umSetAvatarNode(node, u){
  if(!node) return;
  const initials=umInitials(u);
  node.dataset.avatarUserId=u.id || '';
  node.dataset.avatarInitials=initials;
  if(u.avatar_initials_color) node.style.setProperty('--um-avatar-bg', u.avatar_initials_color);
  const url=u.avatar_url || u.avatar_path || '';
  node.innerHTML=url ? `<img src="${umEsc(url)}" alt="" loading="lazy" decoding="async">` : `<span>${umEsc(initials)}</span>`;
}
function umSetAvatarEditor(u){
  const userId=u?.id || '';
  umSetAvatarNode(document.getElementById('umAvatarPreview'), u || {name:'User', email:'U'});
  ['umAvatarChangeBtn','umAvatarRemoveBtn'].forEach(id=>{
    const btn=document.getElementById(id);
    if(!btn) return;
    btn.dataset.avatarUserId=userId;
    btn.disabled=!userId;
  });
  const hint=document.getElementById('umAvatarHint');
  if(hint) hint.textContent=userId ? 'Upload is cropped and compressed before it is saved.' : 'Save the user first, then upload a cropped avatar.';
}
function umCurrentRoleSlug(){
  const select=document.getElementById('umRoleId');
  return select?.selectedOptions?.[0]?.dataset?.roleSlug || '';
}
function umToggleInternSection(){
  const isIntern=umCurrentRoleSlug()==='intern';
  const section=document.getElementById('umInternSection');
  if(section) section.hidden=!isIntern;
  section?.querySelectorAll('[data-intern-required]').forEach(el=>{ el.required=isIntern; });
  window.TRACSDropdowns?.syncAll();
}
function umClearInternFields(){
  ['umUniversityName','umStudyProgram','umInternStart','umInternEnd','umSpecialNotes'].forEach(id=>umSetValue(id,''));
  umSetValue('umMentorUserId',''); umSetValue('umInternshipStatus','active'); umSetValue('umEvaluationStatus','not_started');
  umSetValue('umSkillLevel','beginner'); umSetValue('umAllowedTaskScope','');
}
function umCreateUser(){
  document.getElementById('umUserModalTitle').textContent='Add User';
  document.getElementById('umUserModalSub').textContent='Create a secure TRACS account';
  umSetValue('umUserAction','create_user'); umSetValue('umUserId','');
  umSetValue('umOriginalStatus',''); umSetValue('umUserReason','');
  ['umName','umUsername','umEmail','umPhone','umPosition','umAvatarColor','umShift','umPassword'].forEach(id=>umSetValue(id,''));
  umSetAvatarEditor({id:'', name:'User', email:'U'});
  umClearInternFields();
  umSetValue('umStatus','active'); umSetValue('umDivisionId',''); document.getElementById('umSecuritySection').style.display='';
  openModal('userForm'); umToggleInternSection(); window.TRACSDropdowns?.syncAll();
}
function umCreateUserInDivision(divisionId){ umCreateUser(); umSetValue('umDivisionId', divisionId); window.TRACSDropdowns?.syncAll(); }
function umEditUser(btn){
  const u=umData(btn,'user');
  document.getElementById('umUserModalTitle').textContent='Edit User';
  document.getElementById('umUserModalSub').textContent='Update identity, access, division, and status';
  umSetValue('umUserAction','update_user'); umSetValue('umUserId',u.id);
  umSetValue('umOriginalStatus',u.status || 'active'); umSetValue('umUserReason','');
  umSetValue('umName',u.name); umSetValue('umUsername',u.username); umSetValue('umEmail',u.email); umSetValue('umPhone',u.phone);
  umSetValue('umPosition',u.position); umSetValue('umAvatarColor',u.avatar_initials_color); umSetValue('umRoleId',u.role_id);
  umSetAvatarEditor(u);
  umSetValue('umDivisionId',u.division_id || ''); umSetValue('umStatus',u.status); umSetValue('umShift',u.shift_preference);
  umSetValue('umUniversityName',u.university_name); umSetValue('umStudyProgram',u.study_program); umSetValue('umInternStart',u.internship_start_date);
  umSetValue('umInternEnd',u.internship_end_date); umSetValue('umMentorUserId',u.mentor_user_id || ''); umSetValue('umInternshipStatus',u.internship_status || 'active');
  umSetValue('umEvaluationStatus',u.evaluation_status || 'not_started'); umSetValue('umSkillLevel',u.skill_level || 'beginner'); umSetValue('umAllowedTaskScope',u.allowed_task_scope || '');
  umSetValue('umSpecialNotes',u.special_notes);
  umSetValue('umPassword',''); document.getElementById('umSecuritySection').style.display='none';
  openModal('userForm'); umToggleInternSection(); window.TRACSDropdowns?.syncAll();
}
function umCreateDivision(){
  document.getElementById('umDivisionModalTitle').textContent='Add Division';
  umSetValue('umDivisionAction','create_division'); umSetValue('umDivisionFormId','');
  ['umDivisionName','umDivisionCode','umDivisionDescription'].forEach(id=>umSetValue(id,''));
  umSetValue('umDivisionSupervisor',''); umSetValue('umDivisionStatus','active');
  openModal('divisionForm'); window.TRACSDropdowns?.syncAll();
}
function umEditDivision(btn){
  const d=umData(btn,'division');
  document.getElementById('umDivisionModalTitle').textContent='Edit Division';
  umSetValue('umDivisionAction','update_division'); umSetValue('umDivisionFormId',d.id);
  umSetValue('umDivisionName',d.name); umSetValue('umDivisionCode',d.code); umSetValue('umDivisionDescription',d.description);
  umSetValue('umDivisionSupervisor',d.supervisor_id || ''); umSetValue('umDivisionStatus',d.status || 'active');
  openModal('divisionForm'); window.TRACSDropdowns?.syncAll();
}
function umSubmitReason(form, promptText){
  if(umAllowDialogSubmit(form)) return true;
  (async()=>{
    const reason=await umPromptText({
      type:'warning',
      title:'Status change reason',
      message:promptText,
      required:true,
      confirmText:'Review change'
    });
    if(!reason) return;
    form.querySelector('[name="reason"]').value=reason;
    const ok=await tracsConfirm({
      type:'warning',
      title:'Confirm account status change',
      message:'Confirm this account status change?',
      confirmText:'Confirm',
      destructive:true
    });
    if(ok) umSubmitAfterDialog(form);
  })();
  return false;
}
function umUserFormSubmit(form){
  if(umAllowDialogSubmit(form)) return true;
  const action=form.querySelector('[name="action"]')?.value;
  const original=form.querySelector('[name="original_status"]')?.value || '';
  const next=form.querySelector('[name="status"]')?.value || '';
  if(umCurrentRoleSlug()==='intern'){
    const university=document.getElementById('umUniversityName')?.value.trim();
    const start=document.getElementById('umInternStart')?.value;
    const end=document.getElementById('umInternEnd')?.value;
    if(!university || !start || !end){ toast('University, start date, and end date are required for interns.','error'); return false; }
    if(new Date(end) <= new Date(start)){ toast('Internship end date must be after start date.','error'); return false; }
  }
  if(action === 'update_user' && original && original !== next){
    (async()=>{
      if(next !== 'active'){
        const reason=await umPromptText({
          type:'warning',
          title:'Status change reason',
          message:'Provide a reason for this account status change.',
          required:true,
          confirmText:'Review change'
        });
        if(!reason) return;
        form.querySelector('[name="reason"]').value=reason;
      }
      const ok=await tracsConfirm({
        type:'warning',
        title:'Save account status change',
        message:'Save this account status change?',
        confirmText:'Save change',
        destructive:next !== 'active'
      });
      if(ok) umSubmitAfterDialog(form);
    })();
    return false;
  }
  return true;
}
function umConfirmReset(form){
  if(umAllowDialogSubmit(form)) return true;
  (async()=>{
    const reason=await umPromptText({
      type:'warning',
      title:'Password reset reason',
      message:'Add an optional reason for this password reset.',
      inputLabel:'Reason (optional)',
      required:false,
      confirmText:'Continue'
    });
    form.querySelector('[name="reason"]').value=reason;
    const ok=await tracsConfirm({
      type:'warning',
      title:'Generate temporary password',
      message:'Generate a temporary password for this user? It will be shown once.',
      confirmText:'Generate',
      destructive:true
    });
    if(ok) umSubmitAfterDialog(form);
  })();
  return false;
}
function umOpenTwoFactorResetModal(btn){
  umOpenTwoFactorResetModalForUser(umData(btn,'user'));
}
function umOpenTwoFactorResetModalForUser(u){
  if(!UM_CAN_RESET_2FA || !u?.id) return;
  umSetValue('umTwoFactorResetUserId', u.id);
  umSetValue('umTwoFactorResetReason', '');
  const reasonInput=document.getElementById('umTwoFactorResetReasonInput');
  if(reasonInput) reasonInput.value='';
  const sub=document.getElementById('umTwoFactorResetSub');
  if(sub) sub.textContent=`${u.name || u.email || 'This user'} will need to set up 2FA again on their next login.`;
  openModal('twoFactorReset');
}
function umConfirmTwoFactorResetSubmit(form){
  if(umAllowDialogSubmit(form)) return true;
  const reasonInput=document.getElementById('umTwoFactorResetReasonInput');
  form.querySelector('[name="reason"]').value=(reasonInput?.value || '').trim();
  tracsConfirm({
    type:'warning',
    title:'Reset user 2FA',
    message:'Reset this user\'s 2FA now? They will not be able to access TRACS until setup is completed again.',
    confirmText:'Reset 2FA',
    destructive:true
  }).then(ok=>{ if(ok) umSubmitAfterDialog(form); });
  return false;
}
function umArchiveDivision(form, activeCount){
  if(umAllowDialogSubmit(form)) return true;
  (async()=>{
    let reason='Archived empty division';
    if(activeCount > 0){
      reason=await umPromptText({
        type:'warning',
        title:'Archive active division',
        message:'This division still has active users. Type a reason to archive anyway.',
        required:true,
        confirmText:'Review archive'
      });
      if(!reason) return;
    }
    const ok=await tracsConfirm({
      type:'warning',
      title:'Archive division',
      message:'Archive this division?',
      confirmText:'Archive',
      destructive:true
    });
    if(!ok) return;
    form.querySelector('[name="confirm_archive"]').value='1';
    form.querySelector('[name="reason"]').value=reason;
    umSubmitAfterDialog(form);
  })();
  return false;
}
function umCopy(id){
  const input=document.getElementById(id); if(!input)return;
  input.select(); navigator.clipboard?.writeText(input.value); toast('Copied to clipboard','success');
}
function umOpenUserDrawer(btn){
  const u=umData(btn,'user');
  umSetAvatarNode(document.getElementById('umDrawerAvatar'), u);
  document.getElementById('umDrawerName').textContent=u.name || 'User';
  document.getElementById('umDrawerEmail').textContent=`${u.email} · @${u.username}`;
  document.getElementById('umDrawerBadges').innerHTML=`<span class="badge ${u.role_slug==='super_admin'?'b-critical':u.role_slug==='admin'?'b-active':u.role_slug==='supervisor'?'b-info':u.role_slug==='viewer'?'b-done':'b-low'}">${umEsc(u.role_name)}</span><span class="badge ${u.status==='active'?'b-active':u.status==='suspended'?'b-critical':'b-done'}">${umEsc(u.status)}</span><span class="badge ${u.division_name==='No Division'?'b-done':'b-info'}">${umEsc(u.division_name)}</span>`;
  const twoFactorState = Number(u.two_factor_enabled) === 1 && Number(u.two_factor_reset_required) !== 1 ? 'Enabled' : 'Setup required';
  const details=[['Position',u.position||'—'],['Phone',u.phone||'—'],['Shift',u.shift_preference||'—'],['2FA',twoFactorState],['2FA Confirmed',u.two_factor_confirmed_at],['2FA Last Verified',u.two_factor_last_verified_at],['Created',u.created_at],['Updated',u.updated_at],['Last Login',u.last_login_at],['Last Activity',u.last_activity_at],['Last Password Change',u.last_password_change_at]];
  document.getElementById('umDrawerDetails').innerHTML=details.map(([k,v])=>`<div><span>${umEsc(k)}</span><strong>${umEsc(v||'—')}</strong></div>`).join('');
  const summary=Object.entries(u.created_summary||{});
  document.getElementById('umDrawerSummary').innerHTML=summary.length?summary.map(([k,v])=>`<div><span>${umEsc(k)}</span><strong>${umEsc(v)}</strong></div>`).join(''):'<div class="um-empty-mini">No created item summary available.</div>';
  const internPanel=document.getElementById('umDrawerIntern');
  if(internPanel){
    if(u.is_intern){
      const remaining=Number.isFinite(Number(u.internship_days_remaining)) ? Number(u.internship_days_remaining) : null;
      const remainingText=remaining===null ? '—' : (remaining < 0 ? 'End date passed' : `${remaining} days remaining`);
      const warnings=[];
      if(u.internship_monitor_state==='ending_soon') warnings.push('Internship ends within 14 days.');
      if(u.internship_monitor_state==='end_passed') warnings.push('Internship end date has passed.');
      if(!u.mentor_user_id) warnings.push('Mentor is not assigned.');
      if(['not_started','in_review','needs_improvement'].includes(u.evaluation_status || '')) warnings.push('Evaluation is pending or needs follow-up.');
      document.getElementById('umDrawerInternDetails').innerHTML=[
        ['University',u.university_name||'—'],['Study Program',u.study_program||'—'],['Period',`${u.internship_start_date||'—'} to ${u.internship_end_date||'—'}`],['Remaining',remainingText],
        ['Mentor',u.mentor_name||'Unassigned'],['Internship Status',String(u.internship_status||'—').replaceAll('_',' ')],['Evaluation',String(u.evaluation_status||'—').replaceAll('_',' ')],['Skill Level',String(u.skill_level||'—').replaceAll('_',' ')],
        ['Task Scope',String(u.allowed_task_scope||'—').replaceAll('_',' ')]
      ].map(([k,v])=>`<div><span>${umEsc(k)}</span><strong>${umEsc(v)}</strong></div>`).join('');
      document.getElementById('umDrawerInternNotes').textContent=u.special_notes || 'No supervisor notes recorded.';
      document.getElementById('umDrawerInternWarnings').innerHTML=warnings.length?warnings.map(w=>`<div class="um-intern-warning"><i data-lucide="alert-triangle" class="icon-sm"></i>${umEsc(w)}</div>`).join(''):'<div class="um-empty-mini">No intern monitoring warnings.</div>';
      internPanel.hidden=false;
    } else {
      internPanel.hidden=true;
    }
  }
  window.UM_DRAWER_USER = u;
  const drawerActions=[
    `<button type="button" class="btn btn-ghost btn-sm" onclick="umRenderPermissionDrawer(window.UM_DRAWER_USER || {})"><i data-lucide="shield-check" class="icon-sm"></i>Permissions</button>`,
    `<a class="btn btn-ghost btn-sm" href="?tab=activity&target_user_id=${u.id}"><i data-lucide="history" class="icon-sm"></i>Activity</a>`
  ];
  if(UM_CAN_RESET_2FA){
    drawerActions.push(`<button type="button" class="btn btn-ghost btn-sm" onclick="umOpenTwoFactorResetModalForUser(window.UM_DRAWER_USER || {})"><i data-lucide="shield-off" class="icon-sm"></i>Reset 2FA</button>`);
  }
  document.getElementById('umDrawerActions').innerHTML=drawerActions.join('');
  document.getElementById('umUserDrawer').classList.add('is-open');
  document.getElementById('umDrawerScrim').classList.add('is-open');
  lucide?.createIcons();
}
function umOpenPermissionDrawer(btn){
  umRenderPermissionDrawer(umData(btn,'user'));
}
function umRenderPermissionDrawer(u){
  const role=UM_ROLE_PERMISSIONS[String(u.role_id)] || {keys:[], name:u.role_name || 'Role', slug:u.role_slug || '', id:u.role_id};
  const keys=new Set(role.keys || []);
  const canEditRole = UM_CAN_MANAGE_PERMISSIONS && role.slug !== 'super_admin' && (UM_IS_SUPER_ADMIN || Number(role.id) !== Number(UM_ACTOR_ROLE_ID));
  document.getElementById('umPermissionRoleId').value=role.id || u.role_id || '';
  document.getElementById('umPermissionTitle').textContent=`${u.name || 'User'} Permissions`;
  document.getElementById('umPermissionSub').textContent=`Role default: ${role.name || u.role_name || 'Role'} · ${keys.size} permissions`;
  const groups=Object.entries(UM_PERMISSION_CATALOG).map(([module, items])=>{
    const allSelected=items.length && items.every(item=>keys.has(item.key));
    const checks=items.map(item=>`
      <label class="um-permission-toggle">
        <input type="checkbox" name="permissions[]" value="${umEsc(item.key)}" ${keys.has(item.key)?'checked':''} ${canEditRole?'':'disabled'}>
        <span></span>
        <strong>${umEsc(item.description || item.key)}</strong>
        <em>${umEsc(item.key)}</em>
      </label>`).join('');
    return `<section class="um-permission-module">
      <div class="um-permission-module-head"><span>${umEsc(module)}</span><label class="um-module-select"><input type="checkbox" ${allSelected?'checked':''} ${canEditRole?'':'disabled'} onchange="umTogglePermissionModule(this)"><span>Select all</span></label></div>
      ${checks || '<div class="um-empty-mini">No permission assigned.</div>'}
    </section>`;
  }).join('');
  document.getElementById('umPermissionGroups').innerHTML=groups || '<div class="um-empty-state um-empty-compact"><div class="empty-t">No permissions available</div></div>';
  document.getElementById('umPermissionDrawer').classList.add('is-open');
  document.getElementById('umDrawerScrim').classList.add('is-open');
  lucide?.createIcons();
}
function umTogglePermissionModule(toggle){
  const module=toggle.closest('.um-permission-module');
  module?.querySelectorAll('.um-permission-toggle input[type="checkbox"]:not(:disabled)').forEach(input=>input.checked=toggle.checked);
}
function umConfirmPermissionSave(form){
  return tracsConfirmSubmit(form, {
    type:'warning',
    title:'Save role permissions',
    message:'Save role permission changes? This affects every user assigned to this role and will be audited.',
    confirmText:'Save permissions',
    destructive:false
  });
}
function umCloseDrawers(){
  document.getElementById('umUserDrawer')?.classList.remove('is-open');
  document.getElementById('umPermissionDrawer')?.classList.remove('is-open');
  document.getElementById('umDrawerScrim')?.classList.remove('is-open');
}
function umCloseUserDrawer(){ umCloseDrawers(); }
document.addEventListener('keydown', e=>{ if(e.key==='Escape') umCloseDrawers(); });
</script>
<?php endif; ?>

<?php if($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', () => toast(<?=json_encode($flash['message'])?>, <?=json_encode($flash['type'])?>));
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
