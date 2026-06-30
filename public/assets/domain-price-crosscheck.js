/**
 * TRACS — Domain Price Crosscheck Module
 * Frontend interaction and modal dialog controllers.
 */

// 1. Create New Monthly Record Modal
const DPC_MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

function dpcPadMonth(month) {
    return String(month).padStart(2, '0');
}

function dpcSelectedMonthCode() {
    const month = document.getElementById('period_month_select')?.value || '';
    const year = document.getElementById('period_year_select')?.value || '';
    if (!month || !year) return '';
    return `${year}-${dpcPadMonth(month)}`;
}

function dpcMonthLabel(monthCode) {
    const [year, month] = String(monthCode).split('-');
    const monthIndex = parseInt(month, 10) - 1;
    if (!year || monthIndex < 0 || monthIndex > 11) return monthCode;
    return `${DPC_MONTH_NAMES[monthIndex]} ${year}`;
}

function dpcMonthRecordMap() {
    const records = Array.isArray(window.DPC_MONTH_RECORDS) ? window.DPC_MONTH_RECORDS : [];
    return records.reduce((map, record) => {
        if (record.month) map[record.month] = record;
        return map;
    }, {});
}

function dpcCleanRate(value) {
    return String(value || '').replace(/[^\d.]/g, '');
}

function dpcFormatRate(value) {
    const clean = dpcCleanRate(value);
    if (!clean) return '';
    const numeric = Number(clean);
    if (!Number.isFinite(numeric) || numeric <= 0) return '';
    return `Rp${numeric.toLocaleString('en-US', { maximumFractionDigits: 2 })}`;
}

function syncNewMonthPreview() {
    const monthCode = dpcSelectedMonthCode();
    const monthInput = document.getElementById('month_code_input');
    const periodPreview = document.getElementById('selected_period_preview');
    const codePreview = document.getElementById('month_code_preview');
    const duplicateWarning = document.getElementById('new_month_duplicate_warning');
    const submitButton = document.getElementById('btnCreateMonthDraft');
    const records = dpcMonthRecordMap();
    const existing = records[monthCode];

    if (monthInput) monthInput.value = monthCode;
    if (periodPreview) periodPreview.textContent = dpcMonthLabel(monthCode);
    if (codePreview) codePreview.textContent = monthCode;
    if (duplicateWarning) {
        duplicateWarning.hidden = !existing;
        duplicateWarning.textContent = existing
            ? `A monthly record for ${dpcMonthLabel(monthCode)} already exists. Please select the existing record instead.`
            : '';
    }
    if (submitButton) submitButton.disabled = !!existing;
}

function resetNewMonthDefaults() {
    const defaults = window.DPC_CREATE_DEFAULTS || {};
    const monthSelect = document.getElementById('period_month_select');
    const yearSelect = document.getElementById('period_year_select');
    const rateInput = document.getElementById('exchange_rate_input');

    if (monthSelect && defaults.month) monthSelect.value = String(defaults.month);
    if (yearSelect && defaults.year) yearSelect.value = String(defaults.year);
    if (rateInput) {
        const defaultRate = defaults.exchange_rate ? String(defaults.exchange_rate) : '';
        rateInput.value = defaultRate;
        rateInput.dataset.rawValue = defaultRate;
    }
    syncNewMonthPreview();
}

function openNewMonthModal() {
    const modal = document.getElementById('newMonthModal');
    if (modal) {
        resetNewMonthDefaults();
        tracsOpenModalElement(modal, { display: 'flex' });
        const monthSelect = document.getElementById('period_month_select');
        if (monthSelect) monthSelect.focus();
    }
}

function closeNewMonthModal() {
    const modal = document.getElementById('newMonthModal');
    if (modal) tracsCloseModalElement(modal);
}

// 2. Update Exchange Rate Modal
function openUpdateRateModal(monthId, currentRate) {
    const modal = document.getElementById('updateRateModal');
    if (modal) {
        const idInput = document.getElementById('update_rate_month_id');
        const rateInput = document.getElementById('update_rate_input');
        
        if (idInput) idInput.value = monthId;
        if (rateInput) {
            rateInput.value = currentRate;
            tracsOpenModalElement(modal, { display: 'flex' });
            rateInput.focus();
            rateInput.select();
        }
    }
}

function closeUpdateRateModal() {
    const modal = document.getElementById('updateRateModal');
    if (modal) tracsCloseModalElement(modal);
    window.DPC_CURRENT_EXCHANGE_RATE = Number(window.DPC_SAVED_EXCHANGE_RATE || 0);
    dpcSyncLiveMatrixPreview();
}

// 3. Approve and Lock Modal
function openApproveModal(monthId) {
    const modal = document.getElementById('approveModal');
    if (modal) {
        const idInput = document.getElementById('approve_month_id');
        if (idInput) idInput.value = monthId;
        tracsOpenModalElement(modal, { display: 'flex' });
        const noteInput = document.getElementById('approval_note_input');
        if (noteInput) noteInput.focus();
    }
}

function closeApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) tracsCloseModalElement(modal);
}

// 4. Unlock Modal
function openUnlockModal(monthId) {
    const modal = document.getElementById('unlockModal');
    if (modal) {
        const idInput = document.getElementById('unlock_month_id');
        if (idInput) idInput.value = monthId;
        tracsOpenModalElement(modal, { display: 'flex' });
        const reasonInput = document.getElementById('unlock_reason_input');
        if (reasonInput) reasonInput.focus();
    }
}

function closeUnlockModal() {
    const modal = document.getElementById('unlockModal');
    if (modal) tracsCloseModalElement(modal);
}

// 4b. Assign Task Modal
function openAssignTaskModal(monthId) {
    const modal = document.getElementById('assignTaskModal');
    if (modal) {
        const idInput = document.getElementById('assign_task_month_id');
        if (idInput) idInput.value = monthId;
        tracsOpenModalElement(modal, { display: 'flex' });
    }
}

function closeAssignTaskModal() {
    const modal = document.getElementById('assignTaskModal');
    if (modal) tracsCloseModalElement(modal);
}

// 4c. Export CSV Modal
function openExportModal() {
    const modal = document.getElementById('exportCsvModal');
    if (modal) {
        tracsOpenModalElement(modal, { display: 'flex' });
        const firstInput = modal.querySelector('input[name="export_scope"]');
        if (firstInput) firstInput.focus();
    }
}

function closeExportModal() {
    const modal = document.getElementById('exportCsvModal');
    if (modal) tracsCloseModalElement(modal);
}

// 4c. Domain Extension Modal
function openExtensionModal(focusSection) {
    const modal = document.getElementById('extensionModal');
    if (modal) {
        tracsOpenModalElement(modal, { display: 'flex' });
        window.TRACSDropdowns?.init?.(modal);
        window.TRACSDropdowns?.syncAll?.();
        if (focusSection === 'registrars') {
            const registrarCard = modal.querySelector('.dpc-source-config-card');
            registrarCard?.scrollIntoView({ block: 'start' });
        }
        const sourceInput = modal.querySelector('input[name="source_name"]');
        const extensionInput = modal.querySelector('input[name="tld_name"]');
        (focusSection === 'registrars' ? sourceInput : (sourceInput || extensionInput))?.focus();
    }
}
function openRegistrarManagementModal() {
    openExtensionModal('registrars');
}

