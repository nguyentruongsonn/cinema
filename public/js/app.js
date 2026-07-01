/**
 * Deprecated legacy frontend bundle.
 *
 * The application now uses the Blade View Shell + page-scoped modules:
 * - public/js/core/api-client.js
 * - public/js/auth.js
 * - public/js/pages/*.js
 *
 * This file is intentionally kept as a harmless compatibility stub so that
 * any accidental legacy <script src="/js/app.js"> include will not execute the
 * old duplicated frontend application, call stale endpoints, or register
 * duplicate event handlers.
 */
(function () {
    'use strict';

    if (window.console && typeof window.console.info === 'function') {
        window.console.info(
            '[Cinema] public/js/app.js is deprecated. Use api-client.js, auth.js, and page-specific modules instead.'
        );
    }
})();
