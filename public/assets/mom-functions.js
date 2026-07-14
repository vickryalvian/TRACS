/* ═════════════════════════════════════════════════════════════════
   TRACS — MOM (Minutes of Meeting) JavaScript
   Client-side functionality for meeting workspace operations
═════════════════════════════════════════════════════════════════ */

// ═══════════════════════════════════════════════════════════════
// MODAL MANAGEMENT
// ═══════════════════════════════════════════════════════════════

function momNavigateAfterToast(url,delay=420){
  if(window.navigateAfterToast)window.navigateAfterToast(url,delay);
  else setTimeout(()=>{location.href=url;},delay);
}

function momMarkSaved(root){
  window.TRACSUnsavedChanges?.markSaved(root || null);
  if(root instanceof Element){
    root.dispatchEvent(new CustomEvent('tracs:save-success',{bubbles:true,detail:{root}}));
  }
}

function momEscape(value=''){
  return typeof escHtml === 'function'
    ? escHtml(value)
    : String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

function momFlash(node){
  if(!node)return;
  node.classList.remove('tracs-row-live-updated');
  void node.offsetWidth;
  node.classList.add('tracs-row-live-updated');
  setTimeout(()=>node.classList.remove('tracs-row-live-updated'),1400);
  if(window.lucide) lucide.createIcons({nodes:node.querySelectorAll('[data-lucide]')});
}

function momRemoveNode(node){
  if(!node)return;
  node.classList.add('tracs-row-removing');
  setTimeout(()=>node.remove(),190);
}

function momTimeNow(){
  return new Date().toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit',hour12:false});
}

function momAppendAgendaItem(id, topic){
  const host=document.getElementById('momAgendaTopic')?.closest('.section-body');
  if(!host)return;
  const div=document.createElement('div');
  div.className='agenda-item agenda-item-pending';
  div.dataset.agendaId=String(id);
  div.innerHTML=`<input type="checkbox" class="agenda-check" data-unsaved-ignore onchange="toggleAgendaItem(${id},this.checked)"><div class="agenda-content"><div class="agenda-topic">${momEscape(topic)}</div></div><button class="btn btn-ghost btn-icon btn-sm mom-item-delete" onclick="deleteAgendaItem(${id})" title="Delete" aria-label="Delete agenda item"><i data-lucide="x" class="icon-xs"></i></button>`;
  host.appendChild(div);
  momFlash(div);
}

function momAppendNote(id, content, type='discussion'){
  const host=document.getElementById('momNotesArea');
  if(!host)return;
  const div=document.createElement('div');
  div.className=`discussion-note discussion-note-${type}`;
  div.dataset.noteId=String(id);
  div.innerHTML=`<div class="note-header"><span class="note-type">${momEscape(type.charAt(0).toUpperCase()+type.slice(1))}</span><span class="note-time">${momEscape(momTimeNow())}</span><button class="btn btn-ghost btn-icon btn-xs mom-item-delete" onclick="deleteNote(${id})" title="Delete" aria-label="Delete discussion note"><i data-lucide="x" class="icon-xs"></i></button></div><div class="note-text" onmouseup="handleTextSelection(this.parentElement.parentElement)">${momEscape(content).replace(/\n/g,'<br>')}</div>`;
  host.appendChild(div);
  momFlash(div);
}

function momAppendDecision(id, decision, rationale='', owner=''){
  const host=document.getElementById('momInlineDecisionText')?.closest('.section-body');
  if(!host)return;
  const div=document.createElement('div');
  div.className='decision-card';
  div.innerHTML=`<div class="decision-head"><strong>${momEscape(decision)}</strong><button class="btn btn-ghost btn-icon btn-xs mom-item-delete" onclick="deleteDecision(${id})" title="Delete" aria-label="Delete decision"><i data-lucide="x" class="icon-xs"></i></button></div>${rationale ? `<div class="decision-rationale"><span class="label">Rationale:</span> ${momEscape(rationale)}</div>` : ''}${owner ? `<div class="decision-owner"><span class="label">Owner:</span> ${momEscape(owner)}</div>` : ''}`;
  host.appendChild(div);
  momFlash(div);
}

function momAppendAction(id, title, description='', assignee='', priority='medium', dueDate=''){
  const host=document.getElementById('momInlineActionTitle')?.closest('.section-body');
  if(!host)return;
  const div=document.createElement('div');
  div.className=`action-item action-item-${priority} action-item-pending`;
  div.dataset.aid=String(id);
  div.innerHTML=`<input type="checkbox" class="action-check" data-unsaved-ignore onchange="completeAction(${id},this.checked)"><div class="action-content"><div class="action-title">${momEscape(title)}</div>${description ? `<div class="action-desc">${momEscape(description)}</div>` : ''}<div class="action-meta"><span class="action-owner">${momEscape(assignee || '—')}</span>${dueDate ? `<span class="action-due">${momEscape(dueDate)}</span>` : ''}</div></div><div class="action-btns"><button class="btn btn-ghost btn-icon btn-sm" onclick="createReminderFromAction(${id})" title="Create Reminder" aria-label="Create reminder from action"><i data-lucide="bell" class="icon-sm"></i></button><button class="btn btn-ghost btn-icon btn-sm" onclick="createCaseFromAction(${id})" title="Create Case" aria-label="Create case from action"><i data-lucide="briefcase" class="icon-sm"></i></button><button class="btn btn-ghost btn-icon btn-sm" onclick="editActionItem(${id})" title="Edit" aria-label="Edit action item"><i data-lucide="edit-2" class="icon-sm"></i></button><button class="btn btn-ghost btn-icon btn-sm mom-item-delete" onclick="deleteActionItem(${id})" title="Delete" aria-label="Delete action item"><i data-lucide="trash-2" class="icon-sm"></i></button></div>`;
  host.appendChild(div);
  momFlash(div);
  momUpdateActionProgress();
}

function momUpdateActionProgress(){
  const total=document.querySelectorAll('[data-aid]').length;
  const done=document.querySelectorAll('.action-item-completed,[data-aid].action-item-completed').length;
  const fill=document.querySelector('.progress-fill');
  const text=document.querySelector('.progress-text');
  if(fill)fill.style.width=`${total ? Math.round(done/total*100) : 0}%`;
  if(text)text.textContent=`${done}/${total} completed`;
}

function momDateLabel(value){
  if(!value)return '';
  const date=new Date(String(value).replace(' ','T'));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString(undefined,{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
}

function momEmpty(text){
  return `<p class="empty-text">${momEscape(text)}</p>`;
}

function momRemoveEmpty(host){
  host?.querySelectorAll('.empty-text').forEach(el=>el.remove());
}

function momReminderClass(status=''){
  const s=String(status).toLowerCase();
  if(s.includes('overdue'))return 'text-red-500';
  if(s.includes('today'))return 'text-orange-500';
  return 'text-gray-400';
}

function momReminderHtml(reminder){
  const id=Number(reminder?.id || reminder?.reminder_id || 0);
  const priority=String(reminder?.priority || 'medium');
  const status=reminder?.status || 'Upcoming';
  const statusClass=reminder?.status_class || momReminderClass(status);
  return `<div class="reminder-item reminder-item-${momEscape(priority)}" data-rid="${id}">
    <div class="reminder-stat ${momEscape(statusClass)}">${momEscape(status)}</div>
    <div class="reminder-info">
      <div class="reminder-title">${momEscape(reminder?.title || 'Reminder')}</div>
      <div class="reminder-due">${momEscape(momDateLabel(reminder?.due_date))}</div>
    </div>
    <button class="btn btn-ghost btn-icon btn-xs" onclick="openEditReminder(${id})" title="Edit" aria-label="Edit reminder"><i data-lucide="edit-2" class="icon-xs"></i></button>
  </div>`;
}

function momApplyReminder(reminder){
  const id=Number(reminder?.id || reminder?.reminder_id || 0);
  const host=document.querySelector('.mom-reminders-list');
  if(!host || !id)return;
  momRemoveEmpty(host);
  const existing=host.querySelector(`[data-rid="${id}"]`);
  const wrap=document.createElement('div');
  wrap.innerHTML=momReminderHtml(reminder).trim();
  const node=wrap.firstElementChild;
  if(existing)existing.replaceWith(node);
  else host.prepend(node);
  momFlash(node);
}

function momCaseStatusOptions(status='active'){
  const options=[['completed','Solved'],['active','Active'],['in_progress','In Progress'],['pending','Pending'],['stuck','Stuck'],['on_hold','On Hold']];
  return options.map(([value,label])=>`<option value="${value}"${value===status?' selected':''}>${label}</option>`).join('');
}

function momCaseHtml(record){
  const id=Number(record?.id || record?.case_id || 0);
  const priority=String(record?.priority || 'low');
  const status=String(record?.status || 'active');
  const completed=!!document.querySelector('[data-sidebar-edit="screenshots"], .mom-case-resolution');
  const momId=Number(document.querySelector('[data-sidebar-edit="cases"]')?.dataset?.momId || 0);
  return `<div class="case-item case-item-${momEscape(priority)} case-item-${momEscape(status)}" data-case-id="${id}">
    <div class="case-badge case-badge-${momEscape(priority)}">${momEscape(priority.charAt(0).toUpperCase())}</div>
    <div class="case-info">
      <div class="case-id">#${id}</div>
      <div class="case-title">${momEscape(record?.title || 'Case')}</div>
    </div>
    ${completed ? `<div class="mom-case-resolution mom-sidebar-edit">
      <select class="form-select" id="momCaseStatus${id}">${momCaseStatusOptions(status)}</select>
      <input class="form-input" id="momCaseNote${id}" placeholder="Add resolution note or follow-up detail">
      <button class="btn btn-primary btn-sm" onclick="resolveLinkedCaseFromMOM(${momId}, ${id})">Update</button>
    </div>` : ''}
    <div class="case-actions">
      <button class="btn btn-danger btn-icon btn-xs mom-sidebar-edit" onclick="markMOMCaseForRemoval(this)" title="Remove Link" aria-label="Remove linked case"><i data-lucide="x" class="icon-xs"></i></button>
      <a href="cases.php?action=edit&id=${id}" class="btn btn-ghost btn-icon btn-xs" title="View" aria-label="View case"><i data-lucide="external-link" class="icon-xs"></i></a>
    </div>
  </div>`;
}

function momApplyCase(record){
  const id=Number(record?.id || record?.case_id || 0);
  const host=document.querySelector('.mom-cases-list');
  if(!host || !id)return;
  momRemoveEmpty(host);
  const existing=host.querySelector(`[data-case-id="${id}"]`);
  const wrap=document.createElement('div');
  wrap.innerHTML=momCaseHtml(record).trim();
  const node=wrap.firstElementChild;
  if(existing)existing.replaceWith(node);
  else host.appendChild(node);
  momFlash(node);
}

function momRemoveCase(id){
  momRemoveNode(document.querySelector(`[data-case-id="${Number(id)}"]`));
  const host=document.querySelector('.mom-cases-list');
  setTimeout(()=>{
    if(host && !host.querySelector('.case-item'))host.insertAdjacentHTML('beforeend',momEmpty('No cases linked'));
  },220);
}

function momScreenshotHtml(shot){
  const id=Number(shot?.id || shot?.screenshot_id || 0);
  const src=shot?.src || `/api/mom-screenshot.php?id=${id}`;
  return `<div class="mom-shot-item" data-shot-id="${id}">
    <button class="mom-shot-link" type="button" data-mom-screenshot-src="${momEscape(src)}" onclick="openMOMScreenshotLightbox(this)" title="Open screenshot">
      <img class="mom-shot-thumb" src="${momEscape(src)}" alt="MOM screenshot">
    </button>
    <button class="btn btn-danger btn-icon btn-xs mom-shot-remove mom-sidebar-edit" onclick="markMOMScreenshotForRemoval(this)" title="Delete Screenshot" aria-label="Delete screenshot"><i data-lucide="trash-2" class="icon-xs"></i></button>
  </div>`;
}

function momApplyScreenshot(shot){
  const id=Number(shot?.id || shot?.screenshot_id || 0);
  const host=document.querySelector('.mom-shot-list');
  if(!host || !id)return;
  momRemoveEmpty(host);
  const wrap=document.createElement('div');
  wrap.innerHTML=momScreenshotHtml(shot).trim();
  const node=wrap.firstElementChild;
  host.prepend(node);
  momFlash(node);
}

function momModalIsOpen(id) {
  const modal = document.getElementById(`${id}Modal`);
  return !!modal && !modal.classList.contains('hidden') && getComputedStyle(modal).display !== 'none';
}

function formatLocalDateTime(date) {
  const p = v => String(v).padStart(2, '0');
  return {
    date: `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`,
    time: `${p(date.getHours())}:${p(date.getMinutes())}`
  };
}

function setMOMDateLikeInput(el, value, displayValue = value) {
  if(!el) return;

  if(typeof window.setDateLikeInput === 'function') {
    window.setDateLikeInput(el, value, displayValue);
    return;
  }

  if(el._flatpickr) {
    el._flatpickr.setDate(value, true);
  }
  el.value = value;
  if(el._flatpickr?.altInput) {
    el._flatpickr.altInput.value = displayValue;
    el._flatpickr.altInput.dispatchEvent(new Event('input', { bubbles: true }));
    el._flatpickr.altInput.dispatchEvent(new Event('change', { bubbles: true }));
  }
  el.dispatchEvent(new Event('input', { bubbles: true }));
  el.dispatchEvent(new Event('change', { bubbles: true }));
}

function setMOMDateTime(value) {
  const dateEl = document.getElementById('momFormDate');
  const timeEl = document.getElementById('momFormTime');
  if(!dateEl || !timeEl) return;

  if(!value) {
    setMOMDateLikeInput(dateEl, '', '');
    setMOMDateLikeInput(timeEl, '', '');
    return;
  }

  const normalized = String(value).trim().replace(' ', 'T');
  const parts = normalized.split('T');
  const dateValue = parts[0] || '';
  const timeValue = (parts[1] || '').slice(0, 5);
  setMOMDateLikeInput(dateEl, dateValue, typeof formatDateDisplay === 'function' ? formatDateDisplay(dateValue) : dateValue);
  setMOMDateLikeInput(timeEl, timeValue, timeValue);
}

function setMOMQuickTime(hours) {
  const dateEl = document.getElementById('momFormDate');
  const timeEl = document.getElementById('momFormTime');
  if(!dateEl || !timeEl) {
    toast('Meeting date/time fields are not ready', 'warning');
    return;
  }

  const date = new Date();
  date.setHours(date.getHours() + Number(hours || 0));
  const formatted = formatLocalDateTime(date);
  setMOMDateLikeInput(dateEl, formatted.date, typeof formatDateDisplay === 'function' ? formatDateDisplay(formatted.date) : formatted.date);
  setMOMDateLikeInput(timeEl, formatted.time, formatted.time);
}

window.setMOMQuickTime = setMOMQuickTime;

function getMOMMeetingAt() {
  const date = document.getElementById('momFormDate')?.value || '';
  const time = document.getElementById('momFormTime')?.value || '';
  if(!date) return '';
  return `${date} ${time || '00:00'}:00`;
}

function clearMOMSuggestedCases() {
  document.querySelectorAll('.mom-suggestion-item.is-selected').forEach(item => toggleMOMSuggestedCase(item, false));
}

function toggleMOMSuggestedCase(item, force) {
  const selected = typeof force === 'boolean' ? force : !item.classList.contains('is-selected');
  item.classList.toggle('is-selected', selected);
  const icon = item.querySelector('.mom-suggestion-check');
  if(icon) icon.innerHTML = selected ? '<i data-lucide="check" class="icon-sm"></i>' : '<i data-lucide="plus" class="icon-sm"></i>';
  if(window.lucide) lucide.createIcons();
}

function getSelectedMOMCases() {
  return [...document.querySelectorAll('.mom-suggestion-item.is-selected')]
    .map(item => Number(item.dataset.caseId || 0))
    .filter(Boolean);
}

function linkSelectedCasesToMOM(mom_id) {
  const caseIds = getSelectedMOMCases();
  if(!mom_id || !caseIds.length) return Promise.resolve();
  return Promise.all(caseIds.map(case_id => api('api/api_mom.php', {
    action: 'link_case',
    mom_id,
    case_id
  })));
}

function openNewMOM() {
  document.getElementById('momFormId').value = '';
  document.getElementById('momFormTitle').value = '';
  document.getElementById('momFormType').value = 'weekly';
  document.getElementById('momFormObjective').value = '';
  document.getElementById('momFormUrl').value = '';
  document.getElementById('momFormParticipants').value = '';
  setMOMQuickTime(0);
  clearMOMSuggestedCases();
  document.getElementById('momModalTitle').textContent = 'Add New Meeting';
  document.getElementById('momModalSub').textContent = 'Schedule operational coordination';
  openModal('momForm');
}

function openNewMOMWithCase(case_id) {
  openNewMOM();
  const item = document.querySelector(`.mom-suggestion-item[data-case-id="${case_id}"]`);
  if(item) toggleMOMSuggestedCase(item, true);
}

function editMOMHeader(mom_id, meeting_at) {
  const row = document.querySelector(`[data-mid="${mom_id}"]`);
  const editButton = document.querySelector(`[data-edit-mom-id="${mom_id}"]`);
  const title = row?.cells[1]?.textContent?.trim() || document.querySelector('.mom-title')?.textContent?.trim() || '';
  const type = row?.cells[2]?.textContent?.trim()?.toLowerCase() || document.querySelector('.mom-badge')?.textContent?.trim()?.toLowerCase() || 'weekly';
  
  document.getElementById('momFormId').value = mom_id;
  document.getElementById('momFormTitle').value = title;
  document.getElementById('momFormType').value = type;
  document.getElementById('momFormObjective').value = editButton?.dataset?.objective || '';
  document.getElementById('momFormUrl').value = editButton?.dataset?.meetingUrl || '';
  document.getElementById('momFormParticipants').value = editButton?.dataset?.participants || '';
  setMOMDateTime(meeting_at || row?.dataset?.meetingAt || '');
  clearMOMSuggestedCases();
  document.getElementById('momModalTitle').textContent = 'Edit Meeting';
  document.getElementById('momModalSub').textContent = 'Update meeting details';
  openModal('momForm');
}

function editMOMObjective(mom_id) {
  document.getElementById('momObjectiveText')?.focus();
}

function saveMOMObjective(mom_id) {
  const obj = document.getElementById('momObjectiveText')?.value?.trim() || '';
  api('api/api_mom.php', {
    action: 'update_objective',
    mom_id: mom_id,
    objective: obj
  }).then(r => {
    if(r.ok) {
      toast('Objective updated', 'success');
      momMarkSaved(document.getElementById('momObjectiveText'));
    } else {
      toast(r.msg || 'Failed to update objective', 'error');
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function editMOMParticipants(mom_id) {
  document.getElementById('momParticipantsText')?.focus();
}

function saveMOMParticipants(mom_id) {
  const parts = document.getElementById('momParticipantsText')?.value?.trim() || '';
  api('api/api_mom.php', {
    action: 'update_participants',
    mom_id: mom_id,
    participants: parts
  }).then(r => {
    if(r.ok) {
      toast('Participants updated', 'success');
      const tags=document.getElementById('momParticipantTags');
      if(tags){
        tags.innerHTML=parts.split(',').map(v=>v.trim()).filter(Boolean).map(v=>`<span class="participant-tag">${momEscape(v)}</span>`).join('');
      }
      document.querySelector('[data-sidebar-edit="participants"]')?.classList.remove('is-editing');
      momMarkSaved(document.querySelector('[data-sidebar-edit="participants"]'));
    } else {
      toast(r.msg || 'Failed to update participants', 'error');
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function toggleMOMSidebarEdit(section) {
  const card = document.querySelector(`[data-sidebar-edit="${section}"]`);
  if(!card) return;
  const isEditing = card.classList.toggle('is-editing');
  if(isEditing) {
    const firstField = card.querySelector('.mom-sidebar-edit input, .mom-sidebar-edit textarea, .mom-sidebar-edit select');
    firstField?.focus();
  } else {
    card.querySelectorAll('.is-pending-remove').forEach(el => el.classList.remove('is-pending-remove'));
  }
}

async function saveMOM() {
  const mom_id = document.getElementById('momFormId').value;
  const title = document.getElementById('momFormTitle').value.trim();
  const type = document.getElementById('momFormType').value;
  const objective = document.getElementById('momFormObjective').value.trim();
  const meeting_url = document.getElementById('momFormUrl').value.trim();
  const participants = document.getElementById('momFormParticipants').value.trim();
  const meeting_at = getMOMMeetingAt();
  
  if(!title) {
    toast('Title is required', 'warning');
    return;
  }
  
  const action = mom_id ? 'update_mom' : 'create_mom';
  const fd = new FormData();
  fd.append('action', action);
  if(mom_id) fd.append('mom_id', mom_id);
  fd.append('title', title);
  fd.append('type', type);
  fd.append('objective', objective);
  fd.append('meeting_url', meeting_url);
  fd.append('participants', participants);
  fd.append('meeting_at', meeting_at);

  const button = document.getElementById('momSaveBtn');
  try {
    const r = await withLoadingState(button, 'Saving...', async () => {
      const response = await fetch('api/api_mom.php', { method: 'POST', body: fd });
      const payload = await response.json();
      if(!response.ok) {
        const error = new Error(payload.msg || payload.message || 'Unable to save meeting.');
        error.status = response.status;
        throw error;
      }
      return payload;
    });
    if(!r) return;
    if(r.ok) {
      const targetMomId = r.mom_id || Number(mom_id || 0);
      await linkSelectedCasesToMOM(targetMomId);
      momMarkSaved(document.getElementById('momFormModal'));
      showModalSuccessAndClose({
        modal:'momForm',
        message:mom_id ? 'Meeting updated.' : 'Meeting scheduled.',
        onAfterClose:()=>{
          if(!mom_id && targetMomId) location.assign('mom.php');
          else {
            const titleEl=document.querySelector('.mom-title');
            if(titleEl)titleEl.textContent=title;
          }
        }
      });
    } else {
      handleModalError({modal:'momForm',error:{message:r.msg || r.message},fallbackMessage:'The meeting could not be saved. Please check the data and try again.'});
    }
  } catch(error) {
    handleModalError({modal:'momForm',button,error,fallbackMessage:'The meeting could not be saved. Please check the data and try again.'});
  }
}

function closeMOM(mom_id) {
  tracsConfirm('Complete this meeting and move it into Meeting History?', () => {
    api('api/api_mom.php', {
      action: 'close_mom',
      mom_id: mom_id
    }).then(r => {
      if(r.ok) {
        toast('Meeting completed', 'success');
        momNavigateAfterToast('mom.php',300);
      } else {
        toast(r.msg || 'Failed to close meeting', 'error');
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  });
}

function startMOM(mom_id) {
  api('api/api_mom.php', {
    action: 'start_mom',
    mom_id: mom_id
  }).then(r => {
    if(r.ok) {
      toast('Meeting started', 'success');
      momNavigateAfterToast('mom.php?mom_id=' + mom_id,250);
    } else {
      toast(r.msg || 'Failed to start meeting', 'error');
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function cancelMOM(mom_id) {
  tracsConfirm('Cancel this scheduled meeting?', () => {
    api('api/api_mom.php', {
      action: 'cancel_mom',
      mom_id: mom_id
    }).then(r => {
      if(r.ok) {
        toast('Meeting cancelled', 'success');
        momNavigateAfterToast('mom.php',250);
      } else {
        toast(r.msg || 'Failed to cancel meeting', 'error');
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  }, 'Cancel Meeting');
}

function saveMOMSummary(mom_id) {
  const summary = document.getElementById('momSummaryText')?.value?.trim() || '';
  api('api/api_mom.php', {
    action: 'save_summary',
    mom_id,
    summary
  }).then(r => {
    if(r.ok) {
      toast('MOM summary saved', 'success');
      momMarkSaved(document.getElementById('momSummaryText'));
    }
    else toast(r.msg || 'Failed to save summary', 'error');
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function deleteMOM(mom_id) {
  tracsConfirm('Delete this meeting? This cannot be undone.', () => {
    api('api/api_mom.php', {
      action: 'delete_mom',
      mom_id: mom_id
    }).then(r => {
      if(r.ok) {
        toast('Meeting deleted', 'success');
        document.querySelectorAll(`[data-mid="${mom_id}"], [data-preview-for="${mom_id}"]`).forEach(momRemoveNode);
      } else {
        toast(r.msg || 'Failed to delete meeting', 'error');
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  });
}

// ═══════════════════════════════════════════════════════════════
// AGENDA MANAGEMENT
// ═══════════════════════════════════════════════════════════════

function addAgendaItem(mom_id) {
  document.getElementById('momAgendaTopic')?.focus();
}

const momInlineSaving = new Set();

function momInlineKey(type, mom_id) {
  return `${type}:${mom_id}`;
}

function momStartInlineSave(type, mom_id) {
  const key = momInlineKey(type, mom_id);
  if(momInlineSaving.has(key)) return false;
  momInlineSaving.add(key);
  return true;
}

function momStopInlineSave(type, mom_id) {
  momInlineSaving.delete(momInlineKey(type, mom_id));
}

function saveInlineAgendaItem(mom_id, options = {}) {
  const input = document.getElementById('momAgendaTopic');
  const topic = input?.value?.trim() || '';
  if(!topic) {
    if(options.silent) return;
    toast('Agenda topic is required', 'warning');
    input?.focus();
    return;
  }
  if(!momStartInlineSave('agenda', mom_id)) return;
  api('api/api_mom.php', {
    action: 'add_agenda_item',
    mom_id: mom_id,
    topic: topic
  }).then(r => {
    if(r.ok) {
      toast('Agenda item added', 'success');
      momAppendAgendaItem(r.item_id, topic);
      input.value='';
      momStopInlineSave('agenda', mom_id);
      momMarkSaved(input.closest('[data-mom-autosave]') || input);
    } else {
      momStopInlineSave('agenda', mom_id);
      toast(r.msg || 'Failed to add agenda item', 'error');
    }
  }).catch(e => {
    momStopInlineSave('agenda', mom_id);
    toast(e.message || "The change didn't go through. Please try again.", 'error');
  });
}

function toggleAgendaItem(item_id, checked) {
  const status = checked ? 'completed' : 'pending';
  
  api('api/api_mom.php', {
    action: 'update_agenda_item',
    item_id: item_id,
    status: status
  }).then(r => {
    if(r.ok) {
      // Optimistic UI update
      const item = document.querySelector(`[data-agenda-id="${item_id}"]`);
      if(item) {
        item.classList.toggle('agenda-item-completed', checked);
      }
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function deleteAgendaItem(item_id) {
  tracsConfirm('Delete this agenda item?', () => {
    api('api/api_mom.php', {
      action: 'delete_agenda_item',
      item_id: item_id
    }).then(r => {
      if(r.ok) {
        toast('Agenda item deleted', 'success');
        momRemoveNode(document.querySelector(`[data-agenda-id="${item_id}"]`));
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  });
}

// ═══════════════════════════════════════════════════════════════
// DISCUSSION NOTES
// ═══════════════════════════════════════════════════════════════

function addDiscussionNote(mom_id) {
  window._currentMOMId = mom_id;
  document.getElementById('momInlineNoteContent')?.focus();
}

function saveInlineDiscussionNote(mom_id, options = {}) {
  const contentEl = document.getElementById('momInlineNoteContent');
  const typeEl = document.getElementById('momInlineNoteType');
  const content = contentEl?.value?.trim() || '';
  const note_type = typeEl?.value || 'discussion';
  
  if(!content) {
    if(options.silent) return;
    toast('Note content required', 'warning');
    contentEl?.focus();
    return;
  }
  if(!momStartInlineSave('note', mom_id)) return;
  
  api('api/api_mom.php', {
    action: 'add_discussion_note',
    mom_id: mom_id,
    content: content,
    note_type: note_type
  }).then(r => {
    if(r.ok) {
      toast('Note added', 'success');
      momAppendNote(r.note_id, content, note_type);
      contentEl.value='';
      momStopInlineSave('note', mom_id);
      momMarkSaved(contentEl.closest('[data-mom-autosave]') || contentEl);
    } else {
      momStopInlineSave('note', mom_id);
      toast(r.msg || 'Failed to add note', 'error');
    }
  }).catch(e => {
    momStopInlineSave('note', mom_id);
    toast(e.message || "The change didn't go through. Please try again.", 'error');
  });
}

async function saveDiscussionNote() {
  if(!momModalIsOpen('momNoteForm')) {
    saveInlineDiscussionNote(window._currentMOMId);
    return;
  }
  const contentEl=document.getElementById('momNoteFormContent');
  const content=contentEl?.value?.trim() || '';
  if(!content) {
    handleModalError({modal:'momNoteForm',message:'Note content is required.',focus:contentEl});
    return;
  }
  const button=document.getElementById('momNoteSaveBtn');
  const r=await withLoadingState(button,'Saving...',()=>api('api/api_mom.php',{
    action:'add_discussion_note',
    mom_id:window._currentMOMId,
    content,
    note_type:document.getElementById('momNoteFormType')?.value || 'discussion'
  }));
  if(!r)return;
  if(r.ok) {
    momAppendNote(r.note_id, content, document.getElementById('momNoteFormType')?.value || 'discussion');
    momMarkSaved(document.getElementById('momNoteFormModal'));
    showModalSuccessAndClose({modal:'momNoteForm',message:'Discussion note saved.'});
  } else {
    handleModalError({modal:'momNoteForm',error:{message:r.msg || r.message},fallbackMessage:'The discussion note could not be saved. Please try again.'});
  }
}

function deleteNote(note_id) {
  tracsConfirm('Delete this note?', () => {
    api('api/api_mom.php', {
      action: 'delete_note',
      note_id: note_id
    }).then(r => {
      if(r.ok) {
        toast('Note deleted', 'success');
        momRemoveNode(document.querySelector(`[data-note-id="${note_id}"]`));
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  });
}

function handleTextSelection(noteElement) {
  const selection = window.getSelection();
  const selectedText = selection.toString().trim();
  
  if(!selectedText) return;
  
  // Show quick action menu above selection
  const range = selection.getRangeAt(0);
  const rect = range.getBoundingClientRect();
  
  const menu = document.createElement('div');
  menu.className = 'text-selection-menu';
  [
    ['Create Action', () => createActionFromText(selectedText)],
    ['Create Reminder', () => createReminderFromText(selectedText)],
    ['Add Decision', () => addDecisionFromText(selectedText)]
  ].forEach(([label, handler]) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = label;
    btn.addEventListener('click', handler);
    menu.appendChild(btn);
  });
  
  menu.style.position = 'fixed';
  menu.style.top = (rect.top - 50) + 'px';
  menu.style.left = rect.left + 'px';
  menu.style.zIndex = '10000';
  
  document.body.appendChild(menu);
  setTimeout(() => menu.remove(), 8000);
}

// ═══════════════════════════════════════════════════════════════
// DECISIONS
// ═══════════════════════════════════════════════════════════════

function addDecision(mom_id) {
  window._currentMOMId = mom_id;
  document.getElementById('momInlineDecisionText')?.focus();
}

function addDecisionFromText(text) {
  addDecision(window._currentMOMId);
  const el = document.getElementById('momInlineDecisionText');
  if(el) el.value = text;
}

function saveInlineDecision(mom_id, options = {}) {
  const decisionEl = document.getElementById('momInlineDecisionText');
  const decision = decisionEl?.value?.trim() || '';
  const rationale = document.getElementById('momInlineDecisionRationale')?.value?.trim() || '';
  const owner = document.getElementById('momInlineDecisionOwner')?.value?.trim() || '';
  
  if(!decision) {
    if(options.silent) return;
    toast('Decision text required', 'warning');
    decisionEl?.focus();
    return;
  }
  if(!momStartInlineSave('decision', mom_id)) return;
  
  api('api/api_mom.php', {
    action: 'add_decision',
    mom_id: mom_id,
    decision: decision,
    rationale: rationale,
    owner: owner
  }).then(r => {
    if(r.ok) {
      toast('Decision recorded', 'success');
      momAppendDecision(r.decision_id, decision, rationale, owner);
      decisionEl.value='';
      const rationaleEl=document.getElementById('momInlineDecisionRationale');
      const ownerEl=document.getElementById('momInlineDecisionOwner');
      if(rationaleEl)rationaleEl.value='';
      if(ownerEl)ownerEl.value='';
      momStopInlineSave('decision', mom_id);
      momMarkSaved(decisionEl.closest('[data-mom-autosave]') || decisionEl);
    } else {
      momStopInlineSave('decision', mom_id);
      toast(r.msg || 'Failed to add decision', 'error');
    }
  }).catch(e => {
    momStopInlineSave('decision', mom_id);
    toast(e.message || "The change didn't go through. Please try again.", 'error');
  });
}

async function saveDecision() {
  if(!momModalIsOpen('momDecisionForm')) {
    saveInlineDecision(window._currentMOMId);
    return;
  }
  const decisionEl=document.getElementById('momDecisionFormText');
  const decision=decisionEl?.value?.trim() || '';
  if(!decision) {
    handleModalError({modal:'momDecisionForm',message:'Decision text is required.',focus:decisionEl});
    return;
  }
  const button=document.getElementById('momDecisionSaveBtn');
  const r=await withLoadingState(button,'Saving...',()=>api('api/api_mom.php',{
    action:'add_decision',
    mom_id:window._currentMOMId,
    decision,
    rationale:document.getElementById('momDecisionFormRationale')?.value?.trim() || '',
    owner:document.getElementById('momDecisionFormOwner')?.value?.trim() || ''
  }));
  if(!r)return;
  if(r.ok) {
    momAppendDecision(r.decision_id, decision, document.getElementById('momDecisionFormRationale')?.value?.trim() || '', document.getElementById('momDecisionFormOwner')?.value?.trim() || '');
    momMarkSaved(document.getElementById('momDecisionFormModal'));
    showModalSuccessAndClose({modal:'momDecisionForm',message:'Decision recorded.'});
  } else {
    handleModalError({modal:'momDecisionForm',error:{message:r.msg || r.message},fallbackMessage:'The decision could not be saved. Please try again.'});
  }
}

function deleteDecision(decision_id) {
  tracsConfirm('Delete this decision?', () => {
    api('api/api_mom.php', {
      action: 'delete_decision',
      decision_id: decision_id
    }).then(r => {
      if(r.ok) {
        toast('Decision deleted', 'success');
        const btn=document.querySelector(`[onclick="deleteDecision(${decision_id})"]`);
        momRemoveNode(btn?.closest('.decision-card'));
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  });
}

// ═══════════════════════════════════════════════════════════════
// ACTION ITEMS
// ═══════════════════════════════════════════════════════════════

function addActionItem(mom_id) {
  window._currentMOMId = mom_id;
  document.getElementById('momInlineActionTitle')?.focus();
}

function createActionFromText(text) {
  addActionItem(window._currentMOMId);
  const el = document.getElementById('momInlineActionTitle');
  if(el) el.value = text;
}

function editActionItem(action_id) {
  const row = document.querySelector(`[data-aid="${action_id}"]`);
  if(!row) return;
  document.getElementById('momInlineActionTitle').value = row.querySelector('.action-title')?.textContent?.trim() || '';
  document.getElementById('momInlineActionDesc').value = row.querySelector('.action-desc')?.textContent?.trim() || '';
  document.getElementById('momInlineActionTitle')?.focus();
  toast('Loaded action into inline editor. Update the fields and save.', 'info');
}

function saveInlineActionItem(mom_id, options = {}) {
  const titleEl = document.getElementById('momInlineActionTitle');
  const title = titleEl?.value?.trim() || '';
  const description = document.getElementById('momInlineActionDesc')?.value?.trim() || '';
  const assignee = document.getElementById('momInlineActionAssignee')?.value?.trim() || '';
  const priority = document.getElementById('momInlineActionPriority')?.value || 'medium';
  const due_date = document.getElementById('momInlineActionDueDate')?.value || '';
  
  if(!title) {
    if(options.silent) return;
    toast('Action title required', 'warning');
    titleEl?.focus();
    return;
  }
  if(!momStartInlineSave('action', mom_id)) return;
  
  const payload = {
    action: 'add_action_item',
    mom_id: mom_id,
    title: title,
    description: description,
    assigned_to: assignee,
    priority: priority,
    due_date: due_date
  };
  
  api('api/api_mom.php', payload).then(r => {
    if(r.ok) {
      toast('Action created', 'success');
      momAppendAction(r.action_id, title, description, assignee, priority, due_date);
      ['momInlineActionTitle','momInlineActionDesc','momInlineActionAssignee','momInlineActionDueDate'].forEach(id => {
        const el=document.getElementById(id);
        if(el)el.value='';
      });
      const priorityEl=document.getElementById('momInlineActionPriority');
      if(priorityEl)priorityEl.value='medium';
      momStopInlineSave('action', mom_id);
      momMarkSaved(titleEl.closest('[data-mom-autosave]') || titleEl);
    } else {
      momStopInlineSave('action', mom_id);
      toast(r.msg || 'Failed to save action', 'error');
    }
  }).catch(e => {
    momStopInlineSave('action', mom_id);
    toast(e.message || "The change didn't go through. Please try again.", 'error');
  });
}

async function saveActionItem() {
  if(!momModalIsOpen('momActionForm')) {
    saveInlineActionItem(window._currentMOMId);
    return;
  }
  const titleEl=document.getElementById('momActionFormTitle');
  const title=titleEl?.value?.trim() || '';
  if(!title) {
    handleModalError({modal:'momActionForm',message:'Action title is required.',focus:titleEl});
    return;
  }
  const button=document.getElementById('momActionSaveBtn');
  const r=await withLoadingState(button,'Saving...',()=>api('api/api_mom.php',{
    action:'add_action_item',
    mom_id:window._currentMOMId,
    title,
    description:document.getElementById('momActionFormDesc')?.value?.trim() || '',
    assigned_to:document.getElementById('momActionFormAssignee')?.value?.trim() || '',
    priority:document.getElementById('momActionFormPriority')?.value || 'medium',
    due_date:document.getElementById('momActionFormDueDate')?.value || ''
  }));
  if(!r)return;
  if(r.ok) {
    momAppendAction(
      r.action_id,
      title,
      document.getElementById('momActionFormDesc')?.value?.trim() || '',
      document.getElementById('momActionFormAssignee')?.value?.trim() || '',
      document.getElementById('momActionFormPriority')?.value || 'medium',
      document.getElementById('momActionFormDueDate')?.value || ''
    );
    momMarkSaved(document.getElementById('momActionFormModal'));
    showModalSuccessAndClose({modal:'momActionForm',message:'Action item saved.'});
  } else {
    handleModalError({modal:'momActionForm',error:{message:r.msg || r.message},fallbackMessage:'The action item could not be saved. Please try again.'});
  }
}

function momInlineHasContent(form) {
  return [...form.querySelectorAll('input, textarea')]
    .some(el => String(el.value || '').trim() !== '');
}

function saveMOMInlineForm(form) {
  if(!form || !momInlineHasContent(form)) return;
  const momId = Number(form.dataset.momId || 0);
  const type = form.dataset.momAutosave || '';
  if(!momId || !type) return;

  if(type === 'agenda') saveInlineAgendaItem(momId, { silent: true });
  if(type === 'note') saveInlineDiscussionNote(momId, { silent: true });
  if(type === 'decision') saveInlineDecision(momId, { silent: true });
  if(type === 'action') saveInlineActionItem(momId, { silent: true });
}

document.addEventListener('focusout', event => {
  const form = event.target?.closest?.('[data-mom-autosave]');
  if(!form) return;
  setTimeout(() => {
    if(form.contains(document.activeElement)) return;
    saveMOMInlineForm(form);
  }, 0);
});

document.addEventListener('keydown', event => {
  if(event.key !== 'Enter' || event.shiftKey) return;
  const form = event.target?.closest?.('[data-mom-autosave]');
  if(!form) return;
  if(event.target?.tagName === 'TEXTAREA' && event.target.id !== 'momAgendaTopic') return;
  event.preventDefault();
  saveMOMInlineForm(form);
});

function completeAction(action_id, checked) {
  api('api/api_mom.php', {
    action: 'complete_action',
    action_id: action_id,
    completed: checked
  }).then(r => {
    if(r.ok) {
      // Optimistic UI update
      const item = document.querySelector(`[data-aid="${action_id}"]`);
      if(item) {
        item.classList.toggle('action-item-completed', checked);
        item.classList.toggle('action-item-pending', !checked);
      }
      momUpdateActionProgress();
      toast(checked ? 'Action completed' : 'Action reopened', 'success');
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function deleteActionItem(action_id) {
  tracsConfirm('Delete this action item?', () => {
    api('api/api_mom.php', {
      action: 'delete_action_item',
      action_id: action_id
    }).then(r => {
      if(r.ok) {
        toast('Action deleted', 'success');
        momRemoveNode(document.querySelector(`[data-aid="${action_id}"]`));
        setTimeout(momUpdateActionProgress,210);
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  });
}

// ═══════════════════════════════════════════════════════════════
// REMINDER INTEGRATION
// ═══════════════════════════════════════════════════════════════

function createReminderFromAction(action_id) {
  tracsConfirm('Create a reminder for this action?', () => {
    api('api/api_mom.php', {
      action: 'create_reminder_from_action',
      action_id: action_id
    }).then(r => {
      if(r.ok) {
        toast('Reminder created and linked', 'success');
        momApplyReminder(r.reminder || {id:r.reminder_id,title:'Reminder'});
      } else {
        toast(r.msg || 'Failed to create reminder', 'error');
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  }, 'Create Reminder');
}

function createReminderFromText(text) {
  document.getElementById('remTitle').value = text;
  openNewReminder();
}

// ═══════════════════════════════════════════════════════════════
// CASE LINKING
// ═══════════════════════════════════════════════════════════════

function linkCaseToMOM(mom_id) {
  document.getElementById('momInlineCaseId')?.focus();
}

function saveInlineCaseLink(mom_id) {
  const input = document.getElementById('momInlineCaseId');
  const case_id = input?.value?.trim() || '';
  if(!case_id) {
    toast('Case ID is required', 'warning');
    input?.focus();
    return;
  }
  api('api/api_mom.php', {
    action: 'link_case',
    mom_id: mom_id,
    case_id: parseInt(case_id)
  }).then(r => {
    if(r.ok) {
      toast('Case linked', 'success');
      momApplyCase(r.case || {id:case_id,title:`Case #${case_id}`});
      if(input)input.value='';
      momMarkSaved(input || document.querySelector('[data-sidebar-edit="cases"]'));
    } else {
      toast(r.msg || 'Failed to link case', 'error');
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function markMOMCaseForRemoval(button) {
  const item = button?.closest('.case-item');
  if(!item) return;
  item.classList.toggle('is-pending-remove');
}

function saveMOMSidebarCases(mom_id) {
  const input = document.getElementById('momInlineCaseId');
  const case_id = input?.value?.trim() || '';
  const removals = [...document.querySelectorAll('[data-sidebar-edit="cases"] .case-item.is-pending-remove')]
    .map(item => Number(item.dataset.caseId || 0))
    .filter(Boolean);

  const jobs = [];
  if(case_id) {
    jobs.push(api('api/api_mom.php', {
      action: 'link_case',
      mom_id,
      case_id: parseInt(case_id, 10)
    }));
  }
  removals.forEach(id => {
    jobs.push(api('api/api_mom.php', {
      action: 'unlink_case',
      mom_id,
      case_id: id
    }));
  });

  if(!jobs.length) {
    toggleMOMSidebarEdit('cases');
    return;
  }

  Promise.all(jobs).then(results => {
    const failed = results.find(r => !r.ok);
    if(failed) {
      toast(failed.msg || 'Failed to save linked cases', 'error');
      return;
    }
    toast('Linked cases updated', 'success');
    removals.forEach(momRemoveCase);
    if(case_id) {
      const linked = results.find(r => r.case || r.case_id);
      momApplyCase(linked?.case || {id:Number(case_id),title:`Case #${case_id}`});
      if(input)input.value='';
    }
    toggleMOMSidebarEdit('cases');
    momMarkSaved(document.querySelector('[data-sidebar-edit="cases"]'));
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function createCaseFromAction(action_id) {
  tracsConfirm('Create an operational case for this action?', () => {
    api('api/api_mom.php', {
      action: 'create_case_from_action',
      action_id: action_id
    }).then(r => {
      if(r.ok) {
        toast('Case created and linked', 'success');
        momApplyCase(r.case || {id:r.case_id,title:`Case #${r.case_id}`});
      } else {
        toast(r.msg || 'Failed to create case', 'error');
      }
    }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
  }, 'Create Case');
}

function resolveLinkedCaseFromMOM(mom_id, case_id) {
  const status = document.getElementById(`momCaseStatus${case_id}`)?.value || 'completed';
  const note = document.getElementById(`momCaseNote${case_id}`)?.value || '';
  api('api/api_mom.php', {
    action: 'resolve_linked_case',
    mom_id,
    case_id,
    status,
    note
  }).then(r => {
    if(r.ok) {
      toast('Linked case updated', 'success');
      momApplyCase(r.case || {id:case_id,status});
      const noteEl=document.getElementById(`momCaseNote${case_id}`);
      if(noteEl)noteEl.value='';
      momMarkSaved(noteEl || document.getElementById(`momCaseStatus${case_id}`));
    } else {
      toast(r.msg || 'Failed to update case', 'error');
    }
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

function uploadMOMScreenshotFile(mom_id, file) {
  if(!file || !file.type || !file.type.startsWith('image/')) {
    toast('Screenshot must be an image', 'warning');
    return Promise.resolve(false);
  }
  if(file.size > 5 * 1024 * 1024) {
    toast('Screenshot must be under 5MB', 'warning');
    return Promise.resolve(false);
  }
  return new Promise(resolve => {
    const reader = new FileReader();
    reader.onload = () => {
      api('api/api_mom.php', {
        action: 'upload_screenshot',
        mom_id,
        image_data: reader.result
      }).then(r => {
        if(r.ok) {
          momApplyScreenshot(r.screenshot || {id:r.screenshot_id});
          resolve(true);
        }
        else { toast(r.msg || 'Failed to upload screenshot', 'error'); resolve(false); }
      }).catch(e => { toast(e.message || "The change didn't go through. Please try again.", 'error'); resolve(false); });
    };
    reader.readAsDataURL(file);
  });
}

async function uploadMOMScreenshotFiles(mom_id, files) {
  const list = Array.from(files || []).filter(Boolean);
  if(!list.length) return;
  let uploaded = 0;
  for(const file of list) {
    if(await uploadMOMScreenshotFile(mom_id, file)) uploaded++;
  }
  if(uploaded) {
    toast(`${uploaded} screenshot${uploaded === 1 ? '' : 's'} uploaded`, 'success');
    momMarkSaved(document.querySelector('[data-sidebar-edit="screenshots"]'));
  }
}

function uploadMOMScreenshot(mom_id, input) {
  const files = input.files;
  if(!files || !files.length) return;
  uploadMOMScreenshotFiles(mom_id, files).finally(() => { input.value = ''; });
}

/* Screenshots card: click (above), drag & drop, and paste all funnel through
   the same uploadMOMScreenshotFiles — same upload behavior as the case and
   shift handover modals. */
function momScreenshotsCard() {
  return document.querySelector('[data-sidebar-edit="screenshots"]');
}
function momInitScreenshotDropzone() {
  const card = momScreenshotsCard();
  if(!card || card.dataset.dropReady) return;
  card.dataset.dropReady = '1';
  ['dragenter', 'dragover'].forEach(evt => card.addEventListener(evt, e => {
    if(!card.classList.contains('is-editing')) return;
    e.preventDefault();
    card.classList.add('is-drag-over');
  }));
  ['dragleave', 'drop'].forEach(evt => card.addEventListener(evt, e => {
    e.preventDefault();
    card.classList.remove('is-drag-over');
  }));
  card.addEventListener('drop', e => {
    if(!card.classList.contains('is-editing')) return;
    const momId = Number(card.dataset.momId || 0);
    if(!momId) return;
    uploadMOMScreenshotFiles(momId, e.dataTransfer?.files);
  });
}
momInitScreenshotDropzone();
document.addEventListener('paste', e => {
  const card = momScreenshotsCard();
  if(!card || !card.classList.contains('is-editing')) return;
  const items = e.clipboardData?.items;
  if(!items || !items.length) return;
  const files = [];
  for(const it of items) {
    if(it.kind === 'file' && it.type && it.type.startsWith('image/')) {
      const f = it.getAsFile();
      if(f) files.push(f);
    }
  }
  if(!files.length) return;
  e.preventDefault();
  const momId = Number(card.dataset.momId || 0);
  if(momId) uploadMOMScreenshotFiles(momId, files);
});

function markMOMScreenshotForRemoval(button) {
  const item = button?.closest('.mom-shot-item');
  if(!item) return;
  item.classList.toggle('is-pending-remove');
}

function saveMOMSidebarScreenshots(mom_id) {
  const removals = [...document.querySelectorAll('[data-sidebar-edit="screenshots"] .mom-shot-item.is-pending-remove')]
    .map(item => Number(item.dataset.shotId || 0))
    .filter(Boolean);

  if(!removals.length) {
    toggleMOMSidebarEdit('screenshots');
    return;
  }

  Promise.all(removals.map(id => api('api/api_mom.php', {
    action: 'delete_screenshot',
    mom_id,
    screenshot_id: id
  }))).then(results => {
    const failed = results.find(r => !r.ok);
    if(failed) {
      toast(failed.msg || 'Failed to delete screenshot', 'error');
      return;
    }
    toast('Screenshots updated', 'success');
    removals.forEach(id=>momRemoveNode(document.querySelector(`[data-shot-id="${id}"]`)));
    toggleMOMSidebarEdit('screenshots');
    momMarkSaved(document.querySelector('[data-sidebar-edit="screenshots"]'));
    setTimeout(()=>{
      const host=document.querySelector('.mom-shot-list');
      if(host && !host.querySelector('.mom-shot-item'))host.insertAdjacentHTML('beforeend',momEmpty('No screenshots uploaded'));
    },220);
  }).catch(e => toast(e.message || "The change didn't go through. Please try again.", 'error'));
}

let momShotLightboxItems = [];
let momShotLightboxIndex = 0;

function momScreenshotGroupFor(trigger) {
  const group = trigger?.closest?.('.mom-shot-list, .mom-preview-shots') || document;
  return [...group.querySelectorAll('[data-mom-screenshot-src]')]
    .map(el => ({
      src: el.dataset.momScreenshotSrc || '',
      alt: el.querySelector('img')?.alt || 'MOM screenshot'
    }))
    .filter(item => item.src);
}

function renderMOMScreenshotLightbox() {
  const box = document.getElementById('momShotLightbox');
  const img = document.getElementById('momShotLightboxImage');
  const count = document.getElementById('momShotLightboxCount');
  if(!box || !img || !momShotLightboxItems.length) return;

  const item = momShotLightboxItems[momShotLightboxIndex];
  img.src = item.src;
  img.alt = item.alt;

  const hasMany = momShotLightboxItems.length > 1;
  box.classList.toggle('has-multiple', hasMany);
  if(count) count.textContent = hasMany ? `${momShotLightboxIndex + 1} / ${momShotLightboxItems.length}` : '';
}

function openMOMScreenshotLightbox(trigger) {
  const box = document.getElementById('momShotLightbox');
  if(!box) return;

  momShotLightboxItems = momScreenshotGroupFor(trigger);
  const src = trigger?.dataset?.momScreenshotSrc || '';
  momShotLightboxIndex = Math.max(0, momShotLightboxItems.findIndex(item => item.src === src));
  if(!momShotLightboxItems.length) return;

  renderMOMScreenshotLightbox();
  box.classList.remove('hidden');
  document.body.classList.add('mom-shot-lightbox-open');
  if(window.lucide) lucide.createIcons({ nodes: box.querySelectorAll('[data-lucide]') });
}

function closeMOMScreenshotLightbox() {
  const box = document.getElementById('momShotLightbox');
  const img = document.getElementById('momShotLightboxImage');
  box?.classList.add('hidden');
  if(img) img.src = '';
  document.body.classList.remove('mom-shot-lightbox-open');
}

function moveMOMScreenshotLightbox(direction) {
  if(momShotLightboxItems.length <= 1) return;
  momShotLightboxIndex = (momShotLightboxIndex + direction + momShotLightboxItems.length) % momShotLightboxItems.length;
  renderMOMScreenshotLightbox();
}

document.addEventListener('keydown', event => {
  const box = document.getElementById('momShotLightbox');
  if(!box || box.classList.contains('hidden')) return;
  if(event.key === 'Escape') closeMOMScreenshotLightbox();
  if(event.key === 'ArrowLeft') moveMOMScreenshotLightbox(-1);
  if(event.key === 'ArrowRight') moveMOMScreenshotLightbox(1);
});

document.addEventListener('click', event => {
  if(event.target?.id === 'momShotLightbox') closeMOMScreenshotLightbox();
});

function filterMOMHistory(value) {
  const needle = String(value || '').trim().toLowerCase();
  document.querySelectorAll('[data-mom-history-row]').forEach(row => {
    const visible = !needle || (row.dataset.momSearch || '').includes(needle);
    row.style.display = visible ? '' : 'none';
    const preview = document.querySelector(`[data-mom-preview-row][data-preview-for="${row.dataset.mid}"]`);
  if(preview) {
      preview.style.display = visible ? '' : 'none';
    }
  });
}

const MOM_PREVIEW_OPEN_MS = 280;
const MOM_PREVIEW_CLOSE_MS = 260;

function getMOMPreviewInner(row) {
  return row?.querySelector?.('.mom-preview-inner') || null;
}

function setMOMPreviewHeight(row) {
  const inner = getMOMPreviewInner(row);
  if(!inner) return;
  inner.style.setProperty('--mom-preview-height', `${inner.scrollHeight}px`);
}

function resetMOMPreviewInner(inner) {
  if(!inner) return;
  inner.style.maxHeight = '';
  inner.style.opacity = '';
  inner.style.transform = '';
  inner.style.transition = '';
}

function refreshMOMPreviewButton(sourceRow, expanded) {
  sourceRow?.classList.toggle('is-preview-open', expanded);
  const btn = sourceRow?.querySelector('.mom-preview-toggle');
  if(!btn) return;
  btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  btn.classList.toggle('is-active', expanded);
  const icon = btn.querySelector('[data-lucide]');
  if(icon) icon.setAttribute('data-lucide', expanded ? 'chevron-up' : 'chevron-down');
  if(window.lucide) lucide.createIcons({ nodes: [btn] });
}

function closeMOMPreviewRow(row, immediate = false) {
  if(!row || (row.classList.contains('mom-preview-collapsed') && !row.classList.contains('mom-preview-closing'))) return;
  window.clearTimeout(row._momPreviewTimer);
  const inner = getMOMPreviewInner(row);
  row.classList.remove('mom-preview-opening');

  const sourceRow = row.dataset.previewFor
    ? document.querySelector(`tr[data-mid="${row.dataset.previewFor}"]`)
    : null;
  refreshMOMPreviewButton(sourceRow, false);

  if(immediate || !inner) {
    row.classList.add('mom-preview-collapsed');
    row.classList.remove('mom-preview-closing');
    row.style.display = '';
    resetMOMPreviewInner(inner);
    return;
  }

  row.style.display = '';
  row.classList.add('mom-preview-closing');
  row.classList.remove('mom-preview-collapsed');
  inner.style.transition = 'none';
  inner.style.maxHeight = `${inner.scrollHeight}px`;
  inner.style.opacity = '1';
  inner.style.transform = 'translateY(0)';
  inner.offsetHeight;
  inner.style.transition = '';
  requestAnimationFrame(() => {
    inner.style.maxHeight = '0px';
    inner.style.opacity = '0';
    inner.style.transform = 'translateY(-6px)';
  });
  row._momPreviewTimer = window.setTimeout(() => {
    row.classList.add('mom-preview-collapsed');
    row.classList.remove('mom-preview-closing');
    row.style.display = '';
    resetMOMPreviewInner(inner);
  }, MOM_PREVIEW_CLOSE_MS);
}

function openMOMPreviewRow(row, sourceRow) {
  window.clearTimeout(row._momPreviewTimer);
  const inner = getMOMPreviewInner(row);
  row.style.display = '';
  row.classList.remove('mom-preview-collapsed', 'mom-preview-closing', 'mom-preview-opening');
  if(inner) {
    inner.style.transition = 'none';
    inner.style.maxHeight = '0px';
    inner.style.opacity = '0';
    inner.style.transform = 'translateY(-8px)';
    inner.offsetHeight;
    setMOMPreviewHeight(row);
    inner.style.transition = '';
  }
  row.classList.add('mom-preview-opening');
  requestAnimationFrame(() => {
    if(inner) {
      inner.style.maxHeight = `${inner.scrollHeight}px`;
      inner.style.opacity = '1';
      inner.style.transform = 'translateY(0)';
    }
  });
  row._momPreviewTimer = window.setTimeout(() => {
    row.classList.remove('mom-preview-opening');
    if(inner) inner.style.maxHeight = '';
  }, MOM_PREVIEW_OPEN_MS);
  refreshMOMPreviewButton(sourceRow, true);
}

function toggleMOMPreviewNote(noteId, button) {
  const text = document.getElementById(noteId);
  if(!text) return;
  const collapsed = text.classList.toggle('is-clamped');
  button.textContent = collapsed ? 'Show more' : 'Show less';
}

function toggleMOMPreview(mom_id, button) {
  if(button?.disabled) return;
  const row = document.getElementById(`momPreview${mom_id}`);
  if(!row) return;
  const willOpen = row.classList.contains('mom-preview-collapsed') || row.classList.contains('mom-preview-closing');
  const sourceRow = button?.closest('tr');

  // Accordion: close all other open previews first
  document.querySelectorAll('[data-mom-preview-row]').forEach(otherRow => {
    if(otherRow === row) return;
    if(!otherRow.classList.contains('mom-preview-collapsed')) {
      closeMOMPreviewRow(otherRow, false);
    }
  });

  // Toggle this row
  if(willOpen) {
    openMOMPreviewRow(row, sourceRow);
  } else {
    closeMOMPreviewRow(row, false);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.mom-preview-row.hidden').forEach(row => {
    row.classList.remove('hidden');
    row.classList.add('mom-preview-collapsed');
    row.classList.remove('mom-preview-opening', 'mom-preview-closing');
    row.style.display = '';
  });
});

// ═══════════════════════════════════════════════════════════════
// API HELPER (uses existing tracs.js api() function)
// ═══════════════════════════════════════════════════════════════

// Assumes api() and toast() functions exist in tracs.js
// Uses tracsConfirm() from tracs.js

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  // Initialize any date pickers or additional functionality
  document.querySelectorAll('.mom-quick-btn[data-mom-quick-hours]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      setMOMQuickTime(btn.dataset.momQuickHours);
    });
  });
  lucide.createIcons();
});
