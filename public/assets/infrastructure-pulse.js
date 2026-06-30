(function () {
  'use strict';

  const Infra = window.TRACSInfrastructure;
  if (!Infra) return;

  const state = {
    selectedCode: 'NDS',
    lastEventKey: '',
    store: null,
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
            <div><span>Latency</span><b>${esc(ms(node.latency))}</b></div>
            <div><span>Loss</span><b>${esc(Number(node.packetLoss || 0).toFixed(2))}%</b></div>
            <div><span>Uptime</span><b>${esc(pct(node.uptime))}</b></div>
            <div><span>Checked</span><b>${esc(Infra.formatTime(node.lastChecked))}</b></div>
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
              <em>${esc(Infra.statusLabel(item.status))} / ${esc(ms(item.latency))}</em>
            </button>
          `).join('') : '<p class="infra-empty-line">No degraded, critical, maintenance, or recovery servers.</p>'}
        </section>
        <section>
          <div class="infra-report-list-title"><i data-lucide="check-check" class="icon-xs"></i>Stable Nodes</div>
          ${healthy.length ? healthy.slice(0, 6).map((item) => `
            <button type="button" class="infra-report-row is-healthy" data-infra-select="${esc(item.code)}">
              <span>${esc(item.code)}</span>
              <strong>${esc(item.name)}</strong>
              <em>${esc(ms(item.latency))} / ${esc(pct(item.uptime))}</em>
            </button>
          `).join('') : '<p class="infra-empty-line">No healthy servers in this snapshot.</p>'}
        </section>
      </div>
    `;
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
      row.querySelector('[data-field="latency"]').textContent = ms(node.latency);
      row.querySelector('[data-field="loss"]').textContent = `${Number(node.packetLoss).toFixed(2)}% loss`;
      row.querySelector('[data-field="uptime"]').textContent = pct(node.uptime);
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
            <b>${esc(ms(node.latency))}</b>
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
        : 'Real targets are registered as awaiting backend checks. TRACS will display results after VPS-side monitoring is wired.';
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
            ${node.mode === 'real' ? '<small class="infra-server-registry__backend">Awaiting VPS backend worker results. No real browser probing is active.</small>' : ''}
          </div>
          <div class="infra-server-registry__quick">
            ${statusChip(node.status)}
            <span>${esc(ms(node.latency))}</span>
            <span>${esc(Number(node.packetLoss || 0).toFixed(2))}% loss</span>
            <span>${esc(pct(node.uptime))}</span>
            <time>${esc(node.lastChecked ? Infra.formatTime(node.lastChecked) : 'Not checked')}</time>
          </div>
          <div class="infra-server-registry__remove" data-infra-remove-wrap="${esc(node.code)}">
            <button type="button" class="btn btn-ghost btn-sm" data-infra-remove-server="${esc(node.code)}">
              <i data-lucide="trash-2" class="icon-sm"></i>Remove
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
      updateMethodUi(modal);
    });
    modal.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-infra-remove-server]');
      const cancelRemove = event.target.closest('[data-infra-cancel-remove]');
      const confirmRemove = event.target.closest('[data-infra-confirm-remove]');
      if (cancelRemove) {
        renderServerRegistry(modal, store);
        return;
      }
      if (confirmRemove) {
        const code = confirmRemove.getAttribute('data-infra-confirm-remove');
        const snapshot = store.getSnapshot();
        // TODO(soft delete): deactivate this target through the backend API once persisted monitoring history exists.
        const nodes = snapshot.nodes.filter((node) => node.code !== code);
        if (state.selectedCode === code) state.selectedCode = nodes[0]?.code || '';
        store.ingest({ ...snapshot, nodes });
        renderServerRegistry(modal, store);
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
            <button type="button" class="btn btn-ghost btn-sm" data-infra-cancel-remove>Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" data-infra-confirm-remove="${esc(node.code)}">Remove Server</button>
          </div>
        </div>
      `;
      if (window.lucide) window.lucide.createIcons();
    });
    form?.addEventListener('submit', (event) => {
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
      const snapshot = store.getSnapshot();
      const nodes = [node, ...snapshot.nodes.filter((item) => item.code !== node.code)];
      const button=event.submitter || form.querySelector('button[type="submit"]');
      if(button && !setButtonLoading(button,'Saving...'))return;
      showModalSuccessAndClose({
        modal,
        button,
        message:'Server added to monitoring.',
        close:()=>closeModal(),
        onAfterClose:()=>{
          state.selectedCode = node.code;
          store.ingest({ ...snapshot, nodes });
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
      const store = Infra.createSharedStore({ intervalMs: 4000 });
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
      window.addEventListener('pagehide', () => store.stop(), { once: true });
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