function closeExtensionModal() {
    const modal = document.getElementById('extensionModal');
    if (modal) tracsCloseModalElement(modal);
}

function dpcNormalizeExtension(value) {
    let normalized = String(value || '').trim().toLowerCase();
    if (normalized && normalized[0] !== '.') normalized = '.' + normalized;
    return normalized;
}

function dpcNormalizeSourceName(value) {
    return String(value || '').trim().replace(/\s+/g, ' ');
}

function dpcToast(type, title, message, options = {}) {
    const safeMessage = type === 'error' && typeof getFriendlyErrorMessage === 'function'
        ? getFriendlyErrorMessage(message, 'The request could not be completed. Please check the data and try again.')
        : message;
    if (typeof showToast === 'function') {
        showToast(type, title, safeMessage, options);
    } else if (typeof toast === 'function') {
        toast([title, safeMessage].filter(Boolean).join(': '), type);
    } else {
        console[type === 'error' ? 'error' : 'log']([title, safeMessage].filter(Boolean).join(': '));
    }
}

function dpcNotify(message, type = 'error') {
    dpcToast(type, type === 'error' ? 'Action blocked' : 'Notice', message);
}

function dpcReloadAfterNotice(delay = 620) {
    if (typeof reloadAfterToast === 'function') {
        reloadAfterToast(delay);
        return;
    }
    window.setTimeout(() => window.location.reload(), delay);
}

function dpcMatrixSavedNotice(message) {
    const raw = String(message || '');
    const match = raw.match(/updated\s+(\d+)\s+cells?\s+and\s+cleared\s+(\d+)\s+cells?/i);
    if (!match) {
        return { title: 'Matrix saved', message: raw || 'Matrix saved successfully.' };
    }
    const updated = Number(match[1]);
    const cleared = Number(match[2]);
    const updatedText = `Updated ${updated} ${updated === 1 ? 'cell' : 'cells'}.`;
    const clearedText = cleared > 0
        ? `Cleared ${cleared} ${cleared === 1 ? 'cell' : 'cells'}.`
        : 'No cells were cleared.';
    return { title: 'Matrix saved', message: `${updatedText} ${clearedText}` };
}

function dpcParseMoneyInput(value) {
    const raw = String(value ?? '').trim();
    if (!raw || raw === '.' || raw === ',' || raw === '-' || raw === '+') return null;
    const compact = raw.replace(/\$/g, '').replace(/\s+/g, '');
    let normalized = compact;
    if (compact.includes(',') && compact.includes('.')) {
        const decimalIndex = Math.max(compact.lastIndexOf(','), compact.lastIndexOf('.'));
        normalized = `${compact.slice(0, decimalIndex).replace(/[.,]/g, '')}.${compact.slice(decimalIndex + 1).replace(/[.,]/g, '')}`;
    } else if (compact.includes(',')) {
        normalized = compact.replace(',', '.');
    }
    if (!/^\d*(?:\.\d*)?$/.test(normalized) || normalized === '.') return null;
    const numeric = Number(normalized);
    return Number.isFinite(numeric) && numeric >= 0 ? numeric : null;
}

function dpcFormatUsdInputValue(value) {
    const numeric = dpcParseMoneyInput(value);
    if (numeric === null) return null;
    return numeric.toFixed(2);
}

function dpcFormatIdrPreview(value) {
    if (!Number.isFinite(value)) return '<span class="dpc-empty-value">—</span>';
    return `Rp ${value.toLocaleString('id-ID', { maximumFractionDigits: 4 })}`;
}

function dpcFormatDerivedInputValue(value) {
    if (!Number.isFinite(value)) return '';
    return value.toFixed(4).replace(/(?:\.0+|(\.\d+?)0+)$/, '$1');
}

function dpcParseIdrInput(value) {
    const raw = String(value ?? '')
        .trim()
        .replace(/rp/gi, '')
        .replace(/\s+/g, '')
        .replace(/[^\d.,]/g, '');
    if (!raw) return null;

    let normalized = raw;
    if (raw.includes(',')) {
        const commaIndex = raw.lastIndexOf(',');
        const integerPart = raw.slice(0, commaIndex).replace(/[.,]/g, '');
        const decimalPart = raw.slice(commaIndex + 1).replace(/[.,]/g, '');
        normalized = decimalPart ? `${integerPart}.${decimalPart}` : integerPart;
    } else if (raw.includes('.')) {
        const parts = raw.split('.');
        const groupedThousands = parts.length > 2
            ? parts.slice(1).every(part => part.length === 3)
            : parts[0].length <= 3 && parts[1]?.length === 3;
        normalized = groupedThousands
            ? parts.join('')
            : `${parts.slice(0, -1).join('')}.${parts.at(-1)}`;
    }

    if (!/^\d+(?:\.\d+)?$/.test(normalized)) return null;
    const numeric = Number(normalized);
    return Number.isFinite(numeric) && numeric >= 0 ? numeric : null;
}

function dpcFormatIdrInputValue(value) {
    const numeric = typeof value === 'number' ? value : dpcParseIdrInput(value);
    if (!Number.isFinite(numeric)) return '';
    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
        useGrouping: true,
    });
}

function dpcCleanMatrixInputValue(input) {
    if (input.dataset.currency !== 'IDR') return input.value;
    const numeric = dpcParseIdrInput(input.value);
    return numeric === null ? '' : dpcFormatDerivedInputValue(numeric);
}

function dpcCssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value));
    return String(value).replace(/["\\]/g, '\\$&');
}

function dpcApplyFilter(scope, filter, activeButton = null) {
    const scopes = scope === 'both' ? ['website', 'cctld'] : [scope];
    scopes.forEach(currentScope => {
        document.querySelectorAll(`[data-dpc-filter="${dpcCssEscape(currentScope)}"]`).forEach(button => {
            button.classList.toggle('active', activeButton ? button === activeButton : button.dataset.dpcFilterValue === filter);
        });
        document.querySelectorAll(`[data-dpc-filter-scope="${dpcCssEscape(currentScope)}"]`).forEach(row => {
            const status = row.getAttribute('data-dpc-filter-status') || '';
            row.hidden = filter !== 'all' && status !== filter;
        });
    });
}

function dpcParseSortableNumber(value, type = 'number') {
    let raw = String(value ?? '').trim();
    if (!raw || raw === '—' || raw === '-') return null;
    if (type === 'percent') {
        const percentMatch = raw.match(/[+-]?\d+(?:[.,]\d+)?(?=\s*%)/);
        if (percentMatch) {
            const percentValue = Number(percentMatch[0].replace(',', '.'));
            return Number.isFinite(percentValue) ? percentValue : null;
        }
    }
    raw = raw
        .replace(/rp/gi, '')
        .replace(/\$/g, '')
        .replace(/%/g, '')
        .replace(/from target/gi, '')
        .replace(/on target/gi, '')
        .replace(/\s+/g, '')
        .replace(/[^\d.,+-]/g, '');
    if (!raw || raw === '-' || raw === '+') return null;

    const sign = raw.startsWith('-') ? -1 : 1;
    raw = raw.replace(/^[+-]/, '');
    let normalized = raw;
    const lastDot = raw.lastIndexOf('.');
    const lastComma = raw.lastIndexOf(',');

    if (lastDot >= 0 && lastComma >= 0) {
        const decimalIndex = Math.max(lastDot, lastComma);
        normalized = `${raw.slice(0, decimalIndex).replace(/[.,]/g, '')}.${raw.slice(decimalIndex + 1).replace(/[.,]/g, '')}`;
    } else if (lastComma >= 0) {
        const parts = raw.split(',');
        const isGrouping = type !== 'percent'
            && parts.length > 1
            && parts.slice(1).every(part => part.length === 3)
            && (parts.length > 2 || parts[0].length <= 3);
        normalized = isGrouping ? parts.join('') : `${parts.slice(0, -1).join('')}.${parts.at(-1)}`;
    } else if (lastDot >= 0) {
        const parts = raw.split('.');
        const isGrouping = type !== 'percent'
            && parts.length > 1
            && parts.slice(1).every(part => part.length === 3)
            && (parts.length > 2 || parts[0].length <= 3);
        normalized = isGrouping ? parts.join('') : `${parts.slice(0, -1).join('')}.${parts.at(-1)}`;
    }

    const numeric = Number(normalized) * sign;
    return Number.isFinite(numeric) ? numeric : null;
}

function dpcSortableCellValue(cell, type, valueAttribute = 'sortValue') {
    const source = Object.hasOwn(cell.dataset, valueAttribute) ? cell.dataset[valueAttribute] : cell.textContent;
    const raw = String(source ?? '').trim();
    if (!raw || raw === '—' || raw === '-') return { missing: true, value: null };
    if (type === 'number' || type === 'percent') {
        const numeric = dpcParseSortableNumber(raw, type);
        return { missing: numeric === null, value: numeric };
    }
    if (type === 'date') {
        const numeric = Number(raw);
        const dateValue = Number.isFinite(numeric) && raw !== '' ? numeric : Date.parse(raw);
        return { missing: !Number.isFinite(dateValue), value: dateValue };
    }
    return { missing: false, value: raw.toLocaleLowerCase() };
}

function dpcSortTableByHeader(header, forcedDirection = '', options = {}) {
    const table = header.closest('table[data-dpc-sortable-table]');
    const body = table?.tBodies?.[0];
    if (!table || !body) return;
    const headers = Array.from(header.parentElement?.cells || []);
    const columnIndex = headers.indexOf(header);
    if (columnIndex < 0) return;

    const type = options.type || header.dataset.dpcSortType || 'text';
    const valueAttribute = options.valueAttribute || 'sortValue';
    const activeSortKey = `${header.dataset.dpcSortKey || columnIndex}:${valueAttribute}`;
    const direction = forcedDirection || (
        header.dataset.dpcActiveSortKey === activeSortKey && header.getAttribute('aria-sort') === 'ascending'
            ? 'desc'
            : 'asc'
    );
    const rows = Array.from(body.rows).map((row, originalIndex) => ({
        row,
        originalIndex,
        parsed: dpcSortableCellValue(row.cells[columnIndex] || row, type, valueAttribute),
    }));
    const multiplier = direction === 'desc' ? -1 : 1;

    rows.sort((left, right) => {
        if (left.parsed.missing !== right.parsed.missing) return left.parsed.missing ? 1 : -1;
        if (left.parsed.missing) return left.originalIndex - right.originalIndex;
        const comparison = typeof left.parsed.value === 'string'
            ? left.parsed.value.localeCompare(right.parsed.value, undefined, { numeric: true, sensitivity: 'base' })
            : left.parsed.value - right.parsed.value;
        return comparison ? comparison * multiplier : left.originalIndex - right.originalIndex;
    });
    rows.forEach(item => body.appendChild(item.row));
    table.querySelectorAll('th[data-dpc-sort-type]').forEach(other => other.setAttribute('aria-sort', 'none'));
    header.setAttribute('aria-sort', direction === 'desc' ? 'descending' : 'ascending');
    header.dataset.dpcActiveSortKey = activeSortKey;
    table.querySelectorAll('[data-dpc-sort-secondary]').forEach(control => {
        const active = control === options.control;
        control.classList.toggle('active', active);
        control.setAttribute('aria-pressed', active ? 'true' : 'false');
        if (!active) delete control.dataset.sortDirection;
    });
    if (options.control) {
        options.control.dataset.sortDirection = direction;
    }
}

function dpcSortMatrixColumns(header, forcedDirection = '') {
    const table = header.closest('table[data-dpc-matrix-sort]');
    if (!table) return;
    const direction = forcedDirection || (header.getAttribute('aria-sort') === 'ascending' ? 'desc' : 'asc');
    const headers = Array.from(table.querySelectorAll('thead th[data-dpc-matrix-column]'));
    const ordered = headers.sort((left, right) => {
        const comparison = (left.dataset.dpcMatrixLabel || '').localeCompare(
            right.dataset.dpcMatrixLabel || '',
            undefined,
            { numeric: true, sensitivity: 'base' }
        );
        return direction === 'desc' ? -comparison : comparison;
    });
    const keys = ordered.map(cell => cell.dataset.dpcMatrixColumn || '');

    table.querySelectorAll('tr').forEach(row => {
        const keyedCells = new Map(
            Array.from(row.querySelectorAll('[data-dpc-matrix-column]'))
                .map(cell => [cell.dataset.dpcMatrixColumn || '', cell])
        );
        const trailingCell = row.querySelector('.dpc-notes-col, .dpc-row-note');
        keys.forEach(key => {
            const cell = keyedCells.get(key);
            if (cell) row.insertBefore(cell, trailingCell);
        });
    });
    table.querySelectorAll('th[data-dpc-matrix-column]').forEach(other => other.setAttribute('aria-sort', 'none'));
    const activeHeader = table.querySelector(`th[data-dpc-matrix-column="${dpcCssEscape(header.dataset.dpcMatrixColumn || '')}"]`);
    activeHeader?.setAttribute('aria-sort', direction === 'desc' ? 'descending' : 'ascending');
}

function dpcInitTableSorting() {
    document.querySelectorAll('th[data-dpc-sort-type], th[data-dpc-matrix-column]').forEach(header => {
        header.classList.add('dpc-sortable-header');
        header.tabIndex = 0;
        header.setAttribute('aria-sort', 'none');
        const activate = () => {
            if (header.hasAttribute('data-dpc-matrix-column')) dpcSortMatrixColumns(header);
            else dpcSortTableByHeader(header);
        };
        header.addEventListener('click', activate);
        header.addEventListener('keydown', event => {
            if (event.target !== header) return;
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            activate();
        });
    });
    document.querySelectorAll('[data-dpc-sort-secondary]').forEach(control => {
        control.setAttribute('aria-pressed', 'false');
        control.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const header = control.closest('th[data-dpc-sort-type]');
            if (!header) return;
            const defaultDirection = control.dataset.dpcSortDefault || 'asc';
            const direction = control.dataset.sortDirection
                ? (control.dataset.sortDirection === 'asc' ? 'desc' : 'asc')
                : defaultDirection;
            dpcSortTableByHeader(header, direction, {
                type: control.dataset.dpcSortType || 'number',
                valueAttribute: control.dataset.dpcSortSecondary || 'sortValue',
                control,
            });
        });
    });
}

