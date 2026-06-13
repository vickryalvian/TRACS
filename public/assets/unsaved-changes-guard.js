/*! TRACS reusable unsaved-changes protection */
'use strict';

(function initTracsUnsavedChanges(global) {
  if (global.TRACSUnsavedChanges) return;

  const editableSelector = [
    'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([readonly]):not([disabled])',
    'textarea:not([readonly]):not([disabled])',
    'select:not([disabled])',
    '[contenteditable="true"]'
  ].join(',');
  const ignoredSelector = [
    '[data-unsaved-ignore]',
    '.search-input',
    '[type="search"]',
    'form[method="get"] *',
    '[data-filter]',
    '[data-dpc-filter]',
    '[data-dpc-sort-secondary]'
  ].join(',');

  const scopes = new Set();
  const bypassForms = new WeakSet();
  const originals = new WeakMap();
  const dirtyElements = new Set();
  let pendingPrompt = null;
  let allowNextUnload = false;
  let bar = null;
  let dialog = null;
  let historyGuardArmed = false;
  let suppressNextPop = false;
  let preserveHistoryForAction = false;

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
    if (control.matches('[contenteditable="true"]')) return control.textContent?.trim() || '';
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
    return String(control.value ?? '').trim();
  }

  function elementScope(control) {
    let match = null;
    scopes.forEach(scope => {
      if (match || !scope.roots.some(root => root === control || root.contains(control))) return;
      if (scope.ignore && control.matches(scope.ignore)) return;
      match = scope;
    });
    return match;
  }

  function isIgnored(control) {
    if (!(control instanceof Element)
      || !control.matches(editableSelector)
      || control.matches(ignoredSelector)
      || !!control.closest(ignoredSelector)) {
      return true;
    }
    if (control.hasAttribute('data-unsaved-track')) return false;
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
  }

  function syncControl(control) {
    if (isIgnored(control)) return;
    snapshot(control);
    const original = originals.get(control);
    const current = valueOf(control, elementScope(control));
    if (current === original) dirtyElements.delete(control);
    else dirtyElements.add(control);
    syncUi();
  }

  function connectedDirtyElements() {
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
        stayLabel: 'Keep editing',
        leaveLabel: 'Discard changes',
        showSave: false
      });
      if (confirmed === 'leave') discard();
    });
    global.tracsRefreshIcons?.(bar);
    return bar;
  }

  function syncUi() {
    const dirty = isDirty();
    const scope = dirtyScope();
    const warningBar = ensureBar(scope);
    warningBar.hidden = !dirty;
    const saveButton = warningBar.querySelector('[data-unsaved-save]');
    if (saveButton) saveButton.hidden = !scope?.save;
    document.documentElement.classList.toggle('tracs-has-unsaved-changes', dirty);
    if (dirty && !historyGuardArmed) {
      history.pushState({ ...(history.state || {}), tracsUnsavedGuard: true }, '', window.location.href);
      historyGuardArmed = true;
    } else if (!dirty && historyGuardArmed && !preserveHistoryForAction) {
      historyGuardArmed = false;
      suppressNextPop = true;
      history.back();
    } else if (!dirty && preserveHistoryForAction) {
      historyGuardArmed = false;
    }
  }

  function markSaved(root = null) {
    const controls = root
      ? Array.from(root.querySelectorAll(editableSelector))
      : Array.from(document.querySelectorAll(editableSelector));
    controls.forEach(control => {
      if (isIgnored(control)) return;
      originals.set(control, valueOf(control, elementScope(control)));
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
      const original = originals.get(control);
      if (control.type === 'checkbox' || control.type === 'radio') control.checked = original === '1';
      else if (control.type !== 'file' && !control.matches('[contenteditable="true"]')) control.value = original ?? '';
      else if (control.matches('[contenteditable="true"]')) control.textContent = original ?? '';
      else control.value = '';
      dirtyElements.delete(control);
      control.dispatchEvent(new Event('change', { bubbles: true }));
    });
    if (scope?.discard) scope.discard();
    syncUi();
  }

  function ensureDialog() {
    if (dialog?.isConnected) return dialog;
    dialog = document.createElement('div');
    dialog.className = 'tracs-unsaved-dialog-overlay hidden';
    dialog.innerHTML = `
      <div class="tracs-unsaved-dialog" role="dialog" aria-modal="true" aria-labelledby="tracsUnsavedTitle" aria-describedby="tracsUnsavedMessage">
        <div class="tracs-unsaved-dialog__icon"><i data-lucide="triangle-alert"></i></div>
        <div>
          <h2 id="tracsUnsavedTitle">Unsaved changes</h2>
          <p id="tracsUnsavedMessage">You have unsaved changes. Save your changes before leaving this page?</p>
        </div>
        <div class="tracs-unsaved-dialog__actions">
          <button type="button" class="btn btn-ghost" data-unsaved-stay>Stay on page</button>
          <button type="button" class="btn btn-danger" data-unsaved-leave>Leave without saving</button>
          <button type="button" class="btn btn-primary" data-unsaved-dialog-save>Save changes</button>
        </div>
      </div>`;
    document.body.appendChild(dialog);
    dialog.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        event.preventDefault();
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
    const title = overlay.querySelector('#tracsUnsavedTitle');
    const message = overlay.querySelector('#tracsUnsavedMessage');
    const stay = overlay.querySelector('[data-unsaved-stay]');
    const leave = overlay.querySelector('[data-unsaved-leave]');
    const save = overlay.querySelector('[data-unsaved-dialog-save]');
    title.textContent = options.title || 'Unsaved changes';
    message.textContent = options.message || 'You have unsaved changes. Save your changes before leaving this page?';
    stay.textContent = options.stayLabel || 'Stay on page';
    leave.textContent = options.leaveLabel || 'Leave without saving';
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
    if (!scope?.save) return false;
    try {
      const result = await scope.save();
      if (result === false) return false;
      markSaved(scope.roots[0] || null);
      return true;
    } catch (error) {
      global.showToast?.(error?.message || 'Your changes could not be saved.', 'error');
      return false;
    }
  }

  async function protect(action, options = {}) {
    const root = options.root || null;
    if (!isDirty(root)) {
      action();
      return true;
    }
    const scope = dirtyScope(root);
    const choice = await confirmChoice({
      title: options.modal ? 'Unsaved changes in this popup' : 'Unsaved changes',
      message: options.modal
        ? 'You have unsaved changes in this popup. Close without saving?'
        : 'You have unsaved changes. Save your changes before leaving this page?',
      stayLabel: options.modal ? 'Keep editing' : 'Stay on page',
      leaveLabel: options.modal ? 'Close without saving' : 'Leave without saving',
      showSave: !!scope?.save
    });
    if (choice === 'leave') {
      preserveHistoryForAction = true;
      discard(root);
      if (!options.modal) allowNextUnload = true;
      try {
        action();
        return true;
      } finally {
        preserveHistoryForAction = false;
        if (options.modal) syncUi();
      }
    }
    if (choice === 'save') {
      preserveHistoryForAction = true;
      const saved = await runSave(scope);
      if (!saved) {
        preserveHistoryForAction = false;
        return false;
      }
      if (!options.modal) allowNextUnload = true;
      try {
        action();
        return true;
      } finally {
        preserveHistoryForAction = false;
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
    const scope = {
      ...options,
      roots,
      ignore: options.ignore || ''
    };
    scopes.add(scope);
    roots.forEach(root => root.querySelectorAll(editableSelector).forEach(snapshot));
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
          form.requestSubmit(submit);
          return new Promise(resolve => {
            const timeout = setTimeout(() => resolve(false), 15000);
            form.addEventListener('tracs:save-success', () => {
              clearTimeout(timeout);
              resolve(true);
            }, { once: true });
          });
        }
      });
    });
  }

  function autoRegisterModals() {
    document.querySelectorAll('.modal-overlay, .dpc-modal, .infra-modal, .cf-modal').forEach(modal => {
      if (!modal.querySelector(editableSelector) || modal.matches('[data-unsaved-ignore]')) return;
      const saveButton = modal.querySelector(
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
    const editablePages = new Set([
      'cases', 'case', 'shift-reports', 'shift_report', 'mom', 'checklist', 'reminders',
      'finance', 'domains', 'activity', 'user-management', 'infrastructure-pulse',
      'cancellation-feedback', 'feedback', 'settings', 'profile', 'domain_price_crosscheck',
      'shifting-assignment', 'dashboard', 'intern-management', 'monitoring'
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
  document.addEventListener('input', event => syncControl(event.target), true);
  document.addEventListener('change', event => syncControl(event.target), true);
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
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
  }, true);
  document.addEventListener('tracs:save-success', event => markSaved(event.detail?.root || event.target), true);

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download') || link.dataset.unsavedIgnore !== undefined) return;
    if (!isDirty()) return;
    const destination = new URL(link.href, window.location.href);
    if (destination.href === window.location.href || destination.hash && destination.pathname === location.pathname && destination.search === location.search) return;
    event.preventDefault();
    protect(() => {
      allowNextUnload = true;
      window.location.assign(destination.href);
    });
  }, true);

  window.addEventListener('beforeunload', event => {
    if (allowNextUnload || !isDirty()) return;
    event.preventDefault();
    event.returnValue = '';
  });

  window.addEventListener('popstate', () => {
    if (suppressNextPop) {
      suppressNextPop = false;
      return;
    }
    if (!historyGuardArmed || !isDirty()) return;
    suppressNextPop = true;
    history.forward();
    window.setTimeout(() => {
      protect(() => {
        allowNextUnload = true;
        historyGuardArmed = false;
        history.go(-2);
      });
    }, 0);
  });

  window.addEventListener('DOMContentLoaded', () => {
    autoRegisterForms();
    autoRegisterModals();
    autoRegisterEditablePage();
  });

  global.TRACSUnsavedChanges = {
    register,
    isDirty,
    markSaved,
    discard,
    protect,
    requestModalClose(modal, close) {
      return protect(close, { root: modal, modal: true });
    }
  };
})(window);
