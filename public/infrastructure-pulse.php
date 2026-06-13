<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
tracs_require_page_permission($conn, 'dashboard.view');
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
      <span class="infra-live-chip" title="Simulated telemetry only"><span aria-hidden="true"></span>Mock realtime / <b data-infra-generated-at>--:--:--</b></span>
      <button type="button" class="btn btn-ghost btn-sm" data-infra-manage-open><i data-lucide="server-cog" class="icon-sm"></i>Manage Servers</button>
      <a href="tv-mode.php" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm"><i data-lucide="monitor-up" class="icon-sm"></i>TV Mode</a>
    </div>
  </div>

  <section class="infra-summary-grid" data-infra-summary aria-label="Infrastructure status summary"></section>

  <section class="infra-layout">
    <section class="panel infra-report-panel">
      <div class="panel-head">
        <div>
          <span class="panel-title">Infrastructure Summary</span>
          <div class="panel-meta">NOC handover view / mock telemetry</div>
        </div>
        <div class="panel-right">
          <span class="panel-meta">No browser pinging / API-ready snapshot</span>
        </div>
      </div>
      <div class="infra-report-wrap" data-infra-report aria-live="polite"></div>
    </section>

    <aside class="panel infra-metrics-panel">
      <div class="panel-head">
        <div>
          <span class="panel-title">Realtime Metrics</span>
          <div class="panel-meta">Risk-sorted view / high latency &amp; packet loss first</div>
        </div>
      </div>
      <div class="infra-metrics-list" data-infra-metrics aria-label="Datacenter metrics"></div>
    </aside>
  </section>

  <section class="infra-graph-grid" data-infra-graphs aria-label="Infrastructure monitoring graphs"></section>

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

