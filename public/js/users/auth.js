/**
 * Authentication Module
 * Handles login, register, logout, token management, and Google OAuth
 */

class AuthManager {
    constructor() {
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        const ssrAuth = window.APP_CONFIG?.auth || {};
        this.user = ssrAuth.authenticated ? ssrAuth.user : null;
        this.modal = null;
        this.isCheckingAuth = false;
        this.authCheckPromise = null;
        this.authChecked = !!ssrAuth.checked;
        this.isRefreshing = false;
        this.refreshPromise = null;
        this.hadAuthenticatedSession = !!ssrAuth.authenticated;
        this.sessionExpired = false;
        this.sessionExpiredNotified = false;
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            const modalEl = document.getElementById('authModal');
            if (modalEl) {
                this.ensureModal();
            }
            this.setupEventListeners();
            if (this.authChecked) {
                this.updateUI();
            } else {
                this.checkAuthStatus();
            }
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

        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const backToLoginBtn = document.getElementById('backToLoginBtn');
        const forgotPasswordForm = document.getElementById('forgotPasswordFormElement');
        forgotPasswordLink?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showForgotPasswordPanel();
        });
        backToLoginBtn?.addEventListener('click', () => this.showLoginPanel());
        forgotPasswordForm?.addEventListener('submit', (e) => this.handleForgotPassword(e));

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
                body: data
            });

            if (response.success) {
                // Cookies are automatically set by server (HttpOnly)
                this.setAuthenticatedUser(response.data.user);
                this.modal.hide();
                this.showToast('Đăng nhập thành công!', 'success');

                // Redirect if provided by backend, else reload current page
                const redirectUrl = response.data.redirect_url || '/';
                setTimeout(() => {
                    if (redirectUrl === window.location.pathname) {
                        window.location.reload();
                    } else {
                        window.location.href = redirectUrl;
                    }
                }, 300);
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
                body: data
            });

            if (response.success) {
                // Cookies are automatically set by server (HttpOnly)
                this.setAuthenticatedUser(response.data.user);
                this.modal.hide();
                this.showToast('Đăng ký thành công!', 'success');

                // Reload page immediately to trigger SSR with new auth state
                setTimeout(() => window.location.reload(), 300);
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

    async handleForgotPassword(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = document.getElementById('forgotPasswordSubmitBtn');
        const email = String(new FormData(form).get('email') || '').trim();

        this.clearForgotPasswordState();
        if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
            this.showFieldError('forgotPasswordEmail', 'Vui lòng nhập email hợp lệ');
            return;
        }

        try {
            this.setButtonLoading(submitBtn, true, 'Đang gửi...');
            await this.fetchAPI('/auth/forgot-password', {
                method: 'POST',
                body: { email },
                skipRefresh: true,
            });
            form.reset();
            this.showForgotPasswordAlert('Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi. Vui lòng kiểm tra hộp thư và thư rác.', 'success');
        } catch (error) {
            this.showForgotPasswordAlert(error?.message || 'Không thể gửi liên kết. Vui lòng thử lại sau.', 'danger');
        } finally {
            this.setButtonLoading(submitBtn, false, 'Gửi liên kết đặt lại');
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
            await this.fetchAPI('/auth/logout', {
                method: 'POST',
                skipRefresh: true,
            });
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            // Cookies are cleared by server; never try to refresh after logout.
            this.user = null;
            this.authChecked = true;
            this.hadAuthenticatedSession = false;
            this.sessionExpired = false;
            this.sessionExpiredNotified = false;
            this.updateUI();
            this.showToast('Đã đăng xuất', 'info');
            setTimeout(() => window.location.replace('/'), 500);
        }
    }

    async checkAuthStatus() {
        if (this.authCheckPromise) {
            return this.authCheckPromise;
        }

        this.isCheckingAuth = true;
        this.authCheckPromise = (async () => {
            try {
                const response = await this.fetchAPI('/auth/me', {
                    skipRefresh: false,
                    silentAuth: true,
                });

                if (response.success && response.data) {
                    this.setAuthenticatedUser(response.data.user || response.data);
                } else {
                    this.user = null;
                }
            } catch (error) {
                // Expected for guest users. A previously authenticated session is
                // handled by fetchAPI and announced through cinema:session-expired.
                this.user = null;
            } finally {
                this.isCheckingAuth = false;
                this.authChecked = true;
                this.updateUI();
            }

            return !!this.user;
        })().finally(() => {
            this.authCheckPromise = null;
        });

        return this.authCheckPromise;
    }

    async fetchAPI(endpoint, options = {}) {
        const requestOptions = { ...options };
        delete requestOptions.skipRefresh;
        delete requestOptions.silentAuth;

        try {
            return await window.apiClient.request(endpoint, requestOptions);
        } catch (error) {
            if (error.status === 401) {
                if (options.skipRefresh || endpoint.includes('/auth/refresh')) {
                    error.isAuthExpected = true;
                    throw error;
                }

                const refreshed = await this.refreshAccessToken();
                if (refreshed) {
                    return window.apiClient.request(endpoint, requestOptions);
                }

                this.expireSession({ notify: !options.silentAuth });
                const sessionError = new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
                sessionError.status = 401;
                sessionError.code = 'SESSION_EXPIRED';
                sessionError.isSessionExpired = true;
                throw sessionError;
            }

            throw error;
        }
    }

    async refreshAccessToken() {
        if (this.isRefreshing && this.refreshPromise) {
            return this.refreshPromise;
        }

        this.isRefreshing = true;
        this.refreshPromise = (async () => {
            try {
                const data = await window.apiClient.post('/auth/refresh');

                if (data.success && data.data.user) {
                    this.setAuthenticatedUser(data.data.user);
                    return true;
                }

                return false;
            } catch (error) {
                console.error('Token refresh failed:', error);
                return false;
            } finally {
                this.isRefreshing = false;
                this.refreshPromise = null;
            }
        })();

        return this.refreshPromise;
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
                btn.setAttribute('aria-label', 'Ẩn mật khẩu');
                btn.setAttribute('aria-pressed', 'true');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                btn.setAttribute('aria-label', 'Hiển thị mật khẩu');
                btn.setAttribute('aria-pressed', 'false');
            }
        }
    }

    showModal(tab = 'login') {
        if (!this.ensureModal()) {
            this.showToast('Không thể mở form đăng nhập. Vui lòng tải lại trang.', 'warning');
            return false;
        }

        this.showAuthForms(tab);
        this.clearErrors();
        this.modal.show();
        return true;
    }

    showAuthRequired() {
        // Khi người dùng chưa đăng nhập vào trang đặt vé, hiển thị trực tiếp form
        // đăng nhập đầy đủ thay vì màn hình trung gian chỉ có 2 nút Đăng nhập/Đăng ký.
        return this.showModal('login');
    }

    ensureModal() {
        if (this.modal) {
            return true;
        }

        const modalEl = document.getElementById('authModal');
        const ModalClass = window.bootstrap?.Modal;
        if (!modalEl || !ModalClass) {
            return false;
        }

        this.modal = ModalClass.getOrCreateInstance
            ? ModalClass.getOrCreateInstance(modalEl)
            : new ModalClass(modalEl);

        return true;
    }

    setAuthenticatedUser(user) {
        this.user = user || null;
        this.authChecked = true;
        this.hadAuthenticatedSession = !!this.user;
        this.sessionExpired = false;
        this.sessionExpiredNotified = false;
    }

    expireSession({ notify = true } = {}) {
        const shouldNotify = notify
            && this.hadAuthenticatedSession
            && !this.sessionExpiredNotified;

        this.user = null;
        this.authChecked = true;
        this.sessionExpired = this.hadAuthenticatedSession;
        this.updateUI();

        if (!shouldNotify) {
            return;
        }

        this.sessionExpiredNotified = true;
        window.dispatchEvent(new CustomEvent('cinema:session-expired', {
            detail: {
                message: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại để tiếp tục.',
            },
        }));
    }

    async ensureAuthenticated() {
        if (!this.authChecked) {
            await this.checkAuthStatus();
        }

        return !!this.user;
    }

    showAuthForms(tab = 'login') {
        const modal = document.getElementById('authModal');
        const authRequiredSection = modal?.querySelector('.auth-required-section');
        const authFormsSection = modal?.querySelector('.auth-forms-section');

        if (authRequiredSection && authFormsSection) {
            authRequiredSection.classList.add('d-none');
            authFormsSection.classList.remove('d-none');
        }

        this.showLoginPanel();

        if (tab === 'register') {
            document.getElementById('register-tab')?.click();
        } else {
            document.getElementById('login-tab')?.click();
        }
    }

    showForgotPasswordPanel() {
        document.getElementById('login-tab')?.click();
        document.getElementById('loginPanel')?.classList.add('d-none');
        document.getElementById('forgotPasswordPanel')?.classList.remove('d-none');
        this.clearForgotPasswordState();

        const loginEmail = document.getElementById('loginEmail')?.value?.trim();
        const forgotEmail = document.getElementById('forgotPasswordEmail');
        if (forgotEmail) {
            forgotEmail.value = loginEmail || '';
            window.setTimeout(() => forgotEmail.focus(), 50);
        }
    }

    showLoginPanel() {
        document.getElementById('forgotPasswordPanel')?.classList.add('d-none');
        document.getElementById('loginPanel')?.classList.remove('d-none');
        this.clearForgotPasswordState();
    }

    clearForgotPasswordState() {
        document.getElementById('forgotPasswordEmail')?.classList.remove('is-invalid');
        const error = document.getElementById('forgotPasswordEmailError');
        if (error) error.textContent = '';
        const alert = document.getElementById('forgotPasswordAlert');
        if (alert) {
            alert.className = 'alert d-none mt-3';
            alert.textContent = '';
        }
    }

    showForgotPasswordAlert(message, type) {
        const alert = document.getElementById('forgotPasswordAlert');
        if (!alert) return;
        alert.textContent = message;
        alert.className = `alert alert-${type} mt-3`;
    }

    setButtonLoading(button, loading, label) {
        if (!button) return;
        button.disabled = loading;
        button.querySelector('.spinner-border')?.classList.toggle('d-none', !loading);
        const text = button.querySelector('.btn-text');
        if (text) text.textContent = label;
    }

    updateUI() {
        // Don't update UI until initial auth check is complete
        // This prevents flickering between logged-out and logged-in states
        if (!this.authChecked) {
            return;
        }

        // Add class to body to trigger CSS fade-in (prevents FOUC)
        document.body.classList.add('auth-checked');

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
        const normalizedType = type === 'danger' ? 'error' : type;
        const titles = {
            success: 'Thành công',
            error: 'Có lỗi xảy ra',
            warning: 'Cần lưu ý',
            info: 'Thông báo',
        };
        const toastMethod = window.Toast?.[normalizedType] || window.Toast?.info;
        toastMethod?.(titles[normalizedType] || titles.info, String(message || ''));
    }

    // Tokens managed via HttpOnly cookies - no localStorage needed

    isAuthenticated() {
        // Don't return true until we've checked auth status
        // This prevents race conditions where code checks auth before checkAuthStatus() completes
        if (!this.authChecked) {
            return false;
        }
        return !!this.user;
    }

    getUser() {
        return this.user;
    }
}

// Initialize auth manager
window.authManager = new AuthManager();
