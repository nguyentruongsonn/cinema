/**
 * Admin Core - Handles API requests and centralized logic for the Admin SPA.
 */

const AdminCore = {
    _cachePrefix: 'admin_api_cache:',
    _pendingRequests: new Map(),
    _requestControllers: new Map(),

    paginationPages(meta, radius = 2) {
        const lastPage = Math.max(1, Number.parseInt(meta?.last_page, 10) || 1);
        const currentPage = Math.min(lastPage, Math.max(1, Number.parseInt(meta?.current_page, 10) || 1));

        if (lastPage <= 7) {
            return Array.from({ length: lastPage }, (_, index) => index + 1);
        }

        const pages = new Set([1, lastPage]);
        for (let page = currentPage - radius; page <= currentPage + radius; page++) {
            if (page > 1 && page < lastPage) pages.add(page);
        }

        return [...pages].sort((left, right) => left - right);
    },

    debounce(callback, wait = 300) {
        let timeoutId;

        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => callback.apply(this, args), wait);
        };
    },

    clearGetCache() {
        Object.keys(sessionStorage)
            .filter((key) => key.startsWith(this._cachePrefix))
            .forEach((key) => sessionStorage.removeItem(key));
    },

    _cacheScope() {
        const user = window.APP_CONFIG?.auth?.user;
        return `${user?.id ?? 'anonymous'}:${user?.role_id ?? 'no-role'}`;
    },

    _cacheStorageKey(key) {
        return `${this._cachePrefix}${this._cacheScope()}:${key}`;
    },

    abortAllRequests() {
        this._requestControllers.forEach((controller) => controller.abort());
        this._requestControllers.clear();
        this._pendingRequests.clear();
    },

    _cacheTtl(url) {
        const parsedUrl = new URL(url, window.location.origin);
        const pathname = parsedUrl.pathname;
        const referenceData = [
            '/api/v1/admin/users/roles',
            '/api/v1/admin/combos/available-products',
            '/api/v1/admin/promotions/categories',
            '/api/v1/admin/banners/positions',
        ];

        const isScreenReferencePayload = pathname === '/api/v1/admin/screens'
            && parsedUrl.searchParams.get('include_references') === '1';

        return referenceData.includes(pathname) || isScreenReferencePayload ? 300000 : 10000;
    },

    _readCachedResponse(key) {
        try {
            const storageKey = this._cacheStorageKey(key);
            const cached = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
            if (!cached || cached.expiresAt <= Date.now()) {
                sessionStorage.removeItem(storageKey);
                return null;
            }

            return new Response(cached.body, {
                status: cached.status,
                statusText: cached.statusText,
                headers: cached.headers,
            });
        } catch {
            return null;
        }
    },

    async _writeCachedResponse(key, response, ttl) {
        try {
            const clone = response.clone();
            sessionStorage.setItem(this._cacheStorageKey(key), JSON.stringify({
                body: await clone.text(),
                status: clone.status,
                statusText: clone.statusText,
                headers: [...clone.headers.entries()],
                expiresAt: Date.now() + ttl,
            }));
        } catch {
            // Cache storage is best-effort (private mode/quota may reject writes).
        }
    },

    /**
     * Base fetch wrapper for calling the API.
     * Since JWT is stored in an HttpOnly cookie ('access_token'), 
     * we must include credentials so the browser sends the cookie.
     */
    async apiFetch(url, options = {}) {
        // Nếu body là FormData thì không set Content-Type
        // (browser tự set multipart/form-data kèm boundary)
        const isFormData = options.body instanceof FormData;

        const defaultHeaders = {
            'Accept': 'application/json',
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        };

        const method = String(options.method || 'GET').toUpperCase();
        const normalizedUrl = new URL(String(url), window.location.origin).href;
        const requestKey = options.requestKey;
        const cacheTtl = options.cacheTtl ?? this._cacheTtl(url);
        const skipCache = options.skipCache === true;
        const fetchOptions = { ...options };
        delete fetchOptions.requestKey;
        delete fetchOptions.cacheTtl;
        delete fetchOptions.skipCache;

        if (requestKey) {
            this._requestControllers.get(requestKey)?.abort();
            this._pendingRequests.delete(normalizedUrl);
            const controller = new AbortController();
            this._requestControllers.set(requestKey, controller);
            fetchOptions.signal = controller.signal;
        }

        const config = {
            ...fetchOptions,
            method,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {}),
            },
            credentials: 'include',
            cache: 'default',
        };

        const cacheKey = method === 'GET' ? normalizedUrl : null;
        if (cacheKey && !skipCache) {
            const cachedResponse = this._readCachedResponse(cacheKey);
            if (cachedResponse) {
                if (requestKey && this._requestControllers.get(requestKey)?.signal === config.signal) {
                    this._requestControllers.delete(requestKey);
                }
                return cachedResponse;
            }
        }

        try {
            let request = cacheKey ? this._pendingRequests.get(cacheKey) : null;
            if (!request) {
                request = fetch(url, config);
                if (cacheKey) this._pendingRequests.set(cacheKey, request);
            }

            const response = (await request).clone();

            if (cacheKey) this._pendingRequests.delete(cacheKey);
            if (requestKey && this._requestControllers.get(requestKey)?.signal === config.signal) {
                this._requestControllers.delete(requestKey);
            }

            // Handle 401 Unauthorized (Token expired or missing)
            if (response.status === 401) {
                console.error('Unauthorized: JWT token expired or invalid.');
                // Optional: Try to refresh token here, or redirect to login
                window.location.href = '/login'; 
                return null;
            }

            // Handle 403 Forbidden
            if (response.status === 403) {
                console.error('Forbidden: User does not have the required role/permissions.');
                window.showAdminToast?.('Bạn không có quyền thực hiện thao tác này!', 'danger');
                return null;
            }

            if (cacheKey && response.ok && !skipCache && cacheTtl > 0) {
                void this._writeCachedResponse(cacheKey, response, cacheTtl);
            } else if (!cacheKey && response.ok) {
                this.clearGetCache();
            }

            return response;
        } catch (error) {
            if (cacheKey) this._pendingRequests.delete(cacheKey);
            if (requestKey && this._requestControllers.get(requestKey)?.signal === config.signal) {
                this._requestControllers.delete(requestKey);
            }
            if (error?.name === 'AbortError') throw error;
            console.error('API Fetch Error:', error);
            throw error;
        }
    },

    /**
     * Load current admin user data to render the navbar
     */
    async loadAdminProfile() {
        const res = await this.apiFetch('/api/v1/auth/me'); // Example route
        if (res && res.ok) {
            const data = await res.json();
            // Update UI with admin details...
            const userNameEl = document.getElementById('admin-user-name');
            if (userNameEl && data.user) {
                userNameEl.textContent = data.user.name;
            }
        }
    }
};

window.AdminCore = AdminCore;

window.onAdminPageLoad = function (callback) {
    if (window.Turbo) {
        if (window.__adminTurboLoadedUrl === window.location.href) {
            queueMicrotask(callback);
            return;
        }
        document.addEventListener('turbo:load', callback, { once: true });
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        queueMicrotask(callback);
    }
};

window.onAdminPageCleanup = function (callback) {
    if (window.Turbo) {
        document.addEventListener('turbo:before-cache', callback, { once: true });
    }
    window.addEventListener('pagehide', callback, { once: true });
};
