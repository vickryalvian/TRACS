/* TRACS — Global JS v3 */
'use strict';
/* ── BASE API ─────────────────────────────────────────── */
/* ── API CONFIG ───────────────────────────────────────── */
const API_BASE = '/api/';

const API = {

  CASE: {
    CREATE : API_BASE + 'case-create.php',
    UPDATE : API_BASE + 'case-update.php',
    DELETE : API_BASE + 'case-delete.php',
    GET    : API_BASE + 'case-get.php'
  },

  REMINDER: {
    CREATE : API_BASE + 'reminder-create.php',
    UPDATE : API_BASE + 'reminder-update.php',
    DELETE : API_BASE + 'reminder-delete.php',
    TOGGLE : API_BASE + 'reminder-toggle.php',
    GET    : API_BASE + 'reminder-get.php'
  },

  TASK: {
    CREATE : API_BASE + 'task-create.php',
    UPDATE : API_BASE + 'task-update.php',
    DELETE : API_BASE + 'task-delete.php',
    TOGGLE : API_BASE + 'task-toggle.php'
  },

  DOMAIN: {
    CREATE : API_BASE + 'domain-create.php',
    UPDATE : API_BASE + 'domain-update.php',
    DELETE : API_BASE + 'domain-delete.php'
  },

  FINANCE: {
    CREATE : API_BASE + 'finance-create.php',
    DELETE : API_BASE + 'finance-delete.php'
  },

  TICKER: {
    CREATE : API_BASE + 'ticker-create.php',
    DELETE : API_BASE + 'ticker-delete.php',
    LIST   : API_BASE + 'ticker-list.php'
  },

  SHIFT: {
    CREATE : API_BASE + 'shift-create.php',
    UPDATE : API_BASE + 'shift-update.php',
    RESOLVE: API_BASE + 'shift-resolve.php',
    DELETE : API_BASE + 'shift-delete.php',
    LIST   : API_BASE + 'shift-list.php'
  }
};



