/*! TRACS — Global JS v3 | Initial Build by Vickry */
'use strict';

function tracsLogBuildSignature() {
  if (window.__TRACS_SIGNATURE_LOGGED__) return;
  const build = window.TRACS_BUILD_INFO || {};
  const owner = build.owner || 'Vickry';
  const version = build.version ? ` • ${build.version}` : '';
  console.log(`%cTRACS System • Initial Build by ${owner}${version}`, 'color:#0891b2;font-weight:700;');
  if (build.easterEgg) {
    console.log('Internal build channel active.');
  }
  window.__TRACS_SIGNATURE_LOGGED__ = true;
}
tracsLogBuildSignature();

/* ── BASE API ─────────────────────────────────────────── */
/* ── API CONFIG ───────────────────────────────────────── */
const API_BASE = '/api/';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const csrfMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const nativeFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;

if (nativeFetch) {
  window.fetch = function(input, init = {}) {
    const method = String(init.method || input?.method || 'GET').toUpperCase();
    if (!csrfToken || !csrfMethods.has(method)) {
      return nativeFetch(input, init);
    }

    const headers = new Headers(init.headers || input?.headers || {});
    if (!headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', csrfToken);
    }

    return nativeFetch(input, { ...init, headers });
  };
}

const API = {

  CASE: {
    CREATE : API_BASE + 'case-create.php',
    UPDATE : API_BASE + 'case-update.php',
    DELETE : API_BASE + 'case-delete.php',
    RESOLVE: API_BASE + 'case-resolve.php',
    STATUS : API_BASE + 'case-status.php',
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
  },

  NOTIFICATION: {
    LIST     : API_BASE + 'notifications-list.php',
    MARK_READ: API_BASE + 'notification-mark-read.php',
    CLAIM_PUSH: API_BASE + 'notification-push-claim.php',
    PUSH_STATUS: API_BASE + 'notification-push-status.php'
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

/* ── TRACS notifications + shared UI state ───────────── */
const TOAST_FLASH_KEY='tracs:toast-flash';
let _lastToast=null;
const tracsToastDocks=new Map();
const tracsModalToastDocks=new WeakMap();
const tracsInlineToastDocks=new WeakMap();
let tracsLoginToastDockNode=null;
const tracsToastDefaults={success:3500,info:4000,warning:7000,error:9000};
const tracsModalOverlaySelector='.modal-overlay, .dpc-modal, .infra-modal, .cf-modal, .tracs-dialog-overlay';
function tracsNoticeType(type){
  return ['success','error','warning','info'].includes(type) ? type : 'info';
}
function toastIconFor(type){
  if(type === 'error') return 'circle-alert';
  if(type === 'warning') return 'alert-triangle';
  if(type === 'success') return 'check';
  return 'info';
}
function tracsRefreshIcons(root){
  if(window.lucide) lucide.createIcons(root ? { nodes: Array.from(root.querySelectorAll('[data-lucide]')) } : undefined);
}
function tracsVisibleModal(){
  return Array.from(document.querySelectorAll('.modal-overlay:not(.hidden), .dpc-modal, .infra-modal:not([hidden]), .cf-modal, [role="dialog"]:not([hidden])'))
    .find(node=>!node.hidden && !node.classList.contains('hidden') && getComputedStyle(node).display!=='none') || null;
}
function tracsSourceElement(sourceElement){
  if(sourceElement instanceof Element)return sourceElement;
  const active=document.activeElement;
  return active instanceof Element && active!==document.body && active!==document.documentElement ? active : null;
}
function tracsSourceModal(sourceElement){
  const source=tracsSourceElement(sourceElement);
  return source?.closest(tracsModalOverlaySelector) || source?.closest('[role="dialog"]') || null;
}
function tracsResolveModal(modal){
  if(typeof modal === 'string'){
    return document.getElementById(modal)
      || document.getElementById(`${modal}Modal`)
      || document.querySelector(modal);
  }
  if(modal instanceof Element){
    return modal.matches(tracsModalOverlaySelector)
      ? modal
      : (modal.closest(tracsModalOverlaySelector) || modal);
  }
  return tracsVisibleModal();
}
function tracsModalPanel(modal){
  const target=tracsResolveModal(modal);
  if(!target)return null;
  if(target.matches('.modal, .dpc-modal-content, .infra-modal__panel, [role="dialog"]'))return target;
  return target.querySelector('.modal, .dpc-modal-content, .infra-modal__panel, [role="dialog"]') || target;
}
function tracsPositionModalToastDock(dock,modal){
  const target=tracsResolveModal(modal);
  const panel=tracsModalPanel(target);
  if(!target || !panel || !dock)return;
  const targetRect=target.getBoundingClientRect();
  const panelRect=panel.getBoundingClientRect();
  const relativeTop=panelRect.top-targetRect.top;
  const hasRoomAbove=relativeTop>=72;
  const panelHeader=panel.querySelector('.modal-head, .dpc-modal-header, .infra-modal__head, [data-modal-header]');
  const insideTop=panelHeader
    ? panelHeader.getBoundingClientRect().bottom-targetRect.top+8
    : relativeTop+12;
  const requestedMaxWidth=Number.parseFloat(dock.dataset.toastMaxWidth || '420');
  const panelInset=panelRect.width<=240 ? 20 : 32;
  const availableWidth=Math.max(1,Math.min(
    Number.isFinite(requestedMaxWidth) ? requestedMaxWidth : 420,
    panelRect.width-panelInset,
    window.innerWidth-32
  ));
  dock.style.setProperty('--toast-modal-left',`${panelRect.left-targetRect.left+(panelRect.width/2)}px`);
  dock.style.setProperty('--toast-modal-right',`${Math.max(10,targetRect.right-panelRect.right+12)}px`);
  dock.style.setProperty('--toast-modal-top',`${hasRoomAbove?relativeTop-10:insideTop}px`);
  dock.style.setProperty('--toast-modal-max-width',`${availableWidth}px`);
  dock.classList.toggle('is-inside-modal',!hasRoomAbove);
}
function tracsToastContext(options={}){
  const requested=String(options.context || '').toLowerCase();
  if(['page','modal','inline'].includes(requested))return requested;
  if(options.modal || options.contextElement)return 'modal';
  const source=tracsSourceElement(options.sourceElement);
  if(source)return tracsSourceModal(source) ? 'modal' : 'page';
  return tracsVisibleModal() ? 'modal' : 'page';
}
function tracsToastOptionsForSource(sourceElement,options={}){
  const source=tracsSourceElement(sourceElement);
  const sourceModal=tracsSourceModal(source);
  const context=options.context || (sourceModal ? 'modal' : 'page');
  return {
    ...options,
    context,
    position:options.position || (context === 'modal' ? 'modal-center' : 'top-right'),
    sourceElement:source,
    modal:options.modal || (context === 'modal' ? sourceModal : null)
  };
}
function tracsToastDock(context='page',position='',modal=null,sourceElement=null,maxWidth=null){
  const normalizedContext=['modal','inline'].includes(context) ? context : 'page';
  const normalizedPosition=position || (normalizedContext === 'modal' ? 'modal-center' : 'top-right');
  if(normalizedContext === 'modal'){
    const target=tracsResolveModal(modal || tracsSourceModal(sourceElement));
    if(target){
      let dock=tracsModalToastDocks.get(target);
      if(!dock || !dock.isConnected){
        dock=document.createElement('div');
        dock.className=`toast-dock toast-dock--modal toast-container--modal toast-dock--${normalizedPosition}`;
        dock.dataset.toastContext='modal';
        target.appendChild(dock);
        tracsModalToastDocks.set(target,dock);
      }
      const oldPosition=dock.dataset.toastPosition;
      if(oldPosition && oldPosition!==normalizedPosition)dock.classList.remove(`toast-dock--${oldPosition}`);
      dock.classList.add(`toast-dock--${normalizedPosition}`);
      dock.dataset.toastPosition=normalizedPosition;
      dock.dataset.toastMaxWidth=String(Number.parseFloat(maxWidth) || 420);
      tracsPositionModalToastDock(dock,target);
      return dock;
    }
  }
  if(normalizedContext === 'inline'){
    const source=tracsSourceElement(sourceElement);
    const host=source?.closest('[data-toast-context="inline"], .panel, .card, form') || source?.parentElement;
    if(host){
      let dock=tracsInlineToastDocks.get(host);
      if(!dock || !dock.isConnected){
        dock=document.createElement('div');
        dock.className=`toast-dock toast-dock--inline toast-dock--${normalizedPosition}`;
        dock.dataset.toastContext='inline';
        dock.dataset.toastPosition=normalizedPosition;
        host.classList.add('toast-inline-host');
        host.appendChild(dock);
        tracsInlineToastDocks.set(host,dock);
      }
      return dock;
    }
  }
  const key=`${normalizedContext}:${normalizedPosition}`;
  if(tracsToastDocks.has(key))return tracsToastDocks.get(key);
  const dock=document.createElement('div');
  dock.className=`toast-dock toast-dock--${normalizedContext} toast-dock--${normalizedPosition}`;
  dock.dataset.toastContext=normalizedContext;
  dock.dataset.toastPosition=normalizedPosition;
  document.body.appendChild(dock);
  tracsToastDocks.set(key,dock);
  return dock;
}
function tracsLoginToastDock(position='login-top'){
  const card=document.querySelector('.login-card');
  if(!card)return null;
  const shell=card.closest('.login-shell') || card.parentElement || document.body;
  if(!tracsLoginToastDockNode || !tracsLoginToastDockNode.isConnected){
    tracsLoginToastDockNode=document.createElement('div');
    tracsLoginToastDockNode.className=`toast-dock toast-dock--login toast-dock--${position}`;
    tracsLoginToastDockNode.dataset.toastContext='login';
    shell.insertBefore(tracsLoginToastDockNode,card);
  }
  const oldPosition=tracsLoginToastDockNode.dataset.toastPosition;
  if(oldPosition && oldPosition!==position)tracsLoginToastDockNode.classList.remove(`toast-dock--${oldPosition}`);
  tracsLoginToastDockNode.classList.add(`toast-dock--${position}`);
  tracsLoginToastDockNode.dataset.toastPosition=position;
  return tracsLoginToastDockNode;
}
window.addEventListener('resize',()=>{
  document.querySelectorAll('.toast-dock--modal-center, .toast-dock--modal-top-right').forEach(dock=>tracsPositionModalToastDock(dock,dock.parentElement));
});
function tracsToastArgs(args){
  const types=['success','error','warning','info'];
  if(types.includes(args[0]) && args.length >= 3){
    return {type:args[0],title:args[1],message:args[2],options:args[3]||{}};
  }
  return {message:args[0],type:args[1]||'info',options:args[2]||{},title:(args[2]||{}).title||''};
}
function tracsDismissToast(toastNode){
  if(!toastNode?.isConnected || toastNode.classList.contains('is-leaving'))return;
  toastNode.classList.add('is-leaving');
  setTimeout(()=>toastNode.remove(),420);
}
function showToast(...args){
  const parsed=tracsToastArgs(args);
  const noticeType=tracsNoticeType(parsed.type);
  const options=parsed.options && typeof parsed.options === 'object' ? parsed.options : {};
  const critical=options.priority === 'critical' || (noticeType === 'error' && /could not sign|could not be saved|could not be deleted|could not be resolved|server.+unavailable|session.+expired|do not have permission|too many attempts/i.test(`${parsed.title || ''} ${parsed.message || ''}`));
  const persistent=options.persistent === true || critical;
  const duration=persistent ? 0 : (Number.isFinite(Number(options.duration)) ? Number(options.duration) : tracsToastDefaults[noticeType]);
  const sourceElement=tracsSourceElement(options.sourceElement);
  const context=tracsToastContext({...options,sourceElement});
  const position=options.position || (context === 'modal' ? 'modal-center' : '');
  const modal=options.modal || options.contextElement || (context === 'modal' ? tracsSourceModal(sourceElement) : null);
  const dock=(context === 'page' && document.querySelector('.login-card') && options.loginGlobal !== true)
    ? tracsLoginToastDock(position || 'login-top')
    : tracsToastDock(context,position,modal,sourceElement,options.maxWidth);
  const title=parsed.title;
  const message=parsed.message;
  let toastTitle=String(title || '').trim();
  let toastMessage=String(message || '').trim();
  if(noticeType === 'error'){
    if(toastMessage)toastMessage=getFriendlyErrorMessage(toastMessage,'The request could not be completed. Please check the data and try again.');
    else{
      toastMessage=getFriendlyErrorMessage(toastTitle,'The request could not be completed. Please check the data and try again.');
      toastTitle='';
    }
  }
  const fallback=toastTitle || toastMessage;
  if(!fallback)return null;
  _lastToast={type:noticeType,title:toastTitle,message:toastMessage || toastTitle};
  const duplicateKey=`${noticeType}:${(toastMessage || toastTitle).toLowerCase().replace(/\s+/g,' ').trim()}`;
  let existing=Array.from(dock.querySelectorAll('.toast:not(.is-leaving)'));
  if(context === 'modal'){
    existing.forEach(item=>item.remove());
    existing=[];
  }else{
    const duplicate=existing.find(item=>item.dataset.toastKey===duplicateKey);
    if(duplicate)tracsDismissToast(duplicate);
    if(existing.length >= 3){
      const replaceable=existing.find(item=>item.dataset.persistent!=='true') || existing[0];
      tracsDismissToast(replaceable);
    }
  }
  const t=document.createElement('div');
  const ic=toastIconFor(noticeType);
  t.className=`toast toast--${context} ${noticeType}`;
  const requestedMaxWidth=Number.parseFloat(options.maxWidth);
  if(context !== 'modal' && Number.isFinite(requestedMaxWidth))t.style.maxWidth=`min(100%, ${requestedMaxWidth}px)`;
  t.dataset.toastKey=duplicateKey;
  t.dataset.persistent=persistent?'true':'false';
  if(critical)t.classList.add('is-critical');
  t.setAttribute('role',noticeType === 'error' ? 'alert' : 'status');
  t.setAttribute('aria-live',noticeType === 'error' ? 'assertive' : 'polite');
  const radar=document.createElement('span');
  radar.className='toast-radar';
  radar.setAttribute('aria-hidden','true');
  const icon=document.createElement('i');
  icon.dataset.lucide=ic;
  icon.className='toast-icon';
  icon.setAttribute('aria-hidden','true');
  radar.appendChild(icon);
  const body=document.createElement('span');
  body.className='toast-body';
  if(toastTitle && toastMessage && toastTitle !== toastMessage){
    const titleEl=document.createElement('span');
    titleEl.className='toast-title';
    titleEl.textContent=toastTitle;
    body.appendChild(titleEl);
    const msgEl=document.createElement('span');
    msgEl.className='toast-msg';
    msgEl.textContent=toastMessage;
    body.appendChild(msgEl);
  }else{
    const msgEl=document.createElement('span');
    msgEl.className='toast-msg';
    msgEl.textContent=toastMessage || toastTitle;
    body.appendChild(msgEl);
  }
  const close=document.createElement('button');
  close.type='button';
  close.className='toast-close';
  close.setAttribute('aria-label','Dismiss notification');
  close.innerHTML='<i data-lucide="x" class="icon-xs"></i>';
  const closable=options.closable !== false && (options.closable === true || persistent || noticeType === 'error' || noticeType === 'warning');
  close.hidden=!closable;
  t.append(radar,body,close);
  if(critical)dock.prepend(t);
  else dock.appendChild(t);
  const dismiss=()=>tracsDismissToast(t);
  close.addEventListener('click',dismiss);
  tracsRefreshIcons(t);
  if(duration > 0)setTimeout(dismiss,duration);
  return t;
}
function toast(msg,type='info',ms){
  const options=Number.isFinite(Number(ms)) ? {duration:Number(ms)} : {};
  return showToast(msg,type,options);
}
window.toast=toast;
window.showToast=showToast;
window.tracsToast=showToast;
window.tracsToastOptionsForSource=tracsToastOptionsForSource;

function setButtonLoading(button,loadingText='Processing...'){
  if(typeof button === 'string')button=document.querySelector(button);
  if(!button || button.dataset.tracsLoading === '1')return false;
  const label=button.querySelector('[data-loading-label], .btn-login-label');
  const inputButton=button.matches?.('input[type="submit"], input[type="button"]');
  button.dataset.tracsLoading='1';
  button.dataset.tracsOriginalHtml=button.innerHTML;
  button.dataset.tracsOriginalValue=inputButton ? button.value : '';
  button.dataset.tracsOriginalDisabled=button.disabled?'1':'0';
  button.dataset.tracsOriginalAriaDisabled=button.getAttribute('aria-disabled') ?? '';
  button.dataset.tracsOriginalAriaBusy=button.getAttribute('aria-busy') ?? '';
  button.dataset.tracsOriginalAriaLabel=button.getAttribute('aria-label') ?? '';
  button.dataset.tracsOriginalTitle=button.getAttribute('title') ?? '';
  button.dataset.tracsOriginalMinWidth=button.style.minWidth || '';
  const width=Math.ceil(button.getBoundingClientRect().width);
  if(width>0)button.style.minWidth=`${width}px`;
  if(inputButton){
    button.value=loadingText;
  }else if(button.classList.contains('btn-icon')){
    button.classList.add('is-loading-icon');
    button.setAttribute('aria-label',loadingText);
    button.title=loadingText;
  }else if(label){
    label.dataset.tracsOriginalLabel=label.textContent || '';
    label.textContent=loadingText;
  }else{
    button.textContent=loadingText;
  }
  button.disabled=true;
  button.setAttribute('aria-disabled','true');
  button.setAttribute('aria-busy','true');
  button.classList.add('is-loading');
  return true;
}
function resetButtonLoading(button){
  if(typeof button === 'string')button=document.querySelector(button);
  if(!button || button.dataset.tracsLoading !== '1')return;
  const inputButton=button.matches?.('input[type="submit"], input[type="button"]');
  if(inputButton)button.value=button.dataset.tracsOriginalValue || button.value;
  else button.innerHTML=button.dataset.tracsOriginalHtml || button.innerHTML;
  button.disabled=button.dataset.tracsOriginalDisabled === '1';
  const ariaDisabled=button.dataset.tracsOriginalAriaDisabled;
  const ariaBusy=button.dataset.tracsOriginalAriaBusy;
  const ariaLabel=button.dataset.tracsOriginalAriaLabel;
  const title=button.dataset.tracsOriginalTitle;
  if(ariaDisabled)button.setAttribute('aria-disabled',ariaDisabled); else button.removeAttribute('aria-disabled');
  if(ariaBusy)button.setAttribute('aria-busy',ariaBusy); else button.removeAttribute('aria-busy');
  if(ariaLabel)button.setAttribute('aria-label',ariaLabel); else button.removeAttribute('aria-label');
  if(title)button.setAttribute('title',title); else button.removeAttribute('title');
  button.style.minWidth=button.dataset.tracsOriginalMinWidth || '';
  button.classList.remove('is-loading','is-loading-icon');
  delete button.dataset.tracsLoading;
  tracsRefreshIcons(button);
}
async function withLoadingState(button,loadingText,asyncCallback){
  if(typeof loadingText === 'function'){
    asyncCallback=loadingText;
    loadingText='Processing...';
  }
  if(typeof button === 'string')button=document.querySelector(button);
  if(!button)return await asyncCallback();
  if(!setButtonLoading(button,loadingText))return undefined;
  try{return await asyncCallback();}
  finally{resetButtonLoading(button);}
}
function getFriendlyErrorMessage(error,fallbackMessage='Something went wrong. Please try again.'){
  const raw=String(error?.userMessage || error?.message || error || '').trim();
  const status=Number(error?.status || error?.response?.status || 0);
  if(/invalid login credentials|invalid credentials/i.test(raw))return 'We could not sign you in. Please check your email and password, then try again.';
  if(status===401 || /session.+expired|unauthenticated/i.test(raw))return 'Your session has expired. Please sign in again to continue.';
  if(status===403 || /permission|forbidden|not authorized/i.test(raw))return 'You do not have permission to perform this action.';
  if(status===429 || /too many|rate limit|busy|timeout|timed out/i.test(raw))return 'The server is taking longer than usual. Please wait a moment and try again.';
  if(status>=500 || /server unavailable|service unavailable|bad gateway/i.test(raw))return 'The server is currently unavailable. Please try again in a moment.';
  if(error?.name==='TypeError' || /failed to fetch|network error|networkerror|load failed/i.test(raw))return 'Connection issue detected. Please check your internet connection and try again.';
  if(/sql|mysqli|pdo|stack trace|fatal error|warning:|\/var\/|\/home\/|\\.php on line/i.test(raw))return fallbackMessage;
  return raw || fallbackMessage;
}
function handleRequestError(error,context='page',fallbackMessage='Your changes could not be saved. Please check the data and try again.'){
  const message=getFriendlyErrorMessage(error,fallbackMessage);
  const critical=Number(error?.status || 0)>=500 || /server is currently unavailable|session has expired|permission|could not be saved|could not be deleted|could not be resolved|could not sign/i.test(message);
  showToast(message,'error',{
    context,
    duration:critical?0:9000,
    persistent:critical,
    closable:true,
    priority:critical?'critical':'normal'
  });
  return message;
}
function tracsCloseModalElement(modal,options={}){
  const target=tracsResolveModal(modal);
  if(!target)return;
  if(target.dataset.tracsSuccessPending === '1')return;
  if(!options.bypassUnsaved && window.TRACSUnsavedChanges?.isDirty(target)){
    window.TRACSUnsavedChanges.requestModalClose(target,()=>tracsCloseModalElement(target,{bypassUnsaved:true}));
    return;
  }
  if(target.classList.contains('modal-overlay') || target.classList.contains('tracs-dialog-overlay'))target.classList.add('hidden');
  else if(target.classList.contains('dpc-modal') || target.classList.contains('cf-modal'))target.style.display='none';
  else if(target.classList.contains('infra-modal') || target.hasAttribute('hidden'))target.hidden=true;
  else target.classList.add('hidden');
  target.removeAttribute('aria-busy');
  target.setAttribute('aria-hidden','true');
  delete target.dataset.tracsSuccessPending;
  const toastDock=tracsModalToastDocks.get(target);
  if(toastDock){
    toastDock.remove();
    tracsModalToastDocks.delete(target);
  }
  const trigger=target._tracsModalTrigger;
  if(trigger?.isConnected)requestAnimationFrame(()=>trigger.focus({preventScroll:true}));
}
function tracsOpenModalElement(modal,options={}){
  const target=tracsResolveModal(modal);
  if(!target)return null;
  target._tracsModalTrigger=document.activeElement;
  target.classList.remove('hidden');
  target.hidden=false;
  target.removeAttribute('aria-hidden');
  if(options.display)target.style.display=options.display;
  const panel=tracsModalPanel(target);
  if(panel){
    if(!panel.hasAttribute('role'))panel.setAttribute('role','dialog');
    panel.setAttribute('aria-modal','true');
  }
  requestAnimationFrame(()=>window.TRACSUnsavedChanges?.markSaved(target));
  return target;
}
function tracsValidationTarget(field){
  if(!(field instanceof Element))return null;
  if(field.matches('select.tracs-native-select')){
    return field.closest('.tracs-select')?.querySelector('.tracs-select-trigger') || field;
  }
  if(field._flatpickr?.altInput)return field._flatpickr.altInput;
  if(field.matches('input[type="checkbox"], input[type="radio"]')){
    return field.closest('label, .form-check, .shift-check-label, [data-check-row]') || field;
  }
  return field;
}
function tracsRevealInvalidField(field,modal){
  if(!(field instanceof Element))return;
  field.closest('details:not([open])')?.setAttribute('open','');
  const pane=field.closest('[role="tabpanel"], [data-infra-modal-pane], [data-modal-pane]');
  if(pane && getComputedStyle(pane).display === 'none'){
    const paneId=pane.id;
    const paneName=pane.getAttribute('data-infra-modal-pane') || pane.getAttribute('data-modal-pane');
    const selector=paneId
      ? `[aria-controls="${CSS.escape(paneId)}"]`
      : paneName
        ? `[data-infra-modal-tab="${CSS.escape(paneName)}"], [data-modal-tab="${CSS.escape(paneName)}"]`
        : '';
    if(selector)(modal || document).querySelector(selector)?.click();
  }
  field.dispatchEvent(new CustomEvent('tracs:reveal-invalid',{bubbles:true,detail:{field}}));
}
function tracsFocusInvalidField(field,options={}){
  if(!(field instanceof Element))return;
  const modal=tracsResolveModal(options.modal || field);
  tracsRevealInvalidField(field,modal);
  field.setAttribute('aria-invalid','true');
  const target=tracsValidationTarget(field);
  target?.classList.add('is-invalid');
  requestAnimationFrame(()=>{
    target?.scrollIntoView?.({behavior:'smooth',block:'center',inline:'nearest'});
    window.setTimeout(()=>{
      const focusNode=target?.matches?.('label, .form-check, .shift-check-label, [data-check-row]')
        ? target.querySelector('input, select, textarea, button, [tabindex]')
        : target;
      focusNode?.focus?.({preventScroll:true});
    },180);
  });
}
function showModalSuccessAndClose(options={}){
  const modal=tracsResolveModal(options.modal);
  const delay=Math.min(1500,Math.max(800,Number(options.delay ?? options.closeDelay ?? 1000)));
  const message=String(options.message || 'Saved successfully.');
  resetButtonLoading(options.button);
  if(modal){
    modal.dataset.tracsSuccessPending='1';
    modal.setAttribute('aria-busy','false');
    modal.dispatchEvent(new CustomEvent('tracs:save-success',{bubbles:true,detail:{root:modal}}));
  }
  const toastNode=showToast(message,'success',{
    context:'modal',
    position:'modal-center',
    modal,
    duration:Math.max(Number(options.duration || 0),delay+700),
    closable:false
  });
  return new Promise(resolve=>{
    window.setTimeout(async()=>{
      if(modal)delete modal.dataset.tracsSuccessPending;
      if(typeof options.close === 'function')options.close(modal);
      else tracsCloseModalElement(modal);
      try{
        if(typeof options.onAfterClose === 'function')await options.onAfterClose();
      }finally{
        resolve(toastNode);
      }
    },delay);
  });
}
function handleModalError(options={}){
  const modal=tracsResolveModal(options.modal);
  const error=options.error || options.message || '';
  resetButtonLoading(options.button);
  if(modal){
    modal.removeAttribute('aria-busy');
    delete modal.dataset.tracsSuccessPending;
  }
  const message=getFriendlyErrorMessage(error,options.fallbackMessage || 'Your changes could not be saved. Please check the data and try again.');
  const critical=options.persistent === true
    || Number(error?.status || 0)>=500
    || /session has expired|permission|server is currently unavailable|could not be saved|network/i.test(message);
  const toastNode=showToast(message,'error',{
    context:'modal',
    position:'modal-center',
    modal,
    duration:critical?0:Number(options.duration || 10000),
    persistent:critical,
    closable:true,
    priority:critical?'critical':'normal'
  });
  const focusTarget=options.focus || modal?.querySelector('[aria-invalid="true"], :invalid');
  if(focusTarget)tracsFocusInvalidField(focusTarget,{modal});
  return toastNode;
}
function bindModalValidation(){
  let pendingForm=null;
  document.addEventListener('invalid',event=>{
    const field=event.target;
    if(!(field instanceof HTMLElement))return;
    const modal=tracsSourceModal(field);
    if(!modal)return;
    const form=field.form || field.closest('form');
    field.setAttribute('aria-invalid','true');
    if(form && pendingForm===form)return;
    pendingForm=form;
    requestAnimationFrame(()=>{ pendingForm=null; });
    form?.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(resetButtonLoading);
    if(form)delete form.dataset.tracsSubmitting;
    handleModalError({
      modal,
      focus:field,
      message:field.validationMessage || 'Complete the highlighted required field before saving.',
      duration:7000
    });
  },true);
  document.addEventListener('input',event=>{
    const field=event.target;
    if(!(field instanceof HTMLElement) || !field.hasAttribute('aria-invalid'))return;
    if(typeof field.checkValidity === 'function' && !field.checkValidity())return;
    field.removeAttribute('aria-invalid');
    tracsValidationTarget(field)?.classList.remove('is-invalid');
  });
}
function initModalAccessibility(){
  document.querySelectorAll('.modal-close, .modal-close-btn, [data-modal-close], [data-infra-manage-close]').forEach(button=>{
    if(button instanceof HTMLButtonElement && !button.hasAttribute('type'))button.type='button';
    if(button.hasAttribute('aria-label'))return;
    const panel=button.closest('.modal, .dpc-modal-content, .infra-modal__panel, [role="dialog"]');
    const title=panel?.querySelector('.modal-title, .dpc-modal-header h3, .infra-modal__head h2, [data-modal-title]')?.textContent?.trim();
    button.setAttribute('aria-label',title ? `Close ${title}` : 'Close dialog');
  });
  document.querySelectorAll('.modal-overlay, .dpc-modal, .infra-modal, .cf-modal').forEach(root=>{
    const panel=tracsModalPanel(root);
    if(!panel)return;
    if(!panel.hasAttribute('role'))panel.setAttribute('role','dialog');
    panel.setAttribute('aria-modal','true');
    const title=panel.querySelector('.modal-title[id], .dpc-modal-header h3[id], .infra-modal__head h2[id], [data-modal-title][id]');
    if(title && !panel.hasAttribute('aria-labelledby'))panel.setAttribute('aria-labelledby',title.id);
  });
}
function initWhenVisible(target,callback,options={}){
  if(typeof target === 'string')target=document.querySelector(target);
  if(!target || typeof callback !== 'function')return ()=>{};
  let started=false;
  const start=()=>{
    if(started)return;
    started=true;
    target.classList.remove('is-lazy-pending');
    target.classList.add('is-lazy-loading');
    Promise.resolve(callback(target))
      .catch(error=>handleRequestError(error,'page','This component could not be loaded. Please refresh and try again.'))
      .finally(()=>target.classList.remove('is-lazy-loading'));
  };
  target.classList.add('is-lazy-pending');
  if(!('IntersectionObserver' in window)){start();return ()=>{};}
  const observer=new IntersectionObserver(entries=>{
    if(entries.some(entry=>entry.isIntersecting)){
      observer.disconnect();
      start();
    }
  },{rootMargin:options.rootMargin || '180px 0px',threshold:options.threshold ?? 0.01});
  observer.observe(target);
  return ()=>observer.disconnect();
}
window.setButtonLoading=setButtonLoading;
window.resetButtonLoading=resetButtonLoading;
window.withLoadingState=withLoadingState;
window.getFriendlyErrorMessage=getFriendlyErrorMessage;
window.handleRequestError=handleRequestError;
window.showModalSuccessAndClose=showModalSuccessAndClose;
window.handleModalError=handleModalError;
window.tracsResolveModal=tracsResolveModal;
window.tracsCloseModalElement=tracsCloseModalElement;
window.tracsOpenModalElement=tracsOpenModalElement;
window.tracsFocusInvalidField=tracsFocusInvalidField;
window.initWhenVisible=initWhenVisible;

function getShiftReportReminderState({shiftEndTime,reportSubmitted=false,now=new Date()}={}){
  if(reportSubmitted){
    return {
      status:'completed',
      message:'Shift report submitted for the current shift.',
      severity:'normal',
      minutesRemaining:0,
      minutesOverdue:0
    };
  }
  const endMs=shiftEndTime instanceof Date ? shiftEndTime.getTime() : new Date(shiftEndTime).getTime();
  const nowMs=now instanceof Date ? now.getTime() : new Date(now).getTime();
  if(!Number.isFinite(endMs) || !Number.isFinite(nowMs)){
    return {
      status:'normal',
      message:'Shift timing is unavailable. Open Shift Reports to review your handover.',
      severity:'normal',
      minutesRemaining:null,
      minutesOverdue:null
    };
  }
  const diffMs=endMs-nowMs;
  const minutesRemaining=Math.max(0,Math.ceil(diffMs/60000));
  const minutesOverdue=Math.max(0,Math.floor(Math.abs(diffMs)/60000));

  // Reminder thresholds intentionally progress at 30, 20, 10, 0, and 10 minutes overdue.
  if(diffMs<=-10*60000){
    return {status:'overdue-10',message:`Shift report is overdue by ${minutesOverdue} minutes. Please complete it immediately.`,severity:'overdue',minutesRemaining:0,minutesOverdue};
  }
  if(diffMs<=0){
    return {status:'expired',message:'Shift has ended. Please submit your shift report now.',severity:'critical',minutesRemaining:0,minutesOverdue};
  }
  if(diffMs<=10*60000){
    return {status:'critical-10',message:`Critical: only ${minutesRemaining} minutes left to submit your shift report.`,severity:'critical',minutesRemaining,minutesOverdue:0};
  }
  if(diffMs<=20*60000){
    return {status:'reminder-20',message:`Please complete your shift report. ${minutesRemaining} minutes remaining.`,severity:'warning',minutesRemaining,minutesOverdue:0};
  }
  if(diffMs<=30*60000){
    return {status:'reminder-30',message:`Shift report reminder: ${minutesRemaining} minutes left to complete your report.`,severity:'warning',minutesRemaining,minutesOverdue:0};
  }
  return {status:'normal',message:'Shift report reminder will appear 30 minutes before handover.',severity:'normal',minutesRemaining,minutesOverdue:0};
}
function initShiftReportReminders(){
  const widgets=Array.from(document.querySelectorAll('[data-shift-report-reminder]'));
  if(!widgets.length)return;
  const states=['completed','normal','reminder-30','reminder-20','critical-10','expired','overdue-10'];
  const render=widget=>{
    const submitted=widget.dataset.reportSubmitted==='1';
    const serverNowMs=new Date(widget.dataset.serverNow || '').getTime();
    const serverOffset=Number.isFinite(serverNowMs) ? serverNowMs-Number(widget.dataset.clientStartedAt || Date.now()) : 0;
    if(!widget.dataset.clientStartedAt)widget.dataset.clientStartedAt=String(Date.now());
    const state=getShiftReportReminderState({
      shiftEndTime:widget.dataset.shiftEndTime,
      reportSubmitted:submitted,
      now:new Date(Date.now()+serverOffset)
    });
    const reminder=widget.querySelector('.shift-dashboard-widget__reminder');
    if(!reminder)return state;
    reminder.dataset.shiftReminderState=state.status;
    states.forEach(name=>reminder.classList.remove(`is-${name}`));
    reminder.classList.add(`is-${state.status}`);
    reminder.setAttribute('aria-label',state.message);
    const message=reminder.querySelector('[data-shift-reminder-message]');
    const countdown=reminder.querySelector('[data-shift-reminder-countdown]');
    const icon=reminder.querySelector('[data-shift-reminder-icon]');
    if(message)message.textContent=state.message;
    if(countdown){
      countdown.textContent=state.status==='completed'
        ? 'Submitted'
        : (state.minutesOverdue>0 ? `Overdue ${state.minutesOverdue}m` : (state.minutesRemaining===null ? 'Timing unavailable' : `${state.minutesRemaining}m left`));
    }
    if(icon){
      icon.setAttribute('data-lucide',state.status==='completed'?'check-circle':(state.severity==='critical'||state.severity==='overdue'?'circle-alert':(state.severity==='warning'?'bell-ring':'bell')));
      tracsRefreshIcons(reminder);
    }
    return state;
  };
  const updateAll=()=>widgets.map(render);
  const initial=updateAll();
  if(initial.every(state=>state.status==='completed'))return;
  const timer=setInterval(()=>{
    const current=updateAll();
    if(current.every(state=>state.status==='completed'))clearInterval(timer);
  },30000);
  window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
}
window.getShiftReportReminderState=getShiftReportReminderState;

function queueToastAfterReload(){
  if(!_lastToast || (!_lastToast.message && !_lastToast.title) || _lastToast.type === 'error')return;
  try{sessionStorage.setItem(TOAST_FLASH_KEY,JSON.stringify(_lastToast));}catch(e){}
}

function reloadAfterToast(delay=420){
  queueToastAfterReload();
  setTimeout(()=>location.reload(),delay);
}

function navigateAfterToast(url,delay=420){
  queueToastAfterReload();
  setTimeout(()=>{location.href=url;},delay);
}

function showQueuedToast(){
  let payload=null;
  try{
    payload=sessionStorage.getItem(TOAST_FLASH_KEY);
    sessionStorage.removeItem(TOAST_FLASH_KEY);
  }catch(e){}
  if(!payload)return;
  try{
    const data=JSON.parse(payload);
    if(data?.message || data?.title)showToast(data.type||'info',data.title||'',data.message||data.title||'');
  }catch(e){}
}
showQueuedToast();
window.reloadAfterToast=reloadAfterToast;
window.navigateAfterToast=navigateAfterToast;

function tracsDialogShell(){
  let overlay=document.getElementById('tracsSystemDialog');
  if(overlay)return overlay;
  overlay=document.createElement('div');
  overlay.id='tracsSystemDialog';
  overlay.className='tracs-dialog-overlay hidden';
  overlay.innerHTML=[
    '<div class="tracs-dialog" role="dialog" aria-modal="true" aria-labelledby="tracsDialogTitle" aria-describedby="tracsDialogMessage">',
      '<div class="tracs-dialog-head">',
        '<span class="tracs-dialog-icon"><i data-lucide="info" class="icon-sm"></i></span>',
        '<div class="tracs-dialog-heading">',
          '<div class="tracs-dialog-title" id="tracsDialogTitle"></div>',
          '<div class="tracs-dialog-sub" id="tracsDialogSub"></div>',
        '</div>',
        '<button type="button" class="modal-close tracs-dialog-x" data-tracs-dialog-cancel aria-label="Close"><i data-lucide="x"></i></button>',
      '</div>',
      '<div class="tracs-dialog-body">',
        '<p class="tracs-dialog-message" id="tracsDialogMessage"></p>',
        '<div class="tracs-dialog-field" hidden>',
          '<label class="form-label" for="tracsDialogInput"></label>',
          '<textarea class="form-textarea" id="tracsDialogInput" rows="3"></textarea>',
        '</div>',
      '</div>',
      '<div class="tracs-dialog-foot">',
        '<button type="button" class="btn btn-ghost" data-tracs-dialog-cancel>Cancel</button>',
        '<button type="button" class="btn btn-primary" data-tracs-dialog-ok>OK</button>',
      '</div>',
    '</div>'
  ].join('');
  document.body.appendChild(overlay);
  return overlay;
}

let tracsDialogActive=null;
function tracsCloseSystemDialog(result,value=''){
  const active=tracsDialogActive;
  const overlay=document.getElementById('tracsSystemDialog');
  tracsDialogActive=null;
  if(overlay)overlay.classList.add('hidden');
  if(active)active.resolve(active.mode === 'prompt' ? (result ? value : null) : !!result);
}
function tracsOpenSystemDialog(options={},mode='alert'){
  const overlay=tracsDialogShell();
  const dialog=overlay.querySelector('.tracs-dialog');
  const type=tracsNoticeType(options.type || (mode === 'confirm' ? 'warning' : 'info'));
  const icon=toastIconFor(type);
  overlay.dataset.type=type;
  dialog.dataset.type=type;
  const iconWrap=overlay.querySelector('.tracs-dialog-icon');
  iconWrap.innerHTML='<i data-lucide="'+icon+'" class="icon-sm"></i>';
  overlay.querySelector('#tracsDialogTitle').textContent=String(options.title || (mode === 'confirm' ? 'Confirm action' : 'Notice'));
  const sub=overlay.querySelector('#tracsDialogSub');
  sub.textContent=String(options.subtitle || (mode === 'confirm' ? 'Please review this action' : 'TRACS notification'));
  overlay.querySelector('#tracsDialogMessage').textContent=String(options.message || '');
  const field=overlay.querySelector('.tracs-dialog-field');
  const input=overlay.querySelector('#tracsDialogInput');
  const label=field.querySelector('label');
  const cancel=overlay.querySelector('[data-tracs-dialog-cancel]');
  const cancelButtons=overlay.querySelectorAll('[data-tracs-dialog-cancel]');
  const ok=overlay.querySelector('[data-tracs-dialog-ok]');
  field.hidden=mode !== 'prompt';
  if(mode === 'prompt'){
    label.textContent=String(options.inputLabel || 'Response');
    input.value=String(options.defaultValue || '');
    input.placeholder=String(options.placeholder || '');
    input.required=!!options.required;
  }else{
    input.value='';
    input.required=false;
  }
  cancelButtons.forEach(btn=>{btn.hidden=mode === 'alert' && btn !== cancel;});
  ok.textContent=String(options.confirmText || options.okText || (mode === 'confirm' ? 'Confirm' : 'OK'));
  ok.className='btn ' + (options.destructive || type === 'error' ? 'btn-danger' : type === 'warning' ? 'btn-warning' : 'btn-primary');
  const cancelFoot=overlay.querySelector('.tracs-dialog-foot [data-tracs-dialog-cancel]');
  cancelFoot.textContent=String(options.cancelText || 'Cancel');
  overlay.classList.remove('hidden');
  tracsRefreshIcons(overlay);
  return new Promise(resolve=>{
    tracsDialogActive={resolve,mode};
    const cleanup=()=>{
      ok.onclick=null;
      cancelButtons.forEach(btn=>{btn.onclick=null;});
    };
    ok.onclick=()=>{
      if(mode === 'prompt' && input.required && !input.value.trim()){
        input.focus();
        return;
      }
      cleanup();
      tracsCloseSystemDialog(true,input.value.trim());
    };
    cancelButtons.forEach(btn=>{
      btn.onclick=()=>{
        cleanup();
        tracsCloseSystemDialog(false,'');
      };
    });
    window.setTimeout(()=>{(mode === 'prompt' ? input : ok).focus();},30);
  });
}

function tracsAlert(options,type='info',title='Notice'){
  const opts=typeof options === 'object' && options !== null ? options : {message:String(options ?? ''),type,title};
  return tracsOpenSystemDialog(opts,'alert');
}
function tracsConfirm(options,cb,title='Confirm action'){
  const msg=String(options ?? '');
  const opts=typeof options === 'object' && options !== null
    ? options
    : {message:msg,title,type:'warning',destructive:/delete|remove|archive|reset|revert|suspend|cancel|permanent/i.test(msg)};
  const promise=tracsOpenSystemDialog(opts,'confirm');
  if(typeof cb === 'function')promise.then(ok=>{if(ok)cb();});
  return promise;
}
function tracsPrompt(options,defaultValue=''){
  const opts=typeof options === 'object' && options !== null ? options : {message:String(options ?? ''),defaultValue,type:'info',title:'Input required'};
  return tracsOpenSystemDialog(opts,'prompt');
}
function tracsConfirmSubmit(form,options){
  if(!form)return false;
  if(form.dataset.tracsConfirmed === '1'){
    delete form.dataset.tracsConfirmed;
    return true;
  }
  tracsConfirm(options).then(ok=>{
    if(!ok)return;
    form.dataset.tracsConfirmed='1';
    if(typeof form.requestSubmit === 'function')form.requestSubmit();
    else form.submit();
  });
  return false;
}

window.tracsAlert=tracsAlert;
window.showAlertDialog=tracsAlert;
window.tracsConfirm=tracsConfirm;
window.showConfirmDialog=tracsConfirm;
window.tracsPrompt=tracsPrompt;
window.tracsConfirmSubmit=tracsConfirmSubmit;
window.alert=function(message){tracsAlert({type:'info',title:'Notice',message:String(message ?? '')});};
window.confirm=function(message){tracsConfirm({type:'warning',title:'Confirm action',message:String(message ?? ''),destructive:true});return false;};
window.prompt=function(message,defaultValue=''){tracsPrompt({type:'info',title:'Input required',message:String(message ?? ''),defaultValue});return null;};

/* ── Modal system ─────────────────────────────────────── */
function openModal(id){
  const el=document.getElementById(id+'Modal');
  if(el){
    tracsOpenModalElement(el);
    requestAnimationFrame(()=>el.querySelector('[autofocus], .modal-body input:not([type="hidden"]):not([disabled]), .modal-body textarea:not([disabled]), .modal-body select:not([disabled]), form input:not([type="hidden"]):not([disabled]), form textarea:not([disabled]), form select:not([disabled]), button:not(.modal-close):not([disabled])')?.focus({preventScroll:true}));
  }
  window.TRACSDropdowns?.syncAll();
}
function closeModal(id){tracsCloseModalElement(document.getElementById(id+'Modal'));}
function closeAllModals(){document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(tracsCloseModalElement);}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAllModals();});
document.addEventListener('click',e=>{
  if(!e.target.classList.contains('modal-overlay'))return;
  if(e.target.id==='caseImageModal'){closeCaseImagePreview();return;}
  closeAllModals();
});

