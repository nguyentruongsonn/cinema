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
     * Hiển thị Custom Toast thông báo cao cấp (Premium Toast).
     * @param {string} message
     * @param {'success'|'danger'|'warning'|'info'|'error'} type
     */
    window.showAdminToast = function (message, type = 'info') {
        const toastContainer = document.getElementById('adminToastContainer');
        if (!toastContainer) return;

        // Đồng bộ class của container để áp dụng đúng CSS grid & positioning từ alerts.css
        if (!toastContainer.classList.contains('admin-toast-container')) {
            toastContainer.className = 'admin-toast-container';
            toastContainer.removeAttribute('style');
        }

        const id = 'toast-' + Date.now();
        const duration = 4000; // 4 giây hiển thị

        // Bản đồ icon Bootstrap
        const icons = {
            success: 'bi-check-circle-fill',
            danger: 'bi-x-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        const iconClass = icons[type] || icons.info;

        // Bản đồ tiêu đề
        const titles = {
            success: 'Thành công',
            danger: 'Lỗi nghiêm trọng',
            error: 'Lỗi',
            warning: 'Cảnh báo',
            info: 'Thông báo'
        };
        const titleText = titles[type] || titles.info;

        // Đổi type 'error' thành 'danger' để khớp class CSS trong alerts.css (.admin-toast-danger / .admin-toast-error)
        const cssType = type === 'error' ? 'error' : type;

        const div = document.createElement('div');
        div.innerHTML = `
            <div id="${id}" class="admin-toast admin-toast-${cssType}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="admin-toast-icon">
                    <i class="bi ${iconClass}"></i>
                </div>
                <div class="admin-toast-content">
                    <div class="admin-toast-title">${titleText}</div>
                    <div class="admin-toast-message">${message}</div>
                </div>
                <div class="admin-toast-close" aria-label="Đóng">
                    <i class="bi bi-x-lg"></i>
                </div>
                <div class="admin-toast-progress">
                    <div class="admin-toast-progress-bar" style="width: 100%;"></div>
                </div>
            </div>`;
        
        const toastEl = div.firstElementChild;
        toastContainer.appendChild(toastEl);

        const progressBar = toastEl.querySelector('.admin-toast-progress-bar');
        if (progressBar) {
            // Thiết lập màu sắc thừa hưởng từ color của lớp cha (currentColor)
            progressBar.style.transition = `width ${duration}ms linear`;
            // Buộc trình duyệt reflow để kích hoạt transition
            progressBar.offsetHeight;
            progressBar.style.width = '0%';
        }

        // Tự động đóng toast sau 4 giây
        const autoCloseTimeout = setTimeout(() => {
            closeToast();
        }, duration);

        // Đóng thủ công khi bấm nút X
        const closeBtn = toastEl.querySelector('.admin-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                clearTimeout(autoCloseTimeout);
                closeToast();
            });
        }

        function closeToast() {
            toastEl.classList.add('hiding');
            toastEl.addEventListener('animationend', (e) => {
                // Chỉ xóa khi hoàn tất hiệu ứng slide-out (toast-slide-out)
                if (e.animationName === 'toast-slide-out') {
                    toastEl.remove();
                }
            });
            // Fallback phòng khi animationend không kích hoạt
            setTimeout(() => {
                if (toastEl.parentNode) {
                    toastEl.remove();
                }
            }, 350);
        }
    };

    /* ------------------------------------------------------------------ */
    /*  Sidebar Management                                                */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.querySelector('.admin-sidebar');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        const desktopToggleBtn = document.getElementById('sidebarCollapseToggle');
        const mobileToggleBtn = document.getElementById('sidebarMobileToggle');
        
        // Storage key for collapse state
        const COLLAPSE_STATE_KEY = 'admin_sidebar_collapsed';

        /* ------------------------------------------------------------------ */
        /*  Desktop Collapse/Expand Toggle                                    */
        /* ------------------------------------------------------------------ */
        if (desktopToggleBtn && sidebar) {
            // Restore saved collapse state (desktop only)
            const savedState = localStorage.getItem(COLLAPSE_STATE_KEY);
            if (savedState === 'true' && window.innerWidth >= 992) {
                sidebar.classList.add('collapsed');
                desktopToggleBtn.setAttribute('aria-expanded', 'false');
            }

            // Desktop toggle click handler
            desktopToggleBtn.addEventListener('click', function () {
                const isCollapsed = sidebar.classList.toggle('collapsed');
                
                // Update ARIA attribute
                desktopToggleBtn.setAttribute('aria-expanded', !isCollapsed);
                
                // Save state to localStorage
                localStorage.setItem(COLLAPSE_STATE_KEY, isCollapsed);
                
                // Re-initialize tooltips after collapse state changes
                setTimeout(initializeTooltips, 300);
            });
        }

        /* ------------------------------------------------------------------ */
        /*  Mobile Menu Toggle (Overlay/Drawer)                               */
        /* ------------------------------------------------------------------ */
        if (mobileToggleBtn && sidebar && sidebarOverlay) {
            // Mobile toggle button
            mobileToggleBtn.addEventListener('click', function () {
                sidebar.classList.add('show');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });

            // Close on overlay click
            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });

            // Close on escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // Close sidebar when clicking nav links on mobile
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    // Only close if it's not a submenu toggle
                    if (!this.hasAttribute('data-bs-toggle') && window.innerWidth < 992) {
                        setTimeout(() => {
                            sidebar.classList.remove('show');
                            sidebarOverlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }, 200);
                    }
                });
            });
        }

        /* ------------------------------------------------------------------ */
        /*  Tooltip Initialization (for collapsed sidebar)                    */
        /* ------------------------------------------------------------------ */
        function initializeTooltips() {
            // Dispose existing tooltips first
            const existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            existingTooltips.forEach(el => {
                const tooltip = bootstrap.Tooltip.getInstance(el);
                if (tooltip) {
                    tooltip.dispose();
                }
            });

            // Only initialize tooltips when sidebar is collapsed (desktop only)
            if (sidebar && sidebar.classList.contains('collapsed') && window.innerWidth >= 992) {
                const tooltipTriggerList = document.querySelectorAll('.admin-sidebar [data-bs-toggle="tooltip"]');
                [...tooltipTriggerList].map(tooltipTriggerEl => {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        trigger: 'hover',
                        container: 'body',
                        boundary: 'window',
                        offset: [0, 8]
                    });
                });
            }
        }

        // Initialize tooltips on load
        initializeTooltips();

        // Re-initialize tooltips on window resize
        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                // Remove collapsed class on mobile
                if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('collapsed')) {
                    sidebar.classList.remove('collapsed');
                }
                // Remove show class on desktop
                if (window.innerWidth >= 992 && sidebar && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
                initializeTooltips();
            }, 250);
        });

        /* ------------------------------------------------------------------ */
        /*  Submenu Behavior in Collapsed State                               */
        /* ------------------------------------------------------------------ */
        if (sidebar) {
            const submenuToggles = sidebar.querySelectorAll('.nav-link[data-bs-toggle="collapse"]');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function (e) {
                    // In collapsed state, prevent default collapse behavior
                    if (sidebar.classList.contains('collapsed') && window.innerWidth >= 992) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Could implement flyout menu here if needed
                        // For now, just prevent the collapse from opening
                    }
                });
            });
        }

        /* ------------------------------------------------------------------ */
        /*  Keyboard Navigation Support                                        */
        /* ------------------------------------------------------------------ */
        if (sidebar) {
            const navLinks = sidebar.querySelectorAll('.nav-link');
            
            navLinks.forEach((link, index) => {
                link.addEventListener('keydown', function (e) {
                    // Arrow down - focus next link
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const nextLink = navLinks[index + 1];
                        if (nextLink) nextLink.focus();
                    }
                    
                    // Arrow up - focus previous link
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const prevLink = navLinks[index - 1];
                        if (prevLink) prevLink.focus();
                    }
                    
                    // Home - focus first link
                    if (e.key === 'Home') {
                        e.preventDefault();
                        navLinks[0].focus();
                    }
                    
                    // End - focus last link
                    if (e.key === 'End') {
                        e.preventDefault();
                        navLinks[navLinks.length - 1].focus();
                    }
                });
            });

            // Keyboard shortcut: Ctrl+B to toggle sidebar (desktop only)
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'b' && window.innerWidth >= 992) {
                    e.preventDefault();
                    if (desktopToggleBtn) {
                        desktopToggleBtn.click();
                    }
                }
            });
        }

        /* ------------------------------------------------------------------ */
        /*  Active State Management                                            */
        /* ------------------------------------------------------------------ */
        // Ensure parent menus are expanded if child is active
        const activeLinks = sidebar?.querySelectorAll('.nav-link.active');
        activeLinks?.forEach(activeLink => {
            const parentCollapse = activeLink.closest('.collapse');
            if (parentCollapse) {
                const bsCollapse = new bootstrap.Collapse(parentCollapse, { toggle: false });
                bsCollapse.show();
            }
        });
    });

})();