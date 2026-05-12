<?php
/* TRACS — Header Include
   Requires: $page_title, $active_page, $user_email, $ticker_items, $critical_count */
$_un  = explode('@',$user_email??'op@tracs',2)[0];
$_av  = strtoupper(substr($_un,0,1));
$_cnt = (int)($critical_count??0);

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
<title>TRACS — <?=htmlspecialchars($page_title??'Dashboard')?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/tracs.css?v=<?=$_css_v?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Apply saved theme immediately to avoid flash
(function(){
  var t = localStorage.getItem('tracs-theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
})();
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
    <a href="activity.php" class="nav-item <?=$active_page==='activity'?'active':''?>">
      <i data-lucide="activity" class="icon-md"></i>
      <span class="nav-tip">Activity Log</span>
    </a>
    <div class="nav-div"></div>
    <a href="shift-reports.php" class="nav-item <?=$active_page==='shift-reports'?'active':''?>">
      <i data-lucide="clipboard-list" class="icon-md"></i>
      <span class="nav-tip">Shift Reports</span>
    </a>
    <a href="finance.php" class="nav-item <?=$active_page==='finance'?'active':''?>">
      <i data-lucide="circle-dollar-sign" class="icon-md"></i>
      <span class="nav-tip">Finance</span>
    </a>
    <a href="domains.php" class="nav-item <?=$active_page==='domains'?'active':''?>">
      <i data-lucide="globe" class="icon-md"></i>
      <span class="nav-tip">Domains</span>
    </a>
    <a href="cancellation_feedback.php" class="nav-item <?=$active_page==='feedback'?'active':''?>">
      <i data-lucide="message-square" class="icon-md"></i>
      <span class="nav-tip">Feedback</span>
    </a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-avatar" style="position:relative;">
      <?=$_av?>
      <span class="nav-tip"><?=htmlspecialchars($user_email??'')?></span>
    </div>
    <button class="theme-toggle" id="themeToggle" title="Toggle theme" onclick="tracsToggleTheme()">
      <!-- Sun icon (shown in dark mode) -->
      <i data-lucide="sun" class="icon-md ic-sun"></i>
      <!-- Moon icon (shown in light mode) -->
      <i data-lucide="moon" class="icon-md ic-moon"></i>
      <span class="nav-tip" style="white-space:nowrap" id="themeTip">Switch to Dark</span>
    </button>
    <a href="../auth/logout.php" class="logout-btn" title="Sign out">
      <i data-lucide="log-out" class="icon-md"></i>
    </a>
  </div>
</aside>

