/**
 * My Tickets Page - Order History
 * Handles user ticket history display and interactions
 */

(function () {
    'use strict';

    // DOM Elements
    const elements = {
        authRequired: document.getElementById('ticketsAuthRequired'),
        loading: document.getElementById('ticketsLoading'),
        content: document.getElementById('ticketsContent'),
        empty: document.getElementById('ticketsEmpty'),
        list: document.getElementById('ticketsList'),
        loadMore: document.getElementById('ticketsLoadMore'),
        loadMoreBtn: document.getElementById('ticketsLoadMoreBtn'),
        loadingMore: document.getElementById('ticketsLoadingMore'),
        avatar: document.getElementById('ticketsAvatar'),
        avatarFallback: document.getElementById('ticketsAvatarFallback'),
        userName: document.getElementById('ticketsUserName'),
        userRank: document.getElementById('ticketsUserRank'),
        tabs: document.querySelectorAll('.tickets-tab'),
        ticketCardTemplate: document.getElementById('ticketCardTemplate'),
        formatBadgeTemplate: document.getElementById('formatBadgeTemplate'),
    };

    // State
    const state = {
        user: null,
        orders: [],
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        loading: false,
        currentFilter: 'all', // 'all' or 'current-year'
    };

    /**
     * Initialize page
     */
    async function init() {
        if (!window.authManager?.isAuthenticated()) {
            showAuthRequired();
            setupAuthButtons();
            return;
        }

        const user = window.authManager.getUser();

        state.user = user;
        showContent();

        if (user) {
            renderUserInfo(user);
        }

        await loadOrders();
        setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Tab filtering
        elements.tabs.forEach(tab => {
            tab.addEventListener('click', () => handleTabChange(tab));
        });

        // Load more button
        if (elements.loadMoreBtn) {
            elements.loadMoreBtn.addEventListener('click', loadMoreOrders);
        }

        // Rebook buttons (delegated)
        if (elements.list) {
            elements.list.addEventListener('click', (e) => {
                const rebookBtn = e.target.closest('.ticket-rebook-btn');
                if (rebookBtn) {
                    const orderId = rebookBtn.dataset.orderId;
                    const movieSlug = rebookBtn.dataset.movieSlug;
                    handleRebook(orderId, movieSlug);
                }
            });
        }
    }

    /**
     * Setup auth buttons
     */
    function setupAuthButtons() {
        const loginBtn = elements.authRequired?.querySelector('[data-auth-action="login"]');
        if (loginBtn && window.authManager) {
            loginBtn.addEventListener('click', () => {
                window.authManager.showModal('login');
            });
        }
    }

    /**
     * Show auth required state
     */
    function showAuthRequired() {
        elements.loading?.classList.add('d-none');
        elements.content?.classList.add('d-none');
        elements.authRequired?.classList.remove('d-none');
    }

    /**
     * Show content
     */
    function showContent() {
        elements.authRequired?.classList.add('d-none');
        elements.loading?.classList.add('d-none');
        elements.content?.classList.remove('d-none');
    }

    /**
     * Render user info
     */
    function renderUserInfo(user) {
        if (!user) return;

        // Set user name
        if (elements.userName) {
            elements.userName.textContent = user.name || 'Người dùng';
        }

        // Set user rank/role
        if (elements.userRank) {
            const role = user.role || user.roles?.[0];
            let rankText = 'Thành viên';

            if (role) {
                if (role === 'premium' || role.slug === 'premium') {
                    rankText = 'Thành viên Premium';
                } else if (role === 'vip' || role.slug === 'vip') {
                    rankText = 'Thành viên VIP';
                }
            }

            elements.userRank.textContent = rankText;
        }

        // Set avatar
        if (user.avatar) {
            if (elements.avatar) {
                elements.avatar.src = user.avatar;
                elements.avatar.classList.remove('d-none');
            }
            if (elements.avatarFallback) {
                elements.avatarFallback.classList.add('d-none');
            }
        } else {
            const initial = (user.name || 'U').charAt(0).toUpperCase();
            if (elements.avatarFallback) {
                elements.avatarFallback.textContent = initial;
            }
        }
    }

    /**
     * Load orders from API
     */
    async function loadOrders(page = 1) {
        if (state.loading) return;

        state.loading = true;

        try {
            const response = await fetch(
                `${window.APP_CONFIG.apiUrl}/orders/user/me?page=${page}&per_page=${state.perPage}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    },
                }
            );

            if (!response.ok) {
                throw new Error('Failed to load orders');
            }

            const result = await response.json();

            if (result.success && result.data) {
                const { data, current_page, last_page } = result.data;

                if (page === 1) {
                    state.orders = data;
                } else {
                    state.orders = [...state.orders, ...data];
                }

                state.currentPage = current_page;
                state.lastPage = last_page;

                renderOrders();
                updateLoadMoreButton();
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            showError('Không thể tải lịch sử đặt vé. Vui lòng thử lại.');
        } finally {
            state.loading = false;
        }
    }

    /**
     * Load more orders
     */
    async function loadMoreOrders() {
        if (state.currentPage >= state.lastPage) return;

        elements.loadMore?.classList.add('d-none');
        elements.loadingMore?.classList.remove('d-none');

        await loadOrders(state.currentPage + 1);

        elements.loadingMore?.classList.add('d-none');
    }

    /**
     * Render orders
     */
    function renderOrders() {
        if (!elements.list) return;

        const filteredOrders = filterOrders(state.orders);

        if (filteredOrders.length === 0) {
            elements.list.innerHTML = '';
            elements.empty?.classList.remove('d-none');
            elements.loadMore?.classList.add('d-none');
            return;
        }

        elements.empty?.classList.add('d-none');
        elements.list.innerHTML = '';

        filteredOrders.forEach(order => {
            const card = createTicketCard(order);
            elements.list.appendChild(card);
        });
    }

    /**
     * Filter orders based on current filter
     */
    function filterOrders(orders) {
        if (state.currentFilter === 'current-year') {
            const currentYear = new Date().getFullYear();
            return orders.filter(order => {
                const orderYear = new Date(order.created_at).getFullYear();
                return orderYear === currentYear;
            });
        }

        return orders;
    }

    /**
     * Create ticket card element
     */
    function createTicketCard(order) {
        const template = elements.ticketCardTemplate.content.cloneNode(true);
        const card = template.querySelector('.ticket-card');

        // Poster
        const poster = card.querySelector('.ticket-poster-img');
        const posterUrl = order.poster_url || order.showtime?.movie?.poster_url;
        const movieTitle = order.movie_title || order.showtime?.movie?.title;
        if (posterUrl) {
            poster.src = posterUrl;
            poster.alt = movieTitle || 'Movie poster';
        }

        // Format badges
        const formatsContainer = card.querySelector('.ticket-formats');
        if (order.showtime?.format) {
            const badge = createFormatBadge(order.showtime.format.name);
            formatsContainer.appendChild(badge);
        }
        if (order.showtime?.sound) {
            const badge = createFormatBadge(order.showtime.sound.name);
            formatsContainer.appendChild(badge);
        }

        // Order ID
        const ticketId = card.querySelector('.ticket-id');
        ticketId.textContent = `ID: #CP-${order.id.toString().padStart(5, '0')}`;

        // Movie title
        const title = card.querySelector('.ticket-title');
        title.textContent = order.showtime?.movie?.title || 'N/A';

        // Showtime
        const showtime = card.querySelector('.ticket-showtime');
        const showDate = order.show_date || order.showtime?.scheduled_at;
        if (showDate) {
            const date = new Date(showDate);
            const formattedDate = `${date.getDate()} Tháng ${date.getMonth() + 1}, ${date.getFullYear()}`;
            showtime.textContent = formattedDate;
        }

        // Theater
        const theater = card.querySelector('.ticket-theater');
        if (order.showtime?.screen?.theater) {
            const theaterData = order.showtime.screen.theater;
            theater.textContent = `${theaterData.name}${theaterData.branch ? ` - ${theaterData.branch.name}` : ''}`;
        }

        // Seats
        const seats = card.querySelector('.ticket-seats');
        let seatNamesStr = 'N/A';
        
        if (order.items && order.items.length > 0) {
            const seatNames = order.items
                .filter(item => item.item_type === 'App\\Models\\Seat' || item.type === 'Seat' || item.type === 'seat')
                .map(item => {
                    if (item.metadata && item.metadata.seat_label) return item.metadata.seat_label;
                    const seat = item.item || item.seat || item;
                    if (!seat) return null;
                    return seat.label || (seat.row && seat.number ? seat.row + seat.number : null) || seat.seat_number || seat.name;
                })
                .filter(Boolean)
                .join(', ');
            if (seatNames) seatNamesStr = seatNames;
        } else if (order.payload && order.payload.seats && order.payload.seats.length > 0) {
            seatNamesStr = order.payload.seats.map(s => s.name || (s.row + s.number)).join(', ');
        }
        
        seats.textContent = seatNamesStr;

        // Status
        const status = card.querySelector('.ticket-status');
        status.textContent = getOrderStatusText(order.status);
        if (order.status !== 'completed' && order.status !== 'confirmed') {
            status.style.setProperty('--tickets-green', '#f59e0b');
        }

        // Rebook button
        const rebookBtn = card.querySelector('.ticket-rebook-btn');
        rebookBtn.dataset.orderId = order.id;
        if (order.showtime?.movie?.slug) {
            rebookBtn.dataset.movieSlug = order.showtime.movie.slug;
        }

        return card;
    }

    /**
     * Create format badge
     */
    function createFormatBadge(text) {
        const template = elements.formatBadgeTemplate.content.cloneNode(true);
        const badge = template.querySelector('.ticket-format-badge');
        badge.textContent = text.toUpperCase();
        return badge;
    }

    /**
     * Get order status text in Vietnamese
     */
    function getOrderStatusText(status) {
        const statusMap = {
            'pending': 'Đang chờ',
            'confirmed': 'Đã xác nhận',
            'completed': 'Đã hoàn thành',
            'cancelled': 'Đã hủy',
            'expired': 'Hết hạn',
        };

        return statusMap[status] || status;
    }

    /**
     * Handle tab change
     */
    function handleTabChange(tab) {
        const filter = tab.dataset.filter;
        if (filter === state.currentFilter) return;

        // Update active tab
        elements.tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Update filter and re-render
        state.currentFilter = filter;
        renderOrders();
    }

    /**
     * Handle rebook action
     */
    function handleRebook(orderId, movieSlug) {
        if (movieSlug) {
            window.location.href = `/movies/${movieSlug}`;
        } else {
            window.location.href = '/movies';
        }
    }

    /**
     * Update load more button visibility
     */
    function updateLoadMoreButton() {
        if (!elements.loadMore) return;

        if (state.currentPage < state.lastPage) {
            elements.loadMore.classList.remove('d-none');
        } else {
            elements.loadMore.classList.add('d-none');
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        console.error(message);
        // Could implement toast notification here
    }

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
