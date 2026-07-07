/**
 * Mobile Search Toggle
 * Toggle search input visibility on mobile when clicking search button
 */

(function() {
    'use strict';

    // Only run on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }

    function initMobileSearchToggle() {
        if (!isMobile()) return;

        // Find all search forms and buttons in admin-filter-container
        const searchForms = document.querySelectorAll('.admin-filter-container form, .admin-filter-container #searchForm, .admin-filter-container .search-container-lg');
        
        searchForms.forEach(form => {
            const searchButton = form.querySelector('.admin-filter-btn, .search-btn-rounded-right, button[type="submit"]');
            const searchInput = form.querySelector('input[type="text"], #search, .admin-filter-input');
            
            if (searchButton && searchInput) {
                // Toggle search on button click
                searchButton.addEventListener('click', function(e) {
                    // If form is not active (input hidden), show it instead of submitting
                    if (!form.classList.contains('search-active')) {
                        e.preventDefault();
                        form.classList.add('search-active');
                        searchInput.focus();
                    }
                    // If form is active and has value, allow submit
                    // If form is active and empty, hide it
                    else if (!searchInput.value.trim()) {
                        e.preventDefault();
                        form.classList.remove('search-active');
                    }
                });

                // Hide search when clicking outside
                document.addEventListener('click', function(e) {
                    if (!form.contains(e.target)) {
                        form.classList.remove('search-active');
                    }
                });

                // Keep search open when typing
                searchInput.addEventListener('focus', function() {
                    form.classList.add('search-active');
                });
            }
        });
    }

    // Init on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileSearchToggle);
    } else {
        initMobileSearchToggle();
    }

    // Re-init on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initMobileSearchToggle();
        }, 250);
    });

})();