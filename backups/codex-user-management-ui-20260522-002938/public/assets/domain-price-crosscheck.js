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
        modal.style.display = 'flex';
        const monthSelect = document.getElementById('period_month_select');
        if (monthSelect) monthSelect.focus();
    }
}

function closeNewMonthModal() {
    const modal = document.getElementById('newMonthModal');
    if (modal) {
        modal.style.display = 'none';
    }
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
            modal.style.display = 'flex';
            rateInput.focus();
            rateInput.select();
        }
    }
}

function closeUpdateRateModal() {
    const modal = document.getElementById('updateRateModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 3. Approve and Lock Modal
function openApproveModal(monthId) {
    const modal = document.getElementById('approveModal');
    if (modal) {
        const idInput = document.getElementById('approve_month_id');
        if (idInput) idInput.value = monthId;
        modal.style.display = 'flex';
        const noteInput = document.getElementById('approval_note_input');
        if (noteInput) noteInput.focus();
    }
}

function closeApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 4. Unlock Modal
function openUnlockModal(monthId) {
    const modal = document.getElementById('unlockModal');
    if (modal) {
        const idInput = document.getElementById('unlock_month_id');
        if (idInput) idInput.value = monthId;
        modal.style.display = 'flex';
        const reasonInput = document.getElementById('unlock_reason_input');
        if (reasonInput) reasonInput.focus();
    }
}

function closeUnlockModal() {
    const modal = document.getElementById('unlockModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 4b. Assign Task Modal
function openAssignTaskModal(monthId) {
    const modal = document.getElementById('assignTaskModal');
    if (modal) {
        const idInput = document.getElementById('assign_task_month_id');
        if (idInput) idInput.value = monthId;
        modal.style.display = 'flex';
    }
}

function closeAssignTaskModal() {
    const modal = document.getElementById('assignTaskModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 4c. Domain Extension Modal
function openExtensionModal() {
    const modal = document.getElementById('extensionModal');
    if (modal) {
        modal.style.display = 'flex';
        const extensionInput = modal.querySelector('input[name="tld_name"]');
        if (extensionInput) extensionInput.focus();
    }
}

function closeExtensionModal() {
    const modal = document.getElementById('extensionModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modals when clicking outside content area
window.addEventListener('click', function(event) {
    const newMonthModal = document.getElementById('newMonthModal');
    const updateRateModal = document.getElementById('updateRateModal');
    const approveModal = document.getElementById('approveModal');
    const unlockModal = document.getElementById('unlockModal');
    const duplicateMonthModal = document.getElementById('duplicateMonthModal');
    const assignTaskModal = document.getElementById('assignTaskModal');
    const extensionModal = document.getElementById('extensionModal');

    if (event.target === newMonthModal) closeNewMonthModal();
    if (event.target === updateRateModal) closeUpdateRateModal();
    if (event.target === approveModal) closeApproveModal();
    if (event.target === unlockModal) closeUnlockModal();
    if (event.target === duplicateMonthModal) closeDuplicateMonthModal();
    if (event.target === assignTaskModal) closeAssignTaskModal();
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
        
        modal.style.display = 'flex';
        if (monthInput) {
            monthInput.focus();
            monthInput.select();
        }
    }
}

function closeDuplicateMonthModal() {
    const modal = document.getElementById('duplicateMonthModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Collapsible Audit Panel
document.addEventListener('DOMContentLoaded', function() {
    const monthSelect = document.getElementById('period_month_select');
    const yearSelect = document.getElementById('period_year_select');
    const newMonthForm = document.getElementById('newMonthForm');
    const exchangeRateInput = document.getElementById('exchange_rate_input');

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
    if (newMonthForm) {
        newMonthForm.addEventListener('submit', function(event) {
            syncNewMonthPreview();
            const monthCode = document.getElementById('month_code_input')?.value || '';
            const existing = dpcMonthRecordMap()[monthCode];
            if (existing) {
                event.preventDefault();
                return;
            }
            if (exchangeRateInput) {
                exchangeRateInput.value = dpcCleanRate(exchangeRateInput.dataset.rawValue || exchangeRateInput.value);
            }
        });
    }
    syncNewMonthPreview();

    const sectionTabs = document.querySelectorAll('.dpc-section-tabs a[href^="#"]');
    if (sectionTabs.length) {
        const focusSection = (target) => {
            target.classList.remove('dpc-section-focus');
            void target.offsetWidth;
            target.classList.add('dpc-section-focus');
            window.setTimeout(() => target.classList.remove('dpc-section-focus'), 1300);
        };

        sectionTabs.forEach(tab => {
            tab.addEventListener('click', function(event) {
                const target = document.querySelector(this.getAttribute('href'));
                if (!target) return;
                event.preventDefault();
                sectionTabs.forEach(item => item.classList.toggle('active', item === this));
                target.scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    block: 'start'
                });
                window.history.replaceState(null, '', this.getAttribute('href'));
                window.setTimeout(() => focusSection(target), 260);
            });
        });
    }

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

    document.querySelectorAll('[data-summary-filter]').forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-summary-filter') || 'all';
            document.querySelectorAll('[data-summary-filter]').forEach(btn => btn.classList.toggle('active', btn === this));
            document.querySelectorAll('[data-summary-status]').forEach(row => {
                const status = row.getAttribute('data-summary-status') || '';
                const severity = row.getAttribute('data-summary-severity') || '';
                const visible = filter === 'all' || status === filter || severity === filter;
                row.hidden = !visible;
            });
        });
    });

    const auditPanel = document.querySelector('.dpc-audit-panel');
    if (auditPanel) {
        // Start collapsed by default
        auditPanel.classList.add('collapsed');
        
        const header = auditPanel.querySelector('.panel-head');
        if (header) {
            header.addEventListener('click', function() {
                auditPanel.classList.toggle('collapsed');
            });
        }
    }

    // TLD Notes AJAX Form Submission
    const notesForm = document.getElementById('tldNotesForm');
    if (notesForm) {
        notesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnSaveNote = document.getElementById('btnSaveNote');
            if (btnSaveNote) btnSaveNote.disabled = true;

            const formData = new FormData(notesForm);
            const payload = {
                action: 'save_tld_note',
                month_id: formData.get('month_id'),
                tld_id: formData.get('tld_id'),
                manual_note: formData.get('manual_note'),
                follow_up_status: formData.get('follow_up_status'),
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
                    alert('Note saved successfully!');
                    window.location.reload(); // Reload to show the updated notes table
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A network error occurred.');
            })
            .finally(() => {
                if (btnSaveNote) btnSaveNote.disabled = false;
            });
        });
    }

    // Task Assignment AJAX Form Submission
    const assignForm = document.getElementById('assignTaskForm');
    if (assignForm) {
        assignForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnSave = document.getElementById('btnAssignTaskSave');
            if (btnSave) btnSave.disabled = true;

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
                    alert('Task assigned successfully!');
                    window.location.reload(); 
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A network error occurred.');
            })
            .finally(() => {
                if (btnSave) btnSave.disabled = false;
            });
        });
    }
});

    // Matrix Logic
    const btnSaveMatrix = document.getElementById('btnSaveMatrix');
    if (btnSaveMatrix) {
        btnSaveMatrix.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="icon-xs spin"></i> Saving...';
            lucide.createIcons();

            const inputs = document.querySelectorAll('.matrix-input');
            const entries = [];
            
            inputs.forEach(input => {
                entries.push({
                    tld_id: input.getAttribute('data-tld'),
                    source_id: input.getAttribute('data-source'),
                    price_type: input.getAttribute('data-type'),
                    currency: input.getAttribute('data-currency'),
                    value: input.value
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
                    alert(data.message || 'Matrix saved successfully!');
                    window.location.reload(); 
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="save" class="icon-xs"></i> Save Matrix';
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A network error occurred.');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save" class="icon-xs"></i> Save Matrix';
                lucide.createIcons();
            });
        });
    }

    const btnRecalculate = document.getElementById('btnRecalculate');
    if (btnRecalculate) {
        btnRecalculate.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="icon-xs spin"></i> Recalculating...';
            lucide.createIcons();

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
                    alert(data.data?.message || data.message || 'Summary recalculated!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Recalculation failed.'));
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="calculator" class="icon-xs"></i> Recalculate Summary';
                    lucide.createIcons();
                }
            })
            .catch(err => {
                console.error('Recalculate error:', err);
                alert('A network error occurred during recalculation.');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="calculator" class="icon-xs"></i> Recalculate Summary';
                lucide.createIcons();
            });
        });
    }
