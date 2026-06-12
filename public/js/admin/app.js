/**
 * Admin Core JS
 * Chứa các utility functions dùng chung cho toàn bộ trang Admin.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Global Utilities – gán lên window để các page JS khác dùng được  */
    /* ------------------------------------------------------------------ */

    /**
     * Format số tiền theo chuẩn Việt Nam (VNĐ).
     * @param {number} amount
     * @returns {string}
     */
    window.formatCurrency = function (amount) {
        if (amount === null || amount === undefined) return '0₫';
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            maximumFractionDigits: 0
        }).format(amount);
    };

    /**
     * Format số nguyên dùng dấu phân cách kiểu Việt Nam.
     * @param {number} value
     * @returns {string}
     */
    window.formatNumber = function (value) {
        return new Intl.NumberFormat('vi-VN').format(value || 0);
    };

    /**
     * Hiển thị Bootstrap Toast thông báo.
     * @param {string} message
     * @param {'success'|'danger'|'warning'|'info'} type
     */
    window.showAdminToast = function (message, type = 'info') {
        const toastContainer = document.getElementById('adminToastContainer');
        if (!toastContainer) return;

        const id = 'toast-' + Date.now();
        const div = document.createElement('div');
        div.innerHTML = `
            <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`;
        
        const toastEl = div.firstElementChild;
        toastContainer.appendChild(toastEl);
        
        const bsToast = new bootstrap.Toast(toastEl, { delay: 3500 });
        bsToast.show();
        
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    };

    /* ------------------------------------------------------------------ */
    /*  Sidebar Mobile Toggle                                              */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.admin-sidebar');
        
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });

            // Đóng sidebar khi click ra ngoài (mobile)
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 992 && sidebar.classList.contains('show')) {
                    if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                        sidebar.classList.remove('show');
                    }
                }
            });
        }
    });

})();
