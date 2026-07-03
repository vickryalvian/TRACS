(function () {
  'use strict';

  const Infra = window.TRACSInfrastructure;
  if (!Infra) return;

  // Mirrors core/infrastructure_servers.php's TRACS_INFRA_SEED_CODES —
  // removing one of these persists (hidden server-side); removing any other
  // mock/"Demo Data" entry stays session-only, matching existing behavior.
  const SEED_CODES = new Set(['DCI', 'IDB', 'CY1', 'BCD', 'BTI', 'DR3', 'SG3', 'EGH', 'NDS']);

  const state = {
    selectedCode: 'NDS',
    lastEventKey: '',
    store: null,
    pendingChecks: new Set(),
    realCheckTimer: null,
    editingCode: null,
  };

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[char]);
  }

  function pct(value, digits = 3) {
    return `${Number(value || 0).toFixed(digits)}%`;
  }

  function ms(value) {
    return `${Math.round(Number(value || 0))} ms`;
  }

  // Nodes awaiting a first real check have no measured data yet; showing
  // 0ms/0%/100% would misrepresent them as measured and healthy.
  function statText(node, formatted) {
    return node?.status === 'pending' ? '--' : formatted;
  }

  // 30D uptime additionally requires historical aggregation that real
  // targets don't have yet (a single check only proves current reachability).
  function uptimeText(node, formatted) {
    if (node?.status === 'pending') return '--';
    if (node?.mode === 'real' && !node?.uptimeTracked) return '--';
    return formatted;
  }

  function statusClass(status) {
    return `is-${esc(status || 'healthy')}`;
  }

  function statusChip(status) {
    return `<span class="infra-status-chip ${statusClass(status)}">${esc(Infra.statusLabel(status))}</span>`;
  }

  function tracsStatusBadgeClass(status) {
    if (status === 'critical') return 'b-critical';
    if (status === 'warning' || status === 'degraded') return 'b-warning';
    if (status === 'maintenance') return 'b-pending';
    if (status === 'recovery') return 'b-info';
    if (status === 'healthy') return 'b-active';
    return 'b-low';
  }

  const METHOD_LABELS = {
    icmp: 'Network Ping',
    tcp: 'Port Check',
    http: 'Health Endpoint',
    mock: 'Demo Data',
  };

  const METHOD_HELP = {
    icmp: 'ICMP ping may be blocked by firewall. Use TCP or HTTP check for more reliable service monitoring.',
    tcp: 'TCP check is recommended for checking specific services such as SSH, HTTP, HTTPS, database, or custom ports.',
    http: 'HTTP health check is recommended for web services and APIs.',
    mock: 'Mock mode is for demo and TV Mode testing only.',
  };

  function methodLabel(method) {
    return METHOD_LABELS[method] || 'Monitoring Target';
  }

  function modeLabel(node) {
    return node.mode === 'real' ? 'REAL MONITORING' : 'MOCK';
  }

  function monitoringTarget(node) {
    if (node.method === 'http') return node.health_url || 'Health URL pending';
    if (node.method === 'tcp') return `${node.target_host || 'Host pending'}:${node.target_port || '--'}`;
    if (node.method === 'icmp') return node.target_host || node.target_ip || 'Host pending';
    return 'Demo session telemetry';
  }

  function metricRiskScore(node) {
    if (!node || node.status === 'pending') return -1;
    const statusWeight = Infra.statusRank(node.status) * 100000;
    const packetLoss = Number(node.packetLoss || 0);
    const latency = Number(node.latency || 0);
    const lossWeight = packetLoss >= 1 ? 60000 : (packetLoss >= 0.1 ? 30000 : packetLoss * 10000);
    const latencyWeight = latency >= 80 ? 45000 : (latency >= 50 ? 22000 : latency * 100);
    return statusWeight + lossWeight + latencyWeight;
  }

  function orderedMetricNodes(nodes) {
    return [...(nodes || [])].sort((a, b) => (
      metricRiskScore(b) - metricRiskScore(a)
      || Number(b.packetLoss || 0) - Number(a.packetLoss || 0)
      || Number(b.latency || 0) - Number(a.latency || 0)
      || String(a.code || '').localeCompare(String(b.code || ''))
    ));
  }

  function dashboardAffectedItems(snapshot) {
    const nodeItems = (snapshot.nodes || [])
      .filter((node) => node.status !== 'healthy')
      .sort((a, b) => (Infra.statusRank(b.status) - Infra.statusRank(a.status)) || (b.latency - a.latency))
      .slice(0, 3)
      .map((node) => ({
        name: node.name,
        detail: node.region || node.country || node.code,
        status: node.status,
        label: Infra.statusLabel(node.status),
      }));

    if (nodeItems.length) return nodeItems;

    const regionItems = [snapshot.summary.indonesia, snapshot.summary.singapore]
      .filter((region) => region?.status && region.status !== 'healthy')
      .slice(0, 2)
      .map((region) => ({
        name: region.country,
        detail: `${region.latency} ms avg`,
        status: region.status,
        label: region.label,
      }));

    return regionItems.length ? regionItems : [{
      name: 'All systems up',
      detail: 'No affected datacenter or region',
      status: 'healthy',
      label: 'Operational',
    }];
  }

  function dashboardAffectedPills(items) {
    const pills = items.map((item) => `
      <span class="badge infra-dashboard-widget__affected-pill ${tracsStatusBadgeClass(item.status)}">
        <span class="badge-dot"></span>
        <strong>${esc(item.name)}</strong>
      </span>
    `).join('');
    if (items.length <= 1) return pills;
    return `
      <span class="infra-dashboard-widget__affected-set">${pills}</span>
      <span class="infra-dashboard-widget__affected-set" aria-hidden="true">${pills}</span>
    `;
  }

  function summaryCards(snapshot) {
    const { summary } = snapshot;
    return [
      {
        label: 'Global Status',
        value: summary.globalStatusLabel,
        meta: `${summary.healthyCount}/${summary.nodeCount} healthy nodes`,
        status: summary.globalStatus,
        icon: 'radar',
      },
      {
        label: 'Active Incidents',
        value: summary.activeIncidents,
        meta: summary.worstAffected ? `Worst: ${summary.worstAffected.name}` : 'No active incident',
        status: summary.activeIncidents > 0 ? summary.globalStatus : 'healthy',
        icon: 'alert-triangle',
      },
      {
        label: 'Average Latency',
        value: ms(summary.averageLatency),
        meta: 'Mock realtime sample',
        status: summary.averageLatency > 60 ? 'degraded' : 'healthy',
        icon: 'activity',
      },
      {
        label: '30D Uptime',
        value: pct(summary.uptime30d),
        meta: 'Prototype aggregate',
        status: summary.uptime30d < 99.9 ? 'degraded' : 'healthy',
        icon: 'shield-check',
      },
      {
        label: 'Indonesia Status',
        value: summary.indonesia.label,
        meta: `${summary.indonesia.latency} ms avg / ${summary.indonesia.incidents} watch`,
        status: summary.indonesia.status,
        icon: 'map-pin',
      },
      {
        label: 'Singapore Status',
        value: summary.singapore.label,
        meta: `${summary.singapore.latency} ms avg / ${summary.singapore.incidents} watch`,
        status: summary.singapore.status,
        icon: 'network',
      },
    ];
  }

  function renderSummary(container, snapshot) {
    if (!container) return;
    container.innerHTML = summaryCards(snapshot).map((card) => `
      <article class="infra-summary-card ${statusClass(card.status)}">
        <div class="infra-summary-card__icon"><i data-lucide="${esc(card.icon)}"></i></div>
        <div>
          <span>${esc(card.label)}</span>
          <strong>${esc(card.value)}</strong>
          <p>${esc(card.meta)}</p>
        </div>
      </article>
    `).join('');
  }

  function selectedNode(snapshot) {
    const worst = snapshot.summary.worstAffected?.code;
    if (!state.selectedCode && worst) state.selectedCode = worst;
    return snapshot.nodes.find((node) => node.code === state.selectedCode)
      || snapshot.nodes.find((node) => node.code === worst)
      || snapshot.nodes[0];
  }

  function reportInsight(snapshot) {
    const { summary } = snapshot;
    if (summary.globalStatus === 'critical') {
      return `Critical infrastructure attention is required. ${summary.worstAffected?.name || 'A monitored node'} is currently the highest-risk server, with ${summary.activeIncidents} active incident signal(s) across the registry.`;
    }
    if (summary.globalStatus === 'warning') {
      return `Infrastructure is degraded but service is still observable. ${summary.activeIncidents} active signal(s) should be reviewed before the next handover.`;
    }
    return `Infrastructure is operating inside the expected band. No critical server is currently blocking handover.`;
  }

  function renderReport(container, snapshot) {
    if (!container) return;
    const node = selectedNode(snapshot);
    const affected = snapshot.nodes
      .filter((item) => item.status !== 'healthy')
      .sort((a, b) => Infra.statusRank(b.status) - Infra.statusRank(a.status));
    const healthy = snapshot.nodes.filter((item) => item.status === 'healthy');
    const focusedSelect = container.contains(document.activeElement)
      ? document.activeElement.closest('[data-infra-select]')?.getAttribute('data-infra-select')
      : null;
    container.innerHTML = `
      <div class="infra-report-main ${statusClass(snapshot.summary.globalStatus)}">
        <div class="infra-report-head">
          <div>
            <h2>${esc(snapshot.summary.globalStatusLabel)}</h2>
          </div>
          ${statusChip(snapshot.summary.globalStatus)}
        </div>
        <p class="infra-report-summary">${esc(reportInsight(snapshot))}</p>
        <div class="infra-report-metrics">
          <div><span>Nodes</span><strong>${esc(snapshot.summary.nodeCount)}</strong></div>
          <div><span>Healthy</span><strong>${esc(snapshot.summary.healthyCount)}</strong></div>
          <div><span>Incidents</span><strong>${esc(snapshot.summary.activeIncidents)}</strong></div>
          <div><span>Avg latency</span><strong>${esc(ms(snapshot.summary.averageLatency))}</strong></div>
        </div>
      </div>
      <aside class="infra-report-side ${statusClass(node?.status || 'healthy')}">
        ${node ? `
          <div class="infra-report-node-head">
            <strong>${esc(node.name)}</strong>
            ${statusChip(node.status)}
          </div>
          <p>${esc(node.region || node.city || '--')} / ${esc(node.provider || node.facility || '--')}</p>
          <div class="infra-report-node-grid">
            <div><span>Latency</span><b>${esc(statText(node, ms(node.latency)))}</b></div>
            <div><span>Loss</span><b>${esc(statText(node, `${Number(node.packetLoss || 0).toFixed(2)}%`))}</b></div>
            <div><span>Uptime</span><b>${esc(uptimeText(node, pct(node.uptime)))}</b></div>
            <div><span>Checked</span><b>${esc(node.lastChecked ? Infra.formatTime(node.lastChecked) : 'Not checked')}</b></div>
          </div>
        ` : '<p>No selected server.</p>'}
      </aside>
      <div class="infra-report-lists">
        <section>
          <div class="infra-report-list-title"><i data-lucide="alert-triangle" class="icon-xs"></i>Needs Attention</div>
          ${affected.length ? affected.slice(0, 6).map((item) => `
            <button type="button" class="infra-report-row ${statusClass(item.status)}" data-infra-select="${esc(item.code)}">
              <span>${esc(item.code)}</span>
              <strong>${esc(item.name)}</strong>
              <em>${esc(Infra.statusLabel(item.status))} / ${esc(statText(item, ms(item.latency)))}</em>
            </button>
          `).join('') : '<p class="infra-empty-line">No degraded, critical, maintenance, or recovery servers.</p>'}
        </section>
        <section>
          <div class="infra-report-list-title"><i data-lucide="check-check" class="icon-xs"></i>Stable Nodes</div>
          ${healthy.length ? healthy.slice(0, 6).map((item) => `
            <button type="button" class="infra-report-row is-healthy" data-infra-select="${esc(item.code)}">
              <span>${esc(item.code)}</span>
              <strong>${esc(item.name)}</strong>
              <em>${esc(ms(item.latency))} / ${esc(uptimeText(item, pct(item.uptime)))}</em>
            </button>
          `).join('') : '<p class="infra-empty-line">No healthy servers in this snapshot.</p>'}
        </section>
      </div>
    `;
    if (focusedSelect) {
      container.querySelector(`[data-infra-select="${CSS.escape(focusedSelect)}"]`)?.focus();
    }
  }

  function ensureMetricRows(container, nodes) {
    if (!container) return;
    const nodeKey = (nodes || []).map((node) => node.code).sort().join('|');
    if (container.dataset.ready === '1' && container.dataset.nodeKey === nodeKey) return;
    container.innerHTML = nodes.map((node) => `
      <article class="infra-metric-row" data-infra-metric="${esc(node.code)}">
        <button type="button" class="infra-metric-row__main" data-infra-select="${esc(node.code)}">
          <span class="infra-metric-row__code">${esc(node.code)}</span>
          <span class="infra-metric-row__name">${esc(node.name)}</span>
        </button>
        <span class="infra-status-chip"></span>
        <span class="infra-metric-value" data-field="latency"></span>
        <span class="infra-metric-value" data-field="loss"></span>
        <span class="infra-metric-value" data-field="uptime"></span>
        <svg class="infra-sparkline" viewBox="0 0 116 32" aria-hidden="true">
          <path class="infra-sparkline__area" data-field="spark-area"></path>
          <path class="infra-sparkline__line" data-field="spark-line"></path>
        </svg>
      </article>
    `).join('');
    container.dataset.ready = '1';
    container.dataset.nodeKey = nodeKey;
  }

  function updateMetricRows(container, snapshot) {
    if (!container) return;
    const nodes = orderedMetricNodes(snapshot.nodes);
    ensureMetricRows(container, nodes);
    nodes.forEach((node) => {
      const row = container.querySelector(`[data-infra-metric="${CSS.escape(node.code)}"]`);
      if (!row) return;
      row.className = `infra-metric-row ${statusClass(node.status)}${state.selectedCode === node.code ? ' is-selected' : ''}`;
      container.appendChild(row);
      const chip = row.querySelector('.infra-status-chip');
      if (chip) {
        chip.className = `infra-status-chip ${statusClass(node.status)}`;
        chip.textContent = Infra.statusLabel(node.status);
      }
      row.querySelector('[data-field="latency"]').textContent = statText(node, ms(node.latency));
      row.querySelector('[data-field="loss"]').textContent = statText(node, `${Number(node.packetLoss).toFixed(2)}% loss`);
      row.querySelector('[data-field="uptime"]').textContent = uptimeText(node, pct(node.uptime));
      const line = row.querySelector('[data-field="spark-line"]');
      const area = row.querySelector('[data-field="spark-area"]');
      const linePath = Infra.sparklinePath(node.history?.latency || []);
      if (line) line.setAttribute('d', linePath);
      if (area) area.setAttribute('d', `${linePath} L113 32 L3 32 Z`);
    });
  }

  function svgPath(points, width = 320, height = 86, padding = 8) {
    return Infra.sparklinePath(points || [], width, height, padding);
  }

  function graphPanel(title, subtitle, series, options = {}) {
    const width = 320;
    const height = 86;
    const legends = series.map((item) => `<span class="${statusClass(item.status || 'healthy')}"><i></i>${esc(item.label)}</span>`).join('');
    const lines = series.map((item) => `
      <path class="infra-graph__area ${statusClass(item.status || 'healthy')}" d="${esc(svgPath(item.points, width, height, 8))} L312 ${height} L8 ${height} Z"></path>
      <path class="infra-graph__line ${statusClass(item.status || 'healthy')}" d="${esc(svgPath(item.points, width, height, 8))}"></path>
    `).join('');
    return `
      <article class="panel infra-graph-panel">
        <div class="panel-head">
          <div>
            <span class="panel-title">${esc(title)}</span>
            <div class="panel-meta">${esc(subtitle)}</div>
          </div>
          ${options.value ? `<strong class="infra-graph-panel__value">${esc(options.value)}</strong>` : ''}
        </div>
        <div class="infra-graph">
          <svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true">
            <path class="infra-graph__grid" d="M8 18H312 M8 43H312 M8 68H312"></path>
            ${lines}
          </svg>
          <div class="infra-graph__legend">${legends}</div>
        </div>
      </article>
    `;
  }

  function renderGraphs(container, snapshot) {
    if (!container) return;
    const worst = selectedNode(snapshot);
    const degradedCount = snapshot.nodes.filter((node) => ['degraded', 'warning'].includes(node.status)).length;
    const criticalCount = snapshot.nodes.filter((node) => node.status === 'critical').length;
    const maintenanceCount = snapshot.nodes.filter((node) => node.status === 'maintenance').length;
    const healthyCount = snapshot.nodes.filter((node) => node.status === 'healthy').length;
    const indonesiaLatency = snapshot.nodes.filter((node) => node.country === 'Indonesia').map((node) => node.latency);
    const singaporeLatency = snapshot.nodes.filter((node) => node.country === 'Singapore').map((node) => node.latency);
    container.innerHTML = [
      graphPanel('Latency Trend', `${worst?.name || 'Selected node'} / last samples`, [
        { label: worst?.code || 'N/A', points: worst?.history?.latency || [], status: worst?.status || 'healthy' },
      ], { value: worst ? ms(worst.latency) : '--' }),
      graphPanel('Packet Loss', 'Selected node loss trend', [
        { label: worst?.code || 'N/A', points: worst?.history?.packetLoss || [], status: worst?.status || 'healthy' },
      ], { value: worst ? `${Number(worst.packetLoss || 0).toFixed(2)}%` : '--' }),
      graphPanel('30D Uptime', 'Aggregate uptime samples', [
        { label: '30D', points: snapshot.nodes.flatMap((node) => node.history?.uptime?.slice(-6) || []), status: snapshot.summary.uptime30d < 99.9 ? 'degraded' : 'healthy' },
      ], { value: pct(snapshot.summary.uptime30d) }),
      graphPanel('Incident Count', 'Synthetic event pressure', [
        { label: 'Incidents', points: snapshot.events.slice(0, 12).map((_, index) => Math.max(0, snapshot.summary.activeIncidents - (index % 3))).reverse(), status: snapshot.summary.globalStatus },
      ], { value: String(snapshot.summary.activeIncidents) }),
      graphPanel('Regional Latency', 'Indonesia vs Singapore', [
        { label: 'Indonesia', points: indonesiaLatency, status: snapshot.summary.indonesia.status },
        { label: 'Singapore', points: singaporeLatency, status: snapshot.summary.singapore.status },
      ]),
      graphPanel('Node Health Mix', 'Healthy / degraded / critical / maintenance', [
        { label: 'Healthy', points: [healthyCount, healthyCount + 1, healthyCount, healthyCount], status: 'healthy' },
        { label: 'Degraded', points: [degradedCount, degradedCount + 1, degradedCount, degradedCount], status: 'degraded' },
        { label: 'Critical', points: [criticalCount, criticalCount, criticalCount + 1, criticalCount], status: 'critical' },
        { label: 'Maint', points: [maintenanceCount, maintenanceCount, maintenanceCount + 1, maintenanceCount], status: 'maintenance' },
      ]),
      graphPanel('Maintenance Timeline', 'Planned work signal', [
        { label: 'Windows', points: snapshot.nodes.map((node) => node.status === 'maintenance' ? 1 : 0), status: 'maintenance' },
      ]),
      graphPanel('Response Percentiles', 'p50 / p95 / p99 selected node', [
        { label: 'p50', points: worst?.history?.p50 || [], status: 'healthy' },
        { label: 'p95', points: worst?.history?.p95 || [], status: 'degraded' },
        { label: 'p99', points: worst?.history?.p99 || [], status: 'critical' },
      ]),
    ].join('');
  }

  function renderEventFeed(container, snapshot) {
    if (!container) return;
    const key = snapshot.events.slice(0, 6).map((event) => `${event.id}:${event.createdAt}`).join('|');
    if (key === state.lastEventKey) {
      container.querySelectorAll('[data-infra-event-ago]').forEach((item) => {
        item.textContent = Infra.formatAgo(item.getAttribute('datetime'));
      });
      return;
    }
    state.lastEventKey = key;
    container.innerHTML = snapshot.events.slice(0, 8).map((event) => `
      <article class="infra-event ${statusClass(event.type)}">
        <span class="infra-event__dot"></span>
        <div>
          <strong>${esc(event.title)}</strong>
          <p>${esc(event.detail)}</p>
          <em>Hook: ${esc(event.integration)} / <time datetime="${esc(event.createdAt)}" data-infra-event-ago>${esc(Infra.formatAgo(event.createdAt))}</time></em>
        </div>
      </article>
    `).join('');
  }

  function renderPage(page, snapshot) {
    renderSummary(page.querySelector('[data-infra-summary]'), snapshot);
    renderReport(page.querySelector('[data-infra-report]'), snapshot);
    updateMetricRows(page.querySelector('[data-infra-metrics]'), snapshot);
    renderGraphs(page.querySelector('[data-infra-graphs]'), snapshot);
    renderEventFeed(page.querySelector('[data-infra-feed]'), snapshot);
    page.querySelectorAll('[data-infra-generated-at]').forEach((node) => {
      node.textContent = Infra.formatTime(snapshot.generatedAt);
    });
  }

  function renderInfrastructurePulseDashboardWidget(target, snapshot) {
    if (!target) return;
    const { summary } = snapshot;
    const affectedItems = dashboardAffectedItems(snapshot);
    const affectedKey = affectedItems.map((item) => `${item.name}:${item.status}`).join('|');
    const isSliding = affectedItems.length > 1 ? ' is-sliding' : '';
    target.className = `panel infra-dashboard-widget ${statusClass(summary.globalStatus)}`;
    const markup = `
      <div class="infra-dashboard-widget__head">
        <div>
          <span>Infrastructure Pulse</span>
          <strong>${esc(summary.globalStatusLabel)}</strong>
        </div>
        <i data-lucide="radar" class="dashboard-widget-main-icon"></i>
      </div>
      <div class="infra-dashboard-widget__metrics">
        <div><span>Incidents</span><b>${esc(summary.activeIncidents)}</b></div>
        <div><span>Avg latency</span><b>${esc(ms(summary.averageLatency))}</b></div>
        <div><span>30D uptime</span><b>${esc(pct(summary.uptime30d))}</b></div>
      </div>
      <div class="infra-dashboard-widget__worst">
        <span>Affected</span>
        <div class="infra-dashboard-widget__affected-viewport" aria-label="Affected infrastructure">
          <div class="infra-dashboard-widget__affected-track${isSliding}">
            ${dashboardAffectedPills(affectedItems)}
          </div>
        </div>
      </div>
    `;
    if (target.dataset.affectedKey === affectedKey && target.dataset.globalStatus === summary.globalStatus) {
      target.querySelectorAll('.infra-dashboard-widget__metrics b')[0].textContent = String(summary.activeIncidents);
      target.querySelectorAll('.infra-dashboard-widget__metrics b')[1].textContent = ms(summary.averageLatency);
      target.querySelectorAll('.infra-dashboard-widget__metrics b')[2].textContent = pct(summary.uptime30d);
      return;
    }
    target.dataset.affectedKey = affectedKey;
    target.dataset.globalStatus = summary.globalStatus;
    target.innerHTML = markup;
  }

  function renderInfrastructurePulseTVWidget(target, snapshot) {
    if (!target) return;
    const { summary } = snapshot;
    const affected = snapshot.nodes
      .filter((node) => node.status !== 'healthy')
      .sort((a, b) => Infra.statusRank(b.status) - Infra.statusRank(a.status))
      .slice(0, 3);
    target.className = `tv-panel infra-tv-widget ${statusClass(summary.globalStatus)}`;
    target.innerHTML = `
      <div class="infra-tv-widget__head">
        <div><span>Infrastructure Pulse</span><h2>${esc(summary.globalStatusLabel)}</h2></div>
        <div class="infra-tv-widget__pulse" aria-hidden="true"></div>
      </div>
      <div class="infra-tv-widget__metrics">
        <div><strong>${esc(summary.activeIncidents)}</strong><span>incidents</span></div>
        <div><strong>${esc(ms(summary.averageLatency))}</strong><span>avg latency</span></div>
        <div><strong>${esc(pct(summary.uptime30d))}</strong><span>30D uptime</span></div>
      </div>
      <div class="infra-tv-widget__regions">
        <div><span>Indonesia</span><strong>${esc(summary.indonesia.label)}</strong></div>
        <div><span>Singapore</span><strong>${esc(summary.singapore.label)}</strong></div>
      </div>
      <div class="infra-tv-widget__affected">
        ${affected.length ? affected.map((node) => `
          <article class="${statusClass(node.status)}">
            <strong>${esc(node.code)}</strong>
            <span>${esc(node.name)}</span>
            <b>${esc(statText(node, ms(node.latency)))}</b>
          </article>
        `).join('') : '<p>All datacenters are inside the normal band.</p>'}
      </div>
    `;
  }

  function bindPage(page, store) {
    if (!page || page.dataset.bound === '1') return;
    page.dataset.bound = '1';
    page.addEventListener('click', (event) => {
      const select = event.target.closest('[data-infra-select]');
      if (!select) return;
      state.selectedCode = select.getAttribute('data-infra-select');
      renderPage(page, store.getSnapshot());
    });
  }

  function initDashboardWidgetSliders() {
    document.querySelectorAll('.dashboard-widget-slider').forEach((slider) => {
      if (slider.dataset.bound === '1') return;
      const slides = slider.querySelectorAll('.dashboard-widget-slide');
      const next = slider.querySelector('[data-dashboard-widget-next]');
      if (!next || slides.length < 2) return;

      slider.dataset.bound = '1';
      slider.dataset.widgetSlide = slider.dataset.widgetSlide || '0';
      let index = 0;
      let intervalId = null;
      let isPaused = false;
      const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const showSlide = (nextIndex) => {
        index = nextIndex % slides.length;
        slider.dataset.widgetSlide = String(index);
      };
      const startAuto = () => {
        if (reduceMotion || intervalId) return;
        intervalId = window.setInterval(() => {
          if (!isPaused) showSlide(index + 1);
        }, 8000);
      };
      const pauseAuto = () => { isPaused = true; };
      const resumeAuto = () => { isPaused = false; };

      next.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        showSlide(index + 1);
      });
      slider.addEventListener('mouseenter', pauseAuto);
      slider.addEventListener('mouseleave', resumeAuto);
      slider.addEventListener('focusin', pauseAuto);
      slider.addEventListener('focusout', resumeAuto);
      startAuto();
    });
  }

  function nodeFromForm(form) {
    const data = new FormData(form);
    const method = String(data.get('method') || 'icmp');
    const mode = method === 'mock' ? 'mock' : 'real';
    const code = String(data.get('code') || '').trim().toUpperCase().replace(/[^A-Z0-9_-]/g, '').slice(0, 8);
    if (!code) return null;
    const createdAt = new Date().toISOString();
    const latency = mode === 'mock' ? Number(data.get('latency') || 24) : 0;
    const packetLoss = mode === 'mock' ? Number(data.get('packetLoss') || 0) : 0;
    const uptime = mode === 'mock' ? Number(data.get('uptime') || 99.99) : 100;
    const targetHost = String(data.get('target_host') || '').trim();
    const targetPort = String(data.get('target_port') || '').trim();
    const healthUrl = String(data.get('health_url') || '').trim();
    const expectedStatus = Number(data.get('expected_status') || 200);
    const timeoutSeconds = Number(data.get('timeout_seconds') || 5);
    const intervalSeconds = Number(data.get('interval_seconds') || 60);
    return {
      id: `manual-${code.toLowerCase()}`,
      code,
      shortCode: code,
      name: String(data.get('name') || code).trim(),
      region: String(data.get('region') || 'Manual Registry').trim(),
      country: String(data.get('country') || 'Indonesia').trim(),
      city: String(data.get('region') || 'Manual Registry').trim(),
      provider: String(data.get('provider') || 'Manual entry').trim(),
      facility: String(data.get('provider') || 'Manual entry').trim(),
      mode,
      method,
      target_host: targetHost,
      target_ip: targetHost,
      target_port: method === 'tcp' ? targetPort : '',
      health_url: method === 'http' ? healthUrl : '',
      expected_status: method === 'http' ? expectedStatus : '',
      expected_keyword: method === 'http' ? String(data.get('expected_keyword') || '').trim() : '',
      packet_count: method === 'icmp' ? Number(data.get('packet_count') || 4) : '',
      interval_seconds: mode === 'real' ? intervalSeconds : '',
      timeout_seconds: mode === 'real' ? timeoutSeconds : '',
      is_active: true,
      status: mode === 'mock' ? String(data.get('status') || 'healthy') : 'pending',
      latency,
      packetLoss,
      uptime,
      incidentCount: mode === 'mock' && ['critical', 'degraded', 'maintenance'].includes(String(data.get('status'))) ? 1 : 0,
      latitude: 0,
      longitude: 0,
      lastChecked: mode === 'mock' ? createdAt : null,
      last_checked_at: mode === 'mock' ? createdAt : null,
      created_at: createdAt,
      updated_at: createdAt,
      history: {
        latency: Array.from({ length: 36 }, (_, index) => Math.max(1, Math.round(latency + Math.sin(index / 3) * 2))),
        packetLoss: Array.from({ length: 36 }, () => packetLoss),
        uptime: Array.from({ length: 36 }, () => uptime),
        incidents: Array.from({ length: 12 }, () => 0),
        p50: Array.from({ length: 24 }, () => Math.round(latency * 0.78)),
        p95: Array.from({ length: 24 }, () => Math.round(latency * 1.24)),
        p99: Array.from({ length: 24 }, () => Math.round(latency * 1.55)),
      },
    };
  }

  // Converts a persisted infrastructure_servers row (loaded server-side into
  // window.TRACS_INFRA_REAL_SERVERS) into the node shape the store expects.
  function dbRowToNode(row) {
    const code = String(row.code || '').toUpperCase();
    const latency = row.last_latency_ms != null ? Math.round(Number(row.last_latency_ms)) : 0;
    const packetLoss = row.last_packet_loss_percent != null ? Number(row.last_packet_loss_percent) : 0;
    return {
      id: `db-${code.toLowerCase()}`,
      code,
      shortCode: code,
      name: row.name || code,
      region: row.region || '',
      country: row.country || '',
      city: row.region || '',
      provider: row.provider || '',
      facility: row.provider || '',
      mode: 'real',
      method: row.method || 'icmp',
      target_host: row.target_host || '',
      target_ip: row.target_host || '',
      target_port: row.target_port || '',
      health_url: row.health_url || '',
      expected_status: row.expected_status || '',
      expected_keyword: row.expected_keyword || '',
      packet_count: row.packet_count || 4,
      interval_seconds: row.interval_seconds || 60,
      timeout_seconds: row.timeout_seconds || 5,
      is_active: true,
      status: row.last_status || 'pending',
      latency,
      packetLoss,
      uptime: 0,
      uptimeTracked: false,
      incidentCount: 0,
      latitude: 0,
      longitude: 0,
      lastChecked: row.last_checked_at || null,
      created_at: row.created_at || new Date().toISOString(),
      updated_at: row.updated_at || new Date().toISOString(),
    };
  }

  // Live ICMP check for real Network Ping targets. Runs server-side
  // (public/api/infrastructure-ping.php) — the browser never probes hosts
  // itself, only asks the TRACS backend to run a bounded ping and report back.
  async function fetchIcmpCheck(node) {
    const response = await fetch('/api/infrastructure-ping.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        host: node.target_host || node.target_ip,
        count: node.packet_count || 4,
        timeout: node.timeout_seconds || 5,
        code: node.code,
      }),
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload || payload.success === false) return null;
    return payload.data;
  }

  async function runIcmpCheck(node, store) {
    if (!node || state.pendingChecks.has(node.code)) return;
    state.pendingChecks.add(node.code);
    try {
      const result = await fetchIcmpCheck(node);
      if (!result) return;
      const snapshot = store.getSnapshot();
      const current = snapshot.nodes.find((item) => item.code === node.code);
      if (!current) return;
      const latency = result.latency_ms != null ? Math.round(Number(result.latency_ms)) : current.latency;
      const packetLoss = result.packet_loss_percent != null ? Number(result.packet_loss_percent) : current.packetLoss;
      const updated = {
        ...current,
        status: result.status || 'critical',
        latency,
        packetLoss,
        lastChecked: result.checked_at || new Date().toISOString(),
        history: {
          ...current.history,
          latency: [...(current.history?.latency || []).slice(-35), latency],
          packetLoss: [...(current.history?.packetLoss || []).slice(-35), packetLoss],
        },
      };
      const nodes = snapshot.nodes.map((item) => (item.code === node.code ? updated : item));
      store.ingest({ ...snapshot, nodes });
    } catch (error) {
      // Backend/network failure: leave the node at its last known state
      // rather than showing an incorrect result.
    } finally {
      state.pendingChecks.delete(node.code);
    }
  }

  function dueRealIcmpNodes(store) {
    const now = Date.now();
    return store.getSnapshot().nodes.filter((node) => {
      if (node.mode !== 'real' || node.method !== 'icmp') return false;
      if (!(node.target_host || node.target_ip)) return false;
      const intervalMs = Math.max(30, Number(node.interval_seconds) || 60) * 1000;
      const lastMs = node.lastChecked ? new Date(node.lastChecked).getTime() : 0;
      return (now - lastMs) >= intervalMs;
    });
  }

  function startRealChecks(store) {
    if (state.realCheckTimer) return;
    const tick = () => dueRealIcmpNodes(store).forEach((node) => runIcmpCheck(node, store));
    tick();
    state.realCheckTimer = window.setInterval(tick, 5000);
  }

  function requiredFieldsFor(method) {
    const base = ['name', 'code', 'region', 'country', 'provider'];
    if (method === 'icmp') return [...base, 'target_host'];
    if (method === 'tcp') return [...base, 'target_host', 'target_port'];
    if (method === 'http') return [...base, 'health_url'];
    return base;
  }

  function validateServerForm(form) {
    const method = String(new FormData(form).get('method') || 'icmp');
    const missing = requiredFieldsFor(method).filter((name) => !String(form.elements[name]?.value || '').trim());
    const invalid = [];
    if (method === 'tcp') {
      const port = Number(form.elements.target_port?.value || 0);
      if (port < 1 || port > 65535) invalid.push('port 1-65535');
    }
    if (method === 'http') {
      const url = String(form.elements.health_url?.value || '').trim();
      if (url && !/^https?:\/\//i.test(url)) invalid.push('HTTP/HTTPS URL');
    }
    return { method, missing, invalid };
  }

  function updateMethodUi(modal) {
    const form = modal.querySelector('[data-infra-server-form]');
    if (!form) return;
    const method = String(new FormData(form).get('method') || 'icmp');
    modal.querySelectorAll('[data-infra-method-card]').forEach((card) => {
      card.classList.toggle('is-active', card.getAttribute('data-infra-method-card') === method);
    });
    modal.querySelectorAll('[data-method-section]').forEach((section) => {
      const sectionName = section.getAttribute('data-method-section');
      section.hidden = (sectionName === 'mock' && method !== 'mock') || (sectionName === 'real' && method === 'mock');
    });
    modal.querySelectorAll('[data-method-field]').forEach((field) => {
      const methods = String(field.getAttribute('data-method-field') || '').split(/\s+/);
      field.hidden = !methods.includes(method);
    });
    const note = modal.querySelector('[data-infra-method-note]');
    if (note) note.textContent = METHOD_HELP[method] || '';
    const validation = modal.querySelector('[data-infra-server-validation]');
    if (validation) {
      validation.classList.remove('is-error');
      validation.textContent = method === 'mock'
        ? 'Mock entries update the current session only and remain separate from real monitoring targets.'
        : method === 'icmp'
          ? 'Network Ping targets get a live ICMP check from the TRACS backend as soon as they are added.'
          : 'Real targets are registered as awaiting backend checks. TRACS will display results after VPS-side monitoring for this method is wired.';
    }
  }

  function activateModalTab(modal, name) {
    modal.querySelectorAll('[data-infra-modal-tab]').forEach((tab) => {
      const active = tab.getAttribute('data-infra-modal-tab') === name;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    modal.querySelectorAll('[data-infra-modal-pane]').forEach((pane) => {
      pane.classList.toggle('is-active', pane.getAttribute('data-infra-modal-pane') === name);
    });
  }

  function renderServerRegistry(modal, store) {
    const target = modal?.querySelector('[data-infra-server-registry]');
    if (!target || !store) return;
    const snapshot = store.getSnapshot();
    const realCount = snapshot.nodes.filter((node) => node.mode === 'real').length;
    const mockCount = snapshot.nodes.length - realCount;
    target.innerHTML = `
      <div class="infra-server-registry__head">
        <span>Current Servers</span>
        <strong>${esc(snapshot.nodes.length)} total</strong>
        <em>${esc(realCount)} real / ${esc(mockCount)} mock</em>
      </div>
      ${snapshot.nodes.length ? snapshot.nodes.map((node) => `
        <article class="infra-server-registry__row ${statusClass(node.status)}">
          <span class="infra-server-code">${esc(node.code)}</span>
          <div class="infra-server-registry__main">
            <div class="infra-server-registry__title">
              <strong>${esc(node.name)}</strong>
              <span class="infra-badge ${node.mode === 'real' ? 'is-real' : 'is-mock'}">${esc(modeLabel(node))}</span>
              <span class="infra-badge is-method">${esc((node.method || 'mock').toUpperCase())}</span>
            </div>
            <em>${esc(node.region || '--')} / ${esc(node.country || '--')} / ${esc(node.provider || '--')}</em>
            <small>${esc(methodLabel(node.method))}: ${esc(monitoringTarget(node))}</small>
            ${node.mode === 'real' && node.method === 'icmp' ? '<small class="infra-server-registry__backend">Live ICMP check runs from the TRACS backend while this tab stays open.</small>' : ''}
            ${node.mode === 'real' && node.method !== 'icmp' ? '<small class="infra-server-registry__backend">Awaiting VPS backend worker results. Only Network Ping runs live checks today.</small>' : ''}
          </div>
          <div class="infra-server-registry__quick">
            ${statusChip(node.status)}
            <span>${esc(statText(node, ms(node.latency)))}</span>
            <span>${esc(statText(node, `${Number(node.packetLoss || 0).toFixed(2)}% loss`))}</span>
            <span>${esc(uptimeText(node, pct(node.uptime)))}</span>
            <time>${esc(node.lastChecked ? Infra.formatTime(node.lastChecked) : 'Not checked')}</time>
          </div>
          <div class="infra-server-registry__remove" data-infra-remove-wrap="${esc(node.code)}">
            <button type="button" class="btn btn-ghost btn-icon" data-infra-edit-server="${esc(node.code)}" title="Edit" aria-label="Edit ${esc(node.name)}">
              <i data-lucide="pencil" class="icon-sm"></i>
            </button>
            <button type="button" class="btn btn-ghost btn-icon" data-infra-remove-server="${esc(node.code)}" title="Remove" aria-label="Remove ${esc(node.name)}">
              <i data-lucide="trash-2" class="icon-sm"></i>
            </button>
          </div>
        </article>
      `).join('') : `
        <div class="infra-server-empty">
          <i data-lucide="server-off" class="icon-sm"></i>
          <strong>No servers registered</strong>
          <p>Add a mock target for demo data or a real target for future VPS-side monitoring.</p>
        </div>
      `}
    `;
    if (window.lucide) window.lucide.createIcons();
  }

  function setFieldValue(form, name, value) {
    const field = form.elements[name];
    if (field) field.value = value ?? '';
  }

  function enterEditMode(modal, node) {
    const form = modal.querySelector('[data-infra-server-form]');
    if (!form || !node) return;
    state.editingCode = node.code;

    const method = node.method || 'mock';
    const methodRadio = form.querySelector(`input[name="method"][value="${CSS.escape(method)}"]`);
    if (methodRadio) methodRadio.checked = true;

    setFieldValue(form, 'name', node.name);
    setFieldValue(form, 'code', node.code);
    setFieldValue(form, 'region', node.region);
    setFieldValue(form, 'country', node.country);
    setFieldValue(form, 'provider', node.provider);
    setFieldValue(form, 'target_host', node.target_host);
    setFieldValue(form, 'target_port', node.target_port);
    setFieldValue(form, 'health_url', node.health_url);
    setFieldValue(form, 'expected_status', node.expected_status || 200);
    setFieldValue(form, 'expected_keyword', node.expected_keyword);
    setFieldValue(form, 'packet_count', node.packet_count || 4);
    setFieldValue(form, 'timeout_seconds', node.timeout_seconds || 5);
    setFieldValue(form, 'interval_seconds', node.interval_seconds || 60);
    setFieldValue(form, 'status', ['healthy', 'recovery', 'degraded', 'critical', 'maintenance'].includes(node.status) ? node.status : 'healthy');
    setFieldValue(form, 'latency', node.latency || 24);
    setFieldValue(form, 'packetLoss', node.packetLoss || 0.02);
    setFieldValue(form, 'uptime', node.uptime || 99.99);

    const banner = modal.querySelector('[data-infra-edit-banner]');
    if (banner) {
      banner.hidden = false;
      const codeEl = banner.querySelector('[data-infra-edit-code]');
      if (codeEl) codeEl.textContent = node.code;
    }
    // Code is the upsert key server-side; keep it fixed during edit so a
    // typo doesn't silently create a second entry alongside the original.
    if (form.elements.code) form.elements.code.readOnly = true;
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.innerHTML = '<i data-lucide="save" class="icon-sm"></i>Save Changes';
    if (window.lucide) window.lucide.createIcons();

    updateMethodUi(modal);
    activateModalTab(modal, 'add');
    form.querySelector('input[name="name"]')?.focus();
  }

  function exitEditMode(modal) {
    state.editingCode = null;
    const banner = modal.querySelector('[data-infra-edit-banner]');
    if (banner) banner.hidden = true;
    const form = modal.querySelector('[data-infra-server-form]');
    if (form?.elements.code) form.elements.code.readOnly = false;
    const submitButton = form?.querySelector('button[type="submit"]');
    if (submitButton) submitButton.innerHTML = '<i data-lucide="plus" class="icon-sm"></i>Add Server';
    if (window.lucide) window.lucide.createIcons();
  }

  function bindServerModal(store) {
    const modal = document.querySelector('[data-infra-server-modal]');
    if (!modal || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';
    const form = modal.querySelector('[data-infra-server-form]');

    function openModal() {
      tracsOpenModalElement(modal);
      activateModalTab(modal, 'add');
      updateMethodUi(modal);
      renderServerRegistry(modal, store);
      form?.querySelector('input[name="name"]')?.focus();
    }

    function closeModal() {
      tracsCloseModalElement(modal);
      exitEditMode(modal);
    }

    document.querySelectorAll('[data-infra-manage-open]').forEach((button) => {
      button.addEventListener('click', openModal);
    });
    modal.querySelectorAll('[data-infra-manage-close]').forEach((button) => {
      button.addEventListener('click', closeModal);
    });
    modal.querySelectorAll('[data-infra-modal-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        const name = tab.getAttribute('data-infra-modal-tab');
        activateModalTab(modal, name);
        if (name === 'servers') renderServerRegistry(modal, store);
      });
    });
    form?.addEventListener('change', (event) => {
      if (event.target.name === 'method') updateMethodUi(modal);
    });
    modal.querySelector('[data-infra-form-reset]')?.addEventListener('click', () => {
      form.reset();
      form.elements.method.value = 'icmp';
      form.elements.expected_status.value = '200';
      form.elements.packet_count.value = '4';
      form.elements.timeout_seconds.value = '5';
      form.elements.interval_seconds.value = '60';
      exitEditMode(modal);
      updateMethodUi(modal);
    });
    modal.querySelector('[data-infra-edit-cancel]')?.addEventListener('click', () => {
      form.reset();
      form.elements.method.value = 'icmp';
      form.elements.expected_status.value = '200';
      form.elements.packet_count.value = '4';
      form.elements.timeout_seconds.value = '5';
      form.elements.interval_seconds.value = '60';
      exitEditMode(modal);
      updateMethodUi(modal);
    });
    modal.addEventListener('click', async (event) => {
      const edit = event.target.closest('[data-infra-edit-server]');
      const remove = event.target.closest('[data-infra-remove-server]');
      const cancelRemove = event.target.closest('[data-infra-cancel-remove]');
      const confirmRemove = event.target.closest('[data-infra-confirm-remove]');
      if (edit) {
        const code = edit.getAttribute('data-infra-edit-server');
        const node = store.getSnapshot().nodes.find((item) => item.code === code);
        if (node) enterEditMode(modal, node);
        return;
      }
      if (cancelRemove) {
        const code = cancelRemove.getAttribute('data-infra-cancel-remove');
        renderServerRegistry(modal, store);
        if (code) modal.querySelector(`[data-infra-remove-server="${CSS.escape(code)}"]`)?.focus();
        return;
      }
      if (confirmRemove) {
        const code = confirmRemove.getAttribute('data-infra-confirm-remove');
        const snapshot = store.getSnapshot();
        const target = snapshot.nodes.find((item) => item.code === code);
        if (target?.mode === 'real' || SEED_CODES.has(code)) {
          confirmRemove.disabled = true;
          try {
            const response = await fetch('/api/infrastructure-server-delete.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ code }),
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || payload.success === false) {
              confirmRemove.disabled = false;
              showToast(payload?.message || 'The server could not be removed. Please try again.', 'error', { context: 'modal', position: 'modal-center', modal });
              return;
            }
          } catch (error) {
            confirmRemove.disabled = false;
            showToast('The server could not be removed. Please check your connection and try again.', 'error', { context: 'modal', position: 'modal-center', modal });
            return;
          }
        }
        const nodes = snapshot.nodes.filter((node) => node.code !== code);
        if (state.selectedCode === code) state.selectedCode = nodes[0]?.code || '';
        store.ingest({ ...snapshot, nodes });
        renderServerRegistry(modal, store);
        modal.querySelector('[data-infra-server-registry]')?.focus();
        showToast('Server removed from monitoring.','success',{context:'modal',position:'modal-center',modal});
        return;
      }
      if (!remove) return;
      const code = remove.getAttribute('data-infra-remove-server');
      const snapshot = store.getSnapshot();
      const node = snapshot.nodes.find((item) => item.code === code);
      const wrap = modal.querySelector(`[data-infra-remove-wrap="${CSS.escape(code)}"]`);
      if (!wrap || !node) return;
      wrap.innerHTML = `
        <div class="infra-remove-confirm">
          <strong>Remove this server from monitoring?</strong>
          <p>This will remove ${esc(node.name)} / ${esc(node.code)} from the server registry. Historical monitoring data should not be deleted unless explicitly requested.</p>
          <div>
            <button type="button" class="btn btn-ghost btn-sm" data-infra-cancel-remove="${esc(node.code)}">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" data-infra-confirm-remove="${esc(node.code)}">Remove Server</button>
          </div>
        </div>
      `;
      wrap.querySelector('[data-infra-cancel-remove]')?.focus();
      if (window.lucide) window.lucide.createIcons();
    });
    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const result = validateServerForm(form);
      const validation = modal.querySelector('[data-infra-server-validation]');
      if (result.missing.length || result.invalid.length) {
        const missing = result.missing.map((name) => name.replace(/_/g, ' ')).join(', ');
        const invalid = result.invalid.join(', ');
        if (validation) {
          validation.classList.add('is-error');
          validation.textContent = `Complete required fields${missing ? `: ${missing}` : ''}${invalid ? `; check ${invalid}` : ''}.`;
        }
        handleModalError({
          modal,
          message:`Complete the required server fields${missing ? `: ${missing}` : ''}${invalid ? `; check ${invalid}` : ''}.`,
          focus:form.querySelector(':invalid, [data-required-base]')
        });
        return;
      }
      const node = nodeFromForm(form);
      if (!node) {
        handleModalError({modal,message:'The server details could not be prepared. Please review the form and try again.'});
        return;
      }
      const isEditing = !!state.editingCode;
      const originalNode = isEditing ? store.getSnapshot().nodes.find((item) => item.code === state.editingCode) : null;
      const button=event.submitter || form.querySelector('button[type="submit"]');
      if(button && !setButtonLoading(button, isEditing ? 'Saving changes...' : 'Saving...'))return;

      if (isEditing && originalNode?.mode === 'real' && node.mode !== 'real') {
        // Downgrading a persisted real target back to Demo Data — drop the
        // DB row so it doesn't keep getting live-checked in the background.
        try {
          await fetch('/api/infrastructure-server-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: originalNode.code }),
          });
        } catch (error) {
          // Best-effort: proceed with the local mock update either way.
        }
      }

      let finalNode = node;
      if (node.mode === 'real') {
        try {
          const response = await fetch('/api/infrastructure-server-create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              code: node.code,
              name: node.name,
              region: node.region,
              country: node.country,
              provider: node.provider,
              method: node.method,
              target_host: node.target_host,
              target_port: node.target_port,
              health_url: node.health_url,
              expected_status: node.expected_status,
              expected_keyword: node.expected_keyword,
              packet_count: node.packet_count,
              timeout_seconds: node.timeout_seconds,
              interval_seconds: node.interval_seconds,
            }),
          });
          const payload = await response.json().catch(() => null);
          if (!response.ok || !payload || payload.success === false) {
            resetButtonLoading(button);
            handleModalError({ modal, message: payload?.message || 'The server could not be saved. Please try again.' });
            return;
          }
          finalNode = dbRowToNode(payload.data);
        } catch (error) {
          resetButtonLoading(button);
          handleModalError({ modal, message: 'The server could not be saved. Please check your connection and try again.' });
          return;
        }
      }

      const snapshot = store.getSnapshot();
      const nodes = [finalNode, ...snapshot.nodes.filter((item) => item.code !== finalNode.code)];
      showModalSuccessAndClose({
        modal,
        button,
        message: isEditing ? 'Server updated.' : 'Server added to monitoring.',
        close:()=>closeModal(),
        onAfterClose:()=>{
          state.selectedCode = finalNode.code;
          store.ingest({ ...snapshot, nodes });
          if (finalNode.mode === 'real' && finalNode.method === 'icmp') runIcmpCheck(finalNode, store);
          form.reset();
          form.elements.method.value = 'icmp';
          form.elements.status.value = 'healthy';
          form.elements.latency.value = '24';
          form.elements.packetLoss.value = '0.02';
          form.elements.uptime.value = '99.990';
          form.elements.expected_status.value = '200';
          form.elements.packet_count.value = '4';
          form.elements.timeout_seconds.value = '5';
          form.elements.interval_seconds.value = '60';
          updateMethodUi(modal);
          renderServerRegistry(modal, store);
        }
      });
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
  }

  function init() {
    const page = document.querySelector('[data-infra-pulse-page]');
    const dashboardWidgets = Array.from(document.querySelectorAll('[data-infra-dashboard-widget]'));
    const tvWidgets = Array.from(document.querySelectorAll('[data-infra-tv-widget]'));
    if (!page && dashboardWidgets.length === 0 && tvWidgets.length === 0) return;

    initDashboardWidgetSliders();
    const startStore = () => {
      if (state.store) return state.store;
      const persistedReal = Array.isArray(window.TRACS_INFRA_REAL_SERVERS)
        ? window.TRACS_INFRA_REAL_SERVERS.map(dbRowToNode)
        : [];
      const hiddenSeedCodes = Array.isArray(window.TRACS_INFRA_HIDDEN_SEED_CODES)
        ? window.TRACS_INFRA_HIDDEN_SEED_CODES
        : [];
      const store = Infra.createSharedStore({ intervalMs: 4000, extraNodes: persistedReal, hiddenSeedCodes });
      state.store = store;
      bindPage(page, store);
      bindServerModal(store);
      store.subscribe((snapshot) => {
        if (page) renderPage(page, snapshot);
        dashboardWidgets.forEach((target) => renderInfrastructurePulseDashboardWidget(target, snapshot));
        tvWidgets.forEach((target) => renderInfrastructurePulseTVWidget(target, snapshot));
        if (window.lucide) window.lucide.createIcons();
      });
      store.start();
      startRealChecks(store);
      window.addEventListener('pagehide', () => {
        store.stop();
        if (state.realCheckTimer) {
          window.clearInterval(state.realCheckTimer);
          state.realCheckTimer = null;
        }
      }, { once: true });
      return store;
    };

    if (page || tvWidgets.length > 0) {
      startStore();
    } else if (dashboardWidgets.length > 0 && typeof window.initWhenVisible === 'function') {
      window.initWhenVisible(dashboardWidgets[0], startStore, { rootMargin: '240px 0px' });
    } else {
      startStore();
    }
  }

  window.renderInfrastructurePulseDashboardWidget = renderInfrastructurePulseDashboardWidget;
  window.renderInfrastructurePulseTVWidget = renderInfrastructurePulseTVWidget;
  window.TRACSInfrastructurePulse = {
    init,
    renderPage,
    renderInfrastructurePulseDashboardWidget,
    renderInfrastructurePulseTVWidget,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
