<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../modules/case/controller.php';
require_once __DIR__ . '/../modules/reminder/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';

$CC = new CaseController($conn, $uid);
$RC = new ReminderController($conn, $uid);
$TC = new AlertTickerController($conn, $uid);

$cases = array_map([$CC, 'formatCase'], $CC->getCases() ?: []);
$reminders = [];
foreach ($RC->getReminders() ?: [] as $r) {
    try {
        $reminders[] = $RC->formatReminder($r);
    } catch (Throwable $e) {
    }
}

$ticker_items = $TC->formatAlertsForTicker();
$critical_cases = count(array_filter($cases, fn($c) => ($c['priority'] ?? '') === 'critical'));
$overdue_reminders = count(array_filter($reminders, fn($r) => ($r['status'] ?? '') === 'Overdue'));
$critical_count = $critical_cases + $overdue_reminders;

$page_title = 'Infrastructure Pulse';
$active_page = 'infrastructure-pulse';
include __DIR__ . '/includes/header.php';

$infra_data_v = @filemtime(__DIR__ . '/assets/infrastructure-pulse-data.js') ?: time();
$infra_js_v = @filemtime(__DIR__ . '/assets/infrastructure-pulse.js') ?: time();
?>
<main class="main">
<div class="main-inner infra-page" data-infra-pulse-page>
  <div class="topbar infra-topbar">
    <div class="topbar-left">
      <div class="page-title">Infrastructure Pulse</div>
      <div class="page-sub">Prototype NOC panel for IDCloudHost datacenter health, realtime latency, uptime, and incident signal.</div>
    </div>
    <div class="infra-topbar__actions">
      <span class="infra-live-chip"><span aria-hidden="true"></span>Mock realtime / <b data-infra-generated-at>--:--:--</b></span>
      <a href="tv-mode.php" class="btn btn-ghost btn-sm"><i data-lucide="monitor-up" class="icon-sm"></i>TV Mode</a>
    </div>
  </div>

  <section class="infra-summary-grid" data-infra-summary aria-label="Infrastructure status summary"></section>

  <section class="infra-layout">
    <section class="panel infra-map-panel">
      <div class="panel-head">
        <div>
          <span class="panel-title">Vector Infrastructure Map</span>
          <div class="panel-meta">SEA overview + Jabodetabek cluster zoom</div>
        </div>
        <div class="panel-right">
          <span class="panel-meta">No browser pinging / mock telemetry only</span>
        </div>
      </div>
      <div class="infra-map-wrap">
        <svg class="infra-map" data-infra-map viewBox="0 0 960 620" role="img" aria-label="Vector radar style infrastructure map for Indonesia and Singapore datacenters">
          <defs>
            <pattern id="infraGrid" width="32" height="32" patternUnits="userSpaceOnUse">
              <path d="M32 0H0V32" fill="none" stroke="rgba(148,163,184,.18)" stroke-width="1"></path>
            </pattern>
            <radialGradient id="infraVignette" cx="50%" cy="44%" r="70%">
              <stop offset="0%" stop-color="rgba(34,211,238,.10)"></stop>
              <stop offset="58%" stop-color="rgba(11,17,24,0)"></stop>
              <stop offset="100%" stop-color="rgba(2,6,23,.72)"></stop>
            </radialGradient>
            <filter id="infraGlow" x="-80%" y="-80%" width="260%" height="260%">
              <feGaussianBlur stdDeviation="3.2" result="blur"></feGaussianBlur>
              <feMerge>
                <feMergeNode in="blur"></feMergeNode>
                <feMergeNode in="SourceGraphic"></feMergeNode>
              </feMerge>
            </filter>
          </defs>

          <rect class="infra-map-bg" x="0" y="0" width="960" height="620" rx="0"></rect>
          <rect class="infra-map-grid" x="0" y="0" width="960" height="620"></rect>
          <rect class="infra-map-vignette" x="0" y="0" width="960" height="620"></rect>

          <path class="infra-region" d="M134 194 C190 142 318 132 398 178 C470 220 462 306 385 330 C280 363 144 316 108 254 C95 232 106 210 134 194Z"></path>
          <path class="infra-region infra-region--sg" d="M640 112 C704 72 814 88 850 142 C884 192 836 250 759 252 C682 254 620 211 610 164 C606 142 616 126 640 112Z"></path>
          <text class="infra-region-label" x="254" y="154" text-anchor="middle">INDONESIA EDGE</text>
          <text class="infra-region-label" x="735" y="96" text-anchor="middle">SINGAPORE EDGE</text>

          <path class="infra-connection-shadow" d="M304 236 C420 118 570 96 716 168"></path>
          <path class="infra-connection" id="infraPulseMainLink" d="M304 236 C420 118 570 96 716 168"></path>
          <circle class="infra-flow-particle" r="4">
            <animateMotion dur="7s" repeatCount="indefinite" rotate="auto">
              <mpath href="#infraPulseMainLink"></mpath>
            </animateMotion>
          </circle>
          <circle class="infra-flow-particle" r="3">
            <animateMotion dur="9s" begin="2s" repeatCount="indefinite" rotate="auto">
              <mpath href="#infraPulseMainLink"></mpath>
            </animateMotion>
          </circle>
          <circle class="infra-flow-particle" r="3.5">
            <animateMotion dur="11s" begin="4s" repeatCount="indefinite" rotate="auto">
              <mpath href="#infraPulseMainLink"></mpath>
            </animateMotion>
          </circle>

          <g data-infra-overview-nodes></g>

          <rect class="infra-zoom-shell" x="48" y="332" width="510" height="244" rx="14"></rect>
          <text class="infra-zoom-title" x="72" y="362">JAKARTA / JABODETABEK CLUSTER ZOOM</text>
          <text class="infra-region-label--small" x="72" y="382">Separated markers for clustered Indonesian facilities</text>
          <path class="infra-region" d="M116 430 C190 356 332 348 462 407 C515 431 512 508 450 538 C328 594 140 548 100 480 C89 462 95 445 116 430Z"></path>

          <rect class="infra-zoom-shell" x="604" y="300" width="288" height="176" rx="14"></rect>
          <text class="infra-zoom-title" x="628" y="330">SINGAPORE DETAIL</text>
          <text class="infra-region-label--small" x="628" y="350">SG3 / EGH / SNG-3</text>

          <g data-infra-map-nodes></g>
        </svg>
        <aside class="infra-map-detail" data-infra-detail aria-live="polite"></aside>
      </div>
      <div class="infra-integration-note">
        <div>Future API hooks: <code>/api/infrastructure/status</code>, <code>/api/infrastructure/metrics</code>, <code>/api/infrastructure/events</code>, and SSE <code>/api/infrastructure/stream</code>.</div>
        <div>Integration placeholders: ticker announcements for active incidents, cases for incident ownership, reminders for maintenance windows, and TV Mode embedding via <code>renderInfrastructurePulseTVWidget()</code>.</div>
      </div>
    </section>

    <aside class="panel infra-metrics-panel">
      <div class="panel-head">
        <div>
          <span class="panel-title">Realtime Metrics</span>
          <div class="panel-meta">1s mock update loop / sparkline history</div>
        </div>
      </div>
      <div class="infra-metrics-list" data-infra-metrics aria-label="Datacenter metrics"></div>
    </aside>
  </section>

  <section class="panel infra-feed-panel">
    <div class="panel-head">
      <div>
        <span class="panel-title">Live Incident & Event Feed</span>
        <div class="panel-meta">Mock incidents shaped for future correlation</div>
      </div>
    </div>
    <div class="infra-feed-list" data-infra-feed aria-live="polite"></div>
  </section>
</div>
</main>

<script src="assets/infrastructure-pulse-data.js?v=<?=$infra_data_v?>"></script>
<script src="assets/infrastructure-pulse.js?v=<?=$infra_js_v?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
