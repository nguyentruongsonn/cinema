/**
 * Authentication Module
 * Handles login, register, logout, token management, and Google OAuth
 */

class AuthManager {
    constructor() {
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api';
        this.token = this.getToken();
        this.user = null;
        this.modal = null;
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.modal = new bootstrap.Modal(document.getElementById('authModal'));
            this.setupEventListeners();
            this.checkAuthStatus();
        });
    }

    setupEventListeners() {
        // Login button click
        document.querySelectorAll('[data-auth-action="login"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.showModal('login');
            });
        });

        // Login form submit
        const loginForm = document.getElementById('loginFormElement');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Register form submit
        const registerForm = document.getElementById('registerFormElement');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Google login
        const googleBtn = document.getElementById('googleLoginBtn');
        if (googleBtn) {
            googleBtn.addEventListener('click', () => this.handleGoogleLogin());
        }

        // Toggle password visibility
        document.querySelectorAll('.cinema-auth-toggle-password').forEach(btn => {
            btn.addEventListener('click', (e) => this.togglePassword(e));
        });

        // Auth required mode buttons
        const showLoginFormBtn = document.getElementById('showLoginFormBtn');
        const showRegisterFormBtn = document.getElementById('showRegisterFormBtn');

        if (showLoginFormBtn) {
            showLoginFormBtn.addEventListener('click', () => this.showAuthForms('login'));
        }

        if (showRegisterFormBtn) {
            showRegisterFormBtn.addEventListener('click', () => this.showAuthForms('register'));
        }

        // Logout
        document.querySelectorAll('[data-auth-action="logout"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleLogout();
            });
        });

        // Tab switch - clear errors
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', () => {
                this.clearErrors();
            });
        });
    }

    async handleLogin(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = document.getElementById('loginSubmitBtn');
        const spinner = submitBtn.querySelector('.spinner-border');
        const btnText = submitBtn.querySelector('.btn-text');

        this.clearErrors('login');

        const formData = new FormData(form);
        const data = {
            login: formData.get('login'),
            password: formData.get('password'),
            remember: formData.get('remember') === 'on'
        };

        // Client-side validation
        if (!data.login || !data.password) {
            this.showAlert('login', 'Vui lòng nhập đầy đủ thông tin');
            return;
        }

        try {
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            btnText.textContent = 'Đang xử lý...';

            const response = await this.fetchAPI('/auth/login', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (response.success) {
                this.saveToken(response.data.access_token);
                this.user = response.data.user;
                this.modal.hide();
                this.updateUI();
                this.showToast('Đăng nhập thành công!', 'success');

                // Reload if needed
                setTimeout(() => window.location.reload(), 500);
            } else {
                this.showAlert('login', response.message || 'Đăng nhập thất bại');
            }
        } catch (error) {
            this.handleError(error, 'login');
        } finally {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
            btnText.textContent = 'Đăng nhập';
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = document.getElementById('registerSubmitBtn');
        const spinner = submitBtn.querySelector('.spinner-border');
        const btnText = submitBtn.querySelector('.btn-text');

        this.clearErrors('register');

        const formData = new FormData(form);
        const data = {
            name: formData.get('name'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            password: formData.get('password'),
            password_confirmation: formData.get('password_confirmation'),
            terms: formData.get('terms') === 'on'
        };

        // Client-side validation
        if (!data.name || !data.email || !data.password || !data.password_confirmation) {
            this.showAlert('register', 'Vui lòng nhập đầy đủ thông tin bắt buộc');
            return;
        }

        if (data.password !== data.password_confirmation) {
            this.showFieldError('regPasswordConfirmation', 'Mật khẩu xác nhận không khớp');
            return;
        }

        if (!data.terms) {
            this.showFieldError('regTerms', 'Bạn cần đồng ý điều khoản sử dụng');
            return;
        }

        try {
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            btnText.textContent = 'Đang xử lý...';

            const response = await this.fetchAPI('/auth/register', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (response.success) {
                this.saveToken(response.data.access_token);
                this.user = response.data.user;
                this.modal.hide();
                this.updateUI();
                this.showToast('Đăng ký thành công!', 'success');

                setTimeout(() => window.location.reload(), 500);
            } else {
                this.showAlert('register', response.message || 'Đăng ký thất bại');
            }
        } catch (error) {
            this.handleError(error, 'register');
        } finally {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
            btnText.textContent = 'Đăng ký';
        }
    }

    async handleGoogleLogin() {
        // This requires Google Sign-In library
        // For now, show a placeholder message
        this.showToast('Tính năng đăng nhập Google đang được phát triển', 'info');

        // TODO: Implement Google OAuth flow
        // 1. Load Google Sign-In library
        // 2. Get ID token
        // 3. Send to backend /auth/google endpoint
    }

    async handleLogout() {
        try {
            await this.fetchAPI('/auth/logout', { method: 'POST' });
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.clearToken();
            this.user = null;
            this.updateUI();
            this.showToast('Đã đăng xuất', 'info');
            setTimeout(() => window.location.href = '/', 500);
        }
    }

    async checkAuthStatus() {
        if (!this.token) {
            this.updateUI();
            return;
        }

        try {
            const response = await this.fetchAPI('/auth/me');
            if (response.success) {
                this.user = response.data;
                this.updateUI();
            } else {
                this.clearToken();
                this.updateUI();
            }
        } catch (error) {
            console.error('Check auth status error:', error);
            this.clearToken();
            this.updateUI();
        }
    }

    async fetchAPI(endpoint, options = {}) {
        const url = `${this.apiUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const config = {
            ...options,
            headers: {
                ...headers,
                ...options.headers
            },
            credentials: 'include' // Send cookies (refresh token)
        };

        let response = await fetch(url, config);
        let data = await response.json();

        // Handle 401 - try to refresh access token
        if (response.status === 401 && !endpoint.includes('/auth/refresh')) {
            const refreshed = await this.refreshAccessToken();
            if (refreshed) {
                // Retry original request with new token
                headers['Authorization'] = `Bearer ${this.token}`;
                config.headers = { ...headers, ...options.headers };
                response = await fetch(url, config);
                data = await response.json();
            } else {
                this.clearToken();
                this.updateUI();
                throw new Error('Session expired. Please login again.');
            }
        }

        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    }

    async refreshAccessToken() {
        try {
            const response = await fetch(`${this.apiUrl}/auth/refresh`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include' // Send refresh token cookie
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.access_token) {
                    this.saveToken(data.data.access_token);
                    this.user = data.data.user;
                    return true;
                }
            }
            return false;
        } catch (error) {
            console.error('Token refresh failed:', error);
            return false;
        }
    }

    handleError(error, formType) {
        console.error('Auth error:', error);

        if (error.message.includes('validation') || error.message.includes('errors')) {
            // Handle validation errors
            try {
                const errors = JSON.parse(error.message);
                Object.keys(errors).forEach(field => {
                    this.showFieldError(field, errors[field][0]);
                });
            } catch {
                this.showAlert(formType, error.message);
            }
        } else {
            this.showAlert(formType, error.message || 'Đã xảy ra lỗi. Vui lòng thử lại.');
        }
    }

    showAlert(formType, message) {
        const alertId = formType === 'login' ? 'loginAlert' : 'registerAlert';
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.textContent = message;
            alert.classList.remove('d-none');
        }
    }

    showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(`${fieldId}Error`);

        if (field) {
            field.classList.add('is-invalid');
        }
        if (errorDiv) {
            errorDiv.textContent = message;
        }
    }

    clearErrors(formType = null) {
        const forms = formType ? [formType] : ['login', 'register'];

        forms.forEach(type => {
            const alertId = type === 'login' ? 'loginAlert' : 'registerAlert';
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.classList.add('d-none');
                alert.textContent = '';
            }

            const form = document.getElementById(`${type}FormElement`);
            if (form) {
                form.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
                form.querySelectorAll('.invalid-feedback').forEach(el => {
                    el.textContent = '';
                });
            }
        });
    }

    togglePassword(e) {
        const btn = e.currentTarget;
        const targetId = btn.dataset.target;
        const input = document.querySelector(targetId);
        const icon = btn.querySelector('i');

        if (input && icon) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    }

    showModal(tab = 'login') {
        this.showAuthForms(tab);
        this.clearErrors();
        this.modal.show();
    }

    showAuthRequired() {
        // Khi người dùng chưa đăng nhập vào trang đặt vé, hiển thị trực tiếp form
        // đăng nhập đầy đủ thay vì màn hình trung gian chỉ có 2 nút Đăng nhập/Đăng ký.
        this.showAuthForms('login');
        this.clearErrors();
        this.modal.show();
    }

    showAuthForms(tab = 'login') {
        const modal = document.getElementById('authModal');
        const authRequiredSection = modal?.querySelector('.auth-required-section');
        const authFormsSection = modal?.querySelector('.auth-forms-section');

        if (authRequiredSection && authFormsSection) {
            authRequiredSection.classList.add('d-none');
            authFormsSection.classList.remove('d-none');
        }

        if (tab === 'register') {
            document.getElementById('register-tab')?.click();
        } else {
            document.getElementById('login-tab')?.click();
        }
    }

    updateUI() {
        const loginBtn = document.querySelector('[data-auth-action="login"]');
        const userDropdown = document.getElementById('userDropdown');

        if (this.user) {
            // User is logged in
            if (loginBtn) loginBtn.classList.add('d-none');
            if (userDropdown) {
                userDropdown.classList.remove('d-none');
                const userName = userDropdown.querySelector('.user-name');
                if (userName) userName.textContent = this.user.name;
            }
        } else {
            // User is not logged in
            if (loginBtn) loginBtn.classList.remove('d-none');
            if (userDropdown) userDropdown.classList.add('d-none');
        }
    }

    showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow-lg`;
        toast.style.zIndex = '9999';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    saveToken(token) {
        localStorage.setItem('auth_token', token);
        this.token = token;
    }

    getToken() {
        return localStorage.getItem('auth_token');
    }

    clearToken() {
        localStorage.removeItem('auth_token');
        this.token = null;
    }

    isAuthenticated() {
        return !!this.token;
    }

    getUser() {
        return this.user;
    }
}

// Initialize auth manager
window.authManager = new AuthManager();
