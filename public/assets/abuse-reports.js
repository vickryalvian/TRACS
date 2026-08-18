(() => {
  'use strict';

  const root = document.getElementById('abuseWorkspace');
  if (!root || root.dataset.abuseInitialized === '1') return;
  root.dataset.abuseInitialized = '1';

  const stages = ['incoming', 'investigating', 'waiting_external', 'action_required', 'resolved'];
  const statuses = ['incoming', 'investigating', 'waiting_external', 'action_taken', 'resolved', 'closed'];
  const stageStatus = {
    incoming: 'incoming',
    investigating: 'investigating',
    waiting_external: 'waiting_external',
    action_required: 'action_taken',
    resolved: 'resolved',
  };
  const statusStage = {
    incoming: 'incoming',
    investigating: 'investigating',
    waiting_external: 'waiting_external',
    action_taken: 'action_required',
    resolved: 'resolved',
    closed: 'resolved',
  };
  const statusLabels = {
    incoming: 'Incoming',
    investigating: 'Investigating',
    waiting_external: 'Waiting External',
    action_required: 'Action Required',
    action_taken: 'Action Required',
    resolved: 'Resolved',
    closed: 'Closed',
  };
  const priorityRank = { critical: 0, high: 1, medium: 2, low: 3 };
  const apiUrls = {
    create: '/api/abuse-report-create.php',
    update: '/api/abuse-report-update.php',
    get: '/api/abuse-report-get.php',
    status: '/api/abuse-report-status.php',
    reorder: '/api/abuse-report-reorder.php',
    note: '/api/abuse-report-note.php',
    evidence: '/api/abuse-report-evidence-upload.php',
  };

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[char]);
  const toId = value => Number.parseInt(value, 10) || 0;
  const parsePayload = () => {
    try {
      const node = $('#abuseDataset');
      return node ? JSON.parse(node.textContent || '[]') : [];
    } catch (_) {
      return [];
    }
  };
  const state = {
    reports: Array.isArray(parsePayload()) ? parsePayload() : [],
    selectedId: toId(root.dataset.selectedId),
    detail: null,
    draggedId: 0,
    canManage: root.dataset.canManage === '1' || window.TRACS_ABUSE_CAPS?.canManage === true,
  };

  const notify = (message, type = 'info') => {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type, { context: 'page' });
    }
  };
  const icons = () => {
    if (typeof window.tracsRefreshIcons === 'function') window.tracsRefreshIcons(root);
    else window.lucide?.createIcons?.();
  };
  const formatBytes = value => {
    const bytes = Number(value) || 0;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  };
  const formatDate = value => {
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString(undefined, {
      day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    });
  };
  const dateKey = value => String(value || '').slice(0, 10);
  const datetimeLocal = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
  const nowLocalInput = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
  };
  const addHoursLocal = (value, hours) => {
    const base = value ? new Date(value) : new Date();
    if (Number.isNaN(base.getTime())) return '';
    base.setHours(base.getHours() + (Number.parseInt(hours, 10) || 24));
    base.setMinutes(base.getMinutes() - base.getTimezoneOffset());
    return base.toISOString().slice(0, 16);
  };
  const eventLabel = value => String(value || 'updated').replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
  const reportTarget = report => [report.affected_domain, report.affected_ip].filter(Boolean).join(' / ') || 'No target set';
  const truthy = value => value === true || value === 1 || value === '1';
  const stageForStatus = status => statusStage[status] || (stages.includes(status) ? status : 'incoming');
  const statusForStage = stage => stageStatus[stage] || (statuses.includes(stage) ? stage : 'incoming');
  const stageAndStatus = value => {
    const raw = String(value || 'incoming');
    const status = statuses.includes(raw) ? raw : statusForStage(raw);
    return { status, stage: stages.includes(raw) ? raw : stageForStatus(status) };
  };
  const normalizeReport = report => {
    if (!report || !report.id) return null;
    report.id = toId(report.id);
    report.status = statuses.includes(report.status) ? report.status : 'incoming';
    report.status_label = report.status_label || statusLabels[report.status] || report.status;
    report.workflow_stage = stages.includes(report.workflow_stage) ? report.workflow_stage : stageForStatus(report.status);
    report.action_required = truthy(report.action_required) || report.workflow_stage === 'action_required';
    if (report.action_required && !['resolved', 'closed'].includes(report.status)) report.workflow_stage = 'action_required';
    report.workflow_label = report.workflow_label || statusLabels[report.workflow_stage] || report.status_label;
    report.priority = ['critical', 'high', 'medium', 'low'].includes(report.priority) ? report.priority : 'medium';
    report.evidence_count = Number(report.evidence_count || 0);
    report.tag_list = Array.isArray(report.tag_list)
      ? report.tag_list
      : String(report.tags || '').split(',').map(tag => tag.trim()).filter(Boolean);
    report.ticket_sent = truthy(report.ticket_sent) || report.ticket_status === 'sent';
    report.nameserver_saved = truthy(report.nameserver_saved) || String(report.nameserver_snapshot || '').trim() !== '';
    report.waiting_label = report.waiting_label || (report.waiting_until ? `Until ${formatDate(report.waiting_until)}` : 'No waiting window');
    return report;
  };
  state.reports = state.reports.map(normalizeReport).filter(Boolean);

  async function jsonPost(url, data) {
    if (typeof window.api === 'function') {
      const payload = await window.api(url, data);
      if (payload?.success === false || payload?.ok === false) throw new Error(payload.message || 'Request failed');
      return payload.data;
    }
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data || {}),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) throw new Error(payload.message || 'Request failed');
    return payload.data;
  }

  async function jsonGet(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) throw new Error(payload.message || 'Request failed');
    return payload.data;
  }

  function updateReportInState(report) {
    const normalized = normalizeReport(report);
    if (!normalized) return null;
    const idx = state.reports.findIndex(item => item.id === normalized.id);
    if (idx >= 0) state.reports[idx] = { ...state.reports[idx], ...normalized };
    else state.reports.unshift(normalized);
    return normalized;
  }

  function currentFilters() {
    return {
      q: ($('#abuseSearchInput')?.value || '').trim().toLowerCase(),
      status: $('#abuseStatusFilter')?.value || '',
      priority: $('#abusePriorityFilter')?.value || '',
      reporter: $('#abuseReporterFilter')?.value || '',
      assigned: $('#abuseAssignedFilter')?.value || '',
      start: $('#abuseDateStart')?.value || '',
      end: $('#abuseDateEnd')?.value || '',
      evidence: $('#abuseHasAttachmentFilter')?.checked || false,
      actionRequired: $('#abuseActionRequiredFilter')?.checked || false,
    };
  }

  function matchesFilters(report, filters) {
    if (filters.status && report.workflow_stage !== filters.status) return false;
    if (filters.priority && report.priority !== filters.priority) return false;
    if (filters.reporter && String(report.reporter || '') !== filters.reporter) return false;
    if (filters.assigned && String(report.assigned_user_id || '') !== filters.assigned) return false;
    if (filters.evidence && Number(report.evidence_count || 0) <= 0) return false;
    if (filters.actionRequired && !report.action_required) return false;
    const created = dateKey(report.created_at);
    if (filters.start && created && created < filters.start) return false;
    if (filters.end && created && created > filters.end) return false;
    if (!filters.q) return true;
    const haystack = [
      report.report_number, report.title, report.affected_domain, report.affected_ip,
      report.reporter, report.reporter_contact, report.customer_name,
      report.customer_reference, report.assigned_staff, report.tags, report.description,
    ].join(' ').toLowerCase();
    return haystack.includes(filters.q);
  }

  function sortReports(a, b) {
    const orderA = Number(a.board_order || 0);
    const orderB = Number(b.board_order || 0);
    if (orderA !== orderB) return orderA - orderB;
    const priority = (priorityRank[a.priority] ?? 9) - (priorityRank[b.priority] ?? 9);
    if (priority !== 0) return priority;
    return String(b.created_at || '').localeCompare(String(a.created_at || ''));
  }

  function renumberStage(stage) {
    let position = 0;
    state.reports.forEach(report => {
      if (report.workflow_stage === stage) {
        report.board_order = position;
        position += 1;
      }
    });
  }

  function renderCard(report) {
    const tags = (report.tag_list || []).slice(0, 3).map(tag => `<span class="abuse-tag">${esc(tag)}</span>`).join('');
    const indicators = [
      report.ticket_sent ? 'Ticket sent' : 'Ticket not sent',
      report.nameserver_saved ? 'NS saved' : 'NS missing',
    ].map(label => `<span class="abuse-indicator">${esc(label)}</span>`).join('');
    const alert = report.action_required ? '<div class="abuse-card-alert">Action required</div>' : '';
    return `
      <article class="abuse-card is-${esc(report.priority)} ${report.action_required ? 'is-action-required' : ''} ${state.selectedId === report.id ? 'is-selected' : ''}" data-abuse-id="${report.id}" draggable="${state.canManage ? 'true' : 'false'}">
        <div class="abuse-card-body">
          <div class="abuse-card-top">
            <span class="abuse-card-number">${esc(report.report_number || `#${report.id}`)}</span>
            <span class="abuse-pill is-${esc(report.priority)}">${esc(report.priority)}</span>
          </div>
          <h3>${esc(report.title || 'Untitled abuse report')}</h3>
          <div class="abuse-card-target">${esc(reportTarget(report))}</div>
          ${alert}
          <div class="abuse-card-meta">
            <span>${esc(report.reporter || 'Unknown reporter')}</span>
            <span>${esc(report.assigned_staff || 'Unassigned')}</span>
          </div>
          <div class="abuse-card-sla">
            <strong class="${report.action_required ? 'is-over' : ''}">${esc(report.waiting_label || 'No waiting window')}</strong>
            <span>${esc(report.open_age || '')}</span>
          </div>
          <div class="abuse-card-indicators">${indicators}</div>
          ${tags ? `<div class="abuse-card-tags">${tags}</div>` : ''}
          <div class="abuse-card-footer">
            <span>${Number(report.evidence_count || 0)} evidence</span>
            <span>${esc(eventLabel(report.last_activity_type || 'received'))}</span>
          </div>
        </div>
      </article>`;
  }

  function renderBoard() {
    const filters = currentFilters();
    const visible = state.reports.filter(report => matchesFilters(report, filters));
    const open = visible.filter(report => !['resolved', 'closed'].includes(report.status)).length;
    const critical = visible.filter(report => report.priority === 'critical' && !['resolved', 'closed'].includes(report.status)).length;
    const actionRequired = visible.filter(report => report.action_required).length;

    $('#abuseOpenStat') && ($('#abuseOpenStat').textContent = open);
    $('#abuseCriticalStat') && ($('#abuseCriticalStat').textContent = critical);
    $('#abuseActionStat') && ($('#abuseActionStat').textContent = actionRequired);
    $('#abusePageSummary') && ($('#abusePageSummary').textContent = `${visible.length} shown · ${open} open · ${critical} critical · ${actionRequired} action required`);

    stages.forEach(stage => {
      const column = $(`[data-abuse-column="${stage}"]`, root);
      const list = $(`[data-abuse-dropzone="${stage}"]`, root);
      if (!column || !list) return;
      const reports = visible.filter(report => report.workflow_stage === stage).sort(sortReports);
      $('[data-column-count]', column).textContent = reports.length;
      $('[data-column-summary]', column).textContent = reports.length === 1 ? '1 report' : `${reports.length} reports`;
      list.innerHTML = reports.length
        ? reports.map(renderCard).join('')
        : '<div class="abuse-empty-column">No reports</div>';
    });
    icons();
  }

  function setDetailVisible(visible) {
    const modal = $('#abuseDetailModal');
    if (!modal) return;
    if (visible) {
      if (typeof window.tracsOpenModalElement === 'function') window.tracsOpenModalElement(modal);
      else modal.classList.remove('hidden');
    } else if (typeof window.tracsCloseModalElement === 'function') {
      window.tracsCloseModalElement(modal, { bypassUnsaved: true });
    } else {
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  function setValue(selector, value) {
    const node = $(selector);
    if (node) node.value = value ?? '';
  }

  function fillDetail(report) {
    state.detail = normalizeReport(report) || report;
    state.selectedId = toId(report?.id);
    setDetailVisible(true);
    $('#abuseDetailRef') && ($('#abuseDetailRef').textContent = report.report_number || (report.id ? `#${report.id}` : 'New'));
    $('#abuseDetailTitle') && ($('#abuseDetailTitle').textContent = report.title || 'New abuse report');
    $('#abuseDetailSub') && ($('#abuseDetailSub').textContent = `${report.workflow_label || statusLabels[report.status] || 'Incoming'} · ${reportTarget(report)}`);
    setValue('#abuseReportId', report.id || '');
    setValue('#abuseTitle', report.title || '');
    setValue('#abuseType', report.report_type || 'phishing');
    setValue('#abuseStatus', report.status || 'incoming');
    setValue('#abusePriority', report.priority || 'medium');
    setValue('#abuseDomain', report.affected_domain || '');
    setValue('#abuseIp', report.affected_ip || '');
    setValue('#abuseReporter', report.reporter || '');
    setValue('#abuseReporterContact', report.reporter_contact || '');
    setValue('#abuseAssignedUser', report.assigned_user_id || '');
    setValue('#abuseSlaDue', datetimeLocal(report.sla_due_at));
    setValue('#abuseCustomer', report.customer_name || '');
    setValue('#abuseCustomerRef', report.customer_reference || '');
    setValue('#abuseTicketStatus', report.ticket_status || 'not_sent');
    setValue('#abuseTicketRef', report.ticket_reference || '');
    setValue('#abuseTicketUrl', report.ticket_url || '');
    setValue('#abuseTicketSentAt', datetimeLocal(report.ticket_sent_at));
    setValue('#abuseWaitingHours', report.waiting_hours || '24');
    setValue('#abuseWaitingStartedAt', datetimeLocal(report.waiting_started_at));
    setValue('#abuseWaitingUntil', datetimeLocal(report.waiting_until));
    $('#abuseWaitingStatus') && ($('#abuseWaitingStatus').textContent = report.waiting_label || 'No waiting window');
    setValue('#abuseTags', report.tags || (report.tag_list || []).join(', '));
    setValue('#abuseNameservers', report.nameserver_snapshot || '');
    setValue('#abuseNameserverAt', datetimeLocal(report.nameserver_snapshot_at));
    setValue('#abuseDescription', report.description || '');
    setValue('#abuseRelatedDomain', report.related_domain_id || '');
    setValue('#abuseRelatedServer', report.related_server_id || '');
    setValue('#abuseRelatedCase', report.related_case_id || '');
    setValue('#abuseRelatedShift', report.related_shift_report_id || '');
    renderTimeline(report.timeline || []);
    renderNotes(report.notes || []);
    renderEvidence(report.evidence || []);
    renderActivity(report.timeline || []);
    renderBoard();
  }

  function blankReport() {
    return {
      id: 0,
      report_number: 'New',
      title: '',
      report_type: 'phishing',
      status: 'incoming',
      workflow_stage: 'incoming',
      workflow_label: 'Incoming',
      priority: 'medium',
      ticket_status: 'not_sent',
      waiting_hours: 24,
      waiting_started_at: nowLocalInput(),
      waiting_until: addHoursLocal('', 24),
      tag_list: [],
      timeline: [],
      notes: [],
      evidence: [],
    };
  }

  async function openReport(id) {
    if (!id) return;
    state.selectedId = id;
    renderBoard();
    try {
      const report = await jsonGet(`${apiUrls.get}?id=${encodeURIComponent(id)}`);
      updateReportInState(report);
      fillDetail(report);
    } catch (error) {
      notify(error.message || 'Abuse report could not be loaded.', 'error');
    }
  }

  function renderTimeline(events) {
    const host = $('#abuseTimeline');
    if (!host) return;
    host.innerHTML = events.length ? events.map(event => `
      <article class="abuse-event">
        <div class="abuse-event-head">
          <strong>${esc(eventLabel(event.event_type))}</strong>
          <span>${esc(formatDate(event.created_at))}</span>
        </div>
        <p>${esc([event.actor_name, event.field_name, event.note || event.new_value].filter(Boolean).join(' · '))}</p>
      </article>
    `).join('') : '<div class="abuse-empty-column">No timeline entries</div>';
  }

  function renderNotes(notes) {
    const host = $('#abuseNotes');
    if (!host) return;
    host.innerHTML = notes.length ? notes.map(note => `
      <article class="abuse-note">
        <div class="abuse-note-head">
          <strong>${esc(note.author_name || 'Internal note')}</strong>
          <span>${esc(formatDate(note.created_at))}</span>
        </div>
        <p>${esc(note.body || '')}</p>
      </article>
    `).join('') : '<div class="abuse-empty-column">No notes</div>';
  }

  function renderEvidence(items) {
    const host = $('#abuseEvidenceGrid');
    if (!host) return;
    host.innerHTML = items.length ? items.map(item => `
      <article class="abuse-evidence-item">
        <a class="abuse-evidence-preview" href="${esc(item.download_url || '#')}" target="_blank" rel="noopener">
          ${item.preview_url ? `<img src="${esc(item.preview_url)}" alt="">` : `<i data-lucide="file-text"></i>`}
        </a>
        <div class="abuse-evidence-meta">
          <strong>${esc(item.original_filename || 'Evidence')}</strong>
          <span>${esc(item.evidence_type || 'other')} · ${esc(formatBytes(item.file_size))}</span>
          <span>${esc(formatDate(item.created_at))}</span>
        </div>
      </article>
    `).join('') : '<div class="abuse-empty-column">No evidence uploaded</div>';
    icons();
  }

  function renderActivity(events) {
    const host = $('#abuseActivityLog');
    if (!host) return;
    host.innerHTML = events.length ? events.slice(0, 12).map(event => `
      <article class="abuse-event">
        <div class="abuse-event-head">
          <strong>${esc(eventLabel(event.event_type))}</strong>
          <span>${esc(event.actor_name || 'System')}</span>
        </div>
        <p>${esc(formatDate(event.created_at))}</p>
      </article>
    `).join('') : '<div class="abuse-empty-column">No activity yet</div>';
  }

  function collectReport() {
    return {
      id: toId($('#abuseReportId')?.value),
      title: $('#abuseTitle')?.value || '',
      report_type: $('#abuseType')?.value || 'phishing',
      status: $('#abuseStatus')?.value || 'incoming',
      priority: $('#abusePriority')?.value || 'medium',
      affected_domain: $('#abuseDomain')?.value || '',
      affected_ip: $('#abuseIp')?.value || '',
      reporter: $('#abuseReporter')?.value || '',
      reporter_contact: $('#abuseReporterContact')?.value || '',
      assigned_user_id: $('#abuseAssignedUser')?.value || '',
      sla_due_at: $('#abuseSlaDue')?.value || '',
      customer_name: $('#abuseCustomer')?.value || '',
      customer_reference: $('#abuseCustomerRef')?.value || '',
      ticket_status: $('#abuseTicketStatus')?.value || 'not_sent',
      ticket_reference: $('#abuseTicketRef')?.value || '',
      ticket_url: $('#abuseTicketUrl')?.value || '',
      ticket_sent_at: $('#abuseTicketSentAt')?.value || '',
      waiting_hours: $('#abuseWaitingHours')?.value || '24',
      waiting_started_at: $('#abuseWaitingStartedAt')?.value || '',
      waiting_until: $('#abuseWaitingUntil')?.value || '',
      tags: $('#abuseTags')?.value || '',
      nameserver_snapshot: $('#abuseNameservers')?.value || '',
      nameserver_snapshot_at: $('#abuseNameserverAt')?.value || '',
      description: $('#abuseDescription')?.value || '',
      related_domain_id: $('#abuseRelatedDomain')?.value || '',
      related_server_id: $('#abuseRelatedServer')?.value || '',
      related_case_id: $('#abuseRelatedCase')?.value || '',
      related_shift_report_id: $('#abuseRelatedShift')?.value || '',
    };
  }

  async function saveReport(successMessage = '') {
    const payload = collectReport();
    if (!payload.title.trim()) {
      notify('Title is required.', 'warning');
      $('#abuseTitle')?.focus();
      return;
    }
    try {
      const data = payload.id
        ? await jsonPost(apiUrls.update, payload)
        : await jsonPost(apiUrls.create, payload);
      const report = data?.report || data;
      updateReportInState(report);
      fillDetail(report);
      notify(successMessage || (payload.id ? 'Abuse report updated.' : 'Abuse report created.'), 'success');
    } catch (error) {
      notify(error.message || 'Abuse report could not be saved.', 'error');
    }
  }

  function orderedIdsFor(stage, status) {
    return state.reports
      .filter(report => report.workflow_stage === stage && (!status || report.status === status))
      .sort(sortReports)
      .map(report => report.id);
  }

  function moveInState(id, target, beforeId = 0) {
    const { stage, status } = stageAndStatus(target);
    const index = state.reports.findIndex(report => report.id === id);
    if (index < 0) return null;
    const [report] = state.reports.splice(index, 1);
    const oldStage = report.workflow_stage;
    report.status = status;
    report.workflow_stage = stage;
    report.status_label = statusLabels[status] || status;
    report.workflow_label = statusLabels[stage] || report.status_label;
    report.action_required = stage === 'action_required';
    if (beforeId === id) beforeId = 0;
    let insertAt = beforeId ? state.reports.findIndex(item => item.id === beforeId) : -1;
    if (insertAt < 0) {
      insertAt = state.reports.reduce((last, item, idx) => item.workflow_stage === stage ? idx + 1 : last, state.reports.length);
    }
    state.reports.splice(insertAt, 0, report);
    renumberStage(oldStage);
    renumberStage(stage);
    return report;
  }

  async function moveReport(id, target, beforeId = 0, source = 'drag_drop') {
    const { stage, status } = stageAndStatus(target);
    if (!state.canManage || !stages.includes(stage)) return;
    const previous = state.reports.find(report => report.id === id);
    if (!previous) return;
    const oldStatus = previous.status;
    const oldReports = state.reports.slice();
    moveInState(id, status, beforeId);
    renderBoard();
    try {
      if (oldStatus !== status) {
        const report = await jsonPost(apiUrls.status, { id, status, source });
        updateReportInState(report);
      }
      await jsonPost(apiUrls.reorder, { status, ordered_ids: orderedIdsFor(stage, status) });
      if (state.selectedId === id) openReport(id);
    } catch (error) {
      state.reports = oldReports;
      renderBoard();
      notify(error.message || 'Abuse report could not be moved.', 'error');
    }
  }

  async function addNote(event) {
    event.preventDefault();
    const id = toId($('#abuseReportId')?.value);
    const body = ($('#abuseNoteBody')?.value || '').trim();
    if (!id) return notify('Save the report before adding notes.', 'warning');
    if (!body) return notify('Note is required.', 'warning');
    try {
      const data = await jsonPost(apiUrls.note, { id, note: body });
      $('#abuseNoteBody').value = '';
      updateReportInState(data?.report);
      fillDetail(data?.report);
      notify('Note added.', 'success');
    } catch (error) {
      notify(error.message || 'Note could not be saved.', 'error');
    }
  }

  async function uploadEvidence(event) {
    event.preventDefault();
    const id = toId($('#abuseReportId')?.value);
    const input = $('#abuseEvidenceInput');
    if (!id) return notify('Save the report before uploading evidence.', 'warning');
    if (!input?.files?.length) return notify('Choose evidence before uploading.', 'warning');
    const body = new FormData();
    body.append('id', String(id));
    body.append('evidence_type', $('#abuseEvidenceType')?.value || '');
    Array.from(input.files).forEach(file => body.append('evidence[]', file));
    try {
      const response = await fetch(apiUrls.evidence, { method: 'POST', body });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload.success === false) throw new Error(payload.message || 'Evidence could not be uploaded.');
      input.value = '';
      const report = payload.data?.report;
      updateReportInState(report);
      fillDetail(report);
      notify('Evidence uploaded.', 'success');
    } catch (error) {
      notify(error.message || 'Evidence could not be uploaded.', 'error');
    }
  }

  function setWaitingDefaults() {
    const started = $('#abuseWaitingStartedAt')?.value || nowLocalInput();
    const hours = $('#abuseWaitingHours')?.value || '24';
    if (!$('#abuseWaitingStartedAt')?.value) setValue('#abuseWaitingStartedAt', started);
    setValue('#abuseWaitingUntil', addHoursLocal(started, hours));
    $('#abuseWaitingStatus') && ($('#abuseWaitingStatus').textContent = `Until ${formatDate($('#abuseWaitingUntil')?.value)}`);
  }

  root.addEventListener('click', event => {
    const card = event.target.closest('.abuse-card');
    if (card) {
      openReport(toId(card.dataset.abuseId));
      return;
    }
    const tab = event.target.closest('[data-abuse-tab]');
    if (tab) {
      const name = tab.dataset.abuseTab;
      $$('[data-abuse-tab]', root).forEach(node => node.classList.toggle('is-active', node === tab));
      $$('[data-abuse-pane]', root).forEach(pane => {
        const active = pane.dataset.abusePane === name;
        pane.classList.toggle('is-active', active);
        pane.hidden = !active;
      });
      return;
    }
    const move = event.target.closest('[data-abuse-move]');
    if (move) {
      moveReport(toId($('#abuseReportId')?.value), move.dataset.abuseMove, 0, 'quick_action');
      return;
    }
    if (event.target.closest('[data-abuse-ticket-sent]')) {
      if (!toId($('#abuseReportId')?.value)) return notify('Save the report before marking a ticket sent.', 'warning');
      setValue('#abuseTicketStatus', 'sent');
      if (!$('#abuseTicketSentAt')?.value) setValue('#abuseTicketSentAt', nowLocalInput());
      saveReport('Ticket marked sent.');
      return;
    }
    if (event.target.closest('[data-abuse-snapshot-now]')) {
      if (!toId($('#abuseReportId')?.value)) return notify('Save the report before stamping a snapshot.', 'warning');
      if (!($('#abuseNameservers')?.value || '').trim()) return notify('Enter nameserver snapshot before stamping.', 'warning');
      setValue('#abuseNameserverAt', nowLocalInput());
      saveReport('Nameserver snapshot stamped.');
      return;
    }
    if (event.target.closest('[data-abuse-placeholder]')) {
      notify('Action captured for future automation integration.', 'info');
      return;
    }
    if (event.target.closest('#abuseNewBtn')) {
      fillDetail(blankReport());
      $('#abuseTitle')?.focus();
      return;
    }
    if (event.target.closest('#abuseResetBtn')) {
      const id = toId($('#abuseReportId')?.value);
      id ? openReport(id) : fillDetail(blankReport());
      return;
    }
    if (event.target.closest('#abuseClosePanel')) {
      state.selectedId = 0;
      setDetailVisible(false);
      renderBoard();
    }
  });

  root.addEventListener('dragstart', event => {
    const card = event.target.closest('.abuse-card');
    if (!card || !state.canManage) return;
    state.draggedId = toId(card.dataset.abuseId);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(state.draggedId));
    card.classList.add('is-dragging');
  });
  root.addEventListener('dragend', event => {
    event.target.closest('.abuse-card')?.classList.remove('is-dragging');
    state.draggedId = 0;
    $$('.abuse-column', root).forEach(column => column.classList.remove('is-drag-over'));
  });
  root.addEventListener('dragover', event => {
    if (!state.draggedId || !state.canManage) return;
    const column = event.target.closest('[data-abuse-column]');
    if (!column) return;
    event.preventDefault();
    column.classList.add('is-drag-over');
  });
  root.addEventListener('dragleave', event => {
    const column = event.target.closest('[data-abuse-column]');
    if (column && !column.contains(event.relatedTarget)) column.classList.remove('is-drag-over');
  });
  root.addEventListener('drop', event => {
    if (!state.draggedId || !state.canManage) return;
    const column = event.target.closest('[data-abuse-column]');
    if (!column) return;
    event.preventDefault();
    const status = column.dataset.abuseColumn;
    const target = event.target.closest('.abuse-card');
    const beforeId = target ? toId(target.dataset.abuseId) : 0;
    column.classList.remove('is-drag-over');
    moveReport(state.draggedId, status, beforeId);
  });

  $('#abuseOverviewPane')?.addEventListener('submit', event => {
    event.preventDefault();
    saveReport();
  });
  $('#abuseSaveRelationships')?.addEventListener('click', saveReport);
  $('#abuseNoteForm')?.addEventListener('submit', addNote);
  $('#abuseEvidenceForm')?.addEventListener('submit', uploadEvidence);
  $('#abuseSearchForm')?.addEventListener('submit', event => event.preventDefault());
  $('#abuseWaitingHours')?.addEventListener('change', setWaitingDefaults);
  $('#abuseWaitingStartedAt')?.addEventListener('input', setWaitingDefaults);
  [
    '#abuseSearchInput', '#abuseStatusFilter', '#abusePriorityFilter', '#abuseReporterFilter',
    '#abuseAssignedFilter', '#abuseDateStart', '#abuseDateEnd', '#abuseHasAttachmentFilter',
    '#abuseActionRequiredFilter',
  ].forEach(selector => $(selector)?.addEventListener('input', renderBoard));
  ['#abuseStatusFilter', '#abusePriorityFilter', '#abuseReporterFilter', '#abuseAssignedFilter']
    .forEach(selector => $(selector)?.addEventListener('change', renderBoard));

  renderBoard();
  if (state.selectedId) openReport(state.selectedId);
  window.openAbuseReport = openReport;
})();