/* ── TRACS custom dropdowns ───────────────────────────── */
const TRACSDropdowns = (() => {
  const instances = new WeakMap();
  let active = null;
  let bodyObserver = null;

  const SELECTOR = 'select:not([data-tracs-native])';
  const labelOf = option => option?.textContent?.trim() || option?.label || option?.value || '';
  const selectedOptions = select => Array.from(select.options || []).filter(option => option.selected);
  const visibleOptions = select => Array.from(select.options || []);
  const mirroredClasses = select => Array.from(select.classList)
    .filter(name => !['tracs-native-select'].includes(name))
    .join(' ');

  function dispatchNativeChange(select) {
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function variantClass(select) {
    const variants = [];
    if (select.multiple) variants.push('is-multiple');
    if (select.classList.contains('compact-select')) variants.push('is-compact');
    if (select.classList.contains('dt-move-select')) variants.push('is-dt-move');
    if (select.classList.contains('dt-status-select')) variants.push('is-dt-status');
    if (select.classList.contains('bt-inline-input') || select.classList.contains('dt-inline-input') || select.classList.contains('fb-inline-input')) variants.push('is-inline');
    return variants.join(' ');
  }

  function selectedText(select) {
    if (select.multiple) {
      const picked = selectedOptions(select).filter(option => option.value !== '');
      if (!picked.length) return select.dataset.placeholder || 'Select options';
      if (picked.length === 1) return labelOf(picked[0]);
      return `${picked.length} selected`;
    }
    const selected = select.options[select.selectedIndex];
    return labelOf(selected) || select.getAttribute('placeholder') || 'Select';
  }

  function setActiveOption(instance, index) {
    const enabled = instance.items.filter(item => !item.option.disabled);
    if (!enabled.length) return;
    const bounded = Math.max(0, Math.min(index, enabled.length - 1));
    instance.activeOption = enabled[bounded];
    instance.items.forEach(item => item.node.classList.toggle('is-active', item === instance.activeOption));
    instance.activeOption.node.scrollIntoView({ block: 'nearest' });
  }

  function activeIndex(instance) {
    const enabled = instance.items.filter(item => !item.option.disabled);
    const index = enabled.indexOf(instance.activeOption);
    if (index >= 0) return index;
    const selected = enabled.find(item => item.option.selected);
    return Math.max(0, enabled.indexOf(selected));
  }

  function positionMenu(instance) {
    if (!instance.open) return;
    const rect = instance.trigger.getBoundingClientRect();
    const viewportGap = 10;
    const minWidth = Math.max(rect.width, 128);
    instance.menu.style.minWidth = `${minWidth}px`;
    instance.menu.style.maxWidth = `${Math.max(minWidth, Math.min(360, window.innerWidth - viewportGap * 2))}px`;
    const menuHeight = Math.min(instance.menu.scrollHeight || 260, Math.max(160, window.innerHeight - viewportGap * 2));
    const hasRoomBelow = rect.bottom + menuHeight + viewportGap <= window.innerHeight;
    const top = hasRoomBelow ? rect.bottom + 4 : Math.max(viewportGap, rect.top - menuHeight - 4);
    const left = Math.min(Math.max(viewportGap, rect.left), window.innerWidth - minWidth - viewportGap);
    instance.menu.style.left = `${left}px`;
    instance.menu.style.top = `${top}px`;
    instance.menu.style.maxHeight = `${menuHeight}px`;
    instance.menu.style.zIndex = instance.root.closest('.modal-overlay:not(.hidden)') ? '12000' : '8000';
  }

  function syncInstance(instance) {
    const { select, root, trigger, valueEl } = instance;
    root.className = `${mirroredClasses(select)} tracs-select ${variantClass(select)}`.trim();
    root.classList.toggle('is-disabled', select.disabled);
    trigger.disabled = select.disabled;
    trigger.setAttribute('aria-expanded', instance.open ? 'true' : 'false');
    trigger.setAttribute('aria-disabled', select.disabled ? 'true' : 'false');
    valueEl.textContent = selectedText(select);
    if (select.style.width) root.style.width = select.style.width;
    if (select.classList.contains('has-value')) root.classList.add('has-value');
    if (select.multiple) root.dataset.count = String(selectedOptions(select).length);
    if (instance.open) renderMenu(instance);
  }

  function makeOptionNode(instance, option, index) {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'tracs-select-option';
    item.setAttribute('role', 'option');
    item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
    item.disabled = option.disabled;
    item.dataset.value = option.value;
    item.innerHTML = `
      <span class="tracs-select-check" aria-hidden="true"></span>
      <span class="tracs-select-option-label"></span>
    `;
    item.querySelector('.tracs-select-option-label').textContent = labelOf(option);
    item.classList.toggle('is-selected', option.selected);
    item.classList.toggle('is-placeholder', option.value === '');
    item.addEventListener('mouseenter', () => setActiveOption(instance, index));
    item.addEventListener('click', event => {
      event.stopPropagation();
      chooseOption(instance, option);
    });
    return item;
  }

  function renderMenu(instance) {
    instance.menu.innerHTML = '';
    instance.menu.className = `tracs-select-menu ${instance.select.multiple ? 'is-multiple' : ''}`;
    instance.menu.setAttribute('role', 'listbox');
    instance.menu.setAttribute('aria-multiselectable', instance.select.multiple ? 'true' : 'false');
    instance.items = visibleOptions(instance.select).map((option, index) => {
      const node = makeOptionNode(instance, option, index);
      instance.menu.appendChild(node);
      return { option, node };
    });
    const selected = instance.items.find(item => item.option.selected && !item.option.disabled);
    instance.activeOption = selected || instance.items.find(item => !item.option.disabled) || null;
    instance.items.forEach(item => item.node.classList.toggle('is-active', item === instance.activeOption));
    positionMenu(instance);
  }

  function open(instance) {
    if (instance.select.disabled) return;
    if (active && active !== instance) close(active);
    active = instance;
    instance.open = true;
    instance.root.classList.add('is-open');
    instance.trigger.setAttribute('aria-expanded', 'true');
    renderMenu(instance);
    document.body.appendChild(instance.menu);
    positionMenu(instance);
  }

  function close(instance) {
    if (!instance) return;
    instance.open = false;
    instance.root.classList.remove('is-open');
    instance.trigger.setAttribute('aria-expanded', 'false');
    instance.menu.remove();
    if (active === instance) active = null;
  }

  function chooseOption(instance, option) {
    if (option.disabled) return;
    const { select } = instance;
    if (select.multiple) {
      option.selected = !option.selected;
      syncInstance(instance);
      dispatchNativeChange(select);
      return;
    }
    const changed = select.value !== option.value;
    select.value = option.value;
    syncInstance(instance);
    close(instance);
    instance.trigger.focus();
    if (changed) dispatchNativeChange(select);
  }

  function handleKeydown(instance, event) {
    const keys = ['ArrowDown', 'ArrowUp', 'Enter', ' ', 'Escape', 'Home', 'End'];
    if (!keys.includes(event.key)) return;
    if (event.key === 'Escape') {
      close(instance);
      instance.trigger.focus();
      return;
    }
    event.preventDefault();
    if (!instance.open) {
      open(instance);
      return;
    }
    const enabled = instance.items.filter(item => !item.option.disabled);
    if (!enabled.length) return;
    let index = activeIndex(instance);
    if (event.key === 'ArrowDown') setActiveOption(instance, Math.min(enabled.length - 1, index + 1));
    if (event.key === 'ArrowUp') setActiveOption(instance, Math.max(0, index - 1));
    if (event.key === 'Home') setActiveOption(instance, 0);
    if (event.key === 'End') setActiveOption(instance, enabled.length - 1);
    if (event.key === 'Enter' || event.key === ' ') chooseOption(instance, instance.activeOption.option);
  }

  function enhance(select) {
    if (!select || instances.has(select) || select.dataset.tracsDropdown === 'off') return null;
    const root = document.createElement('div');
    const trigger = document.createElement('button');
    const valueEl = document.createElement('span');
    const chevron = document.createElement('span');
    const menu = document.createElement('div');

    root.className = `${mirroredClasses(select)} tracs-select ${variantClass(select)}`.trim();
    root.dataset.tracsSelectFor = select.id || select.name || '';
    trigger.type = 'button';
    trigger.className = 'tracs-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || select.id || 'Select option');
    valueEl.className = 'tracs-select-value';
    chevron.className = 'tracs-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(valueEl, chevron);
    root.appendChild(trigger);

    select.classList.add('tracs-native-select');
    select.setAttribute('data-tracs-enhanced', 'true');
    select.after(root);

    const instance = { select, root, trigger, valueEl, menu, items: [], activeOption: null, open: false };
    instances.set(select, instance);

    trigger.addEventListener('click', () => instance.open ? close(instance) : open(instance));
    trigger.addEventListener('keydown', event => handleKeydown(instance, event));
    select.addEventListener('change', () => syncInstance(instance));
    select.addEventListener('invalid', () => trigger.classList.add('is-invalid'));
    select.addEventListener('input', () => syncInstance(instance));
    new MutationObserver(() => syncInstance(instance)).observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['selected', 'disabled', 'label', 'value', 'class', 'style']
    });

    syncInstance(instance);
    return instance;
  }

  function init(root = document) {
    root.querySelectorAll?.(SELECTOR).forEach(enhance);
  }

  function syncAll() {
    document.querySelectorAll('select[data-tracs-enhanced="true"]').forEach(select => {
      const instance = instances.get(select);
      if (instance) syncInstance(instance);
    });
  }

  document.addEventListener('click', event => {
    if (!active) return;
    if (active.root.contains(event.target) || active.menu.contains(event.target)) return;
    close(active);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && active) close(active);
  });
  window.addEventListener('resize', () => active && positionMenu(active));
  window.addEventListener('scroll', () => active && positionMenu(active), true);
  document.addEventListener('DOMContentLoaded', () => {
    init();
    bodyObserver = new MutationObserver(records => {
      records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType !== 1) return;
        if (node.matches?.(SELECTOR)) enhance(node);
        init(node);
      }));
    });
    bodyObserver.observe(document.body, { childList: true, subtree: true });
  });

  return { init, syncAll, syncSelect: select => instances.get(select) && syncInstance(instances.get(select)) };
})();
window.TRACSDropdowns = TRACSDropdowns;

