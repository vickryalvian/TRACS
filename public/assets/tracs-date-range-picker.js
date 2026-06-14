(() => {
  'use strict';

  const PRESETS = [
    ['today', 'Today'],
    ['yesterday', 'Yesterday'],
    ['past_week', 'Past week'],
    ['month_to_date', 'Month to date'],
    ['past_4_weeks', 'Past 4 weeks'],
    ['past_12_weeks', 'Past 12 weeks'],
    ['year_to_date', 'Year to date'],
    ['past_6_months', 'Past 6 months'],
    ['past_12_months', 'Past 12 months'],
    ['custom', 'Custom'],
  ];

  const pad = value => String(value).padStart(2, '0');
  const cloneDate = date => new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const todayLocal = () => cloneDate(new Date());
  const toInternalDate = date =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  const parseInternalDate = value => {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (
      date.getFullYear() !== Number(match[1])
      || date.getMonth() !== Number(match[2]) - 1
      || date.getDate() !== Number(match[3])
    ) return null;
    return date;
  };
  const addDays = (date, amount) => {
    const next = cloneDate(date);
    next.setDate(next.getDate() + amount);
    return next;
  };
  const addMonths = (date, amount) => {
    const day = date.getDate();
    const next = new Date(date.getFullYear(), date.getMonth() + amount, 1);
    const lastDay = new Date(next.getFullYear(), next.getMonth() + 1, 0).getDate();
    next.setDate(Math.min(day, lastDay));
    return next;
  };
  const addYears = (date, amount) => addMonths(date, amount * 12);
  const inclusiveDays = (start, end) =>
    Math.max(1, Math.round((cloneDate(end) - cloneDate(start)) / 86400000) + 1);

  function formatDateDisplay(value) {
    const date = value instanceof Date ? value : parseInternalDate(value);
    return date ? `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}` : '';
  }

  function parseDisplayDate(value) {
    const match = String(value || '').trim().match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (!match) return '';
    const internal = `${match[3]}-${match[2]}-${match[1]}`;
    return parseInternalDate(internal) ? internal : '';
  }

  function formatDateRangeDisplay(startDate, endDate) {
    const start = formatDateDisplay(startDate);
    const end = formatDateDisplay(endDate);
    return start && end ? `${start} - ${end}` : '';
  }

  function presetRange(preset, anchor = todayLocal()) {
    const end = cloneDate(anchor);
    switch (preset) {
      case 'today':
        return { start: end, end };
      case 'yesterday': {
        const yesterday = addDays(end, -1);
        return { start: yesterday, end: yesterday };
      }
      case 'past_week':
        return { start: addDays(end, -6), end };
      case 'month_to_date':
        return { start: new Date(end.getFullYear(), end.getMonth(), 1), end };
      case 'past_4_weeks':
        return { start: addDays(end, -27), end };
      case 'past_12_weeks':
        return { start: addDays(end, -83), end };
      case 'year_to_date':
        return { start: new Date(end.getFullYear(), 0, 1), end };
      case 'past_6_months':
        return { start: addDays(addMonths(end, -6), 1), end };
      case 'past_12_months':
        return { start: addDays(addMonths(end, -12), 1), end };
      default:
        return null;
    }
  }

  function inferPreset(startValue, endValue) {
    if (!startValue || !endValue) return '';
    for (const [key] of PRESETS) {
      if (key === 'custom') continue;
      const range = presetRange(key);
      if (toInternalDate(range.start) === startValue && toInternalDate(range.end) === endValue) return key;
    }
    return 'custom';
  }

  class TRACSDateRangePicker {
    constructor(element, options = {}) {
      if (!(element instanceof Element)) throw new TypeError('TRACSDateRangePicker requires a root element.');
      if (element._tracsDateRangePicker) return element._tracsDateRangePicker;

      this.root = element;
      this.options = options;
      this.startInput = options.startInput instanceof Element
        ? options.startInput
        : element.querySelector('[data-tracs-range-start]');
      this.endInput = options.endInput instanceof Element
        ? options.endInput
        : element.querySelector('[data-tracs-range-end]');
      this.startDate = options.initialStartDate ?? element.dataset.initialStartDate ?? this.startInput?.value ?? '';
      this.endDate = options.initialEndDate ?? element.dataset.initialEndDate ?? this.endInput?.value ?? '';
      this.defaultStartDate = this.startDate;
      this.defaultEndDate = this.endDate;
      this.selectedPreset = options.selectedPreset
        ?? element.dataset.selectedPreset
        ?? inferPreset(this.startDate, this.endDate);
      this.label = options.label ?? element.dataset.label ?? 'Date range';
      this.placeholder = options.placeholder ?? element.dataset.placeholder ?? 'Select date range';
      this.onChange = typeof options.onChange === 'function' ? options.onChange : () => {};
      this.onApply = typeof options.onApply === 'function' ? options.onApply : () => {};
      this.onCancel = typeof options.onCancel === 'function' ? options.onCancel : () => {};
      this.popup = null;
      this.calendar = null;
      this.draftStart = '';
      this.draftEnd = '';
      this.activeEndpoint = 'to';
      this.lastFocused = null;
      this.isOpen = false;

      this.normalizeInitialRange();
      this.renderTrigger();
      this.bind();
      element._tracsDateRangePicker = this;
    }

    normalizeInitialRange() {
      const start = parseInternalDate(this.startDate);
      const end = parseInternalDate(this.endDate);
      if (!start || !end) {
        this.startDate = '';
        this.endDate = '';
        this.selectedPreset = '';
        return;
      }
      if (end < start) {
        this.startDate = toInternalDate(end);
        this.endDate = toInternalDate(start);
      }
      this.syncInputs(false);
      if (!PRESETS.some(([key]) => key === this.selectedPreset)) {
        this.selectedPreset = inferPreset(this.startDate, this.endDate);
      }
    }

    renderTrigger() {
      const display = formatDateRangeDisplay(this.startDate, this.endDate);
      this.root.classList.toggle('is-empty', !display);
      let trigger = this.trigger;
      if (!trigger?.isConnected) {
        trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'tracs-date-range-trigger';
        trigger.addEventListener('click', () => this.toggle());
        this.root.appendChild(trigger);
      }
      trigger.setAttribute('aria-haspopup', 'dialog');
      trigger.setAttribute('aria-expanded', this.isOpen ? 'true' : 'false');
      trigger.setAttribute('aria-label', `${this.label}: ${display || this.placeholder}`);
      trigger.innerHTML = `
        <i data-lucide="calendar-range" aria-hidden="true"></i>
        <span class="tracs-date-range-label">${this.escape(display || this.placeholder)}</span>
        <i data-lucide="chevron-down" class="tracs-date-range-chevron" aria-hidden="true"></i>
      `;
      this.trigger = trigger;
      window.lucide?.createIcons?.();
    }

    bind() {
      this.boundDocumentClick = event => {
        if (!this.isOpen || this.root.contains(event.target) || this.popup?.contains(event.target)) return;
        this.close({ cancel: true });
      };
      this.boundViewport = () => {
        if (!this.isOpen) return;
        this.syncCalendarMonths();
        this.positionPopup();
      };
      document.addEventListener('pointerdown', this.boundDocumentClick);
      window.addEventListener('resize', this.boundViewport);
      window.addEventListener('scroll', this.boundViewport, true);
    }

    escape(value) {
      return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
      })[char]);
    }

    open() {
      document.querySelectorAll('[data-tracs-date-range]').forEach(node => {
        if (node !== this.root) node._tracsDateRangePicker?.close({ cancel: true, restoreFocus: false });
      });
      this.lastFocused = document.activeElement;
      this.ensurePopup();
      this.renderPresets();
      this.popup.hidden = false;
      this.isOpen = true;
      this.trigger.setAttribute('aria-expanded', 'true');
      this.positionPopup();
      requestAnimationFrame(() => {
        const active = this.popup.querySelector('.tracs-date-range-preset.is-active');
        (active || this.popup.querySelector('button'))?.focus({ preventScroll: true });
      });
    }

    close({ cancel = false, restoreFocus = true } = {}) {
      if (!this.popup || !this.isOpen) return;
      if (cancel) this.onCancel();
      this.destroyCalendar();
      this.popup.hidden = true;
      this.popup.classList.remove('is-custom');
      this.isOpen = false;
      this.trigger.setAttribute('aria-expanded', 'false');
      if (restoreFocus) this.trigger.focus({ preventScroll: true });
    }

    toggle() {
      if (this.isOpen) this.close({ cancel: true });
      else this.open();
    }

    ensurePopup() {
      if (this.popup) return;
      const popup = document.createElement('div');
      popup.className = 'tracs-date-range-popup';
      popup.hidden = true;
      popup.setAttribute('role', 'dialog');
      popup.setAttribute('aria-modal', 'false');
      popup.setAttribute('aria-label', this.label);
      popup.addEventListener('click', event => this.handlePopupClick(event));
      popup.addEventListener('keydown', event => this.handlePopupKeydown(event));
      document.body.appendChild(popup);
      this.popup = popup;
    }

    renderPresets() {
      this.destroyCalendar();
      this.popup.classList.remove('is-custom');
      this.popup.innerHTML = `
        <div class="tracs-date-range-popup-head">
          <span class="tracs-date-range-popup-title">${this.escape(this.label)}</span>
          <button type="button" class="tracs-date-range-popup-close" data-range-close aria-label="Close date range picker">
            <i data-lucide="x" aria-hidden="true"></i>
          </button>
        </div>
        <div class="tracs-date-range-presets">
          ${PRESETS.map(([key, label]) => `
            <button type="button" class="tracs-date-range-preset ${this.selectedPreset === key ? 'is-active' : ''}" data-range-preset="${key}">
              <span>${label}</span>
              ${this.selectedPreset === key ? '<i data-lucide="check" aria-hidden="true"></i>' : '<span></span>'}
            </button>
          `).join('')}
        </div>
      `;
      window.lucide?.createIcons?.();
      requestAnimationFrame(() => this.positionPopup());
    }

    renderCustom() {
      this.popup.classList.add('is-custom');
      this.draftStart = this.startDate || toInternalDate(todayLocal());
      this.draftEnd = this.endDate || this.draftStart;
      this.activeEndpoint = this.draftStart && this.draftEnd ? 'to' : 'from';
      this.popup.innerHTML = `
        <div class="tracs-date-range-custom">
          <div class="tracs-date-range-values" aria-label="Selected custom date range" tabindex="-1">
            <div class="tracs-date-range-value" data-range-endpoint="from">
              <span>From</span>
              <strong data-range-from>${formatDateDisplay(this.draftStart)}</strong>
            </div>
            <div class="tracs-date-range-value" data-range-endpoint="to">
              <span>To</span>
              <strong data-range-to>${formatDateDisplay(this.draftEnd)}</strong>
            </div>
          </div>
          <div class="tracs-date-range-calendar">
            <input class="tracs-date-range-calendar-input" type="text" aria-label="Choose custom date range">
          </div>
          <p class="tracs-date-range-warning" data-range-warning hidden></p>
          <div class="tracs-date-range-actions">
            <button type="button" class="tracs-date-range-action" data-range-cancel>Cancel</button>
            <button type="button" class="tracs-date-range-action is-primary" data-range-apply>Apply</button>
          </div>
        </div>
      `;
      window.lucide?.createIcons?.();
      this.initCalendar();
      this.updateCustomState();
      requestAnimationFrame(() => this.positionPopup());
    }

    initCalendar() {
      const input = this.popup.querySelector('.tracs-date-range-calendar-input');
      if (!input || typeof window.flatpickr !== 'function') return;
      const container = input.closest('.tracs-date-range-calendar');
      this.calendar = window.flatpickr(input, {
        mode: 'range',
        inline: true,
        appendTo: container,
        disableMobile: true,
        dateFormat: 'Y-m-d',
        defaultDate: [this.draftStart, this.draftEnd],
        showMonths: this.calendarMonthCount(),
        monthSelectorType: 'static',
        locale: { firstDayOfWeek: 1 },
        onChange: selectedDates => {
          if (!selectedDates.length) {
            this.draftStart = '';
            this.draftEnd = '';
            this.activeEndpoint = 'from';
          } else if (selectedDates.length === 1) {
            this.draftStart = toInternalDate(selectedDates[0]);
            this.draftEnd = '';
            this.activeEndpoint = 'to';
          } else {
            const ordered = [...selectedDates].sort((left, right) => left - right);
            this.draftStart = toInternalDate(ordered[0]);
            this.draftEnd = toInternalDate(ordered[1]);
            this.activeEndpoint = 'to';
          }
          this.updateCustomState();
        },
        onReady: (_dates, _value, instance) => {
          instance.calendarContainer.classList.add('tracs-date-range-inline-calendar');
        },
      });
    }

    destroyCalendar() {
      if (!this.calendar) return;
      this.calendar.destroy();
      this.calendar = null;
    }

    calendarMonthCount() {
      return window.matchMedia('(min-width: 720px)').matches ? 2 : 1;
    }

    syncCalendarMonths() {
      if (!this.calendar) return;
      const months = this.calendarMonthCount();
      if (this.calendar.config.showMonths !== months) this.calendar.set('showMonths', months);
    }

    updateCustomState() {
      const from = this.popup.querySelector('[data-range-from]');
      const to = this.popup.querySelector('[data-range-to]');
      const warning = this.popup.querySelector('[data-range-warning]');
      const apply = this.popup.querySelector('[data-range-apply]');
      this.popup.querySelectorAll('[data-range-endpoint]').forEach(endpoint => {
        endpoint.classList.toggle('is-active', endpoint.dataset.rangeEndpoint === this.activeEndpoint);
      });
      const start = parseInternalDate(this.draftStart);
      const end = parseInternalDate(this.draftEnd);
      const valid = Boolean(start && end && start <= end);
      if (from) from.textContent = formatDateDisplay(this.draftStart) || 'Select date';
      if (to) to.textContent = formatDateDisplay(this.draftEnd) || 'Select date';
      if (warning) {
        warning.hidden = valid || !this.draftStart || !this.draftEnd;
        warning.textContent = valid ? '' : 'The To date must be on or after the From date.';
      }
      if (apply) apply.disabled = !valid;
    }

    handlePopupClick(event) {
      if (event.target.closest('[data-range-close], [data-range-cancel]')) {
        this.close({ cancel: true });
        return;
      }
      const presetButton = event.target.closest('[data-range-preset]');
      if (presetButton) {
        const preset = presetButton.dataset.rangePreset;
        if (preset === 'custom') {
          this.renderCustom();
          requestAnimationFrame(() => this.popup.querySelector('.tracs-date-range-values')?.focus());
          return;
        }
        const range = presetRange(preset);
        this.setRange(toInternalDate(range.start), toInternalDate(range.end), preset, { apply: true });
        this.close();
        return;
      }
      if (event.target.closest('[data-range-apply]')) this.applyCustom();
    }

    handlePopupKeydown(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        this.close({ cancel: true });
        return;
      }
      if (event.key === 'Enter' && this.popup.classList.contains('is-custom')) {
        const target = event.target;
        if (target.closest('[data-range-cancel], [data-range-close]')) return;
        const apply = this.popup.querySelector('[data-range-apply]');
        if (!apply?.disabled) {
          event.preventDefault();
          this.applyCustom();
        }
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = [...this.popup.querySelectorAll(
        'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'
      )].filter(node => node.offsetParent !== null);
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
    }

    applyCustom() {
      const start = parseInternalDate(this.draftStart);
      const end = parseInternalDate(this.draftEnd);
      if (!start || !end || end < start) return;
      this.setRange(this.draftStart, this.draftEnd, 'custom', { apply: true });
      this.close();
    }

    setRange(startDate, endDate, preset = 'custom', { apply = false, silent = false } = {}) {
      let start = parseInternalDate(startDate);
      let end = parseInternalDate(endDate);
      if (!start || !end) return false;
      if (end < start) [start, end] = [end, start];
      this.startDate = toInternalDate(start);
      this.endDate = toInternalDate(end);
      this.selectedPreset = PRESETS.some(([key]) => key === preset) ? preset : 'custom';
      this.syncInputs(!silent);
      this.renderTrigger();
      const detail = this.getValue();
      if (!silent) {
        this.onChange(detail.startDate, detail.endDate, detail.preset);
        this.root.dispatchEvent(new CustomEvent('tracs:date-range-change', { bubbles: true, detail }));
      }
      if (apply && !silent) {
        this.onApply(detail.startDate, detail.endDate, detail.preset);
        this.root.dispatchEvent(new CustomEvent('tracs:date-range-apply', { bubbles: true, detail }));
        if (this.root.dataset.autoSubmit === 'true') {
          const form = this.root.closest('form');
          if (form) {
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
          }
        }
      }
      return true;
    }

    syncInputs(dispatch = true) {
      [[this.startInput, this.startDate], [this.endInput, this.endDate]].forEach(([input, value]) => {
        if (!input) return;
        input.value = value;
        if (dispatch) {
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    }

    reset({ apply = false } = {}) {
      return this.setRange(
        this.defaultStartDate,
        this.defaultEndDate,
        inferPreset(this.defaultStartDate, this.defaultEndDate) || 'custom',
        { apply }
      );
    }

    shift(direction = 1, { apply = false } = {}) {
      const start = parseInternalDate(this.startDate);
      const end = parseInternalDate(this.endDate);
      if (!start || !end) return false;
      const amount = direction < 0 ? -1 : 1;
      let nextStart;
      let nextEnd;
      switch (this.selectedPreset) {
        case 'month_to_date':
          nextStart = addMonths(start, amount);
          nextEnd = addMonths(end, amount);
          break;
        case 'year_to_date':
          nextStart = addYears(start, amount);
          nextEnd = addYears(end, amount);
          break;
        case 'past_6_months':
          nextStart = addMonths(start, amount * 6);
          nextEnd = addMonths(end, amount * 6);
          break;
        case 'past_12_months':
          nextStart = addMonths(start, amount * 12);
          nextEnd = addMonths(end, amount * 12);
          break;
        default: {
          const days = inclusiveDays(start, end);
          nextStart = addDays(start, amount * days);
          nextEnd = addDays(end, amount * days);
        }
      }
      return this.setRange(toInternalDate(nextStart), toInternalDate(nextEnd), this.selectedPreset || 'custom', { apply });
    }

    getValue() {
      return {
        startDate: this.startDate,
        endDate: this.endDate,
        preset: this.selectedPreset,
        display: formatDateRangeDisplay(this.startDate, this.endDate),
      };
    }

    positionPopup() {
      if (!this.popup || this.popup.hidden) return;
      const margin = 10;
      const gap = 6;
      const triggerRect = this.trigger.getBoundingClientRect();
      const popupRect = this.popup.getBoundingClientRect();
      const viewportWidth = document.documentElement.clientWidth;
      const viewportHeight = document.documentElement.clientHeight;
      let left = triggerRect.left;
      if (left + popupRect.width > viewportWidth - margin) left = viewportWidth - popupRect.width - margin;
      left = Math.max(margin, left);
      const spaceBelow = viewportHeight - triggerRect.bottom - margin;
      const placeAbove = popupRect.height > spaceBelow && triggerRect.top > spaceBelow;
      let top = placeAbove ? triggerRect.top - popupRect.height - gap : triggerRect.bottom + gap;
      top = Math.max(margin, Math.min(top, viewportHeight - popupRect.height - margin));
      this.popup.style.left = `${Math.round(left)}px`;
      this.popup.style.top = `${Math.round(top)}px`;
      this.popup.style.maxHeight = `${Math.max(180, viewportHeight - (margin * 2))}px`;
    }

    destroy() {
      this.close({ restoreFocus: false });
      document.removeEventListener('pointerdown', this.boundDocumentClick);
      window.removeEventListener('resize', this.boundViewport);
      window.removeEventListener('scroll', this.boundViewport, true);
      this.popup?.remove();
      delete this.root._tracsDateRangePicker;
    }
  }

  function initDateRangePickers(scope = document) {
    scope.querySelectorAll('[data-tracs-date-range]').forEach(element => {
      if (!element._tracsDateRangePicker) new TRACSDateRangePicker(element);
    });
  }

  window.TRACSDateRangePicker = TRACSDateRangePicker;
  window.TRACSDate = Object.freeze({
    formatDateDisplay,
    parseDisplayDate,
    formatDateRangeDisplay,
    parseInternalDate,
    toInternalDate,
    presetRange,
  });
  window.initTRACSDateRangePickers = initDateRangePickers;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initDateRangePickers(), { once: true });
  } else {
    initDateRangePickers();
  }
})();
