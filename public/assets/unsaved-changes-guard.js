/*! TRACS reusable unsaved-changes protection */
'use strict';

(function initTracsUnsavedChanges(global) {
  if (global.TRACSUnsavedChanges) return;

  const editableSelector = [
    'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([readonly]):not([disabled])',
    'textarea:not([readonly]):not([disabled])',
    'select:not([disabled])',
    'input[data-unsaved-track]',
    'input.flatpickr-input',
    '[contenteditable="true"]'
  ].join(',');
  const ignoredSelector = [
    '[data-unsaved-ignore]',
    '.search-input',
    '[type="search"]',
    'form[method="get"] *',
    '[data-filter]',
    '[data-calendar-filter]',
    '[data-dpc-filter]',
    '[data-dpc-sort-secondary]'
  ].join(',');

  const scopes = new Set();
  const registeredRoots = new WeakSet();
  const bypassForms = new WeakSet();
  const originals = new WeakMap();
  const dirtyElements = new Set();
  const trackedControls = new Set();
  const stateTrackers = new Set();
  const initializing = new Set();
  let pendingPrompt = null;
  let allowNextUnload = false;
  let bar = null;
  let dialog = null;

  function serializeState(value) {
    const ordered = item => Array.isArray(item) ? item.map(ordered)
      : item && typeof item === 'object' ? Object.fromEntries(Object.keys(item).sort().map(key => [key, ordered(item[key])])) : item;
    return JSON.stringify(ordered(value));
  }

  function normalizeCurrency(raw, decimalComma = false) {
    let value = String(raw ?? '').trim().replace(/rp|\$|\s/gi, '').replace(/[^\d.,+-]/g, '');
    if (!value) return '';
    if (decimalComma && value.includes(',') && !value.includes('.')) {
      value = value.replace(',', '.');
    } else if (value.includes(',') && value.includes('.')) {
      const decimalIndex = Math.max(value.lastIndexOf(','), value.lastIndexOf('.'));
      value = `${value.slice(0, decimalIndex).replace(/[.,]/g, '')}.${value.slice(decimalIndex + 1).replace(/[.,]/g, '')}`;
    } else if (value.includes(',')) {
      const parts = value.split(',');
      value = parts.length === 2 && parts[1].length <= 2 ? `${parts[0]}.${parts[1]}` : parts.join('');
    } else if (value.includes('.')) {
      const parts = value.split('.');
      const grouped = parts.length > 2 || (parts.length === 2 && parts[1].length === 3);
      if (grouped) value = parts.join('');
    }
    const numeric = Number(value);
    return Number.isFinite(numeric) ? String(numeric) : String(raw ?? '').trim();
  }

  function valueOf(control, scope = null) {
    if (scope?.normalize) return String(scope.normalize(control));
    if (control.matches('[contenteditable="true"]')) return control.textContent || '';
    if (control.multiple && control.tagName === 'SELECT') return JSON.stringify(Array.from(control.selectedOptions, option => option.value).sort());
    if (control.type === 'checkbox' || control.type === 'radio') return control.checked ? '1' : '0';
    if (control.type === 'file') {
      return Array.from(control.files || []).map(file => `${file.name}:${file.size}:${file.lastModified}`).join('|');
    }
    if (control.dataset.currency === 'IDR' || control.closest('.idr-input, [data-currency="IDR"]')) {
      return normalizeCurrency(control.value);
    }
    if (control.dataset.currency === 'USD' || control.closest('.usd-input, [data-currency="USD"]')) {
      return normalizeCurrency(control.value, true);
    }
    return String(control.value ?? '').replace(/\r\n/g, '\n');
  }

  function elementScope(control) {
    let match = null;
    scopes.forEach(scope => {
      if (!scope.roots.some(root => root === control || root.contains(control))) return;
      if (match && !match.roots.some(parent => scope.roots.some(root => parent.contains(root)))) return;
      match = scope;
    });
    return match;
  }

  function isIgnored(control, existing = false) {
    if (control instanceof Element && (control.matches('.flatpickr-alt-input') || control.closest('[data-unsaved-state]'))) return true;
    if (!(control instanceof Element)
      || (!control.matches(editableSelector) && !(existing && control.matches('input, textarea, select, [contenteditable]')))
      || control.matches(ignoredSelector)
      || !!control.closest(ignoredSelector)) {
      return true;
    }
    if (control.hasAttribute('data-unsaved-track')) return false;
    if (elementScope(control)?.ignore && control.matches(elementScope(control).ignore)) return true;
    if (control.closest('form:not([method="get"]), .modal-overlay, dialog')) return false;
    const filterHint = [
      control.id,
      control.getAttribute('name'),
      control.getAttribute('placeholder'),
      control.getAttribute('aria-label'),
      control.className
    ].filter(value => typeof value === 'string').join(' ').toLowerCase();
    return /(search|filter|sort|pagination|page[_-]?size|per[_-]?page|view[_-]?mode)/.test(filterHint);
  }

  function snapshot(control) {
    if (isIgnored(control) || originals.has(control)) return;
    originals.set(control, valueOf(control, elementScope(control)));
    trackedControls.add(control);
  }

  function syncControl(control) {
    if (isIgnored(control)) return;
    if (Array.from(initializing).some(root => root.contains(control))) return;
    snapshot(control);
    const original = originals.get(control);
    const current = valueOf(control, elementScope(control));
    if (current === original) dirtyElements.delete(control);
    else dirtyElements.add(control);
    syncUi();
  }

  function connectedDirtyElements() {
    trackedControls.forEach(control => {
      if (!control.isConnected) { trackedControls.delete(control); dirtyElements.delete(control); return; }
      if (isIgnored(control, true) || Array.from(initializing).some(root => root.contains(control))) { dirtyElements.delete(control); return; }
      const modal = control.closest('.modal-overlay, .dpc-modal, .infra-modal, .cf-modal, dialog');
      if (modal && (modal.hidden || modal.classList.contains('hidden') || modal.style.display === 'none' || (modal.tagName === 'DIALOG' && !modal.open))) { dirtyElements.delete(control); return; }
      if (valueOf(control, elementScope(control)) === originals.get(control)) dirtyElements.delete(control);
      else dirtyElements.add(control);
    });
    stateTrackers.forEach(tracker => {
      const active = tracker.root.isConnected && !initializing.has(tracker.root) && (!tracker.active || tracker.active());
      if (active && serializeState(tracker.getState()) !== tracker.baseline) dirtyElements.add(tracker.root);
      else dirtyElements.delete(tracker.root);
    });
    Array.from(dirtyElements).forEach(control => {
      if (!control.isConnected) dirtyElements.delete(control);
    });
    return Array.from(dirtyElements);
  }

  function isDirty(root = null) {
    return connectedDirtyElements().some(control => !root || root === control || root.contains(control));
  }

  function isDirtyOutside(root) {
    return connectedDirtyElements().some(control => root !== control && !root.contains(control));
  }

  function dirtyScope(root = null) {
    const dirty = connectedDirtyElements().find(control => !root || root === control || root.contains(control));
    return dirty ? elementScope(dirty) : null;
  }

  function ensureBar(scope = null) {
    if (bar?.isConnected) return bar;
    bar = document.createElement('section');
    bar.className = 'tracs-unsaved-bar';
    bar.hidden = true;
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');
    bar.innerHTML = `
      <div class="tracs-unsaved-bar__message">
        <i data-lucide="triangle-alert" aria-hidden="true"></i>
        <span><strong>You have unsaved changes</strong><small>Save before leaving this page to avoid losing your edits.</small></span>
      </div>
      <div class="tracs-unsaved-bar__actions">
        <button type="button" class="btn btn-ghost btn-sm" data-unsaved-discard>Discard</button>
        <button type="button" class="btn btn-primary btn-sm" data-unsaved-save>Save now</button>
      </div>`;
    const mountBefore = document.querySelector(scope?.mountBefore || '[data-unsaved-bar-before]');
    const host = mountBefore?.parentElement || document.querySelector('.main-inner') || document.querySelector('.main') || document.body;
    if (mountBefore) host.insertBefore(bar, mountBefore);
    else host.prepend(bar);
    bar.querySelector('[data-unsaved-save]').addEventListener('click', () => runSave(dirtyScope()));
    bar.querySelector('[data-unsaved-discard]').addEventListener('click', async () => {
      const confirmed = await confirmChoice({
        title: 'Discard unsaved changes?',
        message: 'This restores the edited fields to their last saved values.',
        stayLabel: 'Keep Editing',
        leaveLabel: 'Discard Changes',
        showSave: false
      });
      if (confirmed === 'leave') discard();
    });
    global.tracsRefreshIcons?.(bar);
    return bar;
  }

  function syncUi() {
    const dirtyElements = connectedDirtyElements();
    const dirty = dirtyElements.length > 0;
    const scope = dirtyScope();
    const warningBar = ensureBar(scope);
    warningBar.hidden = !dirty || dirtyElements.every(control => control.closest?.('[data-unsaved-hide-bar]'));
    const saveButton = warningBar.querySelector('[data-unsaved-save]');
    if (saveButton) saveButton.hidden = !scope?.save;
    document.documentElement.classList.toggle('tracs-has-unsaved-changes', dirty);
  }

  function markSaved(root = null) {
    stateTrackers.forEach(tracker => {
      if (!root || root === tracker.root || root.contains(tracker.root)) tracker.baseline = serializeState(tracker.getState());
    });
    const controls = root
      ? [
          ...(root instanceof Element && root.matches(editableSelector) ? [root] : []),
          ...Array.from(root.querySelectorAll?.(editableSelector) || [])
        ]
      : Array.from(document.querySelectorAll(editableSelector));
    trackedControls.forEach(control => {
      if ((!root || root === control || root.contains(control)) && !controls.includes(control)) controls.push(control);
    });
    controls.forEach(control => {
      if (isIgnored(control, originals.has(control))) return;
      originals.set(control, valueOf(control, elementScope(control)));
      trackedControls.add(control);
      dirtyElements.delete(control);
    });
    if (root) {
      Array.from(dirtyElements).forEach(control => {
        if (root === control || root.contains(control)) dirtyElements.delete(control);
      });
    } else {
      dirtyElements.clear();
    }
    syncUi();
  }

  function discard(root = null) {
    const scope = dirtyScope(root);
    connectedDirtyElements().forEach(control => {
      if (root && root !== control && !root.contains(control)) return;
      if (!originals.has(control)) return;
      const original = originals.get(control);
      if (control.type === 'checkbox' || control.type === 'radio') control.checked = original === '1';
      else if (control.multiple && control.tagName === 'SELECT') Array.from(control.options).forEach(option => { option.selected = JSON.parse(original || '[]').includes(option.value); });
      else if (control._flatpickr) control._flatpickr.setDate(original || '', false);
      else if (control.type !== 'file' && !control.matches('[contenteditable="true"]')) control.value = original ?? '';
      else if (control.matches('[contenteditable="true"]')) control.textContent = original ?? '';
      else control.value = '';
      dirtyElements.delete(control);
      if(control.matches('select')) global.TRACSDropdowns?.syncSelect?.(control);
    });
    const syncIds = new Set(Array.from(trackedControls).filter(control => !root || root === control || root.contains(control)).map(control => control.getAttribute('data-sync')).filter(Boolean));
    syncIds.forEach(id => {
      const parts = Array.from(document.querySelectorAll('[data-sync]')).filter(control => control.getAttribute('data-sync') === id && !control.classList.contains('flatpickr-alt-input'));
      const date = parts.find(control => control.classList.contains('split-date'))?.value;
      const time = parts.find(control => control.classList.contains('split-time'))?.value;
      const target = document.getElementById(id);
      if (target) target.value = date && time ? `${date}T${time}` : '';
    });
    stateTrackers.forEach(tracker => {
      if (!root || root === tracker.root || root.contains(tracker.root)) tracker.restore?.(JSON.parse(tracker.baseline));
    });
    if (scope?.discard) scope.discard();
    syncUi();
  }

  function ensureDialog() {
    if (dialog?.isConnected) return dialog;
    dialog = document.createElement('div');
    dialog.className = 'tracs-unsaved-dialog-overlay hidden';
    dialog.style.zIndex = '2147483646';
    dialog.innerHTML = `
      <div class="tracs-unsaved-dialog" role="dialog" aria-modal="true" aria-labelledby="tracsUnsavedTitle" aria-describedby="tracsUnsavedMessage">
        <div class="tracs-unsaved-dialog__icon"><i data-lucide="triangle-alert"></i></div>
        <div>
          <h2 id="tracsUnsavedTitle">Unsaved changes</h2>
          <p id="tracsUnsavedMessage">You have changes that haven't been saved. Discard them and leave?</p>
        </div>
        <div class="tracs-unsaved-dialog__actions">
          <button type="button" class="btn btn-ghost" data-unsaved-stay>Keep Editing</button>
          <button type="button" class="btn btn-danger" data-unsaved-leave>Discard Changes</button>
          <button type="button" class="btn btn-primary" data-unsaved-dialog-save>Save changes</button>
        </div>
      </div>`;
    document.body.appendChild(dialog);
    dialog.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        dialog.querySelector('[data-unsaved-stay]')?.click();
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = Array.from(dialog.querySelectorAll('button:not([hidden]):not([disabled])'));
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
    global.tracsRefreshIcons?.(dialog);
    return dialog;
  }

  function confirmChoice(options = {}) {
    if (pendingPrompt) return pendingPrompt;
    const overlay = ensureDialog();
    const nativeDialog = Array.from(document.querySelectorAll('dialog[open]')).at(-1);
    (nativeDialog || document.body).appendChild(overlay);
    const title = overlay.querySelector('#tracsUnsavedTitle');
    const message = overlay.querySelector('#tracsUnsavedMessage');
    const stay = overlay.querySelector('[data-unsaved-stay]');
    const leave = overlay.querySelector('[data-unsaved-leave]');
    const save = overlay.querySelector('[data-unsaved-dialog-save]');
    title.textContent = options.title || 'Unsaved changes';
    message.textContent = options.message || "You have changes that haven't been saved. Discard them and leave?";
    stay.textContent = options.stayLabel || 'Keep Editing';
    leave.textContent = options.leaveLabel || 'Discard Changes';
    save.textContent = options.saveLabel || 'Save changes';
    save.hidden = options.showSave === false;
    overlay.classList.remove('hidden');
    const previousFocus = document.activeElement;
    pendingPrompt = new Promise(resolve => {
      const finish = choice => {
        overlay.classList.add('hidden');
        stay.onclick = leave.onclick = save.onclick = null;
        previousFocus?.focus?.({ preventScroll: true });
        pendingPrompt = null;
        resolve(choice);
      };
      stay.onclick = () => finish('stay');
      leave.onclick = () => finish('leave');
      save.onclick = () => finish('save');
      stay.focus();
    });
    return pendingPrompt;
  }

  async function runSave(scope = dirtyScope()) {
    if (!scope?.save || scope.saving) return false;
    scope.saving = true;
    try {
      const result = await scope.save();
      if (result === false) return false;
      scope.roots.forEach(root => markSaved(root));
      return true;
    } catch (error) {
      global.showToast?.(error?.message || 'Your changes could not be saved.', 'error');
      return false;
    } finally {
      scope.saving = false;
    }
  }

  async function protect(action, options = {}) {
    if (pendingPrompt) return false;
    const root = options.root || null;
    if (!isDirty(root)) {
      action();
      return true;
    }
    const scope = dirtyScope(root);
    const choice = await confirmChoice({
      title: 'Unsaved changes',
      showSave: false
    });
    if (choice === 'leave') {
      document.dispatchEvent(new CustomEvent('tracs:before-unsaved-leave', { detail: { root } }));
      discard(root);
      markSaved(root);
      if (!options.modal) allowNextUnload = true;
      try {
        action();
        return true;
      } finally {
        if (options.modal) syncUi();
      }
    }
    if (choice === 'save') {
      const saved = await runSave(scope);
      if (!saved) {
        return false;
      }
      if (!options.modal) allowNextUnload = true;
      try {
        action();
        return true;
      } finally {
        if (options.modal) syncUi();
      }
    }
    return false;
  }

  function register(options = {}) {
    const roots = (Array.isArray(options.root) ? options.root : [options.root || document])
      .map(root => typeof root === 'string' ? document.querySelector(root) : root)
      .filter(Boolean);
    if (!roots.length) return null;
    const freshRoots = roots.filter(root => !registeredRoots.has(root));
    if (!freshRoots.length) return null;
    const scope = {
      ...options,
      roots: freshRoots,
      ignore: options.ignore || ''
    };
    scopes.add(scope);
    freshRoots.forEach(root => {
      registeredRoots.add(root);
      root.querySelectorAll(editableSelector).forEach(control => {
        if (isIgnored(control)) return;
        originals.set(control, valueOf(control, elementScope(control)));
        trackedControls.add(control);
      });
    });
    syncUi();
    return scope;
  }

  function autoRegisterForms() {
    document.querySelectorAll('form').forEach(form => {
      if (String(form.method || 'get').toLowerCase() === 'get' || form.matches('[data-unsaved-ignore]')) return;
      register({
        root: form,
        mountBefore: form.dataset.unsavedMountBefore || '',
        save: () => {
          const submit = form.querySelector('button[type="submit"], input[type="submit"]');
          if (!submit) return false;
          return new Promise(resolve => {
            const timeout = setTimeout(() => resolve(false), 15000);
            form.addEventListener('tracs:save-success', () => {
              clearTimeout(timeout);
              resolve(true);
            }, { once: true });
            form.requestSubmit(submit);
          });
        }
      });
    });
  }

  function rescanDynamicEditableSurfaces(root = document) {
    const scopeRoot = root instanceof Element ? root : document;
    if (scopeRoot.matches?.('form, .modal-overlay, .dpc-modal, .infra-modal, .cf-modal')) {
      if (scopeRoot.matches('form')) {
        autoRegisterForms();
      } else {
        autoRegisterModals();
      }
      return;
    }
    if (scopeRoot.querySelector?.('form, .modal-overlay, .dpc-modal, .infra-modal, .cf-modal')) {
      autoRegisterForms();
      autoRegisterModals();
    }
  }

  function autoRegisterModals() {
    document.querySelectorAll('.modal-overlay, .dpc-modal, .infra-modal, .cf-modal').forEach(modal => {
      if (!modal.querySelector(editableSelector) || modal.matches('[data-unsaved-ignore]')) return;
      const saveButton = modal.matches('[data-unsaved-no-auto-save]') ? null : modal.querySelector(
        '[data-unsaved-save], button[type="submit"], input[type="submit"], [id$="SaveBtn"], .modal-foot .btn-primary, .dpc-modal-footer .btn-primary'
      );
      register({
        root: modal,
        save: saveButton ? () => new Promise(resolve => {
          let settled = false;
          const finish = result => {
            if (settled) return;
            settled = true;
            modal.removeEventListener('tracs:save-success', onSuccess);
            modal.removeEventListener('tracs:save-error', onError);
            resolve(result);
          };
          const onSuccess = () => finish(true);
          const onError = () => finish(false);
          modal.addEventListener('tracs:save-success', onSuccess, { once: true });
          modal.addEventListener('tracs:save-error', onError, { once: true });
          saveButton.click();
          window.setTimeout(() => finish(false), 20000);
        }) : null
      });
    });
  }

  function autoRegisterEditablePage() {
    const page = document.body?.dataset.tracsPage || '';
    // Only pages with genuine standalone editable content — fields that live
    // outside any <form> and persist solely via an explicit, separate save
    // action — belong here. A real <form method="post"> is already protected
    // page-wide by autoRegisterForms() without needing this. Pages whose only
    // "editable" controls are real-time-saved (checklist/reminder checkboxes,
    // etc.) must NOT be in this list: this scope doesn't cause that class of
    // bug by itself (dirty-tracking is global, not scope-gated — see the
    // `data-unsaved-ignore` opt-out on those controls instead), but listing
    // pages here without a real editable surface just adds noise. Audited
    // 2026-07-02 against every editablePages page previously listed:
    //   mom               — agenda topic/decision fields, plain divs (no <form>)
    //   domain_price_crosscheck — price matrix grid (has its own dedicated
    //                       register() too; also listed here per product ask)
    //   domains           — #dtModal edit-transfer fields, plain div (no <form>)
    //   finance           — #btModal edit-transfer fields, plain div (no <form>)
    //   feedback          — inline quick-add feedback fields, no <form> wrapper
    //   infrastructure-pulse — add-server form lacks a method attribute, so
    //                       autoRegisterForms() treats it as GET and skips it
    //   shifting-assignment — already self-manages via its own markSaved() calls
    // Everything else audited (cases, shift-reports, activity, dashboard,
    // checklist, reminders, user-management, profile, monitoring,
    // intern-management) had zero standalone editable content: either no
    // forms/modals at all, or all editing already flows through a real
    // <form method="post"> that autoRegisterForms() covers independently.
    const editablePages = new Set([
      'mom', 'domain_price_crosscheck', 'domains', 'finance', 'feedback',
      'infrastructure-pulse', 'shifting-assignment'
    ]);
    const root = document.querySelector('.main-inner');
    if (!root || !editablePages.has(page)) return;
    register({
      root,
      ignore: [
        '.compact-select',
        '.search-form-wrap *',
        '.filter-group-wrap *',
        '.report-export-popover *',
        '[data-unsaved-ignore]'
      ].join(',')
    });
  }

  document.addEventListener('focusin', event => snapshot(event.target), true);
  document.addEventListener('input', event => { syncControl(event.target); queueMicrotask(syncUi); }, true);
  document.addEventListener('change', event => { syncControl(event.target); queueMicrotask(syncUi); }, true);
  function handleSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (event.defaultPrevented) return;
    if (bypassForms.has(form)) {
      bypassForms.delete(form);
      queueMicrotask(() => {
        if (!event.defaultPrevented) allowNextUnload = true;
      });
      return;
    }
    if (String(form.method || 'get').toLowerCase() === 'get' && isDirty()) {
      event.preventDefault();
      protect(() => {
        bypassForms.add(form);
        form.requestSubmit(event.submitter || undefined);
      });
      return;
    }
    if (isDirtyOutside(form)) {
      event.preventDefault();
      protect(() => {
        bypassForms.add(form);
        form.requestSubmit(event.submitter || undefined);
      });
      return;
    }
    queueMicrotask(() => {
      if (!event.defaultPrevented) allowNextUnload = true;
    });
  }
  document.addEventListener('tracs:save-success', event => markSaved(event.detail?.root || event.target), true);

  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download') || link.dataset.unsavedIgnore !== undefined) return;
    if (!isDirty()) return;
    const destination = new URL(link.href, window.location.href);
    if (!['http:', 'https:'].includes(destination.protocol)) return;
    if (destination.href === window.location.href || destination.hash && destination.pathname === location.pathname && destination.search === location.search) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    protect(() => {
      allowNextUnload = true;
      window.location.assign(destination.href);
    });
  }, true);

  window.addEventListener('beforeunload', event => {
    if (allowNextUnload || !isDirty()) return;
    document.dispatchEvent(new CustomEvent('tracs:before-unsaved-leave'));
    event.preventDefault();
    event.returnValue = '';
  });

  window.addEventListener('pageshow', () => { allowNextUnload = false; });

  window.addEventListener('DOMContentLoaded', () => {
    autoRegisterForms();
    autoRegisterModals();
    autoRegisterEditablePage();
    document.querySelectorAll(editableSelector).forEach(snapshot);
    document.addEventListener('submit', handleSubmit);
    const observer = new MutationObserver(records => {
      let removedDirty = false;
      records.forEach(record => {
        record.addedNodes.forEach(node => {
          if (node instanceof Element) {
            rescanDynamicEditableSurfaces(node);
            if (node.matches(editableSelector)) snapshot(node);
            node.querySelectorAll(editableSelector).forEach(snapshot);
          }
        });
        record.removedNodes.forEach(node => {
          if (!(node instanceof Element)) return;
          const before = dirtyElements.size;
          connectedDirtyElements();
          removedDirty = removedDirty || dirtyElements.size !== before;
        });
      });
      if (removedDirty) syncUi();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  });

  global.TRACSUnsavedChanges = {
    register,
    isDirty,
    markSaved,
    discard,
    protect,
    refresh: syncUi,
    captureInitialState: markSaved,
    beginInitialization(root) { initializing.add(root); },
    finishInitialization(root) { markSaved(root); initializing.delete(root); syncUi(); },
    trackState(root, getState, options = {}) {
      const tracker = { root, getState, ...options, baseline: serializeState(options.initialState ?? getState()) };
      stateTrackers.add(tracker);
      return () => { stateTrackers.delete(tracker); dirtyElements.delete(root); syncUi(); };
    },
    get prompting() { return !!pendingPrompt; },
    requestModalClose(modal, close) {
      if (modal?.matches('[aria-busy="true"]') || modal?.querySelector('[aria-busy="true"]')) return Promise.resolve(false);
      return protect(close, { root: modal, modal: true });
    }
  };
})(window);
