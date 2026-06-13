<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
tracs_require_page_permission($conn, 'profile.view_own');
require_once __DIR__ . '/../modules/user-management/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$UM = new UserManagementController($conn, $uid);
$schema_ready = $UM->schemaReady();

function profile_flash(string $type, string $message): void {
    $_SESSION['profile_flash'] = ['type' => $type, 'message' => $message];
}

function profile_redirect(string $section): never {
    header('Location: /profile.php?section=' . urlencode($section));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$schema_ready) {
        profile_flash('error', 'Run the User Management migration before saving profile changes.');
        profile_redirect('profile');
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        $result = match ($action) {
            'update_profile' => $UM->updateOwnProfile($_POST),
            'change_password' => $UM->changeOwnPassword($_POST),
            'update_preferences' => $UM->updateOwnPreferences($_POST),
            default => throw new InvalidArgumentException('Unknown action.'),
        };
        profile_flash('success', $result['message'] ?? 'Saved successfully.');
        profile_redirect($action === 'change_password' ? 'security' : ($action === 'update_preferences' ? 'preferences' : 'profile'));
    } catch (Throwable $e) {
        profile_flash('error', $e->getMessage());
        profile_redirect($action === 'change_password' ? 'security' : ($action === 'update_preferences' ? 'preferences' : 'profile'));
    }
}

$flash = $_SESSION['profile_flash'] ?? null;
unset($_SESSION['profile_flash']);

$sections = ['profile', 'security', 'preferences', 'activity'];
$section = in_array(($_GET['section'] ?? 'profile'), $sections, true) ? (string)$_GET['section'] : 'profile';

$me = $schema_ready ? $UM->getUser($uid) : tracs_get_user_by_id($conn, $uid);
$prefs = $schema_ready ? $UM->preferences($uid) : [];
$visualTheme = tracs_normalize_visual_theme($prefs['visual_theme'] ?? 'default');
$visualThemeAttr = tracs_visual_theme_data_value($visualTheme);
$activity = $schema_ready && $section === 'activity' ? $UM->ownActivity(50) : [];

$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'My Account';
$active_page = 'profile';

function profile_dt(mixed $value): string {
    return ($value && strtotime((string)$value)) ? date('d M Y H:i', strtotime((string)$value)) : '—';
}

function profile_badge(string $label, string $class): string {
    return '<span class="badge ' . esc($class) . '"><span class="badge-dot"></span>' . esc($label) . '</span>';
}

include __DIR__ . '/includes/header.php';
?>
<main class="main"><div class="main-inner profile-page">

<div class="topbar profile-topbar">
  <div class="topbar-left">
    <div class="page-title">My Account</div>
    <div class="page-sub">Profile, security, preferences, and recent account activity.</div>
  </div>
</div>

<?php if(!$schema_ready): ?>
  <div class="panel um-migration-panel">
    <div class="panel-head"><span class="panel-title">Migration Required</span></div>
    <div class="um-empty-state">
      <div class="empty-ic"><i data-lucide="database"></i></div>
      <div class="empty-t">Profile management schema is not installed yet</div>
      <div class="empty-sub">Run <code>config/migrations/2026_05_17_user_management.sql</code>, then reload this page.</div>
    </div>
  </div>
<?php else:
  $roleClass = tracs_user_role_badge_class($me['role_slug'] ?? 'agent');
  $statusClass = tracs_user_status_badge_class($me['status'] ?? 'active');
  $initials = tracs_user_initials($me['display_name'] ?? '', $me['email'] ?? 'U');
  $avatarUrl = tracs_user_avatar_url($me);
?>

