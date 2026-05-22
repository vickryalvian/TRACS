/*! TRACS TV Mode surface | Initial deployment identity by Vickry */
(function () {
  const root = document.querySelector('[data-tv-mode]');
  if (!root) return;

  const buildInfo = window.TRACS_BUILD_INFO || {};
  if (!window.__TRACS_SIGNATURE_LOGGED__ && buildInfo.owner) {
    const version = buildInfo.version ? ` • ${buildInfo.version}` : '';
    console.log(`%cTRACS System • Initial Build by ${buildInfo.owner}${version}`, 'color:#0891b2;font-weight:700;');
    if (buildInfo.easterEgg) console.log('Internal build channel active.');
    window.__TRACS_SIGNATURE_LOGGED__ = true;
  }

  const state = {
    paused: false,
    lastData: null,
    refreshMs: 45000,
    timer: null,
    shiftGreetingVisible: false,
    spotlightItems: [],
    spotlightIndex: 0,
  };
  const THEME_KEY = 'tracs_theme_preference';
  const THEME_LEGACY_KEY = 'tracs-theme';

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
    const time = now.toLocaleTimeString('en-GB', { hour12: false });
    const weekday = now.toLocaleDateString('en-GB', { weekday: 'short' });
    const dayMonth = now.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
    });
    const date = now.toLocaleDateString('en-GB', {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
    });
    root.querySelectorAll('[data-tv-clock]').forEach((el) => { el.textContent = time; });
    root.querySelectorAll('[data-tv-date]').forEach((el) => {
      if (el.classList.contains('tv-time-panel__date')) {
        el.replaceChildren();
        const day = document.createElement('span');
        const rest = document.createElement('strong');
        day.textContent = weekday;
        rest.textContent = dayMonth;
        el.append(day, rest);
      } else {
        el.textContent = date;
      }
    });
    $('[data-tv-greeting]').textContent = greetingFor(now);
  }

  function greetingFor(date) {
    const hour = date.getHours();
    if (hour >= 5 && hour < 12) return 'Good Morning';
    if (hour >= 12 && hour < 17) return 'Good Afternoon';
    if (hour >= 17 && hour < 21) return 'Good Evening';
    return 'Good Night';
  }

  function shiftTone(shift) {
    if (/1/.test(shift || '')) return 'indigo';
    if (/2/.test(shift || '')) return 'amber';
    return 'blue';
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
        <div class="tv-metric__top">
          <div class="tv-metric__icon"><i data-lucide="${icon}"></i></div>
          <span>${esc(label)}</span>
        </div>
        <div class="tv-metric__body">
          <strong>${esc(value)}</strong>
          <p>${esc(helper)}</p>
        </div>
      </article>
    `;
  }

  function empty(label) {
    return `<div class="tv-empty">${esc(label)}</div>`;
  }

  function holidayIconKey(name) {
    const text = String(name || '').toLowerCase();
    if (/idul fitri|eid al-fitr/.test(text)) return 'eid-spark';
    if (/idul adha|eid al-adha|masjid|mosque/.test(text)) return 'crescent';
    if (/ramadan|isra|mi'?raj|maulid|muharram|islam/.test(text)) return 'crescent';
    if (/natal|christmas/.test(text)) return 'star';
    if (/wafat|paskah|easter|yesus|isa/.test(text)) return 'cross';
    if (/imlek|kongzili|chinese/.test(text)) return 'lantern';
    if (/nyepi|saka/.test(text)) return 'quiet-moon';
    if (/waisak|buddha/.test(text)) return 'lotus';
    if (/kemerdekaan|republik indonesia|proklamasi/.test(text)) return 'flag';
    if (/pancasila/.test(text)) return 'shield';
    if (/buruh|labou?r/.test(text)) return 'briefcase';
    if (/tahun baru 20|new year|masehi/.test(text)) return 'calendar-spark';
    if (/kenaikan|ascension/.test(text)) return 'arrow-light';
    return 'calendar';
  }

  function holidayIconSvg(key) {
    const icons = {
      'eid-spark': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.8 4.7a7.8 7.8 0 1 0 2.5 11.2A7 7 0 1 1 16.8 4.7Z"/><path d="M17.5 6.5v3M16 8h3M20 12v2.6M18.7 13.3h2.6"/></svg>',
      crescent: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.8 4.7a7.8 7.8 0 1 0 2.5 11.2A7 7 0 1 1 16.8 4.7Z"/><path d="M6 18h12M8 18v-4.2L12 11l4 2.8V18"/></svg>',
      star: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.4 5 5.6.8-4 3.9.9 5.5-4.9-2.6-5 2.6 1-5.5-4-3.9 5.5-.8L12 3Z"/></svg>',
      cross: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16M7 9h10"/><path d="M5 20h14"/></svg>',
      lantern: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h8M8 19h8M7 8c0-1.7 10-1.7 10 0v7c0 1.7-10 1.7-10 0V8Z"/><path d="M12 3v2M12 19v2"/></svg>',
      'quiet-moon': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.8 4.7a7.8 7.8 0 1 0 2.5 11.2A7 7 0 1 1 16.8 4.7Z"/><path d="M7 19h10"/></svg>',
      lotus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19c-2.6-2.2-3.8-4.5-3.8-7 2.1.6 3.3 1.8 3.8 3.6.5-1.8 1.7-3 3.8-3.6 0 2.5-1.2 4.8-3.8 7Z"/><path d="M4 13c2.4.3 4.4 1.7 6 4M20 13c-2.4.3-4.4 1.7-6 4M6 20h12"/></svg>',
      flag: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 21V4M7 5h10l-1 4 1 4H7"/></svg>',
      shield: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 19 6v5c0 4.4-2.7 7.7-7 10-4.3-2.3-7-5.6-7-10V6l7-3Z"/><path d="M12 7v9M8.8 10h6.4"/></svg>',
      briefcase: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7V5h6v2M5 8h14v10H5z"/><path d="M5 12h14"/></svg>',
      'calendar-spark': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3M17 3v3M5 8h14M6 5h12v15H6z"/><path d="M16 11v3M14.5 12.5h3"/></svg>',
      'arrow-light': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M7 10l5-5 5 5"/><path d="M5 20h14"/></svg>',
      calendar: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3M17 3v3M5 8h14M6 5h12v15H6z"/><path d="M12 14h.1"/></svg>',
    };
    return icons[key] || icons.calendar;
  }

  function formatHolidayDate(value) {
    const date = new Date(`${value}T00:00:00+07:00`);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function renderHoliday(data, stateName = 'ready') {
    const panel = $('[data-tv-holiday]');
    if (!panel) return;
    const icon = panel.querySelector('[data-tv-holiday-icon]');
    const title = panel.querySelector('[data-tv-holiday-title]');
    const subtitle = panel.querySelector('[data-tv-holiday-subtitle]');
    const countdown = panel.querySelector('[data-tv-holiday-countdown]');
    const type = panel.querySelector('[data-tv-holiday-type]');
    panel.className = 'tv-holiday-panel';

    if (stateName === 'loading') {
      panel.classList.add('is-loading');
      icon.innerHTML = holidayIconSvg('calendar');
      title.textContent = 'Loading holiday calendar';
      subtitle.textContent = 'Checking Indonesian public holidays';
      countdown.textContent = 'Syncing';
      type.textContent = 'Holiday';
      return;
    }

    if (stateName === 'error') {
      panel.classList.add('is-error');
      icon.innerHTML = holidayIconSvg('calendar');
      title.textContent = 'Holiday calendar unavailable';
      subtitle.textContent = 'Showing clock only until the next sync';
      countdown.textContent = 'Retrying';
      type.textContent = 'Error';
      return;
    }

    if (!data || data.status === 'empty' || !data.date) {
      panel.classList.add('is-empty');
      icon.innerHTML = holidayIconSvg('calendar');
      title.textContent = 'No upcoming tanggal merah';
      subtitle.textContent = 'Current and next year are clear';
      countdown.textContent = 'Empty';
      type.textContent = 'Observance';
      return;
    }

    const key = holidayIconKey(data.name);
    const days = Number(data.daysUntil ?? 0);
    const isHot = data.isToday || days <= 1;
    if (isHot) panel.classList.add('is-hot');
    panel.classList.add(`is-${data.type || 'observance'}`);
    icon.innerHTML = holidayIconSvg(key);
    title.textContent = data.isToday ? `Today: ${data.name}` : `Next Holiday: ${data.name}`;
    subtitle.textContent = data.isToday
      ? `Tanggal merah today · ${formatHolidayDate(data.date)}`
      : `${days} ${days === 1 ? 'day' : 'days'} to ${data.name} · ${formatHolidayDate(data.date)}`;
    countdown.textContent = data.isToday ? 'Today' : `${days} days left`;
    type.textContent = data.typeLabel || 'Observance';
  }

  async function loadHoliday() {
    renderHoliday(null, 'loading');
    try {
      const response = await fetch('api/holiday-indonesia.php', { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const json = await response.json();
      if (!response.ok || !json.success) throw new Error(json.message || 'Unable to load holiday');
      renderHoliday(json.data, 'ready');
    } catch (error) {
      renderHoliday(null, 'error');
    }
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
    if (window.lucide) window.lucide.createIcons();
  }

  function caseSpotlightItem(item) {
    const isCritical = item.priority === 'critical' || item.status === 'stuck';
    return {
      type: 'Case Watch',
      severity: isCritical ? 'critical' : (item.attention ? 'watch' : 'info'),
      title: item.title,
      detail: `${item.status.charAt(0).toUpperCase()}${item.status.slice(1)} case needs attention. Owner: ${item.owner}`,
      meta: `Age ${item.age} / ${item.priority.charAt(0).toUpperCase()}${item.priority.slice(1)}`,
    };
  }

  function buildSpotlightItems(data) {
    const caseItems = (data.cases || [])
      .filter((item) => item.attention || item.priority === 'critical' || item.status === 'stuck')
      .map(caseSpotlightItem);
    if (caseItems.length) return caseItems;
    return [data.spotlight || {
      type: 'Summary',
      severity: 'stable',
      title: 'Operations summary',
      detail: 'No immediate operational signal is dominating the board.',
      meta: 'Live',
    }];
  }

  function setSpotlightItems(data) {
    const nextItems = buildSpotlightItems(data);
    const currentTitle = state.spotlightItems[state.spotlightIndex]?.title;
    state.spotlightItems = nextItems;
    const matchingIndex = currentTitle
      ? nextItems.findIndex((item) => item.title === currentTitle)
      : -1;
    state.spotlightIndex = matchingIndex >= 0 ? matchingIndex : 0;
    renderSpotlight(state.spotlightItems[state.spotlightIndex]);
  }

  function advanceSpotlight() {
    if (state.spotlightItems.length < 2) return;
    state.spotlightIndex = (state.spotlightIndex + 1) % state.spotlightItems.length;
    renderSpotlight(state.spotlightItems[state.spotlightIndex]);
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
      metric('Domain Watch', m.domain_watch, 'Expiry or transfer risk', m.domain_watch > 0 ? 'watch' : 'stable', 'globe'),
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

  function renderFeedbackSummary(data) {
    const items = data?.items || [];
    const summary = data?.summary || {};
    const total = summary.total ?? items.length;
    const critical = summary.critical ?? 0;
    const topService = summary.top_service || 'No dominant service';
    const topReason = summary.top_reason || 'No dominant reason';
    $('[data-tv-ops-watch]').innerHTML = items.length ? items.map((item) => `
      <article class="tv-note ${statusClass(item.tone || 'info')}">
        <strong>${esc(item.title)}</strong>
        <span>${esc(item.meta)}</span>
      </article>
    `).join('') : empty('No cancellation feedback logged this week.');
    $('[data-tv-ops-watch]').insertAdjacentHTML('afterbegin', `
      <div class="tv-feedback-summary">
        <div><strong>${esc(total)}</strong><span>records this week</span></div>
        <div><strong>${esc(critical)}</strong><span>risk reasons</span></div>
        <p>${esc(topService)} / ${esc(topReason)}</p>
      </div>
    `);
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
      <div><strong>${esc(items.feedback_7d)}</strong><span>Feedback week</span></div>
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
    const shiftDot = $('[data-tv-shift-dot]');
    if (shiftDot) shiftDot.className = `tv-shift-dot ${shiftTone(data.current_shift || '')}`;
    $('[data-tv-updated]').textContent = new Date(data.generated_at.replace(' ', 'T')).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    const health = $('[data-tv-health]');
    health.className = `tv-mode__health tv-mode__health--${data.health.state}`;
    health.innerHTML = `<span></span><strong>${esc(data.health.state)} / ${esc(data.health.score)}</strong>`;
    const score = $('[data-tv-score]');
    const scoreBar = $('[data-tv-score-bar]');
    const scoreCopy = $('[data-tv-score-copy]');
    if (score) score.textContent = data.health.score;
    if (scoreBar) scoreBar.style.width = `${data.health.score}%`;
    if (scoreCopy) {
      scoreCopy.textContent = data.health.state === 'critical'
        ? 'Multiple signals need supervisor attention.'
        : data.health.state === 'watch'
          ? 'Operations are stable with active watch items.'
          : 'Operating inside the normal band.';
    }

    setSpotlightItems(data);
    renderMetrics(data.metrics);
    renderCases(data.cases || []);
    renderQueue(data.queue || []);
    renderHandover(data.handover || {});
    renderFeedbackSummary(data.feedback_summary || { items: data.ops_watch || [] });
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

    const themeMenu = $('[data-tv-theme-menu]');
    const themeToggle = $('[data-tv-theme-toggle]');
    themeToggle?.addEventListener('click', () => {
      const open = !themeMenu.classList.contains('is-open');
      themeMenu.classList.toggle('is-open', open);
      themeToggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (event) => {
      if (!themeMenu || themeMenu.contains(event.target)) return;
      themeMenu.classList.remove('is-open');
      themeToggle?.setAttribute('aria-expanded', 'false');
    });
    root.addEventListener('click', (event) => {
      const option = event.target.closest('[data-tv-theme-choice]');
      if (option) {
        event.preventDefault();
        setThemePreference(option.getAttribute('data-tv-theme-choice'));
        themeMenu?.classList.remove('is-open');
        themeToggle?.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function normalizeThemePreference(value) {
    return value === 'dark' || value === 'light' ? value : 'light';
  }

  function setThemePreference(value) {
    const pref = normalizeThemePreference(value);
    document.documentElement.setAttribute('data-theme', pref);
    document.documentElement.setAttribute('data-theme-preference', pref);
    document.documentElement.setAttribute('data-applied-theme', pref);
    localStorage.setItem(THEME_KEY, pref);
    localStorage.setItem(THEME_LEGACY_KEY, pref);
    syncThemeMenu(pref);
  }

  function syncThemeMenu(pref) {
    const selected = normalizeThemePreference(pref || localStorage.getItem(THEME_KEY) || localStorage.getItem(THEME_LEGACY_KEY));
    root.querySelectorAll('[data-tv-theme-choice]').forEach((option) => {
      const active = option.getAttribute('data-tv-theme-choice') === selected;
      option.classList.toggle('is-active', active);
      option.setAttribute('aria-checked', String(active));
    });
  }

  setClock();
  setInterval(setClock, 1000);
  setInterval(() => {
    state.shiftGreetingVisible = !state.shiftGreetingVisible;
    $('[data-tv-shift-slider]')?.classList.toggle('show-greeting', state.shiftGreetingVisible);
  }, 5000);
  setInterval(advanceSpotlight, 7000);
  syncThemeMenu(document.documentElement.getAttribute('data-theme'));
  bindControls();
  loadHoliday();
  setInterval(loadHoliday, 30 * 60 * 1000);
  load();
  state.timer = setInterval(load, state.refreshMs);
})();