/* ── Clock ────────────────────────────────────────────── */
function _clock(){
  const el=document.getElementById('tracs-clock');
  if(!el)return;
  const n=new Date(),D=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const p=v=>String(v).padStart(2,'0');
  el.textContent=`${D[n.getDay()]} ${n.getDate()} ${M[n.getMonth()]} ${n.getFullYear()} — ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
}
setInterval(_clock,1000); _clock();

/* ── Toast ────────────────────────────────────────────── */
const _dock=(()=>{const d=document.createElement('div');d.className='toast-dock';document.body.appendChild(d);return d;})();
function toast(msg,type='info',ms=3200){
  const t=document.createElement('div');
  const ic={success:'✓',error:'✕',info:'ℹ'}[type]||'•';
  t.className=`toast ${type}`;
  t.innerHTML=`<span class="toast-icon">${ic}</span><span class="toast-msg">${msg}</span>`;
  _dock.appendChild(t);
  setTimeout(()=>{t.style.animation='t-out .2s var(--ease) forwards';setTimeout(()=>t.remove(),220);},ms);
}

/* ── Modal system ─────────────────────────────────────── */
function openModal(id){const el=document.getElementById(id+'Modal');if(el)el.classList.remove('hidden');}
function closeModal(id){const el=document.getElementById(id+'Modal');if(el)el.classList.add('hidden');}
function closeAllModals(){document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(o=>o.classList.add('hidden'));}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAllModals();});
document.addEventListener('click',e=>{if(e.target.classList.contains('modal-overlay'))closeAllModals();});

/* ── Confirm dialog ───────────────────────────────────── */
let _cb=null;
function confirm(msg,cb,title='Confirm Delete'){
  const m=document.getElementById('confirmModal');if(!m)return;
  document.getElementById('c-title').textContent=title;
  document.getElementById('c-msg').textContent=msg;
  _cb=cb; openModal('confirm');
}
document.addEventListener('DOMContentLoaded',()=>{
  document.getElementById('c-ok')?.addEventListener('click',()=>{closeModal('confirm');if(_cb){_cb();_cb=null;}});
});

/* ── API helper ───────────────────────────────────────── */
async function api(url,data){
  try{
    const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    return await r.json();
  }catch(e){return{success:false,message:'Network error'};}
}

/* ── Cancellation Feedback Handlers ── */
function openNewFeedback() {
    document.getElementById('feedbackId').value = '';
    document.getElementById('feedbackSubmitter').value = '';
    document.getElementById('feedbackEmail').value = '';
    document.getElementById('feedbackService').value = '';
    document.getElementById('feedbackReason').value = '';
    document.getElementById('feedbackReference').value = '';
    document.getElementById('feedbackResolution').value = '';
    document.getElementById('feedbackDetails').value = '';
    document.getElementById('feedbackModalTitle').innerText = 'New Cancellation Feedback';
    openModal('feedback');
}

function openEditFeedback(data) {
    document.getElementById('feedbackId').value = data.id;
    document.getElementById('feedbackSubmitter').value = data.submitter_name;
    document.getElementById('feedbackEmail').value = data.email_address;
    document.getElementById('feedbackService').value = data.cancelled_service;
    document.getElementById('feedbackReason').value = data.cancellation_reason;
    document.getElementById('feedbackReference').value = data.whmcs_reference;
    document.getElementById('feedbackResolution').value = data.payment_resolution;
    document.getElementById('feedbackDetails').value = data.additional_details;
    document.getElementById('feedbackModalTitle').innerText = 'Edit Feedback';
    openModal('feedback');
}

function saveFeedback() {
    const id = document.getElementById('feedbackId').value;
    const url = id ? 'api/feedback-update.php' : 'api/feedback-create.php';
    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('submitter', document.getElementById('feedbackSubmitter').value);
    fd.append('email', document.getElementById('feedbackEmail').value);
    fd.append('service', document.getElementById('feedbackService').value);
    fd.append('reason', document.getElementById('feedbackReason').value);
    fd.append('reference', document.getElementById('feedbackReference').value);
    fd.append('resolution', document.getElementById('feedbackResolution').value);
    fd.append('details', document.getElementById('feedbackDetails').value);

    fetch(url, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert(res.error || 'Save failed');
        }
    });
}

function deleteFeedback(id) {
    if (!confirm('Are you sure you want to delete this feedback record?')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/feedback-delete.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert(res.error || 'Delete failed');
    });
}

function viewFeedback(id) {
    // Basic view: just scroll or highlight for now, or could expand row
    const row = document.querySelector(`tr[data-feedback-id="${id}"]`);
    if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.style.background = 'var(--blue-lt)';
        setTimeout(() => row.style.background = '', 2000);
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Success toast could be added here
        console.log('Copied to clipboard:', text);
    });
}

/* ── Inline Feedback Handlers ── */
function quickSaveFeedback() {
    const fd = new FormData();
    fd.append('submitter', document.getElementById('inSubmitter').value);
    fd.append('service', document.getElementById('inService').value);
    fd.append('reason', document.getElementById('inReason').value);
    fd.append('reference', document.getElementById('inRef').value);
    fd.append('email', document.getElementById('inEmail').value);
    fd.append('resolution', document.getElementById('inResolution').value);
    fd.append('details', document.getElementById('inDetails').value);

    if (!val('inSubmitter') || !val('inService') || !val('inReason')) {
        alert('Submitter, Service, and Reason are required.');
        return;
    }

    fetch('api/feedback-create.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert(res.error || 'Save failed');
        }
    });
}

function clearInlineFeedback() {
    document.getElementById('inSubmitter').value = '';
    document.getElementById('inService').value = '';
    document.getElementById('inReason').value = '';
    document.getElementById('inRef').value = '';
    document.getElementById('inEmail').value = '';
    document.getElementById('inResolution').value = '';
    document.getElementById('inDetails').value = '';
}


/* ── DOM helpers ──────────────────────────────────────── */
function val(id){return document.getElementById(id)?.value||'';}
function setVal(id,v){const el=document.getElementById(id);if(el)el.value=v||'';}
function removeRow(sel){
  const el=document.querySelector(sel);
  if(el){el.style.cssText='opacity:0;transition:opacity .18s';setTimeout(()=>el.remove(),180);}
}
function _reload(){setTimeout(()=>location.reload(),420);}

/* ── CASE CRUD ────────────────────────────────────────── */
function openNewCase(){
  document.getElementById('caseModalTitle').textContent='New Case';
  ['caseId','caseTitle','caseNextCheck','caseNotes'].forEach(id=>setVal(id,''));
  setVal('caseStatus','active'); setVal('casePriority','medium');
  openModal('case');
}
function openEditCase(id){
  const row=document.querySelector(`[data-cid="${id}"]`);
  if(!row)return;
  document.getElementById('caseModalTitle').textContent='Edit Case';
  setVal('caseId',id);
  setVal('caseTitle',row.dataset.title||'');
  setVal('caseStatus',row.dataset.status||'active');
  setVal('casePriority',row.dataset.priority||'medium');
  setVal('caseNextCheck',row.dataset.next||'');
  setVal('caseNotes',row.dataset.notes||'');
  openModal('case');
}
async function saveCase(){
  const title=val('caseTitle').trim();
  if(!title){toast('Case title is required','error');return;}
  const id=val('caseId');
  const d=await api(id?API.CASE.UPDATE:API.CASE.CREATE,{
    id,title,status:val('caseStatus'),priority:val('casePriority'),
    next_check_at:val('caseNextCheck'),notes:val('caseNotes')
  });
  if(d.success){toast(id?'Case updated':'Case created','success');closeModal('case');_reload();}
  else toast(d.message||'Error saving case','error');
}
async function deleteCase(id){
  confirm('Delete this case permanently? This cannot be undone.',async()=>{
    const d=await api(API.CASE.DELETE,{id});
    if(d.success){toast('Case deleted','success');removeRow(`[data-cid="${id}"]`);}
    else toast(d.message||'Error','error');
  });
}

/* ── REMINDER CRUD ────────────────────────────────────── */
function openNewReminder(){
  document.getElementById('remModalTitle').textContent='New Reminder';
  ['remId','remTitle','remDue','remDesc'].forEach(id=>setVal(id,''));
  setVal('remPriority','medium');
  openModal('rem');
}
function openEditReminder(id){
  const row=document.querySelector(`[data-rid="${id}"]`);
  if(!row)return;
  document.getElementById('remModalTitle').textContent='Edit Reminder';
  setVal('remId',id);
  setVal('remTitle',row.dataset.title||'');
  setVal('remPriority',row.dataset.priority||'medium');
  setVal('remDue',row.dataset.due||'');
  setVal('remDesc',row.dataset.desc||'');
  openModal('rem');
}
async function saveReminder(){
  const title=val('remTitle').trim(),due=val('remDue');
  if(!title){toast('Title is required','error');return;}
  if(!due){toast('Due date is required','error');return;}
  const id=val('remId');
  const d=await api(id?API.REMINDER.UPDATE:API.REMINDER.CREATE,{
    id,title,priority:val('remPriority'),due_date:due,description:val('remDesc')
  });
  if(d.success){toast(id?'Reminder updated':'Reminder created','success');closeModal('rem');_reload();}
  else toast(d.message||'Error','error');
}
async function deleteReminder(id){
  confirm('Delete this reminder?',async()=>{
    const d=await api(API.REMINDER.DELETE,{id});
    if(d.success){toast('Reminder deleted','success');removeRow(`[data-rid="${id}"]`);}
    else toast(d.message||'Error','error');
  });
}
async function toggleReminder(id,checked){
  const d=await api(API.REMINDER.TOGGLE,{id,is_completed:checked?1:0});
  if(d.success){
    const row=document.querySelector(`[data-rid="${id}"]`);
    row?.querySelector('.rem-title')?.classList.toggle('done',checked);
  }else toast('Error updating reminder','error');
}

/* ── TASK CRUD ────────────────────────────────────────── */
function openNewTask(){
  document.getElementById('taskModalTitle').textContent='New Task';
  ['taskId','taskTitle','taskDesc'].forEach(id=>setVal(id,''));
  openModal('task');
}
function openEditTask(id){
  const row=document.querySelector(`[data-tid="${id}"]`);
  if(!row)return;
  document.getElementById('taskModalTitle').textContent='Edit Task';
  setVal('taskId',id);
  setVal('taskTitle',row.dataset.title||'');
  setVal('taskDesc',row.dataset.desc||'');
  openModal('task');
}
async function saveTask(){
  const title=val('taskTitle').trim();
  if(!title){toast('Task title is required','error');return;}
  const id=val('taskId');
  const d=await api(id?API.TASK.UPDATE:API.TASK.CREATE,{
    id,title,description:val('taskDesc')
  });
  if(d.success){toast(id?'Task updated':'Task created','success');closeModal('task');_reload();}
  else toast(d.message||'Error','error');
}
async function deleteTask(id){
  confirm('Delete this task?',async()=>{
    const d=await api(API.TASK.DELETE,{id});
    if(d.success){toast('Task deleted','success');removeRow(`[data-tid="${id}"]`);_updateProgress();}
    else toast(d.message||'Error','error');
  });
}
async function toggleTask(id,checked){
  const d=await api(API.TASK.TOGGLE,{id,is_completed:checked?1:0});
  if(d.success){
    const row=document.querySelector(`[data-tid="${id}"]`);
    row?.querySelector('.task-title')?.classList.toggle('done',checked);
    _updateProgress();
  }else toast('Error updating task','error');
}
function _updateProgress(){
  const boxes=document.querySelectorAll('.task-chk');
  const total=boxes.length,done=[...boxes].filter(b=>b.checked).length;
  const pct=total?Math.round(done/total*100):0;
  const fill=document.getElementById('prog-fill');
  const lbl=document.getElementById('prog-lbl');
  const pctlbl=document.getElementById('prog-pct');
  if(fill)fill.style.width=pct+'%';
  if(lbl)lbl.textContent=`${done} / ${total}`;
  if(pctlbl)pctlbl.textContent=pct+'%';

  // Update Notification Badge
  const pending = total - done;
  const badgeContainer = document.getElementById('notif-badge-container');
  if (badgeContainer) {
    if (pending > 0) {
      badgeContainer.innerHTML = `<span class="bell-badge">${pending}</span>`;
    } else {
      badgeContainer.innerHTML = '';
    }
  }
}

/* ── TICKER CRUD ──────────────────────────────────────── */
async function addTickerMsg(){
  const text=val('newTickerText').trim(),cls=val('newTickerCls');
  if(!text){toast('Message text is required','error');return;}
  const d=await api(API.TICKER.CREATE,{text,class:cls});
  if(d.success){toast('Message added','success');setVal('newTickerText','');_reload();}
  else toast(d.message||'Error','error');
}
async function archiveTickerMsg(id){

  confirm(
    'Archive this announcement?',
    async()=>{

      const d = await api(
        API.TICKER.DELETE,
        {
          id,
          is_archived: 1
        }
      );

      if(d.success){

        toast('Announcement archived','success');

        removeRow(`#tmgr-${id}`);

      } else {

        toast(d.message || 'Error','error');
      }

    },
    'Archive Message'
  );
}