function dpcSyncLiveKurs(exchangeRate = Number(window.DPC_CURRENT_EXCHANGE_RATE || 0)) {
    document.querySelectorAll('.matrix-input[data-currency="USD"]').forEach(input => {
        const tld = input.dataset.tld || '';
        const source = input.dataset.source || '';
        const type = input.dataset.type || '';
        const target = document.querySelector(`[data-live-idr][data-tld="${dpcCssEscape(tld)}"][data-source="${dpcCssEscape(source)}"][data-type="${dpcCssEscape(type)}"]`);
        if (!target) return;

        const usd = dpcParseMoneyInput(input.value);
        if (usd === null || exchangeRate <= 0) {
            target.innerHTML = '<span class="dpc-empty-value">—</span>';
            target.dataset.liveValue = '';
            return;
        }
        const idr = usd * exchangeRate;
        target.innerHTML = dpcFormatIdrPreview(idr);
        target.dataset.liveValue = String(idr);
    });
}

function dpcSyncInternalPricing(exchangeRate = Number(window.DPC_CURRENT_EXCHANGE_RATE || 0)) {
    if (!window.DPC_MATRIX_EDITABLE) return;

    const multiplier = Number(window.DPC_TARGET_MARGIN_MULTIPLIER || 1.30);
    const candidateGroups = new Map();
    document.querySelectorAll('.matrix-input[data-currency="USD"][data-source]').forEach(input => {
        const usdValue = dpcParseMoneyInput(input.value);
        const tld = input.dataset.tld || '';
        const type = input.dataset.type || '';
        if (usdValue === null || exchangeRate <= 0 || !tld || !type) return;

        const key = `${tld}:${type}`;
        if (!candidateGroups.has(key)) candidateGroups.set(key, []);
        candidateGroups.get(key).push({
            sourceId: Number(input.dataset.source || 0),
            sourceName: input.dataset.sourceName || 'Registrar',
            sourceOrder: Number(input.dataset.sourceOrder || Number.MAX_SAFE_INTEGER),
            usdValue,
        });
    });

    document.querySelectorAll('.matrix-input[data-internal-pricing]').forEach(input => {
        if (!Object.hasOwn(input.dataset, 'manualValue')) {
            input.dataset.manualValue = input.dataset.savedValue || input.value || '';
        }

        const key = `${input.dataset.tld || ''}:${input.dataset.type || ''}`;
        const candidates = candidateGroups.get(key) || [];
        candidates.sort((left, right) => (
            (left.usdValue - right.usdValue)
            || (left.sourceOrder - right.sourceOrder)
            || (left.sourceId - right.sourceId)
        ));

        const wrap = input.closest('.dpc-internal-pricing-wrap');
        const selected = candidates[0];
        if (!selected || !Number.isFinite(multiplier) || multiplier <= 0) {
            if (input.dataset.derivedValue !== undefined) {
                input.value = dpcFormatIdrInputValue(input.dataset.manualValue || '');
            }
            input.readOnly = false;
            delete input.dataset.derivedValue;
            delete input.dataset.derivedSource;
            input.removeAttribute('title');
            wrap?.classList.remove('is-derived');
            return;
        }

        const derivedValue = selected.usdValue * exchangeRate * multiplier;
        input.value = dpcFormatIdrInputValue(derivedValue);
        input.readOnly = true;
        input.dataset.derivedValue = String(derivedValue);
        input.dataset.derivedSource = selected.sourceName;
        input.title = `Calculated from ${selected.sourceName}: ${selected.usdValue} USD × ${exchangeRate} KURS × ${multiplier.toFixed(2)}`;
        wrap?.classList.add('is-derived');
    });
}

function dpcSyncCheapestRegistrarHighlights() {
    document.querySelectorAll('.dpc-registrar-price.is-cheapest').forEach(cell => {
        cell.classList.remove('is-cheapest');
        cell.removeAttribute('title');
    });

    const groups = new Map();
    document.querySelectorAll('.dpc-registrar-price[data-type="cost_register"], .dpc-registrar-price[data-type="cost_renewal"]').forEach(wrap => {
        const input = wrap.querySelector('.matrix-input[data-currency="USD"]');
        const value = dpcParseMoneyInput(input?.value);
        if (value === null) return;
        const key = `${wrap.dataset.tld || ''}:${wrap.dataset.type || ''}`;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push({ wrap, value });
    });

    groups.forEach(items => {
        if (!items.length) return;
        const min = Math.min(...items.map(item => item.value));
        items.forEach(item => {
            if (Math.abs(item.value - min) < 0.000001) {
                item.wrap.classList.add('is-cheapest');
                item.wrap.title = 'Cheapest registrar price for this extension and type';
            }
        });
    });
}

function dpcSetMatrixRiskState(wrap, state = 'neutral', title = '') {
    if (!wrap) return;
    wrap.classList.remove('dpc-cell-danger', 'dpc-cell-warning', 'dpc-cell-safe', 'dpc-cell-neutral');
    wrap.classList.add(`dpc-cell-${state}`);
    if (title) {
        wrap.title = title;
    } else {
        wrap.removeAttribute('title');
    }
}

function dpcSyncCctldRiskHighlights() {
    const multiplier = Number(window.DPC_TARGET_MARGIN_MULTIPLIER || 1.30);
    const minimumBufferMultiplier = 1.03;
    document.querySelectorAll('[data-cctld-price-cell][data-cctld-role="current"]').forEach(wrap => {
        const tld = wrap.dataset.tld || '';
        const type = wrap.dataset.type || '';
        const baselineWrap = document.querySelector(
            `[data-cctld-price-cell][data-cctld-role="baseline"][data-tld="${dpcCssEscape(tld)}"][data-type="${dpcCssEscape(type)}"]`
        );
        const baseline = dpcParseIdrInput(baselineWrap?.querySelector('.matrix-input')?.value);
        const current = dpcParseIdrInput(wrap.querySelector('.matrix-input')?.value);
        if (baseline === null || current === null || baseline <= 0 || !Number.isFinite(multiplier)) {
            dpcSetMatrixRiskState(wrap, 'neutral', 'Missing comparison data');
            return;
        }
        if (current < baseline * minimumBufferMultiplier) {
            dpcSetMatrixRiskState(wrap, 'danger', 'Below PANDI + 3% minimum buffer');
            return;
        }
        if (current < baseline * multiplier) {
            dpcSetMatrixRiskState(wrap, 'warning', 'Below 30% target margin');
            return;
        }
        dpcSetMatrixRiskState(wrap, 'safe', 'Meets 30% target margin');
    });
}