/* ── Icon popup menus ───────────────────────────────────── */
const TRACS_POPUP_DETAILS_SELECTOR = [
  '.user-menu-wrap',
  '.nav-menu-wrap',
  '.report-export-menu',
  '.row-action-menu'
].join(',');
const TRACS_CUSTOM_POPUP_SELECTOR = '.theme-menu-wrap, .notif-bell-btn';

function tracsClosestPopup(target) {
  return target instanceof Element
    ? target.closest(`${TRACS_POPUP_DETAILS_SELECTOR}, ${TRACS_CUSTOM_POPUP_SELECTOR}`)
    : null;
}

function tracsSetCustomPopupOpen(host, open) {
  if (!host) return;
  host.classList.toggle('is-open', open);
  const toggle = host.matches('.theme-menu-wrap')
    ? host.querySelector('#themeToggle, .theme-toggle')
    : host.matches('.notif-bell-btn')
      ? host
      : null;
  toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function tracsCloseIconPopups(except = null) {
  document.querySelectorAll(`${TRACS_POPUP_DETAILS_SELECTOR}[open]`).forEach(menu => {
    if (menu !== except) menu.removeAttribute('open');
  });
  document.querySelectorAll(`${TRACS_CUSTOM_POPUP_SELECTOR}.is-open`).forEach(menu => {
    if (menu !== except) tracsSetCustomPopupOpen(menu, false);
  });
}

function tracsToggleCustomPopup(host) {
  const willOpen = !host.classList.contains('is-open');
  tracsCloseIconPopups(host);
  tracsSetCustomPopupOpen(host, willOpen);
  if (willOpen && host.matches('.notif-bell-btn')) {
    window.TRACSNotifications?.markVisibleRead?.();
  }
}

function tracsInitNotificationPopups(root = document) {
  root.querySelectorAll?.('.notif-bell-btn').forEach(btn => {
    if (btn.dataset.tracsPopupInit === 'true') return;
    btn.dataset.tracsPopupInit = 'true';
    btn.setAttribute('aria-expanded', 'false');
    btn.addEventListener('click', event => {
      if (event.target.closest('.notif-dropdown')) return;
      event.stopPropagation();
      tracsToggleCustomPopup(btn);
    });
    btn.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        tracsSetCustomPopupOpen(btn, false);
        return;
      }
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      tracsToggleCustomPopup(btn);
    });
  });
}

window.tracsCloseIconPopups = tracsCloseIconPopups;
window.tracsSetCustomPopupOpen = tracsSetCustomPopupOpen;

document.addEventListener('click', e => {
  const popup = tracsClosestPopup(e.target);
  const summary = e.target instanceof Element ? e.target.closest('summary') : null;
  const clickedPopupSummary = summary?.closest(TRACS_POPUP_DETAILS_SELECTOR);

  if (!popup) {
    tracsCloseIconPopups();
    return;
  }

  if (clickedPopupSummary) {
    setTimeout(() => {
      if (clickedPopupSummary.open) tracsCloseIconPopups(clickedPopupSummary);
    }, 0);
  }

  if (e.target instanceof Element && e.target.closest('.row-action-popover button')) {
    e.target.closest('.row-action-menu')?.removeAttribute('open');
  }
});

/* ── Profile picture crop/upload ─────────────────────── */
(() => {
  const MAX_RAW_SIZE = 5 * 1024 * 1024;
  const OUTPUT_SIZE = 512;
  const SUPPORTED_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const state = {
    userId: '',
    image: null,
    objectUrl: '',
    zoom: 1,
    minScale: 1,
    offsetX: 0,
    offsetY: 0,
    dragging: false,
    lastX: 0,
    lastY: 0
  };

  const modal = () => document.getElementById('avatarCropModal');
  const cropCanvas = () => document.getElementById('avatarCropCanvas');
  const previewCanvas = () => document.getElementById('avatarPreviewCanvas');
  const zoomRange = () => document.getElementById('avatarZoomRange');

  function webpSupported() {
    const canvas = document.createElement('canvas');
    return canvas.toDataURL('image/webp').startsWith('data:image/webp');
  }

  function avatarInitials(node) {
    return node?.dataset?.avatarInitials || 'U';
  }

  function setAvatarNode(node, avatarUrl, initials) {
    if (!node) return;
    node.dataset.avatarInitials = initials || avatarInitials(node);
    node.innerHTML = avatarUrl
      ? `<img src="${avatarUrl}" alt="" loading="lazy" decoding="async">`
      : `<span>${node.dataset.avatarInitials || 'U'}</span>`;
  }

  function updateUserPayloads(userId, avatarUrl, avatarPath) {
    document.querySelectorAll('[data-user]').forEach(node => {
      try {
        const payload = JSON.parse(node.dataset.user || '{}');
        if (String(payload.id) !== String(userId)) return;
        payload.avatar_url = avatarUrl || '';
        payload.avatar_path = avatarPath || avatarUrl || '';
        node.dataset.user = JSON.stringify(payload);
      } catch (e) {}
    });
  }

  function selectorEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value));
    return String(value).replace(/["\\]/g, '\\$&');
  }

  function updateAvatarUi(userId, avatarUrl, initials, avatarPath = '') {
    document.querySelectorAll(`.tracs-avatar[data-avatar-user-id="${selectorEscape(userId)}"]`).forEach(node => {
      setAvatarNode(node, avatarUrl, initials);
    });
    document.querySelectorAll(`[data-avatar-remove][data-avatar-user-id="${selectorEscape(userId)}"]`).forEach(btn => {
      btn.disabled = !avatarUrl;
    });
    updateUserPayloads(userId, avatarUrl, avatarPath);
  }

  function clampOffset() {
    if (!state.image) return;
    const width = state.image.width * state.minScale * state.zoom;
    const height = state.image.height * state.minScale * state.zoom;
    state.offsetX = width <= OUTPUT_SIZE ? (OUTPUT_SIZE - width) / 2 : Math.min(0, Math.max(OUTPUT_SIZE - width, state.offsetX));
    state.offsetY = height <= OUTPUT_SIZE ? (OUTPUT_SIZE - height) / 2 : Math.min(0, Math.max(OUTPUT_SIZE - height, state.offsetY));
  }

  function drawAvatarCrop() {
    const canvas = cropCanvas();
    const preview = previewCanvas();
    if (!canvas || !preview || !state.image) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    clampOffset();
    const width = state.image.width * state.minScale * state.zoom;
    const height = state.image.height * state.minScale * state.zoom;
    ctx.drawImage(state.image, state.offsetX, state.offsetY, width, height);
    ctx.save();
    ctx.strokeStyle = 'rgba(255,255,255,.85)';
    ctx.lineWidth = 4;
    ctx.strokeRect(2, 2, OUTPUT_SIZE - 4, OUTPUT_SIZE - 4);
    ctx.restore();

    const pctx = preview.getContext('2d');
    pctx.clearRect(0, 0, preview.width, preview.height);
    pctx.drawImage(canvas, 0, 0, preview.width, preview.height);
  }

  function canvasPoint(event) {
    const canvas = cropCanvas();
    const rect = canvas.getBoundingClientRect();
    const touch = event.touches?.[0] || event.changedTouches?.[0] || event;
    return {
      x: ((touch.clientX - rect.left) / rect.width) * OUTPUT_SIZE,
      y: ((touch.clientY - rect.top) / rect.height) * OUTPUT_SIZE
    };
  }

  function setZoom(nextZoom) {
    const oldZoom = state.zoom;
    state.zoom = Math.max(1, Math.min(4, Number(nextZoom) || 1));
    const ratio = state.zoom / oldZoom;
    state.offsetX = OUTPUT_SIZE / 2 - (OUTPUT_SIZE / 2 - state.offsetX) * ratio;
    state.offsetY = OUTPUT_SIZE / 2 - (OUTPUT_SIZE / 2 - state.offsetY) * ratio;
    const range = zoomRange();
    if (range) range.value = String(state.zoom);
    drawAvatarCrop();
  }

  function closeCropModal() {
    modal()?.classList.add('hidden');
    if (state.objectUrl) URL.revokeObjectURL(state.objectUrl);
    state.objectUrl = '';
    state.image = null;
  }

  function openCropModal(file, userId) {
    state.userId = String(userId || '');
    state.objectUrl = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      state.image = img;
      state.zoom = 1;
      state.minScale = Math.max(OUTPUT_SIZE / img.width, OUTPUT_SIZE / img.height);
      const width = img.width * state.minScale;
      const height = img.height * state.minScale;
      state.offsetX = (OUTPUT_SIZE - width) / 2;
      state.offsetY = (OUTPUT_SIZE - height) / 2;
      const range = zoomRange();
      if (range) range.value = '1';
      modal()?.classList.remove('hidden');
      drawAvatarCrop();
      window.TRACSDropdowns?.syncAll();
      lucide?.createIcons();
    };
    img.onerror = () => {
      closeCropModal();
      toast('The selected image could not be loaded.', 'error');
    };
    img.src = state.objectUrl;
  }

  function chooseAvatarFile(userId) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp';
    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (!file) return;
      if (!SUPPORTED_TYPES.has(file.type)) {
        toast('Only JPG, JPEG, PNG, and WEBP profile pictures are supported.', 'error');
        return;
      }
      if (file.size > MAX_RAW_SIZE) {
        toast('Choose an image smaller than 5MB.', 'error');
        return;
      }
      openCropModal(file, userId);
    }, { once: true });
    input.click();
  }

  function blobFromCanvas(canvas, type, quality) {
    return new Promise(resolve => canvas.toBlob(resolve, type, quality));
  }

  async function uploadCroppedAvatar() {
    const canvas = cropCanvas();
    const confirm = document.querySelector('[data-avatar-confirm]');
    if (!canvas || !state.userId) return;
    const mime = webpSupported() ? 'image/webp' : 'image/jpeg';
    const extension = mime === 'image/webp' ? 'webp' : 'jpg';
    const blob = await blobFromCanvas(canvas, mime, 0.82);
    if (!blob) {
      toast('Unable to process this image.', 'error');
      return;
    }
    const form = new FormData();
    form.append('action', 'upload');
    form.append('target_user_id', state.userId);
    form.append('avatar', blob, `avatar.${extension}`);
    try {
      if (confirm && !setButtonLoading(confirm,'Saving...')) return;
      const res = await fetch('/api/user-avatar.php', { method: 'POST', body: form });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Unable to save profile picture.');
      showModalSuccessAndClose({
        modal:'avatarCrop',
        button:confirm,
        message:json.message || 'Profile picture updated.',
        close:()=>closeCropModal(),
        onAfterClose:()=>updateAvatarUi(json.data.user_id,json.data.avatar_url || '',json.data.initials || 'U',json.data.avatar_path || '')
      });
    } catch (error) {
      handleModalError({modal:'avatarCrop',button:confirm,error,fallbackMessage:'Unable to save profile picture. Please try again.'});
    } finally {
      resetButtonLoading(confirm);
    }
  }

  async function removeAvatar(userId) {
    if (!userId) return;
    const ok = await tracsConfirm({
      type: 'warning',
      title: 'Remove profile picture',
      message: 'Remove this profile picture?',
      confirmText: 'Remove',
      destructive: true
    });
    if (!ok) return;
    const form = new FormData();
    form.append('action', 'remove');
    form.append('target_user_id', userId);
    try {
      const res = await fetch('/api/user-avatar.php', { method: 'POST', body: form });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Unable to remove profile picture.');
      updateAvatarUi(json.data.user_id, '', json.data.initials || 'U', '');
      toast(json.message || 'Profile picture removed.', 'success');
    } catch (error) {
      toast(error.message || 'Unable to remove profile picture.', 'error');
    }
  }

  function bindCropper() {
    const canvas = cropCanvas();
    if (!canvas || canvas.dataset.avatarBound === '1') return;
    canvas.dataset.avatarBound = '1';
    canvas.addEventListener('pointerdown', event => {
      state.dragging = true;
      canvas.setPointerCapture?.(event.pointerId);
      const point = canvasPoint(event);
      state.lastX = point.x;
      state.lastY = point.y;
    });
    canvas.addEventListener('pointermove', event => {
      if (!state.dragging) return;
      const point = canvasPoint(event);
      state.offsetX += point.x - state.lastX;
      state.offsetY += point.y - state.lastY;
      state.lastX = point.x;
      state.lastY = point.y;
      drawAvatarCrop();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(type => {
      canvas.addEventListener(type, () => { state.dragging = false; });
    });
    canvas.addEventListener('wheel', event => {
      event.preventDefault();
      setZoom(state.zoom + (event.deltaY < 0 ? 0.08 : -0.08));
    }, { passive: false });
  }

  document.addEventListener('click', event => {
    const upload = event.target.closest('[data-avatar-upload]');
    if (upload) {
      const userId = upload.dataset.avatarUserId || upload.closest('[data-avatar-scope]')?.querySelector('[data-avatar-user-id]')?.dataset.avatarUserId || '';
      if (!userId) {
        toast('Save this user before uploading a profile picture.', 'error');
        return;
      }
      chooseAvatarFile(userId);
      return;
    }
    const remove = event.target.closest('[data-avatar-remove]');
    if (remove) {
      removeAvatar(remove.dataset.avatarUserId || '');
      return;
    }
    if (event.target.closest('[data-avatar-cancel]')) {
      closeCropModal();
      return;
    }
    if (event.target.closest('[data-avatar-confirm]')) {
      uploadCroppedAvatar();
      return;
    }
    if (event.target.closest('[data-avatar-zoom-in]')) {
      setZoom(state.zoom + 0.12);
      return;
    }
    if (event.target.closest('[data-avatar-zoom-out]')) {
      setZoom(state.zoom - 0.12);
    }
  });

  document.addEventListener('input', event => {
    if (event.target === zoomRange()) setZoom(event.target.value);
  });
  document.addEventListener('DOMContentLoaded', bindCropper);
  bindCropper();
  window.TRACSAvatars = { updateAvatarUi };
})();

document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  tracsCloseIconPopups();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => tracsInitNotificationPopups());
} else {
  tracsInitNotificationPopups();
}

/* ── Stat card cursor glow ───────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const statCards = document.querySelectorAll('.stat-card');
  const statStoreKey = `tracs-stat-values:${location.pathname}`;
  let previousValues = {};
  try { previousValues = JSON.parse(sessionStorage.getItem(statStoreKey) || '{}') || {}; }
  catch(e) { previousValues = {}; }
  const currentValues = {};

  const statValueFor = card => card.querySelector('.stat-num')?.textContent.trim() || card.textContent.trim();
  const statKeyFor = (card, index) => {
    const label = card.querySelector('.stat-label')?.textContent.trim() || `card-${index}`;
    return `${index}:${label}`;
  };
  const playStatRadar = card => {
    card.classList.remove('is-stat-updated');
    void card.offsetWidth;
    card.classList.add('is-stat-updated');
    window.setTimeout(() => card.classList.remove('is-stat-updated'), 1250);
  };

  statCards.forEach((card, index) => {
    if (!card.querySelector('.stat-glow')) {
      const glow = document.createElement('div');
      glow.className = 'stat-glow';
      card.prepend(glow);
    }

    const syncMaskPosition = e => {
      const rect = card.getBoundingClientRect();
      card.style.setProperty('--stat-mask-x', `${e.clientX - rect.left}px`);
      card.style.setProperty('--stat-mask-y', `${e.clientY - rect.top}px`);
    };

    card.addEventListener('pointerenter', e => {
      syncMaskPosition(e);
      card.classList.add('is-stat-hovering');
    });
    card.addEventListener('pointermove', syncMaskPosition);
    card.addEventListener('pointerleave', () => card.classList.remove('is-stat-hovering'));

    const key = statKeyFor(card, index);
    const value = statValueFor(card);
    currentValues[key] = value;
    if (previousValues[key] !== undefined && previousValues[key] !== value) {
      window.setTimeout(() => playStatRadar(card), 180 + index * 70);
    }

    const valueEl = card.querySelector('.stat-num');
    if (valueEl) {
      let lastValue = value;
      new MutationObserver(() => {
        const nextValue = statValueFor(card);
        if (nextValue === lastValue) return;
        lastValue = nextValue;
        playStatRadar(card);
      }).observe(valueEl, { childList: true, characterData: true, subtree: true });
    }
  });

  if (statCards.length) {
    try { sessionStorage.setItem(statStoreKey, JSON.stringify(currentValues)); }
    catch(e) {}
  }
});

/* ── API helper ───────────────────────────────────────── */
async function api(url,data){
  try{
    const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const text=await r.text();
    let payload={};
    try{payload=text ? JSON.parse(text) : {};}
    catch(parseError){
      const error=new Error('The server returned an invalid response.');
      error.status=r.status;
      throw error;
    }
    if(!r.ok){
      payload.success=false;
      payload.ok=false;
      payload.status=r.status;
      payload.message=getFriendlyErrorMessage({message:payload.message,status:r.status},'The request could not be completed. Please try again.');
    }else if(payload.success === false || payload.ok === false){
      payload.message=getFriendlyErrorMessage(payload.message,'The request could not be completed. Please check the data and try again.');
    }
    return payload;
  }catch(e){
    console.error('TRACS request failed:',e);
    return{success:false,ok:false,status:Number(e?.status||0),message:getFriendlyErrorMessage(e,'Connection issue detected. Please check your internet connection and try again.')};
  }
}

function tracsLoadingTextForButton(button){
  const explicit=button?.dataset?.loadingText;
  if(explicit)return explicit;
  const text=(button?.textContent || '').trim().toLowerCase();
  if(/sign in|login/.test(text))return 'Signing in...';
  if(/delete|remove/.test(text))return 'Deleting...';
  if(/resolve|complete/.test(text))return 'Resolving...';
  if(/save|update|create|schedule|add/.test(text))return 'Saving...';
  return 'Processing...';
}
function bindNativeFormLoading(){
  document.addEventListener('submit',event=>{
    const form=event.target;
    if(!(form instanceof HTMLFormElement) || form.dataset.tracsLoadingBound === 'off')return;
    const method=String(form.method || 'get').toLowerCase();
    if(method === 'get')return;
    const button=event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    if(!button)return;
    setTimeout(()=>{
      if(event.defaultPrevented || form.dataset.tracsSubmitting === '1')return;
      form.dataset.tracsSubmitting='1';
      setButtonLoading(button,tracsLoadingTextForButton(button));
    },0);
  },true);
}
function bindModalAjaxForms(){
  document.addEventListener('submit',async event=>{
    const form=event.target;
    if(!(form instanceof HTMLFormElement) || !form.matches('[data-tracs-modal-ajax]'))return;
    if(event.defaultPrevented)return;
    event.preventDefault();
    if(form.dataset.tracsSubmitting === '1')return;
    const modal=tracsResolveModal(form);
    const button=event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    const formData=event.submitter && typeof FormData === 'function'
      ? new FormData(form,event.submitter)
      : new FormData(form);
    if(event.submitter?.name && !formData.has(event.submitter.name)){
      formData.append(event.submitter.name,event.submitter.value);
    }
    form.dataset.tracsSubmitting='1';
    modal?.setAttribute('aria-busy','true');
    if(button && !setButtonLoading(button,tracsLoadingTextForButton(button))){
      delete form.dataset.tracsSubmitting;
      modal?.removeAttribute('aria-busy');
      return;
    }
    try{
      const submitUrl=event.submitter?.getAttribute('formaction')
        || form.getAttribute('action')
        || window.location.href;
      const response=await fetch(submitUrl,{
        method:String(form.method || 'post').toUpperCase(),
        body:formData,
        headers:{
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest'
        }
      });
      const contentType=response.headers.get('content-type') || '';
      if(!contentType.includes('application/json')){
        const error=new Error('The server returned an invalid response.');
        error.status=response.status;
        throw error;
      }
      const payload=await response.json();
      if(!response.ok || payload.success === false || payload.ok === false){
        const error=new Error(payload.message || 'Your changes could not be saved.');
        error.status=response.status || payload.status;
        throw error;
      }
      resetButtonLoading(button);
      form.dispatchEvent(new CustomEvent('tracs:save-success',{bubbles:true,detail:{root:form}}));
      await showModalSuccessAndClose({
        modal,
        button,
        message:payload.message || form.dataset.successMessage || 'Saved successfully.',
        delay:Number(form.dataset.closeDelay || 1000),
        onAfterClose:()=>{
          const redirect=payload.redirect || form.dataset.refreshUrl || window.location.href;
          if(redirect === window.location.href)window.location.reload();
          else window.location.assign(redirect);
        }
      });
    }catch(error){
      form.dispatchEvent(new CustomEvent('tracs:save-error',{bubbles:true,detail:{root:form}}));
      handleModalError({
        modal,
        button,
        error,
        fallbackMessage:form.dataset.errorMessage || 'Your changes could not be saved. Please check the data and try again.'
      });
    }finally{
      delete form.dataset.tracsSubmitting;
      modal?.removeAttribute('aria-busy');
    }
  });
}

/* ── Cancellation Feedback Handlers ── */
function parseFeedbackMulti(value) {
    if (Array.isArray(value)) return value.filter(Boolean);
    if (!value) return [];
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (Array.isArray(parsed)) return parsed.filter(Boolean);
        } catch (e) {}
        return [value].filter(Boolean);
    }
    return [];
}

function selectedValues(id) {
    const el = document.getElementById(id);
    if (!el) return [];
    if (el.matches('[data-multi-choice]')) {
        return Array.from(el.querySelectorAll('input[type="checkbox"]:checked')).map(input => input.value).filter(Boolean);
    }
    return Array.from(el.selectedOptions || []).map(option => option.value).filter(Boolean);
}

