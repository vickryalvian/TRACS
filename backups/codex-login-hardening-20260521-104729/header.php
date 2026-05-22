<?php
/* TRACS — Header Include
   Requires: $page_title, $active_page, $user_email, $ticker_items, $critical_count */
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
$_header_user = null;
if (isset($conn) && $conn instanceof mysqli && !empty($_SESSION['user_id'])) {
  $_header_user = tracs_get_user_by_id($conn, (int)$_SESSION['user_id']);
  $_can_um = tracs_user_can($conn, 'users.view') || tracs_user_can($conn, 'divisions.view') || tracs_user_can($conn, 'roles.view');
  $_can_monitoring = tracs_user_can($conn, 'tasks.view_own') || tracs_user_can($conn, 'tasks.monitor');
  $_can_dpc = tracs_user_can($conn, 'domain_price.view');
  if ($_header_user) {
    $_av = tracs_user_initials($_header_user['display_name'] ?? '', $_header_user['email'] ?? ($_un ?: 'U'));
    $_avatar_url = tracs_user_avatar_url($_header_user);
  }
}

// Build ticker HTML (doubled for seamless loop)
$_ti = $ticker_items??[['text'=>'All systems operational','class'=>'normal']];
$_th = ''; foreach($_ti as $t){$c=htmlspecialchars($t['class']??'normal');$x=htmlspecialchars($t['text']??'');$_th.="<span class=\"ticker-item {$c}\">{$x}</span>";}
$_th.=$_th; // double for infinite loop
$_css_v = @filemtime(__DIR__.'/../assets/tracs.css') ?: time();
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="assets/tracs.css?v=<?=$_css_v?>">
<?php if(in_array(($active_page??''), ['mom','dashboard'], true)): $_mom_css_v = @filemtime(__DIR__.'/../assets/mom-styles.css') ?: time(); ?>
<link rel="stylesheet" href="assets/mom-styles.css?v=<?=$_mom_css_v?>">
<?php endif; ?>
<?php if(in_array(($active_page??''), ['dashboard','infrastructure-pulse'], true)): $_infra_css_v = @filemtime(__DIR__.'/../assets/infrastructure-pulse.css') ?: time(); ?>
<link rel="stylesheet" href="assets/infrastructure-pulse.css?v=<?=$_infra_css_v?>">
<?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
window.TRACS_BUILD_INFO = <?=json_encode($_tracs_build_info, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
</script>
</head>
<body>
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
      <span class="nav-tip">Cases</span>
    </a>
    <a href="reminders.php" class="nav-item <?=$active_page==='reminders'?'active':''?>">
      <i data-lucide="bell" class="icon-md"></i>
      <span class="nav-tip">Reminders</span>
    </a>
    <a href="checklist.php" class="nav-item <?=$active_page==='checklist'?'active':''?>">
      <i data-lucide="list-checks" class="icon-md"></i>
      <span class="nav-tip">Checklist</span>
    </a>
    <a href="shift-reports.php" class="nav-item <?=$active_page==='shift-reports'?'active':''?>">
      <i data-lucide="clipboard-list" class="icon-md"></i>
      <span class="nav-tip">Shift Reports</span>
    </a>
    <?php if ($_can_monitoring): ?>
    <details class="nav-menu-wrap" id="tasksMonitoringNav" <?=in_array($active_page, ['monitoring', 'tasks'], true)?'open':''?>>
      <summary class="nav-item <?=in_array($active_page, ['monitoring', 'tasks'], true)?'active':''?>" aria-label="Tasks / Monitoring menu">
        <i data-lucide="kanban-square" class="icon-md"></i>
        <span class="nav-tip">Tasks / Monitoring</span>
      </summary>
      <div class="nav-submenu" role="menu" aria-label="Tasks / Monitoring">
        <a href="<?=tracs_user_can($conn, 'tasks.monitor') ? 'monitoring.php' : 'tasks.php'?>" role="menuitem" class="<?=in_array($active_page, ['monitoring', 'tasks'], true)?'active':''?>">
          <i data-lucide="kanban-square" class="icon-sm"></i>
          <span>Tasks & Monitoring</span>
        </a>
      </div>
    </details>
    <?php endif; ?>
    <a href="infrastructure-pulse.php" class="nav-item <?=$active_page==='infrastructure-pulse'?'active':''?>">
      <i data-lucide="radar" class="icon-md"></i>
      <span class="nav-tip">Infrastructure Pulse</span>
    </a>
    <details class="nav-menu-wrap" id="domainsNav" <?=in_array($active_page, ['domains', 'domain_price_crosscheck'], true)?'open':''?>>
      <summary class="nav-item <?=in_array($active_page, ['domains', 'domain_price_crosscheck'], true)?'active':''?>" aria-label="Domains menu">
        <i data-lucide="globe" class="icon-md"></i>
        <span class="nav-tip">Domains</span>
      </summary>
      <div class="nav-submenu" role="menu" aria-label="Domains">
        <?php if ($_can_dpc): ?>
        <a href="domain_price_crosscheck.php" role="menuitem" class="<?=$active_page==='domain_price_crosscheck'?'active':''?>">
          <i data-lucide="trending-up" class="icon-sm"></i>
          <span>Crosscheck Pricing</span>
        </a>
        <?php endif; ?>
        <a href="domains.php" role="menuitem" class="<?=$active_page==='domains'?'active':''?>">
          <i data-lucide="globe" class="icon-sm"></i>
          <span>Domain Transfer</span>
        </a>
      </div>
    </details>
    <a href="finance.php" class="nav-item <?=$active_page==='finance'?'active':''?>">
      <i data-lucide="circle-dollar-sign" class="icon-md"></i>
      <span class="nav-tip">Finance</span>
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
    <?php if(in_array((string)($_header_user['role_slug'] ?? ''), ['super_admin','admin','supervisor'], true)): ?>
    <a href="tv-mode.php" class="nav-item <?=$active_page==='tv-mode'?'active':''?>">
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
        <a href="profile.php?section=profile" role="menuitem"><i data-lucide="user" class="icon-sm"></i>My Profile</a>
        <a href="profile.php?section=security" role="menuitem"><i data-lucide="lock-keyhole" class="icon-sm"></i>Change Password</a>
        <a href="profile.php?section=preferences" role="menuitem"><i data-lucide="sliders-horizontal" class="icon-sm"></i>Preferences</a>
        <a href="../auth/logout.php" role="menuitem" class="danger"><i data-lucide="log-out" class="icon-sm"></i>Logout</a>
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
