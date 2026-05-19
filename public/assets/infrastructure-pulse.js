(function () {
  'use strict';

  const Infra = window.TRACSInfrastructure;
  if (!Infra) return;

  const state = {
    selectedCode: 'NDS',
    lastEventKey: '',
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
    if (status === 'warning') return 'b-warning';
    if (status === 'maintenance') return 'b-pending';
    if (status === 'recovery') return 'b-info';
    if (status === 'healthy') return 'b-active';
    return 'b-low';
  }

  function dashboardAffectedItems(snapshot) {
    const nodeItems = (snapshot.nodes || [])
      .filter((node) => node.status !== 'healthy')
      .sort((a, b) => (Infra.statusRank(b.status) - Infra.statusRank(a.status)) || (b.latency - a.latency))
      .slice(0, 3)
      .map((node) => ({
        name: node.code,
        detail: node.name,
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
      name: 'All clear',
      detail: 'No affected datacenter',
      status: 'healthy',
      label: 'Stable',
    }];
  }

  function dashboardAffectedPills(items) {
    const pills = items.map((item) => `
      <span class="badge infra-dashboard-widget__affected-pill ${tracsStatusBadgeClass(item.status)}">
        <span class="badge-dot"></span>
        <strong>${esc(item.name)}</strong>
        <em>${esc(item.label)}</em>
      </span>
    `).join('');
    return items.length > 1 ? pills + pills : pills;
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
        status: summary.averageLatency > 60 ? 'warning' : 'healthy',
        icon: 'activity',
      },
      {
        label: '30D Uptime',
        value: pct(summary.uptime30d),
        meta: 'Prototype aggregate',
        status: summary.uptime30d < 99.9 ? 'warning' : 'healthy',
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

  function overviewNode(label, value, status, x, y, extra) {
    return `
      <g class="infra-map-node infra-map-node--overview ${statusClass(status)}" transform="translate(${x} ${y})">
        <circle class="node-ripple" r="31"></circle>
        <circle class="node-halo" r="22"></circle>
        <circle class="node-core" r="7"></circle>
        <text class="node-label" x="0" y="-34" text-anchor="middle">${esc(label)}</text>
        <text class="node-sub-label" x="0" y="42" text-anchor="middle">${esc(value)}</text>
        <text class="node-mini-label" x="0" y="58" text-anchor="middle">${esc(extra)}</text>
      </g>
    `;
  }

  function renderOverview(svg, snapshot) {
    const target = svg?.querySelector('[data-infra-overview-nodes]');
    if (!target) return;
    target.innerHTML = [
      overviewNode('Indonesia', snapshot.summary.indonesia.label, snapshot.summary.indonesia.status, 296, 246, `${snapshot.summary.indonesia.latency} ms avg`),
      overviewNode('Singapore', snapshot.summary.singapore.label, snapshot.summary.singapore.status, 720, 170, `${snapshot.summary.singapore.latency} ms avg`),
    ].join('');
  }

  function nodeMarkup(node) {
    const pos = node.cluster;
    const active = state.selectedCode === node.code ? ' is-selected' : '';
    return `
      <g class="infra-map-node ${statusClass(node.status)}${active}" transform="translate(${pos.x} ${pos.y})" data-infra-node="${esc(node.code)}" tabindex="0" role="button" aria-label="${esc(node.name)} ${esc(Infra.statusLabel(node.status))}">
        <circle class="node-ripple" r="26"></circle>
        <circle class="node-halo" r="17"></circle>
        <circle class="node-ring" r="12"></circle>
        <circle class="node-core" r="6"></circle>
        ${node.status === 'maintenance' ? '<path class="node-maint-icon" d="M-5 8 L5 8 L0 -6 Z"></path>' : ''}
        <text class="node-label" x="0" y="-23" text-anchor="middle">${esc(node.code)}</text>
        <text class="node-sub-label" x="0" y="30" text-anchor="middle">${esc(ms(node.latency))}</text>
      </g>
    `;
  }

  function renderMap(svg, snapshot) {
    const target = svg?.querySelector('[data-infra-map-nodes]');
    if (!target) return;
    renderOverview(svg, snapshot);
    target.innerHTML = snapshot.nodes.map(nodeMarkup).join('');
  }

  function selectedNode(snapshot) {
    const worst = snapshot.summary.worstAffected?.code;
    if (!state.selectedCode && worst) state.selectedCode = worst;
    return snapshot.nodes.find((node) => node.code === state.selectedCode)
      || snapshot.nodes.find((node) => node.code === worst)
      || snapshot.nodes[0];
  }

  function renderDetail(panel, snapshot) {
    if (!panel) return;
    const node = selectedNode(snapshot);
    if (!node) return;
    panel.className = `infra-map-detail ${statusClass(node.status)}`;
    panel.innerHTML = `
      <div class="infra-map-detail__head">
        <span>${esc(node.code)}</span>
        ${statusChip(node.status)}
      </div>
      <strong>${esc(node.name)}</strong>
      <p>${esc(Infra.statusCopy(node.status))}</p>
      <div class="infra-map-detail__grid">
        <div><span>Latency</span><b>${esc(ms(node.latency))}</b></div>
        <div><span>Loss</span><b>${esc(Number(node.packetLoss).toFixed(2))}%</b></div>
        <div><span>Uptime</span><b>${esc(pct(node.uptime))}</b></div>
        <div><span>Checked</span><b>${esc(Infra.formatTime(node.lastChecked))}</b></div>
      </div>
    `;
  }

  function ensureMetricRows(container, nodes) {
    if (!container || container.dataset.ready === '1') return;
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
        <span class="infra-metric-time" data-field="checked"></span>
      </article>
    `).join('');
    container.dataset.ready = '1';
  }

  function updateMetricRows(container, snapshot) {
    if (!container) return;
    ensureMetricRows(container, snapshot.nodes);
    snapshot.nodes.forEach((node) => {
      const row = container.querySelector(`[data-infra-metric="${CSS.escape(node.code)}"]`);
      if (!row) return;
      row.className = `infra-metric-row ${statusClass(node.status)}${state.selectedCode === node.code ? ' is-selected' : ''}`;
      const chip = row.querySelector('.infra-status-chip');
      if (chip) {
        chip.className = `infra-status-chip ${statusClass(node.status)}`;
        chip.textContent = Infra.statusLabel(node.status);
      }
      row.querySelector('[data-field="latency"]').textContent = ms(node.latency);
      row.querySelector('[data-field="loss"]').textContent = `${Number(node.packetLoss).toFixed(2)}% loss`;
      row.querySelector('[data-field="uptime"]').textContent = pct(node.uptime);
      row.querySelector('[data-field="checked"]').textContent = Infra.formatTime(node.lastChecked);
      const line = row.querySelector('[data-field="spark-line"]');
      const area = row.querySelector('[data-field="spark-area"]');
      const linePath = Infra.sparklinePath(node.history);
      if (line) line.setAttribute('d', linePath);
      if (area) area.setAttribute('d', `${linePath} L113 32 L3 32 Z`);
    });
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
    renderMap(page.querySelector('[data-infra-map]'), snapshot);
    renderDetail(page.querySelector('[data-infra-detail]'), snapshot);
    updateMetricRows(page.querySelector('[data-infra-metrics]'), snapshot);
    renderEventFeed(page.querySelector('[data-infra-feed]'), snapshot);
    page.querySelectorAll('[data-infra-generated-at]').forEach((node) => {
      node.textContent = Infra.formatTime(snapshot.generatedAt);
    });
  }

  function renderInfrastructurePulseDashboardWidget(target, snapshot) {
    if (!target) return;
    const { summary } = snapshot;
    const affectedItems = dashboardAffectedItems(snapshot);
    const isSliding = affectedItems.length > 1 ? ' is-sliding' : '';
    target.className = `panel infra-dashboard-widget ${statusClass(summary.globalStatus)}`;
    target.innerHTML = `
      <div class="infra-dashboard-widget__head">
        <div>
          <span>Infrastructure Pulse</span>
          <strong>${esc(summary.globalStatusLabel)}</strong>
        </div>
        <i data-lucide="radar"></i>
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
      const select = event.target.closest('[data-infra-node], [data-infra-select]');
      if (!select) return;
      state.selectedCode = select.getAttribute('data-infra-node') || select.getAttribute('data-infra-select');
      renderPage(page, store.getSnapshot());
    });
    page.addEventListener('mouseover', (event) => {
      const select = event.target.closest('[data-infra-node]');
      if (!select) return;
      state.selectedCode = select.getAttribute('data-infra-node');
      renderPage(page, store.getSnapshot());
    });
  }

  function init() {
    const page = document.querySelector('[data-infra-pulse-page]');
    const dashboardWidgets = Array.from(document.querySelectorAll('[data-infra-dashboard-widget]'));
    const tvWidgets = Array.from(document.querySelectorAll('[data-infra-tv-widget]'));
    if (!page && dashboardWidgets.length === 0 && tvWidgets.length === 0) return;

    const store = Infra.createSharedStore({ intervalMs: 1000 });
    bindPage(page, store);
    store.subscribe((snapshot) => {
      if (page) renderPage(page, snapshot);
      dashboardWidgets.forEach((target) => renderInfrastructurePulseDashboardWidget(target, snapshot));
      tvWidgets.forEach((target) => renderInfrastructurePulseTVWidget(target, snapshot));
      if (window.lucide) window.lucide.createIcons();
    });
    store.start();
    window.addEventListener('pagehide', () => store.stop(), { once: true });
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