function setMultiSelectValues(id, values) {
    const el = document.getElementById(id);
    if (!el) return;
    const selected = new Set(parseFeedbackMulti(values));
    if (el.matches('[data-multi-choice]')) {
        el.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.checked = selected.has(input.value);
        });
        return;
    }
    Array.from(el.options || []).forEach(option => { option.selected = selected.has(option.value); });
    window.TRACSDropdowns?.syncSelect(el);
}

function appendMultiValues(fd, name, values) {
    values.forEach(value => fd.append(`${name}[]`, value));
}

function renderFeedbackChips(values, critical = false) {
    const items = parseFeedbackMulti(values);
    if (!items.length) return '<span class="text-muted">—</span>';
    return `<div class="cf-chip-row">${items.map(item => `<span class="cf-chip ${critical ? 'cf-chip-critical' : ''}">${escapeHtml(item)}</span>`).join('')}</div>`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));
}

function formatFeedbackDate(value) {
    if (!value) return '—';
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return escapeHtml(value);
    return date.toLocaleString(undefined, { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function referenceHtml(value) {
    const text = String(value || '').trim();
    if (!text) return '—';
    const escaped = escapeHtml(text);
    if (/^https?:\/\//i.test(text)) {
        return `<a href="${escaped}" target="_blank" rel="noopener noreferrer" class="email-link">${escaped}</a>`;
    }
    return escaped;
}

function openNewFeedback() {
    document.getElementById('feedbackId').value = '';
    document.getElementById('feedbackEmail').value = '';
    setMultiSelectValues('feedbackService', []);
    setMultiSelectValues('feedbackReason', []);
    document.getElementById('feedbackReference').value = '';
    document.getElementById('feedbackResolution').value = '';
    document.getElementById('feedbackDetails').value = '';
    document.getElementById('feedbackModalTitle').innerText = 'New Cancellation Feedback';
    openModal('feedback');
}

function openEditFeedback(data) {
    document.getElementById('feedbackId').value = data.id;
    document.getElementById('feedbackEmail').value = data.email_address;
    setMultiSelectValues('feedbackService', data.cancelled_services || data.cancelled_service);
    setMultiSelectValues('feedbackReason', data.cancellation_reasons || data.cancellation_reason);
    document.getElementById('feedbackReference').value = data.whmcs_reference;
    document.getElementById('feedbackResolution').value = data.payment_resolution;
    document.getElementById('feedbackDetails').value = data.additional_details;
    document.getElementById('feedbackModalTitle').innerText = 'Edit Feedback';
    openModal('feedback');
}

async function saveFeedback() {
    const id = document.getElementById('feedbackId').value;
    const url = id ? 'api/feedback-update.php' : 'api/feedback-create.php';
    const button = document.querySelector('#feedbackModal .modal-foot .btn-primary');
    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('email', document.getElementById('feedbackEmail').value);
    appendMultiValues(fd, 'service', selectedValues('feedbackService'));
    appendMultiValues(fd, 'reason', selectedValues('feedbackReason'));
    fd.append('reference', document.getElementById('feedbackReference').value);
    fd.append('resolution', document.getElementById('feedbackResolution').value);
    fd.append('details', document.getElementById('feedbackDetails').value);

    if (!selectedValues('feedbackService').length || !selectedValues('feedbackReason').length) {
        handleModalError({modal:'feedback',message:'Cancelled service and reason are required.'});
        return;
    }

    try {
        const res = await withLoadingState(button,'Saving...',async()=>{
            const response=await fetch(url,{method:'POST',body:fd});
            const payload=await response.json();
            if(!response.ok) {
                const error=new Error(payload.error || payload.message || 'Unable to save feedback.');
                error.status=response.status;
                throw error;
            }
            return payload;
        });
        if(!res)return;
        if (res.success) {
            showModalSuccessAndClose({
                modal:'feedback',
                message:id ? 'Feedback updated.' : 'Feedback added.',
                onAfterClose:()=>location.reload()
            });
        } else {
            handleModalError({modal:'feedback',error:{message:res.error || res.message},fallbackMessage:'The feedback could not be saved. Please try again.'});
        }
    } catch(error) {
        handleModalError({modal:'feedback',button,error,fallbackMessage:'The feedback could not be saved. Please try again.'});
    }
}

async function deleteFeedback(id) {
    const ok = await tracsConfirm({
      type: 'warning',
      title: 'Delete feedback',
      message: 'Delete this feedback record? This cannot be undone.',
      confirmText: 'Delete',
      destructive: true
    });
    if (!ok) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/feedback-delete.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
          toast('Feedback deleted', 'success');
          reloadAfterToast();
        }
        else toast(res.error || "Couldn't delete the feedback entry. Please try again.", 'error');
    });
}

function viewFeedback(id) {
    const data = window.feedbackRecords?.[id];
    if (!data) return;
    const criticalReasons = ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue', 'Issue not resolved'];
    const reasons = parseFeedbackMulti(data.cancellation_reasons || data.cancellation_reason);
    const hasCritical = reasons.some(reason => criticalReasons.includes(reason));
    document.getElementById('feedbackViewSub').textContent = `Feedback #${data.id || id}`;
    document.getElementById('fvSubmitter').textContent = data.submitter_name || '—';
    document.getElementById('fvEmail').innerHTML = data.email_address ? `<a href="mailto:${escapeHtml(data.email_address)}" class="email-link">${escapeHtml(data.email_address)}</a>` : '—';
    document.getElementById('fvServices').innerHTML = renderFeedbackChips(data.cancelled_services || data.cancelled_service);
    document.getElementById('fvReasons').innerHTML = renderFeedbackChips(reasons, hasCritical);
    document.getElementById('fvReference').innerHTML = referenceHtml(data.whmcs_reference);
    document.getElementById('fvResolution').textContent = data.payment_resolution || '—';
    document.getElementById('fvCreated').textContent = formatFeedbackDate(data.created_at);
    document.getElementById('fvUpdated').textContent = formatFeedbackDate(data.updated_at);
    document.getElementById('fvCreator').textContent = data.creator_name || data.created_by_name || '—';
    document.getElementById('fvDetails').textContent = data.additional_details || '—';
    const editBtn = document.getElementById('feedbackViewEdit');
    if (editBtn) {
        editBtn.onclick = () => {
            closeModal('feedbackView');
            openEditFeedback(data);
        };
    }
    openModal('feedbackView');
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
    appendMultiValues(fd, 'service', selectedValues('inService'));
    appendMultiValues(fd, 'reason', selectedValues('inReason'));
    fd.append('reference', document.getElementById('inRef').value);
    fd.append('email', document.getElementById('inEmail').value);
    fd.append('resolution', document.getElementById('inResolution').value);
    fd.append('details', document.getElementById('inDetails').value);

    if (!selectedValues('inService').length || !selectedValues('inReason').length) {
        toast('Service and Reason are required.', 'error');
        return;
    }

    fetch('api/feedback-create.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            toast('Feedback added', 'success');
            reloadAfterToast();
        } else {
            toast(res.error || "Couldn't save the feedback. Please check the fields and try again.", 'error');
        }
    });
}

function clearInlineFeedback() {
    setMultiSelectValues('inService', []);
    setMultiSelectValues('inReason', []);
    document.getElementById('inRef').value = '';
    document.getElementById('inEmail').value = '';
    document.getElementById('inResolution').value = '';
    document.getElementById('inDetails').value = '';
}


/* ── DOM helpers ──────────────────────────────────────── */
function val(id){return document.getElementById(id)?.value||'';}
function setVal(id,v){
  const el=document.getElementById(id);
  if(!el)return;
  el.value=v||'';
  if(el.matches?.('select')) window.TRACSDropdowns?.syncSelect(el);
}
function removeRow(sel){
  const el=document.querySelector(sel);
  if(el){el.style.cssText='opacity:0;transition:opacity .18s';setTimeout(()=>el.remove(),180);}
}
function _reload(){reloadAfterToast();}
function escHtml(value=''){
  return String(value).replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}