function dpcSyncWebsiteRiskHighlights(exchangeRate = Number(window.DPC_CURRENT_EXCHANGE_RATE || 0)) {
    document.querySelectorAll('[data-website-price-cell]').forEach(wrap => {
        const tld = wrap.dataset.tld || '';
        const type = wrap.dataset.type || '';
        const websitePrice = dpcParseIdrInput(wrap.querySelector('.matrix-input')?.value);
        const registrarPrices = Array.from(document.querySelectorAll(
            `.matrix-input[data-currency="USD"][data-tld="${dpcCssEscape(tld)}"][data-type="${dpcCssEscape(type)}"][data-source]`
        ))
            .map(input => dpcParseMoneyInput(input.value))
            .filter(value => value !== null);
        const lowestUsd = registrarPrices.length ? Math.min(...registrarPrices) : null;
        const rawCost = lowestUsd !== null && exchangeRate > 0 ? lowestUsd * exchangeRate : null;
        const internalInput = document.querySelector(
            `.matrix-input[data-internal-pricing][data-tld="${dpcCssEscape(tld)}"][data-type="${dpcCssEscape(type)}"]`
        );
        const internalTarget = dpcParseIdrInput(internalInput?.value);

        if (websitePrice === null || rawCost === null || internalTarget === null || exchangeRate <= 0) {
            dpcSetMatrixRiskState(wrap, 'neutral', 'Missing comparison data');
            return;
        }
        if (websitePrice < rawCost) {
            dpcSetMatrixRiskState(wrap, 'danger', 'Below lowest registrar cost');
            return;
        }
        if (websitePrice < internalTarget) {
            dpcSetMatrixRiskState(wrap, 'warning', 'Below IDCH internal 30% target');
            return;
        }
        dpcSetMatrixRiskState(wrap, 'safe', 'Meets IDCH internal target');
    });
}

function dpcSyncMatrixRiskHighlights(exchangeRate = Number(window.DPC_CURRENT_EXCHANGE_RATE || 0)) {
    dpcSyncCctldRiskHighlights();
    dpcSyncWebsiteRiskHighlights(exchangeRate);
}

function dpcSyncLiveMatrixPreview(exchangeRate = Number(window.DPC_CURRENT_EXCHANGE_RATE || 0)) {
    dpcSyncLiveKurs(exchangeRate);
    dpcSyncCheapestRegistrarHighlights();
    dpcSyncInternalPricing(exchangeRate);
    dpcSyncMatrixRiskHighlights(exchangeRate);
}

function dpcInitLiveMatrixPreview() {
    const inputs = document.querySelectorAll('.matrix-input[data-currency="USD"]');
    const sync = () => dpcSyncLiveMatrixPreview();
    inputs.forEach(input => {
        const formatted = dpcFormatUsdInputValue(input.value);
        if (formatted !== null) input.value = formatted;
        input.addEventListener('input', sync);
        input.addEventListener('blur', () => {
            const normalized = dpcFormatUsdInputValue(input.value);
            if (normalized !== null) {
                input.value = normalized;
                sync();
            }
        });
    });

    document.querySelectorAll('.matrix-input[data-currency="IDR"]').forEach(input => {
        const numeric = dpcParseIdrInput(input.value);
        if (numeric !== null) input.value = dpcFormatIdrInputValue(numeric);
        input.addEventListener('input', sync);
        input.addEventListener('focus', () => {
            if (input.readOnly) return;
            const focusedValue = dpcParseIdrInput(input.value);
            input.value = focusedValue === null ? '' : dpcFormatDerivedInputValue(focusedValue);
        });
        input.addEventListener('blur', () => {
            const blurredValue = dpcParseIdrInput(input.value);
            input.value = blurredValue === null ? '' : dpcFormatIdrInputValue(blurredValue);
            sync();
        });
    });

    document.querySelectorAll('.matrix-input[data-internal-pricing]').forEach(input => {
        input.addEventListener('input', () => {
            if (!input.readOnly) input.dataset.manualValue = dpcCleanMatrixInputValue(input);
        });
    });

    document.querySelectorAll('[data-source-order-input]').forEach(orderInput => {
        orderInput.addEventListener('input', () => {
            const sourceId = orderInput.dataset.sourceId || '';
            const sourceOrder = Number(orderInput.value || Number.MAX_SAFE_INTEGER);
            document.querySelectorAll(`.matrix-input[data-currency="USD"][data-source="${dpcCssEscape(sourceId)}"], .dpc-registrar-price[data-source="${dpcCssEscape(sourceId)}"]`).forEach(element => {
                element.dataset.sourceOrder = String(sourceOrder);
            });
            sync();
        });
    });
    sync();
}

function dpcInitExtensionForm() {
    const form = document.querySelector('[data-extension-form]');
    const sourceForm = document.querySelector('[data-source-form]');

    document.querySelectorAll('[data-delete-source]').forEach(button => {
        button.addEventListener('click', function(event) {
            const source = this.getAttribute('data-delete-source') || 'this registrar source';
            event.preventDefault();
            tracsConfirm({
                type: 'warning',
                title: 'Disable registrar source',
                message: `Disable ${source} from active pricing matrices? Existing monthly records will stay intact.`,
                confirmText: 'Disable source',
                destructive: true
            }).then(ok => {
                if (ok) this.form?.requestSubmit(this);
            });
        });
    });

    document.querySelectorAll('[data-delete-extension]').forEach(button => {
        button.addEventListener('click', function(event) {
            const extension = this.getAttribute('data-delete-extension') || 'this extension';
            event.preventDefault();
            tracsConfirm({
                type: 'warning',
                title: 'Delete domain extension',
                message: `Delete ${extension} from active pricing matrices? Existing monthly records will stay intact.`,
                confirmText: 'Delete extension',
                destructive: true
            }).then(ok => {
                if (ok) this.form?.requestSubmit(this);
            });
        });
    });

    if (sourceForm) {
        sourceForm.addEventListener('submit', function(event) {
            const input = sourceForm.querySelector('input[name="source_name"]');
            const sortOrder = sourceForm.querySelector('input[name="sort_order"]');
            const normalized = dpcNormalizeSourceName(input?.value);

            if (!normalized || normalized.length > 100 || !/^[A-Za-z0-9][A-Za-z0-9 .&+_()\/-]*$/.test(normalized)) {
                event.preventDefault();
                dpcNotify('Use a valid registrar source name.');
                input?.focus();
                return;
            }

            const knownSources = new Set(
                Array.from(document.querySelectorAll('[data-source-name]'))
                    .map(chip => dpcNormalizeSourceName(chip.dataset.sourceName).toLowerCase())
            );
            if (knownSources.has(normalized.toLowerCase())) {
                event.preventDefault();
                dpcNotify('That registrar source already exists.');
                input?.focus();
                return;
            }

            if (sortOrder?.value.trim()) {
                const parsed = Number(sortOrder.value);
                if (!Number.isInteger(parsed) || parsed < 1 || parsed > 999999) {
                    event.preventDefault();
                    dpcNotify('Source order must be a positive number.');
                    sortOrder.focus();
                    return;
                }
            }

            if (input) input.value = normalized;
        });
    }

    document.querySelectorAll('[data-source-edit-form]').forEach(sourceEditForm => {
        sourceEditForm.addEventListener('submit', function(event) {
            const sortOrder = sourceEditForm.querySelector('input[name="sort_order"]');
            if (sortOrder?.value.trim()) {
                const parsed = Number(sortOrder.value);
                if (!Number.isInteger(parsed) || parsed < 1 || parsed > 999999) {
                    event.preventDefault();
                    dpcNotify('Source order must be a positive number.');
                    sortOrder.focus();
                }
            }
        });
    });

    if (!form) return;

    form.addEventListener('submit', function(event) {
        const input = form.querySelector('input[name="tld_name"]');
        const category = form.querySelector('input[name="tld_category"]:checked') || form.querySelector('select[name="tld_category"]');
        const sortOrder = form.querySelector('input[name="sort_order"]');
        const normalized = dpcNormalizeExtension(input?.value);

        if (!/^\.[a-z0-9-]+(?:\.[a-z0-9-]+)*$/.test(normalized) || normalized.length > 30) {
            event.preventDefault();
            dpcNotify('Use a valid extension such as .app or .web.id.');
            input?.focus();
            return;
        }

        const knownExtensions = new Set(
            Array.from(document.querySelectorAll('[data-extension-name]'))
                .map(chip => dpcNormalizeExtension(chip.dataset.extensionName))
        );
        if (knownExtensions.has(normalized)) {
            event.preventDefault();
            dpcNotify('That extension already exists.');
            input?.focus();
            return;
        }

        if (!category || !['gtld', 'cctld'].includes(category.value)) {
            event.preventDefault();
            dpcNotify('Choose a valid extension category.');
            category?.focus();
            return;
        }

        if (sortOrder?.value.trim()) {
            const parsed = Number(sortOrder.value);
            if (!Number.isInteger(parsed) || parsed < 1 || parsed > 999999) {
                event.preventDefault();
                dpcNotify('Sort order must be a positive number.');
                sortOrder.focus();
                return;
            }
        }

        if (input) input.value = normalized;
    });
}

