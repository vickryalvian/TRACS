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
  <section class="tracs-unsaved-bar" id="billingLowBalanceBar" hidden role="status" aria-live="polite">
    <div class="tracs-unsaved-bar__message">
      <i data-lucide="triangle-alert" aria-hidden="true"></i>
      <span><strong>Low Billing Balance</strong><small id="billingLowBalanceMessage"></small></span>
    </div>
    <div class="tracs-unsaved-bar__actions">
      <a class="btn btn-primary btn-sm" href="https://my.idcloudhost.com" target="_blank" rel="noopener noreferrer">Open Billing Portal</a>
    </div>
  </section>

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

  <section class="panel">
    <div class="panel-head">
      <span class="panel-title"><i data-lucide="gauge" class="icon-sm"></i>Server Insights</span>
    </div>
    <div class="server-insights" id="serverHealthInsights">
      <div class="server-insight-section">
        <div class="skeleton-block server-insight-skeleton-line"></div>
        <div class="skeleton-block server-insight-skeleton-line"></div>
        <div class="skeleton-block server-insight-skeleton-line"></div>
      </div>
      <div class="server-insight-section">
        <div class="skeleton-block server-insight-skeleton-line"></div>
        <div class="skeleton-block server-insight-skeleton-line"></div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <span class="panel-title"><i data-lucide="server" class="icon-sm"></i>Runtime Details</span>
    </div>
    <div class="server-detail-list" id="serverHealthVersions"></div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <span class="panel-title"><i data-lucide="scroll-text" class="icon-sm"></i>Sanitized Error Log</span>
      <span class="panel-meta">Paths, IPs, credentials, SQL details, and stack data are redacted</span>
    </div>
    <div class="server-log-scroll">
      <div class="server-log-summary" id="serverLogSummary"></div>
      <div class="server-log-list" id="serverLogList">
        <div class="empty-sub">Loading safe log summary...</div>
      </div>
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
  const LOW_BALANCE_THRESHOLD = 50000;
  let lastPayload = null;
  let lastLogEntries = [];
  let lastLogAvailable = false;
  let logSeverityFilter = null;

  function updateBillingBanner(sections) {
    const bar = document.getElementById('billingLowBalanceBar');
    const messageEl = document.getElementById('billingLowBalanceMessage');
    const billing = (Array.isArray(sections) ? sections : []).find(section => section.key === 'billing');
    const balance = billing?.items?.balance;
    const isLow = typeof balance === 'number' && balance < LOW_BALANCE_THRESHOLD;
    bar.hidden = !isLow;
    if (isLow) {
      messageEl.textContent = `The remaining IDCloudHost billing balance is below Rp 50.000 (currently ${billing.items.balance_display}). Please top up soon to avoid service interruption.`;
    }
  }

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

  function isEmptyItems(items) {
    if (Array.isArray(items)) return items.length === 0;
    if (items && typeof items === 'object') return Object.keys(items).length === 0;
    return !items;
  }

  function renderInsightList(section) {
    return `<div class="server-insight-list">${(section.items || []).map(row =>
      `<p>${escapeHtml(row.text)}</p>`
    ).join('')}</div>`;
  }

  function renderInsightWarnings(section) {
    return `<div class="server-recommendations">${(section.items || []).map(row =>
      `<div class="server-recommendation ${escapeHtml(row.severity || 'warning')}"><strong>${escapeHtml(row.title)}</strong>${row.detail ? `<span>${escapeHtml(row.detail)}</span>` : ''}</div>`
    ).join('')}</div>`;
  }

  function renderInsightBilling(section) {
    const items = section.items || {};
    const rows = [
      ['Billing Balance', items.balance_display],
      ['Estimated Days Remaining', (items.days_remaining ?? null) !== null ? `${items.days_remaining} day${items.days_remaining === 1 ? '' : 's'}` : 'Not enough data yet'],
      ['Monthly Spend So Far', items.monthly_spend_display || 'Not available'],
      ['Status', items.status_label],
      ['Last Updated', items.last_updated],
    ];
    const kv = `<div class="server-detail-list">${rows.map(([label, value]) =>
      `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`
    ).join('')}</div>`;
    const breakdown = Array.isArray(items.resources) && items.resources.length
      ? `<div class="server-insight-subgroup"><span class="server-insight-subgroup-title">Cost Breakdown</span><div class="server-detail-list">${items.resources.map(row =>
          `<div><span>${escapeHtml(row.label)}</span><strong>${escapeHtml(row.value)}</strong></div>`
        ).join('')}</div></div>`
      : '';
    const note = items.recommendation
      ? `<div class="server-recommendation ${escapeHtml(section.status === 'critical' ? 'critical' : 'warning')}"><span>${escapeHtml(items.recommendation)}</span></div>`
      : '';
    return kv + breakdown + note;
  }

  function renderInsightActionsRow(section) {
    return `<div class="server-quick-actions">${(section.items || []).map(action => {
      const icon = `<i data-lucide="${escapeHtml(action.icon || 'circle')}" class="icon-sm"></i>`;
      if (action.kind === 'link') {
        return `<a class="btn btn-sm" href="${escapeHtml(action.href)}" target="_blank" rel="noopener noreferrer">${icon}${escapeHtml(action.label)}</a>`;
      }
      return `<button type="button" class="btn btn-sm" data-quick-action="${escapeHtml(action.kind)}" data-target-id="${escapeHtml(action.target_id || '')}">${icon}${escapeHtml(action.label)}</button>`;
    }).join('')}</div>`;
  }

  const insightRenderers = {
    billing: renderInsightBilling,
    warnings: renderInsightWarnings,
    list: renderInsightList,
    placeholder: renderInsightList,
    actions_row: renderInsightActionsRow,
  };

  function sectionBadge(section) {
    if (section.type === 'actions_row') return '';
    if (section.type === 'placeholder') return '<span class="badge b-done">Planned</span>';
    return `<span class="badge ${badgeClass(section.status)}">${escapeHtml(section.status || 'healthy')}</span>`;
  }

  function renderInsightSection(section) {
    const renderer = insightRenderers[section.type];
    let body;
    if (section.empty_state && isEmptyItems(section.items)) {
      body = `<div class="server-insight-empty"><i data-lucide="check-circle-2" class="icon-sm"></i><span>${escapeHtml(section.empty_state)}</span></div>`;
    } else {
      body = renderer ? renderer(section) : '';
    }
    const reserved = section.type === 'placeholder' ? ' reserved' : '';
    return `<article class="server-insight-section${reserved} ${escapeHtml(section.status || 'healthy')}">
      <div class="server-insight-section-head">
        <span><i data-lucide="${escapeHtml(section.icon || 'circle')}" class="icon-sm"></i>${escapeHtml(section.title || '')}</span>
        ${sectionBadge(section)}
      </div>
      ${body}
    </article>`;
  }

  function renderInsights(sections) {
    const list = Array.isArray(sections) ? sections : [];
    document.getElementById('serverHealthInsights').innerHTML = list.length
      ? list.map(renderInsightSection).join('')
      : '<div class="empty-sub">Server insights are temporarily unavailable.</div>';
    if (window.lucide?.createIcons) window.lucide.createIcons();
  }

  function downloadDiagnostics() {
    if (!lastPayload) return;
    const blob = new Blob([JSON.stringify(lastPayload, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `tracs-server-health-${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  document.getElementById('serverHealthInsights').addEventListener('click', (event) => {
    const button = event.target.closest('[data-quick-action]');
    if (!button) return;
    const kind = button.dataset.quickAction;
    if (kind === 'anchor') {
      document.getElementById(button.dataset.targetId || '')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    } else if (kind === 'download') {
      downloadDiagnostics();
    }
  });

  function renderLogSummary(counts) {
    document.getElementById('serverLogSummary').innerHTML = ['critical', 'error', 'warning', 'notice'].map(level => {
      const active = logSeverityFilter === level ? ' is-active-filter' : '';
      return `<button type="button" class="badge ${badgeClass(level === 'error' ? 'critical' : level)}${active}" data-severity="${escapeHtml(level)}">${escapeHtml(level)} ${Number(counts[level] || 0)}</button>`;
    }).join('');
  }

  function renderLogEntries() {
    const list = document.getElementById('serverLogList');
    if (!lastLogAvailable) {
      list.innerHTML = '<div class="empty-sub">Error log is unavailable with current safe permissions.</div>';
      return;
    }
    const entries = logSeverityFilter
      ? lastLogEntries.filter(entry => entry.severity === logSeverityFilter)
      : lastLogEntries;
    list.innerHTML = entries.length
      ? entries.map(entry => `<div class="server-log-row"><span class="badge ${badgeClass(entry.severity === 'error' ? 'critical' : entry.severity)}">${escapeHtml(entry.severity)}</span><div><strong>${escapeHtml(entry.timestamp || 'Recent')}</strong><p>${escapeHtml(entry.message)}</p></div></div>`).join('')
      : `<div class="empty-sub">No ${logSeverityFilter ? escapeHtml(logSeverityFilter) + ' ' : ''}entries found.</div>`;
  }

  document.getElementById('serverLogSummary').addEventListener('click', (event) => {
    const button = event.target.closest('[data-severity]');
    if (!button) return;
    const severity = button.dataset.severity;
    logSeverityFilter = logSeverityFilter === severity ? null : severity;
    renderLogSummary(lastPayload?.logs?.counts || {});
    renderLogEntries();
  });

  function render(data) {
    lastPayload = data;
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

    renderInsights(data.insights);
    updateBillingBanner(data.insights);

    const logs = data.logs || {};
    lastLogAvailable = !!logs.available;
    lastLogEntries = Array.isArray(logs.entries) ? logs.entries : [];
    renderLogSummary(logs.counts || {});
    renderLogEntries();
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