/* ── SHIFT REPORT CRUD ────────────────────────────────── */
function openNewShiftReport(){
  document.getElementById('shiftModalTitle').textContent='New Shift Report';
  ['shiftId','shiftTitle','shiftDetails'].forEach(id=>setVal(id,''));
  setVal('shiftPriority','medium');
  setVal('shiftDate', new Date().toISOString().split('T')[0]);
  openModal('shift');
}
function openEditShiftReport(id){
  const row=document.querySelector(`[data-id="${id}"]`);
  if(!row)return;
  document.getElementById('shiftModalTitle').textContent='Edit Shift Report';
  setVal('shiftId',id);
  setVal('shiftTitle',row.dataset.title||'');
  setVal('shiftName',row.dataset.shift||'Shift 1');
  setVal('shiftPriority',row.dataset.prio||'medium');
  setVal('shiftDate', row.dataset.date || new Date().toISOString().split('T')[0]);
  setVal('shiftDetails',row.dataset.details||'');
  openModal('shift');
}
async function saveShiftReport(){
  const title=val('shiftTitle').trim();
  if(!title){toast('Title is required','error');return;}
  const id=val('shiftId');
  const d=await api(id?API.SHIFT.UPDATE:API.SHIFT.CREATE,{
    id,
    title,
    shift_name:val('shiftName'),
    priority:val('shiftPriority'),
    details:val('shiftDetails'),
    active_date:val('shiftDate')
  });
  if(d.success){toast(id?'Report updated':'Report created','success');closeModal('shift');_reload();}
  else toast(d.message||'Error','error');
}
async function resolveShiftReport(id){
  confirm('Mark this shift report as resolved?',async()=>{
    const d=await api(API.SHIFT.RESOLVE,{id});
    if(d.success){toast('Report resolved','success');_reload();}
    else toast(d.message||'Error','error');
  });
}
async function deleteShiftReport(id){
  confirm('Delete this shift report?',async()=>{
    const d=await api(API.SHIFT.DELETE,{id});
    if(d.success){toast('Report deleted','success');removeRow(`[data-id="${id}"]`);}
    else toast(d.message||'Error','error');
  });
}