function dpcInitModalAjaxForms() {
    document.querySelectorAll([
        '#newMonthModal form[method="post"]',
        '#extensionModal form[method="post"]',
        '#updateRateModal form[method="post"]',
        '#approveModal form[method="post"]',
        '#unlockModal form[method="post"]',
        '#duplicateMonthModal form[method="post"]'
    ].join(',')).forEach(form => {
        form.dataset.tracsModalAjax = '';
        form.dataset.closeDelay = '1000';
    });
}

// Close modals when clicking outside content area
window.addEventListener('click', function(event) {
    const newMonthModal = document.getElementById('newMonthModal');
    const updateRateModal = document.getElementById('updateRateModal');
    const approveModal = document.getElementById('approveModal');
    const unlockModal = document.getElementById('unlockModal');
    const duplicateMonthModal = document.getElementById('duplicateMonthModal');
    const assignTaskModal = document.getElementById('assignTaskModal');
    const exportCsvModal = document.getElementById('exportCsvModal');
    const extensionModal = document.getElementById('extensionModal');

    if (event.target?.dataset?.tracsSuccessPending === '1') return;
    if (event.target === newMonthModal) closeNewMonthModal();
    if (event.target === updateRateModal) closeUpdateRateModal();
    if (event.target === approveModal) closeApproveModal();
    if (event.target === unlockModal) closeUnlockModal();
    if (event.target === duplicateMonthModal) closeDuplicateMonthModal();
    if (event.target === assignTaskModal) closeAssignTaskModal();
    if (event.target === exportCsvModal) closeExportModal();
    if (event.target === extensionModal) closeExtensionModal();
});

// 5. Duplicate Month Modal
function openDuplicateMonthModal(monthId, currentMonth, currentRate) {
    const modal = document.getElementById('duplicateMonthModal');
    if (modal) {
        const idInput = document.getElementById('duplicate_from_month_id');
        const monthInput = document.getElementById('duplicate_month_code_input');
        const rateInput = document.getElementById('duplicate_exchange_rate_input');
        
        if (idInput) idInput.value = monthId;
        if (monthInput) {
            let year = parseInt(currentMonth.split('-')[0]);
            let month = parseInt(currentMonth.split('-')[1]);
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
            const nextMonthStr = year + '-' + (month < 10 ? '0' + month : month);
            monthInput.value = nextMonthStr;
        }
        if (rateInput) rateInput.value = currentRate;
        
        tracsOpenModalElement(modal, { display: 'flex' });
        if (monthInput) {
            monthInput.focus();
            monthInput.select();
        }
    }
}

function closeDuplicateMonthModal() {
    const modal = document.getElementById('duplicateMonthModal');
    if (modal) tracsCloseModalElement(modal);
}

