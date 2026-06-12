/**
 * Profile Page JavaScript
 * Handles: profile info display, form editing, password change, XP/points.
 * Ticket history is handled by the dedicated /my-tickets page (TicketsPage class).
 */
class ProfilePage {
    constructor() {
        this.user = null;
        this.editableFieldIds = ['profileName', 'profileGender', 'profileAddress'];
        this.elements = {};
        
        // Tickets State
        this.tickets = [];
        this.ticketPage = 1;
        this.ticketLastPage = 1;
        this.ticketPerPage = 10;
        this.ticketFilter = 'all';
        
        this.init();
    }

    async init() {
        this.cacheElements();
        this.bindEvents();
        await this.loadProfile();
        
        // Handle initial navigation via hash
        const hash = window.location.hash.replace('#', '');
        if (hash === 'tickets' || hash === 'points' || hash === 'voucher') {
            this.handleNavigation(hash);
        }
    }

    // ─── Element Cache ────────────────────────────────────────────────────────

    cacheElements() {
        this.elements = {
            loading:         document.getElementById('profileLoading'),
            content:         document.getElementById('profileContent'),
            avatar:          document.getElementById('profileAvatar'),
            avatarFallback:  document.getElementById('profileAvatarFallback'),
            displayName:     document.getElementById('profileDisplayName'),
            memberRank:      document.getElementById('profileMemberRank'),
            logoutBtn:       document.getElementById('profileLogoutBtn'),
            scrollTopBtn:    document.querySelector('.profile-scroll-top'),
            updateForm:      document.getElementById('profileUpdateForm'),
            passwordForm:    document.getElementById('profilePasswordForm'),
            resetBtn:        document.getElementById('profileResetBtn'),
            updateBtn:       document.getElementById('profileUpdateBtn'),
            passwordBtn:     document.getElementById('profilePasswordBtn'),
            updateAlert:     document.getElementById('profileUpdateAlert'),
            passwordAlert:   document.getElementById('profilePasswordAlert'),
            nameInput:       document.getElementById('profileName'),
            emailInput:      document.getElementById('profileEmail'),
            phoneInput:      document.getElementById('profilePhone'),
            birthdayInput:   document.getElementById('profileBirthday'),
            genderInput:     document.getElementById('profileGender'),
            addressInput:    document.getElementById('profileAddress'),
            xpValue:         document.getElementById('profileXpValue'),
            xpProgress:      document.getElementById('profileXpProgress'),
            xpMessage:       document.getElementById('profileXpMessage'),
            
            // Tickets DOM
            ticketsSection:  document.getElementById('ticketsSection'),
            ticketsLoading:  document.getElementById('ticketsLoading'),
            ticketsEmpty:    document.getElementById('ticketsEmpty'),
            ticketsGrid:     document.getElementById('ticketsGrid'),
            ticketsPagination: document.getElementById('ticketsPagination'),
            statusFilters:   document.querySelectorAll('.tickets-tab[data-filter-status]'),
            profileSection:  document.getElementById('profileSection'),
            xpCard:          document.querySelector('.profile-xp-card'),
            coverCard:       document.querySelector('.profile-cover-card'),
        };
    }

    // ─── Events ───────────────────────────────────────────────────────────────

