/**
 * Theaters Management - theaters.js
 * Pattern: IIFE, no global scope pollution
 */
(function () {
    'use strict';

    /* ── DOM cache ──────────────────────────────────────────────────── */
    const els = {};

    function cacheDoms() {
        els.toggleBtns = document.querySelectorAll('.toggle-active-btn');
        els.btnCreateTheater = document.getElementById('btnCreateTheater');
        els.btnEditTheaters = document.querySelectorAll('.btn-edit-theater');
        els.theaterModalEl = document.getElementById('theaterModal');
        els.theaterForm = document.getElementById('theaterForm');
        els.modalLabel = document.getElementById('theaterModalLabel');
        els.formMethod = document.getElementById('formMethod');
        els.theaterIdInput = document.getElementById('theaterIdInput');
        els.theaterName = document.getElementById('theaterName');
        els.theaterBranch = document.getElementById('theaterBranch');
        els.theaterAddress = document.getElementById('theaterAddress');
        els.theaterPhone = document.getElementById('theaterPhone');
        els.theaterEmail = document.getElementById('theaterEmail');
        els.theaterStatus = document.getElementById('theaterStatus');
    }

    /* ── Helpers ────────────────────────────────────────────────────── */
    let modalInstance = null;

    function getModalInstance() {
        if (!modalInstance && els.theaterModalEl) {
            modalInstance = new bootstrap.Modal(els.theaterModalEl);
        }
        return modalInstance;
    }

    function clearValidationErrors() {
        if (!els.theaterForm) return;
        els.theaterForm.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        els.theaterForm.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
    }

    /* ── Events ─────────────────────────────────────────────────────── */
    function bindEvents() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Toggle Active status via AJAX
        els.toggleBtns.forEach(button => {
            button.addEventListener('change', async function() {
                const theaterId = this.getAttribute('data-id');
                const isActive = this.checked;

                try {
                    const response = await fetch(`/admin/theaters/${theaterId}/toggle-active`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    });

                    const result = await response.json();
                    
                    if (!result.success) {
                        throw new Error('Cập nhật thất bại');
                    }
                } catch (error) {
                    console.error(error);
                    alert('Có lỗi xảy ra khi cập nhật trạng thái hoạt động.');
                    this.checked = !isActive;
                }
            });
        });

        // Open Create Modal
        if (els.btnCreateTheater) {
            els.btnCreateTheater.addEventListener('click', () => {
                clearValidationErrors();
                
                els.modalLabel.textContent = 'Thêm rạp chiếu mới';
                els.theaterForm.action = '/admin/theaters';
                els.formMethod.value = 'POST';
                els.theaterIdInput.value = '';
                
                els.theaterName.value = '';
                els.theaterBranch.value = '';
                els.theaterAddress.value = '';
                els.theaterPhone.value = '';
                els.theaterEmail.value = '';
                els.theaterStatus.checked = true;

                getModalInstance()?.show();
            });
        }

        // Open Edit Modal
        els.btnEditTheaters.forEach(btn => {
            btn.addEventListener('click', function() {
                clearValidationErrors();

                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const branchId = this.getAttribute('data-branch-id');
                const address = this.getAttribute('data-address');
                const phone = this.getAttribute('data-phone');
                const email = this.getAttribute('data-email');
                const status = this.getAttribute('data-status') === '1';

                els.modalLabel.textContent = 'Cập nhật rạp chiếu';
                els.theaterForm.action = `/admin/theaters/${id}`;
                els.formMethod.value = 'PUT';
                els.theaterIdInput.value = id;

                els.theaterName.value = name || '';
                els.theaterBranch.value = branchId || '';
                els.theaterAddress.value = address || '';
                els.theaterPhone.value = phone || '';
                els.theaterEmail.value = email || '';
                els.theaterStatus.checked = status;

                getModalInstance()?.show();
            });
        });
    }

    /* ── Validation Error Handling (Server-side redirect recovery) ── */
    function checkValidationErrors() {
        if (!els.theaterForm) return;
        const hasErrors = els.theaterForm.querySelector('.is-invalid') !== null;
        if (hasErrors) {
            const isEdit = els.formMethod.value === 'PUT';
            const theaterId = els.theaterIdInput.value;

            if (isEdit && theaterId) {
                els.modalLabel.textContent = 'Cập nhật rạp chiếu';
                els.theaterForm.action = `/admin/theaters/${theaterId}`;
            } else {
                els.modalLabel.textContent = 'Thêm rạp chiếu mới';
                els.theaterForm.action = '/admin/theaters';
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
