/**
 * Mobile Search Toggle
 * Toggle search input visibility on mobile when clicking search button
 */

(function() {
    'use strict';

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function searchFormFor(element) {
        return element?.closest('.admin-filter-container form, .admin-filter-container .search-container-lg');
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.admin-filter-btn, .search-btn-rounded-right, button[type="submit"]');
        const form = searchFormFor(button);

        if (isMobile() && form) {
            const input = form.querySelector('input[type="text"], #search, .admin-filter-input');
            if (input && !form.classList.contains('search-active')) {
                event.preventDefault();
                form.classList.add('search-active');
                input.focus();
                return;
            }

            if (input && !input.value.trim()) {
                event.preventDefault();
                form.classList.remove('search-active');
                return;
            }
        }

        document.querySelectorAll('.admin-filter-container .search-active').forEach((activeForm) => {
            if (!activeForm.contains(event.target)) activeForm.classList.remove('search-active');
        });
    });

    document.addEventListener('focusin', function (event) {
        const form = searchFormFor(event.target);
        if (isMobile() && form && event.target.matches('input[type="text"], #search, .admin-filter-input')) {
            form.classList.add('search-active');
        }
    });

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (!isMobile()) {
                document.querySelectorAll('.admin-filter-container .search-active')
                    .forEach((form) => form.classList.remove('search-active'));
            }
        }, 250);
    });

})();