function jsAttr(value=''){
  return JSON.stringify(String(value)).replace(/"/g,'&quot;');
}
function formatBytes(bytes=0){
  const size=Number(bytes)||0;
  if(size<1024)return `${size} B`;
  if(size<1024*1024)return `${(size/1024).toFixed(1)} KB`;
  return `${(size/(1024*1024)).toFixed(1)} MB`;
}

/* ── CASE CRUD ────────────────────────────────────────── */
const CASE_ATTACHMENT_MAX = 5 * 1024 * 1024;
const CASE_ATTACHMENT_TYPES = new Set(['image/jpeg','image/png','image/webp']);
let caseSelectedAttachments = [];
let caseRemovedAttachmentIds = new Set();
let currentCaseTicketId = 0;
let currentCaseTicketData = null;
const caseStatusPendingIds = new Set();

const CASE_STATUS_META = {
  active: ['b-active','Active'],
  in_progress: ['b-in_progress','In Progress'],
  pending: ['b-pending','Pending'],
  stuck: ['b-stuck','Stuck'],
  on_hold: ['b-hold','On Hold'],
  completed: ['b-resolved','Resolved']
};
const CASE_BOARD_COLUMN_META = {
  attention: {label:'Need Attention',status:'active'},
  in_progress: {label:'In Progress',status:'in_progress'},
  waiting: {label:'Waiting / Stuck',status:'stuck'},
  on_hold: {label:'On Hold',status:'on_hold'},
  resolved: {label:'Resolved',status:'completed'}
};
const CASE_FILTER_LABELS = {
  all:'All',
  active:'Active',
  in_progress:'In Progress',
  critical:'Critical',
  stuck:'Stuck',
  on_hold:'On Hold',
  overdue:'Overdue'
};
const CASE_SORT_MODES = new Set(['operational','priority','overdue','next_check','created','updated','case_number']);
const caseBoardState = {
  rawCases: [],
  filteredCases: [],
  filter: 'all',
  query: '',
  sort: 'operational',
  draggedId: 0,
  initialized: false
};
function caseStatusMeta(status='pending'){
  return CASE_STATUS_META[String(status||'pending').toLowerCase()] || CASE_STATUS_META.pending;
}
function casePriorityClass(priority='low'){
  const p=String(priority||'low').toLowerCase();
  if(p==='critical')return 'b-critical';
  if(p==='high')return 'b-high';
  if(p==='medium')return 'b-medium';
  return 'b-low';
}
function caseBadgeHtml(status='pending'){
  const [klass,label]=caseStatusMeta(status);
  return `<span class="badge ${klass}"><span class="badge-dot"></span>${escHtml(label)}</span>`;
}
function casePriorityBadgeHtml(priority='low'){
  const p=String(priority||'low').toLowerCase();
  return `<span class="badge ${casePriorityClass(p)}">${escHtml(p.charAt(0).toUpperCase()+p.slice(1))}</span>`;
}
function caseCardToplineHtml(status='pending',priority='low'){
  const normalizedStatus=String(status||'pending').toLowerCase();
  if(normalizedStatus==='completed'){
    return '<span class="case-card-topline is-resolved"><span class="case-card-topline-dot"></span>Resolved</span>';
  }
  const normalizedPriority=String(priority||'low').toLowerCase();
  const severity=['critical','high','medium'].includes(normalizedPriority)?normalizedPriority:'normal';
  const label=severity==='normal'?(normalizedPriority==='low'?'Low':'Normal'):severity.charAt(0).toUpperCase()+severity.slice(1);
  return `<span class="case-card-topline is-${severity}"><span class="case-card-topline-dot"></span>${escHtml(label)}</span>`;
}
function formatCaseDate(value){
  if(!value)return '—';
  const date=new Date(String(value).replace(' ','T'));
  if(Number.isNaN(date.getTime()))return '—';
  return date.toLocaleString(undefined,{year:'numeric',month:'short',day:'2-digit',hour:'2-digit',minute:'2-digit'});
}
function caseReferenceItems(data){
  const text=`${data.title||''}\n${data.notes||''}`;
  const refs=[];
  const domains=[...new Set((text.match(/\b(?:[a-z0-9-]+\.)+[a-z]{2,}\b/gi)||[]).map(v=>v.toLowerCase()))];
  domains.slice(0,4).forEach(domain=>refs.push({type:'Domain',text:domain}));
  const ticket=text.match(/\b(?:ticket|task|finance|invoice|ref(?:erence)?)\s*#?:?\s*([A-Z0-9._-]{3,})/i);
  if(ticket)refs.push({type:'Reference',text:ticket[0]});
  return refs;
}
function renderCaseReferences(data){
  const refs=caseReferenceItems(data);
  if(!refs.length)return '<span class="case-ticket-empty">No related domain, finance, or task reference found.</span>';
  return refs.map(ref=>`<span class="case-ticket-ref-chip">${escHtml(ref.type)}: ${escHtml(ref.text)}</span>`).join('');
}
function caseHistoryItems(notes=''){
  const lines=String(notes||'').split(/\r?\n/).map(line=>line.trim()).filter(Boolean);
  const history=lines.filter(line=>/^\[[^\]]+\]|follow|update|resolved|checked|mom|task|ticket/i.test(line));
  return history.length?history:lines.slice(1);
}
function renderCaseHistory(notes='',activity=[]){
  const activityItems=Array.isArray(activity) ? activity : [];
  if(activityItems.length){
    return activityItems.map(item=>`
      <div class="case-ticket-log-item">
        <span class="case-ticket-log-dot"></span>
        <span><strong>${escHtml(item.actor_name||'System')}</strong> · ${escHtml(formatCaseDate(item.created_at))}<br>${escHtml(item.description||item.action||'Case updated')}</span>
      </div>
    `).join('');
  }
  const items=caseHistoryItems(notes);
  if(!items.length)return '<span class="case-ticket-empty">No follow-up history available.</span>';
  return items.map(item=>`<div class="case-ticket-log-item"><span class="case-ticket-log-dot"></span><span>${escHtml(item)}</span></div>`).join('');
}
function caseTimelineIndex(status='pending'){
  const s=String(status||'pending').toLowerCase();
  if(['completed','complete','done','resolved','closed'].includes(s))return 4;
  if(['on_hold','pending','stuck','waiting','waiting_external','waiting_approval','blocked'].includes(s))return 3;
  if(['in_progress','processing','investigating','checking'].includes(s))return 2;
  return 1;
}
function caseTimelineProgress(status='pending'){
  return Math.max(0,Math.min(100,(caseTimelineIndex(status) / 4) * 100));
}
function caseTimelineResolved(status='pending'){
  return caseTimelineIndex(status)>=4;
}
function renderCaseTimeline(status='pending'){
  const labels=['Created','Assigned','In Progress','Waiting','Resolved'];
  const current=caseTimelineIndex(status);
  const steps=labels.map((label,index)=>{
    const state=index<current?'is-complete':(index===current?'is-current':'is-upcoming');
    const edge=index===0?' is-first':(index===labels.length-1?' is-last':'');
    const progress=index/(labels.length-1);
    const offset=(20*(1-(progress*2))).toFixed(2);
    return `<div class="case-ticket-step ${state}${edge}" style="--step-left:calc(${(progress*100).toFixed(2)}% ${offset>=0?'+':'-'} ${Math.abs(offset)}px)"><span class="case-ticket-dot"></span><span>${escHtml(label)}</span></div>`;
  }).join('');
  return `<div class="case-ticket-track" aria-hidden="true"><span class="case-ticket-track-fill"><span class="case-ticket-track-sweep"></span></span></div>${steps}`;
}

function caseAttachmentEls(){
  return {
    input: document.getElementById('caseAttachments'),
    drop: document.getElementById('caseUploadDrop'),
    status: document.getElementById('caseUploadStatus'),
    selected: document.getElementById('caseAttachmentPreview'),
    existing: document.getElementById('caseExistingAttachments'),
    save: document.getElementById('caseSaveBtn')
  };
}
function caseSetUploadStatus(message='',type=''){
  const el=caseAttachmentEls().status;
  if(!el)return;
  el.textContent=message;
  el.className=`case-upload-status ${type||''}`.trim();
}
function caseValidateAttachment(file){
  if(!file || !file.name)return 'Choose a valid image.';
  if(file.size<=0)return `${file.name} is empty.`;
  if(file.size>CASE_ATTACHMENT_MAX)return `${file.name} is larger than 5MB.`;
  if(!CASE_ATTACHMENT_TYPES.has(file.type))return `${file.name} must be JPG, JPEG, PNG, or WEBP.`;
  return '';
}
function caseAddAttachmentFiles(files){
  const incoming=Array.from(files||[]);
  const errors=[];
  incoming.forEach(file=>{
    const err=caseValidateAttachment(file);
    if(err){errors.push(err);return;}
    const duplicate=caseSelectedAttachments.some(item=>item.file.name===file.name && item.file.size===file.size && item.file.lastModified===file.lastModified);
    if(!duplicate)caseSelectedAttachments.push({id:crypto.randomUUID?.()||String(Date.now()+Math.random()),file,url:URL.createObjectURL(file)});
  });
  renderCaseSelectedAttachments();
  if(errors.length)caseSetUploadStatus(errors[0],'error');
  else if(incoming.length)caseSetUploadStatus(`${caseSelectedAttachments.length} image${caseSelectedAttachments.length===1?'':'s'} ready to upload.`,'ok');
}
function clearCaseAttachmentState(){
  caseSelectedAttachments.forEach(item=>{try{URL.revokeObjectURL(item.url);}catch(e){}});
  caseSelectedAttachments=[];
  caseRemovedAttachmentIds=new Set();
  const els=caseAttachmentEls();
  if(els.input)els.input.value='';
  if(els.selected)els.selected.innerHTML='';
  if(els.existing)els.existing.innerHTML='';
  caseSetUploadStatus('');
}
function removeCaseSelectedAttachment(id){
  const item=caseSelectedAttachments.find(entry=>entry.id===id);
  if(item){try{URL.revokeObjectURL(item.url);}catch(e){}}
  caseSelectedAttachments=caseSelectedAttachments.filter(entry=>entry.id!==id);
  renderCaseSelectedAttachments();
  caseSetUploadStatus(caseSelectedAttachments.length?`${caseSelectedAttachments.length} image${caseSelectedAttachments.length===1?'':'s'} ready to upload.`:'');
}
function renderCaseSelectedAttachments(){
  const el=caseAttachmentEls().selected;
  if(!el)return;
  el.innerHTML=caseSelectedAttachments.map(item=>`
    <div class="case-attachment-tile">
      <button class="case-attachment-thumb" type="button" onclick="openCaseImagePreview(${jsAttr(item.url)},${jsAttr(item.file.name)})">
        <img src="${item.url}" alt="${escHtml(item.file.name)}">
      </button>
      <div class="case-attachment-meta"><span title="${escHtml(item.file.name)}">${escHtml(item.file.name)}</span><small>${formatBytes(item.file.size)}</small></div>
      <button class="case-attachment-remove" type="button" onclick="removeCaseSelectedAttachment(${jsAttr(item.id)})" aria-label="Remove selected image"><i data-lucide="x" class="icon-xs"></i></button>
    </div>
  `).join('');
  tracsRefreshIcons(el);
}
function renderCaseExistingAttachments(attachments=[]){
  const el=caseAttachmentEls().existing;
  if(!el)return;
  const visible=attachments.filter(item=>!caseRemovedAttachmentIds.has(Number(item.id)));
  el.innerHTML=visible.map(item=>`
    <div class="case-attachment-tile existing">
      <button class="case-attachment-thumb" type="button" onclick="openCaseImagePreview(${jsAttr(item.image_url)},${jsAttr(item.original_filename||'Attachment')})">
        <img src="${item.thumbnail_url}" alt="${escHtml(item.original_filename||'Case attachment')}" loading="lazy">
      </button>
      <div class="case-attachment-meta"><span title="${escHtml(item.original_filename||'Attachment')}">${escHtml(item.original_filename||'Attachment')}</span><small>${formatBytes(Number(item.file_size)||0)}</small></div>
      <button class="case-attachment-remove" type="button" onclick="removeExistingCaseAttachment(${Number(item.id)})" aria-label="Remove attachment"><i data-lucide="trash-2" class="icon-xs"></i></button>
    </div>
  `).join('');
  tracsRefreshIcons(el);
}
function removeExistingCaseAttachment(id){
  caseRemovedAttachmentIds.add(Number(id));
  const existing=window.__caseExistingAttachments||[];
  renderCaseExistingAttachments(existing);
  caseSetUploadStatus('Attachment will be removed when you save.','warn');
}
function openCaseImagePreview(src,title='Attachment'){
  const modal=document.getElementById('caseImageModal');
  const img=document.getElementById('caseImagePreviewFull');
  const label=document.getElementById('caseImageTitle');
  const open=document.getElementById('caseImageOpen');
  if(!modal||!img)return;
  img.src=src;
  if(label)label.textContent=title;
  if(open)open.href=src;
  modal.classList.remove('hidden');
}
function closeCaseImagePreview(){
  const modal=document.getElementById('caseImageModal');
  const img=document.getElementById('caseImagePreviewFull');
  if(modal)modal.classList.add('hidden');
  if(img)img.src='';
}
function closeCaseTicketMore(){
  const menu=document.getElementById('caseTicketMoreMenu');
  const btn=document.getElementById('caseTicketMoreBtn');
  if(menu)menu.classList.remove('is-open');
  if(btn)btn.setAttribute('aria-expanded','false');
}
function toggleCaseTicketMore(event){
  event?.preventDefault?.();
  event?.stopPropagation?.();
  const menu=document.getElementById('caseTicketMoreMenu');
  const btn=document.getElementById('caseTicketMoreBtn');
  if(!menu)return;
  const open=!menu.classList.contains('is-open');
  menu.classList.toggle('is-open',open);
  if(btn)btn.setAttribute('aria-expanded',open?'true':'false');
}
document.addEventListener('click',event=>{
  if(event.target instanceof Element && event.target.closest('#caseTicketMoreMenu'))return;
  closeCaseTicketMore();
});
function casePayloadFormData(id=''){
  const fd=new FormData();
  if(id)fd.append('id',id);
  fd.append('title',val('caseTitle').trim());
  fd.append('status',val('caseStatus'));
  fd.append('priority',val('casePriority'));
  fd.append('next_check_at',val('caseNextCheck'));
  fd.append('notes',val('caseNotes'));
  caseSelectedAttachments.forEach(item=>fd.append('attachments[]',item.file,item.file.name));
  Array.from(caseRemovedAttachmentIds).forEach(removeId=>fd.append('remove_attachment_ids[]',String(removeId)));
  return fd;
}
async function caseApiWithUploads(url,formData){
  try{
    const r=await fetch(url,{method:'POST',body:formData});
    return await r.json();
  }catch(e){return{success:false,message:'Network error'};}
}
function initCaseAttachmentUpload(){
  const els=caseAttachmentEls();
  if(!els.input||els.input.dataset.ready)return;
  els.input.dataset.ready='1';
  els.input.addEventListener('change',()=>caseAddAttachmentFiles(els.input.files));
  if(els.drop){
    ['dragenter','dragover'].forEach(evt=>els.drop.addEventListener(evt,e=>{e.preventDefault();els.drop.classList.add('drag');}));
    ['dragleave','drop'].forEach(evt=>els.drop.addEventListener(evt,e=>{e.preventDefault();els.drop.classList.remove('drag');}));
    els.drop.addEventListener('drop',e=>caseAddAttachmentFiles(e.dataTransfer?.files));
  }
}
function openNewCase(defaultStatus='active'){
  initCaseAttachmentUpload();
  document.getElementById('caseModalTitle').textContent='New Case';
  ['caseId','caseTitle','caseNextCheck','caseNotes'].forEach(id=>setVal(id,''));
  const status=Object.prototype.hasOwnProperty.call(CASE_STATUS_META,String(defaultStatus)) ? String(defaultStatus) : 'active';
  setVal('caseStatus',status); setVal('casePriority','medium');
  window.__caseExistingAttachments=[];
  clearCaseAttachmentState();
  openModal('case');
}
async function openEditCase(id){
  initCaseAttachmentUpload();
  const row=document.querySelector(`[data-cid="${id}"]`);
  const caseItem=caseBoardState.rawCases.find(item=>Number(item.id)===Number(id));
  if(!row && !caseItem)return;
  document.getElementById('caseModalTitle').textContent='Edit Case';
  setVal('caseId',id);
  setVal('caseTitle',row?.dataset.title||caseItem?.title||'');
  setVal('caseStatus',row?.dataset.status||caseItem?.status||'active');
  setVal('casePriority',row?.dataset.priority||caseItem?.priority||'medium');
  setVal('caseNextCheck',row?.dataset.next||caseItem?.next_check_local||'');
  setVal('caseNotes',row?.dataset.notes||caseItem?.notes||'');
  clearCaseAttachmentState();
  caseSetUploadStatus('Loading attachments...');
  openModal('case');
  const d=await api(API.CASE.GET,{id});
  if(d.success){
    const data=d.data||{};
    setVal('caseTitle',data.title||row?.dataset.title||caseItem?.title||'');
    setVal('caseStatus',data.status||row?.dataset.status||caseItem?.status||'active');
    setVal('casePriority',data.priority||row?.dataset.priority||caseItem?.priority||'medium');
    setVal('caseNextCheck',data.next_check_at?data.next_check_at.replace(' ','T').slice(0,16):(row?.dataset.next||caseItem?.next_check_local||''));
    setVal('caseNotes',data.notes||'');
    window.__caseExistingAttachments=data.attachments||[];
    renderCaseExistingAttachments(window.__caseExistingAttachments);
    caseSetUploadStatus('');
  }else{
    caseSetUploadStatus(d.message||'Could not load attachments.','error');
  }
}
async function openCaseTicket(id){
  currentCaseTicketId=Number(id)||0;
  currentCaseTicketData=null;
  if(!currentCaseTicketId)return;
  const titleEl=document.getElementById('caseTicketTitle');
  const refEl=document.getElementById('caseTicketRef');
  const badgesEl=document.getElementById('caseTicketBadges');
  const timelineEl=document.getElementById('caseTicketTimeline');
  if(titleEl)titleEl.textContent='Loading case...';
  if(refEl)refEl.textContent=`Case #${currentCaseTicketId}`;
  if(badgesEl)badgesEl.innerHTML='';
  if(timelineEl){
    timelineEl.classList.remove('is-resolved');
    timelineEl.innerHTML='';
  }
  openModal('caseTicket');

  const d=await api(API.CASE.GET,{id:currentCaseTicketId});
  if(!d.success){
    toast(d.message||'Could not load case','error');
    closeModal('caseTicket');
    return;
  }
  const data=d.data||{};
  currentCaseTicketData=data;
  const status=String(data.status||'pending').toLowerCase();
  const priority=String(data.priority||'low').toLowerCase();
  if(titleEl)titleEl.textContent=data.title||`Case #${currentCaseTicketId}`;
  if(refEl)refEl.textContent=`Case #${currentCaseTicketId}`;
  if(badgesEl)badgesEl.innerHTML=`${caseBadgeHtml(status)}${casePriorityBadgeHtml(priority)}`;
  const pic=data.creator_name||data.created_by_name||data.assigned_user||data.pic||'Unassigned';
  const setText=(id,value)=>{const el=document.getElementById(id);if(el)el.textContent=value||'—';};
  setText('caseTicketPic',pic);
  setText('caseTicketCreated',formatCaseDate(data.created_at));
  setText('caseTicketUpdated',formatCaseDate(data.updated_at));
  setText('caseTicketNext',formatCaseDate(data.next_check_at));
  setText('caseTicketNotes',data.notes?.trim()||'No description or issue detail recorded.');
  if(timelineEl){
    timelineEl.style.setProperty('--timeline-progress', `${caseTimelineProgress(status).toFixed(2)}%`);
    timelineEl.classList.toggle('is-resolved',caseTimelineResolved(status));
    timelineEl.innerHTML=renderCaseTimeline(status);
  }
  const historyEl=document.getElementById('caseTicketHistory');
  if(historyEl)historyEl.innerHTML=renderCaseHistory(data.notes,data.activity);
  const refsEl=document.getElementById('caseTicketReferences');
  if(refsEl)refsEl.innerHTML=renderCaseReferences(data);
  setText('caseTicketActionNote',status==='completed'?'Resolved case preview':'View-first ticket preview');

  const attachments=document.getElementById('caseTicketAttachments');
  const empty=document.getElementById('caseTicketAttachmentEmpty');
  const list=Array.isArray(data.attachments)?data.attachments:[];
  if(attachments){
    attachments.innerHTML=list.map(item=>`
      <div class="case-attachment-tile">
        <button class="case-attachment-thumb" type="button" onclick="openCaseImagePreview(${jsAttr(item.image_url)},${jsAttr(item.original_filename||'Attachment')})">
          <img src="${escHtml(item.thumbnail_url||item.image_url||'')}" alt="${escHtml(item.original_filename||'Case attachment')}" loading="lazy">
        </button>
        <div class="case-attachment-meta"><span title="${escHtml(item.original_filename||'Attachment')}">${escHtml(item.original_filename||'Attachment')}</span><small>${formatBytes(Number(item.file_size)||0)}</small></div>
      </div>
    `).join('');
    tracsRefreshIcons(attachments);
  }
  if(empty)empty.hidden=list.length>0;

  const caps=window.TRACS_CASE_CAPS||{};
  const canManage=Boolean(data.can_manage ?? caps.canManage);
  const canDelete=Boolean(data.can_delete ?? caps.canDelete);
  const resolveBtn=document.getElementById('caseTicketResolveBtn');
  const inProgressBtn=document.getElementById('caseTicketInProgressBtn');
  const stuckBtn=document.getElementById('caseTicketStuckBtn');
  const holdBtn=document.getElementById('caseTicketHoldBtn');
  const editBtn=document.getElementById('caseTicketEditBtn');
  const deleteBtn=document.getElementById('caseTicketDeleteBtn');
  const moreMenu=document.getElementById('caseTicketMoreMenu');
  if(resolveBtn)resolveBtn.hidden=!canManage || status==='completed';
  if(inProgressBtn)inProgressBtn.hidden=!canManage || status==='in_progress' || status==='completed';
  if(stuckBtn)stuckBtn.hidden=!canManage || status==='stuck' || status==='completed';
  if(holdBtn)holdBtn.hidden=!canManage || status==='on_hold' || status==='completed';
  if(editBtn)editBtn.hidden=!canManage;
  if(deleteBtn)deleteBtn.hidden=!canDelete;
  if(moreMenu){
    closeCaseTicketMore();
    moreMenu.hidden=!((editBtn && !editBtn.hidden) || (deleteBtn && !deleteBtn.hidden));
  }
  const actionButtons=document.querySelector('#caseTicketModal .case-ticket-action-buttons');
  const actionBar=document.querySelector('#caseTicketModal .case-ticket-actions');
  const hasVisibleAction=[resolveBtn,inProgressBtn,stuckBtn,holdBtn].some(button=>button && !button.hidden);
  if(actionButtons)actionButtons.hidden=!hasVisibleAction;
  if(actionBar)actionBar.classList.toggle('is-note-only',!hasVisibleAction);
  tracsRefreshIcons(document.getElementById('caseTicketModal'));
}
function normalizeCaseText(value=''){
  return String(value??'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase();
}
function caseTimestamp(value,fallback=0){
  if(!value)return fallback;
  const parsed=new Date(String(value).replace(' ','T')).getTime();
  return Number.isFinite(parsed)?parsed:fallback;
}
function caseIsOverdue(caseItem){
  if(!caseItem || String(caseItem.status||'').toLowerCase()==='completed')return false;
  if(caseItem.overdue===true || caseItem.overdue===1 || caseItem.overdue==='1')return true;
  if(/^overdue\b/i.test(String(caseItem.time_until||'')))return true;
  const next=caseTimestamp(caseItem.next_check_at,0);
  return next>0 && next<Date.now();
}
function getWorkflowColumn(caseItem){
  const status=String(caseItem?.status||'pending').toLowerCase();
  if(status==='completed')return 'resolved';
  if(status==='on_hold')return 'on_hold';
  if(status==='in_progress')return 'in_progress';
  if(status==='stuck' || status==='pending')return 'waiting';
  return 'attention';
}
function caseWorkflowKey(status='pending'){
  return getWorkflowColumn({status});
}
function matchesSearch(caseItem,query){
  const needle=normalizeCaseText(query);
  if(!needle)return true;
  const status=String(caseItem?.status||'pending').toLowerCase();
  const fields=[
    caseItem?.title,
    caseItem?.id,
    `#${caseItem?.id||''}`,
    status,
    status.replace(/_/g,' '),
    caseStatusMeta(status)[1],
    caseItem?.priority,
    caseItem?.creator_name,
    caseItem?.created_by_name,
    caseItem?.assigned_agent,
    caseItem?.notes,
    caseItem?.description,
    caseItem?.next_check_at,
    caseItem?.next_check_display,
    caseItem?.time_until,
    caseItem?.client,
    caseItem?.domain,
    caseItem?.service
  ];
  return normalizeCaseText(fields.filter(Boolean).join(' ')).includes(needle);
}
function matchesFilter(caseItem,activeFilter='all'){
  const filter=String(activeFilter||'all').toLowerCase();
  const status=String(caseItem?.status||'pending').toLowerCase();
  const priority=String(caseItem?.priority||'low').toLowerCase();
  if(filter==='all')return true;
  if(filter==='active')return status==='active';
  if(filter==='in_progress')return status==='in_progress';
  if(filter==='critical')return priority==='critical' || status==='critical';
  if(filter==='stuck')return status==='stuck' || getWorkflowColumn(caseItem)==='waiting';
  if(filter==='on_hold')return status==='on_hold';
  if(filter==='overdue')return caseIsOverdue(caseItem);
  return true;
}
function caseComparePriority(a,b){
  const rank={critical:0,high:1,medium:2,low:3};
  return (rank[String(a?.priority||'low').toLowerCase()]??4)-(rank[String(b?.priority||'low').toLowerCase()]??4);
}
function caseCompareNextCheck(a,b){
  return caseTimestamp(a?.next_check_at,Number.MAX_SAFE_INTEGER)-caseTimestamp(b?.next_check_at,Number.MAX_SAFE_INTEGER);
}
function caseCompareUpdated(a,b){
  return caseTimestamp(b?.updated_at,0)-caseTimestamp(a?.updated_at,0);
}
function caseCompareOperational(a,b){
  const overdueDelta=Number(caseIsOverdue(b))-Number(caseIsOverdue(a));
  return overdueDelta
    || caseComparePriority(a,b)
    || caseCompareNextCheck(a,b)
    || caseCompareUpdated(a,b)
    || Number(b?.id||0)-Number(a?.id||0);
}
function sortCases(caseList,sortMode='operational'){
  const mode=CASE_SORT_MODES.has(sortMode)?sortMode:'operational';
  return [...caseList].sort((a,b)=>{
    if(mode==='priority')return caseComparePriority(a,b)||caseCompareOperational(a,b);
    if(mode==='overdue'){
      const overdueDelta=Number(caseIsOverdue(b))-Number(caseIsOverdue(a));
      return overdueDelta||caseCompareNextCheck(a,b)||caseComparePriority(a,b)||caseCompareUpdated(a,b);
    }
    if(mode==='next_check')return caseCompareNextCheck(a,b)||caseComparePriority(a,b)||caseCompareUpdated(a,b);
    if(mode==='created')return caseTimestamp(b?.created_at,0)-caseTimestamp(a?.created_at,0)||Number(b?.id||0)-Number(a?.id||0);
    if(mode==='updated')return caseCompareUpdated(a,b)||Number(b?.id||0)-Number(a?.id||0);
    if(mode==='case_number')return Number(b?.id||0)-Number(a?.id||0);
    return caseCompareOperational(a,b);
  });
}
function groupCasesByWorkflow(caseList){
  const groups=Object.fromEntries(Object.keys(CASE_BOARD_COLUMN_META).map(key=>[key,[]]));
  caseList.forEach(caseItem=>groups[getWorkflowColumn(caseItem)].push(caseItem));
  return groups;
}
function caseFiltersActive(){
  return caseBoardState.filter!=='all' || normalizeCaseText(caseBoardState.query)!=='';
}
function caseDateOnly(value,fallback='—'){
  if(!value)return fallback;
  const date=new Date(String(value).replace(' ','T'));
  if(Number.isNaN(date.getTime()))return fallback;
  return date.toLocaleDateString(undefined,{year:'numeric',month:'short',day:'2-digit'});
}
function caseDataAttributes(caseItem){
  return [
    `data-cid="${Number(caseItem.id)||0}"`,
    `data-title="${escHtml(caseItem.title||'')}"`,
    `data-status="${escHtml(caseItem.status||'pending')}"`,
    `data-priority="${escHtml(caseItem.priority||'low')}"`,
    `data-next="${escHtml(caseItem.next_check_local||'')}"`,
    `data-notes="${escHtml(caseItem.notes||'')}"`,
    `data-created="${escHtml(caseItem.created_at||'')}"`,
    `data-updated="${escHtml(caseItem.updated_at||'')}"`,
    `data-overdue="${caseIsOverdue(caseItem)?'1':'0'}"`
  ].join(' ');
}
function caseCardHtml(caseItem){
  const id=Number(caseItem.id)||0;
  const status=String(caseItem.status||'pending').toLowerCase();
  const priority=String(caseItem.priority||'low').toLowerCase();
  const overdue=caseIsOverdue(caseItem);
  const attachments=Number(caseItem.attachment_count)||0;
  const notes=String(caseItem.notes||'').trim();
  const creator=caseItem.creator_name||caseItem.created_by_name||caseItem.assigned_agent||'System';
  const canManage=Boolean(window.TRACS_CASE_CAPS?.canManage);
  const canDelete=Boolean(window.TRACS_CASE_CAPS?.canDelete);
  const pending=caseStatusPendingIds.has(id);
  return `
    <article class="case-kanban-card ${overdue?'is-overdue':''} ${status==='completed'?'is-resolved':''} ${pending?'is-status-updating':''}"
      ${caseDataAttributes(caseItem)}
      draggable="${canManage && !pending?'true':'false'}"
      aria-grabbed="false">
      <div class="case-card-click" role="button" tabindex="0" onclick="openCaseTicket(${id})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openCaseTicket(${id})}">
        <div class="case-card-header">
          <span class="case-card-number">#${id}</span>
          ${caseCardToplineHtml(status,priority)}
          ${canManage?'<i data-lucide="grip-horizontal" class="case-card-grip"></i>':''}
        </div>
        <h3>${escHtml(caseItem.title||'Untitled case')}</h3>
        <div class="case-card-badges">
          ${caseBadgeHtml(status).replace('<span class="badge ','<span data-card-status-badge class="badge ')}
        </div>
        <div class="case-card-meta">
          <span><i data-lucide="user-round" class="icon-xs"></i>${escHtml(creator)}</span>
          <span><i data-lucide="calendar-days" class="icon-xs"></i>${escHtml(caseItem.created_display||caseDateOnly(caseItem.created_at))}</span>
        </div>
        <div class="case-card-due">
          <span>Next check</span><strong>${escHtml(caseItem.next_check_display||formatCaseDate(caseItem.next_check_at))}</strong>
          <em class="${overdue?'is-overdue':''}">${escHtml(caseItem.time_until||'—')}</em>
        </div>
        ${attachments>0 || notes?`
          <div class="case-card-signals">
            ${notes?'<span title="Notes available"><i data-lucide="notebook-text" class="icon-xs"></i>Notes</span>':''}
            ${attachments>0?`<span title="${attachments} attachment${attachments===1?'':'s'}"><i data-lucide="paperclip" class="icon-xs"></i>${attachments}</span>`:''}
          </div>
        `:''}
      </div>
      <details class="case-card-menu" onclick="event.stopPropagation()" onkeydown="event.stopPropagation()">
        <summary title="Case actions" aria-label="Case actions"><i data-lucide="more-horizontal" class="icon-sm"></i></summary>
        <div class="case-card-popover">
          <button type="button" onclick="openCaseTicket(${id})"><i data-lucide="panel-right-open" class="icon-sm"></i>View detail</button>
          ${canManage?`
            <button type="button" onclick="openEditCase(${id})"><i data-lucide="pencil" class="icon-sm"></i>Edit / add note</button>
            <button type="button" onclick="updateCaseStatusImmediately(${id},'in_progress','quick_action')"><i data-lucide="loader-circle" class="icon-sm"></i>Mark In Progress</button>
            <button type="button" onclick="updateCaseStatusImmediately(${id},'stuck','quick_action')"><i data-lucide="pause-circle" class="icon-sm"></i>Move to Stuck</button>
            <button type="button" onclick="updateCaseStatusImmediately(${id},'on_hold','quick_action')"><i data-lucide="archive" class="icon-sm"></i>Move to On Hold</button>
            <button type="button" onclick="updateCaseStatusImmediately(${id},'completed','quick_action')"><i data-lucide="circle-check" class="icon-sm"></i>Resolve case</button>
          `:''}
          ${canDelete?`<button type="button" class="is-danger" onclick="deleteCase(${id},this)"><i data-lucide="trash-2" class="icon-sm"></i>Delete case</button>`:''}
        </div>
      </details>
    </article>
  `;
}
function caseTableRowHtml(caseItem){
  const id=Number(caseItem.id)||0;
  const status=String(caseItem.status||'pending').toLowerCase();
  const priority=String(caseItem.priority||'low').toLowerCase();
  const overdue=caseIsOverdue(caseItem);
  const attachments=Number(caseItem.attachment_count)||0;
  const creator=caseItem.creator_name||caseItem.created_by_name||caseItem.assigned_agent||'System';
  const canManage=Boolean(window.TRACS_CASE_CAPS?.canManage);
  const canDelete=Boolean(window.TRACS_CASE_CAPS?.canDelete);
  return `
    <tr class="case-click-row" ${caseDataAttributes(caseItem)} role="button" tabindex="0"
      onclick="openCaseTicket(${id})"
      onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openCaseTicket(${id})}">
      <td class="tracs-rownum">${id}</td>
      <td class="case-table-title">
        <strong title="${escHtml(caseItem.title||'')}">${escHtml(caseItem.title||'Untitled')}</strong>
        ${attachments>0?`<span class="case-attachment-count"><i data-lucide="image" class="icon-xs"></i>${attachments} attachment${attachments===1?'':'s'}</span>`:''}
        <span class="tracs-creator-meta"><i data-lucide="user-round" class="icon-xs"></i><span>Created by ${escHtml(creator)}</span></span>
      </td>
      <td>${caseBadgeHtml(status).replace('<span class="badge ','<span data-card-status-badge class="badge ')}</td>
      <td>${casePriorityBadgeHtml(priority)}</td>
      <td class="case-table-date">${escHtml(caseItem.next_check_display||formatCaseDate(caseItem.next_check_at))}</td>
      <td><span class="case-time ${overdue?'ov':''}">${escHtml(caseItem.time_until||'—')}</span></td>
      <td onclick="event.stopPropagation()" onkeydown="event.stopPropagation()">
        <details class="row-action-menu">
          <summary class="btn btn-ghost btn-icon" title="Actions" aria-label="Row actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
          <div class="row-action-popover">
            <button class="btn btn-ghost btn-sm" type="button" onclick="openCaseTicket(${id})">View detail</button>
            ${canManage?`<button class="btn btn-ghost btn-sm" type="button" onclick="openEditCase(${id})">Edit</button>`:''}
            ${canDelete?`<button class="btn btn-danger btn-sm" type="button" onclick="deleteCase(${id},this)">Delete</button>`:''}
          </div>
        </details>
      </td>
    </tr>
  `;
}
function caseColumnSummary(columnKey,items){
  const critical=items.filter(item=>String(item.priority||'').toLowerCase()==='critical').length;
  const overdue=items.filter(caseIsOverdue).length;
  const high=items.filter(item=>['critical','high'].includes(String(item.priority||'').toLowerCase())).length;
  const stuck=items.filter(item=>String(item.status||'').toLowerCase()==='stuck').length;
  if(columnKey==='attention')return `${critical} critical · ${overdue} overdue`;
  if(columnKey==='in_progress')return `${high} high priority`;
  if(columnKey==='waiting')return `${stuck} stuck · ${Math.max(0,items.length-stuck)} pending`;
  if(columnKey==='on_hold')return `${items.length} paused`;
  return `${items.length} completed`;
}
function caseCaptureScrollState(){
  const board=document.querySelector('.case-kanban');
  return {
    boardLeft:board?.scrollLeft||0,
    columns:Object.fromEntries(Array.from(document.querySelectorAll('[data-case-column]')).map(column=>[
      column.dataset.caseColumn,
      column.querySelector('[data-case-dropzone]')?.scrollTop||0
    ]))
  };
}
function caseRestoreScrollState(state){
  if(!state)return;
  const board=document.querySelector('.case-kanban');
  if(board)board.scrollLeft=state.boardLeft||0;
  Object.entries(state.columns||{}).forEach(([key,top])=>{
    const list=document.querySelector(`[data-case-column="${key}"] [data-case-dropzone]`);
    if(list)list.scrollTop=Number(top)||0;
  });
}
function renderBoard(filteredCases=caseBoardState.filteredCases){
  const visibleGroups=groupCasesByWorkflow(filteredCases);
  const totalGroups=groupCasesByWorkflow(caseBoardState.rawCases);
  const active=caseFiltersActive();
  Object.keys(CASE_BOARD_COLUMN_META).forEach(key=>{
    const column=document.querySelector(`[data-case-column="${key}"]`);
    if(!column)return;
    const items=visibleGroups[key];
    const originalTotal=totalGroups[key].length;
    const count=column.querySelector('[data-column-count]');
    const summary=column.querySelector('[data-column-summary]');
    const list=column.querySelector('[data-case-dropzone]');
    if(count){
      count.textContent=active?`${items.length} / ${originalTotal}`:String(items.length);
      count.title=active?`${items.length} visible of ${originalTotal} total`:`${items.length} cases`;
    }
    if(summary)summary.textContent=caseColumnSummary(key,items);
    if(list){
      list.innerHTML=items.length
        ? items.map(caseCardHtml).join('')
        : `<div class="case-column-empty" data-column-empty><i data-lucide="inbox" class="icon-sm"></i><span data-column-empty-label>${active?'No matching cases':'No cases in this stage'}</span></div>`;
    }
  });
}
function renderTable(filteredCases=caseBoardState.filteredCases){
  const body=document.getElementById('caseTableBody');
  const empty=document.getElementById('caseTableEmpty');
  const wrap=body?.closest('.table-wrap');
  if(body)body.innerHTML=filteredCases.map(caseTableRowHtml).join('');
  if(wrap)wrap.hidden=filteredCases.length===0;
  if(empty){
    empty.hidden=filteredCases.length>0;
    const title=empty.querySelector('.empty-t');
    const subtitle=empty.querySelector('.empty-s');
    const clear=empty.querySelector('[data-case-clear-filters]');
    if(title)title.textContent=caseFiltersActive()?'No cases match your search/filter':'No cases found';
    if(subtitle)subtitle.textContent=caseFiltersActive()?'Clear filters to return to the complete case list.':'Create a case to begin tracking operational work.';
    if(clear)clear.hidden=!caseFiltersActive();
  }
}
function updateBoardCounters(){
  const visible=caseBoardState.filteredCases;
  const total=caseBoardState.rawCases.length;
  const active=caseFiltersActive();
  const critical=visible.filter(item=>String(item.priority||'').toLowerCase()==='critical').length;
  const inProgress=visible.filter(item=>String(item.status||'').toLowerCase()==='in_progress').length;
  const onHold=visible.filter(item=>String(item.status||'').toLowerCase()==='on_hold').length;
  const waiting=visible.filter(item=>getWorkflowColumn(item)==='waiting').length;
  const overdue=visible.filter(caseIsOverdue).length;
  const boardCount=document.getElementById('caseBoardCount');
  const tableCount=document.getElementById('caseTableCount');
  const summary=document.getElementById('casePageSummary');
  const health=document.getElementById('caseQueueHealth');
  const healthDot=document.querySelector('.case-queue-health > span');
  const activeState=document.getElementById('caseActiveState');
  const activeStateText=document.getElementById('caseActiveStateText');
  const countText=active?`${visible.length} of ${total} cases shown`:`${total} cases shown`;
  if(boardCount)boardCount.textContent=countText;
  if(tableCount)tableCount.textContent=active?`${visible.length} of ${total} shown`:`${total} shown`;
  if(summary)summary.textContent=active
    ? `${visible.length} of ${total} cases · ${critical} critical · ${inProgress} in progress · ${onHold} on hold`
    : `${total} total · ${critical} critical · ${inProgress} in progress · ${onHold} on hold`;
  if(health)health.textContent=`${overdue} overdue · ${waiting} waiting`;
  healthDot?.classList.toggle('is-alert',overdue>0);
  if(activeState){
    activeState.hidden=!active;
    if(activeStateText){
      const parts=[`Showing ${visible.length} of ${total} cases`];
      if(caseBoardState.filter!=='all')parts.push(`Filter: ${CASE_FILTER_LABELS[caseBoardState.filter]||caseBoardState.filter}`);
      if(normalizeCaseText(caseBoardState.query))parts.push(`Search: ${caseBoardState.query.trim()}`);
      activeStateText.textContent=parts.join(' · ');
    }
  }
  document.querySelectorAll('[data-case-filter-option]').forEach(button=>{
    const selected=button.dataset.caseFilterOption===caseBoardState.filter;
    button.classList.toggle('active',selected);
    button.setAttribute('aria-pressed',selected?'true':'false');
  });
  const workspace=document.getElementById('caseWorkspace');
  if(workspace){
    workspace.dataset.caseFilter=caseBoardState.filter;
    workspace.dataset.caseQuery=caseBoardState.query;
    workspace.dataset.caseSort=caseBoardState.sort;
    workspace.dataset.total=String(total);
  }
  const exportFilter=document.getElementById('caseExportFilter');
  const exportQuery=document.getElementById('caseExportQuery');
  if(exportFilter)exportFilter.value=caseBoardState.filter;
  if(exportQuery)exportQuery.value=caseBoardState.query.trim();
}
function caseSyncUrl(){
  const url=new URL(window.location.href);
  if(caseBoardState.filter==='all')url.searchParams.delete('f');
  else url.searchParams.set('f',caseBoardState.filter);
  const query=caseBoardState.query.trim();
  if(query)url.searchParams.set('q',query);
  else url.searchParams.delete('q');
  if(caseBoardState.sort==='operational')url.searchParams.delete('sort');
  else url.searchParams.set('sort',caseBoardState.sort);
  history.replaceState(null,'',`${url.pathname}${url.search}${url.hash}`);
}
function bindCaseCardMenus(){
  document.querySelectorAll('.case-card-menu:not([data-case-menu-bound])').forEach(menu=>{
    menu.dataset.caseMenuBound='1';
    menu.addEventListener('toggle',()=>{
      if(!menu.open)return;
      document.querySelectorAll('.case-card-menu[open]').forEach(other=>{if(other!==menu)other.removeAttribute('open');});
      const trigger=menu.querySelector('summary');
      const popover=menu.querySelector('.case-card-popover');
      if(!trigger||!popover)return;
      const rect=trigger.getBoundingClientRect();
      const width=190;
      const estimatedHeight=Math.min(popover.scrollHeight||300,360);
      const left=Math.max(8,Math.min(window.innerWidth-width-8,rect.right-width));
      const placeAbove=rect.bottom+estimatedHeight+8>window.innerHeight && rect.top>estimatedHeight;
      const top=placeAbove?Math.max(8,rect.top-estimatedHeight-6):Math.min(window.innerHeight-estimatedHeight-8,rect.bottom+6);
      popover.style.left=`${left}px`;
      popover.style.top=`${Math.max(8,top)}px`;
    });
  });
}
function renderCaseWorkspace({preserveScroll=true,syncUrl=true}={}){
  if(!caseBoardState.initialized)return;
  const scrollState=preserveScroll?caseCaptureScrollState():null;
  const searched=caseBoardState.rawCases.filter(item=>matchesSearch(item,caseBoardState.query));
  const filtered=searched.filter(item=>matchesFilter(item,caseBoardState.filter));
  caseBoardState.filteredCases=sortCases(filtered,caseBoardState.sort);
  renderBoard(caseBoardState.filteredCases);
  renderTable(caseBoardState.filteredCases);
  updateBoardCounters();
  bindCaseCardMenus();
  tracsRefreshIcons(document.getElementById('caseWorkspace'));
  if(scrollState)requestAnimationFrame(()=>caseRestoreScrollState(scrollState));
  if(syncUrl)caseSyncUrl();
}
function clearCaseFilters(){
  caseBoardState.filter='all';
  caseBoardState.query='';
  const input=document.getElementById('caseSearchInput');
  if(input)input.value='';
  renderCaseWorkspace({preserveScroll:false});
}
function caseRefreshOperationalSummary(){
  if(caseBoardState.initialized)updateBoardCounters();
}
function caseRefreshColumnMeta(){
  if(caseBoardState.initialized)renderCaseWorkspace();
}
function applyCaseStatusUpdate(data){
  const id=Number(data?.id)||0;
  const status=String(data?.status||'').toLowerCase();
  const caseItem=caseBoardState.rawCases.find(item=>Number(item.id)===id);
  if(!caseItem || !CASE_STATUS_META[status])return;
  Object.assign(caseItem,data,{id,status});
  caseItem.overdue=caseIsOverdue(caseItem);
  renderCaseWorkspace();
  if(currentCaseTicketId===id && !document.getElementById('caseTicketModal')?.classList.contains('hidden'))openCaseTicket(id);
}
function closeCaseCardMenus(){
  document.querySelectorAll('.case-card-menu[open]').forEach(menu=>menu.removeAttribute('open'));
}
async function updateCaseStatusImmediately(id,status,source='quick_action'){
  const caseId=Number(id);
  const targetStatus=String(status||'').toLowerCase();
  const caseItem=caseBoardState.rawCases.find(item=>Number(item.id)===caseId);
  if(!caseId || !caseItem || !CASE_STATUS_META[targetStatus] || caseStatusPendingIds.has(caseId))return;
  const previous={status:caseItem.status,overdue:caseItem.overdue,updated_at:caseItem.updated_at};
  if(String(previous.status||'').toLowerCase()===targetStatus){
    closeCaseCardMenus();
    return;
  }

  caseStatusPendingIds.add(caseId);
  closeCaseCardMenus();
  caseItem.status=targetStatus;
  if(targetStatus==='completed')caseItem.overdue=false;
  renderCaseWorkspace();

  try{
    const d=await api(API.CASE.STATUS,{id:caseId,status:targetStatus,source:String(source||'manual'),note:''});
    if(!d?.success)throw {message:d?.message,status:d?.status};
    Object.assign(caseItem,d.data||{},{id:caseId,status:targetStatus});
    caseItem.overdue=caseIsOverdue(caseItem);
    showToast(d.message||'Case status updated.','success',{context:'page'});
    if(currentCaseTicketId===caseId && !document.getElementById('caseTicketModal')?.classList.contains('hidden'))openCaseTicket(caseId);
  }catch(error){
    Object.assign(caseItem,previous);
    const ticketModal=document.getElementById('caseTicketModal');
    const ticketOpen=currentCaseTicketId===caseId && ticketModal && !ticketModal.classList.contains('hidden');
    handleRequestError(error,ticketOpen?'modal':'page','The case status could not be updated. Please try again.');
  }finally{
    caseStatusPendingIds.delete(caseId);
    renderCaseWorkspace();
  }
}
function requestCaseTicketStatus(status){
  if(!currentCaseTicketId)return;
  updateCaseStatusImmediately(currentCaseTicketId,status,'drawer_action');
}
function setCaseWorkspaceView(view,remember=true){
  const selected=view==='table'?'table':'board';
  document.querySelectorAll('[data-case-view-panel]').forEach(panel=>{panel.hidden=panel.dataset.caseViewPanel!==selected;});
  document.querySelectorAll('[data-case-view]').forEach(button=>{
    const active=button.dataset.caseView===selected;
    button.classList.toggle('is-active',active);
    button.setAttribute('aria-pressed',active?'true':'false');
  });
  if(remember){
    try{localStorage.setItem('tracs:cases:view',selected);}catch(e){}
  }
}
function initCaseBoard(){
  const workspace=document.getElementById('caseWorkspace');
  const dataset=document.getElementById('caseDataset');
  if(!workspace||!dataset)return;
  try{
    const parsed=JSON.parse(dataset.textContent||'[]');
    caseBoardState.rawCases=Array.isArray(parsed)?parsed.map(item=>({
      ...item,
      id:Number(item.id)||0,
      status:String(item.status||'pending').toLowerCase(),
      priority:String(item.priority||'low').toLowerCase(),
      attachment_count:Number(item.attachment_count)||0,
      overdue:caseIsOverdue(item)
    })).filter(item=>item.id>0):[];
  }catch(error){
    console.error('Unable to load case workspace data:',error);
    caseBoardState.rawCases=[];
  }
  caseBoardState.filter=CASE_FILTER_LABELS[workspace.dataset.caseFilter]?workspace.dataset.caseFilter:'all';
  caseBoardState.query=workspace.dataset.caseQuery||'';
  caseBoardState.sort=CASE_SORT_MODES.has(workspace.dataset.caseSort)?workspace.dataset.caseSort:'operational';
  caseBoardState.initialized=true;

  const search=document.getElementById('caseSearchInput');
  const searchForm=document.getElementById('caseSearchForm');
  const sort=document.getElementById('caseSort');
  let searchTimer=0;
  searchForm?.addEventListener('submit',event=>{
    event.preventDefault();
    window.clearTimeout(searchTimer);
    caseBoardState.query=search?.value.trim()||'';
    renderCaseWorkspace({preserveScroll:false});
  });
  search?.addEventListener('input',()=>{
    window.clearTimeout(searchTimer);
    searchTimer=window.setTimeout(()=>{
      caseBoardState.query=search.value.trim();
      renderCaseWorkspace({preserveScroll:false});
    },100);
  });
  document.querySelectorAll('[data-case-filter-option]').forEach(button=>button.addEventListener('click',()=>{
    caseBoardState.filter=CASE_FILTER_LABELS[button.dataset.caseFilterOption]?button.dataset.caseFilterOption:'all';
    renderCaseWorkspace({preserveScroll:false});
  }));
  sort?.addEventListener('change',()=>{
    caseBoardState.sort=CASE_SORT_MODES.has(sort.value)?sort.value:'operational';
    renderCaseWorkspace({preserveScroll:false});
  });
  document.getElementById('caseClearFilters')?.addEventListener('click',clearCaseFilters);
  document.querySelectorAll('[data-case-clear-filters]').forEach(button=>button.addEventListener('click',clearCaseFilters));

  let preferred='board';
  try{preferred=localStorage.getItem('tracs:cases:view')||'board';}catch(e){}
  setCaseWorkspaceView(preferred,false);
  document.querySelectorAll('[data-case-view]').forEach(button=>button.addEventListener('click',()=>setCaseWorkspaceView(button.dataset.caseView)));
  document.addEventListener('click',event=>{
    if(event.target.closest?.('.case-card-menu'))return;
    closeCaseCardMenus();
  });
  document.querySelectorAll('.case-kanban, .case-column-list').forEach(scroller=>scroller.addEventListener('scroll',closeCaseCardMenus,{passive:true}));
  const boardScroller=workspace.querySelector('.case-kanban');
  boardScroller?.addEventListener('wheel',event=>{
    if(boardScroller.scrollWidth<=boardScroller.clientWidth)return;
    const horizontalIntent=Math.abs(event.deltaX)>Math.abs(event.deltaY);
    const shiftedVertical=event.shiftKey && Math.abs(event.deltaY)>0;
    if(!horizontalIntent && !shiftedVertical)return;
    const delta=horizontalIntent?event.deltaX:event.deltaY;
    const previous=boardScroller.scrollLeft;
    const maximum=boardScroller.scrollWidth-boardScroller.clientWidth;
    const next=Math.max(0,Math.min(maximum,previous+delta));
    if(next===previous)return;
    event.preventDefault();
    boardScroller.scrollLeft=next;
  },{passive:false});
  window.addEventListener('resize',closeCaseCardMenus,{passive:true});

  if(window.TRACS_CASE_CAPS?.canManage){
    workspace.addEventListener('dragstart',event=>{
      const card=event.target.closest?.('.case-kanban-card[draggable="true"]');
      if(!card)return;
      caseBoardState.draggedId=Number(card.dataset.cid)||0;
      card.classList.add('is-dragging');
      card.setAttribute('aria-grabbed','true');
      event.dataTransfer.effectAllowed='move';
      event.dataTransfer.setData('text/plain',String(caseBoardState.draggedId));
    });
    workspace.addEventListener('dragend',event=>{
      event.target.closest?.('.case-kanban-card')?.classList.remove('is-dragging');
      event.target.closest?.('.case-kanban-card')?.setAttribute('aria-grabbed','false');
      document.querySelectorAll('.case-kanban-column.is-drag-over').forEach(column=>column.classList.remove('is-drag-over'));
      caseBoardState.draggedId=0;
    });
    workspace.addEventListener('dragover',event=>{
      const zone=event.target.closest?.('[data-case-dropzone]');
      if(!zone||!caseBoardState.draggedId)return;
      event.preventDefault();
      event.dataTransfer.dropEffect='move';
      zone.closest('.case-kanban-column')?.classList.add('is-drag-over');
    });
    workspace.addEventListener('dragleave',event=>{
      const zone=event.target.closest?.('[data-case-dropzone]');
      if(zone && !zone.contains(event.relatedTarget))zone.closest('.case-kanban-column')?.classList.remove('is-drag-over');
    });
    workspace.addEventListener('drop',event=>{
      const zone=event.target.closest?.('[data-case-dropzone]');
      const column=zone?.closest('.case-kanban-column');
      if(!zone||!column||!caseBoardState.draggedId)return;
      event.preventDefault();
      column.classList.remove('is-drag-over');
      updateCaseStatusImmediately(caseBoardState.draggedId,column.dataset.targetStatus,'drag_drop');
    });
  }

  if(sort)sort.value=caseBoardState.sort;
  renderCaseWorkspace({preserveScroll:false,syncUrl:false});
}
async function resolveCaseFromTicket(){
  const id=currentCaseTicketId;
  if(!id)return;
  updateCaseStatusImmediately(id,'completed','drawer_action');
}
function editCaseFromTicket(){
  const id=currentCaseTicketId;
  if(!id)return;
  closeModal('caseTicket');
  openEditCase(id);
}
function deleteCaseFromTicket(){
  const id=currentCaseTicketId;
  if(!id)return;
  closeModal('caseTicket');
  deleteCase(id);
}
async function saveCase(){
  const title=val('caseTitle').trim();
  if(!title){toast('Case title is required','error');return;}
  const id=val('caseId');
  const saveBtn=caseAttachmentEls().save;
  caseSetUploadStatus(caseSelectedAttachments.length?'Uploading images...':'Saving case...');
  const useForm=caseSelectedAttachments.length>0 || caseRemovedAttachmentIds.size>0;
  const d=await withLoadingState(saveBtn,'Saving...',()=>useForm
    ? caseApiWithUploads(id?API.CASE.UPDATE:API.CASE.CREATE,casePayloadFormData(id))
    : api(id?API.CASE.UPDATE:API.CASE.CREATE,{id,title,status:val('caseStatus'),priority:val('casePriority'),next_check_at:val('caseNextCheck'),notes:val('caseNotes')}));
  if(!d)return;
  if(d.success){
    showModalSuccessAndClose({
      modal:'case',
      message:id?'Case updated.':'Case created.',
      onAfterClose:()=>{
        clearCaseAttachmentState();
        location.reload();
      }
    });
  }
  else {
    const message=getFriendlyErrorMessage(d.message,'Your changes could not be saved. Please check the data and try again.');
    caseSetUploadStatus(message,'error');
    handleModalError({modal:'case',error:{message,status:d.status}});
  }
}
async function deleteCase(id,button=null){
  tracsConfirm('Delete this case permanently? This cannot be undone.',async()=>{
    const caseId=Number(id);
    const caseItem=caseBoardState.rawCases.find(item=>Number(item.id)===caseId);
    const d=await withLoadingState(button,'Deleting...',()=>api(API.CASE.DELETE,{id}));
    if(!d)return;
    if(d.success){
      const badgeContainer = document.getElementById('notif-badge-container');
      if (badgeContainer && badgeContainer.dataset.badgeMode !== 'alerts' && caseItem?.status !== 'completed') {
        const current = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
        badgeContainer.dataset.staticExtra = String(Math.max(0, current - 1));
        refreshNotificationBadge();
      }
      caseBoardState.rawCases=caseBoardState.rawCases.filter(item=>Number(item.id)!==caseId);
      renderCaseWorkspace();
      showToast('Case deleted.','success',{context:'page'});
    }
    else handleRequestError({message:d.message,status:d.status},'page','The case could not be deleted. Please try again.');
  });
}

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initCaseBoard);
else initCaseBoard();

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
  const d=await withLoadingState(document.getElementById('remSaveBtn'),'Saving...',()=>api(id?API.REMINDER.UPDATE:API.REMINDER.CREATE,{
    id,title,priority:val('remPriority'),due_date:due,description:val('remDesc')
  }));
  if(!d)return;
  if(d.success){
    showModalSuccessAndClose({
      modal:'rem',
      message:id?'Reminder updated.':'Reminder created.',
      onAfterClose:()=>location.reload()
    });
  }else handleModalError({modal:'rem',error:{message:d.message,status:d.status}});
}
async function deleteReminder(id,button=null){
  tracsConfirm('Delete this reminder permanently? Use Mark Done for handled reminders so the history remains available.',async()=>{
    const row=document.querySelector(`[data-rid="${id}"]`);
    const d=await withLoadingState(button,'Deleting...',()=>api(API.REMINDER.DELETE,{id}));
    if(!d)return;
    if(d.success){
      const badgeContainer = document.getElementById('notif-badge-container');
      if (badgeContainer && row?.dataset.completed !== '1' && (badgeContainer.dataset.badgeMode !== 'alerts' || row?.dataset.notifAlert === '1')) {
        const current = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
        badgeContainer.dataset.staticExtra = String(Math.max(0, current - 1));
        refreshNotificationBadge();
      }
      document.querySelectorAll(`[data-monitor-reminder-id="${id}"]`).forEach(item=>item.remove());
      showToast('Reminder deleted.','success',{context:'page'});removeRow(`[data-rid="${id}"]`);setTimeout(refreshTaskMonitoringCounters,220);
    }
    else handleRequestError({message:d.message,status:d.status},'page','The reminder could not be deleted. Please try again.');
  }, 'Delete Reminder');
}
const tracsReminderTogglePending=new Set();
const tracsTaskTogglePending=new Set();

