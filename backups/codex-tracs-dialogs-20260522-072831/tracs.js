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
const TOAST_FLASH_KEY='tracs:toast-flash';
let _lastToast=null;
function toastIconFor(msg,type){
  if(type === 'error' || type === 'warning') return 'alert-triangle';
  if(type === 'success') return 'check';
  return 'info';
}
function toast(msg,type='info',ms=6400){
  _lastToast={msg,type};
  const t=document.createElement('div');
  const ic=toastIconFor(msg,type);
  t.className=`toast ${type}`;
  t.innerHTML=`<span class="toast-radar"><i data-lucide="${ic}" class="toast-icon"></i></span><span class="toast-msg">${msg}</span>`;
  _dock.appendChild(t);
  if(window.lucide) lucide.createIcons({ nodes: [t.querySelector('[data-lucide]')] });
  const dismiss=()=>{
    if(!t.isConnected)return;
    t.classList.add('is-leaving');
    t.addEventListener('animationend',event=>{
      if(event.target === t && event.animationName === 'toast-fade-right') t.remove();
    },{once:true});
    setTimeout(()=>t.remove(),520);
  };
  setTimeout(dismiss,ms);
}
window.toast=toast;

function queueToastAfterReload(){
  if(!_lastToast || !_lastToast.msg || _lastToast.type === 'error')return;
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
    if(data?.msg)toast(data.msg,data.type||'info');
  }catch(e){}
}
showQueuedToast();
window.reloadAfterToast=reloadAfterToast;
window.navigateAfterToast=navigateAfterToast;

/* ── Modal system ─────────────────────────────────────── */
function openModal(id){
  const el=document.getElementById(id+'Modal');
  if(el)el.classList.remove('hidden');
  window.TRACSDropdowns?.syncAll();
}
function closeModal(id){const el=document.getElementById(id+'Modal');if(el)el.classList.add('hidden');}
function closeAllModals(){document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(o=>o.classList.add('hidden'));}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAllModals();});
document.addEventListener('click',e=>{if(e.target.classList.contains('modal-overlay'))closeAllModals();});

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
      if (confirm) confirm.disabled = true;
      const res = await fetch('/api/user-avatar.php', { method: 'POST', body: form });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Unable to save profile picture.');
      updateAvatarUi(json.data.user_id, json.data.avatar_url || '', json.data.initials || 'U', json.data.avatar_path || '');
      closeCropModal();
      toast(json.message || 'Profile picture updated.', 'success');
    } catch (error) {
      toast(error.message || 'Unable to save profile picture.', 'error');
    } finally {
      if (confirm) confirm.disabled = false;
    }
  }

  async function removeAvatar(userId) {
    if (!userId || !confirm('Remove this profile picture?')) return;
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
    return await r.json();
  }catch(e){return{success:false,message:'Network error'};}
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

