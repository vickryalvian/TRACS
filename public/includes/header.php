<?php
/* TRACS — Header Include
   Requires: $page_title, $active_page, $user_email, $ticker_items, $critical_count */
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../core/security/csrf.php';
require_once __DIR__ . '/../../core/build_signature.php';
if (isset($conn) && $conn instanceof mysqli) {
  require_once __DIR__ . '/../../core/user_management.php';
}
$_tracs_build_info = tracs_build_public_payload();
$_un  = explode('@',$user_email??'op@tracs',2)[0];
$_av  = strtoupper(substr($_un,0,1));
$_avatar_url = '';
$_cnt = (int)($critical_count??0);
$_can_um = false;
$_can_monitoring = false;
$_can_dpc = false;
$_can_shifts = false;
$_can_finance = true;
$_can_domains = true;
$_can_checklist = true;
$_is_super_admin = false;
$_header_user = null;
$_header_preferences = [];
$_tracs_visual_theme_preference = '';
if (isset($conn) && $conn instanceof mysqli && !empty($_SESSION['user_id'])) {
  $_header_user = tracs_get_user_by_id($conn, (int)$_SESSION['user_id']);
  $_header_preferences = tracs_get_user_preferences($conn, (int)$_SESSION['user_id']);
  $_tracs_visual_theme_preference = tracs_normalize_visual_theme($_header_preferences['visual_theme'] ?? 'default');
  $_can_um = tracs_user_can($conn, 'users.view') || tracs_user_can($conn, 'divisions.view') || tracs_user_can($conn, 'roles.view');
  $_can_monitoring = tracs_user_can($conn, 'tasks.view_own') || tracs_user_can($conn, 'tasks.monitor');
  $_can_dpc = tracs_user_can($conn, 'domain_price.view');
  $_can_shifts = tracs_user_can($conn, 'shifts.view');
  $_can_finance = tracs_user_can($conn, 'finance.view');
  $_can_domains = tracs_user_can($conn, 'domains.view');
  $_can_checklist = tracs_user_can($conn, 'checklist.view');
  $_is_super_admin = (string)($_header_user['role_slug'] ?? '') === 'super_admin';
  if ($_header_user) {
    $_av = tracs_user_initials($_header_user['display_name'] ?? '', $_header_user['email'] ?? ($_un ?: 'U'));
    $_avatar_url = tracs_user_avatar_url($_header_user);
  }
}

if (!function_exists('tracs_ticker_context_class')) {
  function tracs_ticker_context_class(string $text): string {
    return preg_match('/\b(holiday|public holiday|hari libur|hari raya|waisak|vesak|idul|eid|nyepi|imlek|natal)\b/i', $text) ? ' holiday' : '';
  }
}

// Build ticker HTML (doubled for seamless loop)
$_ti = $ticker_items??[['text'=>'All systems operational','class'=>'normal']];
$_th = ''; foreach($_ti as $t){$rawText=(string)($t['text']??'');$c=htmlspecialchars(trim((string)($t['class']??'normal')).tracs_ticker_context_class($rawText));$x=htmlspecialchars($rawText);$_th.="<span class=\"ticker-item {$c}\">{$x}</span>";}
$_th.=$_th; // double for infinite loop
$_css_v = @filemtime(__DIR__.'/../assets/tracs.css') ?: time();
$_date_range_css_v = @filemtime(__DIR__.'/../assets/tracs-date-range-picker.css') ?: time();
$_spacing_css_v = @filemtime(__DIR__.'/../assets/tracs-spacing.css') ?: time();

