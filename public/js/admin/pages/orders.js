/**
 * ════════════════════════════════════════════════════════════════════════════
 * ADMIN ORDERS MANAGEMENT - CARD LAYOUT
 * Following user tickets pattern from profile.js
 * ════════════════════════════════════════════════════════════════════════════
 */

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
        this.loadRequest = null;
        this.loadRequestId = 0;
        
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
                tab.classList.add('active');
                this.filters.status = tab.dataset.filterStatus;
                this.currentPage = 1;
                this.loadOrders();
            });
        });
        
        // Branch filter cascade
        this.els.branchFilter?.addEventListener('change', (e) => {
            this.filters.branch_id = e.target.value;
            this.loadTheaters(e.target.value);
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
    }
    
    // ─── Load Filter Data ────────────────────────────────────────────────────
    
    async loadFiltersData() {
        try {
            // Load branches
            const branchesRes = await this.apiRequest('/branches');
            if (branchesRes?.success) {
                const branches = branchesRes.data || [];
                this.populateSelect(this.els.branchFilter, branches, 'id', 'name');
            }
            
            // Load movies
            const moviesRes = await this.apiRequest('/movies');
            if (moviesRes?.success) {
                const movies = moviesRes.data || [];
                this.populateSelect(this.els.movieFilter, movies, 'id', 'title');
            }
        } catch (err) {
            console.error('[Orders] Load filters error:', err);
        }
    }
    
    async loadTheaters(branchId) {
        if (!branchId) {
            this.els.theaterFilter.innerHTML = '<option value="">Tất cả rạp</option>';
            return;
        }
        
        try {
            const res = await this.apiRequest(`/theaters?branch_id=${branchId}`);
            if (res?.success) {
                const theaters = res.data || [];
                this.populateSelect(this.els.theaterFilter, theaters, 'id', 'name');
            }
        } catch (err) {
            console.error('[Orders] Load theaters error:', err);
        }
    }
    
    populateSelect(select, items, valueKey, labelKey) {
        if (!select) return;
        const firstOption = select.querySelector('option:first-child')?.outerHTML || '';
        select.innerHTML = firstOption + items.map(item => 
            `<option value="${this.esc(item[valueKey])}">${this.esc(item[labelKey])}</option>`
        ).join('');
    }
    
    // ─── Load Orders ─────────────────────────────────────────────────────────
    
    async loadOrders(page = 1) {
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
            const requestId = ++this.loadRequestId;
            const result = await this.apiRequest(url, { signal: this.loadRequest.signal });
            if (requestId !== this.loadRequestId) return;
            
            if (!result.success) throw new Error(result.message || 'Không thể tải đơn hàng.');
            
            this.orders = result.data.data || [];
            this.currentPage = result.data.meta?.current_page || 1;
            this.lastPage = result.data.meta?.last_page || 1;
            
            this.renderOrders();
            this.renderPagination();
            this.updateOrderCount(result.data.meta?.total || 0);
            
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('[Orders] Load orders error:', err);
            this.showToast('Lỗi tải danh sách đơn hàng: ' + err.message, 'danger');
        } finally {
            this.showLoading(false);
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
        const firstTicket = order.tickets?.[0] || {};
        const showtime = order.showtime || firstTicket.showtime || {};
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
        const seats = (order.tickets || []).map(t => {
            const seat = t.seat || {};
            const label = seat.label || `${seat.row || ''}${seat.number || ''}`.trim() || 'N/A';
            const typeName = seat.seat_type?.name || '';
            return typeName ? `${label} (${typeName})` : label;
        }).join(', ');
        
        // Status badge
        const { cls, label: statusLabel, badge } = this.getStatusMeta(order.status);
        
        return `
        <article class="ticket-card">
            <div class="ticket-poster">
                <img class="ticket-poster-img" src="${poster}" alt="${title}" loading="lazy"
                     onerror="this.src='/images/placeholder.jpg'">
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
                        <span class="ticket-info-value">${seats || 'N/A'}</span>
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
        
        this.els.modalBody.innerHTML = `
            <div class="ticket-modal-loading">
                <div class="spinner-border text-danger"></div>
                <p>Đang tải...</p>
            </div>`;
        this.els.modal.classList.add('show');
        document.body.classList.add('modal-open');
        
        try {
            const result = await this.apiRequest(`/admin/orders/${encodeURIComponent(orderCode)}`);
            if (!result.success) throw new Error(result.message);
            this.renderOrderModal(result.data);
        } catch (err) {
            this.els.modalBody.innerHTML = `<div class="alert alert-danger m-4">${this.esc(err.message)}</div>`;
        }
    }
    
    renderOrderModal(order) {
        if (!this.els.modalBody) return;
        
        const user = order.user || {};
        const firstTicket = order.tickets?.[0] || {};
        const showtime = order.showtime || firstTicket.showtime || {};
        const movie = showtime.movie || {};
        const screen = showtime.screen || {};
        const theater = screen.theater || {};
        const { label: statusLabel, cls } = this.getStatusMeta(order.status);
        
        // Build tickets list
        const ticketsHtml = (order.tickets || []).map(ticket => {
            const seat = ticket.seat || {};
            const seatLabel = seat.label || `${seat.row || ''}${seat.number || ''}`.trim();
            const seatType = seat.seat_type?.name || 'Thường';
            return `
            <div class="ticket-modal-row">
                <span>Ghế ${seatLabel}</span>
                <span>${seatType} · ${ticket.ticket_code}</span>
            </div>`;
        }).join('');
        
        this.els.modalBody.innerHTML = `
        <div class="ticket-modal-hero">
            <img src="${movie.poster_url || '/images/placeholder.jpg'}"
                 alt="${this.esc(movie.title || '')}"
                 onerror="this.src='/images/placeholder.jpg'">
            <div class="ticket-modal-hero-info">
                <span class="badge ${cls} mb-2">${statusLabel}</span>
                <h2>${this.esc(movie.title || 'Chưa rõ')}</h2>
                ${movie.age_rating ? `<span class="ticket-format-badge">${this.esc(movie.age_rating)}</span>` : ''}
                ${movie.duration ? `<small class="text-muted ms-2">${movie.duration} phút</small>` : ''}
            </div>
        </div>
        
        <div class="ticket-modal-grid">
            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-person"></i> Thông tin khách hàng</h6>
                <div class="ticket-modal-row"><span>Tên</span><strong>${this.esc(user.name || 'N/A')}</strong></div>
                <div class="ticket-modal-row"><span>Email</span><strong>${this.esc(user.email || 'N/A')}</strong></div>
                ${user.phone ? `<div class="ticket-modal-row"><span>SĐT</span><strong>${this.esc(user.phone)}</strong></div>` : ''}
            </div>
            
            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-film"></i> Suất chiếu</h6>
                <div class="ticket-modal-row"><span>Thời gian</span><strong>${this.formatDateTime(showtime.scheduled_at)}</strong></div>
                <div class="ticket-modal-row"><span>Rạp</span><strong>${this.esc(theater.name || 'N/A')}</strong></div>
                <div class="ticket-modal-row"><span>Phòng</span><strong>${this.esc(screen.name || 'N/A')}</strong></div>
            </div>
            
            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-ticket-perforated"></i> Vé đã đặt</h6>
                ${ticketsHtml}
            </div>
            
            <div class="ticket-modal-section">
                <h6 class="ticket-modal-section-title"><i class="bi bi-receipt"></i> Thanh toán</h6>
                <div class="ticket-modal-row"><span>Mã đơn</span><code>${this.esc(order.code || 'N/A')}</code></div>
                <div class="ticket-modal-row"><span>Tổng tiền</span><strong class="text-danger">${this.formatCurrency(order.total_amount || 0)}</strong></div>
                <div class="ticket-modal-row"><span>Ngày đặt</span><span>${this.formatDateTime(order.created_at)}</span></div>
                ${order.payment_method ? `<div class="ticket-modal-row"><span>Phương thức</span><span>${this.esc(order.payment_method)}</span></div>` : ''}
            </div>
        </div>
        
        <div class="ticket-modal-footer">
            <button class="ticket-primary-btn" onclick="window.adminOrdersManager.closeModal()">
                Đóng
            </button>
        </div>`;
    }
    
    closeModal() {
        this.els.modal?.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
    
    // ─── Pagination ──────────────────────────────────────────────────────────
    
    renderPagination() {
        if (!this.els.pagination || this.lastPage <= 1) {
            if (this.els.pagination) this.els.pagination.innerHTML = '';
            return;
        }
        
        const pages = [];
        const maxPages = 7;
        let start = Math.max(1, this.currentPage - 3);
        let end = Math.min(this.lastPage, start + maxPages - 1);
        
        if (end - start < maxPages - 1) {
            start = Math.max(1, end - maxPages + 1);
        }
        
        if (start > 1) pages.push(1, '...');
        for (let i = start; i <= end; i++) pages.push(i);
        if (end < this.lastPage) pages.push('...', this.lastPage);
        
        this.els.pagination.innerHTML = `
        <nav>
            <ul class="pagination justify-content-center">
                <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="event.preventDefault(); window.adminOrdersManager.loadOrders(${this.currentPage - 1})">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                ${pages.map(p => p === '...' 
                    ? '<li class="page-item disabled"><span class="page-link">...</span></li>'
                    : `<li class="page-item ${p === this.currentPage ? 'active' : ''}">
                         <a class="page-link" href="#" onclick="event.preventDefault(); window.adminOrdersManager.loadOrders(${p})">${p}</a>
                       </li>`
                ).join('')}
                <li class="page-item ${this.currentPage === this.lastPage ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="event.preventDefault(); window.adminOrdersManager.loadOrders(${this.currentPage + 1})">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>`;
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
        const candidate = String(value || '');
        if (/^\/[A-Za-z0-9_./?=&%-]*$/.test(candidate)) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate;
        return '/images/placeholder.jpg';
    }
    
    async apiRequest(endpoint, options = {}) {
        const baseUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        const url = endpoint.startsWith('http') ? endpoint : `${baseUrl}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.APP_CONFIG?.csrfToken || ''
            },
            credentials: 'include'
        };
        
        const response = await fetch(url, { ...defaultOptions, ...options });
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
}

// ─── Initialize ──────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    window.adminOrdersManager = new AdminOrdersManager();
});