/* ── Client-side search ───────────────────────────────── */
function liveSearch(inputEl,rowSel,textSel){
  const q=(inputEl?.value||'').toLowerCase();
  document.querySelectorAll(rowSel).forEach(row=>{
    const t=(row.querySelector(textSel)?.textContent||'').toLowerCase();
    row.style.display=t.includes(q)?'':'none';
  });
}

async function convertCurrency() {

  const from = document.getElementById('currency-from')?.value;
  const to = document.getElementById('currency-to')?.value;
  const amount = document.getElementById('currency-amount')?.value;

  if (!from || !to || !amount) {
    console.warn("Missing input", { from, to, amount });
    return;
  }

  const url = `/api/currency.php?from=${from}&to=${to}&amount=${amount}`;

  try {

    const res = await fetch(url);

    const text = await res.text();
    console.log("RAW RESPONSE:", text);

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("JSON PARSE ERROR:", text);
      return;
    }

    console.log("PARSED DATA:", data);

    if (!data.success) {
      console.error("API FAILED:", data.message);
      return;
    }

    const result = parseFloat(data.result);

    if (isNaN(result)) {
      console.error("RESULT NaN DETECTED:", data);
      return;
    }

    document.getElementById('currency-result').textContent =
      result.toLocaleString();

    document.getElementById('currency-rate').textContent =
      `1 ${from} = ${data.rate} ${to}`;

    document.getElementById('currency-time').textContent =
      data.time;

  } catch (err) {
    console.error("FETCH ERROR:", err);
  }
}

function setOpsModalMode(isEdit) {
  const title = document.getElementById('ops-modal-title');
  const sub = document.getElementById('ops-modal-sub');
  const archiveBtn = document.getElementById('ops-archive-btn');
  const newBtn = document.getElementById('ops-new-btn');
  const saveBtn = document.getElementById('ops-save-btn');

  if (title) title.textContent = isEdit ? 'Edit Operational Status' : 'Add Operational Status';
  if (sub) sub.textContent = isEdit ? 'Update live ops information' : 'Create a new live ops item';
  if (archiveBtn) archiveBtn.classList.toggle('hidden', !isEdit);
  if (newBtn) newBtn.classList.toggle('hidden', !isEdit);
  if (saveBtn) saveBtn.textContent = isEdit ? 'Save' : 'Add';
}

function openNewOpsStatus(evt) {
  evt?.preventDefault?.();
  evt?.stopPropagation?.();

  setVal('ops-id', '');
  setVal('ops-message', '');
  setVal('ops-severity', 'info');
  setOpsModalMode(false);
  openModal('ops');

  setTimeout(() => document.getElementById('ops-message')?.focus(), 0);
}

function openOpsModal(id = '', message = '', severity = 'info') {

  const isEdit = id !== '' && id !== null && id !== undefined;

  document.getElementById('ops-id').value = id;
  document.getElementById('ops-message').value = message;
  document.getElementById('ops-severity').value = severity;
  setOpsModalMode(isEdit);

  openModal('ops');
  setTimeout(() => document.getElementById('ops-message')?.focus(), 0);
}

function closeOpsModal() {
  closeModal('ops');
}

async function saveOpsStatus() {

  const message = document.getElementById('ops-message').value.trim();

  if (!message) {
    toast('Ops message is required', 'error');
    return;
  }

  try {

    const data = await api(API_BASE + 'ops-status.php', {
      action: 'save',
      id: document.getElementById('ops-id').value,
      message,
      severity: document.getElementById('ops-severity').value
    });

    if (!data.success) {
      toast(data.message || 'Failed saving ops status', 'error');
      return;
    }

    toast('Ops status saved', 'success');
    location.reload();

  } catch (err) {

    console.error(err);

    toast('Failed saving ops status', 'error');
  }
}

async function archiveOpsStatus() {

  const id =
    document.getElementById('ops-id').value;

  if (!id) {
    toast('Invalid ops status', 'error');
    return;
  }

  try {

    const data = await api(API_BASE + 'ops-status.php', {
      action: 'archive',
      id
    });

    if (!data.success) {
      toast(data.message || 'Failed archiving ops status', 'error');
      return;
    }

    toast('Ops status archived', 'success');
    location.reload();

  } catch (err) {

    console.error(err);

    toast('Failed archiving ops status', 'error');
  }
}

function bindOpsStatusControls() {
  document.getElementById('ops-close-btn')?.addEventListener('click', closeOpsModal);
  document.getElementById('ops-new-btn')?.addEventListener('click', openNewOpsStatus);
  document.getElementById('ops-archive-btn')?.addEventListener('click', archiveOpsStatus);
  document.getElementById('ops-save-btn')?.addEventListener('click', saveOpsStatus);
}

window.openNewOpsStatus = openNewOpsStatus;
window.openOpsModal = openOpsModal;
window.closeOpsModal = closeOpsModal;
window.saveOpsStatus = saveOpsStatus;
window.archiveOpsStatus = archiveOpsStatus;

let activeQuickTimeInput = null;

function bindQuickDatetimeInputs() {

  document
    .querySelectorAll('.quick-datetime')
    .forEach(input => {

      input.addEventListener('focus', () => {
        activeQuickTimeInput = input;
      });

      input.addEventListener('click', () => {
        activeQuickTimeInput = input;
      });
    });

  document.addEventListener('click', e => {
    const btn = e.target.closest?.('.quick-time-btn');
    if (!btn) return;
    const group = btn.closest('.form-group') || btn.closest('.fb-inline-col');
    if (!group) return;
    
    // Find either the original input or the flatpickr-enabled input in this group
    const input = group.querySelector('.quick-datetime') || group.querySelector('.split-date');
    if (input) activeQuickTimeInput = input;
  }, true);
}