// Collapsible Audit Panel
document.addEventListener('DOMContentLoaded', function() {
    dpcInitExtensionForm();
    dpcInitModalAjaxForms();
    dpcInitLiveMatrixPreview();
    dpcInitTableSorting();

    const monthSelect = document.getElementById('period_month_select');
    const yearSelect = document.getElementById('period_year_select');
    const newMonthForm = document.getElementById('newMonthForm');
    const exchangeRateInput = document.getElementById('exchange_rate_input');
    const updateRateInput = document.getElementById('update_rate_input');

    if (monthSelect) monthSelect.addEventListener('change', syncNewMonthPreview);
    if (yearSelect) yearSelect.addEventListener('change', syncNewMonthPreview);
    if (exchangeRateInput) {
        exchangeRateInput.addEventListener('focus', function() {
            this.value = this.dataset.rawValue || dpcCleanRate(this.value);
        });
        exchangeRateInput.addEventListener('input', function() {
            this.dataset.rawValue = dpcCleanRate(this.value);
        });
        exchangeRateInput.addEventListener('blur', function() {
            this.dataset.rawValue = dpcCleanRate(this.value);
            const formatted = dpcFormatRate(this.dataset.rawValue);
            if (formatted) this.value = formatted;
        });
    }
    if (updateRateInput) {
        updateRateInput.addEventListener('input', function() {
            const previewRate = dpcParseMoneyInput(this.value);
            window.DPC_CURRENT_EXCHANGE_RATE = previewRate || Number(window.DPC_SAVED_EXCHANGE_RATE || 0);
            dpcSyncLiveMatrixPreview();
        });
    }
    if (newMonthForm) {
        newMonthForm.addEventListener('submit', function(event) {
            syncNewMonthPreview();
            const monthCode = document.getElementById('month_code_input')?.value || '';
            const existing = dpcMonthRecordMap()[monthCode];
            if (existing) {
                event.preventDefault();
                handleModalError({
                    modal: 'newMonthModal',
                    message: `A monthly record for ${dpcMonthLabel(monthCode)} already exists. Select the existing record instead.`,
                    focus: monthSelect
                });
                return;
            }
            if (exchangeRateInput) {
                exchangeRateInput.value = dpcCleanRate(exchangeRateInput.dataset.rawValue || exchangeRateInput.value);
            }
        });
    }
    syncNewMonthPreview();

    const sectionTabs = document.querySelectorAll('[data-dpc-tab]');
    const modulePanels = document.querySelectorAll('[data-dpc-panel]');
    if (sectionTabs.length && modulePanels.length) {
        const tabAliases = {
            'action-buckets': 'intelligence-summary',
            'cctld-check': 'website-adjustment',
        };
        const normalizeTabKey = key => tabAliases[key] || key;
        const focusSection = (target) => {
            target.classList.remove('dpc-section-focus');
            void target.offsetWidth;
            target.classList.add('dpc-section-focus');
            window.setTimeout(() => target.classList.remove('dpc-section-focus'), 1300);
        };

        const alignPanelBelowTabs = (target, smooth = false) => {
            const tabs = document.querySelector('.dpc-section-tabs');
            if (!tabs) return;
            window.requestAnimationFrame(() => {
                const gap = 10;
                const desiredTop = tabs.getBoundingClientRect().bottom + gap;
                const currentTop = target.getBoundingClientRect().top;
                const delta = currentTop - desiredTop;
                if (Math.abs(delta) <= 2) return;
                window.scrollTo({
                    top: Math.max(0, window.scrollY + delta),
                    behavior: smooth && !window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'smooth' : 'auto',
                });
            });
        };

        const openTab = (key, options = {}) => {
            const normalizedKey = normalizeTabKey(key);
            const target = document.querySelector(`[data-dpc-panel="${dpcCssEscape(normalizedKey)}"]`);
            if (!target) return;
            sectionTabs.forEach(tab => {
                const active = tab.getAttribute('data-dpc-tab') === normalizedKey;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            modulePanels.forEach(panel => {
                const active = panel === target;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
            if (options.updateHash !== false) {
                window.history.replaceState(null, '', `#${normalizedKey}`);
            }
            if (options.align !== false) {
                alignPanelBelowTabs(target, options.smooth === true);
                window.setTimeout(() => focusSection(target), 260);
            }
        };

        sectionTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                openTab(this.getAttribute('data-dpc-tab') || 'overview');
            });
        });
        document.querySelectorAll('[data-dpc-open-tab]').forEach(trigger => {
            trigger.addEventListener('click', function() {
                openTab(this.getAttribute('data-dpc-open-tab') || 'overview', { smooth: true });
            });
        });

        const initialHashKey = (window.location.hash || '').replace(/^#/, '');
        const initialKey = normalizeTabKey(initialHashKey);
        openTab(document.querySelector(`[data-dpc-panel="${dpcCssEscape(initialKey)}"]`) ? initialKey : 'overview', {
            updateHash: initialHashKey !== initialKey,
            align: false
        });
        window.addEventListener('hashchange', () => {
            const hashKey = (window.location.hash || '').replace(/^#/, '');
            const normalizedKey = normalizeTabKey(hashKey);
            const nextKey = document.querySelector(`[data-dpc-panel="${dpcCssEscape(normalizedKey)}"]`)
                ? normalizedKey
                : 'overview';
            openTab(nextKey, {
                updateHash: hashKey !== nextKey,
                align: false
            });
        });
    }

    document.querySelectorAll('[data-dpc-click-target]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const selector = this.getAttribute('data-dpc-click-target') || '';
            if (!selector) return;
            const target = document.querySelector(selector);
            if (target && !target.disabled) {
                target.click();
            }
        });
    });

    const exportForm = document.getElementById('dpcMonthlyExportForm');
    if (exportForm) {
        const singleWrap = exportForm.querySelector('[data-dpc-export-single]');
        const rangeWrap = exportForm.querySelector('[data-dpc-export-range]');
        const singleInput = exportForm.querySelector('input[name="month"]');
        const fromInput = exportForm.querySelector('input[name="from_month"]');
        const toInput = exportForm.querySelector('input[name="to_month"]');
        const validation = exportForm.querySelector('[data-dpc-export-validation]');

        const showExportValidation = (message) => {
            if (validation) {
                validation.textContent = message;
                validation.hidden = !message;
            }
            if (message && typeof toast === 'function') {
                toast(message, 'error');
            }
        };

        const syncExportScope = () => {
            const scope = exportForm.querySelector('input[name="export_scope"]:checked')?.value || 'single';
            const isRange = scope === 'range';
            exportForm.querySelectorAll('.dpc-export-option').forEach(option => {
                const input = option.querySelector('input[name="export_scope"]');
                option.classList.toggle('is-active', !!input?.checked);
            });
            if (singleWrap) singleWrap.hidden = isRange;
            if (rangeWrap) rangeWrap.hidden = !isRange;
            if (singleInput) singleInput.disabled = isRange;
            if (fromInput) fromInput.disabled = !isRange;
            if (toInput) toInput.disabled = !isRange;
            showExportValidation('');
        };

        exportForm.querySelectorAll('input[name="export_scope"]').forEach(input => {
            input.addEventListener('change', syncExportScope);
        });
        [singleInput, fromInput, toInput].forEach(input => {
            if (input) input.addEventListener('input', () => showExportValidation(''));
        });
        exportForm.addEventListener('submit', function(event) {
            const scope = exportForm.querySelector('input[name="export_scope"]:checked')?.value || 'single';
            if (scope === 'range') {
                const fromMonth = fromInput?.value || '';
                const toMonth = toInput?.value || '';
                if (!fromMonth || !toMonth) {
                    event.preventDefault();
                    showExportValidation('Choose both From Month and To Month before downloading.');
                    return;
                }
                if (toMonth < fromMonth) {
                    event.preventDefault();
                    showExportValidation('To Month cannot be earlier than From Month.');
                    return;
                }
                return;
            }
            if (!(singleInput?.value)) {
                event.preventDefault();
                showExportValidation('Choose a month before downloading.');
            }
        });
        syncExportScope();
    }

    document.querySelectorAll('[data-dpc-filter]').forEach(button => {
        button.addEventListener('click', function() {
            const scope = this.getAttribute('data-dpc-filter') || '';
            const filter = this.getAttribute('data-dpc-filter-value') || 'all';
            dpcApplyFilter(scope, filter, this);
        });
    });

    document.querySelectorAll('[data-dpc-filter-shortcut]').forEach(button => {
        button.addEventListener('click', function() {
            const scope = this.dataset.dpcFilterShortcut || '';
            const filter = this.dataset.dpcFilterValue || 'all';
            dpcApplyFilter(scope, filter);
            const sortKey = this.dataset.dpcSortKey || '';
            if (!sortKey) return;
            const shortcutScopes = scope === 'both' ? ['website', 'cctld'] : [scope];
            shortcutScopes.forEach(currentScope => {
                const row = document.querySelector(`[data-dpc-filter-scope="${dpcCssEscape(currentScope)}"]`);
                const table = row?.closest('table[data-dpc-sortable-table]');
                let header = table?.querySelector(`th[data-dpc-sort-key="${dpcCssEscape(sortKey)}"]`);
                let sortOptions = {};
                if (!header && sortKey === 'required-increase') {
                    header = table?.querySelector('th[data-dpc-sort-key="margin-status"]');
                    sortOptions = { type: 'number', valueAttribute: 'sortGap' };
                }
                if (header) dpcSortTableByHeader(header, this.dataset.dpcSortDirection || 'desc', sortOptions);
            });
        });
    });

    document.querySelectorAll('.dpc-audit-panel.collapsed').forEach(panel => {
        panel.classList.remove('collapsed');
    });

    // TLD Notes AJAX Form Submission
    const notesForm = document.getElementById('tldNotesForm');
    if (notesForm) {
        const noteTldSelect = document.getElementById('note_tld_select');
        const noteManualInput = document.getElementById('note_manual_input');
        const noteStatusSelect = document.getElementById('note_status_select');
        const savedNotes = window.DPC_TLD_NOTES || {};
        const syncNoteForm = () => {
            const note = savedNotes[String(noteTldSelect?.value || '')] || null;
            if (noteManualInput) noteManualInput.value = note?.manual_note || '';
            if (noteStatusSelect) {
                noteStatusSelect.value = note?.follow_up_status || 'No Action';
                window.TRACSDropdowns?.syncSelect?.(noteStatusSelect);
            }
        };
        if (noteTldSelect) noteTldSelect.addEventListener('change', syncNoteForm);

        notesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnSaveNote = document.getElementById('btnSaveNote');
            if (btnSaveNote && !setButtonLoading(btnSaveNote, 'Saving...')) return;

            const formData = new FormData(notesForm);
            const payload = {
                action: 'save_tld_note',
                month_id: formData.get('month_id'),
                tld_id: formData.get('tld_id'),
                manual_note: formData.get('manual_note'),
                follow_up_status: formData.get('follow_up_status') || 'No Action',
                detailed_note: '' // reserved for future rich text
            };

            fetch('api/domain-price-workflow.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    dpcToast('success', 'Note saved', 'TLD note saved successfully.');
                    notesForm.dispatchEvent(new CustomEvent('tracs:save-success', { bubbles: true, detail: { root: notesForm } }));
                    dpcReloadAfterNotice();
                } else {
                    notesForm.dispatchEvent(new CustomEvent('tracs:save-error', { bubbles: true }));
                    dpcToast('error', 'Save failed', data.message || 'The note could not be saved. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                notesForm.dispatchEvent(new CustomEvent('tracs:save-error', { bubbles: true }));
                dpcToast('error', 'Network error', 'A network error occurred while saving the note.');
            })
            .finally(() => {
                resetButtonLoading(btnSaveNote);
            });
        });
    }

    // Task Assignment AJAX Form Submission
    const assignForm = document.getElementById('assignTaskForm');
    if (assignForm) {
        assignForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnSave = document.getElementById('btnAssignTaskSave');
            if (btnSave && !setButtonLoading(btnSave, 'Saving...')) return;

            const formData = new FormData(assignForm);
            const payload = {
                action: 'assign_task',
                month_id: formData.get('month_id'),
                assigned_to: formData.get('assigned_to'),
                due_date: formData.get('due_date'),
                priority: formData.get('priority')
            };

            fetch('api/domain-price-task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showModalSuccessAndClose({
                        modal: 'assignTaskModal',
                        button: btnSave,
                        message: 'Task assigned successfully.',
                        close: () => closeAssignTaskModal(),
                        onAfterClose: () => window.location.reload()
                    });
                } else {
                    handleModalError({
                        modal: 'assignTaskModal',
                        button: btnSave,
                        error: { message: data.message || '' },
                        fallbackMessage: 'The task could not be assigned. Please check the data and try again.'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                handleModalError({
                    modal: 'assignTaskModal',
                    button: btnSave,
                    error,
                    fallbackMessage: 'A network error occurred while assigning the task.'
                });
            })
            .finally(() => {
                resetButtonLoading(btnSave);
            });
        });
    }
});

    // Matrix Logic
    const btnSaveMatrix = document.getElementById('btnSaveMatrix');
    if (btnSaveMatrix) {
        btnSaveMatrix.addEventListener('click', function() {
            const btn = this;
            if (!setButtonLoading(btn, 'Saving...')) return;

            const inputs = document.querySelectorAll('.matrix-input');
            const entries = [];
            
            inputs.forEach(input => {
                entries.push({
                    tld_id: input.getAttribute('data-tld'),
                    source_id: input.getAttribute('data-source'),
                    price_type: input.getAttribute('data-type'),
                    currency: input.getAttribute('data-currency'),
                    value: dpcCleanMatrixInputValue(input)
                });
            });

            // Need month_id
            const urlParams = new URLSearchParams(window.location.search);
            let monthId = urlParams.get('month_id');
            if (!monthId) {
                const monthSelect = document.getElementById('month_id_select');
                if (monthSelect) monthId = monthSelect.value;
            }

            const payload = {
                action: 'save_matrix',
                month_id: monthId,
                entries: entries
            };

            fetch('api/domain-price-matrix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const saved = dpcMatrixSavedNotice(data.message);
                    dpcToast('success', saved.title, saved.message);
                    btn.dispatchEvent(new CustomEvent('tracs:save-success', { bubbles: true, detail: { root: document.getElementById('price-matrix') } }));
                    dpcReloadAfterNotice();
                } else {
                    btn.dispatchEvent(new CustomEvent('tracs:save-error', { bubbles: true }));
                    dpcToast('error', 'Matrix save failed', data.message || 'The matrix could not be saved. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.dispatchEvent(new CustomEvent('tracs:save-error', { bubbles: true }));
                dpcToast('error', 'Network error', 'A network error occurred while saving the matrix.');
            })
            .finally(() => resetButtonLoading(btn));
        });
    }

    const btnRecalculate = document.getElementById('btnRecalculate');
    if (btnRecalculate) {
        btnRecalculate.addEventListener('click', function() {
            const btn = this;
            if (!setButtonLoading(btn, 'Processing...')) return;

            const urlParams = new URLSearchParams(window.location.search);
            let monthId = urlParams.get('month_id');
            if (!monthId) {
                const ms = document.getElementById('month_id_select');
                if (ms) monthId = ms.value;
            }

            fetch('api/domain-price-recalculate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ action: 'recalculate', month_id: monthId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    dpcToast('success', 'Summary recalculated', data.data?.message || data.message || 'Summary recalculated successfully.');
                    dpcReloadAfterNotice();
                } else {
                    dpcToast('error', 'Recalculation failed', data.message || 'Recalculation failed.');
                }
            })
            .catch(err => {
                console.error('Recalculate error:', err);
                dpcToast('error', 'Network error', 'A network error occurred during recalculation.');
            })
            .finally(() => resetButtonLoading(btn));
        });
    }

    const matrixRoot = document.getElementById('price-matrix');
    if (matrixRoot && btnSaveMatrix && window.TRACSUnsavedChanges) {
        window.TRACSUnsavedChanges.register({
            root: matrixRoot,
            mountBefore: '.dpc-toolbar',
            normalize: input => input.classList.contains('matrix-input')
                ? dpcCleanMatrixInputValue(input)
                : String(input.value ?? '').trim(),
            save: () => new Promise(resolve => {
                let settled = false;
                const finish = result => {
                    if (settled) return;
                    settled = true;
                    btnSaveMatrix.removeEventListener('tracs:save-success', handleSuccess);
                    btnSaveMatrix.removeEventListener('tracs:save-error', handleError);
                    resolve(result);
                };
                const handleSuccess = () => finish(true);
                const handleError = () => finish(false);
                btnSaveMatrix.addEventListener('tracs:save-success', handleSuccess, { once: true });
                btnSaveMatrix.addEventListener('tracs:save-error', handleError, { once: true });
                btnSaveMatrix.click();
                window.setTimeout(() => finish(false), 20000);
            })
        });
    }
