/**
 * Seat Layout Templates Management - seat-layout-templates.js
 * Pattern: IIFE, no global scope pollution
 */
(function () {
    'use strict';

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
        els.customMatrix    = document.getElementById('customMatrix');
        els.description     = document.getElementById('description');
        els.status          = document.getElementById('templateStatus');
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
        els.form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
        });
    }

    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        if (els.status) els.status.checked = true;
        els.formMethod.value = 'POST';
        els.idInput.value = '';
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
                els.seatMatrix.value        = matrix || '';
                els.regularSeatRows.value   = regular || '0';
                els.vipSeatRows.value       = vip || '0';
                els.coupleSeatRows.value    = couple || '0';
                els.customMatrix.value      = '';
                els.description.value       = description || '';
                els.status.checked          = status;

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