function setQuickTime(type) {

  const input = activeQuickTimeInput;

  if (!input) return;

  const now = new Date();

  switch(type){

    case '1h':
      now.setHours(now.getHours() + 1);
      break;

    case '2h':
      now.setHours(now.getHours() + 2);
      break;

    case '4h':
      now.setHours(now.getHours() + 4);
      break;

    case '1d':
      now.setDate(now.getDate() + 1);
      break;

    case '3d':
      now.setDate(now.getDate() + 3);
      break;

    case '1w':
      now.setDate(now.getDate() + 7);
      break;

    case '1m':
      now.setMonth(now.getMonth() + 1);
      break;
  }

  const pad = n => String(n).padStart(2,'0');
  const dVal = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
  const tVal = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
  const fullVal = `${dVal}T${tVal}`;

  // If we are targeting a split input, find the sibling or the sync target
  if (input.classList.contains('split-date') || input.classList.contains('split-time')) {
    const targetId = input.getAttribute('data-sync');
    const mainInput = document.getElementById(targetId);
    const dateInp = document.querySelector(`[data-sync="${targetId}"].split-date`);
    const timeInp = document.querySelector(`[data-sync="${targetId}"].split-time`);

    if (dateInp && dateInp._flatpickr) dateInp._flatpickr.setDate(dVal, true);
    if (timeInp && timeInp._flatpickr) timeInp._flatpickr.setDate(tVal, true);
    if (mainInput) mainInput.value = fullVal;
    return;
  }

  if (input._flatpickr) {
    const dateVal = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
    input._flatpickr.setDate(input.type === 'date' ? dateVal : now, true);
  } else {
    input.value = fullVal;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
}

const email = document.querySelector('input[name="email"]');
const password = document.querySelector('input[name="password"]');
const btnLogin = document.querySelector('.btn-login');

function checkForm() {

  if (!email || !password || !btnLogin) return;

  if (email.value.trim() && password.value.trim()) {

    btnLogin.classList.add('is-active');

  } else {

    btnLogin.classList.remove('is-active');
  }
}

/* LOGIN FORM SAFE BIND */

if (email && password && btnLogin) {

  email.addEventListener('input', checkForm);
  password.addEventListener('input', checkForm);

  checkForm();
}

/* ───────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {

  bindQuickDatetimeInputs();
  bindOpsStatusControls();

  /* ── Currency Converter ───────────────────── */

  document.getElementById('convert-btn')
    ?.addEventListener('click', convertCurrency);

  document.getElementById('swap-currency')
    ?.addEventListener('click', () => {

      const from = document.getElementById('currency-from');
      const to = document.getElementById('currency-to');

      if (!from || !to) return;

      [from.value, to.value] = [to.value, from.value];

      convertCurrency();
    });

  if (document.getElementById('currency-result')) {
    convertCurrency();
  }

  /* ── Login Button Loading ─────────────────── */

  const form = document.querySelector('form');

  if (form && btnLogin) {

    form.addEventListener('submit', () => {
      btnLogin.classList.add('loading');
    });
  }

  /* ── OPS STATUS SLIDER ────────────────────── */

  const opsTrack = document.getElementById('opsTrack');
  const opsItems = document.querySelectorAll('.ops-item');

  let opsIndex = 0;
  let opsAnimating = false;

  function updateOpsSlider() {

    if (!opsTrack || !opsItems.length) return;

    opsAnimating = true;

    opsItems.forEach((item, index) => {

      item.classList.remove('active');

      if (index === opsIndex) {

        setTimeout(() => {
          item.classList.add('active');
        }, 120);
      }
    });

    opsTrack.style.transform =
      `translateX(-${opsIndex * 100}%)`;

    setTimeout(() => {
      opsAnimating = false;
    }, 500);
  }

  function nextOps() {

    if (!opsItems.length || opsAnimating) return;

    opsIndex =
      (opsIndex + 1) % opsItems.length;

    updateOpsSlider();
  }

  function prevOps() {

    if (!opsItems.length || opsAnimating) return;

    opsIndex =
      (opsIndex - 1 + opsItems.length) % opsItems.length;

    updateOpsSlider();
  }

  document.getElementById('opsNext')
    ?.addEventListener('click', nextOps);

  document.getElementById('opsPrev')
    ?.addEventListener('click', prevOps);

  if (opsItems.length > 0) {

    opsItems[0].classList.add('active');

    updateOpsSlider();

    if (opsItems.length > 1) {
      setInterval(nextOps, 6500);
    }
  }

});

/* ═══════════════════════════════════════════════════════════════
   DOMAIN TRANSFERS — Page Functions
   (Safe to load on any page — guarded by element checks)
═══════════════════════════════════════════════════════════════ */

/* Register DT endpoints (POST to self, so uses pathname) */
API.DT = {
  SAVE: window.location.pathname
};

/* Status badge class map (mirrors PHP dt_status_class) */
const DT_STATUS_CLASS = {
  'pending transfer'    : 'dt-status-pending',
  'locked'              : 'dt-status-locked',
  'error epp code'      : 'dt-status-error',
  'move domain'         : 'dt-status-move',
  'done'                : 'dt-status-done',
  'cancelled'           : 'dt-status-cancelled',
  'retransferred'       : 'dt-status-retransferred',
  'transferred away'    : 'dt-status-away',
  'pending verification': 'dt-status-verify',
  'renew period'        : 'dt-status-renew',
};
const DT_STATUS_LABEL = {
  'pending transfer'    : 'Pending Transfer',
  'locked'              : 'Locked',
  'error epp code'      : 'Error EPP Code',
  'move domain'         : 'Move Domain',
  'done'                : 'Done',
  'cancelled'           : 'Cancelled',
  'retransferred'       : 'Retransferred',
  'transferred away'    : 'Transferred Away',
  'pending verification': 'Pending Verification',
  'renew period'        : 'Renew Period',
};

