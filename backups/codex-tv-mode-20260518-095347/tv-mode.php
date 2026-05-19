<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/user_management.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$viewer = tracs_get_user_by_id($conn, $uid);
$allowed_roles = ['super_admin', 'admin', 'supervisor'];
if (!$viewer || !in_array((string)($viewer['role_slug'] ?? ''), $allowed_roles, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$css_v = @filemtime(__DIR__ . '/assets/tracs.css') ?: time();
$tv_css_v = @filemtime(__DIR__ . '/assets/tv-mode.css') ?: time();
$tv_js_v = @filemtime(__DIR__ . '/assets/tv-mode.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?= csrf_meta_tag() ?>
<title>TRACS TV Mode</title>
<?php include __DIR__ . '/includes/theme_bootstrap.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/tracs.css?v=<?=$css_v?>">
<link rel="stylesheet" href="assets/tv-mode.css?v=<?=$tv_css_v?>">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="tv-mode-body">
<main class="tv-mode" data-tv-mode>
  <header class="tv-mode__bar">
    <div class="tv-mode__brand">
      <span class="tv-mode__live-dot" aria-hidden="true"></span>
      <div>
        <p>TRACS</p>
        <h1>TV Mode</h1>
      </div>
    </div>
    <div class="tv-mode__status-strip">
      <div><span>Date</span><strong data-tv-date>--</strong></div>
      <div><span>Time</span><strong data-tv-clock>--:--:--</strong></div>
      <div><span>Shift</span><strong data-tv-shift>--</strong></div>
      <div><span>Updated</span><strong data-tv-updated>Loading</strong></div>
    </div>
    <div class="tv-mode__actions">
      <div class="tv-mode__health tv-mode__health--stable" data-tv-health><span></span><strong>Connecting</strong></div>
      <button type="button" class="tv-mode__icon-btn" data-tv-pause title="Pause rotation" aria-label="Pause rotation"><i data-lucide="pause"></i></button>
      <button type="button" class="tv-mode__icon-btn" data-tv-fullscreen title="Fullscreen" aria-label="Fullscreen"><i data-lucide="maximize"></i></button>
      <a class="tv-mode__icon-btn" href="index.php" title="Dashboard" aria-label="Back to dashboard"><i data-lucide="layout-dashboard"></i></a>
    </div>
  </header>

  <section class="tv-mode__grid" aria-live="polite">
    <div class="tv-mode__main">
      <section class="tv-spotlight tv-panel" data-tv-spotlight>
        <div class="tv-panel__eyebrow">Spotlight</div>
        <div class="tv-spotlight__content">
          <p class="tv-spotlight__type">Loading signal</p>
          <h2>Preparing wall display</h2>
          <p>Collecting operational data.</p>
          <span>Connecting...</span>
        </div>
      </section>

      <section class="tv-metrics" data-tv-metrics></section>

      <div class="tv-mode__split">
        <section class="tv-panel">
          <div class="tv-panel__head">
            <div><span>Watchtower</span><h2>Active Cases</h2></div>
            <strong data-tv-case-count>0</strong>
          </div>
          <div class="tv-list tv-list--cases" data-tv-cases></div>
        </section>
        <section class="tv-panel">
          <div class="tv-panel__head">
            <div><span>Queue</span><h2>Reminders & Checklist</h2></div>
            <strong data-tv-queue-count>0</strong>
          </div>
          <div class="tv-list" data-tv-queue></div>
        </section>
      </div>
    </div>

    <aside class="tv-mode__side">
      <section class="tv-panel tv-pulse">
        <div class="tv-panel__eyebrow">Ops Pulse</div>
        <div class="tv-pulse__score"><strong data-tv-score>--</strong><span>/100</span></div>
        <div class="tv-pulse__bar"><span data-tv-score-bar></span></div>
        <p data-tv-score-copy>Waiting for data.</p>
      </section>

      <section class="tv-panel">
        <div class="tv-panel__head"><div><span>Handover</span><h2>Shift Summary</h2></div></div>
        <div class="tv-stack" data-tv-handover></div>
      </section>

      <section class="tv-panel">
        <div class="tv-panel__head"><div><span>MoM</span><h2>Meeting Panel</h2></div></div>
        <div class="tv-stack" data-tv-meetings></div>
      </section>

      <section class="tv-panel">
        <div class="tv-panel__head"><div><span>Pulse</span><h2>Latest Activity</h2></div></div>
        <div class="tv-stack" data-tv-activity></div>
      </section>

      <section class="tv-panel">
        <div class="tv-panel__head"><div><span>Watchlist</span><h2>Operational Edges</h2></div></div>
        <div class="tv-intel" data-tv-intel></div>
      </section>
    </aside>
  </section>

  <footer class="tv-ticker" aria-label="Smart ticker">
    <div class="tv-ticker__label">Smart Ticker</div>
    <div class="tv-ticker__track"><div class="tv-ticker__items" data-tv-ticker><span>Connecting to operations feed</span></div></div>
    <div class="tv-ticker__state" data-tv-refresh-state>Live</div>
  </footer>
</main>

<script src="assets/tv-mode.js?v=<?=$tv_js_v?>"></script>
<script>lucide.createIcons();</script>
</body>
</html>
