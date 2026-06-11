/**
 * Tickets Page JavaScript
 * Handles user's ticket list display with pagination and filtering
 * Follows API-first architecture pattern
 */

class TicketsPage {
    constructor() {
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        this.auth = window.authManager; // From auth.js
        
        // State
        this.tickets = [];
        this.currentPage = 1;
        this.lastPage = 1;
        this.perPage = 15;
        this.totalTickets = 0;
        this.currentFilter = 'all';
        
        // DOM Elements
        this.loadingContainer = document.getElementById('ticketsLoading');
        this.contentContainer = document.getElementById('ticketsContent');
        this.authRequiredContainer = document.getElementById('ticketsAuthRequired');
        this.ticketsGrid = document.getElementById('ticketsGrid');
        this.paginationContainer = document.getElementById('ticketsPagination');
        this.statusFilters = document.querySelectorAll('[data-filter-status]');
        this.errorAlert = document.getElementById('ticketsError');
        this.emptyStateContainer = document.getElementById('ticketsEmpty');
        
        this.init();
    }

    /**
     * Initialize the page
     * - Setup event listeners
     * - Load tickets if authenticated
     */
    async init() {
        try {
            this.setupEventListeners();
            await this.checkAuthAndLoad();
        } catch (error) {
            console.error('[TicketsPage] Init failed:', error);
            this.showError('Lỗi khởi tạo trang. Vui lòng tải lại.');
        }
    }

