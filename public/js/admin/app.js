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
     * Initialize API Client for Admin
     * Tạo window.api object để các trang admin sử dụng
     */
    if (window.apiClient && !window.api) {
        // Alias window.apiClient thành window.api để các trang admin dùng
        window.api = window.apiClient;
    }

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