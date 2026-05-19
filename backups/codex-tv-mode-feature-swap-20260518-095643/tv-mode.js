(function () {
  const root = document.querySelector('[data-tv-mode]');
  if (!root) return;

  const state = {
    paused: false,
    lastData: null,
    refreshMs: 45000,
    timer: null,
  };

  const $ = (selector) => root.querySelector(selector);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[ch]);

  function setClock() {
    const now = new Date();
    $('[data-tv-clock]').textContent = now.toLocaleTimeString('en-GB', { hour12: false });
    $('[data-tv-date]').textContent = now.toLocaleDateString('en-GB', {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
    });
  }

  function statusClass(tone) {
    if (tone === 'critical') return 'is-critical';
    if (tone === 'watch' || tone === 'high') return 'is-watch';
    if (tone === 'stable' || tone === 'normal') return 'is-stable';
    return 'is-info';
  }

  function metric(label, value, helper, tone, icon) {
    return `
      <article class="tv-metric ${statusClass(tone)}">
        <div class="tv-metric__icon"><i data-lucide="${icon}"></i></div>
        <span>${esc(label)}</span>
        <strong>${esc(value)}</strong>
        <p>${esc(helper)}</p>
      </article>
    `;
  }

  function empty(label) {
    return `<div class="tv-empty">${esc(label)}</div>`;
  }

  function renderSpotlight(item) {
    const tone = item?.severity || 'stable';
    $('[data-tv-spotlight]').className = `tv-spotlight tv-panel ${statusClass(tone)}`;
    $('[data-tv-spotlight]').innerHTML = `
      <div class="tv-panel__eyebrow">Spotlight</div>
      <div class="tv-spotlight__content">
        <p class="tv-spotlight__type">${esc(item?.type || 'Summary')}</p>
        <h2>${esc(item?.title || 'Operations summary')}</h2>
        <p>${esc(item?.detail || 'No immediate operational signal is dominating the board.')}</p>
        <span>${esc(item?.meta || 'Live')}</span>
      </div>
    `;
  }

  function renderMetrics(m) {
    $('[data-tv-metrics]').innerHTML = [
      metric('Open Cases', m.open_cases, 'Active workload', m.open_cases > 0 ? 'info' : 'stable', 'briefcase'),
      metric('Pending', m.pending_cases, 'Waiting on next action', m.pending_cases > 0 ? 'watch' : 'stable', 'clock-3'),
      metric('Aging / Stuck', m.stuck_cases, 'Over 24h, overdue, or stuck', m.stuck_cases > 0 ? 'critical' : 'stable', 'alarm-clock'),
      metric('Solved Today', m.solved_today, 'Completed case flow', 'stable', 'check-circle-2'),
      metric('Active Reminders', m.active_reminders, 'Incomplete reminders', m.active_reminders > 0 ? 'info' : 'stable', 'bell'),
      metric('Overdue', m.overdue_reminders, 'Past due reminders', m.overdue_reminders > 0 ? 'critical' : 'stable', 'bell-ring'),
      metric('Unchecked Tasks', m.unchecked_tasks, 'Checklist pressure', m.unchecked_tasks >= 10 ? 'critical' : (m.unchecked_tasks >= 5 ? 'watch' : 'stable'), 'list-checks'),
      metric('Done Today', m.completed_tasks_today, 'Checklist completions', 'stable', 'badge-check'),
      metric('Meetings', m.active_moms, 'Upcoming or ongoing', m.active_moms > 0 ? 'info' : 'stable', 'calendar-days'),
      metric('Handovers', m.critical_handovers, 'High priority active notes', m.critical_handovers > 0 ? 'watch' : 'stable', 'clipboard-list'),
    ].join('');
  }

  function renderCases(items) {
    $('[data-tv-case-count]').textContent = items.length;
    $('[data-tv-cases]').innerHTML = items.length ? items.map((item) => `
      <article class="tv-row ${item.attention ? 'needs-attention' : ''}">
        <div>
          <strong>${esc(item.title)}</strong>
          <span>${esc(item.owner)} / age ${esc(item.age)}</span>
        </div>
        <div class="tv-row__meta">
          <b class="${statusClass(item.priority === 'critical' ? 'critical' : item.status === 'stuck' ? 'watch' : 'info')}">${esc(item.priority)}</b>
          <em>${esc(item.status)}</em>
        </div>
      </article>
    `).join('') : empty('No active case pressure.');
  }

  function renderQueue(items) {
    $('[data-tv-queue-count]').textContent = items.length;
    $('[data-tv-queue]').innerHTML = items.length ? items.map((item) => `
      <article class="tv-row ${item.urgent ? 'needs-attention' : ''}">
        <div>
          <strong>${esc(item.title)}</strong>
          <span>${esc(item.type)} / ${esc(item.owner)}</span>
        </div>
        <div class="tv-row__meta">
          <b class="${statusClass(item.urgent ? 'critical' : item.priority)}">${esc(item.due)}</b>
        </div>
      </article>
    `).join('') : empty('Reminder and checklist queues are clear.');
  }

  function renderHandover(data) {
    const items = data?.items || [];
    $('[data-tv-handover]').innerHTML = `
      <div class="tv-mini-metrics">
        <span><strong>${esc(data?.active_count ?? 0)}</strong> active</span>
        <span><strong>${esc(data?.resolved_today ?? 0)}</strong> resolved today</span>
      </div>
      ${items.length ? items.map((item) => `
        <article class="tv-note ${statusClass(item.priority === 'critical' ? 'critical' : item.priority === 'high' ? 'watch' : 'info')}">
          <strong>${esc(item.title)}</strong>
          <span>${esc(item.shift)} / ${esc(item.status)} / ${esc(item.priority)}</span>
        </article>
      `).join('') : empty('No active handover notes.')}
    `;
  }

  function renderMeetings(items) {
    $('[data-tv-meetings]').innerHTML = items.length ? items.map((item) => `
      <article class="tv-note ${statusClass(item.status === 'ongoing' || item.soon ? 'watch' : 'info')}">
        <strong>${esc(item.title)}</strong>
        <span>${esc(item.time)} / ${esc(item.status)}${item.has_url ? ' / link ready' : ''}</span>
      </article>
    `).join('') : empty('No active or upcoming meetings.');
  }

  function renderActivity(items) {
    $('[data-tv-activity]').innerHTML = items.length ? items.map((item) => `
      <article class="tv-activity">
        <span>${esc(item.label)}</span>
        <strong>${esc(item.text)}</strong>
        <em>${esc(item.time)}</em>
      </article>
    `).join('') : empty('No recent meaningful activity.');
  }

  function renderIntel(items) {
    $('[data-tv-intel]').innerHTML = `
      <div><strong>${esc(items.domain_alerts)}</strong><span>Domain watch</span></div>
      <div><strong>${esc(items.finance_alerts)}</strong><span>Finance watch</span></div>
      <div><strong>${esc(items.feedback_7d)}</strong><span>Feedback 7d</span></div>
    `;
  }

  function renderTicker(items) {
    const html = (items.length ? items : [{ text: 'All systems operational', tone: 'normal' }]).map((item) => (
      `<span class="${statusClass(item.tone)}">${esc(item.text)}</span>`
    )).join('');
    $('[data-tv-ticker]').innerHTML = html + html;
  }

  function render(data) {
    state.lastData = data;
    $('[data-tv-shift]').textContent = data.current_shift || '--';
    $('[data-tv-updated]').textContent = new Date(data.generated_at.replace(' ', 'T')).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    const health = $('[data-tv-health]');
    health.className = `tv-mode__health tv-mode__health--${data.health.state}`;
    health.innerHTML = `<span></span><strong>${esc(data.health.state)} / ${esc(data.health.score)}</strong>`;
    $('[data-tv-score]').textContent = data.health.score;
    $('[data-tv-score-bar]').style.width = `${data.health.score}%`;
    $('[data-tv-score-copy]').textContent = data.health.state === 'critical'
      ? 'Multiple signals need supervisor attention.'
      : data.health.state === 'watch'
        ? 'Operations are stable with active watch items.'
        : 'Operating inside the normal band.';

    renderSpotlight(data.spotlight);
    renderMetrics(data.metrics);
    renderCases(data.cases || []);
    renderQueue(data.queue || []);
    renderHandover(data.handover || {});
    renderMeetings(data.meetings || []);
    renderActivity(data.activities || []);
    renderIntel(data.intelligence || {});
    renderTicker(data.ticker || []);
    $('[data-tv-refresh-state]').textContent = state.paused ? 'Paused' : 'Live';
    if (window.lucide) window.lucide.createIcons();
  }

  async function load() {
    if (state.paused && state.lastData) return;
    $('[data-tv-refresh-state]').textContent = 'Syncing';
    try {
      const response = await fetch('api/tv-mode-summary.php', { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const json = await response.json();
      if (!response.ok || !json.success) throw new Error(json.message || 'Unable to load data');
      render(json.data);
      root.classList.remove('tv-mode--error');
    } catch (error) {
      root.classList.add('tv-mode--error');
      $('[data-tv-refresh-state]').textContent = 'Offline';
      if (!state.lastData) {
        renderSpotlight({
          type: 'Connection',
          severity: 'critical',
          title: 'TV Mode cannot load data',
          detail: 'The page is still available, but the API returned an error.',
          meta: 'Retrying automatically',
        });
      }
    }
  }

  function bindControls() {
    $('[data-tv-pause]')?.addEventListener('click', () => {
      state.paused = !state.paused;
      $('[data-tv-pause]').innerHTML = `<i data-lucide="${state.paused ? 'play' : 'pause'}"></i>`;
      $('[data-tv-refresh-state]').textContent = state.paused ? 'Paused' : 'Live';
      if (window.lucide) window.lucide.createIcons();
      if (!state.paused) load();
    });

    $('[data-tv-fullscreen]')?.addEventListener('click', () => {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.();
      } else {
        document.exitFullscreen?.();
      }
    });
  }

  setClock();
  setInterval(setClock, 1000);
  bindControls();
  load();
  state.timer = setInterval(load, state.refreshMs);
})();
