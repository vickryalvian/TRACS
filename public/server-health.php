<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
$serverHealthUser = tracs_require_super_admin_page($conn);
require_once __DIR__ . '/../modules/activity-log/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = (string)($_SESSION['user_email'] ?? '');
$ticker_items = (new AlertTickerController($conn, $uid))->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'Server Health & Logs';
$active_page = 'server-health';

try {
    (new ActivityLogController($conn, $uid))->logActivity('viewed', 'Server Health', 'Opened Server Health & Logs');
} catch (Throwable) {
    // Monitoring must remain available when audit storage is temporarily unavailable.
}

include __DIR__ . '/includes/header.php';
?>
<main class="main"><div class="main-inner server-health-page">
  <div class="topbar">
    <div class="topbar-left">
      <div class="page-title">Server Health & Logs</div>
      <div class="page-sub">Super Admin-only resource, storage, deployment, and sanitized error monitoring</div>
    </div>
    <div class="topbar-right server-health-actions">
      <span class="badge b-done" id="serverHealthChecked">Not checked</span>
      <button type="button" class="btn btn-primary btn-sm" id="serverHealthRefresh">
        <i data-lucide="refresh-cw" class="icon-sm"></i>Refresh
      </button>
    </div>
  </div>

  <div class="server-health-grid" id="serverHealthGrid" aria-live="polite"></div>

  <div class="server-health-columns">
    <section class="panel">
      <div class="panel-head">
        <span class="panel-title"><i data-lucide="server" class="icon-sm"></i>Runtime Details</span>
      </div>
      <div class="server-detail-list" id="serverHealthVersions"></div>
    </section>

    <section class="panel">
      <div class="panel-head">
        <span class="panel-title"><i data-lucide="shield-alert" class="icon-sm"></i>Recommendations</span>
      </div>
      <div class="server-recommendations" id="serverHealthRecommendations">
        <div class="empty-sub">Recommendations appear when usage reaches warning or critical thresholds.</div>
      </div>
    </section>
  </div>

  <section class="panel">
    <div class="panel-head">
      <span class="panel-title"><i data-lucide="scroll-text" class="icon-sm"></i>Sanitized Error Log</span>
      <span class="panel-meta">Paths, IPs, credentials, SQL details, and stack data are redacted</span>
    </div>
    <div class="server-log-summary" id="serverLogSummary"></div>
    <div class="server-log-list" id="serverLogList">
      <div class="empty-sub">Loading safe log summary...</div>
    </div>
  </section>
</div></main>

<script>
(() => {
  const metricOrder = ['cpu','memory','disk','disk_free','project_size','uploads_size','logs_size','backups_size','database_size','uptime'];
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const badgeClass = status => status === 'critical' ? 'b-critical' : status === 'warning' ? 'b-warning' : status === 'healthy' ? 'b-active' : 'b-done';
  const safeVersion = value => value ? escapeHtml(value) : 'Unavailable';
  const refreshButton = document.getElementById('serverHealthRefresh');

  function renderMetric(metric, key) {
    const percent = metric.percent === null || metric.percent === undefined ? null : Math.max(0, Math.min(100, Number(metric.percent)));
    return `<article class="server-health-card ${escapeHtml(metric.status || 'unavailable')}">
      <div class="server-health-card-head">
        <span>${escapeHtml(metric.label || key)}</span>
        <span class="badge ${badgeClass(metric.status)}">${escapeHtml(metric.status || 'unavailable')}</span>
      </div>
      <strong>${escapeHtml(metric.display || 'Unavailable')}</strong>
      ${metric.detail ? `<small>${escapeHtml(metric.detail)}</small>` : ''}
      ${percent === null ? '' : `<div class="server-health-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}"><span style="width:${percent}%"></span></div>`}
    </article>`;
  }

  function render(data) {
    const metrics = data.metrics || {};
    document.getElementById('serverHealthGrid').innerHTML = metricOrder.map(key => renderMetric(metrics[key] || {label:key,display:'Unavailable',status:'unavailable'}, key)).join('');
    document.getElementById('serverHealthChecked').textContent = data.checked_at ? `Checked ${new Date(data.checked_at).toLocaleString()}` : 'Check unavailable';

    const versions = data.versions || {};
    document.getElementById('serverHealthVersions').innerHTML = [
      ['PHP', versions.php],
      ['MySQL / MariaDB', versions.database],
      ['Nginx', versions.nginx],
      ['TRACS Version', versions.app],
      ['Commit', versions.commit ? String(versions.commit).slice(0, 12) : null],
      ['Last Deployment', versions.last_deploy_at ? new Date(versions.last_deploy_at).toLocaleString() : null],
    ].map(([label,value]) => `<div><span>${escapeHtml(label)}</span><strong>${safeVersion(value)}</strong></div>`).join('');

    const recommendations = Object.values(metrics).filter(metric => metric && metric.recommendation).map(metric =>
      `<div class="server-recommendation ${escapeHtml(metric.status)}"><strong>${escapeHtml(metric.label)}</strong><span>${escapeHtml(metric.recommendation)}</span></div>`
    );
    document.getElementById('serverHealthRecommendations').innerHTML = recommendations.length
      ? recommendations.join('')
      : '<div class="empty-sub">No warning or critical resource recommendations.</div>';

    const logs = data.logs || {};
    const counts = logs.counts || {};
    document.getElementById('serverLogSummary').innerHTML = ['critical','error','warning','notice'].map(level =>
      `<span class="badge ${badgeClass(level === 'error' ? 'critical' : level)}">${escapeHtml(level)} ${Number(counts[level] || 0)}</span>`
    ).join('');
    const entries = Array.isArray(logs.entries) ? logs.entries : [];
    document.getElementById('serverLogList').innerHTML = !logs.available
      ? '<div class="empty-sub">Error log is unavailable with current safe permissions.</div>'
      : entries.length
        ? entries.map(entry => `<div class="server-log-row"><span class="badge ${badgeClass(entry.severity === 'error' ? 'critical' : entry.severity)}">${escapeHtml(entry.severity)}</span><div><strong>${escapeHtml(entry.timestamp || 'Recent')}</strong><p>${escapeHtml(entry.message)}</p></div></div>`).join('')
        : '<div class="empty-sub">No recent error entries found.</div>';
  }

  async function loadHealth() {
    refreshButton.disabled = true;
    refreshButton.classList.add('is-loading');
    try {
      const response = await fetch('/api/server-health.php', {headers:{Accept:'application/json'}, cache:'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || 'Server health is unavailable.');
      render(payload.data || {});
    } catch (error) {
      const message = typeof getFriendlyErrorMessage === 'function'
        ? getFriendlyErrorMessage(error, 'Server health is temporarily unavailable.')
        : 'Server health is temporarily unavailable.';
      document.getElementById('serverHealthGrid').innerHTML = `<div class="panel server-health-error">${escapeHtml(message)}</div>`;
    } finally {
      window.setTimeout(() => {
        refreshButton.disabled = false;
        refreshButton.classList.remove('is-loading');
      }, 5000);
    }
  }

  refreshButton.addEventListener('click', loadHealth);
  loadHealth();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