<div class="infra-modal" data-infra-server-modal hidden>
  <div class="infra-modal__backdrop" data-infra-manage-close></div>
  <section class="infra-modal__panel" role="dialog" aria-modal="true" aria-labelledby="infraServerModalTitle">
    <div class="infra-modal__head">
      <div>
        <span class="panel-title">Server Registry</span>
        <h2 id="infraServerModalTitle">Server Registry &amp; Monitoring Setup</h2>
        <p>Prepare real VPS-side monitoring targets while keeping mock data available for demos and TV Mode testing.</p>
      </div>
      <button type="button" class="btn btn-ghost btn-icon" data-infra-manage-close aria-label="Close"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <div class="infra-modal__tabs" role="tablist" aria-label="Server registry sections">
      <button type="button" class="is-active" data-infra-modal-tab="add" role="tab" aria-selected="true">Add Server</button>
      <button type="button" data-infra-modal-tab="servers" role="tab" aria-selected="false">Current Servers</button>
      <button type="button" data-infra-modal-tab="settings" role="tab" aria-selected="false">Monitoring Settings</button>
    </div>
    <div class="infra-modal__body">
      <section class="infra-modal__pane is-active" data-infra-modal-pane="add">
        <form class="infra-server-form" data-infra-server-form novalidate>
          <div class="infra-method-grid" role="radiogroup" aria-label="Monitoring method">
            <label class="infra-method-card is-active" data-infra-method-card="icmp">
              <input type="radio" name="method" value="icmp" checked>
              <span><i data-lucide="radio-tower" class="icon-sm"></i>Network Ping</span>
              <em>ICMP reachability, latency, and packet loss. Firewall rules can block ping.</em>
            </label>
            <label class="infra-method-card" data-infra-method-card="tcp">
              <input type="radio" name="method" value="tcp">
              <span><i data-lucide="plug-zap" class="icon-sm"></i>Port Check</span>
              <em>Checks a specific service port such as 22, 80, 443, or 3306.</em>
            </label>
            <label class="infra-method-card" data-infra-method-card="http">
              <input type="radio" name="method" value="http">
              <span><i data-lucide="globe-2" class="icon-sm"></i>Health Endpoint</span>
              <em>Recommended for web services, APIs, dashboards, and status URLs.</em>
            </label>
            <label class="infra-method-card" data-infra-method-card="mock">
              <input type="radio" name="method" value="mock">
              <span><i data-lucide="flask-conical" class="icon-sm"></i>Demo Data</span>
              <em>Session-only mock telemetry for local development and TV Mode testing.</em>
            </label>
          </div>

          <div class="infra-form-section">
            <div class="infra-form-section__head">
              <strong>Identity</strong>
              <span>Required for every monitoring target.</span>
            </div>
            <div class="infra-server-form__grid">
              <label><span>Server name *</span><input class="form-input" name="name" data-required-base placeholder="Jakarta Edge 1" autocomplete="off"></label>
              <label><span>Code *</span><input class="form-input" name="code" data-required-base maxlength="8" placeholder="JKT1" autocomplete="off"></label>
              <label><span>Region *</span><input class="form-input" name="region" data-required-base placeholder="Jabodetabek" autocomplete="off"></label>
              <label><span>Country *</span><input class="form-input" name="country" data-required-base placeholder="Indonesia" autocomplete="off"></label>
              <label><span>Provider *</span><input class="form-input" name="provider" data-required-base placeholder="IDCloudHost" autocomplete="off"></label>
            </div>
          </div>

          <div class="infra-form-section" data-method-section="real">
            <div class="infra-form-section__head">
              <strong>Monitoring Target</strong>
              <span>Real checks will run from the TRACS VPS backend, not browser JavaScript.</span>
            </div>
            <div class="infra-server-form__grid">
              <label data-method-field="icmp tcp"><span>IP address or hostname *</span><input class="form-input" name="target_host" placeholder="server.example.com" autocomplete="off"></label>
              <label data-method-field="tcp"><span>Port *</span><input class="form-input" name="target_port" type="number" min="1" max="65535" step="1" placeholder="443"></label>
              <label data-method-field="http"><span>Health check URL *</span><input class="form-input" name="health_url" type="url" placeholder="https://example.com/health" autocomplete="off"></label>
              <label data-method-field="http"><span>Expected status</span><input class="form-input" name="expected_status" type="number" min="100" max="599" step="1" value="200"></label>
              <label data-method-field="http"><span>Expected keyword</span><input class="form-input" name="expected_keyword" placeholder="ok" autocomplete="off"></label>
              <label data-method-field="icmp"><span>Packet count</span><input class="form-input" name="packet_count" type="number" min="1" max="10" step="1" value="4"></label>
              <label data-method-field="icmp tcp http"><span>Timeout seconds</span><input class="form-input" name="timeout_seconds" type="number" min="1" max="30" step="1" value="5"></label>
              <label data-method-field="icmp tcp http"><span>Check interval seconds</span><input class="form-input" name="interval_seconds" type="number" min="30" max="3600" step="5" value="60"></label>
            </div>
          </div>

          <div class="infra-form-section" data-method-section="mock">
            <div class="infra-form-section__head">
              <strong>Mock Telemetry</strong>
              <span>Demo-only values feed the existing mock realtime charts and TV Mode.</span>
            </div>
            <div class="infra-server-form__grid">
              <label><span>Status</span>
                <select class="form-select" name="status">
                  <option value="healthy">Healthy</option>
                  <option value="recovery">Recovery</option>
                  <option value="degraded">Degraded</option>
                  <option value="critical">Critical</option>
                  <option value="maintenance">Maintenance</option>
                </select>
              </label>
              <label><span>Latency ms</span><input class="form-input" name="latency" type="number" min="0" step="1" value="24"></label>
              <label><span>Packet loss %</span><input class="form-input" name="packetLoss" type="number" min="0" step="0.01" value="0.02"></label>
              <label><span>30D uptime %</span><input class="form-input" name="uptime" type="number" min="0" max="100" step="0.001" value="99.990"></label>
            </div>
          </div>

          <div class="infra-method-note" data-infra-method-note role="status"></div>
          <div class="infra-server-form__actions">
            <div class="infra-validation" data-infra-server-validation>Required fields change based on the selected monitoring method.</div>
            <div class="infra-server-form__buttons">
              <button type="button" class="btn btn-ghost btn-sm" data-infra-form-reset>Reset</button>
              <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="plus" class="icon-sm"></i>Add Server</button>
            </div>
          </div>
        </form>
      </section>

      <section class="infra-modal__pane" data-infra-modal-pane="servers">
        <div class="infra-server-registry" data-infra-server-registry></div>
      </section>

      <section class="infra-modal__pane" data-infra-modal-pane="settings">
        <div class="infra-settings-grid">
          <article>
            <i data-lucide="server-cog" class="icon-sm"></i>
            <strong>Backend execution</strong>
            <p>Browser JavaScript cannot reliably perform ICMP or TCP checks. Real monitoring should run from the TRACS VPS backend and stream results to this dashboard.</p>
          </article>
          <article>
            <i data-lucide="database" class="icon-sm"></i>
            <strong>Planned tables</strong>
            <p><code>infrastructure_servers</code>, <code>infrastructure_monitoring_results</code>, and <code>infrastructure_incidents</code> should store targets, samples, history, and incident timelines.</p>
          </article>
          <article>
            <i data-lucide="shield-check" class="icon-sm"></i>
            <strong>Access control</strong>
            <p>Only authorized roles should add, edit, remove, or view sensitive targets. Role-based visibility can be layered onto the server registry API.</p>
          </article>
          <article>
            <i data-lucide="clock-3" class="icon-sm"></i>
            <strong>Worker cadence</strong>
            <p>Use lightweight agentless checks from the TRACS VPS with 30-60 second intervals by default, strict timeouts, jitter, bounded concurrency, and backoff for noisy or down targets.</p>
          </article>
        </div>
        <pre class="infra-schema-note"><code>MonitoringService::checkIcmp($host)
MonitoringService::checkTcp($host, $port)
MonitoringService::checkHttp($url, $expectedStatus, $expectedKeyword)</code></pre>
      </section>
    </div>
  </section>
</div>

<script src="assets/infrastructure-pulse-data.js?v=<?=$infra_data_v?>"></script>
<script src="assets/infrastructure-pulse.js?v=<?=$infra_js_v?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
