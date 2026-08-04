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
            date_from: '',
            date_to: '',
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
            statusFilter: document.getElementById('statusFilter'),
            statusTabs: document.querySelectorAll('[data-filter-status]'),
            branchFilter: document.getElementById('branchFilter'),
            theaterFilter: document.getElementById('theaterFilter'),
            movieFilter: document.getElementById('movieFilter'),
            dateFilter: document.getElementById('dateFilter'),
            dateFromFilter: document.getElementById('dateFromFilter'),
            dateToFilter: document.getElementById('dateToFilter'),
            searchFilter: document.getElementById('searchFilter'),
            searchForm: document.getElementById('searchForm'),
            applyFilterBtn: document.getElementById('btnApplyFilter'),
            resetFilterBtn: document.getElementById('btnResetFilter'),
            
            // Modal
            modal: document.getElementById('orderDetailModal'),
            modalTitle: document.getElementById('modalOrderCodeTitle'),
            modalBody: document.getElementById('orderDetailModalBody'),
            modalStatus: document.getElementById('modalOrderStatusContainer')
        };
        
        if (this.els.modal) {
            this.modalInstance = new window.bootstrap.Modal(this.els.modal);
        }
    }
    
    attachEvents() {
        // Status select dropdown
        this.els.statusFilter?.addEventListener('change', (e) => {
            this.filters.status = e.target.value;
            this.currentPage = 1;
            this.loadOrders();
        });

        // Status tabs (fallback compatibility)
        this.els.statusTabs?.forEach(tab => {
            tab.addEventListener('click', () => {
                this.els.statusTabs.forEach(t => t.classList.remove('active'));
                this.els.statusTabs.forEach(t => t.setAttribute('aria-selected', 'false'));
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                this.filters.status = tab.dataset.filterStatus;
                if (this.els.statusFilter) this.els.statusFilter.value = this.filters.status;
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

        this.els.movieFilter?.addEventListener('change', (e) => {
            this.filters.movie_id = e.target.value;
            this.currentPage = 1;
            this.loadOrders();
        });

        this.els.dateFilter?.addEventListener('change', (e) => {
            this.filters.date = e.target.value;
            this.currentPage = 1;
            this.loadOrders();
        });
        
        this.els.dateFromFilter?.addEventListener('change', (e) => {
            this.filters.date_from = e.target.value;
            this.currentPage = 1;
            this.loadOrders();
        });
        
        this.els.dateToFilter?.addEventListener('change', (e) => {
            this.filters.date_to = e.target.value;
            this.currentPage = 1;
            this.loadOrders();
        });
        
        // Search form & Apply button
        const handleSearch = (e) => {
            if (e) e.preventDefault();
            this.filters.search = this.els.searchFilter?.value || '';
            this.currentPage = 1;
            this.loadOrders();
        };
        this.els.searchForm?.addEventListener('submit', handleSearch);
        this.els.applyFilterBtn?.addEventListener('click', handleSearch);

        // Reset filters button
        this.els.resetFilterBtn?.addEventListener('click', () => {
            if (this.els.statusFilter) this.els.statusFilter.value = 'all';
            if (this.els.branchFilter) this.els.branchFilter.value = '';
            if (this.els.theaterFilter) this.els.theaterFilter.value = '';
            if (this.els.movieFilter) this.els.movieFilter.value = '';
            if (this.els.dateFilter) this.els.dateFilter.value = '';
            if (this.els.dateFromFilter) this.els.dateFromFilter.value = '';
            if (this.els.dateToFilter) this.els.dateToFilter.value = '';
            if (this.els.searchFilter) this.els.searchFilter.value = '';

            this.els.statusTabs?.forEach(t => {
                const isActive = t.dataset.filterStatus === 'all';
                t.classList.toggle('active', isActive);
                t.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            this.filters.status = 'all';
            this.filters.branch_id = '';
            this.filters.theater_id = '';
            this.filters.movie_id = '';
            this.filters.date = '';
            this.filters.date_from = '';
            this.filters.date_to = '';
            this.filters.search = '';

            this.populateTheaterFilter('');
            this.currentPage = 1;
            this.loadOrders();
        });
        
        // Modal events handled by Bootstrap natively
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
            if (this.filters.date_from) params.append('date_from', this.filters.date_from);
            if (this.filters.date_to) params.append('date_to', this.filters.date_to);
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
        const poster = this.safeImageUrl(movie.poster_display_url || movie.poster_url);
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
                    ? `<img class="ticket-poster-img" src="${this.esc(poster)}" alt="${title}" loading="lazy" data-admin-image-fallback="bi-film">`
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
            <div class="p-2">
                <div class="admin-order-detail-skeleton admin-order-detail-skeleton--hero mb-4">
                    <div class="skeleton-line order-skeleton order-skeleton--poster"></div>
                    <div class="flex-grow-1">
                        <div class="skeleton-line order-skeleton order-skeleton--title mb-2"></div>
                        <div class="skeleton-line order-skeleton order-skeleton--line-md mb-2"></div>
                        <div class="skeleton-line order-skeleton order-skeleton--line-sm"></div>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="admin-order-detail-skeleton">
                            <div class="skeleton-line order-skeleton order-skeleton--section-title mb-3"></div>
                            <div class="skeleton-line order-skeleton order-skeleton--line-xl mb-2"></div>
                            <div class="skeleton-line order-skeleton order-skeleton--line-lg"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="admin-order-detail-skeleton">
                            <div class="skeleton-line order-skeleton order-skeleton--section-title mb-3"></div>
                            <div class="skeleton-line order-skeleton order-skeleton--line-wide mb-2"></div>
                            <div class="skeleton-line order-skeleton order-skeleton--line-mid"></div>
                        </div>
                    </div>
                </div>
                <div class="admin-order-detail-skeleton">
                    <div class="skeleton-line order-skeleton order-skeleton--summary-title mb-3"></div>
                    <div class="d-flex justify-content-between mb-2">
                        <div class="skeleton-line order-skeleton order-skeleton--summary-label"></div>
                        <div class="skeleton-line order-skeleton order-skeleton--summary-value"></div>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <div class="skeleton-line order-skeleton order-skeleton--line-mid"></div>
                        <div class="skeleton-line order-skeleton order-skeleton--summary-value"></div>
                    </div>
                </div>
            </div>`;
            
        if (this.els.modalTitle) {
            this.els.modalTitle.textContent = `#${orderCode}`;
        }
            
        this.modalInstance?.show();
        
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
        const items = Array.isArray(order?.items) ? order.items : [];
        const ticketItemsFromOrderItems = items.filter(item => {
            const type = String(item?.type || item?.item_type || '').toLowerCase();
            return type.includes('seat') || type.includes('ticket') || Boolean(item?.metadata?.seat_label);
        });

        if (Array.isArray(order.tickets) && order.tickets.length > 0) {
            return order.tickets.map(t => {
                const seat = t.seat || {};
                const seatType = seat.seat_type?.name || 'Thường';
                const seatLabel = seat.label || (seat.row && seat.number ? `${seat.row}${seat.number}` : 'N/A');
                // Try to find matching item in order.items for the unit price
                const matchedItem = ticketItemsFromOrderItems.find(item => {
                    const metaLabel = item.metadata?.seat_label || item.metadata?.seat_number;
                    return metaLabel && String(metaLabel).trim().toLowerCase() === String(seatLabel).trim().toLowerCase();
                }) || ticketItemsFromOrderItems[0];
                const unitPrice = parseFloat(matchedItem?.unit_price || 0) || (order.total_amount ? Math.round(order.total_amount / order.tickets.length) : 0);
                return {
                    seatLabel,
                    seatType,
                    ticketCode: t.ticket_code || 'N/A',
                    unitPrice,
                    status: t.status || 'confirmed'
                };
            });
        }
        return ticketItemsFromOrderItems.map(item => {
            const metadata = item.metadata || {};
            return {
                seatLabel: metadata.seat_label || metadata.seat_number || 'N/A',
                seatType: metadata.seat_type || metadata.seat_type_name || 'Thường',
                ticketCode: metadata.ticket_code || item.ticket_code || 'N/A',
                unitPrice: parseFloat(item.unit_price || 0) || (order.total_amount ? Math.round(order.total_amount / ticketItemsFromOrderItems.length) : 0),
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
        
        // Build Refined Table Rows
        const ticketsTableRows = tickets.map((t) => {
            const price = t.unitPrice || (order.total_amount && tickets.length ? Math.round(order.total_amount / tickets.length) : 0);
            return `
            <tr>
                <td class="text-start align-middle">
                    <div class="item-title-text">Vé xem phim: <strong>Ghế ${this.esc(t.seatLabel)}</strong></div>
                    <div class="item-subtitle-text">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-1.5 py-0.5 me-1 admin-order-mini-badge">${this.esc(t.seatType)}</span>
                        <span>Mã vé: <code>${this.esc(t.ticketCode)}</code></span>
                    </div>
                </td>
                <td class="text-center align-middle font-monospace fw-bold">1</td>
                <td class="text-end align-middle font-monospace">${this.formatCurrency(price)}</td>
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
                <td class="text-end align-middle font-monospace">${this.formatCurrency(unit)}</td>
                <td class="text-end align-middle font-monospace fw-bold">${this.formatCurrency(f.totalPrice)}</td>
            </tr>`;
        }).join('');

        const payload = order.invoice || {};
        let calculatedSubtotal = 0;
        tickets.forEach(t => calculatedSubtotal += parseFloat(t.unitPrice || 0));
        foodCombos.forEach(f => calculatedSubtotal += parseFloat(f.totalPrice || 0));

        const actualTotal = Number(order.total_amount) || 0;
        let subtotal = parseFloat(payload.subtotal || 0);
        if (subtotal === 0 || subtotal < actualTotal) {
            subtotal = Math.max(calculatedSubtotal, actualTotal);
        }

        let discount = parseFloat(payload.discount_amount || payload.voucher_discount || 0);
        if (discount === 0 && subtotal > actualTotal) {
            discount = Math.round(subtotal - actualTotal);
        }

        const dateFormatted = this.formatCGVDate(showtime.scheduled_at);
        const timeFormatted = this.formatCGVTime(showtime.scheduled_at);

        if (this.els.modalStatus) {
            this.els.modalStatus.innerHTML = `<span class="badge ${statusBadgeClass} text-uppercase px-3 py-2 admin-order-status-badge">${statusLabel}</span>`;
        }

        this.els.modalBody.innerHTML = `
        <div class="row g-4">
            <!-- Left Column: Itemized table & Payment summary -->
            <div class="col-lg-7">
                <!-- Table Box -->
                <div class="admin-order-detail-box mb-4">
                    <h6 class="admin-order-detail-heading">
                        <i class="bi bi-ticket-detailed me-2 admin-accent-icon"></i> Chi tiết Vé & Combo dịch vụ
                    </h6>
                    <div class="admin-table-wrapper admin-order-detail-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th class="text-start">Chi tiết mô tả</th>
                                    <th class="text-center admin-order-col-quantity">SL</th>
                                    <th class="text-end admin-order-col-price">Đơn giá</th>
                                    <th class="text-end admin-order-col-total">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${ticketsTableRows || `<tr><td colspan="4" class="text-center text-secondary py-3">Không có chi tiết vé</td></tr>`}
                                ${combosTableRows}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Summary Box -->
                <div class="admin-order-detail-box">
                    <h6 class="admin-order-detail-heading">
                        <i class="bi bi-credit-card me-2 admin-accent-icon"></i> Chi tiết thanh toán
                    </h6>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-secondary small">Tạm tính</span>
                            <span class="text-white font-monospace">${this.formatCurrency(subtotal)}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-secondary small">Giảm giá / Ưu đãi</span>
                            <span class="text-success font-monospace">-${this.formatCurrency(discount)}</span>
                        </div>
                        <hr class="border-secondary border-opacity-25 my-1">
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-white fw-bold">Tổng tiền thanh toán</span>
                            <span class="font-monospace fw-bold admin-order-grand-total">${this.formatCurrency(actualTotal)}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Customer & Screening info -->
            <div class="col-lg-5">
                <!-- Customer Box -->
                <div class="admin-order-detail-box mb-4">
                    <h6 class="admin-order-detail-heading">
                        <i class="bi bi-person me-2 admin-accent-icon"></i> Khách hàng
                    </h6>
                    <div class="d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Họ tên</span>
                            <strong class="text-white small">${this.esc(user.name || 'Khách vãng lai')}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Email</span>
                            <span class="text-white small">${this.esc(user.email || 'N/A')}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="text-secondary small">Số điện thoại</span>
                            <span class="text-white small">${this.esc(user.phone || 'N/A')}</span>
                        </div>
                    </div>
                </div>

                <!-- Screening Info Box -->
                <div class="admin-order-detail-box mb-4">
                    <h6 class="admin-order-detail-heading">
                        <i class="bi bi-film me-2 admin-accent-icon"></i> Thông tin Suất chiếu
                    </h6>
                    <div class="d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Phim</span>
                            <strong class="text-white small text-end">
                                ${movie.age_rating ? `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-1.5 py-0.5 me-1 admin-order-mini-badge">${this.esc(movie.age_rating)}</span>` : ''}
                                ${this.esc(movie.title || 'N/A')}
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Thời lượng</span>
                            <span class="text-white small">${movie.duration ? `${this.esc(movie.duration)} phút` : 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Rạp / Phòng</span>
                            <span class="text-white small text-end">${this.esc(theater.name || 'N/A')} · ${this.esc(screen.name || 'N/A')}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="text-secondary small">Thời gian</span>
                            <strong class="text-warning small text-end">${dateFormatted} · ${timeFormatted}</strong>
                        </div>
                    </div>
                </div>

                <!-- Transaction Info Box -->
                <div class="admin-order-detail-box">
                    <h6 class="admin-order-detail-heading">
                        <i class="bi bi-receipt-cutoff me-2 admin-accent-icon"></i> Giao dịch
                    </h6>
                    <div class="d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Cổng thanh toán</span>
                            <span class="text-info small fw-semibold">${this.esc(order.payment_method || 'Online PayOS')}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-secondary small">Trạng thái</span>
                            <span class="small">${statusLabel}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="text-secondary small">Ngày thanh toán</span>
                            <span class="text-white small">${order.paid_at ? this.formatDateTime(order.paid_at) : 'Chưa ghi nhận'}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

        // Bind copy button event
        this.els.modal?.querySelector('#btnCopyOrderCode')?.addEventListener('click', () => {
            if (order.code) {
                navigator.clipboard.writeText(order.code).then(() => {
                    this.showToast('Đã sao chép mã đơn hàng!', 'success');
                }).catch(() => {
                    this.showToast('Mã đơn: ' + order.code, 'info');
                });
            }
        });

        // Bind print button event
        this.els.modal?.querySelector('#btnPrintOrderInvoice')?.addEventListener('click', () => {
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
        window.showAdminToast?.(message, type);
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
