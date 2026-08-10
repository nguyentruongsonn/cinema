/**
 * pos-utils.js – Shared utilities for POS Kiosk
 * Includes: API client, formatters, toast, clock
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;

    // ── Token Management ──────────────────────────────────
    function getToken() {
        const name = 'pos_jwt_token';
        const cookies = document.cookie.split(';');
        for (const c of cookies) {
            const [k, v] = c.trim().split('=');
            if (k === name) return decodeURIComponent(v);
        }
        // Fallback: localStorage
        return localStorage.getItem('pos_access_token') || localStorage.getItem('access_token') || '';
    }

    // ── API Client ────────────────────────────────────────
    async function api(method, path, body = null, opts = {}, isRetry = false) {
        const url = path.startsWith('http') ? path : path;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': cfg.csrfToken,
        };
        const token = getToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const init = {
            ...opts,
            method,
            headers: {
                ...headers,
                ...(opts.headers || {}),
            },
        };
        if (body !== null) init.body = JSON.stringify(body);

        const res = await fetch(url, init);
        const json = await res.json().catch(() => ({ success: false, message: 'Lỗi parse response' }));

        if (!res.ok) {
            // Auto refresh token & retry once on 401 Unauthenticated
            if (res.status === 401 && !isRetry && !path.includes('/auth/refresh') && !path.includes('/auth/login')) {
                try {
                    const refreshRes = await fetch('/api/v1/auth/refresh', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                        }
                    });
                    if (refreshRes.ok) {
                        const refreshData = await refreshRes.json();
                        const newToken = refreshData.data?.access_token || refreshData.access_token || refreshData.token;
                        if (newToken) {
                            localStorage.setItem('pos_access_token', newToken);
                            document.cookie = `pos_jwt_token=${encodeURIComponent(newToken)}; path=/; max-age=86400`;
                        }
                        // Retry original request once with fresh token
                        return api(method, path, body, opts, true);
                    }
                } catch (e) {
                    console.warn('Auto refresh token failed:', e);
                }
            }

            const msg = json.message || json.error || `HTTP ${res.status}`;
            throw Object.assign(new Error(msg), { status: res.status, data: json });
        }
        return json;
    }

    function normalizeApiClientPath(path) {
        if (path.startsWith('/api/v1')) {
            return path.substring('/api/v1'.length);
        }
        return path;
    }

    const PosAPI = {
        get:    (path)        => typeof window.apiClient !== 'undefined'
            ? window.apiClient.get(normalizeApiClientPath(path), { cache: 'no-store' })
            : api('GET', path, null, { cache: 'no-store' }),
        post:   (path, body)  => typeof window.apiClient !== 'undefined' ? window.apiClient.post(normalizeApiClientPath(path), body) : api('POST',   path, body),
        put:    (path, body)  => typeof window.apiClient !== 'undefined' ? window.apiClient.put(normalizeApiClientPath(path), body) : api('PUT',    path, body),
        delete: (path)        => typeof window.apiClient !== 'undefined' ? window.apiClient.delete(normalizeApiClientPath(path)) : api('DELETE', path),
    };

    // ── Formatters ────────────────────────────────────────
    function formatVnd(amount) {
        if (amount === null || amount === undefined) return '0đ';
        return Number(amount).toLocaleString('vi-VN') + 'đ';
    }

    function formatDate(date) {
        const d = new Date(date);
        return d.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function formatTime(date) {
        const d = new Date(date);
        return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    }

    function phoneNormalize(phone) {
        return phone.replace(/\D/g, '').replace(/^84/, '0');
    }

    function initials(name) {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0][0].toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function renderEmptyState(container, message, icon = '⚠️') {
        if (!container) return;

        const wrapper = document.createElement('div');
        const iconElement = document.createElement('div');
        const textElement = document.createElement('div');

        wrapper.className = 'pos-empty';
        iconElement.className = 'pos-empty-icon';
        textElement.className = 'pos-empty-text';
        iconElement.textContent = icon;
        textElement.textContent = String(message ?? '');
        wrapper.append(iconElement, textElement);
        container.replaceChildren(wrapper);
    }

    // ── Toast ─────────────────────────────────────────────
    function toast(message, type = 'info', duration = 3500) {
        if (typeof window.showAdminToast === 'function') {
            window.showAdminToast(message, type);
            return;
        }
        
        // Fallback for isolated environment without admin shell
        const container = document.getElementById('toastContainer');
        if (!container) {
            console.log(`[Toast ${type}] ${message}`);
            return;
        }
        const el = document.createElement('div');
        const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
        el.className = `pos-toast ${type}`;
        const icon = document.createElement('span');
        icon.textContent = icons[type] || 'ℹ';
        const text = document.createElement('span');
        text.textContent = String(message ?? '');
        el.append(icon, text);
        container.appendChild(el);
        setTimeout(() => {
            el.style.animation = 'slideInToast 0.25s ease reverse forwards';
            setTimeout(() => el.remove(), 250);
        }, duration);
    }

    // ── Clock ─────────────────────────────────────────────
    function startClock() {
        const clockEl = document.getElementById('posClock');
        const dateEl  = document.getElementById('posDate');
        function tick() {
            const now = new Date();
            if (clockEl) clockEl.textContent = now.toLocaleTimeString('vi-VN', { hour12: false });
            if (dateEl)  dateEl.textContent  = now.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
        }
        tick();
        setInterval(tick, 1000);
    }

    // ── Expose ────────────────────────────────────────────
    global.PosUtils = { api: PosAPI, formatVnd, formatDate, formatTime, phoneNormalize, initials, escapeHtml, renderEmptyState, toast, startClock };

})(window);