/* Inline row: status update */
async function quickStatusUpdate(id, selectEl) {
  const newStatus = selectEl.value;
  const badge     = document.getElementById('dt-status-badge-' + id);
  const prevOpt   = [...selectEl.options].find(o => o.defaultSelected);
  const prevStatus = prevOpt ? prevOpt.value : null;

  /* Optimistic UI */
  if (badge) {
    badge.className  = 'dt-status ' + (DT_STATUS_CLASS[newStatus] || '');
    badge.textContent = DT_STATUS_LABEL[newStatus] || newStatus;
  }

  const d = await api(window.location.pathname, {
    action: 'quick_status', id, transfer_status: newStatus
  });

  if (d.success) {
    toast('Status updated', 'success');
    [...selectEl.options].forEach(o => { o.defaultSelected = (o.value === newStatus); });
  } else {
    toast(d.message || 'Error updating status', 'error');
    if (badge && prevStatus) {
      badge.className  = 'dt-status ' + (DT_STATUS_CLASS[prevStatus] || '');
      badge.textContent = DT_STATUS_LABEL[prevStatus] || prevStatus;
      selectEl.value   = prevStatus;
    }
  }
}

/* Inline row: move domain update */
async function quickMoveUpdate(id, selectEl) {
  const newVal  = selectEl.value;
  const prevOpt = [...selectEl.options].find(o => o.defaultSelected);
  const prevVal = prevOpt ? prevOpt.value : '';

  selectEl.classList.toggle('has-value', !!newVal);

  const d = await api(window.location.pathname, {
    action: 'quick_move', id, move_domain: newVal
  });

  if (d.success) {
    toast(newVal ? 'Move domain: ' + newVal : 'Move domain cleared', 'success');
    [...selectEl.options].forEach(o => { o.defaultSelected = (o.value === newVal); });
  } else {
    toast(d.message || 'Error updating', 'error');
    selectEl.value = prevVal;
    selectEl.classList.toggle('has-value', !!prevVal);
  }
}

/* Inline row: end date update */
async function quickEndDateUpdate(id, inputEl) {
  const newVal  = inputEl.value;
  const prevVal = inputEl.dataset.prev || '';

  inputEl.classList.add('saving');

  const d = await api(window.location.pathname, {
    action: 'quick_end_date', id, process_end_date: newVal || null
  });

  inputEl.classList.remove('saving');

  if (d.success) {
    inputEl.classList.toggle('has-value', !!newVal);
    inputEl.dataset.prev = newVal;
    toast(newVal ? 'End date set' : 'End date cleared', 'success');
  } else {
    toast(d.message || 'Error updating end date', 'error');
    inputEl.value = prevVal;
    inputEl.classList.toggle('has-value', !!prevVal);
  }
}

/* Inline Quick-Save (New Domain Transfer) */
async function quickSaveDt() {
  const domain = val('nDomain').trim();
  if (!domain) { toast('Domain name is required', 'error'); document.getElementById('nDomain').focus(); return; }

  const btn = document.querySelector('.dt-save-btn');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  const d = await api(window.location.pathname, {
    action:                    'create',
    domain_name:               domain,
    transfer_status:           val('nStatus'),
    process_start_date:        val('nStartDate') || null,
    process_end_date:          val('nEndDate')   || null,
    webnic_reseller_transfer:  val('nWebnic').trim() || null,
    notes:                     null,
  });

  if (btn) {
    btn.disabled = false;
    btn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg> Save';
  }

  if (d.success) {
    toast('Domain transfer recorded', 'success');
    ['nDomain','nStartDate','nEndDate','nWebnic'].forEach(id => setVal(id, ''));
    setVal('nStatus', 'pending transfer');
    document.getElementById('nDomain').focus();
    _reload();
  } else {
    toast(d.message || 'Error saving transfer', 'error');
  }
}

/* Open Edit modal (Domain Transfer) */
function openEditDt(data) {
  setVal('dtId',       data.id);
  setVal('dtDomain',   data.domain_name);
  setVal('dtStatus',   data.transfer_status);
  setVal('dtStartDate',data.process_start_date || '');
  setVal('dtEndDate',  data.process_end_date   || '');
  setVal('dtWebnic',   data.webnic_reseller_transfer || '');
  const ta = document.getElementById('dtNotes');
  if (ta) ta.value = data.notes || '';
  openModal('dt');
}

/* Save Edit (Update Domain Transfer) */
async function saveEditDt() {
  const id     = val('dtId');
  const domain = val('dtDomain').trim();

  if (!id)     { toast('Invalid record', 'error'); return; }
  if (!domain) { toast('Domain name is required', 'error'); return; }

  const ta = document.getElementById('dtNotes');
  const notes = ta ? ta.value.trim() : '';

  const d = await api(window.location.pathname, {
    action:                   'update',
    id,
    domain_name:              domain,
    transfer_status:          val('dtStatus'),
    process_start_date:       val('dtStartDate') || null,
    process_end_date:         val('dtEndDate')   || null,
    webnic_reseller_transfer: val('dtWebnic').trim() || null,
    notes:                    notes || null,
  });

  if (d.success) {
    toast('Transfer updated', 'success');
    closeModal('dt');
    _reload();
  } else {
    toast(d.message || 'Error updating transfer', 'error');
  }
}