if (!function_exists('tracs_sidebar_active')) {
  function tracs_sidebar_active(?string $active_page, array $pages): bool {
    return in_array((string)$active_page, $pages, true);
  }
}
if (!function_exists('tracs_sidebar_link')) {
  function tracs_sidebar_link(array $item, ?string $active_page, string $icon_class = 'icon-md'): void {
    if (array_key_exists('visible', $item) && !$item['visible']) return;
    $pages = $item['active_pages'] ?? [$item['active_page'] ?? ''];
    $active = tracs_sidebar_active($active_page, $pages);
    $href = htmlspecialchars((string)($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars((string)($item['icon'] ?? 'circle'), ENT_QUOTES, 'UTF-8');
    $class = $active ? ' active' : '';
    echo '<a href="' . $href . '" role="' . ($icon_class === 'icon-sm' ? 'menuitem' : 'link') . '" class="' . ($icon_class === 'icon-sm' ? trim($class) : 'nav-item' . $class) . '">';
    echo '<i data-lucide="' . $icon . '" class="' . htmlspecialchars($icon_class, ENT_QUOTES, 'UTF-8') . '"></i>';
    echo $icon_class === 'icon-sm' ? '<span>' . $label . '</span>' : '<span class="nav-tip">' . $label . '</span>';
    echo '</a>';
  }
}

$_task_monitoring_items = [
  [
    'label' => 'Case / Task Monitoring',
    'href' => (isset($conn) && $conn instanceof mysqli && tracs_user_can($conn, 'tasks.monitor')) ? 'monitoring.php' : 'tasks.php',
    'icon' => 'kanban-square',
    'active_pages' => ['monitoring', 'tasks'],
    'visible' => $_can_monitoring,
  ],
  [
    'label' => 'Finance',
    'href' => 'finance.php',
    'icon' => 'circle-dollar-sign',
    'active_page' => 'finance',
    'visible' => $_can_finance,
  ],
  [
    'label' => 'Domain Transfer Log',
    'href' => 'domains.php',
    'icon' => 'globe',
    'active_page' => 'domains',
    'visible' => $_can_domains,
  ],
  [
    'label' => 'Domain Pricing Crosscheck',
    'href' => 'domain-price-crosscheck.php',
    'icon' => 'trending-up',
    'active_page' => 'domain_price_crosscheck',
    'visible' => $_can_dpc,
  ],
  [
    'label' => 'Checklist',
    'href' => 'checklist.php',
    'icon' => 'list-checks',
    'active_page' => 'checklist',
    'visible' => $_can_checklist,
  ],
  [
    'label' => 'Shifting Assignment',
    'href' => 'shifting-assignment.php',
    'icon' => 'calendar-range',
    'active_page' => 'shifting-assignment',
    'visible' => $_can_shifts,
  ],
];
$_task_monitoring_pages = [];
foreach ($_task_monitoring_items as $_task_monitoring_item) {
  if (array_key_exists('visible', $_task_monitoring_item) && !$_task_monitoring_item['visible']) continue;
  $_task_monitoring_pages = array_merge($_task_monitoring_pages, $_task_monitoring_item['active_pages'] ?? [$_task_monitoring_item['active_page'] ?? '']);
}
$_task_monitoring_active = tracs_sidebar_active($active_page ?? '', $_task_monitoring_pages);
$_show_task_monitoring = !empty(array_filter($_task_monitoring_items, fn($item) => !array_key_exists('visible', $item) || $item['visible']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?= csrf_meta_tag() ?>
<!-- TRACS System by Vickry -->
<meta name="author" content="<?=htmlspecialchars(TRACS_BUILD_OWNER, ENT_QUOTES, 'UTF-8')?>">
<meta name="application-name" content="TRACS">
<meta name="tracs-build-owner" content="<?=htmlspecialchars(TRACS_BUILD_OWNER, ENT_QUOTES, 'UTF-8')?>">
<meta name="tracs-build-version" content="<?=htmlspecialchars(TRACS_BUILD_VERSION, ENT_QUOTES, 'UTF-8')?>">
<title>TRACS — <?=htmlspecialchars($page_title??'Dashboard')?></title>
<?php include __DIR__ . '/theme_bootstrap.php'; ?>
<?php if(($active_page??'') === 'calendar'): ?>
<script>document.documentElement.setAttribute('data-theme','dark');</script>
<?php endif; ?>
<link rel="icon" type="image/png" href="assets/images/task-monitoring-tab-icon.png">
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/tracs.css?v=<?=$_css_v?>">
<link rel="stylesheet" href="assets/tracs-date-range-picker.css?v=<?=$_date_range_css_v?>">
<?php if(in_array(($active_page??''), ['mom','dashboard'], true)): $_mom_css_v = @filemtime(__DIR__.'/../assets/mom-styles.css') ?: time(); ?>
<link rel="stylesheet" href="assets/mom-styles.css?v=<?=$_mom_css_v?>">
<?php endif; ?>
<?php if(in_array(($active_page??''), ['dashboard','infrastructure-pulse'], true)): $_infra_css_v = @filemtime(__DIR__.'/../assets/infrastructure-pulse.css') ?: time(); ?>
<link rel="stylesheet" href="assets/infrastructure-pulse.css?v=<?=$_infra_css_v?>">
<?php endif; ?>
<?php if(($active_page??'') === 'domain_price_crosscheck'): $_dpc_css_v = @filemtime(__DIR__.'/../assets/domain-price-crosscheck.css') ?: time(); ?>
<link rel="stylesheet" href="assets/domain-price-crosscheck.css?v=<?=$_dpc_css_v?>">
<?php endif; ?>
<?php if(($active_page??'') === 'shifting-assignment'): $_shift_assignment_css_v = @filemtime(__DIR__.'/../assets/shifting-assignment.css') ?: time(); ?>
<link rel="stylesheet" href="assets/shifting-assignment.css?v=<?=$_shift_assignment_css_v?>">
<?php endif; ?>
<link rel="stylesheet" href="assets/tracs-spacing.css?v=<?=$_spacing_css_v?>">
<?php foreach (($calendar_styles ?? []) as $_calendar_style): ?>
<link rel="stylesheet" href="<?=htmlspecialchars((string)$_calendar_style, ENT_QUOTES, 'UTF-8')?>">
<?php endforeach; ?>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
window.TRACS_BUILD_INFO = <?=json_encode($_tracs_build_info, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
</script>
</head>
<body data-tracs-page="<?=htmlspecialchars((string)($active_page ?? ''), ENT_QUOTES, 'UTF-8')?>">
<div class="shell">

<!-- TICKER -->
<div class="ticker-bar">
  <div class="ticker-live"><span class="ticker-dot"></span>LIVE</div>
  <div class="ticker-track"><div class="ticker-scroll"><?=$_th?></div></div>
  <button class="ticker-btn" onclick="openModal('ticker')">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
    MANAGE
  </button>
</div>

<div class="body-row">

<!-- SIDEBAR -->
<aside class="sidebar">
  
  <nav class="sidebar-nav">
    <a href="index.php" class="nav-item <?=$active_page==='dashboard'?'active':''?>">
      <i data-lucide="layout-dashboard" class="icon-md"></i>
      <span class="nav-tip">Dashboard</span>
      <?php if($_cnt>0):?><span class="nav-badge"><?=min($_cnt,99)?></span><?php endif;?>
    </a>
    <a href="cases.php" class="nav-item <?=$active_page==='cases'?'active':''?>">
      <i data-lucide="briefcase" class="icon-md"></i>
      <span class="nav-tip">Case Management</span>
    </a>
    <a href="reminders.php" class="nav-item <?=$active_page==='reminders'?'active':''?>">
      <i data-lucide="bell" class="icon-md"></i>
      <span class="nav-tip">Reminders</span>
    </a>
    <a href="calendar.php" class="nav-item <?=$active_page==='calendar'?'active':''?>">
      <i data-lucide="calendar-range" class="icon-md"></i>
      <span class="nav-tip">Calendar</span>
    </a>
    <a href="shift-reports.php" class="nav-item <?=$active_page==='shift-reports'?'active':''?>">
      <i data-lucide="clipboard-list" class="icon-md"></i>
      <span class="nav-tip">Shift Reports</span>
    </a>
    <?php if ($_show_task_monitoring): ?>
    <details class="nav-menu-wrap" id="tasksMonitoringNav">
      <summary class="nav-item <?=$_task_monitoring_active?'active':''?>" aria-label="Tasks & Monitoring menu">
        <i data-lucide="kanban-square" class="icon-md"></i>
        <span class="nav-tip">Tasks & Monitoring</span>
      </summary>
      <div class="nav-submenu" role="menu" aria-label="Tasks & Monitoring">
        <?php foreach ($_task_monitoring_items as $_task_monitoring_item) tracs_sidebar_link($_task_monitoring_item, $active_page ?? '', 'icon-sm'); ?>
      </div>
    </details>
    <?php endif; ?>
    <a href="infrastructure-pulse.php" class="nav-item <?=$active_page==='infrastructure-pulse'?'active':''?>">
      <i data-lucide="radar" class="icon-md"></i>
      <span class="nav-tip">Infrastructure Pulse</span>
    </a>
    <div class="nav-div"></div>
    <a href="mom.php" class="nav-item <?=$active_page==='mom'?'active':''?>">
      <i data-lucide="calendar-days" class="icon-md"></i>
      <span class="nav-tip">Meetings / MoM</span>
    </a>
    <a href="cancellation_feedback.php" class="nav-item <?=$active_page==='feedback'?'active':''?>">
      <i data-lucide="message-square" class="icon-md"></i>
      <span class="nav-tip">Feedback</span>
    </a>
    <button type="button" class="nav-item" onclick="openModal('ticker')" title="Ticker / Alerts">
      <i data-lucide="megaphone" class="icon-md"></i>
      <span class="nav-tip">Ticker / Alerts</span>
    </button>
    <div class="nav-div"></div>
    <a href="activity.php" class="nav-item <?=$active_page==='activity'?'active':''?>">
      <i data-lucide="activity" class="icon-md"></i>
      <span class="nav-tip">Activity Log</span>
    </a>
    <?php if($_is_super_admin): ?>
    <a href="server-health.php" class="nav-item <?=$active_page==='server-health'?'active':''?>">
      <i data-lucide="server-cog" class="icon-md"></i>
      <span class="nav-tip">Server Health & Logs</span>
    </a>
    <?php endif; ?>
    <?php if(in_array((string)($_header_user['role_slug'] ?? ''), ['super_admin','admin','supervisor'], true)): ?>
    <a href="tv-mode.php" target="_blank" rel="noopener noreferrer" class="nav-item <?=$active_page==='tv-mode'?'active':''?>">
      <i data-lucide="monitor-up" class="icon-md"></i>
      <span class="nav-tip">TV Mode</span>
    </a>
    <?php endif; ?>
    <?php if($_can_um): ?>
    <details class="nav-menu-wrap" id="userManagementNav">
      <summary class="nav-item <?=in_array($active_page, ['user-management','intern-management'], true)?'active':''?>" aria-label="User Management menu">
        <i data-lucide="users-round" class="icon-md"></i>
        <span class="nav-tip">User Management</span>
      </summary>
      <div class="nav-submenu" role="menu" aria-label="User Management">
        <a href="user-management.php" role="menuitem" class="<?=$active_page==='user-management'?'active':''?>">
          <i data-lucide="users-round" class="icon-sm"></i>
          <span>User Management</span>
        </a>
        <a href="intern-management.php" role="menuitem" class="<?=$active_page==='intern-management'?'active':''?>">
          <i data-lucide="graduation-cap" class="icon-sm"></i>
          <span>Intern Management</span>
        </a>
      </div>
    </details>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <details class="user-menu-wrap" id="userMenuWrap">
      <summary class="user-avatar tracs-avatar" style="position:relative;<?=!empty($_header_user['avatar_initials_color'])?'--um-avatar-bg:'.htmlspecialchars((string)$_header_user['avatar_initials_color'], ENT_QUOTES, 'UTF-8'):''?>" aria-label="User menu" data-avatar-user-id="<?=htmlspecialchars((string)($_header_user['id'] ?? ''), ENT_QUOTES, 'UTF-8')?>" data-avatar-initials="<?=htmlspecialchars($_av, ENT_QUOTES, 'UTF-8')?>">
        <?php if($_avatar_url !== ''): ?><img src="<?=htmlspecialchars($_avatar_url, ENT_QUOTES, 'UTF-8')?>" alt="" loading="lazy" decoding="async"><?php else: ?><span><?=$_av?></span><?php endif; ?>
        <span class="nav-tip"><?=htmlspecialchars($user_email??'')?></span>
      </summary>
      <div class="user-menu" role="menu" aria-label="User account menu">
        <div class="user-menu-head">
          <strong><?=htmlspecialchars($_header_user['display_name'] ?? ($_SESSION['user_name'] ?? $user_email ?? 'User'))?></strong>
          <span><?=htmlspecialchars($_header_user['email'] ?? $user_email ?? '')?></span>
        </div>
        <a href="profile.php?section=profile" role="menuitem"><i data-lucide="user" class="icon-sm"></i>Profile / Account</a>
        <a href="profile.php?section=preferences" role="menuitem"><i data-lucide="settings" class="icon-sm"></i>Settings</a>
        <a href="profile.php?section=security" role="menuitem"><i data-lucide="lock-keyhole" class="icon-sm"></i>Change Password</a>
        <form action="/auth/logout.php" method="post" class="user-menu-logout">
          <?=csrf_input()?>
          <button type="submit" role="menuitem" class="danger"><i data-lucide="log-out" class="icon-sm"></i>Logout</button>
        </form>
      </div>
    </details>
    <div class="theme-menu-wrap" id="themeMenuWrap">
      <button class="theme-toggle" id="themeToggle" type="button" title="Theme" aria-label="Theme" aria-haspopup="menu" aria-expanded="false">
        <i data-lucide="sun" class="icon-md ic-sun"></i>
        <i data-lucide="moon" class="icon-md ic-moon"></i>
        <span class="nav-tip" style="white-space:nowrap" id="themeTip">Theme</span>
      </button>
      <div class="theme-menu" id="themeMenu" role="menu" aria-label="Theme preference">
        <button type="button" class="theme-option" role="menuitemradio" aria-checked="false" data-theme-choice="light">
          <i data-lucide="sun" class="icon-sm"></i>
          <span>Light Mode</span>
          <i data-lucide="check" class="icon-sm theme-option-check"></i>
        </button>
        <button type="button" class="theme-option" role="menuitemradio" aria-checked="false" data-theme-choice="dark">
          <i data-lucide="moon" class="icon-sm"></i>
          <span>Dark Mode</span>
          <i data-lucide="check" class="icon-sm theme-option-check"></i>
        </button>
        <button type="button" class="theme-option" role="menuitemradio" aria-checked="false" data-theme-choice="auto">
          <i data-lucide="monitor" class="icon-sm"></i>
          <span>System Default</span>
          <i data-lucide="check" class="icon-sm theme-option-check"></i>
        </button>
      </div>
    </div>
  </div>
</aside>