function tracsCheckableSource(candidate,row){
  if(candidate instanceof Element)return candidate;
  const active=tracsSourceElement();
  return active && row?.contains(active) ? active : row?.querySelector('.rem-check, .task-chk, .rem-primary-action');
}
function tracsCheckableState(row){
  const completed=row?.dataset.completed === '1';
  return {
    completed,
    notificationAlert:row?.dataset.notifAlert,
    titleDone:Boolean(row?.querySelector('.rem-title, .task-title')?.classList.contains('done'))
  };
}
function restoreCheckableState(row,state){
  if(!row || !state)return;
  row.dataset.completed=state.completed?'1':'0';
  row.classList.toggle('is-completed',state.completed);
  row.querySelector('.rem-title, .task-title')?.classList.toggle('done',state.titleDone);
  row.querySelectorAll('.rem-check, .task-chk').forEach(box=>{box.checked=state.completed;});
  if(state.notificationAlert === undefined)delete row.dataset.notifAlert;
  else row.dataset.notifAlert=state.notificationAlert;
}
async function toggleReminder(id,checkedOrSource,sourceElement=null){
  const rows=[...document.querySelectorAll(`[data-rid="${id}"]`)];
  const row=rows[0] || (checkedOrSource instanceof Element ? checkedOrSource.closest('[data-rid]') : null);
  const directSource=checkedOrSource instanceof Element ? checkedOrSource : null;
  const source=tracsCheckableSource(sourceElement || directSource,row);
  const checked=directSource?.matches('input[type="checkbox"]') ? directSource.checked : !!checkedOrSource;
  const previousStates=new Map(rows.map(item=>[item,tracsCheckableState(item)]));
  const previousChecked=previousStates.get(row)?.completed ?? !checked;
  const requestKey=String(id);
  if(tracsReminderTogglePending.has(requestKey)){
    if(source?.matches('input[type="checkbox"]'))source.checked=previousChecked;
    return;
  }
  tracsReminderTogglePending.add(requestKey);
  rows.forEach(item=>setCheckablePending(item,true));
  try{
    const d=await api(API.REMINDER.TOGGLE,{id,is_completed:checked?1:0});
    if(!d.success){
      rows.forEach(item=>restoreCheckableState(item,previousStates.get(item)));
      showToast('Reminder could not be updated. Please try again.','error',tracsToastOptionsForSource(source,{
        duration:9000,
        closable:true
      }));
      return;
    }

    showToast(checked?'Reminder marked as done.':'Reminder reopened.',checked?'success':'info',tracsToastOptionsForSource(source));
    rows.forEach(item=>{
      item.querySelector('.rem-title')?.classList.toggle('done',checked);
      item.querySelectorAll('.rem-check').forEach(box=>{box.checked=checked;});
      syncReminderPrimaryAction(item,id,checked);
    });
    const badgeContainer=document.getElementById('notif-badge-container');
    if(badgeContainer && previousChecked!==checked){
      const current=parseInt(badgeContainer.dataset.staticExtra || '0',10) || 0;
      const alertMode=badgeContainer.dataset.badgeMode === 'alerts';
      let shouldAdjust=true;
      if(alertMode){
        const due=row?.dataset.due ? new Date(row.dataset.due).getTime() : NaN;
        const dueSoon=Number.isFinite(due) && due<=Date.now()+5*60*1000;
        shouldAdjust=row?.dataset.notifAlert === '1' || (!checked && dueSoon);
        if(row)row.dataset.notifAlert=(!checked && dueSoon)?'1':'0';
      }
      if(shouldAdjust)badgeContainer.dataset.staticExtra=String(Math.max(0,current+(checked?-1:1)));
      refreshNotificationBadge();
    }
    syncTaskMonitoringReminderMirrors(id,checked);
    rows.forEach(item=>moveCheckableRow(item,checked));
    refreshTaskMonitoringCounters();
    _updateProgress();
  }finally{
    rows.forEach(item=>setCheckablePending(item,false));
    tracsReminderTogglePending.delete(requestKey);
  }
}

function completeReminder(id,sourceElement=null){
  return toggleReminder(id,true,sourceElement);
}

function syncReminderPrimaryAction(row, id, checked){
  if(!row)return;
  const action = row.querySelector('.rem-primary-action');
  if(!action)return;
  const compact = action.dataset.compactAction === '1';
  if(checked){
    action.className = compact ? 'btn btn-ghost btn-icon rem-primary-action' : 'btn btn-ghost btn-sm rem-primary-action';
    action.title = 'Reopen reminder';
    action.setAttribute('aria-label', 'Reopen reminder');
    action.onclick = () => toggleReminder(id,false,action);
    action.innerHTML = compact ? '<i data-lucide="rotate-ccw" class="icon-sm"></i>' : '<i data-lucide="rotate-ccw" class="icon-xs"></i>Reopen';
  }else{
    action.className = compact ? 'btn btn-ghost btn-icon rem-done-btn rem-primary-action' : 'btn btn-success btn-sm rem-done-btn rem-primary-action';
    action.title = compact ? 'Mark reminder done' : 'Mark reminder as done';
    action.setAttribute('aria-label', 'Mark reminder done');
    action.onclick = () => completeReminder(id,action);
    action.innerHTML = compact ? '<i data-lucide="check-square" class="icon-sm"></i>' : '<i data-lucide="check" class="icon-xs"></i>Mark Done';
  }
  if(compact) action.dataset.compactAction = '1';
  if(window.lucide) lucide.createIcons({ nodes: action.querySelectorAll('[data-lucide]') });
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
  const d=await withLoadingState(document.getElementById('taskSaveBtn'),'Saving...',()=>api(id?API.TASK.UPDATE:API.TASK.CREATE,{
    id,title,description:val('taskDesc')
  }));
  if(!d)return;
  if(d.success){
    showModalSuccessAndClose({
      modal:'task',
      message:id?'Task updated.':'Task created.',
      onAfterClose:()=>location.reload()
    });
  }else handleModalError({modal:'task',error:{message:d.message,status:d.status}});
}
async function deleteTask(id,button=null){
  tracsConfirm('Delete this task?',async()=>{
    const d=await withLoadingState(button,'Deleting...',()=>api(API.TASK.DELETE,{id}));
    if(!d)return;
    if(d.success){
      const row=document.querySelector(`[data-tid="${id}"]`);
      const badgeContainer = document.getElementById('notif-badge-container');
      if (badgeContainer && row?.dataset.completed !== '1') {
        const current = parseInt(badgeContainer.dataset.uncheckedChecklist || '0', 10) || 0;
        badgeContainer.dataset.uncheckedChecklist = String(Math.max(0, current - 1));
      }
      showToast('Task deleted.','success',{context:'page'});removeRow(`[data-tid="${id}"]`);_updateProgress();
    }
    else handleRequestError({message:d.message,status:d.status},'page','The task could not be deleted. Please try again.');
  });
}
async function toggleTask(id,checkboxOrChecked,sourceElement=null){
  const rows=[...document.querySelectorAll(`[data-tid="${id}"]`)];
  const row=rows[0] || (checkboxOrChecked instanceof Element ? checkboxOrChecked.closest('[data-tid]') : null);
  const directSource=checkboxOrChecked instanceof Element ? checkboxOrChecked : null;
  const source=tracsCheckableSource(sourceElement || directSource,row);
  const box=directSource?.matches('.task-chk') ? directSource : row?.querySelector('.task-chk');
  const checked=box ? box.checked : !!checkboxOrChecked;
  const previousStates=new Map(rows.map(item=>[item,tracsCheckableState(item)]));
  const previousChecked=previousStates.get(row)?.completed ?? !checked;
  const requestKey=String(id);
  if(tracsTaskTogglePending.has(requestKey)){
    if(box)box.checked=previousChecked;
    return;
  }
  tracsTaskTogglePending.add(requestKey);
  rows.forEach(item=>setCheckablePending(item,true));
  try{
    const d=await api(API.TASK.TOGGLE,{id,is_completed:checked?1:0});
    if(!d.success){
      rows.forEach(item=>restoreCheckableState(item,previousStates.get(item)));
      showToast('Checklist item could not be updated. Please try again.','error',tracsToastOptionsForSource(source,{
        duration:9000,
        closable:true
      }));
      return;
    }

    showToast(checked?'Checklist item completed.':'Checklist item reopened.',checked?'success':'info',tracsToastOptionsForSource(source));
    rows.forEach(item=>{
      item.querySelector('.task-title')?.classList.toggle('done',checked);
      item.querySelectorAll('.task-chk').forEach(taskBox=>{taskBox.checked=checked;});
    });
    const badgeContainer=document.getElementById('notif-badge-container');
    if(badgeContainer && previousChecked!==checked){
      const current=parseInt(badgeContainer.dataset.uncheckedChecklist || '0',10) || 0;
      badgeContainer.dataset.uncheckedChecklist=String(Math.max(0,current+(checked?-1:1)));
    }
    rows.forEach(item=>moveCheckableRow(item,checked));
    _updateProgress();
  }finally{
    rows.forEach(item=>setCheckablePending(item,false));
    tracsTaskTogglePending.delete(requestKey);
  }
}

function setCheckablePending(row, pending){
  if(!row)return;
  row.classList.toggle('is-updating', !!pending);
  row.setAttribute('aria-busy',pending?'true':'false');
  row.querySelectorAll('.rem-check, .task-chk, .rem-primary-action').forEach(control=>{
    if(pending){
      if(control.dataset.tracsPendingDisabled === undefined)control.dataset.tracsPendingDisabled=control.disabled?'1':'0';
      control.disabled=true;
      control.setAttribute('aria-disabled','true');
    }else{
      control.disabled=control.dataset.tracsPendingDisabled === '1';
      delete control.dataset.tracsPendingDisabled;
      if(control.disabled)control.setAttribute('aria-disabled','true');
      else control.removeAttribute('aria-disabled');
    }
  });
}

