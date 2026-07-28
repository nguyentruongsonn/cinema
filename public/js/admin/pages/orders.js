/**
 * ════════════════════════════════════════════════════════════════════════════
 * ADMIN ORDERS MANAGEMENT - CARD LAYOUT
 * Following user tickets pattern from profile.js
 * ════════════════════════════════════════════════════════════════════════════
 */

(function () {
    'use strict';

class AdminOrdersManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 10;
        this.filters = {
            status: 'all',
            branch_id: '',
            theater_id: '',
            movie_id: '',
            date: '',
            search: ''
        };
        
        this.orders = [];
        this.lastPage = 1;
        this.theaterOptions = [];
        this.loadRequest = null;
        this.loadRequestId = 0;
        this.detailRequest = null;
        this.detailRequestId = 0;
        this.previousFocus = null;
        this.lifecycleController = new AbortController();
        
        this.initElements();
        this.attachEvents();
        this.loadFiltersData();
        this.loadOrders();
    }
    
    initElements() {
        this.els = {
            // Loading & Empty states
            loading: document.getElementById('ordersLoading'),
            empty: document.getElementById('ordersEmpty'),
            grid: document.getElementById('ordersGrid'),
            pagination: document.getElementById('ordersPagination'),
            orderCount: document.getElementById('orderCount'),
            
            // Filters
            statusTabs: document.querySelectorAll('[data-filter-status]'),
            branchFilter: document.getElementById('branchFilter'),
            theaterFilter: document.getElementById('theaterFilter'),
            movieFilter: document.getElementById('movieFilter'),
            dateFilter: document.getElementById('dateFilter'),
            searchFilter: document.getElementById('searchFilter'),
            applyFilterBtn: document.getElementById('btnApplyFilter'),
            
            // Modal
            modal: document.getElementById('orderDetailModal'),
            modalClose: document.getElementById('orderModalClose'),
            modalBody: document.querySelector('#orderDetailModal .ticket-modal-body')
        };
    }
    
    attachEvents() {
        // Status tabs
        this.els.statusTabs?.forEach(tab => {
            tab.addEventListener('click', () => {
                this.els.statusTabs.forEach(t => t.classList.remove('active'));
                this.els.statusTabs.forEach(t => t.setAttribute('aria-selected', 'false'));
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                this.filters.status = tab.dataset.filterStatus;
                this.currentPage = 1;
                this.loadOrders();
            });
        });
        
        // Branch filter cascade
        this.els.branchFilter?.addEventListener('change', (e) => {
            this.filters.branch_id = e.target.value;
            this.filters.theater_id = '';
            this.populateTheaterFilter(e.target.value);
            this.currentPage = 1;
            this.loadOrders();
        });

        this.els.theaterFilter?.addEventListener('change', (e) => {
            this.filters.theater_id = e.target.value;
            this.currentPage = 1;
            this.loadOrders();
        });
        
        // Apply filters button
        this.els.applyFilterBtn?.addEventListener('click', () => {
            this.filters.theater_id = this.els.theaterFilter?.value || '';
            this.filters.movie_id = this.els.movieFilter?.value || '';
            this.filters.date = this.els.dateFilter?.value || '';
            this.filters.search = this.els.searchFilter?.value || '';
            this.currentPage = 1;
            this.loadOrders();
        });
        
        // Modal close
        this.els.modalClose?.addEventListener('click', () => this.closeModal());
        this.els.modal?.addEventListener('click', (e) => {
            if (e.target === this.els.modal) this.closeModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.els.modal?.classList.contains('show')) this.closeModal();
        }, { signal: this.lifecycleController.signal });
    }
    
    // ─── Load Filter Data ────────────────────────────────────────────────────
    
    async loadFiltersData() {
        try {
            const [branchesRes, theatersRes, moviesRes] = await Promise.all([
                this.apiRequest('/admin/branches?options=1', { cacheTtl: 300000 }),
                this.apiRequest('/admin/theaters?options=1', { cacheTtl: 300000 }),
                this.apiRequest('/movies?per_page=100&status=all', { cacheTtl: 300000 }),
            ]);

            const branches = branchesRes?.data || [];
            this.populateSelect(this.els.branchFilter, branches, 'id', 'name');

            this.theaterOptions = Array.isArray(theatersRes?.data) ? theatersRes.data : (theatersRes?.data?.data || []);
            this.populateTheaterFilter(this.filters.branch_id);

            if (moviesRes?.success) {
                const movies = Array.isArray(moviesRes.data) ? moviesRes.data : (moviesRes.data?.data || []);
                this.populateSelect(this.els.movieFilter, movies, 'id', 'title');
            }
        } catch (err) {
            console.error('[Orders] Load filters error:', err);
        }
    }
    
    populateTheaterFilter(branchId = '') {
        const theaters = branchId
            ? this.theaterOptions.filter(theater => String(theater.branch_id ?? theater.branch?.id ?? '') === String(branchId))
            : this.theaterOptions;

        this.populateSelect(this.els.theaterFilter, theaters, 'id', 'name');
    }
    
    populateSelect(select, items, valueKey, labelKey) {
        if (!select) return;
        const firstOption = select.querySelector('option:first-child')?.cloneNode(true);
        select.replaceChildren();
        if (firstOption) select.appendChild(firstOption);
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item[valueKey] ?? '');
            option.textContent = String(item[labelKey] ?? '');
            select.appendChild(option);
        });
    }
    
    // ─── Load Orders ─────────────────────────────────────────────────────────
    
    async loadOrders(page = 1) {
        const requestId = ++this.loadRequestId;
        try {
            this.showLoading(true);
            this.currentPage = page;
            
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage
            });
            
            // Add filters
            if (this.filters.status !== 'all') params.append('status', this.filters.status);
            if (this.filters.branch_id) params.append('branch_id', this.filters.branch_id);
            if (this.filters.theater_id) params.append('theater_id', this.filters.theater_id);
            if (this.filters.movie_id) params.append('movie_id', this.filters.movie_id);
            if (this.filters.date) params.append('date', this.filters.date);
            if (this.filters.search) params.append('search', this.filters.search);
            
            const url = `/admin/orders?${params}`;
            
            this.loadRequest?.abort();
            this.loadRequest = new AbortController();
            const result = await this.apiRequest(url, { signal: this.loadRequest.signal });
            if (requestId !== this.loadRequestId) return;
            
            if (!result.success) throw new Error(result.message || 'Không thể tải đơn hàng.');
            
            this.orders = result.data.data || [];
            this.currentPage = result.data.meta?.current_page || 1;
            this.lastPage = result.data.meta?.last_page || 1;
            this.totalOrders = result.data.meta?.total || 0;
            
            this.renderOrders();
            this.renderPagination();
            this.updateOrderCount(result.data.meta?.total || 0);
            
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('[Orders] Load orders error:', err);
            this.showToast('Lỗi tải danh sách đơn hàng: ' + err.message, 'danger');
        } finally {
            if (requestId === this.loadRequestId) this.showLoading(false);
        }
    }
    
    // ─── Render Orders ───────────────────────────────────────────────────────
    
    renderOrders() {
        if (!this.els.grid) return;
        
        if (this.orders.length === 0) {
            this.els.grid.innerHTML = '';
            this.els.empty?.classList.remove('d-none');
            return;
        }
        
        this.els.empty?.classList.add('d-none');
        this.els.grid.innerHTML = this.orders.map(order => this.buildOrderCard(order)).join('');
        this.els.grid.querySelectorAll('[data-order-id]').forEach((button) => {
            button.addEventListener('click', () => this.showOrderDetail(button.dataset.orderId));
        });
    }
    
    buildOrderCard(order) {
        // Extract first ticket/showtime for poster
        const showtime = order.showtime || {};
        const movie = showtime.movie || {};
        const screen = showtime.screen || {};
        const theater = screen.theater || {};
        const user = order.user || {};
        
        const title = this.esc(movie.title || 'Chưa rõ');
        const poster = this.safeImageUrl(movie.poster_url);
        const rating = movie.age_rating || '';
        const theaterName = this.esc(theater.name || 'Chưa rõ');
        const screenName = this.esc(screen.name || '');
        const showtimeStr = this.formatDateTime(showtime.scheduled_at);
        const amount = this.formatCurrency(order.total_amount || 0);
        
        // Build seats list
        const seats = this.getTicketItems(order).map((item) => {
            const metadata = item.metadata || {};
            const label = metadata.seat_label || metadata.seat_number || 'N/A';
            const typeName = metadata.seat_type || metadata.seat_type_name || '';
            return typeName ? `${label} (${typeName})` : label;
        }).join(', ');
        
        // Status badge
        const { label: statusLabel, badge } = this.getStatusMeta(this.normalizeStatus(order));
        
        return `
        <article class="ticket-card">
            <div class="ticket-poster">
                ${poster
                    ? `<img class="ticket-poster-img" src="${this.esc(poster)}" alt="${title}" loading="lazy" onerror="this.outerHTML='<div class=\'movie-poster-container text-center d-flex align-items-center justify-content-center w-100 h-100\'><i class=\'bi bi-film text-white-50 fs-2\'></i></div>'">`
                    : `<div class="movie-poster-container text-center d-flex align-items-center justify-content-center w-100 h-100"><i class="bi bi-film text-white-50 fs-2"></i></div>`}
                ${rating ? `<div class="ticket-formats"><span class="ticket-format-badge">${this.esc(rating)}</span></div>` : ''}
            </div>
            
            <div class="ticket-details">
                <div class="ticket-header">
                    <span class="ticket-id">#${this.esc(order.code || 'N/A')}</span>
                    <span class="${badge}">${statusLabel}</span>
                </div>
                <h3 class="ticket-title">${title}</h3>
                <div class="ticket-info">
                    <div class="ticket-info-item">
                        <span class="ticket-info-label"><i class="bi bi-calendar3"></i> NGÀY CHIẾU</span>
                        <span class="ticket-info-value">${showtimeStr}</span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label"><i class="bi bi-geo-alt"></i> RẠP CHIẾU</span>
                        <span class="ticket-info-value">${theaterName}${screenName ? ` · ${screenName}` : ''}</span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label"><i class="bi bi-person-check"></i> GHẾ</span>
                        <span class="ticket-info-value">${this.esc(seats || 'N/A')}</span>
                    </div>
                </div>
                
                <div class="ticket-customer-info">
                    <span class="ticket-customer-label">NGƯỜI ĐẶT</span>
                    <span class="ticket-customer-name">${this.esc(user.name || 'N/A')}</span>
                    <span class="ticket-customer-contact">${this.esc(user.email || '')} ${user.phone ? '· ' + this.esc(user.phone) : ''}</span>
                </div>
                
                <div class="ticket-amount"><i class="bi bi-receipt"></i> ${amount}</div>
            </div>
            
            <div class="ticket-actions">
                <button class="ticket-detail-btn" type="button"
                        data-order-id="${this.esc(order.id)}">
                    <i class="bi bi-eye"></i> Chi tiết
                </button>
            </div>
        </article>`;
    }
    
    // ─── Order Detail Modal ──────────────────────────────────────────────────
    
    async showOrderDetail(orderCode) {
        if (!this.els.modal || !this.els.modalBody) return;
        this.detailRequest?.abort();
        this.detailRequest = new AbortController();
        const requestId = ++this.detailRequestId;
        this.previousFocus = document.activeElement;
        
        this.els.modalBody.innerHTML = `
            <div class="ticket-modal-loading">
                <div class="spinner-border text-danger"></div>
                <p>Đang tải...</p>
            </div>`;
        this.els.modal.classList.add('show');
        document.body.classList.add('modal-open');
        this.els.modalClose?.focus();
        
        try {
            const result = await this.apiRequest(`/admin/orders/${encodeURIComponent(orderCode)}`, { signal: this.detailRequest.signal });
            if (requestId !== this.detailRequestId) return;
            if (!result.success) throw new Error(result.message);
            this.renderOrderModal(result.data);
        } catch (err) {
            if (err.name === 'AbortError' || requestId !== this.detailRequestId) return;
            this.els.modalBody.innerHTML = `<div class="alert alert-danger m-4">${this.esc(err.message)}</div>`;
        }
    }
    
    getInitials(name) {
        if (!name) return 'KH';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        return name.substring(0, 2).toUpperCase();
    }

    getTicketItemsList(order) {
        if (Array.isArray(order.tickets) && order.tickets.length > 0) {
            return order.tickets.map(t => {
                const seat = t.seat || {};
                const seatType = seat.seat_type?.name || 'Thường';
                const seatLabel = seat.label || (seat.row && seat.number ? `${seat.row}${seat.number}` : 'N/A');
                return {
                    seatLabel,
                    seatType,
                    ticketCode: t.ticket_code || 'N/A',
                    unitPrice: t.unit_price || 0,
                    status: t.status || 'confirmed'
                };
            });
        }
        const items = Array.isArray(order?.items) ? order.items : [];
        return items.filter(item => {
            const type = String(item?.type || item?.item_type || '').toLowerCase();
            return type.includes('seat') || type.includes('ticket') || Boolean(item?.metadata?.seat_label);
        }).map(item => {
            const metadata = item.metadata || {};
            return {
                seatLabel: metadata.seat_label || metadata.seat_number || 'N/A',
                seatType: metadata.seat_type || metadata.seat_type_name || 'Thường',
                ticketCode: metadata.ticket_code || item.ticket_code || 'N/A',
                unitPrice: item.unit_price || 0,
                status: 'confirmed'
            };
        });
    }

    getFoodComboItemsList(order) {
        const items = Array.isArray(order?.items) ? order.items : [];
        return items.filter(item => {
            const type = String(item?.type || item?.item_type || '').toLowerCase();
            return type.includes('combo') || type.includes('food') || type.includes('product') || Boolean(item?.metadata?.combo_name || item?.metadata?.product_name);
        }).map(item => {
            const metadata = item.metadata || {};
            return {
                name: metadata.combo_name || metadata.product_name || item.name || 'Sản phẩm đi kèm',
                quantity: item.quantity || 1,
                unitPrice: item.unit_price || 0,
                totalPrice: item.total_price || ((item.unit_price || 0) * (item.quantity || 1)) || 0
            };
        });
    }

    formatCGVDate(datetime) {
        if (!datetime) return 'N/A';
        const d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        const days = ['Chủ Nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        const dayName = days[d.getDay()];
        const dateStr = d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
        return `${dayName}, ${dateStr}`;
    }

    formatCGVTime(datetime) {
        if (!datetime) return 'N/A';
        const d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    }

    renderOrderModal(order) {
        if (!this.els.modalBody) return;
        
        const user = order.user || {};
        const showtime = order.showtime || {};
        const movie = showtime.movie || {};
        const screen = showtime.screen || {};
        const theater = screen.theater || {};
        const normalizedStatus = this.normalizeStatus(order);
        const { label: statusLabel, badge: statusBadgeClass } = this.getStatusMeta(normalizedStatus);
        
        const tickets = this.getTicketItemsList(order);
        const foodCombos = this.getFoodComboItemsList(order);
        
        const modalPosterUrl = this.safeImageUrl(movie.poster_url);
        const modalPosterHtml = modalPosterUrl
            ? `<img src="${this.esc(modalPosterUrl)}" alt="${this.esc(movie.title || '')}" loading="lazy" onerror="this.style.display='none'">`
            : '';
        
        // Build Refined Table Rows
        const ticketsTableRows = tickets.map((t) => {
            const price = t.unitPrice || (order.total_amount && tickets.length ? Math.round(order.total_amount / tickets.length) : 0);
            return `
            <tr>
                <td class="text-start align-middle">
                    <div class="item-title-text">Vé xem phim: <strong>Ghế ${this.esc(t.seatLabel)}</strong></div>
                    <div class="item-subtitle-text">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-1.5 py-0.5 me-1" style="font-size: 0.65rem;">${this.esc(t.seatType)}</span>
                        <span>Mã vé: <code>${this.esc(t.ticketCode)}</code></span>
                    </div>
                </td>
                <td class="text-center align-middle font-monospace fw-bold">1</td>
                <td class="text-end align-middle font-monospace text-muted">${this.formatCurrency(price)}</td>
                <td class="text-end align-middle font-monospace fw-bold">${this.formatCurrency(price)}</td>
            </tr>`;
        }).join('');

        const combosTableRows = foodCombos.map(f => {
            const unit = f.unitPrice || (f.quantity ? Math.round(f.totalPrice / f.quantity) : f.totalPrice);
            return `
            <tr>
                <td class="text-start align-middle">
                    <div class="item-title-text">${this.esc(f.name)}</div>
                    <div class="item-subtitle-text text-muted">Bắp nước & Combo tại rạp</div>
                </td>
                <td class="text-center align-middle font-monospace fw-bold">${f.quantity}</td>
                <td class="text-end align-middle font-monospace text-muted">${this.formatCurrency(unit)}</td>
                <td class="text-end align-middle font-monospace fw-bold">${this.formatCurrency(f.totalPrice)}</td>
            </tr>`;
        }).join('');

        const subtotal = (order.total_amount || 0);
        const dateFormatted = this.formatCGVDate(showtime.scheduled_at);
        const timeFormatted = this.formatCGVTime(showtime.scheduled_at);

        this.els.modalBody.innerHTML = `
        <div class="admin-order-modal-wrapper">
            <!-- 1. Header Bar -->
            <div class="admin-order-header">
                <div class="admin-order-title-section">
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="admin-order-title">Chi tiết đơn hàng #${this.esc(order.code || 'N/A')}</h2>
                        <button type="button" class="btn-copy-order-code" id="btnCopyOrderCode" title="Sao chép mã đơn hàng">
                            <i class="bi bi-copy"></i>
                        </button>
                    </div>
                    <span class="admin-order-date">Thời gian đặt: ${this.formatDateTime(order.created_at)}</span>
                </div>
                <div class="admin-order-actions">
                    <span class="badge ${statusBadgeClass} align-middle text-uppercase px-2.5 py-1.5" style="font-size:0.75rem;">
                        ${statusLabel}
                    </span>
                    <button type="button" class="btn-admin-order-print" id="btnPrintOrderInvoice" title="In vé / hóa đơn">
                        <i class="bi bi-printer me-1"></i> In Hóa Đơn
                    </button>
                    <button type="button" class="btn-admin-order-close" data-close-order-modal aria-label="Đóng">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- 2. Main Grid Layout -->
            <div class="admin-order-grid">
                <!-- Left Column: Itemized list & Payment breakdown -->
                <div class="admin-order-main-col">
                    <!-- Table Card -->
                    <div class="admin-order-card">
                        <div class="admin-order-card-header">Chi tiết Vé & Combo dịch vụ</div>
                        <div class="table-responsive">
                            <table class="admin-order-table">
                                <thead>
                                    <tr>
                                        <th class="text-start">Chi tiết mô tả</th>
                                        <th class="text-center" style="width: 60px;">SL</th>
                                        <th class="text-end" style="width: 120px;">Đơn giá</th>
                                        <th class="text-end" style="width: 130px;">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ticketsTableRows || `<tr><td colspan="4" class="text-center text-muted py-3">Không có chi tiết vé</td></tr>`}
                                    ${combosTableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment summary card -->
                    <div class="admin-order-card mt-3">
                        <div class="admin-order-card-header">Chi tiết thanh toán</div>
                        <div class="admin-order-summary-list">
                            <div class="admin-order-summary-item">
                                <span class="label">Tạm tính</span>
                                <span class="value font-monospace">${this.formatCurrency(subtotal)}</span>
                            </div>
                            <div class="admin-order-summary-item">
                                <span class="label">Giảm giá / Ưu đãi</span>
                                <span class="value font-monospace text-success">-0 ₫</span>
                            </div>
                            <div class="admin-order-summary-divider"></div>
                            <div class="admin-order-summary-item total">
                                <span class="label">Tổng tiền thanh toán</span>
                                <span class="value font-monospace highlight-price">${this.formatCurrency(subtotal)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sidebar metadata -->
                <div class="admin-order-side-col">
                    <!-- Customer Profile Card -->
                    <div class="admin-order-card">
                        <div class="admin-order-card-header"><i class="bi bi-person me-1.5"></i>Khách hàng</div>
                        <div class="admin-order-detail-list">
                            <div class="detail-item">
                                <span class="label">Họ tên</span>
                                <strong class="value text-white">${this.esc(user.name || 'Khách vãng lai')}</strong>
                            </div>
                            <div class="detail-item">
                                <span class="label">Email</span>
                                <span class="value">${this.esc(user.email || 'N/A')}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Số điện thoại</span>
                                <span class="value">${this.esc(user.phone || 'N/A')}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Screening Info Card -->
                    <div class="admin-order-card mt-3">
                        <div class="admin-order-card-header"><i class="bi bi-film me-1.5"></i>Thông tin Suất chiếu</div>
                        <div class="admin-order-detail-list">
                            <div class="detail-item">
                                <span class="label">Phim</span>
                                <strong class="value text-white d-flex align-items-center gap-1.5 flex-wrap">
                                    ${movie.age_rating ? `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-1.5 py-0.5" style="font-size:0.65rem;">${this.esc(movie.age_rating)}</span>` : ''}
                                    ${this.esc(movie.title || 'N/A')}
                                </strong>
                            </div>
                            <div class="detail-item">
                                <span class="label">Thời lượng</span>
                                <span class="value">${movie.duration ? `${this.esc(movie.duration)} phút` : 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Rạp / Phòng</span>
                                <span class="value">${this.esc(theater.name || 'N/A')} · ${this.esc(screen.name || 'N/A')}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Thời gian</span>
                                <strong class="value text-warning">${dateFormatted} · ${timeFormatted}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Metadata Card -->
                    <div class="admin-order-card mt-3">
                        <div class="admin-order-card-header"><i class="bi bi-credit-card me-1.5"></i>Giao dịch</div>
                        <div class="admin-order-detail-list">
                            <div class="detail-item">
                                <span class="label">Cổng thanh toán</span>
                                <span class="value text-info fw-semibold">${this.esc(order.payment_method || 'Online PayOS')}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Trạng thái giao dịch</span>
                                <span class="value text-white">${statusLabel}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Ngày thanh toán</span>
                                <span class="value">${order.paid_at ? this.formatDateTime(order.paid_at) : 'Chưa ghi nhận'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

        // Bind copy button event
        this.els.modalBody.querySelector('#btnCopyOrderCode')?.addEventListener('click', () => {
            if (order.code) {
                navigator.clipboard.writeText(order.code).then(() => {
                    this.showToast('Đã sao chép mã đơn hàng!', 'success');
                }).catch(() => {
                    this.showToast('Mã đơn: ' + order.code, 'info');
                });
            }
        });

        // Bind print button event
        this.els.modalBody.querySelector('#btnPrintOrderInvoice')?.addEventListener('click', () => {
            window.print();
        });

        // Bind close button
        this.els.modalBody.querySelectorAll('[data-close-order-modal]').forEach(btn => {
            btn.addEventListener('click', () => this.closeModal());
        });
    }
    
    closeModal() {
        this.detailRequest?.abort();
        this.detailRequest = null;
        this.detailRequestId++;
        this.els.modal?.classList.remove('show');
        document.body.classList.remove('modal-open');
        this.previousFocus?.focus?.();
    }
    
    // ─── Pagination ──────────────────────────────────────────────────────────
    
    renderPagination() {
        window.AdminCore.renderAdminPagination(this.els.pagination, {
            current_page: this.currentPage,
            last_page: this.lastPage,
            total: this.totalOrders || 0,
            per_page: this.perPage || 10,
        }, (page) => {
            this.currentPage = page;
            this.loadOrders(page);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // ─── Helpers ─────────────────────────────────────────────────────────────
    
    updateOrderCount(total) {
        if (this.els.orderCount) {
            this.els.orderCount.textContent = `${total} đơn hàng`;
        }
    }
    
    showLoading(show) {
        this.els.loading?.classList.toggle('d-none', !show);
        this.els.grid?.classList.toggle('d-none', show);
    }
    
    getStatusMeta(status) {
        const meta = {
            pending: { 
                cls: 'ticket-status--pending', 
                label: 'Chờ thanh toán', 
                dot: '#fbbf24',
                badge: 'ticket-status-badge ticket-status-badge--pending'
            },
            paid: { 
                cls: 'ticket-status--valid', 
                label: 'Đã thanh toán', 
                dot: '#22c55e',
                badge: 'ticket-status-badge ticket-status-badge--paid'
            },
            confirmed: { 
                cls: 'ticket-status--confirmed', 
                label: 'Đã xác nhận', 
                dot: '#3b82f6',
                badge: 'ticket-status-badge ticket-status-badge--confirmed'
            },
            cancelled: { 
                cls: 'ticket-status--expired', 
                label: 'Đã hủy', 
                dot: '#ef4444',
                badge: 'ticket-status-badge ticket-status-badge--cancelled'
            },
            expired: { 
                cls: 'ticket-status--expired', 
                label: 'Hết hạn', 
                dot: '#ef4444',
                badge: 'ticket-status-badge ticket-status-badge--expired'
            }
        };
        return meta[status] || meta.pending;
    }

    normalizeStatus(order) {
        const paymentStatus = String(order?.payment_status || '').toLowerCase();
        if (paymentStatus) return paymentStatus === 'confirmed' ? 'paid' : paymentStatus;
        const status = String(order?.status || '').toLowerCase();
        return status === 'confirmed' || status === 'completed' ? 'paid' : status;
    }

    getTicketItems(order) {
        const items = Array.isArray(order?.items) ? order.items : [];
        return items.filter((item) => {
            const type = String(item?.type || item?.item_type || '').toLowerCase();
            return type.includes('seat') || type.includes('ticket') || Boolean(item?.metadata?.seat_label);
        });
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount || 0);
    }
    
    formatDateTime(datetime) {
        if (!datetime) return 'N/A';
        const d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }
    
    esc(str) {
        if (str == null) return '';
        return String(str)
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
        return '';
    }
    
    async apiRequest(endpoint, options = {}) {
        const baseUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        const url = endpoint.startsWith('http') ? endpoint : `${baseUrl}${endpoint}`;

        if (!window.AdminCore) throw new Error('Admin API client is not ready');
        const response = await window.AdminCore.apiFetch(url, options);
        if (!response) throw new Error('Request was rejected');
        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'Request failed' }));
            throw new Error(error.message || `HTTP ${response.status}`);
        }
        return response.json();
    }
    
    showToast(message, type = 'info') {
        if (window.authManager?.showToast) {
            window.authManager.showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    destroy() {
        this.lifecycleController.abort();
        this.loadRequest?.abort?.();
        this.detailRequest?.abort?.();
    }
}

// ─── Initialize ──────────────────────────────────────────────────────────────

window.onAdminPageLoad(() => {
    if (window.location.pathname !== '/admin/orders' || !document.getElementById('ordersGrid')) return;
    const manager = new AdminOrdersManager();
    window.adminOrdersManager = manager;
    window.onAdminPageCleanup(() => manager.destroy());
});

})();
