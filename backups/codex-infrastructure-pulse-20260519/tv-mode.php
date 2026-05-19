<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';

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
      <img src="assets/img/logo.svg" alt="TRACS" class="tv-mode__brand-logo">
      <span class="tv-mode__live-dot" aria-hidden="true"></span>
    </div>
    <div class="tv-mode__status-strip">
      <div class="tv-ticker tv-ticker--status" aria-label="Smart ticker">
        <div class="tv-ticker__track"><div class="tv-ticker__items" data-tv-ticker><span>Connecting to operations feed</span></div></div>
        <div class="tv-ticker__state" data-tv-refresh-state aria-hidden="true">Live</div>
      </div>
      <div class="tv-updated-card"><span>Updated</span><strong data-tv-updated>Loading</strong></div>
      <div class="tv-shift-indicator" data-tv-shift-indicator>
        <span class="tv-shift-dot blue" data-tv-shift-dot aria-hidden="true"></span>
        <span class="tv-shift-info">
          <span class="tv-shift-slider" data-tv-shift-slider>
            <strong data-tv-shift>--</strong>
            <strong data-tv-greeting>Good Day</strong>
          </span>
        </span>
      </div>
    </div>
    <div class="tv-mode__actions">
      <div class="tv-mode__health tv-mode__health--stable" data-tv-health><span></span><strong>Connecting</strong></div>
      <div class="tv-theme-menu" data-tv-theme-menu>
        <button type="button" class="tv-mode__icon-btn" data-tv-theme-toggle title="Theme" aria-label="Theme" aria-haspopup="menu" aria-expanded="false">
          <i data-lucide="sun" class="ic-sun"></i>
          <i data-lucide="moon" class="ic-moon"></i>
        </button>
        <div class="tv-theme-menu__panel" role="menu" aria-label="Theme preference">
          <button type="button" role="menuitemradio" aria-checked="false" data-tv-theme-choice="light"><i data-lucide="sun"></i><span>Light</span></button>
          <button type="button" role="menuitemradio" aria-checked="false" data-tv-theme-choice="dark"><i data-lucide="moon"></i><span>Dark</span></button>
        </div>
      </div>
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
      <section class="tv-panel tv-pulse tv-time-panel">
        <div class="tv-panel__eyebrow">Date & Time</div>
        <div class="tv-time-panel__main">
          <div class="tv-pulse__score tv-time-panel__clock"><strong data-tv-clock>--:--:--</strong></div>
          <p class="tv-time-panel__date" data-tv-date>--</p>
        </div>
        <div class="tv-holiday-panel is-loading" data-tv-holiday>
          <div class="tv-holiday-panel__icon" data-tv-holiday-icon aria-hidden="true"></div>
          <div class="tv-holiday-panel__copy">
            <span>Tanggal Merah</span>
            <strong data-tv-holiday-title>Loading holiday calendar</strong>
            <p data-tv-holiday-subtitle>Checking Indonesian public holidays</p>
          </div>
          <div class="tv-holiday-panel__badges">
            <em data-tv-holiday-countdown>Syncing</em>
            <b data-tv-holiday-type>Holiday</b>
          </div>
        </div>
      </section>

      <section class="tv-panel">
        <div class="tv-panel__head"><div><span>Handover</span><h2>Shift Summary</h2></div></div>
        <div class="tv-stack" data-tv-handover></div>
      </section>

      <section class="tv-panel">
        <div class="tv-panel__head"><div><span>Retention</span><h2>Cancellation Feedback This Week</h2></div></div>
        <div class="tv-stack" data-tv-ops-watch></div>
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
</main>

<script src="assets/tv-mode.js?v=<?=$tv_js_v?>"></script>
<script>lucide.createIcons();</script>
</body>
</html>
