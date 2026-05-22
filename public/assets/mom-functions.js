/* ═════════════════════════════════════════════════════════════════
   TRACS — MOM (Minutes of Meeting) JavaScript
   Client-side functionality for meeting workspace operations
═════════════════════════════════════════════════════════════════ */

// ═══════════════════════════════════════════════════════════════
// MODAL MANAGEMENT
// ═══════════════════════════════════════════════════════════════

function momReloadAfterToast(delay=420){
  if(window.reloadAfterToast)window.reloadAfterToast(delay);
  else setTimeout(()=>location.reload(),delay);
}

function momNavigateAfterToast(url,delay=420){
  if(window.navigateAfterToast)window.navigateAfterToast(url,delay);
  else setTimeout(()=>{location.href=url;},delay);
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
      momReloadAfterToast();
    } else {
      toast(r.msg || 'Failed to update objective', 'error');
    }
  }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast();
    } else {
      toast(r.msg || 'Failed to update participants', 'error');
    }
  }).catch(e => toast('Error: ' + e.message, 'error'));
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

function saveMOM() {
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

  fetch('api/api_mom.php', { method: 'POST', body: fd })
  .then(r => r.json())
  .then(async r => {
    if(r.ok) {
      const targetMomId = r.mom_id || Number(mom_id || 0);
      await linkSelectedCasesToMOM(targetMomId);
      toast(mom_id ? 'Meeting updated' : 'Meeting scheduled', 'success');
      if(targetMomId) {
        momNavigateAfterToast(mom_id ? 'mom.php?mom_id=' + targetMomId : 'mom.php',300);
      } else {
        momReloadAfterToast(300);
      }
    } else {
      toast(r.msg || 'Failed to save meeting', 'error');
    }
  }).catch(e => toast('Error: ' + e.message, 'error'));
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
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
  }).catch(e => toast('Error: ' + e.message, 'error'));
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
    }).catch(e => toast('Error: ' + e.message, 'error'));
  }, 'Cancel Meeting');
}

function saveMOMSummary(mom_id) {
  const summary = document.getElementById('momSummaryText')?.value?.trim() || '';
  api('api/api_mom.php', {
    action: 'save_summary',
    mom_id,
    summary
  }).then(r => {
    if(r.ok) toast('MOM summary saved', 'success');
    else toast(r.msg || 'Failed to save summary', 'error');
  }).catch(e => toast('Error: ' + e.message, 'error'));
}

function deleteMOM(mom_id) {
  tracsConfirm('Delete this meeting? This cannot be undone.', () => {
    api('api/api_mom.php', {
      action: 'delete_mom',
      mom_id: mom_id
    }).then(r => {
      if(r.ok) {
        toast('Meeting deleted', 'success');
        momReloadAfterToast(300);
      } else {
        toast(r.msg || 'Failed to delete meeting', 'error');
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast();
    } else {
      momStopInlineSave('agenda', mom_id);
      toast(r.msg || 'Failed to add agenda item', 'error');
    }
  }).catch(e => {
    momStopInlineSave('agenda', mom_id);
    toast('Error: ' + e.message, 'error');
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
  }).catch(e => toast('Error: ' + e.message, 'error'));
}

function deleteAgendaItem(item_id) {
  tracsConfirm('Delete this agenda item?', () => {
    api('api/api_mom.php', {
      action: 'delete_agenda_item',
      item_id: item_id
    }).then(r => {
      if(r.ok) {
        toast('Agenda item deleted', 'success');
        momReloadAfterToast();
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast();
    } else {
      momStopInlineSave('note', mom_id);
      toast(r.msg || 'Failed to add note', 'error');
    }
  }).catch(e => {
    momStopInlineSave('note', mom_id);
    toast('Error: ' + e.message, 'error');
  });
}

function saveDiscussionNote() {
  saveInlineDiscussionNote(window._currentMOMId);
}

function deleteNote(note_id) {
  tracsConfirm('Delete this note?', () => {
    api('api/api_mom.php', {
      action: 'delete_note',
      note_id: note_id
    }).then(r => {
      if(r.ok) {
        toast('Note deleted', 'success');
        momReloadAfterToast();
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast();
    } else {
      momStopInlineSave('decision', mom_id);
      toast(r.msg || 'Failed to add decision', 'error');
    }
  }).catch(e => {
    momStopInlineSave('decision', mom_id);
    toast('Error: ' + e.message, 'error');
  });
}

function saveDecision() {
  saveInlineDecision(window._currentMOMId);
}

function deleteDecision(decision_id) {
  tracsConfirm('Delete this decision?', () => {
    api('api/api_mom.php', {
      action: 'delete_decision',
      decision_id: decision_id
    }).then(r => {
      if(r.ok) {
        toast('Decision deleted', 'success');
        momReloadAfterToast();
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast();
    } else {
      momStopInlineSave('action', mom_id);
      toast(r.msg || 'Failed to save action', 'error');
    }
  }).catch(e => {
    momStopInlineSave('action', mom_id);
    toast('Error: ' + e.message, 'error');
  });
}

function saveActionItem() {
  saveInlineActionItem(window._currentMOMId);
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
      }
      toast(checked ? 'Action completed' : 'Action reopened', 'success');
    }
  }).catch(e => toast('Error: ' + e.message, 'error'));
}

function deleteActionItem(action_id) {
  tracsConfirm('Delete this action item?', () => {
    api('api/api_mom.php', {
      action: 'delete_action_item',
      action_id: action_id
    }).then(r => {
      if(r.ok) {
        toast('Action deleted', 'success');
        momReloadAfterToast();
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
        momReloadAfterToast();
      } else {
        toast(r.msg || 'Failed to create reminder', 'error');
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast();
    } else {
      toast(r.msg || 'Failed to link case', 'error');
    }
  }).catch(e => toast('Error: ' + e.message, 'error'));
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
    momReloadAfterToast();
  }).catch(e => toast('Error: ' + e.message, 'error'));
}

function createCaseFromAction(action_id) {
  tracsConfirm('Create an operational case for this action?', () => {
    api('api/api_mom.php', {
      action: 'create_case_from_action',
      action_id: action_id
    }).then(r => {
      if(r.ok) {
        toast('Case created and linked', 'success');
        momReloadAfterToast(300);
      } else {
        toast(r.msg || 'Failed to create case', 'error');
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
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
      momReloadAfterToast(300);
    } else {
      toast(r.msg || 'Failed to update case', 'error');
    }
  }).catch(e => toast('Error: ' + e.message, 'error'));
}

function uploadMOMScreenshot(mom_id, input) {
  const file = input.files && input.files[0];
  if(!file) return;
  if(!file.type.startsWith('image/')) {
    toast('Screenshot must be an image', 'warning');
    input.value = '';
    return;
  }
  if(file.size > 5 * 1024 * 1024) {
    toast('Screenshot must be under 5MB', 'warning');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    api('api/api_mom.php', {
      action: 'upload_screenshot',
      mom_id,
      image_data: reader.result
    }).then(r => {
      if(r.ok) {
        toast('Screenshot uploaded', 'success');
        momReloadAfterToast(300);
      } else {
        toast(r.msg || 'Failed to upload screenshot', 'error');
      }
    }).catch(e => toast('Error: ' + e.message, 'error'));
  };
  reader.readAsDataURL(file);
}

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
    momReloadAfterToast();
  }).catch(e => toast('Error: ' + e.message, 'error'));
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