function moveCheckableRow(row, checked){
  if(!row || !row.parentElement)return;
  const parent=row.parentElement;
  row.dataset.completed=checked?'1':'0';
  row.classList.toggle('is-completed', checked);
  row.classList.add('checkable-moving');

  window.requestAnimationFrame(()=>{
    if(checked){
      parent.appendChild(row);
    }else{
      const firstDone=[...parent.children].find(el=>el!==row && el.dataset?.completed==='1');
      if(firstDone) parent.insertBefore(row, firstDone);
      else parent.insertBefore(row, parent.firstElementChild);
    }
    window.requestAnimationFrame(()=>{
      row.classList.add('checkable-landed');
      window.setTimeout(()=>row.classList.remove('checkable-moving','checkable-landed'), 380);
    });
  });
}

function _updateProgress(){
  const boxes=document.querySelectorAll('.task-chk');
  const total=boxes.length,done=[...boxes].filter(b=>b.checked).length;
  const pct=total?Math.round(done/total*100):0;
  const fill=document.getElementById('prog-fill');
  const lbl=document.getElementById('prog-lbl');
  const pctlbl=document.getElementById('prog-pct');
  const monitorProgress=document.querySelector('[data-task-monitor-progress]');
  const uncheckedCounter=document.querySelector('[data-task-monitor-unchecked]');
  const unchecked=total-done;
  if(fill)fill.style.width=pct+'%';
  if(lbl)lbl.textContent=`${done} / ${total}`;
  if(pctlbl)pctlbl.textContent=pct+'%';
  if(monitorProgress)monitorProgress.textContent=`${done}/${total}`;
  if(uncheckedCounter){
    uncheckedCounter.textContent=String(unchecked);
    uncheckedCounter.title=`${unchecked} unchecked checklist item${unchecked===1?'':'s'}`;
    uncheckedCounter.classList.remove('is-zero','is-low','is-warning','is-critical');
    uncheckedCounter.classList.add(
      unchecked===0 ? 'is-zero' :
      unchecked<5 ? 'is-low' :
      unchecked<10 ? 'is-warning' :
      'is-critical'
    );
  }

  refreshNotificationBadge(unchecked);
}

function refreshTaskMonitoringCounters(){
  const activeReminderCounter=document.querySelector('[data-task-monitor-active-reminders]');
  if(activeReminderCounter){
    const reminderRows=document.querySelectorAll('.tm-reminder-row');
    if(reminderRows.length){
      activeReminderCounter.textContent=String(document.querySelectorAll('.tm-reminder-row[data-completed="0"]').length);
    }
  }
}

function syncTaskMonitoringReminderMirrors(id, completed){
  document.querySelectorAll(`[data-monitor-reminder-id="${id}"]`).forEach(item=>{
    item.hidden=!!completed;
    item.dataset.monitorReminderCompleted=completed?'1':'0';
    const status=item.querySelector('.tm-feed-status');
    if(status)status.textContent=completed?'Completed':(item.dataset.monitorReminderStatus || 'Scheduled');
  });
}

function initTaskMonitoringTabs(){
  const root=document.querySelector('[data-task-monitoring]');
  if(!root)return;
  const tabs=[...root.querySelectorAll('[data-task-monitor-tab]')];
  const panes=[...root.querySelectorAll('[data-task-monitor-pane]')];
  const allLink=root.querySelector('[data-task-monitor-all]');
  const activate=(name)=>{
    const activeTab=tabs.find(tab=>tab.dataset.taskMonitorTab===name) || tabs[0];
    if(!activeTab)return;
    tabs.forEach(tab=>{
      const selected=tab===activeTab;
      tab.classList.toggle('active',selected);
      tab.setAttribute('aria-selected',selected?'true':'false');
    });
    panes.forEach(pane=>{
      const selected=pane.dataset.taskMonitorPane===activeTab.dataset.taskMonitorTab;
      pane.hidden=!selected;
      pane.classList.toggle('is-active',selected);
    });
    if(allLink)allLink.href=activeTab.dataset.allHref || '#';
    tracsRefreshIcons(root);
  };
  tabs.forEach(tab=>{
    tab.addEventListener('click',()=>activate(tab.dataset.taskMonitorTab));
  });
  root.querySelectorAll('[data-task-monitor-switch]').forEach(control=>{
    control.addEventListener('click',()=>{
      activate(control.dataset.taskMonitorSwitch || 'assignments');
    });
  });
  activate(tabs.find(tab=>tab.classList.contains('active'))?.dataset.taskMonitorTab || tabs[0]?.dataset.taskMonitorTab);
  refreshTaskMonitoringCounters();
}

function refreshNotificationBadge(pendingOverride){
  const badgeContainer = document.getElementById('notif-badge-container');
  if (!badgeContainer) return;
  const unreadNotifications = parseInt(badgeContainer.dataset.unreadNotifications || '0', 10) || 0;
  if (badgeContainer.dataset.badgeMode === 'alerts') {
    const count = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
    const total = count + unreadNotifications;
    badgeContainer.innerHTML = total > 0 ? `<span class="bell-badge">${Math.min(total, 99)}</span>` : '';
    return;
  }
  const taskBoxes=document.querySelectorAll('.task-chk');
  const pending = typeof pendingOverride === 'number'
    ? pendingOverride
    : (taskBoxes.length ? [...taskBoxes].filter(b=>!b.checked).length : 0);
  const extra = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
  const checklistCount = parseInt(badgeContainer.dataset.uncheckedChecklist || String(pending), 10) || 0;
  const count = checklistCount + extra + unreadNotifications;
  if (count > 0) {
    badgeContainer.innerHTML = `<span class="bell-badge">${Math.min(count, 99)}</span>`;
  } else {
    badgeContainer.innerHTML = '';
  }
}

/* ── TRACS notification center + browser notifications ── */
const TRACSNotifications = (() => {
  const SEEN_KEY = 'tracs:notifications:last-seen-id';
  const PROMPT_KEY = 'tracs:notifications:prompt-dismissed-at';
  const POLL_MS = 60000;
  let polling = false;
  let pollTimer = null;
  let initialPoll = true;
  let lastItems = [];

  function supported() {
    return typeof window.Notification !== 'undefined';
  }

  function permission() {
    if (!supported()) return 'unsupported';
    return Notification.permission || 'default';
  }

  function promptContextAllowed() {
    const page = document.body?.dataset?.tracsPage || '';
    if (page === 'dashboard') return true;
    if (page === 'profile') {
      return new URLSearchParams(location.search).get('section') === 'preferences';
    }
    return false;
  }

  function promptDismissedRecently() {
    try {
      const ts = parseInt(localStorage.getItem(PROMPT_KEY) || '0', 10) || 0;
      return ts > 0 && Date.now() - ts < 24 * 60 * 60 * 1000;
    } catch (e) {
      return false;
    }
  }

  function setPromptVisible(visible) {
    document.querySelectorAll('[data-tracs-notification-permission]').forEach(node => {
      node.classList.toggle('hidden', !visible);
    });
  }

  function updatePermissionPrompt() {
    const canPrompt = supported() && permission() === 'default' && promptContextAllowed() && !promptDismissedRecently();
    setPromptVisible(canPrompt);
  }

  async function requestPermission() {
    if (!supported()) {
      toast('Browser notifications are not supported here. In-app notifications will still work.', 'info');
      return 'unsupported';
    }
    try {
      const result = await Notification.requestPermission();
      updatePermissionPrompt();
      if (result === 'granted') toast('Browser notifications enabled.', 'success');
      if (result === 'denied') toast('No problem. TRACS will keep using in-app notifications.', 'info');
      poll();
      return result;
    } catch (e) {
      toast('Unable to request browser notification permission.', 'error');
      return permission();
    }
  }

  function dismissPrompt() {
    try { localStorage.setItem(PROMPT_KEY, String(Date.now())); } catch (e) {}
    setPromptVisible(false);
  }

  function absoluteUrl(url) {
    try { return new URL(url || '/index.php', location.origin).href; }
    catch (e) { return location.origin + '/index.php'; }
  }

  async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
      return await navigator.serviceWorker.register('/tracs-sw.js');
    } catch (e) {
      return null;
    }
  }

  async function browserNotify(item) {
    const url = absoluteUrl(item.related_url || '/index.php');
    const options = {
      body: item.message || 'Open TRACS for details.',
      tag: `tracs-${item.id}`,
      renotify: false,
      icon: '/assets/img/logo.svg',
      badge: '/assets/img/logo.svg',
      data: { url }
    };
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.ready.catch(() => null);
      if (registration?.showNotification) {
        await registration.showNotification(item.title || 'TRACS notification', options);
        return;
      }
    }
    const notice = new Notification(item.title || 'TRACS notification', options);
    notice.onclick = () => {
      window.focus();
      location.href = url;
      notice.close();
    };
  }

  function notificationStatusClass(item) {
    const module = String(item.related_module || 'pending').replace(/[^a-z0-9_-]/gi, '').toLowerCase();
    if (module === 'mom') return 'meeting';
    if (module === 'shift-reports') return 'shift';
    return module || 'pending';
  }

  function renderList(items = []) {
    const host = document.querySelector('[data-tracs-notification-list]');
    if (!host) return;
    const visible = items.slice(0, 8);
    host.innerHTML = [
      '<div class="notif-section-label">TRACS Notifications</div>',
      visible.length ? visible.map(item => {
        const href = item.related_url ? escHtml(item.related_url) : '#';
        const type = escHtml(String(item.notification_type || 'notification').replace(/_/g, ' '));
        const readClass = item.is_read === '1' || item.is_read === 1 ? 'is-read' : 'is-unread';
        return `
          <a class="notif-drop-item notif-system-item ${readClass}" href="${href}" data-notification-id="${Number(item.id) || 0}">
            <div class="notif-drop-status status-${notificationStatusClass(item)}"></div>
            <div class="notif-drop-info">
              <div class="notif-drop-label">${type}</div>
              <div class="notif-drop-text">${escHtml(item.title || 'TRACS notification')}</div>
              <div class="notif-drop-meta">${escHtml(item.message || '')}</div>
            </div>
          </a>
        `;
      }).join('') : '<div class="notif-drop-empty is-compact">No TRACS notifications yet</div>'
    ].join('');
  }

  function updateBadge(unread) {
    const badge = document.getElementById('notif-badge-container');
    if (!badge) return;
    badge.dataset.unreadNotifications = String(Math.max(0, Number(unread) || 0));
    refreshNotificationBadge();
  }

  async function postJson(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data || {})
    });
    return res.json();
  }

  async function markRead(ids = []) {
    if (!ids.length) return;
    try {
      await postJson(API.NOTIFICATION.MARK_READ, { ids });
      poll();
    } catch (e) {}
  }

  function markVisibleRead() {
    const unreadIds = lastItems
      .filter(item => !(item.is_read === '1' || item.is_read === 1))
      .map(item => Number(item.id))
      .filter(Boolean);
    if (!unreadIds.length) return;
    updateBadge(0);
    markRead(unreadIds);
  }

  function notifyInApp(items = []) {
    let lastSeen = 0;
    try { lastSeen = parseInt(localStorage.getItem(SEEN_KEY) || '0', 10) || 0; } catch (e) {}
    const maxId = items.reduce((max, item) => Math.max(max, Number(item.id) || 0), lastSeen);
    if (initialPoll) {
      try { localStorage.setItem(SEEN_KEY, String(maxId)); } catch (e) {}
      return;
    }
    items
      .filter(item => Number(item.id) > lastSeen)
      .sort((a, b) => Number(a.id) - Number(b.id))
      .slice(0, 3)
      .forEach(item => showToast('info', item.title || 'TRACS notification', item.message || 'Open TRACS for details.', { duration: 7000 }));
    try { localStorage.setItem(SEEN_KEY, String(maxId)); } catch (e) {}
  }

  async function claimAndShowPush(items = []) {
    const pending = items.filter(item => item.push_status === 'pending').slice(0, 5);
    if (!pending.length) return;
    const perm = permission();
    if (perm === 'default') return;
    let claimedIds = [];
    try {
      const claim = await postJson(API.NOTIFICATION.CLAIM_PUSH, {
        ids: pending.map(item => Number(item.id)).filter(Boolean),
        permission: perm
      });
      claimedIds = claim?.data?.claimed_ids || [];
    } catch (e) {
      return;
    }
    if (perm !== 'granted' || !claimedIds.length) return;
    const claimed = new Set(claimedIds.map(Number));
    for (const item of pending.filter(entry => claimed.has(Number(entry.id)))) {
      try {
        await browserNotify(item);
      } catch (e) {
        try {
          await postJson(API.NOTIFICATION.PUSH_STATUS, {
            ids: [Number(item.id)],
            status: 'failed',
            error: e?.message || 'Notification display failed'
          });
        } catch (ignored) {}
      }
    }
  }

  async function poll() {
    if (polling) return;
    polling = true;
    try {
      const res = await fetch(`${API.NOTIFICATION.LIST}?limit=20`, { headers: { 'Accept': 'application/json' } });
      const json = await res.json();
      if (!json.success) return;
      const data = json.data || {};
      const items = data.items || [];
      lastItems = items;
      renderList(items);
      updateBadge(data.unread_count || 0);
      notifyInApp(items);
      await claimAndShowPush(items);
    } catch (e) {
      /* Notification polling should never interrupt active work. */
    } finally {
      initialPoll = false;
      polling = false;
    }
  }

  function bind() {
    document.addEventListener('click', event => {
      const enable = event.target.closest('[data-tracs-notification-enable]');
      if (enable) {
        event.preventDefault();
        requestPermission();
        return;
      }
      const dismiss = event.target.closest('[data-tracs-notification-dismiss]');
      if (dismiss) {
        event.preventDefault();
        dismissPrompt();
        return;
      }
      const item = event.target.closest('.notif-system-item[data-notification-id]');
      if (item) {
        const id = Number(item.dataset.notificationId) || 0;
        if (id) markRead([id]);
      }
    });
  }

  function start() {
    bind();
    registerServiceWorker();
    updatePermissionPrompt();
    poll();
    pollTimer = setInterval(poll, POLL_MS);
  }

  return { start, poll, requestPermission, markRead, markVisibleRead };
})();

window.TRACSNotifications = TRACSNotifications;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => TRACSNotifications.start());
} else {
  TRACSNotifications.start();
}

/* ── TICKER CRUD ──────────────────────────────────────── */
async function addTickerMsg(){
  const text=val('newTickerText').trim(),cls=val('newTickerCls');
  const button=document.getElementById('tickerAddBtn');
  if(!text){
    handleModalError({modal:'ticker',message:'Announcement message is required.',focus:document.getElementById('newTickerText')});
    return;
  }
  const d=await withLoadingState(button,'Adding...',()=>api(API.TICKER.CREATE,{text,class:cls}));
  if(!d)return;
  if(d.success){
    showModalSuccessAndClose({
      modal:'ticker',
      message:'Announcement added.',
      onAfterClose:()=>{
        setVal('newTickerText','');
        location.reload();
      }
    });
  }else handleModalError({modal:'ticker',error:{message:d.message,status:d.status},fallbackMessage:'The announcement could not be added. Please try again.'});
}
async function archiveTickerMsg(id){

  tracsConfirm(
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

        toast(d.message || "Couldn't archive the announcement. Please try again.",'error');
      }

    },
    'Archive Message'
  );
}

/* ── SHIFT REPORT CRUD ────────────────────────────────── */
let shiftSelectedAttachments = [];

function shiftAttachmentEls(){
  return {
    input: document.getElementById('shiftAttachments'),
    drop: document.getElementById('shiftUploadDrop'),
    status: document.getElementById('shiftUploadStatus'),
    selected: document.getElementById('shiftAttachmentPreview')
  };
}
function shiftSetUploadStatus(message='',type=''){
  const el=shiftAttachmentEls().status;
  if(!el)return;
  el.textContent=message;
  el.className=`case-upload-status ${type||''}`.trim();
}
function shiftValidateAttachment(file){
  if(!file || !file.name)return 'Choose a valid image.';
  if(file.size<=0)return `${file.name} is empty.`;
  if(file.size>CASE_ATTACHMENT_MAX)return `${file.name} is larger than 5MB.`;
  if(!CASE_ATTACHMENT_TYPES.has(file.type))return `${file.name} must be JPG, JPEG, PNG, or WEBP.`;
  return '';
}
function shiftAddAttachmentFiles(files){
  const incoming=Array.from(files||[]);
  const errors=[];
  incoming.forEach(file=>{
    const err=shiftValidateAttachment(file);
    if(err){errors.push(err);return;}
    const duplicate=shiftSelectedAttachments.some(item=>item.file.name===file.name && item.file.size===file.size && item.file.lastModified===file.lastModified);
    if(!duplicate)shiftSelectedAttachments.push({id:crypto.randomUUID?.()||String(Date.now()+Math.random()),file,url:URL.createObjectURL(file)});
  });
  renderShiftSelectedAttachments();
  if(errors.length)shiftSetUploadStatus(errors[0],'error');
  else if(incoming.length)shiftSetUploadStatus(`${shiftSelectedAttachments.length} image${shiftSelectedAttachments.length===1?'':'s'} ready to upload.`,'ok');
}
function clearShiftAttachmentState(){
  shiftSelectedAttachments.forEach(item=>{try{URL.revokeObjectURL(item.url);}catch(e){}});
  shiftSelectedAttachments=[];
  const els=shiftAttachmentEls();
  if(els.input)els.input.value='';
  if(els.selected)els.selected.innerHTML='';
  shiftSetUploadStatus('');
}
function removeShiftSelectedAttachment(id){
  const item=shiftSelectedAttachments.find(entry=>entry.id===id);
  if(item){try{URL.revokeObjectURL(item.url);}catch(e){}}
  shiftSelectedAttachments=shiftSelectedAttachments.filter(entry=>entry.id!==id);
  renderShiftSelectedAttachments();
  shiftSetUploadStatus(shiftSelectedAttachments.length?`${shiftSelectedAttachments.length} image${shiftSelectedAttachments.length===1?'':'s'} ready to upload.`:'');
}
function renderShiftSelectedAttachments(){
  const el=shiftAttachmentEls().selected;
  if(!el)return;
  el.innerHTML=shiftSelectedAttachments.map(item=>`
    <div class="case-attachment-tile">
      <button class="case-attachment-thumb" type="button" onclick="openCaseImagePreview(${jsAttr(item.url)},${jsAttr(item.file.name)})">
        <img src="${item.url}" alt="${escHtml(item.file.name)}">
      </button>
      <div class="case-attachment-meta"><span title="${escHtml(item.file.name)}">${escHtml(item.file.name)}</span><small>${formatBytes(item.file.size)}</small></div>
      <button class="case-attachment-remove" type="button" onclick="removeShiftSelectedAttachment(${jsAttr(item.id)})" aria-label="Remove selected image"><i data-lucide="x" class="icon-xs"></i></button>
    </div>
  `).join('');
  tracsRefreshIcons(el);
}
function shiftPayloadFormData(id=''){
  const fd=new FormData();
  if(id)fd.append('id',id);
  fd.append('title',val('shiftTitle').trim());
  fd.append('shift_name',val('shiftName'));
  fd.append('priority',val('shiftPriority'));
  fd.append('status',val('shiftStatus')||'active');
  fd.append('details',val('shiftDetails'));
  fd.append('active_date',val('shiftDate'));
  fd.append('resolution_note',val('shiftResolutionNote'));
  fd.append('resolved_at',val('shiftResolvedAt'));
  shiftSelectedAttachments.forEach(item=>fd.append('attachments[]',item.file,item.file.name));
  return fd;
}
function toggleShiftResolutionFields(){
  const fields=document.getElementById('shiftResolutionFields');
  if(!fields)return;
  const isResolved=val('shiftStatus')==='resolved';
  fields.classList.toggle('hidden',!isResolved);
  if(isResolved && !val('shiftResolvedAt')){
    const now=new Date();
    now.setMinutes(now.getMinutes()-now.getTimezoneOffset());
    setVal('shiftResolvedAt',now.toISOString().slice(0,16));
  }
}
function initShiftAttachmentUpload(){
  const els=shiftAttachmentEls();
  if(!els.input||els.input.dataset.ready)return;
  els.input.dataset.ready='1';
  els.input.addEventListener('change',()=>shiftAddAttachmentFiles(els.input.files));
  if(els.drop){
    ['dragenter','dragover'].forEach(evt=>els.drop.addEventListener(evt,e=>{e.preventDefault();els.drop.classList.add('drag');}));
    ['dragleave','drop'].forEach(evt=>els.drop.addEventListener(evt,e=>{e.preventDefault();els.drop.classList.remove('drag');}));
    els.drop.addEventListener('drop',e=>shiftAddAttachmentFiles(e.dataTransfer?.files));
  }
}
function openNewShiftReport(){
  initShiftAttachmentUpload();
  document.getElementById('shiftModalTitle').textContent='New Shift Report';
  ['shiftId','shiftTitle','shiftDetails','shiftResolutionNote','shiftResolvedAt'].forEach(id=>setVal(id,''));
  setVal('shiftPriority','medium');
  setVal('shiftStatus','active');
  setVal('shiftDate', new Date().toISOString().split('T')[0]);
  toggleShiftResolutionFields();
  clearShiftAttachmentState();
  openModal('shift');
}
function openEditShiftReport(id){
  initShiftAttachmentUpload();
  const row=document.querySelector(`[data-id="${id}"]`);
  if(!row)return;
  document.getElementById('shiftModalTitle').textContent='Edit Shift Report';
  setVal('shiftId',id);
  setVal('shiftTitle',row.dataset.title||'');
  setVal('shiftName',row.dataset.shift||'Shift 1');
  setVal('shiftPriority',row.dataset.prio||'medium');
  setVal('shiftStatus',row.dataset.status||'active');
  setVal('shiftDate', row.dataset.date || new Date().toISOString().split('T')[0]);
  setVal('shiftDetails',row.dataset.details||'');
  setVal('shiftResolutionNote',row.dataset.resolutionNote||'');
  setVal('shiftResolvedAt',(row.dataset.resolvedAt||'').replace(' ','T').slice(0,16));
  toggleShiftResolutionFields();
  clearShiftAttachmentState();
  openModal('shift');
}
async function saveShiftReport(){
  const title=val('shiftTitle').trim();
  if(!title){toast('Title is required','error');return;}
  const id=val('shiftId');
  shiftSetUploadStatus(shiftSelectedAttachments.length?'Uploading images...':'Saving report...');
  const d=await withLoadingState(document.getElementById('shiftSaveBtn'),'Saving...',()=>shiftSelectedAttachments.length
    ? caseApiWithUploads(id?API.SHIFT.UPDATE:API.SHIFT.CREATE,shiftPayloadFormData(id))
    : api(id?API.SHIFT.UPDATE:API.SHIFT.CREATE,{id,title,shift_name:val('shiftName'),priority:val('shiftPriority'),status:val('shiftStatus')||'active',details:val('shiftDetails'),active_date:val('shiftDate'),resolution_note:val('shiftResolutionNote'),resolved_at:val('shiftResolvedAt')}));
  if(!d)return;
  if(d.success){
    showModalSuccessAndClose({
      modal:'shift',
      message:id?'Report updated.':'Report created.',
      onAfterClose:()=>{
        clearShiftAttachmentState();
        location.reload();
      }
    });
  }else handleModalError({modal:'shift',error:{message:d.message,status:d.status}});
}
async function resolveShiftReport(id,button=null){
  tracsConfirm('Mark this shift report as resolved?',async()=>{
    const d=await withLoadingState(button,'Resolving...',()=>api(API.SHIFT.RESOLVE,{id}));
    if(!d)return;
    if(d.success){showToast('Report resolved.','success',{context:'page'});_reload();}
    else handleRequestError({message:d.message,status:d.status},'page','The shift report could not be resolved. Please try again.');
  });
}
async function deleteShiftReport(id,button=null){
  tracsConfirm('Delete this shift report?',async()=>{
    const d=await withLoadingState(button,'Deleting...',()=>api(API.SHIFT.DELETE,{id}));
    if(!d)return;
    if(d.success){showToast('Report deleted.','success',{context:'page'});removeRow(`[data-id="${id}"]`);}
    else handleRequestError({message:d.message,status:d.status},'page','The shift report could not be deleted. Please try again.');
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
    const rate = parseFloat(data.rate);

    if (isNaN(result)) {
      console.error("RESULT NaN DETECTED:", data);
      return;
    }

    document.getElementById('currency-result').textContent =
      formatCurrencyConverterNumber(result);

    document.getElementById('currency-rate').textContent =
      `1 ${from} = ${formatCurrencyConverterNumber(rate, true)} ${to}`;

    document.getElementById('currency-time').textContent =
      data.time;

  } catch (err) {
    console.error("FETCH ERROR:", err);
  }
}

function formatCurrencyConverterNumber(value, allowSmallMarker = false) {
  const num = Number(value);
  if (!Number.isFinite(num)) return '0';
  if (allowSmallMarker && num > 0 && Math.abs(num) < 0.01) return '<0.01';
  const rounded = Math.round((num + Number.EPSILON) * 100) / 100;
  const hadFraction = Math.abs(num - Math.trunc(num)) > Number.EPSILON;
  const hasRoundedFraction = Math.abs(rounded - Math.trunc(rounded)) > Number.EPSILON;
  return rounded.toLocaleString(undefined, {
    minimumFractionDigits: hadFraction || hasRoundedFraction ? 2 : 0,
    maximumFractionDigits: 2,
  });
}

/* ── Website Screenshot widget ─────────────────── */

let screenshotDataUrl = null;
let screenshotHost = 'screenshot';

function setScreenshotStatus(message, isError = false) {
  const el = document.getElementById('screenshot-status');
  if (!el) return;
  if (!message) {
    el.hidden = true;
    el.textContent = '';
    return;
  }
  el.hidden = false;
  el.textContent = message;
  el.classList.toggle('is-error', !!isError);
}

async function captureScreenshot() {
  const input = document.getElementById('screenshot-url');
  const btn = document.getElementById('screenshot-btn');
  const result = document.getElementById('screenshot-result');
  const label = btn?.querySelector('.screenshot-btn-label');

  const raw = (input?.value || '').trim();
  if (!raw) {
    setScreenshotStatus('Enter a domain, URL, or IP address first.', true);
    input?.focus();
    return;
  }

  const region = document.getElementById('screenshot-region')?.value || '';

  // Per spec: the previous image clears as soon as a new capture starts.
  if (result) result.hidden = true;
  screenshotDataUrl = null;
  setScreenshotStatus('Capturing screenshot…');
  if (btn) btn.disabled = true;
  if (label) label.textContent = 'Capturing…';

  const params = new URLSearchParams({ url: raw });
  if (region) params.set('region', region);

  try {
    const res = await fetch(`/api/screenshot-capture.php?${params.toString()}`, {
      headers: { 'Accept': 'application/json' },
    });
    const data = await res.json().catch(() => null);

    if (!res.ok || !data || !data.success || !data.data?.image) {
      setScreenshotStatus(data?.message || `Capture failed (HTTP ${res.status}).`, true);
      return;
    }

    const payload = data.data;
    screenshotDataUrl = payload.image;
    screenshotHost = payload.host || 'screenshot';

    const img = document.getElementById('screenshot-img');
    if (img) img.src = screenshotDataUrl;
    if (result) result.hidden = false;
    setScreenshotStatus('');

    const m = payload.meta || {};
    const bits = [];
    if (m.load != null) bits.push(`Load ${m.load}ms`);
    if (m.dns != null) bits.push(`DNS ${m.dns}ms`);
    if (m.ttfb != null) bits.push(`TTFB ${m.ttfb}ms`);
    const meta = document.getElementById('screenshot-meta');
    if (meta) meta.textContent = bits.length ? `${screenshotHost} · ${bits.join(' · ')}` : screenshotHost;
  } catch (err) {
    console.error('Screenshot capture error:', err);
    setScreenshotStatus('Could not reach the screenshot service.', true);
  } finally {
    if (btn) btn.disabled = false;
    if (label) label.textContent = 'Capture';
  }
}

async function screenshotToBlob() {
  if (!screenshotDataUrl) return null;
  const res = await fetch(screenshotDataUrl);
  return await res.blob();
}

function screenshotFileName() {
  const safeHost = (screenshotHost || 'screenshot').replace(/[^a-z0-9.-]+/gi, '_');
  const stamp = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19);
  return `screenshot_${safeHost}_${stamp}.png`;
}

