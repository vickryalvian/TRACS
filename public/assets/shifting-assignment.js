(() => {
  'use strict';

  const root = document.getElementById('shiftingAssignmentApp');
  if (!root) return;
  if (root.dataset.shiftAppInitialized === '1') return;
  root.dataset.shiftAppInitialized = '1';

  const preference = key => {
    try { return window.localStorage.getItem(`tracs.shifting.${key}`); } catch (_) { return null; }
  };
  const savePreference = (key, value) => {
    try { window.localStorage.setItem(`tracs.shifting.${key}`, value); } catch (_) {}
  };
  const state = {
    data: window.TRACS_SHIFTING_INITIAL || {},
    view: ['daily', 'weekly', 'monthly'].includes(preference('view')) ? preference('view') : 'weekly',
    activeTab: preference('tab') || 'timeline',
    workloadFilter: preference('workloadFilter') || 'all',
    warningType: 'all',
    recapFilters: new Set(),
    loading: false,
    defaultRange: {
      start: window.TRACS_SHIFTING_INITIAL?.range?.start || '',
      end: window.TRACS_SHIFTING_INITIAL?.range?.end || '',
    },
    activeModal: null,
    modalBodyOverflow: '',
    suppressAssignmentClickUntil: 0,
    insightsExpanded: preference('insightsExpanded') === '1',
    monthlyPreview: null,
    monthlyPreviewTemplateId: 0,
  };

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[char]);
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const slug = value => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const hashText = value => {
    let hash = 2166136261;
    for (const char of String(value || '')) {
      hash ^= char.charCodeAt(0);
      hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(36);
  };
  const pad = value => String(value).padStart(2, '0');
  const parseLocal = value => {
    if (!value) return null;
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
  };
  const dateKey = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  const dateTimeSql = date => `${dateKey(date)} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
  const addDays = (date, days) => {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  };
  const startOfDay = date => new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const minutes = value => {
    const total = Math.max(0, Number(value) || 0);
    const h = Math.floor(total / 60);
    const m = Math.round(total % 60);
    return m ? `${h}h ${m}m` : `${h}h`;
  };
  const shortDate = value => {
    const date = parseLocal(`${value} 00:00:00`);
    return date ? new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: '2-digit', month: 'short' }).format(date) : value;
  };
  const monthLabel = value => {
    const date = parseLocal(`${String(value || '').slice(0, 7)}-01 00:00:00`);
    return date ? new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(date) : value;
  };
  const formatDateTime = value => {
    const date = parseLocal(value);
    return date ? new Intl.DateTimeFormat('en-GB', {
      day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date) : (value || '—');
  };
  const nextMonthValue = () => {
    const date = new Date();
    date.setMonth(date.getMonth() + 1, 1);
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
  };
  const monthAfter = value => {
    const date = parseLocal(`${String(value || '').slice(0, 7)}-01 00:00:00`);
    if (!date) return nextMonthValue();
    date.setMonth(date.getMonth() + 1, 1);
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
  };
  const time = value => String(value || '').slice(11, 16);
  const notify = (message, type = 'info') => {
    if (typeof window.showToast !== 'function') return;
    const activeModal = state.activeModal && !state.activeModal.classList.contains('hidden') ? state.activeModal : null;
    window.showToast(message, type, activeModal
      ? { context: 'modal', modal: activeModal, sourceElement: document.activeElement }
      : { context: 'page' });
  };
  const icons = () => window.lucide?.createIcons?.();
  const confirmAction = async (message, title = 'Confirm action') => {
    if (typeof window.tracsConfirm === 'function') {
      return Boolean(await window.tracsConfirm({ type: 'warning', title, message, destructive: true }));
    }
    return Boolean(window.confirm(message));
  };
  const setPickerValue = (input, value) => {
    if (!input) return;
    if (input._flatpickr) input._flatpickr.setDate(value, false);
    else input.value = value;
  };
  const singletonModalDefinitions = {
    assignment: {
      formId: 'shiftAssignmentForm',
      fields: {
        user_id: 1,
        division_name: 1,
        assignment_date: 1,
        shift_template_id: 1,
        start_time: 1,
        end_time: 1,
        break_minutes: 1,
        assignment_type: 1,
        status: 1,
        notes: 1,
      },
    },
    monthlyTemplate: {
      formId: 'shiftMonthlyTemplateForm',
      fields: {
        name: 1,
        target_month: 1,
        division_id: 1,
        shift_template_id: 1,
        agent_ids: 1,
        rest_days: 7,
        weekend_handling: 1,
        status: 1,
        repeat_weekly_pattern: 1,
        rotate_agents_weekly: 1,
        exclude_public_holidays: 1,
        include_holiday_coverage: 1,
        include_lembur_template: 1,
        prevent_workload_over_target: 1,
        warn_coverage_gap: 1,
        notes: 1,
      },
    },
  };
  const canonicalModalBodies = new Map();

  Object.values(singletonModalDefinitions).forEach(({ formId }) => {
    const body = $(`#${formId} > [data-shift-canonical-body]`);
    if (body) canonicalModalBodies.set(formId, body.cloneNode(true));
  });

  async function api(action, payload = null, method = 'POST') {
    const options = { method, headers: { Accept: 'application/json' } };
    let url = `api/shifting-assignment.php?action=${encodeURIComponent(action)}`;
    if (method === 'GET') {
      const query = new URLSearchParams(payload || {});
      url += `&${query.toString()}`;
    } else {
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-CSRF-Token'] = csrf();
      options.body = JSON.stringify({ action, ...(payload || {}) });
    }
    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
    if (!response.ok || !json.success) {
      const error = new Error(json.message || 'Request failed.');
      error.errors = json.errors || {};
      throw error;
    }
    return json;
  }

  function formObject(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    $$('input[type="checkbox"]', form).forEach(input => { data[input.name] = input.checked ? 1 : 0; });
    return data;
  }

  function clearFormErrors(form) {
    if (!form) return;
    $$('[aria-invalid="true"]', form).forEach(field => field.removeAttribute('aria-invalid'));
    $$('.shift-field-error', form).forEach(node => { node.textContent = ''; });
    const alert = $('.shift-form-alert', form);
    if (alert) {
      alert.hidden = true;
      alert.textContent = '';
    }
  }

  function fieldErrorNode(form, fieldName) {
    const field = form?.elements?.[fieldName];
    if (!field) return null;
    const describedBy = field.getAttribute('aria-describedby');
    if (describedBy) return document.getElementById(describedBy);
    const node = document.createElement('small');
    node.className = 'shift-field-error';
    node.id = `${form.id}-${fieldName}-error`;
    field.setAttribute('aria-describedby', node.id);
    field.insertAdjacentElement('afterend', node);
    return node;
  }

  function showFormErrors(form, errors, message = '') {
    clearFormErrors(form);
    let firstField = null;
    Object.entries(errors || {}).forEach(([fieldName, text]) => {
      const field = form.elements[fieldName];
      if (!field) return;
      field.setAttribute('aria-invalid', 'true');
      const node = fieldErrorNode(form, fieldName);
      if (node) node.textContent = text;
      if (!firstField) firstField = field;
    });
    const alert = $('.shift-form-alert', form);
    if (alert && message) {
      alert.textContent = message;
      alert.hidden = false;
    }
    firstField?.focus();
  }

  function validateAssignmentForm(form) {
    const errors = {};
    if (!form.elements.user_id.value) errors.user_id = 'Agent is required to save an assignment.';
    if (!form.elements.assignment_date.value) errors.assignment_date = 'Assignment date is required.';
    if (!form.elements.start_time.value) errors.start_time = 'Start time is required.';
    if (!form.elements.end_time.value) errors.end_time = 'End time is required.';
    const start = form.elements.start_time.value;
    const end = form.elements.end_time.value;
    if (start && end && start === end) errors.end_time = 'Shift duration cannot be zero.';
    const breakMinutes = Number(form.elements.break_minutes.value);
    if (!Number.isFinite(breakMinutes) || breakMinutes < 0) {
      errors.break_minutes = 'Break minutes must be zero or greater.';
    } else if (start && end && start !== end) {
      const [sh, sm] = start.split(':').map(Number);
      const [eh, em] = end.split(':').map(Number);
      let gross = eh * 60 + em - sh * 60 - sm;
      if (gross < 0) gross += 1440;
      if (breakMinutes >= gross) errors.break_minutes = 'Break minutes must be less than the shift duration.';
    }
    showFormErrors(form, errors, Object.keys(errors).length ? 'Review the highlighted assignment fields.' : '');
    return Object.keys(errors).length === 0;
  }

  function validateMonthlyTemplateForm(form) {
    const errors = {};
    if (!form.elements.name.value.trim()) errors.name = 'Template name is required.';
    if (!form.elements.target_month.value) errors.target_month = 'Target month is required.';
    if (!form.elements.division_id.value) errors.division_id = 'Division is required.';
    if (!form.elements.shift_template_id.value) errors.shift_template_id = 'Base shift pattern is required.';
    if (!form.elements.agent_ids.selectedOptions.length) errors.agent_ids = 'Select at least one agent.';
    showFormErrors(form, errors, Object.keys(errors).length ? 'Review the highlighted template fields.' : '');
    return Object.keys(errors).length === 0;
  }

  function fillSelect(select, rows, valueKey, label, blank = null) {
    if (!select) return;
    const current = select.value;
    const placeholder = String(blank ?? '').trim();
    select.innerHTML = blank === null ? '' : `<option value="">${esc(placeholder || 'All')}</option>`;
    rows.forEach(row => {
      const option = document.createElement('option');
      option.value = row[valueKey];
      option.textContent = String(typeof label === 'function' ? label(row) : row[label]).trim() || 'Unnamed option';
      select.appendChild(option);
    });
    if ([...select.options].some(option => option.value === current)) select.value = current;
    else if (!select.multiple) select.selectedIndex = 0;
  }

  function populateOptions() {
    const data = state.data;
    fillSelect($('#shiftFilterDivision'), data.divisions || [], 'id', 'name', 'All divisions');
    fillSelect($('#shiftFilterAgent'), data.agents || [], 'id', row => `${row.agent_name} · ${row.division_name}`, 'All agents');
    fillSelect($('#shiftFilterType'), data.assignment_types || [], 'type_slug', 'type_name', 'All shift types');
    fillSelect($('#shiftSettingsDivision'), data.divisions || [], 'id', 'name', 'Global default');
    fillSelect($('#shiftMonthlyTemplateForm select[name="division_id"]'), data.divisions || [], 'id', 'name', 'Select division');
    fillSelect(
      $('#shiftMonthlyTemplateForm select[name="shift_template_id"]'),
      (data.templates || []).filter(row => Number(row.is_active)),
      'id',
      row => `${row.shift_name} · ${String(row.start_time).slice(0, 5)}-${String(row.end_time).slice(0, 5)}`,
      'Select shift pattern',
    );
    fillSelect(
      $('#shiftMonthlyTemplateForm select[name="agent_ids"]'),
      data.agents || [],
      'id',
      row => `${row.agent_name} · ${row.division_name}`,
    );

    $$('select[name="user_id"], select[name="new_user_id"]', root).forEach(select => {
      fillSelect(select, data.agents || [], 'id', row => `${row.agent_name} · ${row.division_name}`, 'Select agent');
    });
    $$('select[name="assignment_type"], select[name="default_assignment_type"]', root).forEach(select => {
      fillSelect(select, data.assignment_types || [], 'type_slug', 'type_name');
    });
    fillSelect($('#shiftAssignmentForm select[name="shift_template_id"]'), (data.templates || []).filter(row => Number(row.is_active)), 'id', row => `${row.shift_name} · ${String(row.start_time).slice(0, 5)}-${String(row.end_time).slice(0, 5)}`, 'Custom shift');
    $$('select[name="division_id"]', $('#shiftCoverageForm') || document).forEach(select => {
      fillSelect(select, data.divisions || [], 'id', 'name', 'All divisions');
    });
    const assignmentSelect = $('#shiftReplaceForm select[name="assignment_id"]');
    if (assignmentSelect) {
      fillSelect(
        assignmentSelect,
        (data.assignments || []).filter(row => ['assigned', 'confirmed', 'active'].includes(row.status)),
        'id',
        row => `#${row.id} · ${row.agent_name} · ${row.assignment_date} ${time(row.start_datetime)}`,
        'Select assignment',
      );
    }
    window.TRACSDropdowns?.syncAll?.();
  }

  function renderSummary() {
    const summary = state.data.summary || {};
    const values = {
      today_assigned: summary.today_assigned || 0,
      active_now: summary.active_now || 0,
      under_target: summary.under_target || 0,
      over_target: summary.over_target || 0,
      overtime_risk: summary.overtime_risk || 0,
      jumpshift_risk: summary.jumpshift_risk || 0,
      coverage_gaps: summary.coverage_gaps || 0,
      conflicts: summary.conflicts || 0,
      upcoming_holiday: summary.upcoming_holiday ? shortDate(summary.upcoming_holiday.holiday_date) : 'None',
    };
    Object.entries(values).forEach(([key, value]) => {
      const node = $(`[data-summary-value="${key}"]`);
      if (node) node.textContent = value;
    });
    const notes = {
      today_assigned: `${minutes(summary.scheduled_minutes || 0)} in selected range`,
      active_now: 'Jakarta local time',
      under_target: 'Below configured target',
      over_target: 'Above configured target',
      overtime_risk: `${minutes(summary.overtime_minutes || 0)} total OT`,
      jumpshift_risk: 'Rest below minimum',
      coverage_gaps: 'Minimum staffing slots',
      conflicts: 'Overlapping assignments',
      upcoming_holiday: summary.upcoming_holiday?.holiday_name || 'No holiday in range',
    };
    Object.entries(notes).forEach(([key, value]) => {
      const node = $(`[data-summary-note="${key}"]`);
      if (node) node.textContent = value;
    });
  }

  function selectedDays() {
    const start = parseLocal(`${state.data.range?.start || dateKey(new Date())} 00:00:00`);
    const end = parseLocal(`${state.data.range?.end || dateKey(new Date())} 00:00:00`);
    if (!start || !end) return [];
    if (state.view === 'daily') return [start];
    const days = [];
    let cursor = start;
    const cap = state.view === 'weekly' ? 7 : 42;
    while (cursor <= end && days.length < cap) {
      days.push(cursor);
      cursor = addDays(cursor, 1);
    }
    return days;
  }

  function assignmentsForDay(day) {
    const dayStart = startOfDay(day);
    const dayEnd = addDays(dayStart, 1);
    return (state.data.assignments || []).filter(row => {
      const start = parseLocal(row.start_datetime);
      const end = parseLocal(row.end_datetime);
      return start && end && start < dayEnd && end > dayStart;
    });
  }

  function agentWarning(userId) {
    const row = (state.data.recap || []).find(item => Number(item.user_id) === Number(userId));
    if (!row) return '';
    if (row.conflict_count) return 'Conflict';
    if (row.jumpshift_count) return `Rest ${minutes(row.minimum_rest_minutes || 0)}`;
    if (row.difference_minutes < 0) return `${minutes(Math.abs(row.difference_minutes))} under`;
    if (row.difference_minutes > 0) return `${minutes(row.difference_minutes)} over`;
    return '';
  }

  function timelineBlock(row, day) {
    const dayStart = startOfDay(day);
    const dayEnd = addDays(dayStart, 1);
    const fullStart = parseLocal(row.start_datetime);
    const fullEnd = parseLocal(row.end_datetime);
    const segmentStart = fullStart < dayStart ? dayStart : fullStart;
    const segmentEnd = fullEnd > dayEnd ? dayEnd : fullEnd;
    const startMinute = (segmentStart - dayStart) / 60000;
    const duration = Math.max(1, (segmentEnd - segmentStart) / 60000);
    const left = Math.max(0, startMinute / 1440 * 100);
    const width = Math.min(100 - left, duration / 1440 * 100);
    const canResize = Boolean(state.data.permissions?.manage) && !['cancelled', 'no_show', 'replaced'].includes(row.status);
    const firstSegment = dateKey(segmentStart) === dateKey(fullStart);
    const lastSegment = segmentEnd.getTime() === fullEnd.getTime();
    const handles = canResize
      ? `${firstSegment ? '<span class="shift-resize-handle left" data-resize-edge="left"></span>' : ''}${lastSegment ? '<span class="shift-resize-handle right" data-resize-edge="right"></span>' : ''}`
      : '';
    const continuationClass = firstSegment ? '' : 'is-continuation';
    const continuesClass = lastSegment ? '' : 'continues-next-day';
    const crossDayCopy = row.is_cross_day && firstSegment
      ? `${time(row.start_datetime)}-${time(row.end_datetime)} · next day`
      : `${time(row.start_datetime)}-${time(row.end_datetime)} · ${minutes(row.calculated_duration_minutes)}`;
    return `
      <div class="shift-block ${canResize ? 'is-draggable' : ''} ${row.status === 'cancelled' ? 'is-cancelled' : ''} ${continuationClass} ${continuesClass}"
        data-assignment-id="${row.id}" data-day="${dateKey(day)}"
        style="left:${left}%;width:${Math.max(width, .45)}%;--shift-color:${esc(row.color_label || '#4f46e5')}"
        role="button" tabindex="0" aria-label="${esc(`${row.agent_name}, ${row.shift_name}, ${time(row.start_datetime)} to ${time(row.end_datetime)}`)}"
        title="${esc(`${row.shift_name} · ${time(row.start_datetime)}-${time(row.end_datetime)} · ${minutes(row.calculated_duration_minutes)}`)}">
        ${handles}
        ${firstSegment ? '' : '<span class="shift-continuation-mark" aria-hidden="true">↪</span>'}
        <span class="shift-block-copy"><strong>${esc(row.shift_name)}</strong><small>${esc(crossDayCopy)}</small></span>
        ${lastSegment ? '' : '<span class="shift-continuation-mark is-end" aria-hidden="true">→</span>'}
      </div>`;
  }

  function renderTimelineDay(day) {
    const rows = assignmentsForDay(day);
    const byUser = new Map();
    rows.forEach(row => {
      const list = byUser.get(Number(row.user_id)) || [];
      list.push(row);
      byUser.set(Number(row.user_id), list);
    });
    const agents = (state.data.agents || []).filter(agent => byUser.has(Number(agent.id)) || state.view === 'daily');
    const hours = Array.from({ length: 24 }, (_, index) => `<span>${pad(index)}</span>`).join('');
    const holiday = (state.data.holidays || []).find(item => item.holiday_date === dateKey(day));
    if (!agents.length) {
      return `<section class="shift-timeline-day is-empty-day">
        <header class="shift-timeline-day-head"><strong>${esc(shortDate(dateKey(day)))}</strong><span>${holiday ? `Holiday · ${esc(holiday.holiday_name)}` : '0 assignments'}</span></header>
        <div class="shift-timeline-empty-day">
          <span>No agents scheduled</span>
          ${state.data.permissions?.manage ? `<button class="btn btn-ghost btn-sm" type="button" data-shift-open="assignment" data-assignment-date="${dateKey(day)}"><i data-lucide="plus" class="icon-xs"></i>Add Assignment</button>` : ''}
        </div>
      </section>`;
    }
    const agentRows = agents.length ? agents.map(agent => {
      const warning = agentWarning(agent.id);
      const blocks = (byUser.get(Number(agent.id)) || []).map(row => timelineBlock(row, day)).join('');
      const initials = String(agent.agent_name || '?').split(/\s+/).map(word => word[0]).join('').slice(0, 2).toUpperCase();
      return `<div class="shift-timeline-row">
        <div class="shift-agent-cell"><span class="shift-agent-avatar">${esc(initials)}</span><span><strong>${esc(agent.agent_name)}</strong><small>${esc(agent.division_name)}</small></span>${warning ? `<span class="shift-agent-warning">${esc(warning)}</span>` : ''}</div>
        <div class="shift-track">${blocks}</div>
      </div>`;
    }).join('') : '';
    return `<section class="shift-timeline-day">
      <header class="shift-timeline-day-head"><strong>${esc(shortDate(dateKey(day)))}</strong><span>${holiday ? `Holiday · ${esc(holiday.holiday_name)}` : `${rows.length} assignment(s)`}</span></header>
      <div class="shift-timeline-header"><div class="shift-agent-cell"><strong>Agent</strong></div><div class="shift-hours">${hours}</div></div>
      ${agentRows}
    </section>`;
  }

  function renderMonthlyTimeline() {
    const rangeStart = parseLocal(`${state.data.range.start} 00:00:00`);
    const first = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), 1);
    const gridStart = addDays(first, -(first.getDay() || 7) + 1);
    const days = Array.from({ length: 42 }, (_, index) => addDays(gridStart, index));
    const today = dateKey(new Date());
    return `<div class="shift-month-grid">${days.map(day => {
      const key = dateKey(day);
      const rows = assignmentsForDay(day);
      const holiday = (state.data.holidays || []).find(item => item.holiday_date === key);
      const classes = [
        day.getMonth() !== first.getMonth() ? 'is-outside' : '',
        key === today ? 'is-today' : '',
        holiday ? 'is-holiday' : '',
      ].filter(Boolean).join(' ');
      return `<article class="shift-month-day ${classes}">
        <div class="shift-month-date">${day.getDate()}<span>${holiday ? esc(holiday.holiday_name) : rows.length ? `${rows.length} shift(s)` : ''}</span></div>
        ${rows.slice(0, 6).map(row => `<button type="button" class="shift-month-item" data-edit-assignment="${row.id}" style="--shift-color:${esc(row.color_label)}">${esc(time(row.start_datetime))} ${esc(row.agent_name)} · ${esc(row.shift_name)}</button>`).join('')}
        ${rows.length > 6 ? `<small>+${rows.length - 6} more</small>` : ''}
      </article>`;
    }).join('')}</div>`;
  }

  function renderTimeline() {
    const node = $('#shiftTimeline');
    if (!node) return;
    if (state.view === 'monthly') {
      node.innerHTML = renderMonthlyTimeline();
    } else {
      const days = selectedDays();
      const hasAssignments = days.some(day => assignmentsForDay(day).length > 0);
      node.innerHTML = `<div class="shift-timeline">
        ${hasAssignments ? '' : `<div class="shift-timeline-empty"><i data-lucide="calendar-plus"></i><span>No agents scheduled in this range.</span>${state.data.permissions?.manage ? '<button class="btn btn-ghost btn-sm" type="button" data-shift-open="assignment">Add Assignment</button>' : ''}</div>`}
        ${days.map(renderTimelineDay).join('')}
      </div>`;
    }
    $('#shiftTimelineMeta').textContent = `${state.data.range.start} to ${state.data.range.end}`;
    icons();
  }

  function warningRows() {
    const warnings = state.data.warnings || {};
    const rows = [];
    (warnings.conflicts || []).forEach(item => rows.push({
      ...item, severity: 'critical', label: 'Conflict', type: 'conflict', icon: 'triangle-alert',
      suggestion: 'Adjust one of the overlapping shifts or assign a replacement agent.',
    }));
    (warnings.coverage || []).forEach(item => {
      const holiday = item.day_type === 'public_holiday';
      rows.push({
        ...item,
        severity: Number(item.missing_agents || 0) > 2 ? 'critical' : 'warning',
        label: holiday ? 'Holiday Coverage' : 'Coverage Gap',
        type: holiday ? 'holiday-coverage' : 'coverage-gap',
        icon: holiday ? 'calendar-days' : 'shield-alert',
        suggestion: `Assign ${Math.max(1, Number(item.missing_agents || 1))} more agent(s) to ${String(item.start_time || '').slice(0, 5)}-${String(item.end_time || '').slice(0, 5)}.`,
      });
    });
    (warnings.jumpshift || []).forEach(item => rows.push({
      ...item, severity: 'warning', label: 'Jumpshift', type: 'jumpshift', icon: 'clock-alert',
      suggestion: 'Move the next shift later or assign a replacement to restore minimum rest.',
    }));
    (state.data.recap || []).filter(row => row.status === 'Under Target').forEach(row => rows.push({
      severity: 'info', label: 'Under Target', type: 'under-target', icon: 'trending-down',
      message: `${row.agent_name} is ${minutes(Math.abs(row.difference_minutes))} below target.`,
      date: state.data.range.end,
      user_id: row.user_id,
      suggestion: `Add a short shift for ${row.agent_name} to close the workload gap.`,
    }));
    (state.data.recap || []).filter(row => ['Overtime Risk', 'Critical Overload'].includes(row.status)).forEach(row => rows.push({
      severity: row.status === 'Critical Overload' ? 'critical' : 'warning',
      label: 'Overtime Risk', type: 'overtime', icon: 'timer-off',
      message: `${row.agent_name} has ${minutes(row.total_minutes)} scheduled.`,
      date: state.data.range.end,
      user_id: row.user_id,
      suggestion: `Reduce or reassign part of ${row.agent_name}'s weekly schedule.`,
    }));
    const dismissed = new Set(state.data.dismissed_warning_keys || []);
    return rows.map((row, index) => {
      const firstAssignmentId = Number(row.assignment_id || row.next_assignment_id || row.assignment_ids?.[0] || 0);
      const relatedAssignment = firstAssignmentId
        ? (state.data.assignments || []).find(item => Number(item.id) === firstAssignmentId)
        : null;
      const date = row.date || row.assignment_date || relatedAssignment?.assignment_date || '';
      const type = row.type || slug(row.label);
      const target = ['coverage-gap', 'holiday-coverage'].includes(type)
        ? 'timeline'
        : ['under-target', 'overtime', 'over-target'].includes(type)
          ? 'workload'
          : ['conflict', 'duplicate-assignment', 'overlapping-assignment', 'approval-pending'].includes(type)
            ? 'audit'
            : 'audit';
      const keySource = [type, row.user_id || relatedAssignment?.user_id || '', date, firstAssignmentId, row.message].join('|');
      return {
        unresolved: row.unresolved !== false,
        ...row,
        id: `${type}-${index}`,
        warning_key: `${type}:${hashText(keySource)}`,
        date,
        user_id: row.user_id || relatedAssignment?.user_id || 0,
        assignment_id: firstAssignmentId,
        target,
      };
    }).filter(row => !dismissed.has(row.warning_key));
  }

  function renderWarnings() {
    const allRows = warningRows();
    const unresolvedOnly = $('#shiftWarningsUnresolved')?.checked ?? true;
    const rows = allRows.filter(row => (state.warningType === 'all' || row.type === state.warningType) && (!unresolvedOnly || row.unresolved));
    const count = $('#shiftWarningCount');
    if (count) count.textContent = allRows.length;

    const typeSelect = $('#shiftWarningType');
    if (typeSelect) {
      const current = state.warningType;
      const types = [...new Map(allRows.map(row => [row.type, row.label])).entries()];
      typeSelect.innerHTML = '<option value="all">All warning types</option>' + types.map(([value, label]) => `<option value="${esc(value)}">${esc(label)}</option>`).join('');
      typeSelect.value = types.some(([value]) => value === current) ? current : 'all';
      state.warningType = typeSelect.value;
    }

    const grouped = rows.reduce((groups, row) => {
      const group = groups.get(row.type) || { label: row.label, severity: row.severity, icon: row.icon, rows: [] };
      group.rows.push(row);
      if (row.severity === 'critical') group.severity = 'critical';
      groups.set(row.type, group);
      return groups;
    }, new Map());

    $('#shiftWarningList').innerHTML = grouped.size ? [...grouped.entries()].map(([type, group]) => `
      <article class="shift-warning-group is-${esc(group.severity)}">
        <div class="shift-warning-group-head">
          <span class="shift-warning-group-icon"><i data-lucide="${esc(group.icon)}" class="icon-sm"></i></span>
          <div>
            <div class="shift-warning-group-title">
              <strong>${esc(group.label)}</strong>
              <span class="shift-warning-count">${group.rows.length}</span>
            </div>
            <small>${group.rows.length === 1 ? 'Operational warning' : 'Operational warnings'}</small>
          </div>
          <span class="shift-severity is-${esc(group.severity)}">${esc(group.severity)}</span>
        </div>
        <div class="shift-warning-preview">${esc(group.rows[0].message)}</div>
        <details>
          <summary><span>View details</span><i data-lucide="chevron-down" class="icon-xs"></i></summary>
          <div class="shift-warning-details">
            ${group.rows.slice(0, 50).map(row => `
              <div class="shift-warning-detail">
                <div><strong>${esc(row.message)}</strong><small>${esc(row.date || row.assignment_date || '')}</small></div>
                <p><i data-lucide="lightbulb" class="icon-xs"></i>${esc(row.suggestion || '')}</p>
                <div class="shift-warning-actions">
                  <button class="btn btn-ghost btn-sm" type="button" data-warning-target="${esc(row.target)}" data-warning-user="${Number(row.user_id || 0)}" data-warning-date="${esc(row.date || '')}" data-warning-assignment="${Number(row.assignment_id || 0)}">View in ${esc(row.target === 'timeline' ? 'Timeline' : row.target === 'workload' ? 'Workload Recap' : 'Assignment Audit')}</button>
                  <button class="btn btn-ghost btn-sm" type="button" data-dismiss-warning="${esc(row.warning_key)}" data-warning-type="${esc(row.type)}" data-warning-user="${Number(row.user_id || 0)}" data-warning-date="${esc(row.date || '')}" data-warning-assignment="${Number(row.assignment_id || 0)}" data-warning-message="${esc(row.message)}">Dismiss</button>
                </div>
              </div>`).join('')}
          </div>
        </details>
      </article>`).join('') : '<div class="shift-empty-state is-compact"><i data-lucide="badge-check"></i>No schedule warning matches this view.</div>';
  }

  function recapVisible(row) {
    if (!state.recapFilters.size) return true;
    if (state.recapFilters.has('under') && row.status !== 'Under Target') return false;
    if (state.recapFilters.has('over') && !['Over Target', 'Overtime Risk', 'Critical Overload'].includes(row.status)) return false;
    if (state.recapFilters.has('jumpshift') && !row.jumpshift_count) return false;
    if (state.recapFilters.has('conflict') && !row.conflict_count) return false;
    return true;
  }

  function renderRecap() {
    const rows = (state.data.recap || []).filter(recapVisible).filter(row => {
      if (state.workloadFilter === 'under') return row.status === 'Under Target';
      if (state.workloadFilter === 'over') return ['Over Target', 'Overtime Risk', 'Critical Overload'].includes(row.status);
      if (state.workloadFilter === 'overtime') return ['Overtime Risk', 'Critical Overload'].includes(row.status);
      if (state.workloadFilter === 'jumpshift') return Number(row.jumpshift_count) > 0;
      if (state.workloadFilter === 'none') return row.status === 'No Schedule';
      return true;
    });
    $('#shiftRecapMeta').textContent = `${rows.length} agent(s)`;
    $('#shiftRecapBody').innerHTML = rows.length ? rows.map(row => `
      <tr data-workload-row="${row.user_id}">
        <td class="shift-recap-agent"><strong>${esc(row.agent_name)}</strong><small>#${row.user_id}</small></td>
        <td>${esc(row.division_name)}</td><td>${row.working_days}</td><td>${minutes(row.total_minutes)}</td>
        <td>${minutes(row.regular_minutes)}</td><td>${minutes(row.overtime_minutes)}</td>
        <td>${minutes(row.holiday_minutes)}</td><td>${minutes(row.standby_minutes)}</td>
        <td>${minutes(row.target_minutes)}</td>
        <td>${row.difference_minutes > 0 ? '+' : row.difference_minutes < 0 ? '-' : ''}${minutes(Math.abs(row.difference_minutes))}</td>
        <td>${row.minimum_rest_minutes === null
          ? '—'
          : `${minutes(row.minimum_rest_minutes)}${Number(row.minimum_rest_minutes) < Number(state.data.settings?.minimum_rest_between_shifts_minutes || 0) ? ' ⚠' : ''}`}</td>
        <td><span class="shift-status ${slug(row.status)}">${esc(row.status)}</span></td>
      </tr>`).join('') : '<tr><td colspan="12"><div class="shift-empty-state">No workload recap matches the filters.</div></td></tr>';
    renderRecapMini();
  }

  function renderRecapMini() {
    const recap = state.data.recap || [];
    const assigned = recap.filter(row => Number(row.total_minutes) > 0);
    const average = assigned.length ? Math.round(assigned.reduce((sum, row) => sum + Number(row.total_minutes || 0), 0) / assigned.length) : 0;
    const metrics = [
      ['Total Agents', recap.length],
      ['Average Hours', minutes(average)],
      ['Under Target', recap.filter(row => row.status === 'Under Target').length],
      ['Over Target', recap.filter(row => ['Over Target', 'Overtime Risk', 'Critical Overload'].includes(row.status)).length],
      ['Overtime Risk', recap.filter(row => ['Overtime Risk', 'Critical Overload'].includes(row.status)).length],
      ['Jumpshift Risk', recap.filter(row => Number(row.jumpshift_count) > 0).length],
    ];
    const node = $('#shiftRecapMini');
    if (node) node.innerHTML = metrics.map(([label, value]) => `<div><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('');
  }

  function assignmentWarnings(row) {
    const labels = [];
    if ((state.data.warnings?.jumpshift || []).some(item => Number(item.previous_assignment_id) === row.id || Number(item.next_assignment_id) === row.id)) labels.push('Jumpshift');
    if ((state.data.warnings?.conflicts || []).some(item => (item.assignment_ids || []).map(Number).includes(row.id))) labels.push('Conflict');
    if (row.availability_status && row.availability_status !== 'available') labels.push(row.availability_status.replace('_', ' '));
    return labels;
  }

  function renderAssignments() {
    const rows = state.data.assignments || [];
    $('#shiftAssignmentMeta').textContent = `${rows.length} assignment(s)`;
    $('#shiftAssignmentBody').innerHTML = rows.length ? rows.map(row => {
      const warnings = assignmentWarnings(row);
      const manageActions = state.data.permissions?.manage ? `
        <button class="btn btn-ghost btn-icon" type="button" data-edit-assignment="${row.id}" title="Edit assignment" aria-label="Edit assignment"><i data-lucide="pencil" class="icon-xs"></i></button>
        ${row.status === 'assigned' || row.approval_status === 'pending' ? `<button class="btn btn-ghost btn-icon" type="button" data-confirm-assignment="${row.id}" title="Confirm assignment" aria-label="Confirm assignment"><i data-lucide="check" class="icon-xs"></i></button>` : ''}
      ` : '';
      const actions = `<div class="shift-action-group">${manageActions}<button class="btn btn-ghost btn-icon" type="button" data-history-assignment="${row.id}" title="View assignment history" aria-label="View assignment history"><i data-lucide="history" class="icon-xs"></i></button></div>`;
      return `<tr data-assignment-row="${row.id}">
        <td>${esc(shortDate(row.assignment_date))}</td>
        <td class="shift-assignment-agent"><strong>${esc(row.agent_name)}</strong><small>#${row.user_id}</small></td>
        <td>${esc(row.division_name)}</td>
        <td><span class="shift-config-color" style="--shift-color:${esc(row.color_label)}"></span> ${esc(row.shift_name)}</td>
        <td>${esc(time(row.start_datetime))}-${esc(time(row.end_datetime))}</td><td>${minutes(row.calculated_duration_minutes)}</td>
        <td>${esc(row.assignment_type_name)}</td><td><span class="shift-status ${slug(row.status)}">${esc(row.status.replace('_', ' '))}</span></td>
        <td><span class="shift-status ${slug(row.approval_status)}">${esc(row.approval_status.replace('_', ' '))}</span></td>
        <td>${warnings.length ? warnings.map(item => `<span class="shift-status ${slug(item)}">${esc(item)}</span>`).join(' ') : '—'}</td>
        <td><span class="shift-status ${slug(row.source || 'manual')}">${esc(String(row.source || 'manual').replaceAll('_', ' '))}</span></td>
        <td class="shift-last-modified">${esc(formatDateTime(row.updated_at))}</td>
        <td>${actions}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="13"><div class="shift-empty-state is-compact">No assignment matches the global filters.</div></td></tr>';
  }

  function renderTodayCoverage() {
    const node = $('#shiftTodayCoverage');
    if (!node) return;
    const coverage = state.data.summary?.today_coverage || {};
    const scheduled = Number(coverage.scheduled_agents || 0);
    const required = Number(coverage.minimum_agents || 0);
    const missing = Number(coverage.missing_agents || 0);
    node.innerHTML = `
      <div class="shift-today-coverage">
        <strong>${scheduled} / ${required}</strong>
        <span>${required ? 'agents scheduled vs minimum required' : 'No coverage rule for today'}</span>
        <small class="${missing ? 'is-missing' : 'is-ready'}">${missing ? `${missing} coverage slot${missing === 1 ? '' : 's'} missing.` : 'Today is covered.'}</small>
      </div>`;
  }

  function renderInsights() {
    const summary = state.data.summary || {};
    const score = Math.max(0, 100
      - Math.min(35, Number(summary.coverage_gaps || 0) * 2)
      - Math.min(24, Number(summary.conflicts || 0) * 12)
      - Math.min(20, Number(summary.jumpshift_risk || 0) * 8)
      - Math.min(15, Number(summary.overtime_risk || 0) * 5)
      - Math.min(12, Number(summary.under_target || 0) * 2));
    const healthStatus = score >= 85 ? 'Healthy' : score >= 65 ? 'Needs Attention' : 'Critical';
    const scoreNode = $('#shiftHealthScore');
    const bar = $('#shiftHealthBar');
    const statusNode = $('#shiftHealthStatus');
    if (scoreNode) scoreNode.textContent = `${score} / 100`;
    if (bar) {
      bar.style.width = `${score}%`;
      bar.dataset.health = score >= 85 ? 'healthy' : score >= 65 ? 'attention' : 'critical';
    }
    if (statusNode) statusNode.textContent = healthStatus;

    const holiday = summary.upcoming_holiday;
    const holidayNode = $('#shiftInsightHoliday');
    if (holidayNode) {
      if (!holiday) {
        holidayNode.innerHTML = '<div class="shift-insight-empty">No upcoming holiday found.</div>';
      } else {
        const coverage = (state.data.assignments || []).filter(row =>
          row.assignment_date === holiday.holiday_date
          && ['holiday_coverage', 'lembur', 'standby', 'replacement_shift'].includes(row.assignment_type)
          && !['cancelled', 'no_show', 'replaced'].includes(row.status));
        const agents = Array.isArray(holiday.assigned_agents) ? holiday.assigned_agents : [...new Set(coverage.map(row => row.agent_name))];
        const warningMissing = (state.data.warnings?.coverage || [])
          .filter(row => (row.date || row.assignment_date) === holiday.holiday_date)
          .reduce((sum, row) => sum + Number(row.missing_agents || 0), 0);
        const missing = Number(holiday.missing_slots ?? warningMissing);
        holidayNode.innerHTML = `
          <div class="shift-holiday-insight-compact">
            <strong class="shift-holiday-name" title="${esc(holiday.holiday_name)}">${esc(holiday.holiday_name)}</strong>
            <span class="shift-holiday-date">${esc(shortDate(holiday.holiday_date))}</span>
            <div class="shift-holiday-status-row">
              <span class="shift-holiday-coverage">${agents.length ? `${agents.length} assigned` : 'No coverage assigned'}</span>
              ${missing ? `<span class="shift-missing-chip">${missing} missing</span>` : '<span class="shift-ready-chip">Ready</span>'}
            </div>
          </div>`;
      }
    }

    const priority = { critical: 0, warning: 1, info: 2 };
    const warning = warningRows().sort((a, b) => (priority[a.severity] ?? 9) - (priority[b.severity] ?? 9))[0];
    const warningNode = $('#shiftInsightWarning');
    if (warningNode) {
      if (warning) {
        warningNode.innerHTML = `
          <div class="shift-active-warning-compact is-${esc(warning.severity)}">
            <div class="shift-warning-badge-row">
              <span class="shift-warning-badge severity-${esc(warning.severity)}">${esc(warning.severity)}</span>
              <span class="shift-warning-label">${esc(warning.label.toUpperCase())}</span>
            </div>
            <p class="shift-warning-msg" title="${esc(warning.message)}">${esc(warning.message)}</p>
            <button class="shift-warning-link" type="button" data-workspace-target="warnings">Review warnings</button>
          </div>`;
      } else {
        warningNode.innerHTML = `
          <div class="shift-active-warning-compact is-none">
            <span class="shift-warning-badge severity-none">OK</span>
            <p class="shift-warning-msg">No critical warnings found.</p>
          </div>`;
      }
    }
    const compactSummary = $('#shiftInsightsSummary');
    if (compactSummary) {
      compactSummary.innerHTML = `
        <span><strong>Shift Health:</strong> ${esc(score)} ${esc(healthStatus)}</span>
        <span><strong>Coverage Gaps:</strong> ${esc(summary.coverage_gaps || 0)}</span>
        <span><strong>Upcoming Holiday:</strong> ${holiday ? esc(shortDate(holiday.holiday_date)) : 'None'}</span>
        <span><strong>Active Warning:</strong> ${warning ? esc(warning.label) : 'None'}</span>`;
    }
  }

  function renderConfig() {
    if (!state.data.permissions?.settings && !state.data.permissions?.monthly_templates) return;
    const monthlySearch = ($('#shiftMonthlyTemplateSearch')?.value || '').trim().toLowerCase();
    const monthlyStatus = $('#shiftMonthlyTemplateStatus')?.value || 'all';
    const monthlyTemplates = (state.data.monthly_templates || []).filter(row => {
      if (monthlySearch && !`${row.name} ${row.division_name} ${row.created_by_name}`.toLowerCase().includes(monthlySearch)) return false;
      return monthlyStatus === 'all' || row.status === monthlyStatus;
    });
    const monthlyList = $('#shiftMonthlyTemplateList');
    if (monthlyList) {
      monthlyList.innerHTML = monthlyTemplates.length ? monthlyTemplates.map(row => `
        <article class="shift-monthly-template-card">
          <div class="shift-monthly-template-head">
            <div><strong>${esc(row.name)}</strong><span>${esc(monthLabel(row.target_month))} · ${esc(row.division_name)}</span></div>
            <span class="shift-status ${slug(row.status)}">${esc(row.status)}</span>
          </div>
          <div class="shift-monthly-template-metrics">
            <span><strong>${row.agent_count}</strong> agents</span>
            <span><strong>${row.assignment_count}</strong> assignments</span>
            <span><strong>${row.applied_count}</strong> applied</span>
          </div>
          <div class="shift-monthly-template-meta">
            <span>Created by ${esc(row.created_by_name)}</span>
            <span>Updated ${esc(row.updated_at)}</span>
          </div>
          <div class="shift-config-card-actions">
            <button class="btn btn-ghost btn-sm" type="button" data-preview-monthly="${row.id}">Preview</button>
            ${['draft', 'previewed'].includes(row.status) ? `<button class="btn btn-primary btn-sm" type="button" data-apply-monthly="${row.id}">Review & Apply</button>` : ''}
            <button class="btn btn-ghost btn-sm" type="button" data-duplicate-monthly="${row.id}">Duplicate</button>
            ${['draft', 'previewed'].includes(row.status) ? `<button class="btn btn-ghost btn-sm" type="button" data-edit-monthly="${row.id}">Edit</button>` : ''}
            ${row.status !== 'archived' ? `<button class="btn btn-ghost btn-sm" type="button" data-archive-monthly="${row.id}">Archive</button>` : ''}
          </div>
        </article>`).join('') : '<div class="shift-empty-state is-compact">No monthly templates match this filter.</div>';
    }

    if (!state.data.permissions?.settings) return;
    const templateSearch = ($('#shiftTemplateSearch')?.value || '').trim().toLowerCase();
    const templateStatus = $('#shiftTemplateStatus')?.value || 'all';
    const templates = (state.data.templates || []).filter(row => {
      if (templateSearch && !`${row.shift_name} ${row.notes || ''} ${row.default_assignment_type}`.toLowerCase().includes(templateSearch)) return false;
      if (templateStatus === 'active' && !Number(row.is_active)) return false;
      if (templateStatus === 'inactive' && Number(row.is_active)) return false;
      return true;
    });
    const templateList = $('#shiftTemplateList');
    if (templateList) templateList.innerHTML = templates.map(row => `
      <article class="shift-config-card">
        <div class="shift-config-card-head"><span class="shift-config-color" style="--shift-color:${esc(row.color_label)}"></span><strong>${esc(row.shift_name)}</strong><span class="shift-status ${Number(row.is_active) ? 'normal' : 'no-schedule'}">${Number(row.is_active) ? 'Active' : 'Inactive'}</span></div>
        <p>${esc(row.notes || 'Flexible reusable template')}</p>
        <div class="shift-config-card-meta"><span>${esc(String(row.start_time).slice(0, 5))}-${esc(String(row.end_time).slice(0, 5))}</span><span>${minutes(row.duration_minutes)}</span><span>${esc(row.default_assignment_type.replaceAll('_', ' '))}</span></div>
        <div class="shift-config-card-actions"><button class="btn btn-ghost btn-sm" type="button" data-edit-template="${row.id}">Edit</button>${Number(row.is_active) ? `<button class="btn btn-ghost btn-sm" type="button" data-deactivate="template:${row.id}">Deactivate</button>` : ''}</div>
      </article>`).join('') || '<div class="shift-empty-state is-compact">No templates match this filter.</div>';

    const holidayList = $('#shiftHolidayList');
    if (holidayList) holidayList.innerHTML = (state.data.holidays || []).map(row => {
      const assigned = (state.data.assignments || []).filter(assignment =>
        assignment.assignment_date === row.holiday_date
        && ['holiday_coverage', 'lembur', 'standby'].includes(assignment.assignment_type)
        && !['cancelled', 'no_show', 'replaced'].includes(assignment.status)).length;
      return `
      <article class="shift-config-card">
        <div class="shift-config-card-head"><strong>${esc(row.holiday_name)}</strong><span class="shift-status ${assigned ? 'normal' : 'pending'}">${assigned ? `${assigned} assigned` : 'No coverage'}</span></div>
        <p>${esc(row.notes || row.holiday_type.replaceAll('_', ' '))}</p>
        <div class="shift-config-card-meta"><span>${esc(shortDate(row.holiday_date))}</span><span>${esc(row.holiday_type.replaceAll('_', ' '))}</span><span>${esc(row.source)}</span></div>
        ${Number(row.id) ? `<div class="shift-config-card-actions"><button class="btn btn-ghost btn-sm" type="button" data-edit-holiday="${row.id}">Edit</button><button class="btn btn-ghost btn-sm" type="button" data-deactivate="holiday:${row.id}">Deactivate</button></div>` : ''}
      </article>`;
    }).join('');

    const coverageList = $('#shiftCoverageList');
    if (coverageList) coverageList.innerHTML = (state.data.coverage_rules || []).map(row => `
      <article class="shift-config-card">
        <div class="shift-config-card-head"><strong>${esc(row.day_type.replaceAll('_', ' '))}</strong><span class="shift-status ${Number(row.is_active) ? 'normal' : 'no-schedule'}">${Number(row.is_active) ? 'Active' : 'Inactive'}</span></div>
        <p>${esc(row.notes || 'Minimum staffing rule')}</p>
        <div class="shift-config-card-meta"><span>${esc(row.division_name)}</span><span>${esc(String(row.start_time).slice(0, 5))}-${esc(String(row.end_time).slice(0, 5))}</span><span>Min ${row.minimum_agents}</span></div>
        <div class="shift-config-card-actions"><button class="btn btn-ghost btn-sm" type="button" data-edit-coverage="${row.id}">Edit</button>${Number(row.is_active) ? `<button class="btn btn-ghost btn-sm" type="button" data-deactivate="coverage_rule:${row.id}">Deactivate</button>` : ''}</div>
      </article>`).join('');
    fillSettingsForm();
  }

  function fillSettingsForm() {
    const form = $('#shiftSettingsForm');
    if (!form) return;
    const settings = state.data.settings || {};
    const values = {
      weekly_target_hours: (settings.weekly_target_minutes || 0) / 60,
      daily_target_hours: (settings.daily_target_minutes || 0) / 60,
      overtime_threshold_hours: (settings.overtime_threshold_minutes || 0) / 60,
      max_weekly_hours: (settings.max_weekly_minutes || 0) / 60,
      max_daily_hours: (settings.max_daily_minutes || 0) / 60,
      minimum_rest_hours: (settings.minimum_rest_between_shifts_minutes || 0) / 60,
      timeline_snap_minutes: settings.timeline_snap_minutes || 15,
      minimum_shift_hours: (settings.minimum_shift_minutes || 0) / 60,
      normal_working_days_per_week: settings.normal_working_days_per_week || 5,
      holiday_minimum_agents: settings.holiday_minimum_agents || 2,
    };
    Object.entries(values).forEach(([name, value]) => { if (form.elements[name]) form.elements[name].value = value; });
    form.elements.count_standby_as_work_hour.checked = Boolean(Number(settings.count_standby_as_work_hour));
  }

  function activateWorkspace(tabName, persist = true) {
    const pane = $(`[data-workspace-pane="${tabName}"]`);
    const tab = $(`[data-workspace-tab="${tabName}"]`);
    if (!pane || !tab) tabName = 'timeline';
    state.activeTab = tabName;
    $$('[data-workspace-tab]').forEach(button => {
      const active = button.dataset.workspaceTab === tabName;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    $$('[data-workspace-pane]').forEach(item => {
      const active = item.dataset.workspacePane === tabName;
      item.classList.toggle('active', active);
      item.hidden = !active;
    });
    syncInsightsMode();
    if (persist) savePreference('tab', tabName);
  }

  function syncInsightsMode() {
    const container = $('#shiftScheduleInsights');
    const toggle = $('#shiftInsightsToggle');
    if (!container || !toggle) return;
    const expanded = state.insightsExpanded;
    container.classList.toggle('is-collapsed', !expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const label = $('span', toggle);
    if (label) label.textContent = expanded ? 'Collapse' : 'Expand';
    const icon = $('[data-lucide]', toggle);
    if (icon) icon.setAttribute('data-lucide', expanded ? 'chevron-up' : 'chevron-down');
    icons();
  }

  function syncPreferenceControls() {
    $$('[data-shift-view]').forEach(button => button.classList.toggle('active', button.dataset.shiftView === state.view));
    $$('[data-workload-filter]').forEach(button => button.classList.toggle('active', button.dataset.workloadFilter === state.workloadFilter));
    activateWorkspace(state.activeTab, false);
  }

  function updateRiskCount() {
    const count = state.recapFilters.size + ($('#shiftHolidayOnly')?.checked ? 1 : 0);
    const node = $('#shiftRiskCount');
    if (node) node.textContent = count ? String(count) : '';
  }

  function syncRangePickers() {
    [
      [$('#shiftFilterStart'), state.data.range?.start || ''],
      [$('#shiftFilterEnd'), state.data.range?.end || ''],
    ].forEach(([input, value]) => {
      if (!input) return;
      if (input._flatpickr) input._flatpickr.destroy();
      input.type = 'date';
      input.classList.add('form-input');
      input.value = value;
    });
  }

  function renderAll() {
    populateOptions();
    renderSummary();
    renderTimeline();
    renderWarnings();
    renderRecap();
    renderAssignments();
    renderTodayCoverage();
    renderInsights();
    renderConfig();
    updateExportUrl();
    updateRiskCount();
    syncPreferenceControls();
    syncRangePickers();
    icons();
  }

  function filterPayload() {
    return {
      start: $('#shiftFilterStart').value,
      end: $('#shiftFilterEnd').value,
      division_id: $('#shiftFilterDivision').value,
      user_id: $('#shiftFilterAgent').value,
      assignment_type: $('#shiftFilterType').value,
      status: $('#shiftFilterStatus').value,
      holiday_only: $('#shiftHolidayOnly').checked ? 1 : '',
      q: $('#shiftFilterSearch').value.trim(),
    };
  }

  async function refresh(message = '') {
    if (state.loading) return;
    const start = $('#shiftFilterStart')?.value || '';
    const end = $('#shiftFilterEnd')?.value || '';
    if (!start || !end) {
      notify('Select both a From and To date.', 'error');
      return;
    }
    if (start > end) {
      notify('The From date must be on or before the To date.', 'error');
      $('#shiftFilterEnd')?.focus();
      return;
    }
    state.loading = true;
    root.classList.add('is-loading');
    try {
      const json = await api('data', filterPayload(), 'GET');
      state.data = json.data;
      renderAll();
      if (message) notify(message, 'success');
    } catch (error) {
      notify(error.message, 'error');
    } finally {
      state.loading = false;
      root.classList.remove('is-loading');
    }
  }

  function updateExportUrl() {
    const href = `shifting-assignment-export.php?${new URLSearchParams(filterPayload()).toString()}`;
    $$('[data-shift-export], #shiftExportBtn').forEach(link => { link.href = href; });
  }

  function modal(name) {
    return $(`#shift${name[0].toUpperCase()}${name.slice(1)}Modal`);
  }

  function ensureSingletonModal(name, node) {
    const definition = singletonModalDefinitions[name];
    if (!definition || !node) return $('form', node);
    const forms = $$(`[id="${definition.formId}"]`, root);
    const form = forms.find(candidate => node.contains(candidate)) || forms[0] || null;
    forms.forEach(candidate => {
      if (candidate !== form) candidate.remove();
    });
    if (!form) return null;

    const bodies = $$(':scope > [data-shift-canonical-body]', form);
    const invalidFields = Object.entries(definition.fields).some(([fieldName, count]) =>
      $$(`[name="${fieldName}"]`, form).length !== count);
    if (bodies.length === 1 && !invalidFields) return form;

    const canonical = canonicalModalBodies.get(definition.formId);
    if (!canonical) return form;
    $$(':scope > .modal-body', form).forEach(body => body.remove());
    const body = canonical.cloneNode(true);
    const footer = $(':scope > .modal-foot', form);
    form.insertBefore(body, footer || null);
    window.TRACSDropdowns?.init?.(body);
    populateOptions();
    return form;
  }

  function openModal(name, preset = {}) {
    const node = modal(name);
    if (!node) return;
    $$(`[id="${node.id}"]`).forEach(candidate => {
      if (candidate !== node) candidate.remove();
    });
    $$('[data-shift-modal]:not(.hidden)', root).forEach(openNode => {
      if (openNode !== node) closeModal(openNode, { bypassUnsaved: true });
    });
    const form = ensureSingletonModal(name, node) || $('form', node);
    if (form && !preset.keep) {
      form.reset();
      clearFormErrors(form);
    }
    if (name === 'assignment') {
      resetAssignmentStatusOptions(form);
      setPickerValue(form.elements.assignment_date, preset.assignment_date || state.data.range?.start || dateKey(new Date()));
      setPickerValue(form.elements.start_time, preset.start_time || '08:00');
      setPickerValue(form.elements.end_time, preset.end_time || '16:00');
      if (preset.assignment_type) form.elements.assignment_type.value = preset.assignment_type;
      updateAssignmentDivision();
      updateDurationPreview();
    }
    if (name === 'monthlyTemplate' && form && !preset.keep) {
      form.elements.target_month.value = nextMonthValue();
      form.elements.status.value = 'draft';
      form.elements.repeat_weekly_pattern.checked = true;
      form.elements.warn_coverage_gap.checked = true;
      const sunday = [...form.querySelectorAll('input[name="rest_days"]')].find(input => input.value === '7');
      if (sunday) sunday.checked = true;
      filterMonthlyAgents();
    }
    state.activeModal = node;
    lockModalScroll();
    if (typeof window.tracsOpenModalElement === 'function') window.tracsOpenModalElement(node);
    else {
      node.classList.remove('hidden');
      node.removeAttribute('aria-hidden');
    }
    requestAnimationFrame(() => {
      window.TRACSUnsavedChanges?.markSaved(node);
      window.TRACSDropdowns?.syncAll?.();
      node.querySelector('[autofocus], input:not([type="hidden"]):not([readonly]), select, textarea, button:not(.modal-close)')?.focus({ preventScroll: true });
    });
    icons();
  }

  function closeModal(node, options = {}) {
    const overlay = node?.closest?.('.modal-overlay') || node;
    if (!overlay) return;
    if (typeof window.tracsCloseModalElement === 'function') {
      window.tracsCloseModalElement(overlay, options);
    } else {
      overlay.classList.add('hidden');
      overlay.setAttribute('aria-hidden', 'true');
    }
    requestAnimationFrame(syncModalState);
  }

  function lockModalScroll() {
    if (!document.body.classList.contains('sa-modal-open')) {
      state.modalBodyOverflow = document.body.style.overflow;
    }
    document.body.classList.add('sa-modal-open');
    document.body.style.overflow = 'hidden';
  }

  function syncModalState() {
    const visible = $('[data-shift-modal]:not(.hidden)', root);
    state.activeModal = visible || null;
    if (visible) {
      lockModalScroll();
      visible.setAttribute('aria-hidden', 'false');
      return;
    }
    document.body.classList.remove('sa-modal-open');
    document.body.style.overflow = state.modalBodyOverflow;
    state.modalBodyOverflow = '';
  }

  function updateAssignmentDivision() {
    const form = $('#shiftAssignmentForm');
    if (!form) return;
    const agent = (state.data.agents || []).find(row => String(row.id) === form.elements.user_id.value);
    form.elements.division_name.value = agent?.division_name || '';
  }

  function resetAssignmentStatusOptions(form) {
    const allowed = new Set(['assigned', 'confirmed', 'cancelled', 'no_show', 'replaced']);
    [...(form?.elements?.status?.options || [])].forEach(option => {
      if (!allowed.has(option.value)) option.remove();
    });
    if (form?.elements?.status) form.elements.status.value = 'assigned';
  }

  function updateDurationPreview() {
    const form = $('#shiftAssignmentForm');
    if (!form) return;
    const start = form.elements.start_time.value;
    const end = form.elements.end_time.value;
    if (!start || !end) return;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const startMinutes = sh * 60 + sm;
    const endMinutes = eh * 60 + em;
    const crossDay = endMinutes < startMinutes;
    let total = endMinutes - startMinutes;
    if (crossDay) total += 1440;
    total = Math.max(0, total - (Number(form.elements.break_minutes.value) || 0));
    $('#shiftDurationPreview').textContent = `Duration: ${minutes(total)}`;
    const note = $('#shiftCrossDayNote');
    if (note) note.hidden = !crossDay;
  }

  function editAssignment(id) {
    const row = (state.data.assignments || []).find(item => Number(item.id) === Number(id));
    if (!row || !state.data.permissions?.manage) return;
    openModal('assignment');
    const form = $('#shiftAssignmentForm');
    form.elements.id.value = row.id;
    form.elements.user_id.value = row.user_id;
    form.elements.division_name.value = row.division_name;
    setPickerValue(form.elements.assignment_date, row.assignment_date);
    form.elements.shift_template_id.value = row.shift_template_id || '';
    setPickerValue(form.elements.start_time, time(row.start_datetime));
    setPickerValue(form.elements.end_time, time(row.end_datetime));
    form.elements.break_minutes.value = row.break_minutes;
    form.elements.assignment_type.value = row.assignment_type;
    if (![...form.elements.status.options].some(option => option.value === row.status)) {
      const option = document.createElement('option');
      option.value = row.status;
      option.textContent = String(row.status).replaceAll('_', ' ');
      form.elements.status.appendChild(option);
    }
    form.elements.status.value = row.status;
    form.elements.notes.value = row.notes || '';
    form.elements.is_manual_duration_override.value = row.is_manual_duration_override ? 1 : 0;
    updateDurationPreview();
    window.TRACSDropdowns?.syncAll?.();
  }

  function editConfig(kind, id) {
    const maps = {
      template: ['templates', 'shiftTemplateForm', 'template'],
      holiday: ['holidays', 'shiftHolidayForm', 'holiday'],
      coverage: ['coverage_rules', 'shiftCoverageForm', 'coverage'],
    };
    const [collection, formId, modalName] = maps[kind];
    const row = (state.data[collection] || []).find(item => Number(item.id) === Number(id));
    if (!row) return;
    openModal(modalName);
    const form = $(`#${formId}`);
    Object.entries(row).forEach(([key, value]) => {
      const input = form.elements[key];
      if (!input) return;
      if (input.type === 'checkbox') input.checked = Boolean(Number(value));
      else input.value = value ?? '';
    });
    if (kind === 'template') {
      setPickerValue(form.elements.start_time, String(row.start_time).slice(0, 5));
      setPickerValue(form.elements.end_time, String(row.end_time).slice(0, 5));
    }
    if (kind === 'coverage') {
      setPickerValue(form.elements.start_time, String(row.start_time).slice(0, 5));
      setPickerValue(form.elements.end_time, String(row.end_time).slice(0, 5));
      toggleCustomDate();
    }
    window.TRACSDropdowns?.syncAll?.();
  }

  function monthlyFormPayload(form = $('#shiftMonthlyTemplateForm')) {
    const payload = formObject(form);
    payload.agent_ids = [...form.elements.agent_ids.selectedOptions].map(option => Number(option.value));
    payload.rest_days = [...form.querySelectorAll('input[name="rest_days"]:checked')].map(input => Number(input.value));
    return payload;
  }

  function filterMonthlyAgents() {
    const form = $('#shiftMonthlyTemplateForm');
    if (!form) return;
    const divisionId = form.elements.division_id.value;
    const selected = new Set([...form.elements.agent_ids.selectedOptions].map(option => option.value));
    const agents = (state.data.agents || []).filter(row => !divisionId || String(row.division_id) === divisionId);
    fillSelect(form.elements.agent_ids, agents, 'id', row => `${row.agent_name} · ${row.division_name}`);
    [...form.elements.agent_ids.options].forEach(option => { option.selected = selected.has(option.value); });
  }

  function renderMonthlyPreview(preview, templateId = 0) {
    state.monthlyPreview = preview;
    state.monthlyPreviewTemplateId = Number(templateId) || 0;
    const content = $('#shiftMonthlyPreviewContent');
    const actions = $('#shiftMonthlyPreviewActions');
    if (!content || !actions) return;
    const conflicts = preview.conflicts || [];
    const warnings = preview.warnings || [];
    const items = preview.items || [];
    content.innerHTML = `
      <div class="shift-monthly-preview-summary">
        <div><span>Template</span><strong>${esc(preview.template_name)}</strong></div>
        <div><span>Target Month</span><strong>${esc(monthLabel(preview.target_month))}</strong></div>
        <div><span>Agents</span><strong>${preview.agent_count}</strong></div>
        <div><span>Assignments</span><strong>${preview.assignment_count}</strong></div>
        <div><span>Warnings</span><strong class="${preview.warning_count ? 'is-warning' : ''}">${preview.warning_count}</strong></div>
        <div><span>Conflicts</span><strong class="${preview.conflict_count ? 'is-critical' : ''}">${preview.conflict_count}</strong></div>
      </div>
      ${conflicts.length ? `<section class="shift-monthly-review-block is-critical"><h4>Protected assignments detected</h4><p>Existing confirmed, active, completed, or otherwise overlapping assignments will not be overwritten.</p>${conflicts.slice(0, 20).map(row => `<div><strong>${esc(row.agent_name)}</strong><span>${esc(row.assignment_date)} ${esc(row.start_time)}-${esc(row.end_time)} · ${esc(row.message)}</span></div>`).join('')}</section>` : ''}
      ${warnings.length ? `<section class="shift-monthly-review-block is-warning"><h4>Potential warnings</h4>${warnings.slice(0, 20).map(row => `<div><strong>${esc(String(row.type || 'warning').replaceAll('_', ' '))}</strong><span>${esc(row.date || '')} ${esc(row.message)}</span></div>`).join('')}</section>` : ''}
      <section class="shift-monthly-review-block"><h4>Generated schedule preview</h4>
        <div class="table-wrap"><table class="data-table shift-monthly-preview-table"><thead><tr><th>Date</th><th>Agent</th><th>Shift</th><th>Time</th><th>Type</th></tr></thead><tbody>
          ${items.slice(0, 100).map(row => `<tr><td>${esc(shortDate(row.assignment_date))}</td><td>${esc(row.agent_name || `Agent #${row.agent_id}`)}</td><td>${esc(row.shift_name || '')}</td><td>${esc(String(row.start_time).slice(0, 5))}-${esc(String(row.end_time).slice(0, 5))}</td><td>${esc(String(row.assignment_type).replaceAll('_', ' '))}</td></tr>`).join('')}
        </tbody></table></div>
        ${items.length > 100 ? `<p class="shift-monthly-preview-note">Showing the first 100 of ${items.length} generated assignments.</p>` : ''}
      </section>`;
    actions.innerHTML = `
      <button class="btn btn-ghost" type="button" data-shift-close>Cancel</button>
      ${templateId ? `<button class="btn ${preview.conflict_count ? 'btn-ghost' : 'btn-primary'}" type="button" data-confirm-monthly="${templateId}" data-allow-conflicts="0"${preview.conflict_count ? ' disabled' : ''}>Apply Template</button>` : ''}
      ${templateId && preview.conflict_count ? `<button class="btn btn-primary" type="button" data-confirm-monthly="${templateId}" data-allow-conflicts="1">Apply Non-conflicting (${preview.assignment_count - preview.conflict_count})</button>` : ''}`;
    openModal('monthlyPreview', { keep: true });
    icons();
  }

  async function previewMonthlyTemplate(input, templateId = 0) {
    const content = $('#shiftMonthlyPreviewContent');
    if (content) content.innerHTML = '<div class="shift-empty-state is-compact">Generating monthly preview...</div>';
    openModal('monthlyPreview', { keep: true });
    try {
      const json = await api('preview_monthly_template', input);
      renderMonthlyPreview(json.data, templateId || input.id || input.template_id || 0);
    } catch (error) {
      if (content) content.innerHTML = `<div class="shift-empty-state is-compact">${esc(error.message)}</div>`;
      notify(error.message, 'error');
    }
  }

  async function editMonthlyTemplate(id) {
    try {
      const json = await api('monthly_template', { id }, 'GET');
      const row = json.data;
      openModal('monthlyTemplate');
      const form = $('#shiftMonthlyTemplateForm');
      form.elements.id.value = row.id;
      form.elements.name.value = row.name;
      form.elements.target_month.value = String(row.target_month).slice(0, 7);
      form.elements.division_id.value = row.division_id;
      filterMonthlyAgents();
      const settings = row.settings || {};
      form.elements.shift_template_id.value = settings.shift_template_id || '';
      form.elements.weekend_handling.value = settings.weekend_handling || 'exclude';
      form.elements.status.value = 'draft';
      form.elements.notes.value = settings.notes || '';
      [...form.elements.agent_ids.options].forEach(option => { option.selected = (settings.agent_ids || []).map(String).includes(option.value); });
      [...form.querySelectorAll('input[name="rest_days"]')].forEach(input => { input.checked = (settings.rest_days || []).map(Number).includes(Number(input.value)); });
      ['repeat_weekly_pattern', 'rotate_agents_weekly', 'exclude_public_holidays', 'include_holiday_coverage', 'include_lembur_template', 'prevent_workload_over_target', 'warn_coverage_gap'].forEach(name => {
        form.elements[name].checked = Boolean(settings[name]);
      });
      window.TRACSDropdowns?.syncAll?.();
    } catch (error) {
      notify(error.message, 'error');
    }
  }

  async function viewAssignmentHistory(id) {
    const node = modal('history');
    const content = $('#shiftHistoryContent');
    if (!node || !content) return;
    content.innerHTML = '<div class="shift-empty-state is-compact">Loading assignment history...</div>';
    openModal('history', { keep: true });
    try {
      const json = await api('history', { id }, 'GET');
      const assignment = json.data?.assignment || {};
      const logs = json.data?.history || [];
      content.innerHTML = `
        <div class="shift-history-summary">
          <strong>${esc(assignment.agent_name || 'Assignment')} · ${esc(assignment.shift_name || 'Custom Shift')}</strong>
          <span>${esc(assignment.assignment_date || '')} ${esc(time(assignment.start_datetime))}-${esc(time(assignment.end_datetime))}</span>
          <span class="shift-status ${slug(assignment.status)}">${esc(String(assignment.status || '').replaceAll('_', ' '))}</span>
        </div>
        <div class="shift-history-list">
          ${logs.length ? logs.map(log => `
            <article>
              <span class="shift-history-dot"></span>
              <div><strong>${esc(String(log.action || 'updated').replaceAll('_', ' '))}</strong><p>${esc(log.description || 'Assignment updated')}</p><small>${esc(log.creator_name || 'System')} · ${esc(log.created_at || '')}</small></div>
            </article>`).join('') : '<div class="shift-empty-state is-compact">No recorded changes yet.</div>'}
        </div>`;
      icons();
    } catch (error) {
      content.innerHTML = `<div class="shift-empty-state is-compact">${esc(error.message)}</div>`;
    }
  }

  function startTimelineGesture(event, mode, edge = '') {
    if (event.button !== undefined && event.button !== 0) return;
    event.preventDefault();
    event.stopPropagation();
    const block = event.target.closest('.shift-block');
    const track = block.closest('.shift-track');
    const row = (state.data.assignments || []).find(item => Number(item.id) === Number(block.dataset.assignmentId));
    if (!row || !track) return;
    const originalStart = parseLocal(row.start_datetime);
    const originalEnd = parseLocal(row.end_datetime);
    const originalX = event.clientX;
    const rect = track.getBoundingClientRect();
    const snap = Number(state.data.settings?.timeline_snap_minutes) || 15;
    const minDuration = Number(state.data.settings?.minimum_shift_minutes) || 60;
    const maxDuration = Number(state.data.settings?.max_daily_minutes) || 720;
    let nextStart = new Date(originalStart);
    let nextEnd = new Date(originalEnd);
    let moved = false;
    block.classList.add(mode === 'move' ? 'is-dragging' : 'is-resizing');
    block.setPointerCapture?.(event.pointerId);

    const move = moveEvent => {
      const deltaRaw = (moveEvent.clientX - originalX) / rect.width * 1440;
      const delta = Math.round(deltaRaw / snap) * snap;
      moved = moved || delta !== 0;
      nextStart = new Date(originalStart);
      nextEnd = new Date(originalEnd);
      if (mode === 'move') {
        nextStart.setMinutes(nextStart.getMinutes() + delta);
        nextEnd.setMinutes(nextEnd.getMinutes() + delta);
      } else if (edge === 'left') {
        nextStart.setMinutes(nextStart.getMinutes() + delta);
      } else {
        nextEnd.setMinutes(nextEnd.getMinutes() + delta);
      }
      const duration = (nextEnd - nextStart) / 60000 - Number(row.break_minutes || 0);
      const valid = duration >= minDuration && duration <= maxDuration && nextEnd > nextStart;
      block.style.filter = valid ? '' : 'saturate(.3)';
      const day = parseLocal(`${block.dataset.day} 00:00:00`);
      const dayEnd = addDays(day, 1);
      const segmentStart = nextStart < day ? day : nextStart;
      const segmentEnd = nextEnd > dayEnd ? dayEnd : nextEnd;
      const left = Math.max(0, (segmentStart - day) / 60000 / 1440 * 100);
      const width = Math.max(.45, Math.min(100 - left, (segmentEnd - segmentStart) / 60000 / 1440 * 100));
      block.style.left = `${left}%`;
      block.style.width = `${width}%`;
      const tooltip = $('#shiftResizeTooltip');
      tooltip.hidden = false;
      tooltip.innerHTML = `<strong>${mode === 'move' ? 'Move' : 'Resize'} ${time(dateTimeSql(nextStart))}-${time(dateTimeSql(nextEnd))}</strong><br>Duration ${minutes(Math.max(0, duration))}<br>Snap ${snap}m`;
      const bounds = $('#shiftTimeline')?.getBoundingClientRect() || { left: 0, right: window.innerWidth, top: 0, bottom: window.innerHeight };
      const tooltipLeft = Math.min(bounds.right - tooltip.offsetWidth - 8, Math.max(bounds.left + 8, moveEvent.clientX + 12));
      const tooltipTop = Math.min(bounds.bottom - tooltip.offsetHeight - 8, Math.max(bounds.top + 8, moveEvent.clientY + 12));
      tooltip.style.left = `${Math.max(8, tooltipLeft)}px`;
      tooltip.style.top = `${Math.max(8, tooltipTop)}px`;
    };
    const end = async () => {
      document.removeEventListener('pointermove', move);
      document.removeEventListener('pointerup', end);
      document.removeEventListener('pointercancel', cancel);
      block.classList.remove('is-resizing', 'is-dragging');
      block.style.filter = '';
      $('#shiftResizeTooltip').hidden = true;
      if (!moved) return;
      state.suppressAssignmentClickUntil = Date.now() + 350;
      const duration = (nextEnd - nextStart) / 60000 - Number(row.break_minutes || 0);
      if (duration < minDuration || duration > maxDuration || nextEnd <= nextStart) {
        notify('Invalid shift duration. The resize was reverted.', 'error');
        renderTimeline();
        return;
      }
      try {
        const json = await api('resize_assignment', {
          id: row.id,
          start_datetime: dateTimeSql(nextStart),
          end_datetime: dateTimeSql(nextEnd),
          operation: mode,
        });
        await refresh(json.message || (mode === 'move' ? 'Shift moved successfully.' : 'Shift resized successfully.'));
      } catch (error) {
        notify(error.message, 'error');
        renderTimeline();
      }
    };
    const cancel = () => {
      document.removeEventListener('pointermove', move);
      document.removeEventListener('pointerup', end);
      document.removeEventListener('pointercancel', cancel);
      block.classList.remove('is-resizing', 'is-dragging');
      $('#shiftResizeTooltip').hidden = true;
      renderTimeline();
    };
    document.addEventListener('pointermove', move);
    document.addEventListener('pointerup', end, { once: true });
    document.addEventListener('pointercancel', cancel, { once: true });
  }

  function toggleCustomDate() {
    const form = $('#shiftCoverageForm');
    if (!form) return;
    $('.shift-custom-date', form).hidden = form.elements.day_type.value !== 'custom';
  }

  root.addEventListener('click', async event => {
    if (event.target.matches('[data-shift-modal]')) {
      event.preventDefault();
      event.stopPropagation();
      closeModal(event.target);
      return;
    }
    const open = event.target.closest('[data-shift-open]');
    if (open) {
      openModal(open.dataset.shiftOpen, {
        assignment_type: open.dataset.shiftType || '',
        assignment_date: open.dataset.assignmentDate || '',
      });
      return;
    }
    if (event.target.closest('[data-shift-close]')) {
      event.preventDefault();
      event.stopPropagation();
      closeModal(event.target);
      return;
    }
    const insightsToggle = event.target.closest('#shiftInsightsToggle');
    if (insightsToggle) {
      state.insightsExpanded = insightsToggle.getAttribute('aria-expanded') !== 'true';
      savePreference('insightsExpanded', state.insightsExpanded ? '1' : '0');
      syncInsightsMode();
      return;
    }
    const workspace = event.target.closest('[data-workspace-tab], [data-workspace-target]');
    if (workspace) {
      activateWorkspace(workspace.dataset.workspaceTab || workspace.dataset.workspaceTarget);
      return;
    }
    const warningTarget = event.target.closest('[data-warning-target]');
    if (warningTarget) {
      const targetTab = warningTarget.dataset.warningTarget || 'audit';
      const userId = warningTarget.dataset.warningUser || '';
      const date = warningTarget.dataset.warningDate || '';
      if (userId && userId !== '0') $('#shiftFilterAgent').value = userId;
      if (date) {
        setPickerValue($('#shiftFilterStart'), date);
        setPickerValue($('#shiftFilterEnd'), date);
      }
      window.TRACSDropdowns?.syncAll?.();
      activateWorkspace(targetTab);
      await refresh();
      const assignmentId = Number(warningTarget.dataset.warningAssignment || 0);
      const selector = targetTab === 'timeline' && assignmentId
        ? `.shift-block[data-assignment-id="${assignmentId}"]`
        : targetTab === 'audit' && assignmentId
          ? `[data-assignment-row="${assignmentId}"]`
          : targetTab === 'workload' && userId
            ? `[data-workload-row="${userId}"]`
            : '';
      const related = selector ? $(selector) : null;
      related?.classList.add('is-highlighted');
      related?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
      if (related) window.setTimeout(() => related.classList.remove('is-highlighted'), 2400);
      return;
    }
    const dismissWarning = event.target.closest('[data-dismiss-warning]');
    if (dismissWarning) {
      if (!await confirmAction('Dismiss this warning? The action will be recorded in the assignment audit log.', 'Dismiss warning')) return;
      try {
        const json = await api('dismiss_warning', {
          warning_key: dismissWarning.dataset.dismissWarning,
          warning_type: dismissWarning.dataset.warningType,
          user_id: dismissWarning.dataset.warningUser,
          affected_date: dismissWarning.dataset.warningDate,
          assignment_id: dismissWarning.dataset.warningAssignment,
          message: dismissWarning.dataset.warningMessage,
        });
        await refresh(json.message || 'Warning dismissed.');
      } catch (error) { notify(error.message, 'error'); }
      return;
    }
    const view = event.target.closest('[data-shift-view]');
    if (view) {
      state.view = view.dataset.shiftView;
      savePreference('view', state.view);
      $$('[data-shift-view]').forEach(button => button.classList.toggle('active', button === view));
      renderTimeline();
      return;
    }
    const workload = event.target.closest('[data-workload-filter]');
    if (workload) {
      state.workloadFilter = workload.dataset.workloadFilter;
      savePreference('workloadFilter', state.workloadFilter);
      $$('[data-workload-filter]').forEach(button => button.classList.toggle('active', button === workload));
      renderRecap();
      return;
    }
    const range = event.target.closest('[data-shift-range]');
    if (range) {
      const amount = Number(range.dataset.shiftRange);
      const days = state.view === 'daily' ? 1 : state.view === 'monthly' ? 28 : 7;
      const start = parseLocal(`${$('#shiftFilterStart').value} 00:00:00`);
      const end = parseLocal(`${$('#shiftFilterEnd').value} 00:00:00`);
      setPickerValue($('#shiftFilterStart'), dateKey(addDays(start, amount * days)));
      setPickerValue($('#shiftFilterEnd'), dateKey(addDays(end, amount * days)));
      refresh();
      return;
    }
    const edit = event.target.closest('[data-edit-assignment], .shift-block');
    if (edit && !event.target.closest('.shift-resize-handle')) {
      if (Date.now() < state.suppressAssignmentClickUntil) return;
      editAssignment(edit.dataset.editAssignment || edit.dataset.assignmentId);
      return;
    }
    const history = event.target.closest('[data-history-assignment]');
    if (history) {
      viewAssignmentHistory(history.dataset.historyAssignment);
      return;
    }
    const confirmAssignment = event.target.closest('[data-confirm-assignment]');
    if (confirmAssignment) {
      if (!await confirmAction('Confirm this assignment and approve it when approval is pending?', 'Confirm assignment')) return;
      try {
        const json = await api('confirm_assignment', { id: confirmAssignment.dataset.confirmAssignment });
        await refresh(json.message);
      } catch (error) { notify(error.message, 'error'); }
      return;
    }
    const tab = event.target.closest('[data-config-tab]');
    if (tab) {
      $$('[data-config-tab]').forEach(button => button.classList.toggle('active', button === tab));
      $$('[data-config-pane]').forEach(pane => pane.classList.toggle('active', pane.dataset.configPane === tab.dataset.configTab));
      return;
    }
    const template = event.target.closest('[data-edit-template]');
    if (template) return editConfig('template', template.dataset.editTemplate);
    const holiday = event.target.closest('[data-edit-holiday]');
    if (holiday) return editConfig('holiday', holiday.dataset.editHoliday);
    const coverage = event.target.closest('[data-edit-coverage]');
    if (coverage) return editConfig('coverage', coverage.dataset.editCoverage);
    const previewMonthly = event.target.closest('[data-preview-monthly]');
    if (previewMonthly) {
      return previewMonthlyTemplate({ id: previewMonthly.dataset.previewMonthly }, previewMonthly.dataset.previewMonthly);
    }
    const applyMonthly = event.target.closest('[data-apply-monthly]');
    if (applyMonthly) {
      return previewMonthlyTemplate({ id: applyMonthly.dataset.applyMonthly }, applyMonthly.dataset.applyMonthly);
    }
    const editMonthly = event.target.closest('[data-edit-monthly]');
    if (editMonthly) return editMonthlyTemplate(editMonthly.dataset.editMonthly);
    const duplicateMonthly = event.target.closest('[data-duplicate-monthly]');
    if (duplicateMonthly) {
      const row = (state.data.monthly_templates || []).find(item => Number(item.id) === Number(duplicateMonthly.dataset.duplicateMonthly));
      if (!row) return;
      openModal('monthlyDuplicate');
      const form = $('#shiftMonthlyDuplicateForm');
      const targetMonth = monthAfter(row.target_month);
      form.elements.id.value = row.id;
      form.elements.name.value = `${row.name} - ${monthLabel(`${targetMonth}-01`)}`;
      form.elements.target_month.value = targetMonth;
      return;
    }
    const archiveMonthly = event.target.closest('[data-archive-monthly]');
    if (archiveMonthly && await confirmAction('Archive this monthly template? Existing generated assignments will remain unchanged.', 'Archive monthly template')) {
      try {
        const json = await api('archive_monthly_template', { id: archiveMonthly.dataset.archiveMonthly });
        await refresh(json.message);
      } catch (error) { notify(error.message, 'error'); }
      return;
    }
    const confirmMonthly = event.target.closest('[data-confirm-monthly]');
    if (confirmMonthly) {
      const button = confirmMonthly;
      const preview = state.monthlyPreview || {};
      const protectedCount = Number(preview.conflict_count || 0);
      const message = protectedCount
        ? `Apply ${preview.assignment_count - protectedCount} non-conflicting assignments for ${monthLabel(preview.target_month)}? ${protectedCount} existing assignment(s) will be skipped and not overwritten.`
        : `Apply ${preview.assignment_count || 0} assignments for ${monthLabel(preview.target_month)}? This creates live assigned shifts.`;
      if (!await confirmAction(message, 'Apply monthly template')) return;
      if (window.setButtonLoading && !window.setButtonLoading(button, 'Applying...')) return;
      try {
        const json = await api('apply_monthly_template', {
          id: confirmMonthly.dataset.confirmMonthly,
          apply_non_conflicting: confirmMonthly.dataset.allowConflicts === '1' ? 1 : 0,
        });
        closeModal(button, { bypassUnsaved: true });
        const target = parseLocal(`${json.data.target_month} 00:00:00`);
        setPickerValue($('#shiftFilterStart'), dateKey(new Date(target.getFullYear(), target.getMonth(), 1)));
        setPickerValue($('#shiftFilterEnd'), dateKey(new Date(target.getFullYear(), target.getMonth() + 1, 0)));
        state.view = 'monthly';
        savePreference('view', state.view);
        activateWorkspace('timeline');
        await refresh(json.message);
      } catch (error) {
        notify(error.message, 'error');
      } finally {
        window.resetButtonLoading?.(button);
      }
      return;
    }
    const deactivate = event.target.closest('[data-deactivate]');
    if (deactivate && await confirmAction('Deactivate this configuration record?', 'Deactivate configuration')) {
      const [kind, id] = deactivate.dataset.deactivate.split(':');
      try {
        const json = await api('deactivate', { kind, id });
        await refresh(json.message);
      } catch (error) { notify(error.message, 'error'); }
    }
  });

  $('#shiftTimeline')?.addEventListener('pointerdown', event => {
    const handle = event.target.closest('.shift-resize-handle');
    if (handle) {
      startTimelineGesture(event, 'resize', handle.dataset.resizeEdge);
      return;
    }
    const block = event.target.closest('.shift-block.is-draggable');
    if (block) startTimelineGesture(event, 'move');
  });

  $('#shiftTimeline')?.addEventListener('keydown', event => {
    if (!['Enter', ' '].includes(event.key)) return;
    const block = event.target.closest('.shift-block');
    if (!block) return;
    event.preventDefault();
    editAssignment(block.dataset.assignmentId);
  });

  $('#shiftApplyFilters')?.addEventListener('click', () => refresh());
  $('#shiftResetFilters')?.addEventListener('click', () => {
    setPickerValue($('#shiftFilterStart'), state.defaultRange.start);
    setPickerValue($('#shiftFilterEnd'), state.defaultRange.end);
    ['shiftFilterAgent', 'shiftFilterDivision', 'shiftFilterType', 'shiftFilterStatus'].forEach(id => {
      const select = $(`#${id}`);
      if (select) select.value = '';
    });
    const search = $('#shiftFilterSearch');
    if (search) search.value = '';
    $$('[data-recap-filter]', root).forEach(input => { input.checked = false; });
    const holidayOnly = $('#shiftHolidayOnly');
    if (holidayOnly) holidayOnly.checked = false;
    state.recapFilters.clear();
    const riskMenu = $('.shift-risk-menu');
    if (riskMenu) riskMenu.open = false;
    window.TRACSDropdowns?.syncAll?.();
    updateRiskCount();
    refresh();
  });
  $('#shiftTodayBtn')?.addEventListener('click', () => {
    const today = new Date();
    if (state.view === 'daily') {
      setPickerValue($('#shiftFilterStart'), dateKey(today));
      setPickerValue($('#shiftFilterEnd'), dateKey(today));
    } else if (state.view === 'monthly') {
      setPickerValue($('#shiftFilterStart'), dateKey(new Date(today.getFullYear(), today.getMonth(), 1)));
      setPickerValue($('#shiftFilterEnd'), dateKey(new Date(today.getFullYear(), today.getMonth() + 1, 0)));
    } else {
      const mondayOffset = (today.getDay() + 6) % 7;
      const monday = addDays(today, -mondayOffset);
      setPickerValue($('#shiftFilterStart'), dateKey(monday));
      setPickerValue($('#shiftFilterEnd'), dateKey(addDays(monday, 6)));
    }
    refresh();
  });

  $$('[data-recap-filter]').forEach(input => input.addEventListener('change', () => {
    if (input.checked) state.recapFilters.add(input.dataset.recapFilter);
    else state.recapFilters.delete(input.dataset.recapFilter);
    updateRiskCount();
    renderRecap();
  }));
  $('#shiftHolidayOnly')?.addEventListener('change', updateRiskCount);
  $('#shiftWarningType')?.addEventListener('change', event => {
    state.warningType = event.target.value;
    renderWarnings();
    icons();
  });
  $('#shiftWarningsUnresolved')?.addEventListener('change', () => {
    renderWarnings();
    icons();
  });
  ['shiftTemplateSearch', 'shiftTemplateStatus', 'shiftMonthlyTemplateSearch', 'shiftMonthlyTemplateStatus'].forEach(id => {
    const input = $(`#${id}`);
    input?.addEventListener(input.matches('input[type="search"]') ? 'input' : 'change', () => {
      renderConfig();
      icons();
    });
  });
  let filterSearchTimer = null;
  $('#shiftFilterSearch')?.addEventListener('input', () => {
    window.clearTimeout(filterSearchTimer);
    filterSearchTimer = window.setTimeout(() => refresh(), 300);
  });
  $('#shiftFilterSearch')?.addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    window.clearTimeout(filterSearchTimer);
    refresh();
  });
  ['shiftFilterStart', 'shiftFilterEnd'].forEach(id => {
    $(`#${id}`)?.addEventListener('change', () => refresh());
  });

  $('#shiftAssignmentForm')?.addEventListener('input', event => {
    if (!['start_time', 'end_time', 'break_minutes'].includes(event.target.name)) return;
    event.currentTarget.elements.is_manual_duration_override.value = '1';
    updateDurationPreview();
  });
  $('#shiftAssignmentForm')?.addEventListener('change', event => {
    if (event.target.name === 'user_id') {
      updateAssignmentDivision();
      return;
    }
    if (event.target.name !== 'shift_template_id') return;
    const row = (state.data.templates || []).find(item => String(item.id) === event.target.value);
    if (!row) return;
    const form = event.currentTarget;
    setPickerValue(form.elements.start_time, String(row.start_time).slice(0, 5));
    setPickerValue(form.elements.end_time, String(row.end_time).slice(0, 5));
    form.elements.break_minutes.value = row.default_break_minutes;
    form.elements.assignment_type.value = row.default_assignment_type;
    form.elements.is_manual_duration_override.value = '0';
    updateDurationPreview();
  });
  $('#shiftMonthlyTemplateForm')?.addEventListener('change', event => {
    if (event.target.name === 'division_id') filterMonthlyAgents();
  });

  $('#shiftAssignmentForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = $('button[type="submit"]', form);
    if (!validateAssignmentForm(form)) return;
    if (window.setButtonLoading && !window.setButtonLoading(button, 'Saving...')) return;
    try {
      const json = await api('save_assignment', formObject(form));
      window.TRACSUnsavedChanges?.markSaved(form);
      notify(json.message || 'Assignment saved.', 'success');
      await new Promise(resolve => setTimeout(resolve, 260));
      closeModal(form, { bypassUnsaved: true });
      await refresh();
    } catch (error) {
      showFormErrors(form, error.errors || {}, error.message || 'Could not save. Try again.');
      notify(error.message, 'error');
    } finally {
      window.resetButtonLoading?.(button);
    }
  });

  $('#shiftMonthlyPreviewDraft')?.addEventListener('click', () => {
    const form = $('#shiftMonthlyTemplateForm');
    if (!validateMonthlyTemplateForm(form)) return;
    previewMonthlyTemplate(monthlyFormPayload(form), Number(form.elements.id.value) || 0);
  });

  $('#shiftMonthlyTemplateForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = $('button[type="submit"]', form);
    if (!validateMonthlyTemplateForm(form)) return;
    if (window.setButtonLoading && !window.setButtonLoading(button, 'Saving...')) return;
    try {
      const json = await api('save_monthly_template', monthlyFormPayload(form));
      window.TRACSUnsavedChanges?.markSaved(form);
      notify(json.message || 'Template saved as draft.', 'success');
      await new Promise(resolve => setTimeout(resolve, 260));
      closeModal(form, { bypassUnsaved: true });
      await refresh();
    } catch (error) {
      showFormErrors(form, error.errors || {}, error.message || 'Could not save. Try again.');
      notify(error.message, 'error');
    } finally {
      window.resetButtonLoading?.(button);
    }
  });

  const formActions = [
    ['shiftTemplateForm', 'save_template'],
    ['shiftHolidayForm', 'save_holiday'],
    ['shiftCoverageForm', 'save_coverage_rule'],
    ['shiftSettingsForm', 'save_settings'],
    ['shiftReplaceForm', 'replace_agent'],
    ['shiftMonthlyDuplicateForm', 'duplicate_monthly_template'],
  ];
  formActions.forEach(([id, action]) => {
    $(`#${id}`)?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const button = $('button[type="submit"]', form);
      if (!form.reportValidity()) return;
      if (window.setButtonLoading && !window.setButtonLoading(button, 'Saving...')) return;
      try {
        const json = await api(action, formObject(form));
        window.TRACSUnsavedChanges?.markSaved(form);
        if (form.closest('.modal-overlay')) closeModal(form, { bypassUnsaved: true });
        syncModalState();
        notify(json.message, 'success');
        await refresh();
      } catch (error) {
        notify(error.message, 'error');
      } finally {
        window.resetButtonLoading?.(button);
      }
    });
  });

  $('#shiftCoverageForm select[name="day_type"]')?.addEventListener('change', toggleCustomDate);
  $('#shiftCopyLastWeek')?.addEventListener('click', async () => {
    if (!await confirmAction('Copy eligible assignments from the previous week into this selected week?', 'Copy last week')) return;
    try {
      const json = await api('copy_last_week', { start: $('#shiftFilterStart').value, division_id: $('#shiftFilterDivision').value });
      await refresh(json.message);
    } catch (error) { notify(error.message, 'error'); }
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !state.activeModal) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    closeModal(state.activeModal);
  }, true);

  const modalObserver = new MutationObserver(syncModalState);
  $$('[data-shift-modal]', root).forEach(overlay => modalObserver.observe(overlay, {
    attributes: true,
    attributeFilter: ['class', 'hidden'],
  }));

  const moreBtn = document.getElementById('shiftMoreBtn');
  const moreDropdown = document.getElementById('shiftMoreDropdown');
  if (moreBtn && moreDropdown) {
    moreBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      moreDropdown.hidden = !moreDropdown.hidden;
    });
    document.addEventListener('click', () => { moreDropdown.hidden = true; });
  }

  renderAll();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(syncRangePickers), { once: true });
  } else {
    requestAnimationFrame(syncRangePickers);
  }
})();
