/**
 * Admin Core - Handles API requests and centralized logic for the Admin SPA.
 */

const AdminCore = {
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

        const config = {
            ...options,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {}),
            },
            credentials: 'include',
            cache: 'no-store',
        };

        try {
            const response = await fetch(url, config);

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

            return response;
        } catch (error) {
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