    bindEvents() {
        // Nav buttons (only "profile" nav remains; "tickets" is now an <a> link)
        document.querySelectorAll('[data-profile-nav]').forEach(btn => {
            btn.addEventListener('click', () => this.handleNavigation(btn.dataset.profileNav));
        });

        // Inline field edit pencils
        document.querySelectorAll('[data-edit-field]').forEach(btn => {
            btn.addEventListener('click', () => this.enableFieldEditing(btn.dataset.editField));
        });

        this.elements.updateForm?.addEventListener('submit',  e => this.handleUpdateProfile(e));
        this.elements.passwordForm?.addEventListener('submit', e => this.handleChangePassword(e));
        this.elements.resetBtn?.addEventListener('click', () => this.populateForms());
        this.elements.logoutBtn?.addEventListener('click', () => window.authManager?.handleLogout?.());
        this.elements.scrollTopBtn?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

        // Ticket Events
        this.elements.statusFilters.forEach(btn => {
            btn.addEventListener('click', () => this.handleFilterChange(btn.dataset.filterStatus));
        });

        document.getElementById('ticketDetailModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'ticketDetailModal') this.closeModal();
        });
        document.getElementById('ticketModalClose')?.addEventListener('click', () => this.closeModal());
    }

    // ─── Auth & Load ──────────────────────────────────────────────────────────

    async loadProfile() {
        this.setLoading(true);

        try {
            // Wait for authManager to finish its initial check (up to 5 s)
            if (window.authManager && !window.authManager.authChecked) {
                let attempts = 0;
                while (!window.authManager.authChecked && attempts < 50) {
                    await new Promise(r => setTimeout(r, 100));
                    attempts++;
                }
            }

            if (!window.authManager?.isAuthenticated()) {
                this.showAuthRequired();
                return;
            }

            const response = await this.apiRequest('/auth/profile');
            this.user = response.data?.user || response.data || null;

            if (!this.user) throw new Error('Không thể tải dữ liệu hồ sơ.');

            // Sync user into authManager so navbar updates
            if (window.authManager) {
                window.authManager.user = this.user;
                window.authManager.updateUI();
            }

            this.renderProfile();
            this.populateForms();
            this.showContent();

        } catch (err) {
            console.error('[Profile] Load error:', err);

            if (err.message?.includes('Session expired') || err.message?.includes('Unauthenticated')) {
                this.showAuthRequired();
                return;
            }

            this.showContent();
            this.showAlert(this.elements.updateAlert, err.message || 'Không thể tải hồ sơ.', 'danger');
        } finally {
            this.setLoading(false);
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    renderProfile() {
        const user    = this.user || {};
        const year    = this.getYear(user.created_at);
        const points  = Number(user.loyalty_points || 0);
        const progress = Math.min(100, Math.round((points / 1000) * 100));

        this.setText(this.elements.displayName, user.name || 'Người dùng');
        this.setText(this.elements.memberRank,  `Thành viên Cinema từ ${year}`);
        this.setText(this.elements.xpValue,     `${points.toLocaleString('vi-VN')} điểm`);

        if (this.elements.xpProgress) this.elements.xpProgress.style.width = `${progress}%`;

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
        if (this.elements.nameInput)     this.elements.nameInput.value     = user.name     || '';
        if (this.elements.emailInput)    this.elements.emailInput.value    = user.email    || '';
        if (this.elements.phoneInput)    this.elements.phoneInput.value    = user.phone    || '';
        if (this.elements.birthdayInput) this.elements.birthdayInput.value = this.toDateInput(user.birthday);
        if (this.elements.genderInput)   this.elements.genderInput.value   = user.gender   || '';
        if (this.elements.addressInput)  this.elements.addressInput.value  = user.address  || '';

        this.clearFormErrors(this.elements.updateForm);
        this.hideAlert(this.elements.updateAlert);
        this.disableEditableFields();
        this.renderAvatar(user.avatar_url, user.name);
    }

    renderAvatar(url, name = 'U') {
        const fallback = (name || 'U').trim().charAt(0).toUpperCase() || 'U';
        if (this.elements.avatarFallback) this.elements.avatarFallback.textContent = fallback;

        if (!this.elements.avatar || !this.elements.avatarFallback) return;

        if (url) {
            this.elements.avatar.src = url;
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

    // ─── Editable Fields ──────────────────────────────────────────────────────

    enableFieldEditing(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.disabled = false;
        field.focus();
        if (field.tagName !== 'SELECT' && field.select) field.select();

        document.querySelector(`[data-edit-field="${fieldId}"]`)?.classList.add('is-active');

        if (this.elements.updateBtn) this.elements.updateBtn.disabled = false;
        if (this.elements.resetBtn)  this.elements.resetBtn.disabled  = false;
    }

    disableEditableFields() {
        this.editableFieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = true;
        });
        document.querySelectorAll('[data-edit-field]').forEach(btn => btn.classList.remove('is-active'));
        if (this.elements.updateBtn) this.elements.updateBtn.disabled = true;
        if (this.elements.resetBtn)  this.elements.resetBtn.disabled  = true;
    }

    // ─── Navigation ───────────────────────────────────────────────────────────

    handleNavigation(nav) {
        document.querySelectorAll('[data-profile-nav]').forEach(item => {
            item.classList.toggle('active', item.dataset.profileNav === nav);
        });

        const { ticketsSection, profileSection, xpCard, coverCard } = this.elements;

        if (nav === 'profile') {
            ticketsSection?.classList.add('d-none');
            profileSection?.classList.remove('d-none');
            xpCard?.classList.remove('d-none');
            coverCard?.classList.remove('d-none');
        } else if (nav === 'tickets') {
            profileSection?.classList.add('d-none');
            xpCard?.classList.add('d-none');
            coverCard?.classList.add('d-none');
            ticketsSection?.classList.remove('d-none');
            
            if (this.tickets.length === 0) {
                this.loadTickets();
            }
        } else {
            // Future sections (points, voucher) — placeholder
            window.authManager?.showToast?.('Chức năng đang phát triển.', 'info');
        }
    }

    // ─── Form Handlers ────────────────────────────────────────────────────────

    async handleUpdateProfile(e) {
        e.preventDefault();

        this.clearFormErrors(this.elements.updateForm);
        this.hideAlert(this.elements.updateAlert);
        this.setButtonLoading(this.elements.updateBtn, true);

        const payload = {
            name:     this.elements.nameInput?.value?.trim()    || '',
            phone:    this.elements.phoneInput?.value?.trim()   || '',
            birthday: this.elements.birthdayInput?.value        || null,
            gender:   this.elements.genderInput?.value          || null,
            address:  this.elements.addressInput?.value?.trim() || null,
        };

        try {
            const response = await this.apiRequest('/auth/profile', {
                method: 'PUT',
                body:   JSON.stringify(payload),
            });

            this.user = response.data?.user || response.data || { ...this.user, ...payload };

            if (window.authManager) {
                window.authManager.user = this.user;
                window.authManager.updateUI();
            }

            this.renderProfile();
            this.populateForms();
            this.showAlert(this.elements.updateAlert, response.message || 'Cập nhật hồ sơ thành công.', 'success');
            window.authManager?.showToast?.('Cập nhật hồ sơ thành công.', 'success');

        } catch (err) {
            this.handleFormError(err, this.elements.updateAlert, this.elements.updateForm);
        } finally {
            this.setButtonLoading(this.elements.updateBtn, false);
            if (this.elements.updateBtn) this.elements.updateBtn.disabled = true;
        }
    }

    async handleChangePassword(e) {
        e.preventDefault();

        this.clearFormErrors(this.elements.passwordForm);
        this.hideAlert(this.elements.passwordAlert);
        this.setButtonLoading(this.elements.passwordBtn, true);

        const newPwd = document.getElementById('newPassword')?.value || '';
        const payload = {
            current_password:      document.getElementById('currentPassword')?.value || '',
            new_password:          newPwd,
            new_password_confirmation: document.getElementById('newPasswordConfirmation')?.value || newPwd,
        };

        try {
            const response = await this.apiRequest('/auth/change-password', {
                method: 'POST',
                body:   JSON.stringify(payload),
            });

            this.elements.passwordForm?.reset();
            this.showAlert(this.elements.passwordAlert, response.message || 'Đổi mật khẩu thành công.', 'success');
            window.authManager?.showToast?.('Đổi mật khẩu thành công.', 'success');

        } catch (err) {
            this.handleFormError(err, this.elements.passwordAlert, this.elements.passwordForm);
        } finally {
            this.setButtonLoading(this.elements.passwordBtn, false);
        }
    }

    // ─── Error Handling ───────────────────────────────────────────────────────

    handleFormError(err, alertEl, form = null) {
        this.showAlert(alertEl, err.message || 'Có lỗi xảy ra. Vui lòng thử lại.', 'danger');

        const errors = err.errors || err.data?.errors;
        if (!errors || !form) return;

        Object.entries(errors).forEach(([field, messages]) => {
            const input    = form.querySelector(`[name="${field}"]`);
            const feedback = input?.closest('.profile-form-group')?.querySelector('.invalid-feedback');
            input?.classList.add('is-invalid');
            if (feedback) feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
        });
    }

    clearFormErrors(form) {
        if (!form) return;
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => { el.textContent = ''; });
    }

    // ─── API ──────────────────────────────────────────────────────────────────

    async apiRequest(endpoint, options = {}) {
        if (window.authManager?.apiRequest) return window.authManager.apiRequest(endpoint, options);
        if (window.authManager?.fetchAPI)   return window.authManager.fetchAPI(endpoint, options);
        throw new Error('Không tìm thấy trình quản lý xác thực.');
    }

    // ─── UI Helpers ───────────────────────────────────────────────────────────

    setLoading(on) {
        this.elements.loading?.classList.toggle('d-none', !on);
    }

    showContent() {
        this.elements.content?.classList.remove('d-none');
    }

    showAuthRequired() {
        this.elements.content?.classList.add('d-none');
        // Auth-required block is rendered server-side for guests, nothing to toggle here
    }

    setButtonLoading(btn, on) {
        if (!btn) return;
        btn.disabled = on;
        btn.querySelector('.spinner-border')?.classList.toggle('d-none', !on);
    }

    showAlert(el, message, type = 'info') {
        if (!el) return;
        el.textContent = message;
        el.className   = `alert alert-${type} profile-alert`;
        el.classList.remove('d-none');
    }

    hideAlert(el) { el?.classList.add('d-none'); }
    setText(el, text) { if (el) el.textContent = text; }

    toDateInput(value) {
        if (!value) return '';
        const d = new Date(value);
        return isNaN(d.getTime()) ? String(value).slice(0, 10) : d.toISOString().slice(0, 10);
    }

    getYear(value) {
        if (!value) return '2022';
        const d = new Date(value);
        return isNaN(d.getTime()) ? '2022' : String(d.getFullYear());
    }

    // ─── Tickets Logic ────────────────────────────────────────────────────────

    async loadTickets(page = 1) {
        try {
            this.elements.ticketsLoading?.classList.remove('d-none');
            this.elements.ticketsGrid?.classList.add('d-none');
            this.elements.ticketsEmpty?.classList.add('d-none');
            this.ticketPage = page;

            const params = new URLSearchParams({ page, per_page: this.ticketPerPage });
            if (this.ticketFilter !== 'all') params.append('status', this.ticketFilter);

            const result = await this.apiRequest(`/tickets?${params}`);

            if (!result.success) throw new Error(result.message || 'Không thể tải vé.');

            this.tickets = result.data.data || [];
            this.ticketPage = result.data.meta.current_page;
            this.ticketLastPage = result.data.meta.last_page;

            this.renderTickets();
            this.renderPagination();
        } catch (err) {
            console.error('[Profile] Load tickets error:', err);
            window.authManager?.showToast?.('Lỗi tải danh sách vé: ' + err.message, 'danger');
        } finally {
            this.elements.ticketsLoading?.classList.add('d-none');
            this.elements.ticketsGrid?.classList.remove('d-none');
        }
    }

    renderTickets() {
        if (!this.elements.ticketsGrid) return;

        if (this.tickets.length === 0) {
            this.elements.ticketsGrid.innerHTML = '';
            this.elements.ticketsEmpty?.classList.remove('d-none');
            return;
        }

        this.elements.ticketsEmpty?.classList.add('d-none');
        this.elements.ticketsGrid.innerHTML = this.tickets.map(t => this.buildTicketCard(t)).join('');
    }

    buildTicketCard(ticket) {
        const movie      = ticket.showtime?.movie  || {};
        const screen     = ticket.showtime?.screen || {};
        const theater    = screen.theater          || {};
        const seat       = ticket.seat             || {};
        const seatType   = seat.seat_type          || {};
        const order      = ticket.order            || {};

        const title       = this.esc(movie.title     || 'Chưa rõ');
        const poster      = movie.poster_url          || '/images/placeholder.jpg';
        const rating      = movie.age_rating          || '';
        const theaterName = this.esc(theater.name    || 'Chưa rõ');
        const screenName  = this.esc(screen.name     || '');
        const seatLabel   = this.esc(seat.label || (`${seat.row || ''}${seat.number || ''}`).trim() || 'N/A');
        const seatTypeName = this.esc(seatType.name  || 'Thường');
        const showtime    = this.formatDateTime(ticket.showtime?.scheduled_at);
        const amount      = order.total_amount ? this.formatCurrency(order.total_amount) : '';
        const isUpcoming  = ticket.showtime?.scheduled_at && new Date(ticket.showtime.scheduled_at) > new Date();

        const { cls: statusCls, label: statusLabel, dot: dotColor } = this.getStatusMeta(ticket.status);
        const upcoming = isUpcoming && ticket.status === 'valid';

        return `
        <article class="ticket-card ${upcoming ? 'ticket-card--upcoming' : ''}">
            <div class="ticket-poster">
                <img class="ticket-poster-img" src="${poster}" alt="${title}" loading="lazy"
                     onerror="this.src='/images/placeholder.jpg'">
                ${rating ? `<div class="ticket-formats"><span class="ticket-format-badge">${this.esc(rating)}</span></div>` : ''}
                ${upcoming ? '<div class="ticket-upcoming-badge"><i class="bi bi-clock-fill"></i> Sắp chiếu</div>' : ''}
            </div>

            <div class="ticket-details">
                <div class="ticket-header">
                    <span class="ticket-id">#${this.esc(ticket.ticket_code)}</span>
                </div>
                <h3 class="ticket-title">${title}</h3>
                <div class="ticket-info">
                    <div class="ticket-info-item">
                        <span class="ticket-info-label"><i class="bi bi-calendar3"></i> NGÀY CHIẾU</span>
                        <span class="ticket-info-value ticket-showtime">${showtime}</span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label"><i class="bi bi-geo-alt"></i> RẠP CHIẾU</span>
                        <span class="ticket-info-value ticket-theater">${theaterName}${screenName ? ` · ${screenName}` : ''}</span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label"><i class="bi bi-person-check"></i> GHẾ</span>
                        <span class="ticket-info-value ticket-seats">${seatLabel} <small class="ticket-seat-type">(${seatTypeName})</small></span>
                    </div>
                </div>
                ${amount ? `<div class="ticket-amount"><i class="bi bi-receipt"></i> ${amount}</div>` : ''}
            </div>

            <div class="ticket-actions">
                <div class="ticket-status ${statusCls}">
                    <span class="ticket-status-dot" style="background:${dotColor}"></span>
                    ${statusLabel}
                </div>
                <button class="ticket-detail-btn" type="button"
                        onclick="window.profilePage.showTicketDetail('${this.esc(ticket.ticket_code)}')">
                    <i class="bi bi-eye"></i> Chi tiết
                </button>
                ${upcoming ? `
                <a class="ticket-rebook-btn" href="/movies">
                    <i class="bi bi-ticket-perforated"></i> Đặt thêm
                </a>` : `
                <button class="ticket-rebook-btn" type="button"
                        onclick="window.profilePage.rebookTicket('${this.esc(movie.slug || '')}')">
                    <i class="bi bi-arrow-repeat"></i> Đặt lại
                </button>`}
            </div>
        </article>`;
    }

    async showTicketDetail(ticketCode) {
        const modal = document.getElementById('ticketDetailModal');
        if (!modal) return;

        const body = modal.querySelector('.ticket-modal-body');
        if (body) body.innerHTML = `<div class="ticket-modal-loading"><div class="spinner-border text-danger"></div><p>Đang tải...</p></div>`;
        modal.classList.add('show');
        document.body.classList.add('modal-open');

        try {
            const result = await this.apiRequest(`/tickets/${encodeURIComponent(ticketCode)}`);
            if (!result.success) throw new Error(result.message);
            this.renderModal(result.data);
        } catch (err) {
            if (body) body.innerHTML = `<div class="alert alert-danger m-4">${this.esc(err.message)}</div>`;
        }
    }

    renderModal(ticket) {
        const modal = document.getElementById('ticketDetailModal');
        const body  = modal?.querySelector('.ticket-modal-body');
        if (!body) return;

        const movie   = ticket.showtime?.movie  || {};
        const screen  = ticket.showtime?.screen || {};
        const theater = screen.theater          || {};
        const seat    = ticket.seat             || {};
        const order   = ticket.order            || {};
        const { label: statusLabel, cls: statusCls } = this.getStatusMeta(ticket.status);

        body.innerHTML = `
        <div class="ticket-modal-hero">
            <img src="${movie.poster_url || '/images/placeholder.jpg'}"
                 alt="${this.esc(movie.title || '')}"
                 onerror="this.src='/images/placeholder.jpg'">
            <div class="ticket-modal-hero-info">
                <span class="badge ${statusCls} mb-2">${statusLabel}</span>
                <h2>${this.esc(movie.title || 'Chưa rõ')}</h2>
                ${movie.age_rating ? `<span class="ticket-format-badge">${this.esc(movie.age_rating)}</span>` : ''}
                ${movie.duration ? `<small class="text-muted ms-2">${movie.duration} phút</small>` : ''}
            </div>
        </div>

        <div class="ticket-modal-grid">
            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-film"></i> Suất chiếu</h6>
                <div class="ticket-modal-row"><span>Thời gian</span><strong>${this.formatDateTime(ticket.showtime?.scheduled_at)}</strong></div>
                <div class="ticket-modal-row"><span>Rạp</span><strong>${this.esc(theater.name || 'N/A')}</strong></div>
                ${theater.address ? `<div class="ticket-modal-row"><span>Địa chỉ</span><span class="text-muted">${this.esc(theater.address)}${theater.city ? ', ' + this.esc(theater.city) : ''}</span></div>` : ''}
                <div class="ticket-modal-row"><span>Phòng chiếu</span><strong>${this.esc(screen.name || 'N/A')}</strong></div>
            </div>

            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-person-check"></i> Thông tin ghế</h6>
                <div class="ticket-modal-row"><span>Ghế</span><strong>${this.esc(seat.label || `${seat.row || ''}${seat.number || ''}`)}</strong></div>
                <div class="ticket-modal-row"><span>Loại ghế</span><strong>${this.esc(seat.seat_type?.name || 'Thường')}</strong></div>
            </div>

            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-receipt"></i> Đơn hàng</h6>
                <div class="ticket-modal-row"><span>Mã đơn</span><code>${this.esc(order.code || 'N/A')}</code></div>
                <div class="ticket-modal-row"><span>Tổng tiền</span><strong class="text-danger">${order.total_amount ? this.formatCurrency(order.total_amount) : 'N/A'}</strong></div>
                ${ticket.checked_in_at ? `<div class="ticket-modal-row"><span>Check-in lúc</span><span>${this.formatDateTime(ticket.checked_in_at)}</span></div>` : ''}
            </div>
        </div>

        <div class="ticket-modal-code-block">
            <div class="ticket-modal-code-label">MÃ VÉ</div>
            <div class="ticket-modal-code">${this.esc(ticket.ticket_code)}</div>
            ${ticket.qr_code ? `<img src="${ticket.qr_code}" class="ticket-modal-qr" alt="QR Code">` : ''}
            <p class="ticket-modal-note"><i class="bi bi-info-circle"></i> Vui lòng xuất trình mã vé này tại quầy soát vé của rạp.</p>
        </div>`;
    }

    closeModal() {
        const modal = document.getElementById('ticketDetailModal');
        modal?.classList.remove('show');
        document.body.classList.remove('modal-open');
    }

    rebookTicket(movieSlug) {
        if (movieSlug) window.location.href = `/movies/${movieSlug}`;
        else window.location.href = '/movies';
    }

    renderPagination() {
        if (!this.elements.ticketsPagination || this.ticketLastPage <= 1) {
            if (this.elements.ticketsPagination) this.elements.ticketsPagination.innerHTML = '';
            return;
        }

        const start = Math.max(1, this.ticketPage - 2);
        const end   = Math.min(this.ticketLastPage, this.ticketPage + 2);

        let html = '<nav aria-label="Phân trang"><ul class="pagination justify-content-center">';
        html += this.pageItem('&laquo;', this.ticketPage - 1, this.ticketPage === 1);
        if (start > 1) { html += this.pageItem('1', 1); if (start > 2) html += '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
        for (let i = start; i <= end; i++) html += this.pageItem(i, i, false, i === this.ticketPage);
        if (end < this.ticketLastPage) { if (end < this.ticketLastPage - 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>'; html += this.pageItem(this.ticketLastPage, this.ticketLastPage); }
        html += this.pageItem('&raquo;', this.ticketPage + 1, this.ticketPage === this.ticketLastPage);
        html += '</ul></nav>';

        this.elements.ticketsPagination.innerHTML = html;
    }

    pageItem(label, page, disabled = false, active = false) {
        if (disabled) return `<li class="page-item disabled"><span class="page-link">${label}</span></li>`;
        if (active)   return `<li class="page-item active"><span class="page-link">${label}</span></li>`;
        return `<li class="page-item"><a class="page-link" href="#" onclick="window.profilePage.loadTickets(${page});return false;">${label}</a></li>`;
    }

    handleFilterChange(status) {
        this.ticketFilter = status;
        this.elements.statusFilters.forEach(btn => btn.classList.toggle('active', btn.dataset.filterStatus === status));
        this.loadTickets(1);
    }

    getStatusMeta(status) {
        const map = {
            valid:     { cls: 'text-success',  label: 'Còn hạn',      dot: '#22d3a6' },
            used:      { cls: 'text-secondary', label: 'Đã sử dụng',   dot: '#888'    },
            cancelled: { cls: 'text-danger',    label: 'Đã hủy',       dot: '#ff111d' },
            refunded:  { cls: 'text-warning',   label: 'Đã hoàn tiền', dot: '#f59e0b' },
        };
        return map[status] || { cls: 'text-secondary', label: status, dot: '#888' };
    }

    formatDateTime(dt) {
        if (!dt) return 'N/A';
        try {
            return new Date(dt).toLocaleString('vi-VN', {
                weekday: 'short', year: 'numeric', month: '2-digit',
                day: '2-digit', hour: '2-digit', minute: '2-digit',
            });
        } catch { return 'N/A'; }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
    }

    esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.profilePage = new ProfilePage();
});
