/**
 * Branches Management - branches.js
 * Pattern: IIFE, no global scope pollution
 */
(function () {
    'use strict';

    /* ── DOM cache ──────────────────────────────────────────────────── */
    const els = {};

    function cacheDoms() {
        els.toggleBtns = document.querySelectorAll('.toggle-active-btn');
    }

    /* ── Events ─────────────────────────────────────────────────────── */
    function bindEvents() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        els.toggleBtns.forEach(button => {
            button.addEventListener('change', async function() {
                const branchId = this.getAttribute('data-id');
                const isActive = this.checked;

                try {
                    // We don't use authManager here because this is a standard Web route (not API), 
                    // relying on session cookie authentication, but we still use fetch.
                    const response = await fetch(`/admin/branches/${branchId}/toggle-active`, {
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
                    // Revert toggle visually
                    this.checked = !isActive;
                }
            });
        });
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    function init() {
        bindEvents();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cacheDoms();
        init();
    });

})();
