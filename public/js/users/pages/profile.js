import Toast from '../components/toast.js';

class ProfilePage {
    constructor() {
        this.user = null;
        this.elements = {};
        this.editableFieldIds = ['profileName', 'profileGender', 'profileAddress'];
        this.init();
    }

    async init() {
        this.cacheElements();
        this.bindEvents();
        await this.loadProfile();
    }

    // Helper methods for DOM manipulation
    createLoadingSpinner() {
        const wrapper = document.createElement('div');
        wrapper.className = 'text-center py-5';

        const spinner = document.createElement('div');
        spinner.className = 'spinner-border text-danger';

        wrapper.appendChild(spinner);
        return wrapper;
    }

    createTicketsSkeleton() {
        let html = '<div class="w-100">';
        for (let i = 0; i < 3; i++) {
            html += `
                <div class="profile-card mb-3" style="padding: 16px;">
                    <div class="row g-3">
                        <div class="col-auto">
                            <div class="skeleton rounded-3" style="width: 90px; height: 130px;"></div>
                        </div>
                        <div class="col">
                            <div class="d-flex gap-2 mb-2">
                                <div class="skeleton rounded" style="width: 50px; height: 20px;"></div>
                                <div class="skeleton rounded" style="width: 50px; height: 20px;"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="skeleton rounded" style="width: 40%; height: 24px;"></div>
                                <div class="skeleton rounded" style="width: 80px; height: 24px;"></div>
                            </div>
                            <div class="mb-3">
                                <div class="skeleton rounded mb-2" style="width: 70%; height: 16px;"></div>
                                <div class="skeleton rounded mb-2" style="width: 60%; height: 16px;"></div>
                                <div class="skeleton rounded" style="width: 50%; height: 16px;"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 border-top pt-3" style="border-color: #2a2a2a !important;">
                                <div class="skeleton rounded" style="width: 100px; height: 20px;"></div>
                                <div class="skeleton rounded" style="width: 110px; height: 32px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        html += '</div>';
        return html;
    }

    createPointHistorySkeleton() {
        return `
            <tr>
                <td><div class="profile-skeleton rounded" style="width: 80px; height: 16px;"></div></td>
                <td><div class="profile-skeleton rounded" style="width: 150px; height: 16px;"></div></td>
                <td class="text-end"><div class="profile-skeleton rounded ms-auto" style="width: 50px; height: 16px;"></div></td>
            </tr>
            <tr>
                <td><div class="profile-skeleton rounded" style="width: 80px; height: 16px;"></div></td>
                <td><div class="profile-skeleton rounded" style="width: 150px; height: 16px;"></div></td>
                <td class="text-end"><div class="profile-skeleton rounded ms-auto" style="width: 50px; height: 16px;"></div></td>
            </tr>
            <tr>
                <td><div class="profile-skeleton rounded" style="width: 80px; height: 16px;"></div></td>
                <td><div class="profile-skeleton rounded" style="width: 150px; height: 16px;"></div></td>
                <td class="text-end"><div class="profile-skeleton rounded ms-auto" style="width: 50px; height: 16px;"></div></td>
            </tr>
        `;
    }

    createVouchersSkeleton() {
        let html = '<div class="row g-3 w-100 m-0">';
        for (let i = 0; i < 2; i++) {
            html += `
                <div class="col-12 col-md-6">
                    <div style="
                        background: linear-gradient(135deg, #1a1a1a 0%, #222 100%);
                        border: 1px solid #2a2a2a;
                        border-left: 4px solid #2a2a2a;
                        border-radius: 12px;
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 10px;
                    ">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div class="skeleton rounded-pill" style="width: 70px; height: 22px;"></div>
                            <div class="skeleton rounded" style="width: 90px; height: 22px;"></div>
                        </div>
                        <div class="skeleton rounded" style="width: 60%; height: 20px;"></div>
                        <div class="skeleton rounded" style="width: 80%; height: 16px;"></div>
                        <div style="display:flex; gap:16px;">
                            <div class="skeleton rounded" style="width: 100px; height: 14px;"></div>
                            <div class="skeleton rounded" style="width: 120px; height: 14px;"></div>
                        </div>
                    </div>
                </div>
            `;
        }
        html += '</div>';
        return html;
    }

    createErrorAlert(message) {
        return `<div class="alert alert-danger">${message}</div>`;
    }

    clearContainer(container) {
        if (!container) return;
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
    }

    cacheElements() {
        this.elements = {
            loading: document.getElementById('profileLoading'),
            content: document.getElementById('profileContent'),
            authRequired: document.getElementById('profileAuthRequired'),
            avatar: document.getElementById('profileAvatar'),
            avatarFallback: document.getElementById('profileAvatarFallback'),
            displayName: document.getElementById('profileDisplayName'),
            memberRank: document.getElementById('profileMemberRank'),
            logoutBtn: document.getElementById('profileLogoutBtn'),
            scrollTopBtn: document.querySelector('.profile-scroll-top'),
            updateForm: document.getElementById('profileUpdateForm'),
            passwordForm: document.getElementById('profilePasswordForm'),
            resetBtn: document.getElementById('profileResetBtn'),
            updateBtn: document.getElementById('profileUpdateBtn'),
            passwordBtn: document.getElementById('profilePasswordBtn'),
            updateAlert: document.getElementById('profileUpdateAlert'),
            passwordAlert: document.getElementById('profilePasswordAlert'),
            nameInput: document.getElementById('profileName'),
            emailInput: document.getElementById('profileEmail'),
            phoneInput: document.getElementById('profilePhone'),
            birthdayInput: document.getElementById('profileBirthday'),
            genderInput: document.getElementById('profileGender'),
            addressInput: document.getElementById('profileAddress'),
            xpValue: document.getElementById('profileXpValue'),
            xpProgress: document.getElementById('profileXpProgress'),
            xpMessage: document.getElementById('profileXpMessage'),
        };
    }

    bindEvents() {
        document.querySelectorAll('[data-profile-nav]').forEach((button) => {
            button.addEventListener('click', () => this.handleNavigation(button));
        });

        document.querySelectorAll('[data-edit-field]').forEach((button) => {
            button.addEventListener('click', () => this.enableFieldEditing(button.dataset.editField));
        });

        this.elements.updateForm?.addEventListener('submit', (event) => this.handleUpdateProfile(event));
        this.elements.passwordForm?.addEventListener('submit', (event) => this.handleChangePassword(event));
        this.elements.resetBtn?.addEventListener('click', () => this.populateForms());

        this.elements.logoutBtn?.addEventListener('click', () => window.authManager?.handleLogout?.());

        this.elements.scrollTopBtn?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Ticket filter buttons
        document.querySelectorAll('[data-ticket-filter]').forEach((button) => {
            button.addEventListener('click', () => this.handleTicketFilter(button));
        });

        // Load more button
        document.getElementById('ticketsLoadMore')?.addEventListener('click', () => this.loadMoreTickets());
    }

    async loadProfile() {
        this.setLoading(true);

        try {
            if (!window.authManager?.isAuthenticated()) {
                this.showAuthRequired();
                return;
            }

            const response = await this.apiRequest('/auth/profile');
            this.user = response.data?.user || response.data || response.user || null;

            if (!this.user) {
                throw new Error('Không thể tải dữ liệu hồ sơ.');
            }

                if (window.authManager) {
                window.authManager.user = this.user;
                window.authManager.updateUI();
            }

            this.renderProfile();
            this.populateForms();
            this.showContent();
        } catch (error) {
            console.error('Profile load failed:', error);

            if (error.message.includes('Session expired') || error.message.includes('Unauthenticated')) {
                this.showAuthRequired();
                return;
            }

            this.showContent();
            this.showAlert(this.elements.updateAlert, error.message || 'Không thể tải thông tin hồ sơ.', 'danger');
        } finally {
            this.setLoading(false);
        }
    }

    renderProfile() {
        const user = this.user || {};
        const memberSince = user.created_at ? this.getYear(user.created_at) : '2022';
        const points = Number(user.loyalty_points || 0);
        const progress = Math.min(100, Math.round((points / 1000) * 100));

        this.setText(this.elements.displayName, user.name || 'Ng\u01b0\u1eddi d\u00f9ng');
        this.setText(this.elements.memberRank, `Th\u00e0nh vi\u00ean Cinema t\u1eeb ${memberSince}`);
        this.setText(this.elements.xpValue, `${points.toLocaleString('vi-VN')} \u0111i\u1ec3m`);

        // Populate sidebar user info
        const sidebarName = document.getElementById('sidebarDisplayName');
        const sidebarRank = document.getElementById('sidebarMemberRank');
        if (sidebarName) sidebarName.textContent = user.name || 'Ng\u01b0\u1eddi d\u00f9ng';
        if (sidebarRank) sidebarRank.textContent = `T\u1eeb ${memberSince}`;

        // Populate member badge
        const memberBadge = document.getElementById('profileMemberBadge');
        if (memberBadge) memberBadge.textContent = 'Th\u00e0nh vi\u00ean';

        // Populate cover stats
        const coverStatPoints = document.getElementById('coverStatPoints');
        if (coverStatPoints) coverStatPoints.textContent = points.toLocaleString('vi-VN');

        if (this.elements.xpProgress) {
            this.elements.xpProgress.style.width = `${progress}%`;
        }

        if (this.elements.xpMessage) {
            const remaining = Math.max(0, 1000 - points);
            this.elements.xpMessage.textContent = remaining > 0
                ? `C\u00f2n ${remaining.toLocaleString('vi-VN')} \u0111i\u1ec3m \u0111\u1ec3 \u0111\u1ea1t m\u1ed1c \u01b0u \u0111\u00e3i ti\u1ebfp theo.`
                : 'B\u1ea1n \u0111\u00e3 \u0111\u1ea1t m\u1ed1c \u01b0u \u0111\u00e3i Cinema. Ti\u1ebfp t\u1ee5c \u0111\u1eb7t v\u00e9 \u0111\u1ec3 duy tr\u00ec quy\u1ec1n l\u1ee3i.';
        }

        this.renderAvatar(user.avatar_url, user.name);
    }

    populateForms() {
        const user = this.user || {};

        if (this.elements.nameInput) this.elements.nameInput.value = user.name || '';
        if (this.elements.emailInput) this.elements.emailInput.value = user.email || '';
        if (this.elements.phoneInput) this.elements.phoneInput.value = user.phone || '';
        if (this.elements.birthdayInput) this.elements.birthdayInput.value = this.toDateInputValue(user.birthday);
        if (this.elements.genderInput) this.elements.genderInput.value = user.gender || '';
        if (this.elements.addressInput) this.elements.addressInput.value = user.address || '';

        this.clearFormErrors(this.elements.updateForm);
        this.hideAlert(this.elements.updateAlert);
        this.disableEditableFields();
        this.renderAvatar(user.avatar_url, user.name);
    }

    enableFieldEditing(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.disabled = false;
        field.focus();

        if (field.select && field.tagName !== 'SELECT') {
            field.select();
        }

        const button = document.querySelector(`[data-edit-field="${fieldId}"]`);
        button?.classList.add('is-active');

        if (this.elements.updateBtn) this.elements.updateBtn.disabled = false;
        if (this.elements.resetBtn) this.elements.resetBtn.disabled = false;
    }

    disableEditableFields() {
        this.editableFieldIds.forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (field) field.disabled = true;
        });

        document.querySelectorAll('[data-edit-field]').forEach((button) => {
            button.classList.remove('is-active');
        });

        if (this.elements.updateBtn) this.elements.updateBtn.disabled = true;
        if (this.elements.resetBtn) this.elements.resetBtn.disabled = true;
    }

    renderAvatar(avatarUrl, name = 'U') {
        const fallbackText = (name || 'U').trim().charAt(0).toUpperCase() || 'U';
        const safeAvatarUrl = avatarUrl ? this.safeImageUrl(avatarUrl) : '';

        // Main cover avatar
        if (this.elements.avatarFallback) {
            this.elements.avatarFallback.textContent = fallbackText;
        }

        if (!this.elements.avatar || !this.elements.avatarFallback) return;

        if (safeAvatarUrl) {
            this.elements.avatar.src = safeAvatarUrl;
            this.elements.avatar.classList.remove('d-none');
            this.elements.avatarFallback.classList.add('d-none');

            this.elements.avatar.addEventListener('error', () => {
                this.elements.avatar.classList.add('d-none');
                this.elements.avatarFallback.classList.remove('d-none');
            }, { once: true });
        } else {
            this.elements.avatar.classList.add('d-none');
            this.elements.avatarFallback.classList.remove('d-none');
        }

        // Sidebar avatar
        const sidebarAvatarImg = document.getElementById('sidebarAvatar');
        const sidebarAvatarFallback = document.getElementById('sidebarAvatarFallback');
        if (sidebarAvatarFallback) sidebarAvatarFallback.textContent = fallbackText;

        if (sidebarAvatarImg && sidebarAvatarFallback) {
            if (safeAvatarUrl) {
                sidebarAvatarImg.src = safeAvatarUrl;
                sidebarAvatarImg.classList.remove('d-none');
                sidebarAvatarFallback.classList.add('d-none');
                sidebarAvatarImg.addEventListener('error', () => {
                    sidebarAvatarImg.classList.add('d-none');
                    sidebarAvatarFallback.classList.remove('d-none');
                }, { once: true });
            } else {
                sidebarAvatarImg.classList.add('d-none');
                sidebarAvatarFallback.classList.remove('d-none');
            }
        }
    }

    async handleUpdateProfile(event) {
        event.preventDefault();

        this.clearFormErrors(this.elements.updateForm);
        this.hideAlert(this.elements.updateAlert);
        this.setButtonLoading(this.elements.updateBtn, true);

        const payload = {
            name: this.elements.nameInput?.value?.trim() || '',
            phone: this.elements.phoneInput?.value?.trim() || '',
            birthday: this.elements.birthdayInput?.value || null,
            gender: this.elements.genderInput?.value || null,
            address: this.elements.addressInput?.value?.trim() || null,
        };

        try {
            const response = await this.apiRequest('/auth/profile', {
                method: 'PUT',
                body: payload,
            });

            this.user = response.data?.user || response.data || response.user || { ...this.user, ...payload };

            if (window.authManager) {
                window.authManager.user = this.user;
                window.authManager.updateUI();
            }

            this.renderProfile();
            this.populateForms();
            this.showAlert(this.elements.updateAlert, response.message || 'Cập nhật hồ sơ thành công.', 'success');
            window.authManager?.showToast?.('Cập nhật hồ sơ thành công.', 'success');
        } catch (error) {
            this.handleFormError(error, this.elements.updateAlert, this.elements.updateForm);
        } finally {
            this.setButtonLoading(this.elements.updateBtn, false);
            if (this.elements.updateBtn) this.elements.updateBtn.disabled = true;
        }
    }

    async handleChangePassword(event) {
        event.preventDefault();

        this.clearFormErrors(this.elements.passwordForm);
        this.hideAlert(this.elements.passwordAlert);
        this.setButtonLoading(this.elements.passwordBtn, true);

        const newPassword = document.getElementById('newPassword')?.value || '';
        const newPasswordConfirmation = document.getElementById('newPasswordConfirmation')?.value || '';

        if (newPassword !== newPasswordConfirmation) {
            this.handleFormError(
                { message: 'Mật khẩu xác nhận không khớp.' },
                this.elements.passwordAlert,
                this.elements.passwordForm
            );
            const confirmationInput = document.getElementById('newPasswordConfirmation');
            const feedback = confirmationInput?.closest('.profile-form-group')?.querySelector('.invalid-feedback');
            confirmationInput?.classList.add('is-invalid');
            if (feedback) feedback.textContent = 'Mật khẩu xác nhận không khớp.';
            
            this.setButtonLoading(this.elements.passwordBtn, false);
            return;
        }

        const payload = {
            current_password: document.getElementById('currentPassword')?.value || '',
            new_password: newPassword,
            new_password_confirmation: newPasswordConfirmation,
        };

        try {
            const response = await this.apiRequest('/auth/change-password', {
                method: 'POST',
                body: payload,
            });

            this.elements.passwordForm?.reset();
            this.showAlert(this.elements.passwordAlert, response.message || 'Đổi mật khẩu thành công.', 'success');
            window.authManager?.showToast?.('Đổi mật khẩu thành công.', 'success');
        } catch (error) {
            this.handleFormError(error, this.elements.passwordAlert, this.elements.passwordForm);
        } finally {
            this.setButtonLoading(this.elements.passwordBtn, false);
        }
    }

    handleFormError(error, alertElement, form = null) {
        const message = error.message || 'Có lỗi xảy ra. Vui lòng thử lại.';
        this.showAlert(alertElement, message, 'danger');

        const errors = error.errors || error.data?.errors || null;
        if (!errors || !form) return;

        Object.entries(errors).forEach(([field, messages]) => {
            const input = form.querySelector(`[name="${field}"]`);
            const feedback = input?.closest('.profile-form-group')?.querySelector('.invalid-feedback');
            input?.classList.add('is-invalid');
            if (feedback) feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
        });
    }

    clearFormErrors(form) {
        if (!form) return;
        form.querySelectorAll('.is-invalid').forEach((input) => input.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((feedback) => {
            feedback.textContent = '';
        });
    }

    handleNavigation(button) {
        const nav = button.dataset.profileNav;

        document.querySelectorAll('[data-profile-nav]').forEach((item) => {
            item.classList.toggle('active', item === button);
        });

        // Hide all sections first
        const sections = ['profileSection', 'ticketsSection', 'voucherSection', 'pointsSection'];
        sections.forEach(sec => document.getElementById(sec)?.classList.add('d-none'));
        
        document.querySelector('#profileContent .profile-card-grid')?.classList.add('d-none');
        document.querySelector('#profileContent .profile-cover-card')?.classList.add('d-none');

        // Show/hide sections based on nav
        if (nav === 'profile') {
            document.getElementById('profileSection')?.classList.remove('d-none');
            document.querySelector('#profileContent .profile-card-grid')?.classList.remove('d-none');
            document.querySelector('#profileContent .profile-cover-card')?.classList.remove('d-none');
        } else if (nav === 'tickets') {
            document.getElementById('ticketsSection')?.classList.remove('d-none');
            this.ticketFilter = 'all';
            this.ticketPage = 1;
            this.loadTickets();
        } else if (nav === 'points') {
            document.getElementById('pointsSection')?.classList.remove('d-none');
            this.loadPointHistory();
        } else if (nav === 'voucher') {
            document.getElementById('voucherSection')?.classList.remove('d-none');
            this.loadVouchers();
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.info('Chức năng đang phát triển', 'Tính năng này sẽ sớm được cập nhật.');
            }
        }
    }

    handleTicketFilter(button) {
        const filter = button.dataset.ticketFilter;

        document.querySelectorAll('[data-ticket-filter]').forEach((btn) => {
            btn.classList.toggle('active', btn === button);
        });

        this.ticketFilter = filter;
        this.ticketPage = 1;
        this.loadTickets();
    }

    async loadVouchers() {
        const voucherList = document.getElementById('voucherList');
        const voucherEmpty = document.getElementById('voucherEmpty');
        const voucherLoading = document.getElementById('voucherLoading');
        if (!voucherList) return;

        voucherLoading?.classList.add('d-none'); // Hide default spinner as we use skeleton
        voucherEmpty?.classList.add('d-none');
        voucherList.innerHTML = this.createVouchersSkeleton();

        try {
            const response = await this.apiRequest('/promotions/registered');
            const vouchers = response.data || [];

            if (vouchers.length === 0) {
                voucherList.innerHTML = '';
                voucherEmpty?.classList.remove('d-none');
                return;
            }

            voucherList.innerHTML = vouchers.map(v => this.renderVoucherCard(v)).join('');
            
            // Attach event listeners
            voucherList.querySelectorAll('.voucher-copy-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const code = e.currentTarget.dataset.code;
                    try {
                        await navigator.clipboard.writeText(code);
                        Toast.success('Sao chép thành công', `Mã ${code} đã được sao chép.`);
                    } catch {
                        Toast.info('Mã voucher', code);
                    }
                });
            });
        } catch (error) {
            console.error('Load vouchers error:', error);
            voucherList.innerHTML = this.createErrorAlert('Không thể tải danh sách voucher.');
        }
    }

    renderVoucherCard(v) {
        const isPercent = v.discount_type === 'percentage';
        const discountLabel = isPercent
            ? `Giảm ${parseFloat(v.discount_value).toFixed(0)}%${ v.max_discount_amount > 0 ? ` (tối đa ${Number(v.max_discount_amount).toLocaleString('vi-VN')}đ)` : '' }`
            : `Giảm ${Number(v.discount_value).toLocaleString('vi-VN')}đ`;

        const minLabel = v.min_order_value > 0
            ? `Đơn tối thiểu ${Number(v.min_order_value).toLocaleString('vi-VN')}đ`
            : 'Áp dụng mọi đơn hàng';

        const expiry = v.end_date
            ? `HSD: ${new Date(v.end_date).toLocaleDateString('vi-VN')}`
            : 'Không giới hạn';

        return `
            <div class="col-12 col-md-6">
                <div style="
                    background: linear-gradient(135deg, #1a1a1a 0%, #222 100%);
                    border: 1px solid #2a2a2a;
                    border-left: 4px solid var(--cinema-danger, #e50914);
                    border-radius: 12px;
                    padding: 20px;
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                ">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="background:#e50914; color:#fff; font-size:12px; font-weight:700; padding:4px 10px; border-radius:20px; letter-spacing:.5px;">VOUCHER</span>
                        <code style="color:#e50914; font-size:16px; font-weight:700; letter-spacing:2px;">${this.escapeHtml(v.code)}</code>
                    </div>
                    <div style="color:#fff; font-size:15px; font-weight:600;">${this.escapeHtml(v.name || discountLabel)}</div>
                    <div style="color:#ccc; font-size:13px;">${this.escapeHtml(v.description || discountLabel)}</div>
                    <div style="display:flex; gap:16px; font-size:12px; color:#8d96a3;">
                        <span><i class="bi bi-info-circle me-1"></i>${this.escapeHtml(minLabel)}</span>
                        <span><i class="bi bi-calendar me-1"></i>${this.escapeHtml(expiry)}</span>
                    </div>
                    <div style="margin-top:4px;">
                        <button class="voucher-copy-btn" data-code="${this.escapeHtml(v.code)}" style="
                            background:transparent; border:1px solid #333; color:#ccc;
                            border-radius:8px; padding:6px 14px; font-size:13px; cursor:pointer;
                            transition:all .2s;
                        "><i class="bi bi-clipboard me-1"></i>Sao chép mã</button>
                    </div>
                </div>
            </div>`;
    }

    async loadPointHistory() {
        const pointHistoryList = document.getElementById('pointHistoryList');
        const pointHistoryEmpty = document.getElementById('pointHistoryEmpty');
        if (!pointHistoryList || !pointHistoryEmpty) return;
        
        pointHistoryList.innerHTML = this.createPointHistorySkeleton();
        pointHistoryEmpty.classList.add('d-none');

        try {
            // First load user points to update UI
            let loyaltyPoints = 0;
            try {
                const userRes = await this.apiRequest('/auth/me');
                if (userRes && userRes.data) {
                    loyaltyPoints = Number(userRes.data.loyalty_points || 0);
                }
            } catch (e) {
                console.warn('Cannot fetch user points');
            }

            // Update Membership Status UI
            const pointsTotal = document.getElementById('pointsDashboardTotal');
            pointsTotal.replaceChildren(document.createTextNode(loyaltyPoints.toLocaleString('vi-VN')));
            const pointsLabel = document.createElement('span');
            pointsLabel.className = 'fs-4 text-muted fw-normal';
            pointsLabel.textContent = ' Points';
            pointsTotal.appendChild(pointsLabel);
            
            let rank = 'Silver Member';
            let nextTier = '1000 Points to Gold';
            let progress = 0;
            
            if (loyaltyPoints >= 5000) {
                rank = 'Platinum Member';
                nextTier = 'You are at highest tier';
                progress = 100;
            } else if (loyaltyPoints >= 1000) {
                rank = 'Gold Member';
                nextTier = `${(5000 - loyaltyPoints).toLocaleString('vi-VN')} Points to Platinum`;
                progress = (loyaltyPoints - 1000) / 4000 * 100;
            } else {
                nextTier = `${(1000 - loyaltyPoints).toLocaleString('vi-VN')} Points to Gold`;
                progress = loyaltyPoints / 1000 * 100;
            }

            document.getElementById('pointsDashboardRank').textContent = rank;
            document.getElementById('pointsDashboardNextTier').textContent = nextTier;
            document.getElementById('pointsDashboardPercent').textContent = `${Math.round(progress)}%`;
            document.getElementById('pointsDashboardProgress').style.width = `${progress}%`;

            // Load Orders for history
            const response = await this.apiRequest('/orders/user/me?per_page=50');
            const orders = (response.data?.data || []).filter(o => o.status === 'completed');

            let historyHTML = '';
            
            orders.forEach(order => {
                const date = new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const pointsUsed = parseInt(order.payload?.points_used || 0, 10);
                // Assume final_amount was what was paid after discount. Earned points = final_amount / 10000
                const amountPaid = parseFloat(order.total_amount || 0);
                const pointsEarned = Math.floor(amountPaid / 10000);

                let movieName = 'Ticket Booking';
                if (order.showtime && order.showtime.movie) {
                    movieName = order.showtime.movie.title;
                }

                if (pointsEarned > 0) {
                    historyHTML += `
                        <tr>
                            <td class="text-white align-middle" style="font-size: 0.9rem;">${date}</td>
                            <td class="align-middle">
                                <div class="text-white fw-bold mb-1">${this.escapeHtml(movieName)}</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Ticket Booking</div>
                            </td>
                            <td class="text-end align-middle">
                                <span class="fs-5 fw-bold text-danger">+${pointsEarned}</span>
                            </td>
                        </tr>
                    `;
                }

                if (pointsUsed > 0) {
                    historyHTML += `
                        <tr>
                            <td class="text-white align-middle" style="font-size: 0.9rem;">${date}</td>
                            <td class="align-middle">
                                <div class="text-white fw-bold mb-1">${this.escapeHtml(movieName)}</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Point Redemption</div>
                            </td>
                            <td class="text-end align-middle">
                                <span class="fs-5 fw-bold text-white">-${pointsUsed}</span>
                            </td>
                        </tr>
                    `;
                }
            });

            if (historyHTML === '') {
                pointHistoryList.innerHTML = '';
                pointHistoryEmpty.classList.remove('d-none');
            } else {
                pointHistoryList.innerHTML = historyHTML;
            }
        } catch (error) {
            pointHistoryList.innerHTML = '';
            pointHistoryEmpty.classList.remove('d-none');
            if (typeof Toast !== 'undefined') {
                Toast.error('Không thể tải lịch sử điểm');
            }
        }
    }

    async loadMoreTickets() {
        this.ticketPage++;
        await this.loadTickets(true);
    }

    async loadTickets(append = false) {
        const ticketsList = document.getElementById('ticketsList');
        const ticketsEmpty = document.getElementById('ticketsEmpty');
        const loadMoreBtn = document.getElementById('ticketsLoadMore');

        if (!ticketsList || !ticketsEmpty) return;

        try {
            if (!append) {
                this.clearContainer(ticketsList);
                ticketsList.innerHTML = this.createTicketsSkeleton();
                ticketsEmpty.classList.add('d-none');
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            } else {
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = true;
                    loadMoreBtn.textContent = 'Đang tải...';
                }
            }

            const params = new URLSearchParams({
                page: this.ticketPage || 1,
                per_page: 10
            });

            const response = await this.apiRequest(`/orders/user/me?${params}`);
            const data = response.data || {};
            const orders = data.data || [];
            const currentPage = data.current_page || 1;
            const lastPage = data.last_page || 1;

            // Update cover stat ticket count (total from API)
            if (!append) {
                const totalTickets = data.total || orders.length;
                const coverStatTickets = document.getElementById('coverStatTickets');
                if (coverStatTickets) coverStatTickets.textContent = totalTickets.toLocaleString('vi-VN');
            }

            if (!append) {
                ticketsList.innerHTML = '';
            }

            if (orders.length === 0 && !append) {
                ticketsEmpty.classList.remove('d-none');
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            } else {
                let filteredOrders = orders;

                // Filter by year if needed
                if (this.ticketFilter === 'year') {
                    const currentYear = new Date().getFullYear();
                    filteredOrders = orders.filter(order => {
                        const orderYear = new Date(order.created_at).getFullYear();
                        return orderYear === currentYear;
                    });
                }

                if (!append) {
                    ticketsList.innerHTML = '';
                }
                filteredOrders.forEach(order => {
                    const cardNode = this.createTicketCard(order);
                    ticketsList.appendChild(cardNode);
                });

                // Show/hide load more button
                if (loadMoreBtn) {
                    if (currentPage < lastPage) {
                        loadMoreBtn.style.display = 'inline-block';
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = 'Xem thêm lịch sử';
                    } else {
                        loadMoreBtn.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load tickets error:', error);
            if (!append) {
                this.clearContainer(ticketsList);
                ticketsList.innerHTML = this.createErrorAlert('Không thể tải danh sách vé');
            }
            if (loadMoreBtn) {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = 'Xem thêm lịch sử';
            }
        }
    }

    createTicketCard(order) {
        const template = document.getElementById('ticketCardTemplate');
        if (!template) return document.createElement('div');

        const card = template.content.cloneNode(true);
        const cardEl = card.querySelector('.ticket-card');

        // Status class
        const status = order.status || 'pending';
        if (cardEl) cardEl.dataset.status = status;

        // Make whole card clickable
        if (cardEl) {
            cardEl.style.cursor = 'pointer';
            cardEl.addEventListener('click', (e) => {
                if (e.target.closest('.ticket-detail-btn')) return;
                this.openOrderDetailModal(order);
            });
        }

        // Poster + cancelled overlay
        const poster = card.querySelector('.ticket-poster');
        const overlay = card.querySelector('.ticket-cancelled-overlay');
        if (poster) {
            poster.src = this.safeImageUrl(order.poster_url || order.showtime?.movie?.poster_url);
            poster.alt = order.movie_title || order.showtime?.movie?.title || 'Poster';
            // Prevent infinite loop: remove handler after first error
            poster.addEventListener('error', () => {
                poster.src = '/images/default-poster.jpg';
            }, { once: true });
        }
        if (overlay) {
            overlay.style.display = status === 'cancelled' ? 'flex' : 'none';
        }

        // Format badges (3D, IMAX, etc.)
        const formatsContainer = card.querySelector('.ticket-formats');
        if (formatsContainer) {
            const addBadge = (text) => {
                if (!text) return;
                const b = document.createElement('span');
                b.className = 'ticket-format-badge';
                b.textContent = text;
                formatsContainer.appendChild(b);
            };
            addBadge(order.showtime?.format?.name);
            addBadge(order.showtime?.sound?.name);
            addBadge(order.showtime?.subtitle?.name);
        }

        // Title
        const title = card.querySelector('.ticket-title');
        if (title) title.textContent = order.movie_title || order.showtime?.movie?.title || 'N/A';

        // ID
        const ticketId = card.querySelector('.ticket-id');
        if (ticketId) ticketId.textContent = `ID: #CP-${String(order.id).padStart(5, '0')}`;

        // Showtime
        const showtime = card.querySelector('.ticket-showtime');
        if (showtime) {
            const rawDate = order.show_date || order.showtime?.scheduled_at;
            if (rawDate) {
                const d = new Date(rawDate);
                showtime.textContent = `${d.toLocaleDateString('vi-VN')} - ${d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`;
            } else {
                showtime.textContent = 'N/A';
            }
        }

        // Theater
        const theater = card.querySelector('.ticket-theater');
        if (theater) {
            const branch = order.branch_name || order.showtime?.screen?.theater?.branch?.name || '';
            const screen = order.screen_name || order.showtime?.screen?.name || '';
            theater.textContent = branch && screen ? `${branch} - ${screen}` : (branch || screen || 'N/A');
        }

        // Seats
        const seats = card.querySelector('.ticket-seats');
        if (seats && order.order_items) {
            const names = order.order_items.map(i => i.metadata?.seat_label || i.seat?.seat_number).filter(Boolean).join(', ');
            seats.textContent = names || 'N/A';
        }

        // Status badge
        const statusEl = card.querySelector('.ticket-status');
        if (statusEl) this.renderTicketStatus(statusEl, status);

        // Detail button
        const detailBtn = card.querySelector('.ticket-detail-btn');
        if (detailBtn) {
            detailBtn.addEventListener('click', () => this.openOrderDetailModal(order));
        }

        return card;
    }

    renderTicketStatus(container, status) {
        if (!container) return;
        container.innerHTML = '';

        const config = {
            completed: { dot: '#22c55e', label: 'CONFIRMED', color: '#22c55e' },
            confirmed: { dot: '#22c55e', label: 'CONFIRMED', color: '#22c55e' },
            pending:   { dot: '#f59e0b', label: 'PENDING',   color: '#f59e0b' },
            cancelled: { dot: '#ed0712', label: 'ĐÃ HỦY',    color: '#ed0712' },
        }[status] || { dot: '#6b7280', label: String(status || 'Không rõ').toUpperCase(), color: '#6b7280' };

        const dot = document.createElement('span');
        dot.className = 'ticket-status-dot';
        dot.style.background = config.dot;
        dot.style.boxShadow = `0 0 6px ${config.dot}66`;

        const label = document.createElement('span');
        label.className = 'ticket-status-label';
        label.textContent = config.label;
        label.style.color = config.color;

        container.appendChild(dot);
        container.appendChild(label);
    }

    async apiRequest(endpoint, options = {}) {
        if (window.authManager?.apiRequest) {
            return window.authManager.apiRequest(endpoint, options);
        }

        if (window.authManager?.fetchAPI) {
            return window.authManager.fetchAPI(endpoint, options);
        }

        throw new Error('Không tìm thấy trình quản lý xác thực.');
    }

    setLoading(isLoading) {
        this.elements.loading?.classList.toggle('d-none', !isLoading);
    }

    showContent() {
        this.elements.content?.classList.remove('d-none');
        this.elements.authRequired?.classList.add('d-none');
    }

    showAuthRequired() {
        this.elements.content?.classList.add('d-none');
        this.elements.authRequired?.classList.remove('d-none');
    }

    setButtonLoading(button, isLoading) {
        if (!button) return;
        button.disabled = isLoading;
        button.querySelector('.spinner-border')?.classList.toggle('d-none', !isLoading);
    }

    showAlert(element, message, type = 'info') {
        if (!element) return;
        element.textContent = message;
        element.className = `alert alert-${type} profile-alert`;
        element.classList.remove('d-none');
    }

    hideAlert(element) {
        element?.classList.add('d-none');
    }

    setText(element, text) {
        if (element) element.textContent = text;
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    safeImageUrl(value) {
        const candidate = String(value || '').trim();
        if (/^\/(?!\/)[A-Za-z0-9_./?=&%-]+$/.test(candidate) && !candidate.includes('..')) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate;
        return '/images/default-poster.jpg';
    }

    toDateInputValue(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
        return date.toISOString().slice(0, 10);
    }

    getYear(value) {
        if (!value) return '2022';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '2022' : String(date.getFullYear());
    }

    async openOrderDetailModal(orderSummary) {
        const modalEl = document.getElementById('orderDetailModal');
        if (!modalEl) return;

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        document.getElementById('odModalTicketsList').innerHTML = '<div class="text-muted py-3">Đang tải chi tiết vé...</div>';
        document.getElementById('odModalProductsList').innerHTML = '';

        try {
            const response = await this.apiRequest(`/orders/${orderSummary.id}`);
            this.renderOrderDetailModal(response?.data || orderSummary);
        } catch (error) {
            console.error('Load order detail error:', error);
            this.renderOrderDetailModal(orderSummary);
            this.showToast?.('Không thể tải đầy đủ chi tiết đơn hàng. Đang hiển thị dữ liệu đã lưu.', 'warning');
        }
    }

    renderOrderDetailModal(order) {
        const modalEl = document.getElementById('orderDetailModal');
        if (!modalEl) return;
        
        // Populate modal data
        const orderCode = order.code || order.order_code || order.id;
        document.getElementById('odModalCode').textContent = String(orderCode).startsWith('ORD-')
            ? orderCode
            : `ORD-${orderCode}`;
        
        const posterUrl = this.safeImageUrl(order.poster_url || order.showtime?.movie?.poster_url);
        const modalPoster = document.getElementById('odModalPoster');
        modalPoster.src = posterUrl;
        // Prevent infinite loop: remove handler after first error
        modalPoster.addEventListener('error', () => {
            modalPoster.src = '/images/default-poster.jpg';
        }, { once: true });
        
        document.getElementById('odModalMovieTitle').textContent = order.movie_title || (order.showtime?.movie?.title) || 'N/A';
        
        const branch = order.branch_name || (order.showtime?.screen?.theater?.branch?.name) || '';
        const screen = order.screen_name || (order.showtime?.screen?.name) || '';
        document.getElementById('odModalTheater').textContent = branch ? `${branch}` : 'N/A';
        document.getElementById('odModalRoom').textContent = screen || 'N/A';
        document.getElementById('odModalAddress').textContent = order.theater_address
            || order.showtime?.screen?.theater?.address
            || order.showtime?.screen?.theater?.branch?.address
            || 'N/A';
        
        const rawDate = order.show_date || (order.showtime?.scheduled_at);
        if (rawDate) {
            const date = new Date(rawDate);
            const dateString = date.toLocaleDateString('vi-VN');
            const timeString = date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('odModalShowtime').textContent = `${timeString}, ${dateString}`;
        } else {
            document.getElementById('odModalShowtime').textContent = 'N/A';
        }
        
        // Seats
        let seatsText = 'N/A';
        let seatTypes = [];
        const ticketsList = document.getElementById('odModalTicketsList');
        const productsList = document.getElementById('odModalProductsList');
        ticketsList.innerHTML = '';
        productsList.innerHTML = '';
        
        // Try to get items from order_items OR fallback to multiple sources
        let tickets = Array.isArray(order.invoice?.tickets) ? order.invoice.tickets : [];
        let products = Array.isArray(order.invoice?.products) ? order.invoice.products : [];
        
        if (tickets.length === 0 && products.length === 0 && order.order_items && order.order_items.length > 0) {
            // Primary path: use order_items for completed orders
            tickets = order.order_items.filter(item => 
                item.item_type.includes('Seat') || item.item_type === 'ticket'
            );
            products = order.order_items.filter(item => 
                item.item_type.includes('Product') || item.item_type === 'product'
            );
        } else {
            // Fallback paths for failed/cancelled orders - check multiple sources
            
            // PRIORITY 1: Check payload.seats and payload.products (most common for failed orders)
            if (order.payload?.seats && Array.isArray(order.payload.seats) && order.payload.seats.length > 0) {
                tickets = order.payload.seats.map(seat => ({
                    item_type: 'ticket',
                    metadata: {
                        seat_label: seat.name || seat.seat_number || seat.label || `${seat.row}${seat.number}`,
                        seat_type: seat.type || seat.seat_type || 'Ghế Thường'
                    },
                    seat: seat,
                    unit_price: seat.price || 0,
                    price: seat.price || 0,
                    quantity: seat.quantity || 1
                }));
            }
            
            if (order.payload?.products && Array.isArray(order.payload.products) && order.payload.products.length > 0) {
                products = order.payload.products.map(product => ({
                    item_type: 'product',
                    metadata: {
                        product_name: product.name,
                        product_description: product.description || ''
                    },
                    product: product,
                    quantity: product.quantity || 1,
                    unit_price: product.price || 0,
                    price: product.price || 0,
                    total_price: product.total_price || (product.quantity * product.price)
                }));
            }
            
            // PRIORITY 2: Try order.tickets array (alternative structure)
            if (tickets.length === 0) {
                const ticketSource = order.tickets || order.payload?.tickets || order.payload?.items?.tickets || [];
                if (ticketSource.length > 0) {
                    tickets = ticketSource.map(ticket => ({
                        item_type: 'ticket',
                        metadata: {
                            seat_label: ticket.seat?.seat_number || ticket.seat_number || ticket.name || ticket.metadata?.seat_label,
                            seat_type: ticket.seat?.seat_type?.name || ticket.seat_type || ticket.type || ticket.metadata?.seat_type || 'Ghế Thường'
                        },
                        seat: ticket.seat || ticket,
                        unit_price: ticket.price || ticket.unit_price || ticket.seat?.price || 0,
                        price: ticket.price || ticket.unit_price || ticket.seat?.price || 0,
                        quantity: ticket.quantity || 1
                    }));
                }
            }
            
            // PRIORITY 3: Try order.products array (alternative structure)
            if (products.length === 0) {
                const productSource = order.products || order.payload?.items?.products || [];
                if (productSource.length > 0) {
                    products = productSource.map(product => ({
                        item_type: 'product',
                        metadata: {
                            product_name: product.product?.name || product.name || product.metadata?.product_name,
                            product_description: product.product?.description || product.description || product.metadata?.product_description || ''
                        },
                        product: product.product || product,
                        quantity: product.quantity || 1,
                        unit_price: product.price || product.unit_price || product.product?.price || 0,
                        price: product.price || product.unit_price || product.product?.price || 0,
                        total_price: product.total_price || (product.quantity * (product.price || product.unit_price || 0))
                    }));
                }
            }
            
            // PRIORITY 4: Try cart_data or order_data (legacy structure)
            if (tickets.length === 0 && products.length === 0 && order.payload) {
                const cartData = order.payload.cart_data || order.payload.order_data;
                if (cartData) {
                    if (cartData.seats && Array.isArray(cartData.seats)) {
                        tickets = cartData.seats.map(seat => ({
                            item_type: 'ticket',
                            metadata: {
                                seat_label: seat.name || seat.seat_number || seat.label,
                                seat_type: seat.type || seat.seat_type || 'Ghế Thường'
                            },
                            seat: seat,
                            unit_price: seat.price || 0,
                            price: seat.price || 0,
                            quantity: 1
                        }));
                    }
                    if (cartData.products && Array.isArray(cartData.products)) {
                        products = cartData.products.map(p => ({
                            item_type: 'product',
                            metadata: {
                                product_name: p.name,
                                product_description: p.description || ''
                            },
                            product: p,
                            quantity: p.quantity || 1,
                            unit_price: p.price || 0,
                            price: p.price || 0,
                            total_price: p.total || (p.quantity * p.price)
                        }));
                    }
                }
            }
        }
        
        // Extract seat information
        if (tickets.length > 0) {
            seatsText = tickets.map(t => t.metadata?.seat_label || t.seat?.seat_number).filter(Boolean).join(', ');
            
        // Collect unique seat types for badge
        tickets.forEach(t => {
            const type = t.metadata?.seat_type || 'THƯỜNG';
            if (!seatTypes.includes(type)) seatTypes.push(type);
        });
        
        // Render Tickets in invoice (grouped by seat type)
        const ticketGroups = {};
        tickets.forEach(t => {
            const label = t.metadata?.seat_label || t.seat?.seat_number || '';
            const type = t.metadata?.seat_type || 'Ghế Thường';
            const price = parseFloat(t.unit_price || t.price || 0);
            if (!ticketGroups[type]) {
                ticketGroups[type] = {
                    type: type,
                    quantity: 0,
                    seats: [],
                    totalPrice: 0
                };
            }
            ticketGroups[type].quantity++;
            ticketGroups[type].seats.push(label);
            ticketGroups[type].totalPrice += price;
        });

        ticketsList.innerHTML = Object.values(ticketGroups).map(group => {
            return `
                <div class="d-flex justify-content-between mb-2">
                    <div>
                        <div class="text-white fw-semibold">Vé ${this.escapeHtml(group.type)} (x${this.escapeHtml(group.quantity)})</div>
                        <div class="text-muted" style="font-size: 0.8rem;">Ghế ${this.escapeHtml(group.seats.join(', '))}</div>
                    </div>
                    <div class="text-white">${group.totalPrice.toLocaleString('vi-VN')}đ</div>
                </div>
            `;
        }).join('');
        
        // Render Products in invoice
        productsList.innerHTML = products.map(p => {
            const name = p.metadata?.product_name || p.product?.name || 'Combo / Bắp nước';
            const qty = p.quantity || 1;
            const price = p.unit_price || p.price || 0;
            const total = p.total_price || (price * qty);
            const description = p.metadata?.product_description || p.product?.description || '';
            return `
                <div class="d-flex justify-content-between mb-2">
                    <div>
                        <div class="text-white fw-semibold">${this.escapeHtml(name)} (x${this.escapeHtml(qty)})</div>
                        ${description ? `<div class="text-muted" style="font-size: 0.8rem;">${this.escapeHtml(description)}</div>` : ''}
                    </div>
                    <div class="text-white">${parseFloat(total).toLocaleString('vi-VN')}đ</div>
                </div>
            `;
        }).join('');
        }
        
        document.getElementById('odModalSeats').textContent = seatsText || 'N/A';
        const seatTypeElement = document.getElementById('odModalSeatType');
        seatTypeElement.replaceChildren();
        const seatTypeIcon = document.createElement('i');
        seatTypeIcon.className = 'bi bi-star-fill me-1 text-warning';
        seatTypeElement.append(seatTypeIcon, document.createTextNode(` ${seatTypes.join(' & ')}`));
        
        // Status check - completed, confirmed, paid are successful
        const statusBadge = document.getElementById('odModalStatus');
        const orderStatus = String(order.status).toLowerCase();
        if (orderStatus === 'completed' || orderStatus === 'confirmed' || orderStatus === 'paid' || order.status_code === 2) {
            statusBadge.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i> THANH TOÁN THÀNH CÔNG`;
            statusBadge.style.backgroundColor = 'rgba(25, 135, 84, 0.15)';
            statusBadge.style.color = '#198754';
        } else if (orderStatus === 'pending' || order.status_code === 1) {
            statusBadge.innerHTML = `<i class="bi bi-clock-fill me-2"></i> CHỜ THANH TOÁN`;
            statusBadge.style.backgroundColor = 'rgba(255, 193, 7, 0.15)';
            statusBadge.style.color = '#ffc107';
        } else {
            statusBadge.innerHTML = `<i class="bi bi-x-circle-fill me-2"></i> ĐÃ HỦY`;
            statusBadge.style.backgroundColor = 'rgba(220, 53, 69, 0.15)';
            statusBadge.style.color = '#dc3545';
        }
        
        // Transaction/Payer Info
        const payerName = this.user ? `${this.user.name} (${this.user.email})` : 'N/A';
        document.getElementById('odModalPayerName').textContent = payerName;
        
        const method = order.payment_provider || order.payment?.method || 'Cổng thanh toán PayOS';
        document.getElementById('odModalPaymentMethod').textContent = method;
        
        if (order.created_at) {
            const date = new Date(order.created_at);
            const dateString = date.toLocaleDateString('vi-VN');
            const timeString = date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('odModalTxDate').textContent = `${timeString} - ${dateString}`;
        } else {
            document.getElementById('odModalTxDate').textContent = 'N/A';
        }
        
        // Generate Barcode SVG
        const barcodeContainer = document.getElementById('odModalBarcodeContainer');
        if (barcodeContainer) {
            barcodeContainer.innerHTML = this.generateBarcodeSVG(orderCode);
        }
        
        const promoCode = order.invoice?.promotion?.code || order.payload?.promotion?.code || order.payload?.voucher?.code;
        const voucherDiscount = Number(order.invoice?.voucher_discount ?? order.payload?.voucher_discount ?? 0) || 0;
        const pointDiscount = Number(order.invoice?.point_discount ?? order.payload?.point_discount ?? 0) || 0;
        const discountAmount = Number(order.invoice?.discount_amount ?? order.payload?.discount_amount ?? 0) || 0;
        const displayedVoucherDiscount = voucherDiscount || Math.max(0, discountAmount - pointDiscount);
        const voucherList = document.getElementById('odModalVoucherList');
        
        if (promoCode && displayedVoucherDiscount > 0) {
            voucherList.classList.remove('d-none');
            document.getElementById('odModalVoucherCode').textContent = promoCode;
            document.getElementById('odModalVoucherValue').textContent = `-${displayedVoucherDiscount.toLocaleString('vi-VN')}đ`;
        } else {
            voucherList.classList.add('d-none');
        }

        const pointsList = document.getElementById('odModalPointsList');
        const pointsUsed = Number(order.invoice?.points_used ?? order.payload?.points_used ?? 0) || 0;
        if (pointDiscount > 0) {
            pointsList.classList.remove('d-none');
            document.getElementById('odModalPointsUsed').textContent = pointsUsed.toLocaleString('vi-VN');
            document.getElementById('odModalPointsValue').textContent = `-${pointDiscount.toLocaleString('vi-VN')}đ`;
        } else {
            pointsList.classList.add('d-none');
        }

        const subtotal = Number(order.invoice?.subtotal ?? order.payload?.subtotal ?? order.total_amount ?? 0) || 0;
        document.getElementById('odModalSubtotal').textContent = `${subtotal.toLocaleString('vi-VN')}đ`;
        
        document.getElementById('odModalTotal').textContent = `${(Number(order.total_amount) || 0).toLocaleString('vi-VN')}đ`;
        
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    generateBarcodeSVG(code) {
        return window.generateProfileBarcodeSvg(code, (value) => this.escapeHtml(value));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.profilePage = new ProfilePage();
});
