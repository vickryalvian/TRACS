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
  }
  
};

console.log('API BASE:', API_BASE);
console.log('TRACS JS LOADED OK');

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
    if(d.success){toast('Task deleted','success');removeRow(`[data-tid="${id}"]`);}
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
    const input = btn.closest('.form-group')?.querySelector('.quick-datetime');
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

  input.value =
    `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

const email = document.querySelector('input[name="email"]');
const password = document.querySelector('input[name="password"]');
const btn = document.querySelector('.btn-login');

function checkForm() {
  if (email.value.trim() && password.value.trim()) {
    btn.classList.add('is-active');
  } else {
    btn.classList.remove('is-active');
  }
}

email.addEventListener('input', checkForm);
password.addEventListener('input', checkForm);

// initial state
checkForm();

document.addEventListener('DOMContentLoaded', () => {

  bindQuickDatetimeInputs();
  bindOpsStatusControls();

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

const btn = document.querySelector('.btn-login');
const form = document.querySelector('form');

form.addEventListener('submit', () => {
  btn.classList.add('loading');
});

  convertCurrency();

  window.addEventListener('load', () => {
  if (document.getElementById('currency-result')) {
    convertCurrency();
  }
});

/* ── OPS STATUS ───────────────────────────────────── */

/* ── OPS STATUS ───────────────────────────── */

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

if (opsItems.length > 1) {

  opsItems[0].classList.add('active');

  setInterval(nextOps, 6500);
}
  
});