<div class="profile-layout">
  <aside class="panel profile-card">
    <div class="profile-avatar-wrap" data-avatar-scope>
      <div class="profile-avatar tracs-avatar" data-avatar-user-id="<?=esc((string)$uid)?>" data-avatar-initials="<?=esc($initials)?>" style="<?=!empty($me['avatar_initials_color'])?'--um-avatar-bg:'.esc($me['avatar_initials_color']):''?>"><?php if($avatarUrl): ?><img src="<?=esc($avatarUrl)?>" alt="" loading="lazy" decoding="async"><?php else: ?><span><?=$initials?></span><?php endif; ?></div>
      <div class="profile-avatar-actions">
        <button type="button" class="btn btn-ghost btn-sm" data-avatar-upload data-avatar-user-id="<?=esc((string)$uid)?>"><i data-lucide="image-plus" class="icon-sm"></i>Change Photo</button>
        <button type="button" class="btn btn-ghost btn-sm" data-avatar-remove data-avatar-user-id="<?=esc((string)$uid)?>" <?=$avatarUrl ? '' : 'disabled'?>> <i data-lucide="trash-2" class="icon-sm"></i>Remove</button>
      </div>
    </div>
    <div class="profile-name"><?=esc($me['display_name'])?></div>
    <div class="profile-email"><?=esc($me['email'])?> · @<?=esc($me['username'])?></div>
    <div class="um-badge-row profile-badges">
      <?=profile_badge($me['role_name'] ?? 'Agent', $roleClass)?>
      <?=profile_badge($me['division_name'] ?: 'No Division', $me['division_name'] ? 'b-info' : 'b-done')?>
      <?=profile_badge(ucfirst($me['status'] ?? 'active'), $statusClass)?>
    </div>
    <div class="profile-facts">
      <div><span>Account Created</span><strong><?=profile_dt($me['created_at'] ?? null)?></strong></div>
      <div><span>Last Login</span><strong><?=profile_dt($me['last_login_at'] ?? null)?></strong></div>
      <div><span>Last Activity</span><strong><?=profile_dt($me['last_activity_at'] ?? null)?></strong></div>
    </div>
  </aside>

  <section class="profile-main">
    <div class="filter-bar profile-tabs">
      <a class="filter-tab <?=$section==='profile'?'active':''?>" href="?section=profile"><i data-lucide="user" class="icon-sm"></i>Profile</a>
      <a class="filter-tab <?=$section==='security'?'active':''?>" href="?section=security"><i data-lucide="lock-keyhole" class="icon-sm"></i>Security</a>
      <a class="filter-tab <?=$section==='preferences'?'active':''?>" href="?section=preferences"><i data-lucide="sliders-horizontal" class="icon-sm"></i>Preferences</a>
      <a class="filter-tab <?=$section==='activity'?'active':''?>" href="?section=activity"><i data-lucide="history" class="icon-sm"></i>Activity</a>
    </div>

    <?php if($section === 'profile'): ?>
      <form method="post" class="panel profile-section-panel">
        <?=csrf_input()?><input type="hidden" name="action" value="update_profile">
        <div class="panel-head"><span class="panel-title">Profile Details</span><span class="panel-meta">Editable self-service fields</span></div>
        <div class="profile-form-body">
          <div class="form-row"><div class="form-group"><label class="form-label">Full Name</label><input class="form-input" name="name" value="<?=esc($me['display_name'])?>" required></div><div class="form-group"><label class="form-label">Username</label><input class="form-input" name="username" value="<?=esc($me['username'])?>" required></div></div>
          <div class="form-row"><div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" value="<?=esc($me['email'])?>" required></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" value="<?=esc($me['phone'] ?? '')?>"></div></div>
          <div class="form-row"><div class="form-group"><label class="form-label">Avatar Color</label><input class="form-input" name="avatar_initials_color" value="<?=esc($me['avatar_initials_color'] ?? '')?>" placeholder="#2563eb"></div><div class="form-group"><label class="form-label">Position</label><input class="form-input" value="<?=esc($me['position'] ?? '')?>" readonly></div></div>
          <div class="profile-readonly-grid">
            <div><span>Role</span><strong><?=esc($me['role_name'] ?? 'Agent')?></strong></div>
            <div><span>Division</span><strong><?=esc($me['division_name'] ?: 'No Division')?></strong></div>
            <div><span>Status</span><strong><?=esc(ucfirst($me['status'] ?? 'active'))?></strong></div>
            <div><span>Created By</span><strong><?=esc(getUserDisplayName($conn, (int)($me['created_by'] ?? 0)))?></strong></div>
          </div>
        </div>
        <div class="modal-foot"><button type="submit" class="btn btn-primary"><i data-lucide="save" class="icon-sm"></i>Save Profile</button></div>
      </form>
    <?php endif; ?>

    <?php if($section === 'security'): ?>
      <form method="post" class="panel profile-section-panel" onsubmit="return profileValidatePassword()">
        <?=csrf_input()?><input type="hidden" name="action" value="change_password">
        <div class="panel-head"><span class="panel-title">Change Password</span><span class="panel-meta">Current password verification required</span></div>
        <div class="profile-form-body">
          <div class="form-group profile-password-field"><label class="form-label">Current Password</label><input class="form-input" type="password" name="current_password" id="profileCurrentPassword" required><button type="button" onclick="profileTogglePassword('profileCurrentPassword')"><i data-lucide="eye" class="icon-sm"></i></button></div>
          <div class="form-row">
            <div class="form-group profile-password-field"><label class="form-label">New Password</label><input class="form-input" type="password" name="new_password" id="profileNewPassword" required oninput="profilePasswordStrength()"><button type="button" onclick="profileTogglePassword('profileNewPassword')"><i data-lucide="eye" class="icon-sm"></i></button></div>
            <div class="form-group profile-password-field"><label class="form-label">Confirm New Password</label><input class="form-input" type="password" name="confirm_password" id="profileConfirmPassword" required><button type="button" onclick="profileTogglePassword('profileConfirmPassword')"><i data-lucide="eye" class="icon-sm"></i></button></div>
          </div>
          <div class="profile-strength"><div class="profile-strength-track"><span id="profileStrengthBar"></span></div><span id="profileStrengthText">Minimum 8 characters. Uppercase, lowercase, number, and symbol are recommended.</span></div>
          <div class="profile-readonly-grid">
            <div><span>Last Password Change</span><strong><?=profile_dt($me['last_password_change_at'] ?? null)?></strong></div>
            <div><span>Last Login</span><strong><?=profile_dt($me['last_login_at'] ?? null)?></strong></div>
          </div>
        </div>
        <div class="modal-foot"><button type="submit" class="btn btn-primary"><i data-lucide="lock-keyhole" class="icon-sm"></i>Update Password</button></div>
      </form>
    <?php endif; ?>

    <?php if($section === 'preferences'): ?>
      <form method="post" class="panel profile-section-panel">
        <?=csrf_input()?><input type="hidden" name="action" value="update_preferences">
        <div class="panel-head"><span class="panel-title">Preferences</span><span class="panel-meta">Stored in TRACS user preferences</span></div>
        <div class="profile-form-body">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Theme Preference</label><select class="form-select" name="theme_preference" id="profileThemePreference"><option value="auto" <?=($prefs['theme_preference'] ?? 'auto')==='auto'?'selected':''?>>System / Auto</option><option value="light" <?=($prefs['theme_preference'] ?? '')==='light'?'selected':''?>>Light</option><option value="dark" <?=($prefs['theme_preference'] ?? '')==='dark'?'selected':''?>>Dark</option></select></div>
            <div class="form-group" id="visualThemeField"><label class="form-label">Visual Theme</label><select class="form-select" name="visual_theme" id="profileVisualTheme"><option value="default" <?=$visualTheme==='default'?'selected':''?>>Default TRACS</option><option value="intercom_inspired" <?=$visualTheme==='intercom_inspired'?'selected':''?>>Intercom Inspired</option></select></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Notification Preference</label><select class="form-select" name="notification_preference"><option value="in_app" <?=($prefs['notification_preference'] ?? 'in_app')==='in_app'?'selected':''?>>In App</option><option value="email" <?=($prefs['notification_preference'] ?? '')==='email'?'selected':''?>>Email</option><option value="both" <?=($prefs['notification_preference'] ?? '')==='both'?'selected':''?>>Both</option><option value="muted" <?=($prefs['notification_preference'] ?? '')==='muted'?'selected':''?>>Muted</option></select></div>
            <div class="form-group"><label class="form-label">Default Landing Page</label><select class="form-select" name="default_landing_page"><option value="index.php" <?=($prefs['default_landing_page'] ?? 'index.php')==='index.php'?'selected':''?>>Dashboard</option><option value="cases.php" <?=($prefs['default_landing_page'] ?? '')==='cases.php'?'selected':''?>>Cases</option><option value="reminders.php" <?=($prefs['default_landing_page'] ?? '')==='reminders.php'?'selected':''?>>Reminders</option><option value="checklist.php" <?=($prefs['default_landing_page'] ?? '')==='checklist.php'?'selected':''?>>Checklist</option><option value="shift-reports.php" <?=($prefs['default_landing_page'] ?? '')==='shift-reports.php'?'selected':''?>>Shift Reports</option><option value="mom.php" <?=($prefs['default_landing_page'] ?? '')==='mom.php'?'selected':''?>>Meetings / MoM</option><option value="activity.php" <?=($prefs['default_landing_page'] ?? '')==='activity.php'?'selected':''?>>Activity Log</option></select></div>
          </div>
        </div>
        <div class="modal-foot"><button type="submit" class="btn btn-primary"><i data-lucide="save" class="icon-sm"></i>Save Preferences</button></div>
      </form>
    <?php endif; ?>

    <?php if($section === 'activity'): ?>
      <div class="panel profile-section-panel">
        <div class="panel-head"><span class="panel-title">Recent Account Activity</span><span class="panel-counter"><?=count($activity)?></span></div>
        <?php if(!$activity): ?>
          <div class="um-empty-state"><div class="empty-ic"><i data-lucide="history"></i></div><div class="empty-t">No recent profile activity</div></div>
        <?php else: ?>
          <div class="um-timeline">
            <?php foreach($activity as $log): ?>
              <div class="um-log-row">
                <div class="act-ic"><i data-lucide="history" class="icon-sm"></i></div>
                <div class="flex1"><div class="act-text"><strong><?=esc(str_replace('_',' ', $log['action']))?></strong><span>· <?=esc($log['target_name'] ?? 'Account')?></span></div><div class="act-desc">Actor: <?=esc($log['actor_name'] ?? 'System')?><?php if(!empty($log['reason'])): ?> · <?=esc($log['reason'])?><?php endif; ?></div><div class="act-time"><?=profile_dt($log['created_at'])?></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php endif; ?>
