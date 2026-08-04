/**
 * Admin Core JS
 * Chứa các utility functions dùng chung cho toàn bộ trang Admin.
 */
(function () {
    'use strict';

    async function handleAdminLogout() {
        try {
            await window.AdminCore?.apiFetch?.('/api/v1/auth/logout', {
                method: 'POST',
                skipCache: true,
            });
            window.showAdminToast?.('Đã đăng xuất.', 'info');
        } catch (error) {
            console.error('Admin logout error:', error);
        } finally {
            window.location.replace('/');
        }
    }

    document.addEventListener('click', (event) => {
        const logoutButton = event.target.closest?.('[data-admin-logout]');
        if (!logoutButton) return;

        event.preventDefault();
        handleAdminLogout();
    });

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

    document.addEventListener('error', function (event) {
        const image = event.target.closest?.('img[data-admin-image-fallback]');
        if (!image) return;
        const icon = document.createElement('i');
        icon.className = `bi ${image.dataset.adminImageFallback || 'bi-image'} text-white-50 fs-3`;
        image.replaceWith(icon);
    }, true);

    window.formatAdminErrors = function (errors, fallback = 'Dữ liệu không hợp lệ') {
        if (!errors) return fallback;
        if (typeof errors === 'string') return errors;

        if (Array.isArray(errors)) {
            return errors.filter(Boolean).join(' ');
        }

        if (typeof errors === 'object') {
            const messages = Object.values(errors)
                .flatMap((value) => Array.isArray(value) ? value : [value])
                .filter(Boolean);

            return messages.length ? messages.join(' ') : fallback;
        }

        return fallback;
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

        const toastEl = document.createElement('div');
        toastEl.id = id;
        toastEl.className = `admin-toast admin-toast-${cssType}`;
        toastEl.setAttribute('role', type === 'warning' || type === 'info' ? 'status' : 'alert');
        toastEl.setAttribute('aria-live', type === 'warning' || type === 'info' ? 'polite' : 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        const iconWrap = document.createElement('div');
        iconWrap.className = 'admin-toast-icon';
        const icon = document.createElement('i');
        icon.className = `bi ${iconClass}`;
        icon.setAttribute('aria-hidden', 'true');
        iconWrap.appendChild(icon);

        const content = document.createElement('div');
        content.className = 'admin-toast-content';
        const title = document.createElement('div');
        title.className = 'admin-toast-title';
        title.textContent = titleText;
        const messageElement = document.createElement('div');
        messageElement.className = 'admin-toast-message';
        messageElement.textContent = String(message ?? '');
        content.append(title, messageElement);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'admin-toast-close';
        closeButton.setAttribute('aria-label', 'Đóng thông báo');
        closeButton.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

        const progress = document.createElement('div');
        progress.className = 'admin-toast-progress';
        const progressBar = document.createElement('div');
        progressBar.className = 'admin-toast-progress-bar';
        progress.appendChild(progressBar);

        toastEl.append(iconWrap, content, closeButton, progress);
        toastContainer.appendChild(toastEl);

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
        closeButton.addEventListener('click', () => {
            clearTimeout(autoCloseTimeout);
            closeToast();
        });

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
