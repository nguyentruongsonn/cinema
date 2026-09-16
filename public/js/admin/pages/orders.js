/**
 * ════════════════════════════════════════════════════════════════════════════
 * ADMIN ORDERS MANAGEMENT
 * Uses the shared admin table, filters, pagination and status patterns.
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
        this.loadRequestId = 0;
        this.detailRequest = null;
        this.detailRequestId = 0;
        this.previousFocus = null;
        this.lifecycleController = new AbortController();
        
        this.initElements();
        this.applyDefaultDateRange();
        this.attachEvents();
        this.loadFiltersData();
        this.loadOrders();
    }
    
    initElements() {
        this.els = {
            tableBody: document.getElementById('ordersTableBody'),
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
        
        if (this.els.modal && window.bootstrap?.Modal) {
            this.modalInstance = new window.bootstrap.Modal(this.els.modal);
        }
    }

    applyDefaultDateRange() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const monday = new Date(today);
        const daysSinceMonday = today.getDay() === 0 ? 6 : today.getDay() - 1;
        monday.setDate(today.getDate() - daysSinceMonday);

        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);

        this.filters.date_from = this.formatDateInputValue(monday);
        this.filters.date_to = this.formatDateInputValue(sunday);

        if (this.els.dateFromFilter) this.els.dateFromFilter.value = this.filters.date_from;
        if (this.els.dateToFilter) this.els.dateToFilter.value = this.filters.date_to;
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
            this.filters.search = '';
            this.applyDefaultDateRange();

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
            
            const result = await this.apiRequest(url, {
                requestKey: 'admin-orders-list',
                skipCache: true,
            });
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
            this.renderTableMessage('Không thể tải danh sách đơn hàng.', 'bi-exclamation-triangle');
            this.showToast('Lỗi tải danh sách đơn hàng: ' + err.message, 'danger');
        } finally {
            if (requestId === this.loadRequestId) this.showLoading(false);
        }
    }
    
    // ─── Render Orders ───────────────────────────────────────────────────────
    
    renderOrders() {
        if (!this.els.tableBody) return;
        
        if (this.orders.length === 0) {
            this.renderTableMessage('Không có đơn hàng phù hợp.', 'bi-receipt', 'Thử thay đổi trạng thái, thời gian hoặc từ khóa tìm kiếm.');
            return;
        }
        
        this.els.tableBody.innerHTML = this.orders.map(order => this.buildOrderRow(order)).join('');
        this.els.tableBody.querySelectorAll('[data-order-id]').forEach((button) => {
            button.addEventListener('click', () => this.showOrderDetail(button.dataset.orderId));
        });
    }
    
    buildOrderRow(order) {
        const showtime = order.showtime || {};
        const movie = showtime.movie || {};
        const screen = showtime.screen || {};
        const theater = screen.theater || order.theater || {};
        const user = order.user || {};
        const productItems = this.getFoodComboItemsList(order);
        const hasShowtime = Boolean(showtime.id || showtime.scheduled_at);
        const rawTitle = movie.title || (productItems.length > 0 ? 'Đơn bắp nước tại quầy' : 'Đơn hàng không có suất chiếu');
        const title = this.esc(rawTitle);
        const poster = this.safeImageUrl(movie.poster_display_url || movie.poster_url);
        const theaterName = this.esc(theater.name || 'Chưa xác định rạp');
        const screenName = this.esc(screen.name || '');
        const showtimeStr = this.formatDateTime(showtime.scheduled_at || order.created_at);
        const amount = this.formatCurrency(order.total_amount || 0);
        const seats = this.getTicketItems(order).map((item) => {
            const metadata = item.metadata || {};
            const label = metadata.seat_label || metadata.seat_number || 'N/A';
            const typeName = metadata.seat_type || metadata.seat_type_name || '';
            return typeName ? `${label} (${typeName})` : label;
        }).join(', ');
        const serviceSummary = productItems.length > 0
            ? `${productItems.reduce((total, item) => total + Number(item.quantity || 0), 0)} sản phẩm / combo`
            : 'Không có sản phẩm đi kèm';
        const customerContact = [user.email, user.phone].filter(Boolean).map(value => this.esc(value)).join(' · ');
        const paymentMethod = this.esc(this.formatPaymentMethod(
            order.payment_method || order.payment?.method,
            order.payment_provider || order.payment?.provider
        ));
        const sourceLabel = String(order.source || '').toLowerCase() === 'pos' ? 'Tại quầy POS' : 'Đặt trực tuyến';
        const sourceBadge = String(order.source || '').toLowerCase() === 'pos'
            ? 'admin-badge admin-badge-info'
            : 'admin-badge admin-badge-secondary';
        const { label: statusLabel, badge } = this.getStatusMeta(this.normalizeStatus(order));

        return `
        <tr>
            <td data-label="Đơn hàng">
                <div class="admin-order-identity">
                    <div class="admin-order-poster" aria-hidden="true">
                        ${poster
                            ? `<img src="${this.esc(poster)}" alt="" loading="lazy" data-admin-image-fallback="bi-film">`
                            : `<i class="bi ${hasShowtime ? 'bi-film' : 'bi-cup-straw'}"></i>`}
                    </div>
                    <div class="admin-order-primary">
                        <span class="admin-order-code">#${this.esc(order.code || 'N/A')}</span>
                        <strong class="admin-order-movie">${title}</strong>
                        <span class="admin-order-created">${this.formatDateTime(order.created_at)}</span>
                    </div>
                </div>
            </td>
            <td data-label="Lịch chiếu / dịch vụ">
                <div class="admin-order-column">
                <strong class="admin-order-field-value">${hasShowtime ? showtimeStr : serviceSummary}</strong>
                <span class="admin-order-field-meta">
                    <i class="bi ${hasShowtime ? 'bi-geo-alt' : 'bi-shop'}"></i>
                    ${hasShowtime ? `${theaterName}${screenName ? ` · ${screenName}` : ''}` : sourceLabel}
                </span>
                <span class="admin-order-field-meta admin-order-seat-summary">
                    <i class="bi ${seats ? 'bi-person-check' : 'bi-bag'}"></i>
                    ${this.esc(seats || serviceSummary)}
                </span>
                </div>
            </td>
            <td data-label="Khách hàng">
                <div class="admin-order-column">
                <strong class="admin-order-field-value">${this.esc(user.name || 'Khách vãng lai')}</strong>
                <span class="admin-order-field-meta admin-order-contact">${customerContact || 'Không có thông tin liên hệ'}</span>
                </div>
            </td>
            <td data-label="Thanh toán" class="text-nowrap">
                <div class="admin-order-column">
                <strong class="admin-order-amount">${amount}</strong>
                <span class="admin-order-field-meta">${paymentMethod}</span>
                </div>
            </td>
            <td data-label="Trạng thái" class="text-center"><span class="${badge}">${statusLabel}</span></td>
            <td data-label="Nguồn" class="text-center"><span class="${sourceBadge}">${sourceLabel}</span></td>
            <td data-label="Hành động" class="text-center">
                <div class="admin-table-actions">
                    <button class="admin-btn-icon admin-btn-view" type="button"
                            data-order-id="${this.esc(order.id)}" aria-label="Xem chi tiết đơn ${this.esc(order.code || '')}"
                            title="Xem chi tiết">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }
    
    // ─── Order Detail Modal ──────────────────────────────────────────────────
    
    async showOrderDetail(orderId) {
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
            
        this.openModal();
        
        try {
            const result = await this.apiRequest(`/admin/orders/${encodeURIComponent(orderId)}`, { signal: this.detailRequest.signal });
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
                            <span class="text-info small fw-semibold">${this.esc(this.formatPaymentMethod(order.payment_method, order.payment_provider))}</span>
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

        const printButton = this.els.modal?.querySelector('#btnPrintOrderInvoice');
        if (printButton) {
            const sections = ['invoice'];
            if (tickets.length > 0) sections.push('tickets');
            if (foodCombos.length > 0) sections.push('concessions');
            printButton.disabled = this.normalizeStatus(order) !== 'paid';
            printButton.addEventListener('click', () => window.OrderPrinting?.open(order.id, sections));
        }

        // Bind close button
        this.els.modalBody.querySelectorAll('[data-close-order-modal]').forEach(btn => {
            btn.addEventListener('click', () => this.closeModal());
        });
    }
    
    closeModal() {
        this.detailRequest?.abort();
        this.detailRequest = null;
        this.detailRequestId++;
        if (this.modalInstance) {
            this.modalInstance.hide();
        } else if (this.els.modal) {
            this.els.modal.classList.remove('show');
            this.els.modal.style.removeProperty('display');
            this.els.modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }
        this.previousFocus?.focus?.();
    }

    openModal() {
        if (this.modalInstance) {
            this.modalInstance.show();
            return;
        }
        if (!this.els.modal) return;
        this.els.modal.style.display = 'block';
        this.els.modal.classList.add('show');
        this.els.modal.removeAttribute('aria-hidden');
        this.els.modal.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');
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

    renderTableMessage(title, icon, description = '') {
        if (!this.els.tableBody) return;

        this.els.tableBody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="admin-empty-state compact">
                        <i class="bi ${this.esc(icon)}"></i>
                        <h3>${this.esc(title)}</h3>
                        ${description ? `<p>${this.esc(description)}</p>` : ''}
                    </div>
                </td>
            </tr>`;
    }
    
    showLoading(show) {
        this.els.pagination?.classList.toggle('d-none', show);
        this.els.tableBody?.closest('.admin-table-wrapper')?.setAttribute('aria-busy', show ? 'true' : 'false');
        if (show) window.AdminCore?.renderTableSkeleton?.(this.els.tableBody, 7, 5, false);
    }
    
    getStatusMeta(status) {
        const meta = {
            pending: { 
                label: 'Chờ thanh toán', 
                badge: 'admin-badge admin-badge-warning with-dot'
            },
            paid: { 
                label: 'Đã thanh toán', 
                badge: 'admin-badge admin-badge-success with-dot'
            },
            confirmed: { 
                label: 'Đã xác nhận', 
                badge: 'admin-badge admin-badge-info with-dot'
            },
            cancelled: { 
                label: 'Đã hủy', 
                badge: 'admin-badge admin-badge-danger with-dot'
            },
            expired: { 
                label: 'Hết hạn', 
                badge: 'admin-badge admin-badge-secondary with-dot'
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

    formatPaymentMethod(method, provider) {
        const labels = {
            cash: 'Tiền mặt',
            qr_online: 'QR PayOS',
            payos_qr: 'QR PayOS',
            payos: 'PayOS',
            zero_amount: 'Đơn 0đ',
        };

        return labels[method] || labels[provider] || method || provider || 'Chưa xác định';
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

    formatDateInputValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
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
        this.detailRequest?.abort?.();
    }
}

// ─── Initialize ──────────────────────────────────────────────────────────────

function bootOrdersPage(attempt = 0) {
    if (window.location.pathname !== '/admin/orders' || !document.getElementById('ordersTableBody')) return;
    if (!window.AdminCore && attempt < 40) {
        window.setTimeout(() => bootOrdersPage(attempt + 1), 50);
        return;
    }
    window.adminOrdersManager?.destroy?.();
    const manager = new AdminOrdersManager();
    window.adminOrdersManager = manager;
    window.onAdminPageCleanup?.(() => manager.destroy());
}

if (typeof window.onAdminPageLoad === 'function') {
    window.onAdminPageLoad(() => bootOrdersPage());
} else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bootOrdersPage(), { once: true });
} else {
    bootOrdersPage();
}

})();
