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

    createErrorAlert(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.textContent = message;
        return alert;
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

        this.setText(this.elements.displayName, user.name || 'Người dùng');
        this.setText(this.elements.memberRank, `Thành viên Cinema từ ${memberSince}`);
        this.setText(this.elements.xpValue, `${points.toLocaleString('vi-VN')} điểm`);

        if (this.elements.xpProgress) {
            this.elements.xpProgress.style.width = `${progress}%`;
        }

        if (this.elements.xpMessage) {
            const remaining = Math.max(0, 1000 - points);
            this.elements.xpMessage.textContent = remaining > 0
                ? `Còn ${remaining.toLocaleString('vi-VN')} điểm để đạt mốc ưu đãi tiếp theo.`
                : 'Bạn đã đạt mốc ưu đãi Cinema. Tiếp tục đặt vé để duy trì quyền lợi.';
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

        if (this.elements.avatarFallback) {
            this.elements.avatarFallback.textContent = fallbackText;
        }

        if (!this.elements.avatar || !this.elements.avatarFallback) return;

        if (avatarUrl) {
            this.elements.avatar.src = avatarUrl;
            this.elements.avatar.classList.remove('d-none');
            this.elements.avatarFallback.classList.add('d-none');

            this.elements.avatar.onerror = () => {
                this.elements.avatar.classList.add('d-none');
                this.elements.avatarFallback.classList.remove('d-none');
            };
        } else {
            this.elements.avatar.classList.add('d-none');
            this.elements.avatarFallback.classList.remove('d-none');
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
                body: JSON.stringify(payload),
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
        const payload = {
            current_password: document.getElementById('currentPassword')?.value || '',
            new_password: newPassword,
            new_password_confirmation: document.getElementById('newPasswordConfirmation')?.value || newPassword,
        };

        try {
            const response = await this.apiRequest('/auth/change-password', {
                method: 'POST',
                body: JSON.stringify(payload),
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

        // Show/hide sections based on nav
        if (nav === 'profile') {
            document.getElementById('profileSection')?.classList.remove('d-none');
            document.getElementById('ticketsSection')?.classList.add('d-none');
            document.querySelector('.profile-card-grid')?.classList.remove('d-none');
            document.querySelector('.profile-cover-card')?.classList.remove('d-none');
        } else if (nav === 'tickets') {
            document.getElementById('profileSection')?.classList.add('d-none');
            document.getElementById('ticketsSection')?.classList.remove('d-none');
            document.querySelector('.profile-card-grid')?.classList.add('d-none');
            document.querySelector('.profile-cover-card')?.classList.add('d-none');
            this.ticketFilter = 'all';
            this.ticketPage = 1;
            this.loadTickets();
        } else {
            alert('Chức năng đang phát triển');
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
                ticketsList.appendChild(this.createLoadingSpinner());
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

                filteredOrders.forEach(order => {
                    const card = this.createTicketCard(order);
                    ticketsList.appendChild(card);
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
                ticketsList.appendChild(this.createErrorAlert('Không thể tải danh sách vé'));
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

        // Poster
        const poster = card.querySelector('.ticket-poster');
        if (poster && order.showtime?.movie?.poster) {
            poster.src = order.showtime.movie.poster;
            poster.alt = order.showtime.movie.title;
        }

        // Formats (IMAX, 4DX, Dolby Atmos, etc.)
        const formatsContainer = card.querySelector('.ticket-formats');
        if (formatsContainer) {
            this.clearContainer(formatsContainer);
            if (order.showtime?.format) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-dark text-white me-1';
                badge.textContent = order.showtime.format.name;
                formatsContainer.appendChild(badge);
            }
            if (order.showtime?.sound) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-dark text-white me-1';
                badge.textContent = order.showtime.sound.name;
                formatsContainer.appendChild(badge);
            }
            if (order.showtime?.subtitle) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-dark text-white me-1';
                badge.textContent = order.showtime.subtitle.name;
                formatsContainer.appendChild(badge);
            }
        }

        // Ticket ID
        const ticketId = card.querySelector('.ticket-id');
        if (ticketId) {
            ticketId.textContent = `ID: #CP-${String(order.id).padStart(5, '0')}`;
        }

        // Title
        const title = card.querySelector('.ticket-title');
        if (title) {
            title.textContent = order.showtime?.movie?.title || 'N/A';
        }

        // Showtime
        const showtime = card.querySelector('.ticket-showtime');
        if (showtime && order.showtime?.show_date && order.showtime?.show_time) {
            const date = new Date(order.showtime.show_date);
            showtime.textContent = `${date.toLocaleDateString('vi-VN')} - ${order.showtime.show_time.slice(0, 5)}`;
        }

        // Theater
        const theater = card.querySelector('.ticket-theater');
        if (theater && order.showtime?.screen) {
            theater.textContent = `${order.showtime.screen.theater?.branch?.name || ''} - ${order.showtime.screen.name || ''}`;
        }

        // Seats
        const seats = card.querySelector('.ticket-seats');
        if (seats && order.order_items) {
            const seatNames = order.order_items.map(item => item.seat?.seat_number).filter(Boolean).join(', ');
            seats.textContent = seatNames || 'N/A';
        }

        // Status
        const status = card.querySelector('.ticket-status');
        if (status) {
            this.renderTicketStatus(status, order.status);
        }

        // Rebook button
        const rebookBtn = card.querySelector('.ticket-rebook-btn');
        if (rebookBtn && order.showtime?.movie?.slug) {
            rebookBtn.addEventListener('click', () => {
                window.location.href = `/movies/${order.showtime.movie.slug}`;
            });
        }

        return card;
    }

    renderTicketStatus(container, status) {
        if (!container) return;

        container.textContent = '';

        const statusConfig = {
            completed: {
                iconClass: 'bi-check-circle-fill',
                textClass: 'text-success',
                label: 'Đã hoàn thành',
            },
            pending: {
                iconClass: 'bi-clock-fill',
                textClass: 'text-warning',
                label: 'Chờ thanh toán',
            },
            cancelled: {
                iconClass: 'bi-x-circle-fill',
                textClass: 'text-danger',
                label: 'Đã hủy',
            },
        };

        const config = statusConfig[status] || {
            iconClass: 'bi-info-circle-fill',
            textClass: 'text-secondary',
            label: String(status || 'Không rõ'),
        };

        const icon = document.createElement('i');
        icon.className = `bi ${config.iconClass} ${config.textClass} me-1`;

        const label = document.createElement('span');
        label.className = config.textClass;
        label.textContent = config.label;

        container.appendChild(icon);
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
}

document.addEventListener('DOMContentLoaded', () => {
    window.profilePage = new ProfilePage();
});