/* Delete Domain Transfer */
function deleteDt(id) {
  confirm('Delete this domain transfer record? This cannot be undone.', async () => {
    const d = await api(window.location.pathname, { action: 'delete', id });
    if (d.success) {
      toast('Transfer deleted', 'success');
      removeRow(`[data-dt-id="${id}"]`);
    } else {
      toast(d.message || 'Error deleting transfer', 'error');
    }
  });
}

/* ═══════════════════════════════════════════════════════════════
   FINANCE — Balance Transfer Functions
═══════════════════════════════════════════════════════════════ */

API.BT = {
  CREATE : API_BASE + 'bt-create.php',
  UPDATE : API_BASE + 'bt-update.php',
  DELETE : API_BASE + 'bt-delete.php'
};

/* Set default datetime in inline form (only if on finance page) */
(function(){
  const nDate = document.getElementById('nDate');
  if (nDate) nDate.value = new Date().toISOString().slice(0, 16);
})();

/* Inline Quick-Save (New Balance Transfer) */
async function quickSaveBt() {
  const amount      = parseFloat(val('nAmount')) || 0;
  const admin_name  = val('nAdmin').trim();
  const sender_type = val('nSenderType');
  const receiver_type = val('nReceiverType');

  if (!amount || amount <= 0) { toast('Amount is required', 'error'); document.getElementById('nAmount').focus(); return; }
  if (!admin_name)            { toast('Admin name is required', 'error'); document.getElementById('nAdmin').focus(); return; }

  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const sender_email    = val('nSenderEmail').trim();
  const receiver_email  = val('nReceiverEmail').trim();
  if (sender_email   && !emailRx.test(sender_email))   { toast('Invalid sender email', 'error');   return; }
  if (receiver_email && !emailRx.test(receiver_email)) { toast('Invalid receiver email', 'error'); return; }

  const transfer_date = val('nDate') || new Date().toISOString().slice(0, 16);

  const btn = document.querySelector('.bt-save-btn');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  const d = await api(API.BT.CREATE, {
    sender_email:     sender_email    || '',
    sender_user_id:   val('nSenderUid').trim()    || '',
    sender_type,
    receiver_email:   receiver_email  || '',
    receiver_user_id: val('nReceiverUid').trim()  || '',
    receiver_type,
    amount,
    status:           val('nStatus'),
    admin_name,
    ticket_id:        val('nTicket').trim() || null,
    transfer_date
  });

  if (btn) { btn.disabled = false; btn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg> Save'; }

  if (d.success) {
    toast('Transfer recorded', 'success');
    ['nSenderEmail','nSenderUid','nReceiverEmail','nReceiverUid','nAmount','nTicket'].forEach(id => setVal(id, ''));
    setVal('nDate', new Date().toISOString().slice(0, 16));
    setVal('nStatus', 'pending');
    document.getElementById('nAmount').focus();
    _reload();
  } else {
    toast(d.message || 'Error saving transfer', 'error');
  }
}

/* Open Edit modal (Balance Transfer) */
function openEditBt(data) {
  setVal('btId',            data.id);
  setVal('btDate',          data.transfer_date);
  setVal('btSenderEmail',   data.sender_email);
  setVal('btSenderUid',     data.sender_user_id);
  setVal('btSenderType',    data.sender_type);
  setVal('btReceiverEmail', data.receiver_email);
  setVal('btReceiverUid',   data.receiver_user_id);
  setVal('btReceiverType',  data.receiver_type);
  setVal('btAmount',        data.amount);
  setVal('btStatus',        data.status);
  setVal('btAdmin',         data.admin_name);
  setVal('btTicket',        data.ticket_id || '');
  openModal('bt');
}

/* Save Edit (Update Balance Transfer) */
async function saveEditBt() {
  const id         = val('btId');
  const amount     = parseFloat(val('btAmount')) || 0;
  const admin_name = val('btAdmin').trim();

  if (!id)                    { toast('Invalid record', 'error'); return; }
  if (!amount || amount <= 0) { toast('Amount is required', 'error'); return; }
  if (!admin_name)            { toast('Admin name is required', 'error'); return; }

  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const sender_email   = val('btSenderEmail').trim();
  const receiver_email = val('btReceiverEmail').trim();
  if (sender_email   && !emailRx.test(sender_email))   { toast('Invalid sender email', 'error');   return; }
  if (receiver_email && !emailRx.test(receiver_email)) { toast('Invalid receiver email', 'error'); return; }

  const d = await api(API.BT.UPDATE, {
    id,
    transfer_date:    val('btDate'),
    sender_email:     sender_email   || '',
    sender_user_id:   val('btSenderUid').trim()   || '',
    sender_type:      val('btSenderType'),
    receiver_email:   receiver_email || '',
    receiver_user_id: val('btReceiverUid').trim() || '',
    receiver_type:    val('btReceiverType'),
    amount,
    status:           val('btStatus'),
    admin_name,
    ticket_id:        val('btTicket').trim() || null
  });

  if (d.success) {
    toast('Transfer updated', 'success');
    closeModal('bt');
    _reload();
  } else {
    toast(d.message || 'Error updating transfer', 'error');
  }
}

/* Delete Balance Transfer */
function deleteBt(id) {
  confirm('Delete this transfer record? This cannot be undone.', async () => {
    const d = await api(API.BT.DELETE, { id });
    if (d.success) {
      toast('Transfer deleted', 'success');
      removeRow(`[data-bt-id="${id}"]`);
    } else {
      toast(d.message || 'Error deleting transfer', 'error');
    }
  });
}

/* ═══════════════════════════════════════════════════════════════
   ACTIVITY LOG — Page Functions
═══════════════════════════════════════════════════════════════ */

function filterActs(inp) {
  const q = inp.value.toLowerCase();
  document.querySelectorAll('[data-act-row]').forEach(row => {
    const t = row.querySelector('.act-desc')?.textContent.toLowerCase() || '';
    const a = row.querySelector('.act-text')?.textContent.toLowerCase() || '';
    row.style.display = (t.includes(q) || a.includes(q)) ? '' : 'none';
  });
}

/* ═══════════════════════════════════════════════════════════════
   THEME TOGGLE
═══════════════════════════════════════════════════════════════ */

function tracsToggleTheme() {
  var html = document.documentElement;
  var current = html.getAttribute('data-theme') || 'light';
  var next = current === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('tracs-theme', next);
  var tip = document.getElementById('themeTip');
  if (tip) tip.textContent = next === 'dark' ? 'Switch to Light' : 'Switch to Dark';
}
/* Sync tip text on load */
(function(){
  var tip = document.getElementById('themeTip');
  var t = localStorage.getItem('tracs-theme') || 'light';
  if (tip) tip.textContent = t === 'dark' ? 'Switch to Light' : 'Switch to Dark';
})();

/* ── Calendar Initialization (Flatpickr) ── */
document.addEventListener('DOMContentLoaded', () => {
  // Only init visible inputs, avoid hidden master inputs
  document.querySelectorAll('.form-input[type="date"], .form-input[type="datetime-local"], .form-input[type="time"], .dt-date-input').forEach(el => {
    const isDateTime = el.type === 'datetime-local' || el.classList.contains('quick-datetime');
    const isTimeOnly = el.type === 'time' || el.classList.contains('split-time');
    const isDateOnly = el.type === 'date' || el.classList.contains('split-date');

    // Default to 'Now' for new split inputs if empty
    let defDate = null;
    if ((isDateOnly || isTimeOnly) && !el.value) {
      defDate = new Date();
    }

    // Combine original classes with flatpickr classes
    const originalClasses = Array.from(el.classList).filter(c => c !== 'form-input' && c !== 'flatpickr-input');
    const altClass = 'form-input flatpickr-alt-input ' + originalClasses.join(' ');

    flatpickr(el, {
      disableMobile: "true",
      enableTime: isDateTime || isTimeOnly,
      noCalendar: isTimeOnly,
      time_24hr: true,
      dateFormat: isTimeOnly ? "H:i" : (isDateTime ? "Y-m-d H:i" : "Y-m-d"),
      altInput: true,
      altFormat: isTimeOnly ? "H:i" : (isDateTime ? "d/m/Y H:i" : "d/m/Y"),
      allowInput: true,
      clickOpens: false,
      altInputClass: altClass,
      placeholder: isTimeOnly ? "HH:MM" : (isDateTime ? "DD/MM/YYYY --:--" : "DD/MM/YYYY"),
      defaultDate: defDate,
      minDate: "today",
      onOpen: function(selectedDates, dateStr, instance) {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        instance.calendarContainer.classList.add('tracs-flatpickr-' + theme);
      },
      onChange: function(selectedDates, dateStr, instance) {
        instance.element.value = dateStr;
        
        // Handle split field synchronization
        const syncId = instance.element.getAttribute('data-sync');
        if (syncId) {
          const mainInput = document.getElementById(syncId);
          const dateEl = document.querySelector(`[data-sync="${syncId}"].split-date`);
          const timeEl = document.querySelector(`[data-sync="${syncId}"].split-time`);
          if (mainInput && dateEl && timeEl) {
            mainInput.value = `${dateEl.value}T${timeEl.value}`;
            mainInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
        } else {
          instance.element.dispatchEvent(new Event('change', { bubbles: true }));
        }
      },
      onDayCreate: function(dObj, dStr, fp, dayElem) {
        if (dayElem.dateObj < new Date().setHours(0,0,0,0)) {
          dayElem.classList.add("past-day");
        }
      },
      onReady: function(selectedDates, dateStr, instance) {
        const target = instance.altInput || instance.element;
        target.addEventListener('mousedown', (e) => {
          const rect = target.getBoundingClientRect();
          const x = e.clientX - rect.left;
          if (x > rect.width - 35) { instance.open(); }
        });
        if (instance.altInput) {
          instance.altInput.addEventListener('focus', () => { activeQuickTimeInput = instance.element; });
        }
      }
    });
  });

  // --- Shift Greeting Slider ---
  const slider = document.getElementById('shift-slider');
  const greetingEl = document.getElementById('shift-greeting');
  const infoEl = document.querySelector('.shift-info');
  
  if (slider && greetingEl && infoEl) {
    function updateGreeting() {
      const hour = new Date().getHours();
      let g = "Good Night";
      if (hour >= 5 && hour < 12) g = "Good Morning";
      else if (hour >= 12 && hour < 17) g = "Good Afternoon";
      else if (hour >= 17 && hour < 21) g = "Good Evening";
      greetingEl.textContent = g;
    }
    
    function syncWidth() {
      const activeSlide = slider.classList.contains('show-greeting') ? greetingEl : slider.firstElementChild;
      // Temporarily remove overflow and fixed width to measure
      const originalWidth = infoEl.style.width;
      infoEl.style.width = 'auto';
      const targetWidth = activeSlide.scrollWidth + 4; // +4 for breathing room
      infoEl.style.width = originalWidth;
      
      // Force a reflow
      infoEl.offsetHeight; 
      infoEl.style.width = targetWidth + 'px';
    }

    updateGreeting();
    setTimeout(syncWidth, 100); // Initial sync

    setInterval(() => {
      updateGreeting();
      slider.classList.toggle('show-greeting');
      syncWidth();
    }, 5000);
  }
});
