/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ADMIN RESPONSIVE MENU
 * Mobile hamburger menu functionality for admin panel
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initResponsiveMenu);
    } else {
        initResponsiveMenu();
    }

    function initResponsiveMenu() {
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (!menuToggle || !sidebar || !overlay) {
            console.warn('Responsive menu: Required elements not found');
            return;
        }

        // Toggle menu on button click
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });

        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            closeMenu();
        });

        // Close menu when clicking backdrop (outside sidebar)
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('mobile-show')) {
                // Check if click is outside sidebar
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    closeMenu();
                }
            }
        });

        // Close menu when clicking submenu links (actual pages), not parent toggles
        const submenuLinks = sidebar.querySelectorAll('.submenu .nav-link');
        submenuLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Only auto-close on mobile/tablet when clicking submenu items (actual pages)
                if (window.innerWidth <= 1024) {
                    closeMenu();
                }
            });
        });

        // Don't close when clicking parent menu items (they just toggle submenus)
        // Bootstrap collapse handles the submenu toggle, we just keep sidebar open

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Close menu if resizing to desktop
                if (window.innerWidth > 1024 && sidebar.classList.contains('mobile-show')) {
                    closeMenu();
                }
            }, 250);
        });

        // Prevent body scroll when menu is open
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (sidebar.classList.contains('mobile-show')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                }
            });
        });

        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-show')) {
                closeMenu();
                menuToggle.focus();
            }
        });

        // Trap focus inside sidebar when open
        sidebar.addEventListener('keydown', function(e) {
            if (!sidebar.classList.contains('mobile-show')) return;

            if (e.key === 'Tab') {
                const focusableElements = sidebar.querySelectorAll(
                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );

                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];

                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        });
    }

    function toggleMenu() {
        const sidebar = document.querySelector('.admin-sidebar');
        const isOpen = sidebar.classList.contains('mobile-show');

        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    function openMenu() {
        const sidebar = document.querySelector('.admin-sidebar');
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.sidebar-overlay');

        sidebar.classList.add('mobile-show');
        overlay.classList.add('active');
        menuToggle.setAttribute('aria-expanded', 'true');
        menuToggle.setAttribute('aria-label', 'Close menu');

        // Change icon to X
        const icon = menuToggle.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-x-lg';
        }

        // Focus first link in sidebar
        setTimeout(function() {
            const firstLink = sidebar.querySelector('.sidebar-nav .nav-link');
            if (firstLink) {
                firstLink.focus();
            }
        }, 100);
    }

    function closeMenu() {
        const sidebar = document.querySelector('.admin-sidebar');
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.sidebar-overlay');

        sidebar.classList.remove('mobile-show');
        overlay.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open menu');

        // Change icon back to hamburger
        const icon = menuToggle.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-list';
        }
    }

    // Expose functions globally for debugging
    window.adminMenu = {
        open: openMenu,
        close: closeMenu,
        toggle: toggleMenu
    };
})();
