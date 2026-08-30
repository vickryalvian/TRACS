/**
 * TRACS — Cancellation Feedback Auto-Save Engine
 *
 * Provides Google-Docs-style auto-save for the inline quick-entry form
 * and the edit feedback modal. Changes are debounced (600 ms), saved
 * field-by-field via the /api/feedback-autosave.php endpoint, and never
 * block user input. A small status badge shows Saving / Saved / Failed.
 *
 * Public API (consumed by tracs.js and cancellation_feedback.php):
 *   FeedbackAutoSave.bindInlineForm()    – wire the inline quick-entry form
 *   FeedbackAutoSave.bindEditModal(id)   – wire the edit modal for record `id`
 *   FeedbackAutoSave.unbindModal()       – teardown modal listeners on close
 *   FeedbackAutoSave.getCurrentId()      – returns current record id or null
 *   FeedbackAutoSave.flushAll()          – force-save all pending fields now
 *   FeedbackAutoSave.resetInlineForm()   – clear state after a successful create
 */

'use strict';

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Constants                                                                   */
/* ─────────────────────────────────────────────────────────────────────────── */
const CF_AUTOSAVE_DEBOUNCE_MS   = 600;
const CF_AUTOSAVE_SAVED_FADE_MS = 2500;   // how long "Saved ✓" stays visible
const CF_AUTOSAVE_API_URL       = '/api/feedback-autosave.php';
const CF_CREATE_API_URL         = '/api/feedback-create.php';

