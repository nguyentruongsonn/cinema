/**
 * Seat Layout Templates Management - seat-layout-templates.js
 * Pattern: IIFE, no global scope pollution
 */
(function () {
    'use strict';

    /* ── Preset matrix config ────────────────────────────────────────── */
    const MATRIX_PRESETS = {
        '12x12': { rows: 12, cols: 12, capacity: 144 },
        '13x13': { rows: 13, cols: 13, capacity: 169 },
        '14x14': { rows: 14, cols: 14, capacity: 196 },
        '15x15': { rows: 15, cols: 15, capacity: 225 },
    };

    /* ── DOM cache ──────────────────────────────────────────────────── */
    const els = {};

    function cacheDoms() {
        els.toggleBtns      = document.querySelectorAll('.toggle-active-btn');
        els.btnCreate       = document.getElementById('btnOpenCreateSeatLayoutTemplate');
        els.btnEdits        = document.querySelectorAll('.btn-edit-seat-layout-template');
        els.modalEl         = document.getElementById('seatLayoutTemplateModal');
        els.form            = document.getElementById('seatLayoutTemplateForm');
        els.modalLabel      = document.getElementById('seatLayoutTemplateModalLabel');
        els.formMethod      = document.getElementById('seatLayoutTemplateFormMethod');
        els.idInput         = document.getElementById('seatLayoutTemplateIdInput');
        els.templateName    = document.getElementById('templateName');
        els.seatMatrix      = document.getElementById('seatMatrix');
        els.regularSeatRows = document.getElementById('regularSeatRows');
        els.vipSeatRows     = document.getElementById('vipSeatRows');
        els.coupleSeatRows  = document.getElementById('coupleSeatRows');
        els.description     = document.getElementById('description');
        els.status          = document.getElementById('templateStatus');
        els.submitBtn       = document.getElementById('sltSubmitBtn');
        // Matrix info elements
        els.matrixInfo      = document.getElementById('matrixInfo');
        els.matrixSize      = document.getElementById('matrixSize');
        els.matrixCapacity  = document.getElementById('matrixCapacity');
        els.matrixRows      = document.getElementById('matrixRows');
        // Row sum elements
        els.rowSumUsed      = document.getElementById('rowSumUsed');
        els.rowSumMax       = document.getElementById('rowSumMax');
        els.rowSumBar       = document.getElementById('rowSumBar');
        els.rowSumWarning   = document.getElementById('rowSumWarning');
        els.rowSumWarningText = document.getElementById('rowSumWarningText');
        els.seatRowInputs   = document.querySelectorAll('.seat-row-input');
    }

    /* ── Helpers ────────────────────────────────────────────────────── */
    let modalInstance = null;

    function getModalInstance() {
        if (!modalInstance && els.modalEl) {
            modalInstance = new bootstrap.Modal(els.modalEl);
        }
        return modalInstance;
    }

    function clearValidationErrors() {
        if (!els.form) return;
        els.form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        els.form.querySelectorAll('[data-error-for]').forEach(el => {
            el.textContent = '';
        });
    }

    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        if (els.status) els.status.checked = true;
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        // Reset matrix UI
        updateMatrixInfo('');
        updateRowSum();
    }

    /* ── Matrix Info & Row Sum Logic ─────────────────────────────────── */
    function updateMatrixInfo(matrixValue) {
        if (!els.matrixInfo) return;

        const preset = MATRIX_PRESETS[matrixValue];

        if (preset) {
            els.matrixInfo.classList.remove('d-none');
            els.matrixSize.textContent     = matrixValue;
            els.matrixCapacity.textContent = preset.capacity;
            els.matrixRows.textContent     = preset.rows;
        } else {
            els.matrixInfo.classList.add('d-none');
        }

        updateRowSum();
    }

    function updateRowSum() {
        if (!els.rowSumUsed) return;

        const matrixValue = els.seatMatrix?.value || '';
        const preset      = MATRIX_PRESETS[matrixValue];
        const maxRows     = preset ? preset.rows : null;

        const regular = parseInt(els.regularSeatRows?.value || '0', 10) || 0;
        const vip     = parseInt(els.vipSeatRows?.value     || '0', 10) || 0;
        const couple  = parseInt(els.coupleSeatRows?.value  || '0', 10) || 0;
        const total   = regular + vip + couple;

        els.rowSumUsed.textContent = total;

        if (maxRows !== null) {
            els.rowSumMax.textContent = maxRows;
            const pct = Math.min((total / maxRows) * 100, 100);

            // Progress bar color
            let barColor;
            if (total > maxRows) {
                barColor = 'linear-gradient(90deg, #ef4444, #dc2626)';
            } else if (pct >= 85) {
                barColor = 'linear-gradient(90deg, #f59e0b, #d97706)';
            } else {
                barColor = 'linear-gradient(90deg, #22c55e, #16a34a)';
            }
            els.rowSumBar.style.width      = pct + '%';
            els.rowSumBar.style.background = barColor;

            // Warning
            if (total > maxRows) {
                els.rowSumWarning.classList.remove('d-none');
                els.rowSumWarningText.textContent =
                    `Tổng ${total} hàng vượt quá giới hạn ${maxRows} hàng của ma trận ${matrixValue}. Vui lòng giảm bớt.`;
                if (els.submitBtn) els.submitBtn.disabled = true;
            } else {
                els.rowSumWarning.classList.add('d-none');
                if (els.submitBtn) els.submitBtn.disabled = false;
            }
        } else {
            els.rowSumMax.textContent = '—';
            els.rowSumBar.style.width = '0%';
            els.rowSumWarning.classList.add('d-none');
            if (els.submitBtn) els.submitBtn.disabled = false;
        }
    }

    /* ── Events ─────────────────────────────────────────────────────── */
    function bindEvents() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Toggle Active status via AJAX
        els.toggleBtns.forEach(button => {
            button.addEventListener('change', async function () {
                const id = this.getAttribute('data-id');
                const isActive = this.checked;

                try {
                    const response = await fetch(`/admin/seat-layout-templates/${id}/toggle-active`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error('Cập nhật thất bại');
                } catch (error) {
                    console.error(error);
                    alert('Có lỗi xảy ra khi cập nhật trạng thái hoạt động.');
                    this.checked = !isActive;
                }
            });
        });

        // Matrix dropdown change → update info + row sum
        if (els.seatMatrix) {
            els.seatMatrix.addEventListener('change', function () {
                updateMatrixInfo(this.value);
            });
        }

        // Row input changes → update row sum
        els.seatRowInputs.forEach(input => {
            input.addEventListener('input', updateRowSum);
        });

        // Open Create Modal
        if (els.btnCreate) {
            els.btnCreate.addEventListener('click', () => {
                clearValidationErrors();
                resetForm();
                els.modalLabel.innerHTML = '<i class="bi bi-grid-3x3-gap me-2"></i>Tạo mẫu sơ đồ ghế mới';
                els.form.action = '/admin/seat-layout-templates';
                getModalInstance()?.show();
            });
        }

        // Open Edit Modal
        els.btnEdits.forEach(btn => {
            btn.addEventListener('click', function () {
                clearValidationErrors();

                const id          = this.getAttribute('data-id');
                const name        = this.getAttribute('data-template-name');
                const matrix      = this.getAttribute('data-seat-matrix');
                const regular     = this.getAttribute('data-regular-seats');
                const vip         = this.getAttribute('data-vip-seats');
                const couple      = this.getAttribute('data-couple-seats');
                const description = this.getAttribute('data-description');
                const status      = this.getAttribute('data-status') === '1';

                els.modalLabel.innerHTML    = '<i class="bi bi-grid-3x3-gap me-2"></i>Cập nhật mẫu sơ đồ ghế';
                els.form.action             = `/admin/seat-layout-templates/${id}`;
                els.formMethod.value        = 'PUT';
                els.idInput.value           = id;
                els.templateName.value      = name || '';
                els.regularSeatRows.value   = regular || '0';
                els.vipSeatRows.value       = vip     || '0';
                els.coupleSeatRows.value    = couple  || '0';
                els.description.value       = description || '';
                els.status.checked          = status;

                // Set matrix dropdown value and update info
                if (els.seatMatrix) {
                    els.seatMatrix.value = matrix || '';
                    updateMatrixInfo(matrix || '');
                }

                getModalInstance()?.show();
            });
        });
    }

    /* ── Validation Error Handling (Server-side redirect recovery) ── */
    function checkValidationErrors() {
        if (!els.form) return;
        const hasErrors = els.form.querySelector('.is-invalid') !== null;
        if (hasErrors) {
            const isEdit = els.formMethod.value === 'PUT';
            const id = els.idInput.value;

            if (isEdit && id) {
                els.modalLabel.innerHTML = '<i class="bi bi-grid-3x3-gap me-2"></i>Cập nhật mẫu sơ đồ ghế';
                els.form.action = `/admin/seat-layout-templates/${id}`;
            } else {
                els.modalLabel.innerHTML = '<i class="bi bi-grid-3x3-gap me-2"></i>Tạo mẫu sơ đồ ghế mới';
                els.form.action = '/admin/seat-layout-templates';
            }

            // Re-trigger matrix info on error reload
            if (els.seatMatrix?.value) {
                updateMatrixInfo(els.seatMatrix.value);
            }

            getModalInstance()?.show();
        }
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    function init() {
        bindEvents();
        checkValidationErrors();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cacheDoms();
        init();
    });

})();