async function viewScreenshot() {
  const blob = await screenshotToBlob();
  if (!blob) return;
  const url = URL.createObjectURL(blob);
  window.open(url, '_blank', 'noopener');
  // Give the new tab time to load before releasing the object URL.
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

function downloadScreenshot() {
  if (!screenshotDataUrl) return;
  const a = document.createElement('a');
  a.href = screenshotDataUrl;
  a.download = screenshotFileName();
  document.body.appendChild(a);
  a.click();
  a.remove();
}

async function copyScreenshot() {
  const labelEl = document.querySelector('.screenshot-copy-label');
  const original = labelEl?.textContent || 'Copy';
  try {
    const blob = await screenshotToBlob();
    if (!blob || !navigator.clipboard || typeof ClipboardItem === 'undefined') {
      throw new Error('Clipboard image copy unsupported');
    }
    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
    if (labelEl) {
      labelEl.textContent = 'Copied!';
      setTimeout(() => { labelEl.textContent = original; }, 1800);
    }
  } catch (err) {
    console.error('Screenshot copy failed:', err);
    setScreenshotStatus('Copy not supported in this browser — use Download instead.', true);
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
  const button = document.getElementById('ops-save-btn');

  if (!message) {
    handleModalError({modal:'ops',message:'Ops message is required.',focus:document.getElementById('ops-message')});
    return;
  }

  try {

    const data = await withLoadingState(button,'Saving...',()=>api(API_BASE + 'ops-status.php', {
      action: 'save',
      id: document.getElementById('ops-id').value,
      message,
      severity: document.getElementById('ops-severity').value
    }));
    if(!data)return;

    if (!data.success) {
      handleModalError({modal:'ops',error:{message:data.message,status:data.status},fallbackMessage:'The operational status could not be saved. Please try again.'});
      return;
    }

    showModalSuccessAndClose({
      modal:'ops',
      message:'Operational status saved.',
      onAfterClose:()=>location.reload()
    });

  } catch (err) {

    console.error(err);
    handleModalError({modal:'ops',button,error:err,fallbackMessage:'The operational status could not be saved. Please try again.'});
  }
}

async function archiveOpsStatus() {

  const id =
    document.getElementById('ops-id').value;
  const button = document.getElementById('ops-archive-btn');

  if (!id) {
    handleModalError({modal:'ops',message:'Select a valid operational status first.'});
    return;
  }

  try {

    const data = await withLoadingState(button,'Archiving...',()=>api(API_BASE + 'ops-status.php', {
      action: 'archive',
      id
    }));
    if(!data)return;

    if (!data.success) {
      handleModalError({modal:'ops',error:{message:data.message,status:data.status},fallbackMessage:'The operational status could not be archived. Please try again.'});
      return;
    }

    showModalSuccessAndClose({
      modal:'ops',
      message:'Operational status archived.',
      onAfterClose:()=>location.reload()
    });

  } catch (err) {

    console.error(err);
    handleModalError({modal:'ops',button,error:err,fallbackMessage:'The operational status could not be archived. Please try again.'});
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
let pendingQuickTimeInput = null;

function resolveQuickTimeTarget(btn) {
  const explicitTarget = btn?.dataset?.quickTarget || btn?.getAttribute?.('data-sync');
  if (explicitTarget) {
    const explicitInput = document.getElementById(explicitTarget);
    if (explicitInput) return explicitInput;
  }

  const group = btn?.closest?.('.form-group') || btn?.closest?.('.fb-inline-col');
  const modal = btn?.closest?.('.modal') || btn?.closest?.('.modal-overlay') || btn?.closest?.('form');
  const row = btn?.closest?.('.form-row');
  const scopedTargets = [group, row, modal].filter(Boolean);

  for (const scope of scopedTargets) {
    const target = scope.querySelector('.split-date') ||
      scope.querySelector('.split-time') ||
      scope.querySelector('.quick-datetime');
    if (target) return target;
  }

  return activeQuickTimeInput;
}

function formatDateDisplay(value) {
  const [y, m, d] = String(value).split('-');
  return y && m && d ? `${d}/${m}/${y}` : value;
}

function setDateLikeInput(el, value, displayValue = value) {
  if (!el) return;
  if (el._flatpickr) {
    if (value === '') {
      el._flatpickr.clear();
    } else {
      el._flatpickr.setDate(value, true);
    }
  }
  el.value = value;
  if (el._flatpickr?.altInput) {
    el._flatpickr.altInput.value = displayValue;
    el._flatpickr.altInput.dispatchEvent(new Event('input', { bubbles: true }));
    el._flatpickr.altInput.dispatchEvent(new Event('change', { bubbles: true }));
  }
  el.dispatchEvent(new Event('input', { bubbles: true }));
  el.dispatchEvent(new Event('change', { bubbles: true }));
}

window.setDateLikeInput = setDateLikeInput;
window.formatDateDisplay = formatDateDisplay;

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
    const input = resolveQuickTimeTarget(btn);
    if (input) {
      activeQuickTimeInput = input;
      pendingQuickTimeInput = input;
    }
  }, true);
}

function setQuickTime(type, sourceBtn = null) {

  const input = sourceBtn ? resolveQuickTimeTarget(sourceBtn) : (pendingQuickTimeInput || activeQuickTimeInput);
  pendingQuickTimeInput = null;

  if (!input) return;

  const now = new Date();

  switch(type){

    case 'now':
      break;

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

    setDateLikeInput(dateInp, dVal, formatDateDisplay(dVal));
    setDateLikeInput(timeInp, tVal, tVal);
    if (mainInput) {
      mainInput.value = fullVal;
      mainInput.dispatchEvent(new Event('input', { bubbles: true }));
      mainInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    return;
  }

  if (input._flatpickr) {
    const dateVal = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
    input._flatpickr.setDate(input.type === 'date' ? dateVal : now, true);
  } else {
    input.value = fullVal;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
}

window.setQuickTime = setQuickTime;

function bindSidebarTooltips() {
  const hosts = document.querySelectorAll('.sidebar .nav-item, .sidebar .user-avatar, .sidebar .theme-toggle');
  if (!hosts.length) return;

  const placeTip = (host) => {
    if (!host) return;
    const tip = host.querySelector('.nav-tip');
    const sidebar = host.closest('.sidebar');
    if (!sidebar) return;

    const hostRect = host.getBoundingClientRect();
    const sideRect = sidebar.getBoundingClientRect();
    if (tip) {
      tip.style.setProperty('--nav-tip-left', `${sideRect.right + 10}px`);
      tip.style.setProperty('--nav-tip-top', `${hostRect.top + hostRect.height / 2}px`);
    }

    const submenu = host.closest('.nav-menu-wrap')?.querySelector('.nav-submenu');
    if (submenu) {
      submenu.style.setProperty('--nav-submenu-left', `${sideRect.right + 10}px`);
      submenu.style.setProperty('--nav-submenu-top', `${hostRect.top + hostRect.height / 2}px`);
    }
  };

  const placeOpenSubmenus = () => {
    document.querySelectorAll('.sidebar .nav-menu-wrap[open] > .nav-item').forEach(host => placeTip(host));
  };

  hosts.forEach((host) => {
    host.addEventListener('mouseenter', () => placeTip(host));
    host.addEventListener('focusin', () => placeTip(host));
    if (host.closest('.nav-menu-wrap')) {
      host.addEventListener('click', () => placeTip(host));
      host.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') placeTip(host);
      });
    }
  });

  document.querySelectorAll('.sidebar .nav-menu-wrap').forEach(menu => {
    menu.addEventListener('toggle', () => {
      if (menu.open) placeTip(menu.querySelector('summary.nav-item'));
    });
  });

  window.addEventListener('resize', placeOpenSubmenus);
  window.addEventListener('scroll', placeOpenSubmenus, true);
  requestAnimationFrame(placeOpenSubmenus);
}

function bindSidebarMenus() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;

  const navMenus = [...sidebar.querySelectorAll('.nav-menu-wrap')];
  const userMenu = sidebar.querySelector('.user-menu-wrap');
  const allMenus = [...navMenus, ...(userMenu ? [userMenu] : [])];

  const closeMenus = (except = null) => {
    allMenus.forEach(menu => {
      if (menu !== except) menu.open = false;
    });
  };

  allMenus.forEach(menu => {
    menu.addEventListener('toggle', () => {
      if (menu.open) closeMenus(menu);
    });
  });

  sidebar.addEventListener('click', event => {
    const action = event.target.closest('a[href], button');
    if (action && !action.closest('summary')) closeMenus();
  });

  document.addEventListener('click', event => {
    if (!sidebar.contains(event.target)) closeMenus();
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const openMenu = allMenus.find(menu => menu.open);
    if (!openMenu) return;

    openMenu.open = false;
    openMenu.querySelector('summary')?.focus();
  });
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

  bindNativeFormLoading();
  bindModalAjaxForms();
  bindModalValidation();
  initModalAccessibility();
  bindQuickDatetimeInputs();
  bindOpsStatusControls();
  bindSidebarMenus();
  bindSidebarTooltips();
  initShiftReportReminders();

  const loginError=document.querySelector('.login-card .err-box');
  if(loginError?.textContent.trim()){
    showToast(loginError.textContent.trim(),'error',{
      context:'page',
      persistent:true,
      closable:true,
      priority:'critical'
    });
  }

  /* ── Currency Converter ───────────────────── */

  document.getElementById('convert-btn')
    ?.addEventListener('click', convertCurrency);

  document.getElementById('swap-currency')
    ?.addEventListener('click', () => {

      const from = document.getElementById('currency-from');
      const to = document.getElementById('currency-to');

      if (!from || !to) return;

      [from.value, to.value] = [to.value, from.value];
      window.TRACSDropdowns?.syncSelect(from);
      window.TRACSDropdowns?.syncSelect(to);

      convertCurrency();
    });

  if (document.getElementById('currency-result')) {
    convertCurrency();
  }

  /* ── Website Screenshot widget ────────────── */

  document.getElementById('screenshot-btn')
    ?.addEventListener('click', captureScreenshot);

  document.getElementById('screenshot-url')
    ?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        captureScreenshot();
      }
    });

  document.getElementById('screenshot-preview')
    ?.addEventListener('click', viewScreenshot);
  document.getElementById('screenshot-view')
    ?.addEventListener('click', viewScreenshot);
  document.getElementById('screenshot-download')
    ?.addEventListener('click', downloadScreenshot);
  document.getElementById('screenshot-copy')
    ?.addEventListener('click', copyScreenshot);

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
  'pending'              : 'dt-status-awaiting',
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
  'pending'              : 'Pending',
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
    toast(d.message || "Couldn't update the status. Please try again.", 'error');
    if (badge && prevStatus) {
      badge.className  = 'dt-status ' + (DT_STATUS_CLASS[prevStatus] || '');
      badge.textContent = DT_STATUS_LABEL[prevStatus] || prevStatus;
      selectEl.value   = prevStatus;
      window.TRACSDropdowns?.syncSelect(selectEl);
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
    toast(d.message || "Couldn't update the move-domain field. Please try again.", 'error');
    selectEl.value = prevVal;
    selectEl.classList.toggle('has-value', !!prevVal);
    window.TRACSDropdowns?.syncSelect(selectEl);
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
    toast(d.message || "Couldn't update the end date. Please try again.", 'error');
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
    toast(d.message || "Couldn't save the transfer. Please try again.", 'error');
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
  const button = document.querySelector('#dtModal .modal-foot .btn-primary');

  if (!id)     { toast('Invalid record', 'error'); return; }
  if (!domain) { toast('Domain name is required', 'error'); return; }

  const ta = document.getElementById('dtNotes');
  const notes = ta ? ta.value.trim() : '';

  const d = await withLoadingState(button,'Saving...',()=>api(window.location.pathname, {
    action:                   'update',
    id,
    domain_name:              domain,
    transfer_status:          val('dtStatus'),
    process_start_date:       val('dtStartDate') || null,
    process_end_date:         val('dtEndDate')   || null,
    webnic_reseller_transfer: val('dtWebnic').trim() || null,
    notes:                    notes || null,
  }));
  if(!d)return;

  if (d.success) {
    showModalSuccessAndClose({
      modal:'dt',
      message:'Transfer updated.',
      onAfterClose:()=>location.reload()
    });
  } else {
    handleModalError({modal:'dt',error:{message:d.message,status:d.status},fallbackMessage:'The transfer could not be updated. Please try again.'});
  }
}

/* Delete Domain Transfer */
function deleteDt(id) {
  tracsConfirm('Delete this domain transfer record? This cannot be undone.', async () => {
    const d = await api(window.location.pathname, { action: 'delete', id });
    if (d.success) {
      toast('Transfer deleted', 'success');
      removeRow(`[data-dt-id="${id}"]`);
    } else {
      toast(d.message || "Couldn't delete the transfer. Please try again.", 'error');
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
  const sender_type = val('nSenderType');
  const receiver_type = val('nReceiverType');

  if (!amount || amount <= 0) { toast('Amount is required', 'error'); document.getElementById('nAmount').focus(); return; }

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
    toast(d.message || "Couldn't save the transfer. Please try again.", 'error');
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
  setVal('btTicket',        data.ticket_id || '');
  openModal('bt');
}

/* Save Edit (Update Balance Transfer) */
async function saveEditBt() {
  const id         = val('btId');
  const amount     = parseFloat(val('btAmount')) || 0;
  const button = document.querySelector('#btModal .modal-foot .btn-primary');

  if (!id)                    { toast('Invalid record', 'error'); return; }
  if (!amount || amount <= 0) { toast('Amount is required', 'error'); return; }

  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const sender_email   = val('btSenderEmail').trim();
  const receiver_email = val('btReceiverEmail').trim();
  if (sender_email   && !emailRx.test(sender_email))   { toast('Invalid sender email', 'error');   return; }
  if (receiver_email && !emailRx.test(receiver_email)) { toast('Invalid receiver email', 'error'); return; }

  const d = await withLoadingState(button,'Saving...',()=>api(API.BT.UPDATE, {
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
    ticket_id:        val('btTicket').trim() || null
  }));
  if(!d)return;

  if (d.success) {
    showModalSuccessAndClose({
      modal:'bt',
      message:'Transfer updated.',
      onAfterClose:()=>location.reload()
    });
  } else {
    handleModalError({modal:'bt',error:{message:d.message,status:d.status},fallbackMessage:'The transfer could not be updated. Please try again.'});
  }
}

/* Delete Balance Transfer */
function deleteBt(id) {
  tracsConfirm('Delete this transfer record? This cannot be undone.', async () => {
    const d = await api(API.BT.DELETE, { id });
    if (d.success) {
      toast('Transfer deleted', 'success');
      removeRow(`[data-bt-id="${id}"]`);
    } else {
      toast(d.message || "Couldn't delete the transfer. Please try again.", 'error');
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
   THEME MEMORY
═══════════════════════════════════════════════════════════════ */

const TRACS_THEME_KEY = 'tracs_theme_preference';
const TRACS_THEME_LEGACY_KEY = 'tracs-theme';
const TRACS_THEME_CHOICES = new Set(['light', 'dark', 'auto']);
const TRACS_VISUAL_THEME_KEY = 'tracs_visual_theme_preference';
const TRACS_VISUAL_THEME_LEGACY_KEY = 'tracs-visual-theme';
const TRACS_VISUAL_THEME_CHOICES = new Set(['default']);

function tracsAutoThemeForDate(date = new Date()) {
  const hour = date.getHours();
  return hour >= 6 && hour < 18 ? 'light' : 'dark';
}

function tracsBrowserTheme() {
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function tracsNormalizeThemePreference(value) {
  if (value === 'system') return 'auto';
  return TRACS_THEME_CHOICES.has(value) ? value : '';
}

function tracsNormalizeVisualThemePreference(value) {
  const normalized = String(value || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
  if (normalized === 'tracs_v2' || normalized === 'tracsv2' || normalized === 'intercom_inspired') return 'default';
  return TRACS_VISUAL_THEME_CHOICES.has(normalized) ? normalized : '';
}

function tracsVisualThemeAttribute(value) {
  return 'default';
}

function tracsGetThemePreference() {
  const stored = tracsNormalizeThemePreference(localStorage.getItem(TRACS_THEME_KEY));
  if (stored) return stored;

  const legacy = tracsNormalizeThemePreference(localStorage.getItem(TRACS_THEME_LEGACY_KEY));
  if (legacy) {
    localStorage.setItem(TRACS_THEME_KEY, legacy);
    return legacy;
  }

  return '';
}

function tracsGetVisualThemePreference() {
  const stored = tracsNormalizeVisualThemePreference(localStorage.getItem(TRACS_VISUAL_THEME_KEY));
  if (stored) return stored;

  const legacy = tracsNormalizeVisualThemePreference(localStorage.getItem(TRACS_VISUAL_THEME_LEGACY_KEY));
  if (legacy) {
    localStorage.setItem(TRACS_VISUAL_THEME_KEY, legacy);
    return legacy;
  }

  return 'default';
}

function tracsResolveTheme(preference = tracsGetThemePreference()) {
  if (preference === 'light' || preference === 'dark') return preference;
  if (preference === 'auto') return tracsAutoThemeForDate();
  return tracsBrowserTheme() || 'light';
}

function tracsApplyTheme(preference = tracsGetThemePreference()) {
  const normalized = tracsNormalizeThemePreference(preference);
  const applied = tracsResolveTheme(normalized);
  const html = document.documentElement;

  html.setAttribute('data-theme', applied);
  html.setAttribute('data-theme-preference', normalized || 'browser');
  html.setAttribute('data-applied-theme', applied);
  tracsSyncThemeMenu(normalized, applied);

  document.querySelectorAll('.flatpickr-calendar').forEach(calendar => {
    calendar.classList.toggle('tracs-flatpickr-dark', applied === 'dark');
    calendar.classList.toggle('tracs-flatpickr-light', applied === 'light');
  });

  return applied;
}

function tracsApplyVisualTheme(preference = tracsGetVisualThemePreference()) {
  const normalized = tracsNormalizeVisualThemePreference(preference) || 'default';
  const html = document.documentElement;
  html.setAttribute('data-visual-theme', tracsVisualThemeAttribute(normalized));
  html.setAttribute('data-visual-theme-preference', normalized);
  return normalized;
}

function tracsSetThemePreference(preference) {
  const normalized = tracsNormalizeThemePreference(preference) || 'auto';
  localStorage.setItem(TRACS_THEME_KEY, normalized);
  localStorage.setItem(TRACS_THEME_LEGACY_KEY, normalized);
  tracsApplyTheme(normalized);
}

function tracsSetVisualThemePreference(preference) {
  const normalized = tracsNormalizeVisualThemePreference(preference) || 'default';
  localStorage.setItem(TRACS_VISUAL_THEME_KEY, normalized);
  localStorage.setItem(TRACS_VISUAL_THEME_LEGACY_KEY, normalized);
  tracsApplyVisualTheme(normalized);
}

function tracsSyncThemeMenu(preference = tracsGetThemePreference(), applied = tracsResolveTheme(preference)) {
  const selected = preference || '';
  const tip = document.getElementById('themeTip');
  const toggle = document.getElementById('themeToggle');
  const label = selected === 'auto'
    ? `Theme: Auto (${applied === 'dark' ? 'Dark' : 'Light'})`
    : selected === 'dark'
      ? 'Theme: Dark'
      : selected === 'light'
        ? 'Theme: Light'
        : `Theme: Browser (${applied === 'dark' ? 'Dark' : 'Light'})`;

  if (tip) tip.textContent = label;
  if (toggle) toggle.setAttribute('aria-label', label);

  document.querySelectorAll('[data-theme-choice]').forEach(option => {
    const isActive = option.getAttribute('data-theme-choice') === selected;
    option.classList.toggle('is-active', isActive);
    option.setAttribute('aria-checked', isActive ? 'true' : 'false');
  });
}

function tracsOpenThemeMenu() {
  const wrap = document.getElementById('themeMenuWrap');
  const toggle = document.getElementById('themeToggle');
  if (!wrap || !toggle) return;
  tracsCloseIconPopups?.(wrap);
  tracsSetCustomPopupOpen?.(wrap, true);
}

function tracsCloseThemeMenu() {
  const wrap = document.getElementById('themeMenuWrap');
  const toggle = document.getElementById('themeToggle');
  if (!wrap || !toggle) return;
  tracsSetCustomPopupOpen?.(wrap, false);
}

function tracsToggleThemeMenu() {
  const wrap = document.getElementById('themeMenuWrap');
  if (wrap?.classList.contains('is-open')) tracsCloseThemeMenu();
  else tracsOpenThemeMenu();
}

function tracsToggleTheme() {
  tracsToggleThemeMenu();
}

function tracsInitThemeMemory() {
  tracsApplyTheme();
  tracsApplyVisualTheme();

  const toggle = document.getElementById('themeToggle');
  const wrap = document.getElementById('themeMenuWrap');
  const menu = document.getElementById('themeMenu');

  toggle?.addEventListener('click', event => {
    event.stopPropagation();
    tracsToggleThemeMenu();
  });

  menu?.addEventListener('click', event => {
    const option = event.target.closest('[data-theme-choice]');
    if (!option) return;
    tracsSetThemePreference(option.getAttribute('data-theme-choice'));
    tracsCloseThemeMenu();
  });

  document.addEventListener('click', event => {
    if (wrap && !wrap.contains(event.target)) tracsCloseThemeMenu();
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') tracsCloseThemeMenu();
  });

  setInterval(() => {
    if (tracsGetThemePreference() === 'auto') tracsApplyTheme('auto');
  }, 120000);

  const darkQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  darkQuery?.addEventListener?.('change', () => {
    if (!tracsGetThemePreference()) tracsApplyTheme('');
  });

  window.addEventListener('storage', event => {
    if (event.key === TRACS_THEME_KEY || event.key === TRACS_THEME_LEGACY_KEY) tracsApplyTheme();
    if (event.key === TRACS_VISUAL_THEME_KEY || event.key === TRACS_VISUAL_THEME_LEGACY_KEY) tracsApplyVisualTheme();
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', tracsInitThemeMemory);
} else {
  tracsInitThemeMemory();
}

/* ── Calendar Initialization (Flatpickr) ── */
document.addEventListener('DOMContentLoaded', () => {
  initTaskMonitoringTabs();

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
      altFormat: isTimeOnly ? "H:i" : (isDateTime ? "d-m-Y H:i" : "d-m-Y"),
      allowInput: true,
      clickOpens: false,
      altInputClass: altClass,
      placeholder: isTimeOnly ? "HH:MM" : (isDateTime ? "DD-MM-YYYY --:--" : "DD-MM-YYYY"),
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