    /**
     * Setup event listeners for filters and pagination
     */
    setupEventListeners() {
        // Filter buttons
        this.statusFilters.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const status = button.dataset.filterStatus;
                this.handleFilterChange(status);
            });
        });

        // Pagination would be handled dynamically
    }

    /**
     * Check authentication and load tickets
     */
    async checkAuthAndLoad() {
        // Wait for auth check to complete
        if (window.authManager && !window.authManager.authChecked) {
            console.log('[TicketsPage] Waiting for auth check...');
            
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds
            
            while (!window.authManager.authChecked && attempts < maxAttempts) {
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
        }

        // Check if authenticated
        if (!window.authManager?.isAuthenticated()) {
            console.log('[TicketsPage] User not authenticated');
            this.showAuthRequired();
            return;
        }

        console.log('[TicketsPage] User authenticated, loading tickets...');
        await this.loadTickets();
    }

    /**
     * Load tickets from API with current filter and page
     */
    async loadTickets(page = 1) {
        try {
            this.showLoading();
            this.currentPage = page;

            // Build query parameters
            const params = new URLSearchParams({
                page,
                per_page: this.perPage,
            });

            if (this.currentFilter !== 'all') {
                params.append('status', this.currentFilter);
            }

            // Make API request
            const response = await fetch(
                `${this.apiUrl}/tickets?${params}`,
                {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                }
            );

            if (!response.ok) {
                if (response.status === 401) {
                    throw new Error('Session expired. Please login again.');
                }
                throw new Error(`HTTP ${response.status}: Failed to load tickets`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Failed to load tickets');
            }

            // Update state
            this.tickets = result.data.data || [];
            this.currentPage = result.data.meta.current_page;
            this.lastPage = result.data.meta.last_page;
            this.perPage = result.data.meta.per_page;
            this.totalTickets = result.data.meta.total;

            console.log('[TicketsPage] Loaded', this.tickets.length, 'tickets');

            // Render
            this.renderTickets();
            this.renderPagination();
            this.showContent();

        } catch (error) {
            console.error('[TicketsPage] Load tickets error:', error);
            this.showError(error.message || 'Không thể tải danh sách vé.');
        }
    }

    /**
     * Render tickets grid
     */
    renderTickets() {
        if (!this.ticketsGrid) {
            console.warn('[TicketsPage] Tickets grid container not found');
            return;
        }

        if (this.tickets.length === 0) {
            this.ticketsGrid.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-info-circle me-2"></i>
                        <span>${this.getEmptyMessage()}</span>
                    </div>
                </div>
            `;
            return;
        }

        this.ticketsGrid.innerHTML = this.tickets.map(ticket => this.renderTicketCard(ticket)).join('');
    }

    /**
     * Render single ticket card
     */
    renderTicketCard(ticket) {
        const statusBadgeClass = this.getStatusBadgeClass(ticket.status);
        const statusLabel = this.getStatusLabel(ticket.status);
        const movieTitle = ticket.showtime?.movie?.title || 'Unknown';
        const cinemaName = ticket.showtime?.screen?.cinema?.name || 'Unknown';
        const screenName = ticket.showtime?.screen?.name || 'Screen';
        const seatLabel = ticket.seat?.label || 'Unknown';
        const startTime = this.formatDateTime(ticket.showtime?.start_time);

        return `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card ticket-card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0">${this.escapeHtml(movieTitle)}</h5>
                            <span class="badge ${statusBadgeClass}">${statusLabel}</span>
                        </div>

                        <div class="ticket-info mb-3">
                            <div class="info-row mb-2">
                                <small class="text-muted">Rạp:</small>
                                <small>${this.escapeHtml(cinemaName)}</small>
                            </div>
                            <div class="info-row mb-2">
                                <small class="text-muted">Phòng:</small>
                                <small>${this.escapeHtml(screenName)}</small>
                            </div>
                            <div class="info-row mb-2">
                                <small class="text-muted">Ghế:</small>
                                <small><strong>${seatLabel}</strong></small>
                            </div>
                            <div class="info-row mb-2">
                                <small class="text-muted">Suất chiếu:</small>
                                <small>${startTime}</small>
                            </div>
                            <div class="info-row">
                                <small class="text-muted">Mã vé:</small>
                                <small><code>${ticket.ticket_code}</code></small>
                            </div>
                        </div>

                        ${ticket.qr_code ? `
                            <div class="text-center mb-3">
                                <img src="${ticket.qr_code}" alt="QR Code" class="ticket-qr" style="max-width: 100px;">
                            </div>
                        ` : ''}

                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="window.ticketsPage.showTicketDetail('${ticket.ticket_code}')">
                                Xem chi tiết
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render pagination controls
     */
    renderPagination() {
        if (!this.paginationContainer) {
            return;
        }

        if (this.lastPage <= 1) {
            this.paginationContainer.innerHTML = '';
            return;
        }

        let html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';

        // Previous button
        if (this.currentPage > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="window.ticketsPage.loadTickets(${this.currentPage - 1}); return false;">
                        &laquo; Trước
                    </a>
                </li>
            `;
        } else {
            html += '<li class="page-item disabled"><span class="page-link">&laquo; Trước</span></li>';
        }

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.lastPage, this.currentPage + 2);

        if (startPage > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="window.ticketsPage.loadTickets(1); return false;">1</a>
                </li>
            `;
            if (startPage > 2) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === this.currentPage ? 'active' : '';
            html += `
                <li class="page-item ${isActive}">
                    <a class="page-link" href="#" onclick="window.ticketsPage.loadTickets(${i}); return false;">${i}</a>
                </li>
            `;
        }

        if (endPage < this.lastPage) {
            if (endPage < this.lastPage - 1) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="window.ticketsPage.loadTickets(${this.lastPage}); return false;">${this.lastPage}</a>
                </li>
            `;
        }

        // Next button
        if (this.currentPage < this.lastPage) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="window.ticketsPage.loadTickets(${this.currentPage + 1}); return false;">
                        Tiếp &raquo;
                    </a>
                </li>
            `;
        } else {
            html += '<li class="page-item disabled"><span class="page-link">Tiếp &raquo;</span></li>';
        }

        html += '</ul></nav>';
        this.paginationContainer.innerHTML = html;
    }

    /**
     * Handle filter status change
     */
    handleFilterChange(status) {
        this.currentFilter = status;
        this.currentPage = 1;

        // Update active button
        this.statusFilters.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filterStatus === status);
        });

        // Reload tickets
        this.loadTickets(1);
    }

    /**
     * Show ticket detail (placeholder for future modal/detail view)
     */
    showTicketDetail(ticketCode) {
        console.log('[TicketsPage] Show detail for:', ticketCode);
        alert('Chi tiết vé: ' + ticketCode);
        // TODO: Implement modal or detail page
    }

    // UI State Methods

    showLoading() {
        if (this.loadingContainer) this.loadingContainer.style.display = 'block';
        if (this.contentContainer) this.contentContainer.style.display = 'none';
        if (this.authRequiredContainer) this.authRequiredContainer.style.display = 'none';
        if (this.errorAlert) this.errorAlert.style.display = 'none';
    }

    showContent() {
        if (this.loadingContainer) this.loadingContainer.style.display = 'none';
        if (this.contentContainer) this.contentContainer.style.display = 'block';
        if (this.authRequiredContainer) this.authRequiredContainer.style.display = 'none';
        if (this.errorAlert) this.errorAlert.style.display = 'none';
    }

    showAuthRequired() {
        if (this.loadingContainer) this.loadingContainer.style.display = 'none';
        if (this.contentContainer) this.contentContainer.style.display = 'none';
        if (this.authRequiredContainer) this.authRequiredContainer.style.display = 'block';
        if (this.errorAlert) this.errorAlert.style.display = 'none';
    }

    showError(message) {
        console.error('[TicketsPage] Error:', message);
        if (this.errorAlert) {
            this.errorAlert.textContent = message;
            this.errorAlert.style.display = 'block';
        }
        if (this.loadingContainer) this.loadingContainer.style.display = 'none';
        if (this.contentContainer && this.tickets.length === 0) {
            this.contentContainer.style.display = 'none';
        }
    }

    // Helper Methods

    getStatusLabel(status) {
        const labels = {
            'valid': 'Còn hạn',
            'used': 'Đã sử dụng',
            'cancelled': 'Đã hủy',
            'refunded': 'Đã hoàn tiền',
        };
        return labels[status] || status;
    }

    getStatusBadgeClass(status) {
        const classes = {
            'valid': 'bg-success',
            'used': 'bg-secondary',
            'cancelled': 'bg-danger',
            'refunded': 'bg-warning text-dark',
        };
        return classes[status] || 'bg-secondary';
    }

    getEmptyMessage() {
        if (this.currentFilter === 'all') {
            return 'Bạn chưa có vé nào. Hãy đặt vé để xem phim!';
        }
        return `Bạn không có vé nào với trạng thái "${this.getStatusLabel(this.currentFilter)}"`;
    }

    formatDateTime(datetime) {
        if (!datetime) return 'N/A';
        try {
            return new Date(datetime).toLocaleString('vi-VN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return 'N/A';
        }
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Auto-initialize when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.ticketsPage = new TicketsPage();
    });
} else {
    window.ticketsPage = new TicketsPage();
}