</div></main>

<?php if($schema_ready): ?>
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
<?php endif; ?>

<script>
function profileTogglePassword(id){
  const input=document.getElementById(id); if(!input)return;
  input.type = input.type === 'password' ? 'text' : 'password';
}
function profilePasswordStrength(){
  const value=document.getElementById('profileNewPassword')?.value || '';
  let score=0;
  if(value.length >= 8) score++;
  if(/[A-Z]/.test(value)) score++;
  if(/[a-z]/.test(value)) score++;
  if(/[0-9]/.test(value)) score++;
  if(/[^A-Za-z0-9]/.test(value)) score++;
  const bar=document.getElementById('profileStrengthBar');
  const text=document.getElementById('profileStrengthText');
  if(bar) bar.style.width=(score*20)+'%';
  if(text) text.textContent=score >= 5 ? 'Strong password.' : score >= 3 ? 'Good. Add more variety for a stronger password.' : 'Minimum 8 characters. Uppercase, lowercase, number, and symbol are recommended.';
}
function profileValidatePassword(){
  const n=document.getElementById('profileNewPassword')?.value || '';
  const c=document.getElementById('profileConfirmPassword')?.value || '';
  if(n.length < 8){ toast('Password must be at least 8 characters.','error'); return false; }
  if(n !== c){ toast('New password and confirmation do not match.','error'); return false; }
  return true;
}
<?php if(!empty($prefs['theme_preference'])): ?>
try {
  localStorage.setItem('tracs_theme_preference', <?=json_encode($prefs['theme_preference'])?>);
  localStorage.setItem('tracs-theme', <?=json_encode($prefs['theme_preference'])?>);
} catch(e) {}
<?php endif; ?>
try {
  localStorage.setItem('tracs_visual_theme_preference', <?=json_encode($visualTheme)?>);
  localStorage.setItem('tracs-visual-theme', <?=json_encode($visualTheme)?>);
  document.documentElement.setAttribute('data-visual-theme', <?=json_encode($visualThemeAttr)?>);
  document.documentElement.setAttribute('data-visual-theme-preference', <?=json_encode($visualTheme)?>);
} catch(e) {}
</script>

<?php if($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', () => toast(<?=json_encode($flash['message'])?>, <?=json_encode($flash['type'])?>));
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
