(function () {
  'use strict';

  const API_ENDPOINTS = {
    status: '/api/infrastructure/status',
    metrics: '/api/infrastructure/metrics',
    events: '/api/infrastructure/events',
    stream: '/api/infrastructure/stream',
  };

  /*
   * Future backend handoff:
   * TODO(real ping worker): move all probing to backend workers; never ping real IPs from the browser.
   * TODO(adaptive interval monitoring): healthy targets every 10-15s, warning every 5s, critical every 1s.
   * TODO(Redis/cache layer): publish latest status into Redis or another low-latency cache for dashboard reads.
   * TODO(SSE/WebSocket stream): expose API_ENDPOINTS.stream for EventSource or WebSocket push updates.
   * TODO(database aggregation): retain raw 1s samples briefly, 1m rollups longer, and 1h rollups long term.
   * TODO(TRACS correlation): link incident records to cases, reminders, ticker announcements, and TV Mode.
   */

  const DATACENTERS = [
    {
      code: 'DCI',
      name: 'DCI Cibitung',
      country: 'Indonesia',
      region: 'Jabodetabek',
      status: 'recovery',
      latency: 18,
      uptime: 99.982,
      packetLoss: 0.02,
      cluster: { x: 154, y: 415 },
      overview: { x: 272, y: 242 },
    },
    {
      code: 'IDB',
      name: 'IDCloudHost Bogor',
      country: 'Indonesia',
      region: 'Jabodetabek',
      status: 'healthy',
      latency: 16,
      uptime: 99.995,
      packetLoss: 0.01,
      cluster: { x: 229, y: 488 },
      overview: { x: 292, y: 260 },
    },
    {
      code: 'CY1',
      name: 'Cyber 1',
      country: 'Indonesia',
      region: 'Jabodetabek',
      status: 'warning',
      latency: 35,
      uptime: 99.931,
      packetLoss: 0.18,
      cluster: { x: 318, y: 421 },
      overview: { x: 310, y: 247 },
    },
    {
      code: 'BCD',
      name: 'BC-DC',
      country: 'Indonesia',
      region: 'Jabodetabek',
      status: 'maintenance',
      latency: 24,
      uptime: 99.961,
      packetLoss: 0.05,
      cluster: { x: 425, y: 397 },
      overview: { x: 327, y: 269 },
    },
    {
      code: 'BTI',
      name: 'Bali Towerindo',
      country: 'Indonesia',
      region: 'Jabodetabek',
      status: 'healthy',
      latency: 21,
      uptime: 99.988,
      packetLoss: 0.02,
      cluster: { x: 489, y: 487 },
      overview: { x: 350, y: 283 },
    },
    {
      code: 'DR3',
      name: 'Duren 3',
      country: 'Indonesia',
      region: 'Jabodetabek',
      status: 'healthy',
      latency: 20,
      uptime: 99.991,
      packetLoss: 0.01,
      cluster: { x: 257, y: 375 },
      overview: { x: 296, y: 230 },
    },
    {
      code: 'SG3',
      name: 'Equinix SG3',
      country: 'Singapore',
      region: 'Singapore',
      status: 'warning',
      latency: 41,
      uptime: 99.902,
      packetLoss: 0.35,
      cluster: { x: 656, y: 360 },
      overview: { x: 696, y: 165 },
    },
    {
      code: 'EGH',
      name: 'Epsilon Global Hub',
      country: 'Singapore',
      region: 'Singapore',
      status: 'healthy',
      latency: 38,
      uptime: 99.973,
      packetLoss: 0.04,
      cluster: { x: 752, y: 326 },
      overview: { x: 723, y: 190 },
    },
    {
      code: 'NDS',
      name: 'NeutraDC SNG-3',
      country: 'Singapore',
      region: 'Singapore',
      status: 'critical',
      latency: 88,
      uptime: 99.721,
      packetLoss: 1.28,
      cluster: { x: 801, y: 429 },
      overview: { x: 742, y: 154 },
    },
  ];

  const EVENT_SEEDS = [
    {
      id: 'evt-sg3-loss',
      type: 'warning',
      title: 'Packet loss detected at Equinix SG3',
      datacenterCode: 'SG3',
      detail: 'Mock packet loss crossed the prototype warning threshold.',
      integration: 'ticker',
    },
    {
      id: 'evt-bcdc-maintenance',
      type: 'maintenance',
      title: 'Scheduled maintenance at BC-DC',
      datacenterCode: 'BCD',
      detail: 'Maintenance window placeholder for reminder integration.',
      integration: 'reminders',
    },
    {
      id: 'evt-cyber-stabilized',
      type: 'recovery',
      title: 'Latency stabilized at Cyber 1',
      datacenterCode: 'CY1',
      detail: 'Jitter is settling after a simulated route adjustment.',
      integration: 'cases',
    },
    {
      id: 'evt-dci-recovery',
      type: 'recovery',
      title: 'Recovery completed at DCI Cibitung',
      datacenterCode: 'DCI',
      detail: 'Recovery ring remains visible for operator confidence.',
      integration: 'cases',
    },
    {
      id: 'evt-nds-outage',
      type: 'critical',
      title: 'Critical reachability drop at NeutraDC SNG-3',
      datacenterCode: 'NDS',
      detail: 'Prototype critical incident for NOC escalation flow.',
      integration: 'notifications',
    },
  ];

  const STATUS_RANK = {
    critical: 5,
    warning: 4,
    maintenance: 3,
    recovery: 2,
    healthy: 1,
  };

  const STATUS_LABELS = {
    critical: 'Critical',
    warning: 'Degraded',
    maintenance: 'Maintenance',
    recovery: 'Recovery',
    healthy: 'Healthy',
  };

  const STATUS_COPY = {
    critical: 'Investigating outage',
    warning: 'Latency or packet loss elevated',
    maintenance: 'Planned work in progress',
    recovery: 'Stabilizing after incident',
    healthy: 'Operating normally',
  };

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function statusRank(status) {
    return STATUS_RANK[status] || 0;
  }

  function statusLabel(status) {
    return STATUS_LABELS[status] || 'Unknown';
  }

  function statusCopy(status) {
    return STATUS_COPY[status] || 'Awaiting telemetry';
  }

  function nowIso() {
    return new Date().toISOString();
  }

  function makeHistory(baseLatency, index) {
    const points = [];
    for (let i = 0; i < 36; i += 1) {
      const wave = Math.sin((i + index) / 4) * 2.8;
      const smaller = Math.cos((i + index * 2) / 6) * 1.7;
      points.push(Math.max(4, Math.round(baseLatency + wave + smaller + (index % 3))));
    }
    return points;
  }

  function withHistories(nodes) {
    return nodes.map((node, index) => ({
      ...node,
      lastChecked: nowIso(),
      history: makeHistory(node.latency, index),
    }));
  }

  function average(values) {
    if (!values.length) return 0;
    return values.reduce((sum, value) => sum + Number(value || 0), 0) / values.length;
  }

  function regionSummary(nodes, country) {
    const regionNodes = nodes.filter((node) => node.country === country);
    const worst = regionNodes.reduce((acc, node) => (statusRank(node.status) > statusRank(acc.status) ? node : acc), { status: 'healthy' });
    return {
      country,
      status: worst.status,
      label: statusLabel(worst.status),
      latency: Math.round(average(regionNodes.map((node) => node.latency))),
      incidents: regionNodes.filter((node) => ['critical', 'warning', 'maintenance'].includes(node.status)).length,
    };
  }

  function computeSummary(nodes) {
    const worst = nodes.reduce((acc, node) => (statusRank(node.status) > statusRank(acc.status) ? node : acc), { status: 'healthy' });
    const activeIncidents = nodes.filter((node) => ['critical', 'warning', 'maintenance'].includes(node.status)).length;
    const warningCount = nodes.filter((node) => node.status === 'warning').length;
    const criticalCount = nodes.filter((node) => node.status === 'critical').length;
    const globalStatus = criticalCount > 0 ? 'critical' : (warningCount > 0 || activeIncidents > 0 ? 'warning' : 'healthy');
    return {
      globalStatus,
      globalStatusLabel: globalStatus === 'critical' ? 'Degraded' : statusLabel(globalStatus),
      activeIncidents,
      averageLatency: Math.round(average(nodes.map((node) => node.latency))),
      uptime30d: Number(average(nodes.map((node) => node.uptime)).toFixed(3)),
      indonesia: regionSummary(nodes, 'Indonesia'),
      singapore: regionSummary(nodes, 'Singapore'),
      worstAffected: activeIncidents > 0 ? {
        code: worst.code,
        name: worst.name,
        status: worst.status,
        label: statusLabel(worst.status),
        latency: Math.round(worst.latency),
        packetLoss: Number(worst.packetLoss.toFixed(2)),
      } : null,
      healthyCount: nodes.filter((node) => node.status === 'healthy').length,
      nodeCount: nodes.length,
    };
  }

  function eventTime(offsetSeconds) {
    return new Date(Date.now() - offsetSeconds * 1000).toISOString();
  }

  function initialEvents() {
    return EVENT_SEEDS.map((event, index) => ({
      ...event,
      createdAt: eventTime((index + 1) * 52),
    }));
  }

  function createSnapshot() {
    const nodes = withHistories(clone(DATACENTERS));
    return {
      generatedAt: nowIso(),
      endpoints: { ...API_ENDPOINTS },
      nodes,
      summary: computeSummary(nodes),
      events: initialEvents(),
    };
  }

  function driftFor(node, tick) {
    const base = {
      critical: 5.8,
      warning: 3.4,
      maintenance: 1.2,
      recovery: -1.1,
      healthy: 0.6,
    }[node.status] || 0;
    const wave = Math.sin((tick + node.code.length) / 3) * 1.8;
    const jitter = Math.cos((tick + node.name.length) / 5) * 1.1;
    return base + wave + jitter;
  }

  function nextNode(node, tick, index) {
    const drift = driftFor(node, tick + index);
    const floor = node.country === 'Singapore' ? 32 : 10;
    const ceiling = node.status === 'critical' ? 130 : (node.status === 'warning' ? 76 : 48);
    const latency = Math.min(ceiling, Math.max(floor, Math.round(node.latency + drift)));
    const packetBump = {
      critical: 0.08,
      warning: 0.03,
      maintenance: 0.01,
      recovery: -0.01,
      healthy: -0.004,
    }[node.status] || 0;
    const packetLoss = Math.max(0, Math.min(4, Number((node.packetLoss + packetBump + Math.sin(tick / 4 + index) * 0.015).toFixed(2))));
    const history = [...node.history.slice(-35), latency];
    return {
      ...node,
      latency,
      packetLoss,
      lastChecked: nowIso(),
      history,
    };
  }

  function rotateEvent(snapshot, tick) {
    if (tick % 13 !== 0) return snapshot.events;
    const seed = EVENT_SEEDS[(tick / 13) % EVENT_SEEDS.length | 0];
    const node = snapshot.nodes.find((item) => item.code === seed.datacenterCode);
    const event = {
      ...seed,
      id: `${seed.id}-${tick}`,
      createdAt: nowIso(),
      detail: node ? `${seed.detail} Latest latency ${Math.round(node.latency)} ms.` : seed.detail,
    };
    return [event, ...snapshot.events].slice(0, 10);
  }

  function advanceSnapshot(snapshot, tick) {
    const nodes = snapshot.nodes.map((node, index) => nextNode(node, tick, index));
    const next = {
      ...snapshot,
      generatedAt: nowIso(),
      nodes,
      events: rotateEvent({ ...snapshot, nodes }, tick),
    };
    next.summary = computeSummary(nodes);
    return next;
  }

  function formatTime(value) {
    const date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) return '--:--:--';
    return date.toLocaleTimeString('en-GB', { hour12: false });
  }

  function formatAgo(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'just now';
    const seconds = Math.max(1, Math.round((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return `${seconds}s ago`;
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    return `${Math.round(minutes / 60)}h ago`;
  }

  function sparklinePath(points, width = 116, height = 32, padding = 3) {
    const data = points.length ? points : [0];
    const min = Math.min(...data);
    const max = Math.max(...data);
    const spread = Math.max(1, max - min);
    const step = data.length > 1 ? (width - padding * 2) / (data.length - 1) : 0;
    return data.map((value, index) => {
      const x = padding + index * step;
      const y = height - padding - ((value - min) / spread) * (height - padding * 2);
      return `${index === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)}`;
    }).join(' ');
  }

  function createMockStore(options = {}) {
    let snapshot = createSnapshot();
    let timer = null;
    let tick = 0;
    const intervalMs = Number(options.intervalMs || 1000);
    const listeners = new Set();

    function emit() {
      listeners.forEach((listener) => listener(snapshot));
    }

    function step() {
      tick += 1;
      snapshot = advanceSnapshot(snapshot, tick);
      emit();
    }

    return {
      endpoints: { ...API_ENDPOINTS },
      getSnapshot: () => snapshot,
      subscribe(listener) {
        if (typeof listener !== 'function') return () => {};
        listeners.add(listener);
        listener(snapshot);
        return () => listeners.delete(listener);
      },
      start() {
        if (timer) return;
        timer = window.setInterval(step, intervalMs);
      },
      stop() {
        if (!timer) return;
        window.clearInterval(timer);
        timer = null;
      },
      ingest(nextSnapshot) {
        if (!nextSnapshot || !Array.isArray(nextSnapshot.nodes)) return;
        snapshot = {
          ...snapshot,
          ...nextSnapshot,
          summary: nextSnapshot.summary || computeSummary(nextSnapshot.nodes),
          generatedAt: nextSnapshot.generatedAt || nowIso(),
        };
        emit();
      },
      step,
    };
  }

  function createSharedStore(options = {}) {
    if (!window.__tracsInfrastructureStore) {
      window.__tracsInfrastructureStore = createMockStore(options);
    }
    return window.__tracsInfrastructureStore;
  }

  function connectEventSource(store, url = API_ENDPOINTS.stream) {
    if (!window.EventSource) return null;
    const source = new EventSource(url);
    source.addEventListener('message', (event) => {
      try {
        const payload = JSON.parse(event.data);
        if (payload?.nodes && payload?.summary) {
          store.ingest?.(payload);
        }
      } catch (error) {
        // Keep the prototype resilient while backend streams are still being wired.
      }
    });
    return source;
  }

  window.TRACSInfrastructure = {
    endpoints: API_ENDPOINTS,
    datacenters: clone(DATACENTERS),
    createSnapshot,
    computeSummary,
    createMockStore,
    createSharedStore,
    connectEventSource,
    formatTime,
    formatAgo,
    sparklinePath,
    statusRank,
    statusLabel,
    statusCopy,
  };
})();