/* ─────────────────────────────────────────────────────────────────────────── */
/*  FeedbackAutoSave namespace                                                  */
/* ─────────────────────────────────────────────────────────────────────────── */
const FeedbackAutoSave = (() => {

  /* ── State ─────────────────────────────────────────────────────────────── */
  let _currentId      = null;   // null = new record (not yet created)
  let _context        = null;   // 'inline' | 'modal'
  let _pendingFields  = {};     // { fieldKey: encodedValue } — queued saves
  let _inflightField  = null;   // field key currently being saved
  let _debounceTimer  = null;   // setTimeout handle
  let _retryFields    = {};     // fields that failed last time
  let _isSaving       = false;
  let _isCreating     = false;  // guard: one create at a time
  let _abortCtrl      = null;   // AbortController for in-flight requests

  // DOM refs for the status badge
  let _statusEl     = null;
  let _statusFadeId = null;

  // Listeners we attach so we can remove them cleanly
  const _listeners = [];

  /* ── Status badge ──────────────────────────────────────────────────────── */

  /**
   * Find or create the status badge element for a given host container.
   * The badge is a small span injected next to the form's label/title element.
   */
  function _ensureStatusEl(hostEl) {
    if (_statusEl && document.contains(_statusEl)) return _statusEl;

    // Look for an existing badge already in the DOM for this context
    const existing = hostEl?.querySelector?.('.cf-autosave-status');
    if (existing) { _statusEl = existing; return _statusEl; }

    _statusEl = document.createElement('span');
    _statusEl.className = 'cf-autosave-status cf-as-idle';
    _statusEl.setAttribute('aria-live', 'polite');
    _statusEl.setAttribute('aria-atomic', 'true');

    // Inject after the first label/title in the host container, else append
    const anchor =
      hostEl?.querySelector?.('.fb-inline-label, .modal-title, .fb-autosave-anchor') ||
      hostEl;
    if (anchor && anchor !== hostEl) {
      anchor.insertAdjacentElement('afterend', _statusEl);
    } else if (hostEl) {
      hostEl.prepend(_statusEl);
    }
    return _statusEl;
  }

  function _showStatus(state, hostEl) {
    const el = _statusEl || _ensureStatusEl(hostEl);
    if (!el) return;

    clearTimeout(_statusFadeId);
    el.className = 'cf-autosave-status';

    if (state === 'saving') {
      el.className += ' cf-as-saving';
      el.textContent = 'Saving…';
    } else if (state === 'saved') {
      el.className += ' cf-as-saved';
      el.textContent = '✓ Saved';
      _statusFadeId = setTimeout(() => {
        el.className = 'cf-autosave-status cf-as-idle';
        el.textContent = '';
      }, CF_AUTOSAVE_SAVED_FADE_MS);
    } else if (state === 'failed') {
      el.className += ' cf-as-failed';
      el.innerHTML = '✕ Failed &mdash; <button class="cf-as-retry-btn" type="button">Retry</button>';
      el.querySelector('.cf-as-retry-btn')?.addEventListener('click', () => _retryAll(), { once: true });
    } else {
      el.textContent = '';
    }
  }

  /* ── Helpers ───────────────────────────────────────────────────────────── */

  function _getHostEl() {
    if (_context === 'modal') return document.getElementById('feedbackModal');
    return document.querySelector('.fb-inline-form');
  }

  /** Encode a field value the same way the PHP endpoint expects it. */
  function _encodeValue(fieldKey, rawValue) {
    // Multi-choice fields send an array of strings
    if (fieldKey === 'cancelled_service' || fieldKey === 'cancellation_reason') {
      return Array.isArray(rawValue) ? rawValue : [];
    }
    return typeof rawValue === 'string' ? rawValue : String(rawValue ?? '');
  }

  /**
   * Read the current value of a field from the DOM.
   * fieldKey must map to a recognized element id or data attribute.
   */
  function _readFieldValue(fieldKey, contextPrefix) {
    const idMap = {
      inline: {
        cancelled_service  : 'inService',
        cancellation_reason: 'inReason',
        additional_details : 'inDetails',
        whmcs_reference    : 'inRef',
        email_address      : 'inEmail',
        payment_resolution : 'inResolution',
      },
      modal: {
        cancelled_service  : 'feedbackService',
        cancellation_reason: 'feedbackReason',
        additional_details : 'feedbackDetails',
        whmcs_reference    : 'feedbackReference',
        email_address      : 'feedbackEmail',
        payment_resolution : 'feedbackResolution',
      },
    };

    const prefix = contextPrefix || _context;
    const elementId = idMap[prefix]?.[fieldKey];
    if (!elementId) return null;

    const el = document.getElementById(elementId);
    if (!el) return null;

    // Multi-choice: gather checked values from the cf-choice-box
    if (el.dataset.multiChoice !== undefined || el.classList.contains('cf-choice-box')) {
      return Array.from(el.querySelectorAll('input[type="checkbox"]:checked'))
                  .map(cb => cb.value);
    }

    // Native multi-select (used inside the modal)
    if (el.tagName === 'SELECT' && el.multiple) {
      return Array.from(el.selectedOptions).map(o => o.value);
    }

    return el.value;
  }

  /* ── Debounced save scheduling ─────────────────────────────────────────── */

  /**
   * Schedule a save for fieldKey after the debounce delay.
   * Multiple rapid edits to the same field collapse into one request.
   */
  function _scheduleFieldSave(fieldKey, value) {
    _pendingFields[fieldKey] = _encodeValue(fieldKey, value);

    clearTimeout(_debounceTimer);
    _debounceTimer = setTimeout(() => _flushNextPending(), CF_AUTOSAVE_DEBOUNCE_MS);
  }

  /** Process one pending field (or trigger a create if no id yet). */
  async function _flushNextPending() {
    const keys = Object.keys(_pendingFields);
    if (keys.length === 0) return;

    // If no record exists yet, try to create one first
    if (_currentId === null && _context === 'inline') {
      await _tryCreateRecord();
      return; // _tryCreateRecord calls _flushNextPending again on success
    }

    if (_currentId === null) return; // modal context always has an id

    if (_isSaving) return; // another field is already in-flight

    const fieldKey = keys[0];
    const value    = _pendingFields[fieldKey];
    delete _pendingFields[fieldKey];

    await _saveField(fieldKey, value);

    // If more fields are queued, flush them (no extra debounce needed)
    if (Object.keys(_pendingFields).length > 0) {
      await _flushNextPending();
    }
  }

  /* ── New-record creation (inline form only) ────────────────────────────── */

  /**
   * Create a new feedback record using all current inline field values.
   * Requires at least one service and one reason to be selected.
   */
  async function _tryCreateRecord() {
    if (_isCreating) return;

    const services = _readFieldValue('cancelled_service', 'inline');
    const reasons  = _readFieldValue('cancellation_reason', 'inline');

    // Minimum requirement: both service and reason must be non-empty
    if (!services?.length || !reasons?.length) return;

    _isCreating = true;
    _showStatus('saving', _getHostEl());

    const fd = new FormData();
    services.forEach(v => fd.append('service[]', v));
    reasons.forEach(v  => fd.append('reason[]',  v));
    fd.append('reference',  _readFieldValue('whmcs_reference',    'inline') || '');
    fd.append('email',      _readFieldValue('email_address',      'inline') || '');
    fd.append('resolution', _readFieldValue('payment_resolution', 'inline') || '');
    fd.append('details',    _readFieldValue('additional_details', 'inline') || '');

    try {
      const resp    = await fetch(CF_CREATE_API_URL, { method: 'POST', body: fd });
      const payload = await resp.json();

      if (resp.ok && payload.success && payload.id) {
        _currentId = parseInt(payload.id, 10);
        _showStatus('saved', _getHostEl());
        window.TRACSFeedbackRealtime?.applyRecord(payload.data?.record || payload.record, { isNew: true });
        _markContextSaved();

        // Sync the id onto the inline form so the "Save" button knows
        const idHidden = document.getElementById('inFeedbackId');
        if (idHidden) idHidden.value = _currentId;

        // Update in-memory record cache so view/edit modals work immediately
        if (window.feedbackRecords) {
          window.feedbackRecords[_currentId] = {
            id                : _currentId,
            cancelled_service : services.join(', '),
            cancelled_services: services,
            cancellation_reason: reasons.join(', '),
            cancellation_reasons: reasons,
            additional_details: _readFieldValue('additional_details', 'inline') || '',
            whmcs_reference   : _readFieldValue('whmcs_reference', 'inline')    || '',
            email_address     : _readFieldValue('email_address', 'inline')      || '',
            payment_resolution: _readFieldValue('payment_resolution', 'inline') || '',
          };
        }

        // Any remaining pending fields can now be patched individually
        await _flushNextPending();
      } else {
        _showStatus('failed', _getHostEl());
        // Re-queue the raw field values so user can retry
        _pendingFields['cancelled_service']   = services;
        _pendingFields['cancellation_reason'] = reasons;
      }
    } catch (err) {
      _showStatus('failed', _getHostEl());
      _pendingFields['cancelled_service']   = services;
      _pendingFields['cancellation_reason'] = reasons;
    } finally {
      _isCreating = false;
    }
  }

  /* ── Single-field PATCH ────────────────────────────────────────────────── */

  async function _saveField(fieldKey, value) {
    if (_currentId === null) return;

    _isSaving      = true;
    _inflightField = fieldKey;
    _abortCtrl     = new AbortController();

    _showStatus('saving', _getHostEl());

    try {
      const resp = await fetch(CF_AUTOSAVE_API_URL, {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ id: _currentId, field: fieldKey, value }),
        signal : _abortCtrl.signal,
      });

      const payload = await resp.json();

      if (resp.ok && payload.success) {
        window.TRACSFeedbackRealtime?.applyRecord(payload.data?.record || payload.record);
        // Update in-memory record cache
        if (window.feedbackRecords?.[_currentId]) {
          window.feedbackRecords[_currentId].updated_at = payload.data?.updated_at || '';
          _applyFieldToRecord(_currentId, fieldKey, value);
        }
        // Clear from retry queue if it was there
        delete _retryFields[fieldKey];
        _showStatus('saved', _getHostEl());
        _markFieldSaved(fieldKey);
      } else {
        _retryFields[fieldKey] = value;
        _showStatus('failed', _getHostEl());
      }
    } catch (err) {
      if (err.name !== 'AbortError') {
        _retryFields[fieldKey] = value;
        _showStatus('failed', _getHostEl());
      }
    } finally {
      _isSaving      = false;
      _inflightField = null;
      _abortCtrl     = null;
    }
  }

  /** Keep window.feedbackRecords consistent after a patch. */
  function _applyFieldToRecord(id, fieldKey, value) {
    const rec = window.feedbackRecords?.[id];
    if (!rec) return;

    if (fieldKey === 'cancelled_service') {
      rec.cancelled_services = Array.isArray(value) ? value : [value];
      rec.cancelled_service  = rec.cancelled_services.join(', ');
    } else if (fieldKey === 'cancellation_reason') {
      rec.cancellation_reasons = Array.isArray(value) ? value : [value];
      rec.cancellation_reason  = rec.cancellation_reasons.join(', ');
    } else {
      rec[fieldKey] = typeof value === 'string' ? value : String(value);
    }
  }

  function _fieldElement(fieldKey) {
    const idMap = {
      inline: {
        cancelled_service  : 'inService',
        cancellation_reason: 'inReason',
        additional_details : 'inDetails',
        whmcs_reference    : 'inRef',
        email_address      : 'inEmail',
        payment_resolution : 'inResolution',
      },
      modal: {
        cancelled_service  : 'feedbackService',
        cancellation_reason: 'feedbackReason',
        additional_details : 'feedbackDetails',
        whmcs_reference    : 'feedbackReference',
        email_address      : 'feedbackEmail',
        payment_resolution : 'feedbackResolution',
      },
    };
    const id = idMap[_context]?.[fieldKey];
    return id ? document.getElementById(id) : null;
  }

  function _markFieldSaved(fieldKey) {
    const el = _fieldElement(fieldKey);
    window.TRACSUnsavedChanges?.markSaved(el || _getHostEl());
  }

  function _markContextSaved() {
    window.TRACSUnsavedChanges?.markSaved(_getHostEl());
  }

  /* ── Retry queue ───────────────────────────────────────────────────────── */

  async function _retryAll() {
    const entries = Object.entries(_retryFields);
    if (entries.length === 0) return;

    _retryFields = {};
    _showStatus('saving', _getHostEl());

    for (const [fieldKey, value] of entries) {
      await _saveField(fieldKey, value);
    }
  }

  /* ── Event listeners ───────────────────────────────────────────────────── */

  /**
   * Attach a change/input listener to a single element, tracking it for cleanup.
   * fieldKey is the DB column name; contextPrefix is 'inline' or 'modal'.
   */
  function _bindEl(el, fieldKey, eventType = 'input') {
    if (!el) return;

    const handler = () => {
      // Retry any failed fields on the next user interaction
      if (Object.keys(_retryFields).length > 0) {
        Object.assign(_pendingFields, _retryFields);
        _retryFields = {};
      }

      const value = _readFieldValue(fieldKey, _context);
      if (value !== null) _scheduleFieldSave(fieldKey, value);
    };

    el.addEventListener(eventType, handler);
    _listeners.push({ el, eventType, handler });
  }

  /** Attach listeners for all fields in the inline form. */
  function _bindInlineFields() {
    const idMap = {
      cancelled_service  : { id: 'inService',     event: 'change' },
      cancellation_reason: { id: 'inReason',       event: 'change' },
      additional_details : { id: 'inDetails',      event: 'input'  },
      whmcs_reference    : { id: 'inRef',           event: 'input'  },
      email_address      : { id: 'inEmail',         event: 'input'  },
      payment_resolution : { id: 'inResolution',    event: 'change' },
    };

    for (const [fieldKey, cfg] of Object.entries(idMap)) {
      const el = document.getElementById(cfg.id);
      if (!el) continue;

      // For cf-choice-box (custom checkbox groups), listen on the wrapper container
      if (el.dataset.multiChoice !== undefined || el.classList.contains('cf-choice-box')) {
        _bindEl(el, fieldKey, 'change');
        // Also bind individual checkboxes
        el.querySelectorAll('input[type="checkbox"]').forEach(cb => {
          _bindEl(cb, fieldKey, 'change');
        });
      } else {
        _bindEl(el, fieldKey, cfg.event);
      }
    }
  }

  /** Attach listeners for all fields in the edit modal. */
  function _bindModalFields() {
    const idMap = {
      cancelled_service  : { id: 'feedbackService',    event: 'change' },
      cancellation_reason: { id: 'feedbackReason',     event: 'change' },
      additional_details : { id: 'feedbackDetails',    event: 'input'  },
      whmcs_reference    : { id: 'feedbackReference',  event: 'input'  },
      email_address      : { id: 'feedbackEmail',      event: 'input'  },
      payment_resolution : { id: 'feedbackResolution', event: 'change' },
    };

    for (const [fieldKey, cfg] of Object.entries(idMap)) {
      const el = document.getElementById(cfg.id);
      if (!el) continue;

      if (el.tagName === 'SELECT' && el.multiple) {
        _bindEl(el, fieldKey, 'change');
      } else if (el.dataset.multiChoice !== undefined || el.classList.contains('cf-choice-box')) {
        _bindEl(el, fieldKey, 'change');
        el.querySelectorAll('input[type="checkbox"]').forEach(cb => {
          _bindEl(cb, fieldKey, 'change');
        });
      } else {
        _bindEl(el, fieldKey, cfg.event);
      }
    }
  }

  /** Remove all attached listeners (call when modal closes). */
  function _unbindAll() {
    for (const { el, eventType, handler } of _listeners) {
      el.removeEventListener(eventType, handler);
    }
    _listeners.length = 0;
  }

  /* ── Public API ────────────────────────────────────────────────────────── */

  return {

    /**
     * Wire the inline quick-entry form.
     * Call once on page load. Existing id can be pre-seeded if a draft exists.
     */
    bindInlineForm(existingId = null) {
      _context   = 'inline';
      _currentId = existingId ? parseInt(existingId, 10) : null;
      _pendingFields = {};
      _retryFields   = {};

      const hostEl = document.querySelector('.fb-inline-form');
      _ensureStatusEl(hostEl);
      _bindInlineFields();
    },

    /**
     * Wire the edit modal for an existing feedback record.
     * Call every time the modal opens with a different record.
     */
    bindEditModal(id) {
      _unbindAll();               // clean up previous modal session
      _context       = 'modal';
      _currentId     = parseInt(id, 10);
      _pendingFields = {};
      _retryFields   = {};

      const hostEl = document.getElementById('feedbackModal');
      _ensureStatusEl(hostEl);
      _bindModalFields();
    },

    /** Teardown — call when modal closes. */
    unbindModal() {
      if (_context !== 'modal') return;
      clearTimeout(_debounceTimer);
      _abortCtrl?.abort();
      _unbindAll();
      _context   = null;
      _currentId = null;
      const inlineForm = document.querySelector('.fb-inline-form');
      if (inlineForm) {
        const existingId = document.getElementById('inFeedbackId')?.value || null;
        this.bindInlineForm(existingId);
      }
    },

    /** Returns the current record id (null if new/unsaved). */
    getCurrentId() {
      return _currentId;
    },

    /**
     * Force-flush all pending changes immediately (skip the debounce).
     * Returns a Promise that resolves when all queued saves complete.
     */
    async flushAll() {
      clearTimeout(_debounceTimer);
      await _flushNextPending();
      // Also flush retry queue
      await _retryAll();
    },

    /**
     * Reset inline form state after the record has been submitted/cleared.
     */
    resetInlineForm() {
      clearTimeout(_debounceTimer);
      _abortCtrl?.abort();
      _currentId     = null;
      _pendingFields = {};
      _retryFields   = {};
      if (_statusEl) {
        _statusEl.className  = 'cf-autosave-status cf-as-idle';
        _statusEl.textContent = '';
      }
    },

    /** Expose for debugging. */
    _debug() {
      return { _currentId, _pendingFields, _retryFields, _isSaving, _context };
    },
  };

})();

// Make globally accessible
window.FeedbackAutoSave = FeedbackAutoSave;
