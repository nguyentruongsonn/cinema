/**
 * Shared API client for the Blade View Shell + REST API /api/v1 architecture.
 *
 * Rules:
 * - All page scripts should call APIs through window.apiClient.
 * - Base URL comes from window.APP_CONFIG.apiUrl and defaults to /api/v1.
 * - Cookies/JWT-compatible credentials are included by default.
 * - CSRF token is automatically attached for unsafe methods.
 * - API errors are normalized into Error instances with status/data/errors fields.
 */
(function (window) {
    'use strict';

    const DEFAULT_BASE_URL = '/api/v1';
    const JSON_CONTENT_TYPE = 'application/json';

    function trimTrailingSlash(value) {
        return String(value || '').replace(/\/+$/, '');
    }

    function normalizePath(path) {
        if (!path) {
            return '';
        }

        const value = String(path);

        if (/^https?:\/\//i.test(value)) {
            return value;
        }

        return value.startsWith('/') ? value : `/${value}`;
    }

    function getBaseUrl() {
        return trimTrailingSlash(window.APP_CONFIG?.apiUrl || DEFAULT_BASE_URL);
    }

    function getCsrfToken() {
        return (
            window.APP_CONFIG?.csrfToken ||
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            ''
        );
    }

    function getBearerToken() {
        try {
            return window.localStorage?.getItem('auth_token') || '';
        } catch (error) {
            return '';
        }
    }

    function isFormData(value) {
        return typeof FormData !== 'undefined' && value instanceof FormData;
    }

    function isUnsafeMethod(method) {
        return !['GET', 'HEAD', 'OPTIONS'].includes(String(method || 'GET').toUpperCase());
    }

    async function parseResponseBody(response) {
        const contentType = response.headers.get('content-type') || '';

        if (response.status === 204) {
            return null;
        }

        if (contentType.includes(JSON_CONTENT_TYPE)) {
            return response.json();
        }

        const text = await response.text();
        return text ? { message: text } : null;
    }

    function getFirstValidationMessage(errors) {
        if (!errors || typeof errors !== 'object') {
            return null;
        }

        const firstError = Object.values(errors).find(Boolean);

        if (Array.isArray(firstError)) {
            return firstError[0] || null;
        }

        return typeof firstError === 'string' ? firstError : null;
    }

    function createApiError(message, response, data) {
        const validationMessage = getFirstValidationMessage(data?.errors);
        const error = new Error(validationMessage || message || `API request failed with status ${response.status}`);

        error.name = 'ApiError';
        error.status = response.status;
        error.response = response;
        error.data = data;
        error.errors = data?.errors || null;

        return error;
    }

    class ApiClient {
        constructor(options = {}) {
            this.baseUrl = trimTrailingSlash(options.baseUrl || getBaseUrl());
            this.defaultHeaders = {
                Accept: JSON_CONTENT_TYPE,
                ...(options.headers || {}),
            };
        }

        url(path) {
            const normalizedPath = normalizePath(path);

            if (/^https?:\/\//i.test(normalizedPath)) {
                return normalizedPath;
            }

            return `${this.baseUrl}${normalizedPath}`;
        }

        async request(path, options = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const headers = {
                ...this.defaultHeaders,
                ...(options.headers || {}),
            };

            const token = getBearerToken();
            if (token && !headers.Authorization) {
                headers.Authorization = `Bearer ${token}`;
            }

            if (isUnsafeMethod(method) && !headers['X-CSRF-TOKEN']) {
                const csrfToken = getCsrfToken();
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }
            }

            const requestOptions = {
                credentials: 'include',
                ...options,
                method,
                headers,
            };

            if (
                requestOptions.body &&
                typeof requestOptions.body === 'object' &&
                !isFormData(requestOptions.body) &&
                !(requestOptions.body instanceof Blob) &&
                !(requestOptions.body instanceof ArrayBuffer)
            ) {
                requestOptions.body = JSON.stringify(requestOptions.body);

                if (!requestOptions.headers['Content-Type']) {
                    requestOptions.headers['Content-Type'] = JSON_CONTENT_TYPE;
                }
            }

            if (isFormData(requestOptions.body)) {
                delete requestOptions.headers['Content-Type'];
            }

            const response = await fetch(this.url(path), requestOptions);
            const data = await parseResponseBody(response);

            if (!response.ok) {
                throw createApiError(data?.message, response, data);
            }

            return data;
        }

        get(path, options = {}) {
            return this.request(path, { ...options, method: 'GET' });
        }

        post(path, body = null, options = {}) {
            return this.request(path, { ...options, method: 'POST', body });
        }

        put(path, body = null, options = {}) {
            return this.request(path, { ...options, method: 'PUT', body });
        }

        patch(path, body = null, options = {}) {
            return this.request(path, { ...options, method: 'PATCH', body });
        }

        delete(path, options = {}) {
            return this.request(path, { ...options, method: 'DELETE' });
        }
    }

    window.ApiClient = ApiClient;
    window.apiClient = window.apiClient || new ApiClient();
})(window);