function saveFeedback() {
    const id = document.getElementById('feedbackId').value;
    const url = id ? 'api/feedback-update.php' : 'api/feedback-create.php';
    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('email', document.getElementById('feedbackEmail').value);
    appendMultiValues(fd, 'service', selectedValues('feedbackService'));
    appendMultiValues(fd, 'reason', selectedValues('feedbackReason'));
    fd.append('reference', document.getElementById('feedbackReference').value);
    fd.append('resolution', document.getElementById('feedbackResolution').value);
    fd.append('details', document.getElementById('feedbackDetails').value);

    fetch(url, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            toast(id ? 'Feedback updated' : 'Feedback added', 'success');
            reloadAfterToast();
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
        if (res.success) {
          toast('Feedback deleted', 'success');
          reloadAfterToast();
        }
        else alert(res.error || 'Delete failed');
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
        alert('Service and Reason are required.');
        return;
    }

    fetch('api/feedback-create.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            toast('Feedback added', 'success');
            reloadAfterToast();
        } else {
            alert(res.error || 'Save failed');
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
    const row=document.querySelector(`[data-cid="${id}"]`);
    const d=await api(API.CASE.DELETE,{id});
    if(d.success){
      const badgeContainer = document.getElementById('notif-badge-container');
      if (badgeContainer && badgeContainer.dataset.badgeMode !== 'alerts' && row?.dataset.status !== 'completed') {
        const current = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
        badgeContainer.dataset.staticExtra = String(Math.max(0, current - 1));
        refreshNotificationBadge();
      }
      toast('Case deleted','success');removeRow(`[data-cid="${id}"]`);
    }
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
  confirm('Delete this reminder permanently? Use Mark Done for handled reminders so the history remains available.',async()=>{
    const row=document.querySelector(`[data-rid="${id}"]`);
    const d=await api(API.REMINDER.DELETE,{id});
    if(d.success){
      const badgeContainer = document.getElementById('notif-badge-container');
      if (badgeContainer && row?.dataset.completed !== '1' && (badgeContainer.dataset.badgeMode !== 'alerts' || row?.dataset.notifAlert === '1')) {
        const current = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
        badgeContainer.dataset.staticExtra = String(Math.max(0, current - 1));
        refreshNotificationBadge();
      }
      toast('Reminder deleted','success');removeRow(`[data-rid="${id}"]`);
    }
    else toast(d.message||'Error','error');
  }, 'Delete Reminder');
}
async function toggleReminder(id,checked){
  const row=document.querySelector(`[data-rid="${id}"]`);
  setCheckablePending(row, true);
  const d=await api(API.REMINDER.TOGGLE,{id,is_completed:checked?1:0});
  if(d.success){
    row?.querySelector('.rem-title')?.classList.toggle('done',checked);
    row?.querySelectorAll('.rem-check').forEach(box => { box.checked = checked; });
    const badgeContainer = document.getElementById('notif-badge-container');
    if (badgeContainer) {
      const current = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
      const alertMode = badgeContainer.dataset.badgeMode === 'alerts';
      let shouldAdjust = true;
      if (alertMode) {
        const due = row?.dataset.due ? new Date(row.dataset.due).getTime() : NaN;
        const dueSoon = Number.isFinite(due) && due <= Date.now() + 5 * 60 * 1000;
        shouldAdjust = row?.dataset.notifAlert === '1' || (!checked && dueSoon);
        if (row) row.dataset.notifAlert = (!checked && dueSoon) ? '1' : '0';
      }
      if (shouldAdjust) {
        badgeContainer.dataset.staticExtra = String(Math.max(0, current + (checked ? -1 : 1)));
      }
      refreshNotificationBadge();
    }
    syncReminderPrimaryAction(row, id, checked);
    moveCheckableRow(row, checked);
    _updateProgress();
    toast(checked?'Reminder completed':'Reminder reopened','success');
  }else{
    if(row){
      const box=row.querySelector('.rem-check');
      if(box) box.checked=!checked;
    }
    toast('Error updating reminder','error');
  }
  setCheckablePending(row, false);
}

function completeReminder(id){
  const row=document.querySelector(`[data-rid="${id}"]`);
  row?.querySelectorAll('.rem-check').forEach(box => { box.checked = true; });
  toggleReminder(id, true);
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
    action.onclick = () => toggleReminder(id, false);
    action.innerHTML = compact ? '<i data-lucide="rotate-ccw" class="icon-sm"></i>' : '<i data-lucide="rotate-ccw" class="icon-xs"></i>Reopen';
  }else{
    action.className = compact ? 'btn btn-ghost btn-icon rem-done-btn rem-primary-action' : 'btn btn-success btn-sm rem-done-btn rem-primary-action';
    action.title = compact ? 'Mark reminder done' : 'Mark reminder as done';
    action.setAttribute('aria-label', 'Mark reminder done');
    action.onclick = () => completeReminder(id);
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
  const d=await api(id?API.TASK.UPDATE:API.TASK.CREATE,{
    id,title,description:val('taskDesc')
  });
  if(d.success){toast(id?'Task updated':'Task created','success');closeModal('task');_reload();}
  else toast(d.message||'Error','error');
}
async function deleteTask(id){
  confirm('Delete this task?',async()=>{
    const d=await api(API.TASK.DELETE,{id});
    if(d.success){
      const row=document.querySelector(`[data-tid="${id}"]`);
      const badgeContainer = document.getElementById('notif-badge-container');
      if (badgeContainer && row?.dataset.completed !== '1') {
        const current = parseInt(badgeContainer.dataset.uncheckedChecklist || '0', 10) || 0;
        badgeContainer.dataset.uncheckedChecklist = String(Math.max(0, current - 1));
      }
      toast('Task deleted','success');removeRow(`[data-tid="${id}"]`);_updateProgress();
    }
    else toast(d.message||'Error','error');
  });
}
async function toggleTask(id,checked){
  const row=document.querySelector(`[data-tid="${id}"]`);
  setCheckablePending(row, true);
  const d=await api(API.TASK.TOGGLE,{id,is_completed:checked?1:0});
  if(d.success){
    row?.querySelector('.task-title')?.classList.toggle('done',checked);
    const badgeContainer = document.getElementById('notif-badge-container');
    if (badgeContainer) {
      const current = parseInt(badgeContainer.dataset.uncheckedChecklist || '0', 10) || 0;
      badgeContainer.dataset.uncheckedChecklist = String(Math.max(0, current + (checked ? -1 : 1)));
    }
    moveCheckableRow(row, checked);
    _updateProgress();
    toast(checked?'Task completed':'Task reopened','success');
  }else{
    if(row){
      const box=row.querySelector('.task-chk');
      if(box) box.checked=!checked;
    }
    toast('Error updating task','error');
  }
  setCheckablePending(row, false);
}

function setCheckablePending(row, pending){
  if(!row)return;
  row.classList.toggle('is-updating', !!pending);
  const box=row.querySelector('.rem-check');
  if(box) box.disabled=!!pending;
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
  if(fill)fill.style.width=pct+'%';
  if(lbl)lbl.textContent=`${done} / ${total}`;
  if(pctlbl)pctlbl.textContent=pct+'%';

  refreshNotificationBadge(total - done);
}

function refreshNotificationBadge(pendingOverride){
  const badgeContainer = document.getElementById('notif-badge-container');
  if (!badgeContainer) return;
  if (badgeContainer.dataset.badgeMode === 'alerts') {
    const count = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
    badgeContainer.innerHTML = count > 0 ? `<span class="bell-badge">${Math.min(count, 99)}</span>` : '';
    return;
  }
  const taskBoxes=document.querySelectorAll('.task-chk');
  const pending = typeof pendingOverride === 'number'
    ? pendingOverride
    : (taskBoxes.length ? [...taskBoxes].filter(b=>!b.checked).length : 0);
  const extra = parseInt(badgeContainer.dataset.staticExtra || '0', 10) || 0;
  const checklistCount = parseInt(badgeContainer.dataset.uncheckedChecklist || String(pending), 10) || 0;
  const count = checklistCount + extra;
  if (count > 0) {
    badgeContainer.innerHTML = `<span class="bell-badge">${Math.min(count, 99)}</span>`;
  } else {
    badgeContainer.innerHTML = '';
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
    reloadAfterToast();

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
    reloadAfterToast();

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
  bindSidebarTooltips();

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
    toast(d.message || 'Error updating', 'error');
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
  setVal('btTicket',        data.ticket_id || '');
  openModal('bt');
}

/* Save Edit (Update Balance Transfer) */
async function saveEditBt() {
  const id         = val('btId');
  const amount     = parseFloat(val('btAmount')) || 0;

  if (!id)                    { toast('Invalid record', 'error'); return; }
  if (!amount || amount <= 0) { toast('Amount is required', 'error'); return; }

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
   THEME MEMORY
═══════════════════════════════════════════════════════════════ */

const TRACS_THEME_KEY = 'tracs_theme_preference';
const TRACS_THEME_LEGACY_KEY = 'tracs-theme';
const TRACS_THEME_CHOICES = new Set(['light', 'dark', 'auto']);

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

function tracsSetThemePreference(preference) {
  const normalized = tracsNormalizeThemePreference(preference) || 'auto';
  localStorage.setItem(TRACS_THEME_KEY, normalized);
  localStorage.setItem(TRACS_THEME_LEGACY_KEY, normalized);
  tracsApplyTheme(normalized);
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
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', tracsInitThemeMemory);
} else {
  tracsInitThemeMemory();
}

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
