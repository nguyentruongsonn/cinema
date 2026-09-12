(function() {
    'use strict';

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

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });

        overlay.addEventListener('click', function() {
            closeMenu();
        });

        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('mobile-show')) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    closeMenu();
                }
            }
        });

        const submenuLinks = sidebar.querySelectorAll('.submenu .nav-link');
        submenuLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    closeMenu();
                }
            });
        });


        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('mobile-show')) {
                    closeMenu();
                }
            }, 250);
        });

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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-show')) {
                closeMenu();
                menuToggle.focus();
            }
        });

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

        const icon = menuToggle.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-x-lg';
        }

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

        const icon = menuToggle.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-list';
        }
    }

    window.adminMenu = {
        open: openMenu,
        close: closeMenu,
        toggle: toggleMenu
    };
})();
