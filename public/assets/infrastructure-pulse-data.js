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
   * TODO(lightweight checks): run agentless ICMP/TCP/HTTP probes from the TRACS VPS only, with bounded
   * concurrency, jitter, timeouts, and backoff so neither TRACS nor monitored servers receive avoidable load.
   * TODO(MonitoringService): add server-side MonitoringService::checkIcmp($host),
   * MonitoringService::checkTcp($host, $port), and MonitoringService::checkHttp($url, $expectedStatus, $expectedKeyword).
   * TODO(database schema): persist infrastructure_servers, infrastructure_monitoring_results,
   * and infrastructure_incidents for history, uptime calculation, charts, TV Mode, and incident recovery timelines.
   * TODO(soft delete): remove actions should deactivate monitoring targets instead of deleting historical records.
   * TODO(RBAC): restrict add/edit/remove and sensitive target visibility to authorized infrastructure roles.
   * TODO(adaptive interval monitoring): default to 30-60s checks, back off noisy/down targets, and avoid sub-30s
   * polling unless an authorized operator explicitly enables a short diagnostic window.
   * TODO(Redis/cache layer): publish latest status into Redis or another low-latency cache for dashboard reads.
   * TODO(SSE/WebSocket stream): expose API_ENDPOINTS.stream for EventSource or WebSocket push updates.
   * TODO(database aggregation): retain raw 1s samples briefly, 1m rollups longer, and 1h rollups long term.
   * TODO(TRACS correlation): link incident records to cases, reminders, ticker announcements, and TV Mode.
   */

  // Add, remove, or disable infrastructure nodes in this single list.
  // Recommended minimum fields: code, name, country, region, status.
  // Optional fields such as provider, facility, incidentCount, and history are
  // normalized below so future API responses can use the same shape.
  const DATACENTERS = [
    {
      id: 'id-jkt-dci-cibitung',
      code: 'DCI',
      shortCode: 'DCI',
      name: 'DCI Cibitung',
      provider: 'DCI Indonesia',
      facility: 'Cibitung Campus',
      country: 'Indonesia',
      city: 'Bekasi Regency',
      region: 'Jabodetabek',
      status: 'recovery',
      latency: 18,
      uptime: 99.982,
      packetLoss: 0.02,
      incidentCount: 1,
      latitude: -6.2929,
      longitude: 107.0986,
    },
    {
      id: 'id-jkt-idcloudhost-bogor',
      code: 'IDB',
      shortCode: 'IDB',
      name: 'IDCloudHost Bogor',
      provider: 'IDCloudHost',
      facility: 'IDC / IDCloudHost',
      country: 'Indonesia',
      city: 'Bogor',
      region: 'Jabodetabek',
      status: 'healthy',
      latency: 16,
      uptime: 99.995,
      packetLoss: 0.01,
      incidentCount: 0,
      latitude: -6.5950,
      longitude: 106.8167,
    },
    {
      id: 'id-jkt-cyber-1',
      code: 'CY1',
      shortCode: 'CY1',
      name: 'Cyber 1',
      provider: 'Cyber Building',
      facility: 'Cyber 1',
      country: 'Indonesia',
      city: 'Jakarta Selatan',
      region: 'Jabodetabek',
      status: 'degraded',
      latency: 35,
      uptime: 99.931,
      packetLoss: 0.18,
      incidentCount: 1,
      latitude: -6.2297,
      longitude: 106.8296,
    },
    {
      id: 'id-jkt-bcdc',
      code: 'BCD',
      shortCode: 'BCD',
      name: 'BC-DC',
      provider: 'BC-DC',
      facility: 'Business Continuity DC',
      country: 'Indonesia',
      city: 'Jakarta',
      region: 'Jabodetabek',
      status: 'maintenance',
      latency: 24,
      uptime: 99.961,
      packetLoss: 0.05,
      incidentCount: 1,
      latitude: -6.1766,
      longitude: 106.8307,
    },
    {
      id: 'id-jkt-bali-tower',
      code: 'BTI',
      shortCode: 'BTI',
      name: 'Bali Tower',
      provider: 'Bali Towerindo',
      facility: 'Bali Tower',
      country: 'Indonesia',
      city: 'Jakarta',
      region: 'Jabodetabek',
      status: 'healthy',
      latency: 21,
      uptime: 99.988,
      packetLoss: 0.02,
      incidentCount: 0,
      latitude: -6.2033,
      longitude: 106.7991,
    },
    {
      id: 'id-jkt-duren-3',
      code: 'DR3',
      shortCode: 'DR3',
      name: 'Duren 3',
      provider: 'Jakarta Edge',
      facility: 'Duren 3',
      country: 'Indonesia',
      city: 'Jakarta Selatan',
      region: 'Jabodetabek',
      status: 'healthy',
      latency: 20,
      uptime: 99.991,
      packetLoss: 0.01,
      incidentCount: 0,
      latitude: -6.2520,
      longitude: 106.8451,
    },
    {
      id: 'sg-equinix-sg3',
      code: 'SG3',
      shortCode: 'SG3',
      name: 'Equinix SG3',
      provider: 'Equinix',
      facility: 'SG3',
      country: 'Singapore',
      city: 'Singapore',
      region: 'Singapore',
      status: 'degraded',
      latency: 41,
      uptime: 99.902,
      packetLoss: 0.35,
      incidentCount: 2,
      latitude: 1.3318,
      longitude: 103.8936,
    },
    {
      id: 'sg-epsilon-global-hub',
      code: 'EGH',
      shortCode: 'EGH',
      name: 'Epsilon Global Hub',
      provider: 'Epsilon Telecommunications',
      facility: 'Global Hub',
      country: 'Singapore',
      city: 'Singapore',
      region: 'Singapore',
      status: 'healthy',
      latency: 38,
      uptime: 99.973,
      packetLoss: 0.04,
      incidentCount: 0,
      latitude: 1.2897,
      longitude: 103.8501,
    },
    {
      id: 'sg-neutradc-sng3',
      code: 'NDS',
      shortCode: 'NDS',
      name: 'NeutraDC SNG-3',
      provider: 'NeutraDC',
      facility: 'SNG-3',
      country: 'Singapore',
      city: 'Singapore',
      region: 'Singapore',
      status: 'critical',
      latency: 88,
      uptime: 99.721,
      packetLoss: 1.28,
      incidentCount: 3,
      latitude: 1.3521,
      longitude: 103.8198,
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
    degraded: 4,
    warning: 4,
    maintenance: 3,
    recovery: 2,
    healthy: 1,
    pending: 0,
  };

  const STATUS_LABELS = {
    critical: 'Critical',
    degraded: 'Degraded',
    warning: 'Degraded',
    maintenance: 'Maintenance',
    recovery: 'Recovery',
    healthy: 'Healthy',
    pending: 'Awaiting Backend',
  };

  const STATUS_COPY = {
    critical: 'Investigating outage',
    degraded: 'Latency or packet loss elevated',
    warning: 'Latency or packet loss elevated',
    maintenance: 'Planned work in progress',
    recovery: 'Stabilizing after incident',
    healthy: 'Operating normally',
    pending: 'No backend monitoring result yet',
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

  function makeSeries(baseValue, index, length, amplitude, floor, ceiling, precision = 0) {
    const points = [];
    const factor = precision > 0 ? 10 ** precision : 1;
    for (let i = 0; i < length; i += 1) {
      const wave = Math.sin((i + index) / 4) * amplitude;
      const smaller = Math.cos((i + index * 2) / 6) * (amplitude * 0.55);
      const value = Math.min(ceiling, Math.max(floor, baseValue + wave + smaller + ((index % 3) * amplitude * 0.16)));
      points.push(Math.round(value * factor) / factor);
    }
    return points;
  }

  function makeHistory(baseLatency, index, baseLoss = 0, baseUptime = 99.95, incidents = 0) {
    const latency = [];
    for (let i = 0; i < 36; i += 1) {
      const wave = Math.sin((i + index) / 4) * 2.8;
      const smaller = Math.cos((i + index * 2) / 6) * 1.7;
      latency.push(Math.max(4, Math.round(baseLatency + wave + smaller + (index % 3))));
    }
    return {
      latency,
      packetLoss: makeSeries(baseLoss, index, 36, Math.max(0.015, baseLoss * 0.18), 0, 4, 2),
      uptime: makeSeries(baseUptime, index, 36, 0.012, 99.5, 100, 3),
      incidents: makeSeries(incidents, index, 12, 0.42, 0, 5, 0),
      p50: makeSeries(baseLatency * 0.78, index, 24, 1.8, 1, 140, 0),
      p95: makeSeries(baseLatency * 1.24, index, 24, 3.2, 1, 170, 0),
      p99: makeSeries(baseLatency * 1.55, index, 24, 5.2, 1, 220, 0),
    };
  }

  function withHistories(nodes) {
    return nodes.map((node, index) => {
      const code = String(node.code || node.shortCode || node.id || `NODE${index + 1}`).toUpperCase();
      const latency = Number(node.latency ?? node.latency_ms ?? 0);
      const packetLoss = Number(node.packetLoss ?? node.packet_loss_percent ?? 0);
      const uptime = Number(node.uptime ?? node.uptime_30d ?? 0);
      const incidentCount = Number(node.incidentCount ?? node.incident_count ?? 0);
      return {
        ...node,
        id: node.id || code.toLowerCase(),
        code,
        shortCode: node.shortCode || node.short_code || code,
        name: node.name || code,
        provider: node.provider || node.facility || 'Unknown provider',
        facility: node.facility || node.provider || 'Unknown facility',
        country: node.country || 'Unknown',
        city: node.city || node.region || 'Unknown',
        region: node.region || node.city || 'Unknown',
        mode: node.mode || (node.method && node.method !== 'mock' ? 'real' : 'mock'),
        method: node.method || 'mock',
        target_host: node.target_host || node.targetHost || '',
        target_ip: node.target_ip || node.targetIp || '',
        target_port: node.target_port || node.targetPort || '',
        health_url: node.health_url || node.healthUrl || '',
        expected_status: node.expected_status || node.expectedStatus || '',
        expected_keyword: node.expected_keyword || node.expectedKeyword || '',
        interval_seconds: Number(node.interval_seconds || node.intervalSeconds || 60),
        timeout_seconds: Number(node.timeout_seconds || node.timeoutSeconds || 5),
        is_active: node.is_active ?? true,
        created_at: node.created_at || node.createdAt || nowIso(),
        updated_at: node.updated_at || node.updatedAt || nowIso(),
        status: node.status || 'healthy',
        latency,
        uptime,
        packetLoss,
        incidentCount,
        latitude: Number(node.latitude),
        longitude: Number(node.longitude),
        lastChecked: node.lastChecked || node.last_checked || nowIso(),
        history: node.history || makeHistory(latency, index, packetLoss, uptime, incidentCount),
      };
    });
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
      incidents: regionNodes.filter((node) => ['critical', 'warning', 'degraded', 'maintenance'].includes(node.status)).length,
    };
  }

  function computeSummary(nodes) {
    const worst = nodes.reduce((acc, node) => (statusRank(node.status) > statusRank(acc.status) ? node : acc), { status: 'healthy' });
    const activeIncidents = nodes.reduce((sum, node) => sum + Number(node.incidentCount || (['critical', 'degraded', 'warning', 'maintenance'].includes(node.status) ? 1 : 0)), 0);
    const warningCount = nodes.filter((node) => node.status === 'warning' || node.status === 'degraded').length;
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
      degraded: 3.4,
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
    if (node.mode === 'real') {
      // Real targets are display-only until backend workers provide results; the browser never probes servers.
      return node;
    }
    const drift = driftFor(node, tick + index);
    const floor = node.country === 'Singapore' ? 32 : 10;
    const ceiling = node.status === 'critical' ? 130 : (['warning', 'degraded'].includes(node.status) ? 76 : 48);
    const latency = Math.min(ceiling, Math.max(floor, Math.round(node.latency + drift)));
    const packetBump = {
      critical: 0.08,
      degraded: 0.03,
      warning: 0.03,
      maintenance: 0.01,
      recovery: -0.01,
      healthy: -0.004,
    }[node.status] || 0;
    const packetLoss = Math.max(0, Math.min(4, Number((node.packetLoss + packetBump + Math.sin(tick / 4 + index) * 0.015).toFixed(2))));
    const history = {
      latency: [...(node.history?.latency || []).slice(-35), latency],
      packetLoss: [...(node.history?.packetLoss || []).slice(-35), packetLoss],
      uptime: [...(node.history?.uptime || []).slice(-35), node.uptime],
      incidents: [...(node.history?.incidents || []).slice(-11), node.incidentCount || 0],
      p50: [...(node.history?.p50 || []).slice(-23), Math.round(latency * 0.78)],
      p95: [...(node.history?.p95 || []).slice(-23), Math.round(latency * 1.24)],
      p99: [...(node.history?.p99 || []).slice(-23), Math.round(latency * 1.55)],
    };
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
