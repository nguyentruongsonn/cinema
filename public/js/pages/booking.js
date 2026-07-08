/**
 * ═══════════════════════════════════════════════════════════════════════════
 * BOOKING PAGE MODULE
 * Handles seat selection, food/combo selection, promotions, and order creation
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    // State
    let showtimeId = null;
    let showtimeData = null;
    let seatMapData = [];
    let selectedSeats = [];
    let products = [];
    let selectedProducts = {}; // { productId: quantity }
    let promotionCode = '';
    let currentTab = 'seats';

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        // Get showtime ID from page
        const bookingPage = document.querySelector('.booking-page');
        if (!bookingPage) return;

        showtimeId = bookingPage.dataset.showtimeId;
        if (!showtimeId) {
            console.error('Showtime ID not found');
            return;
        }

        try {
            await loadShowtimeData();
            await loadSeatMap();
            await loadProducts();
            renderSeatMap();
            setupEventListeners();
        } catch (error) {
            console.error('Failed to initialize booking page:', error);
            alert('Có lỗi xảy ra khi tải trang đặt vé. Vui lòng thử lại.');
        }
    }

    async function loadShowtimeData() {
        try {
            const response = await fetch(`/api/v1/showtimes/${showtimeId}`);
            if (!response.ok) throw new Error('Failed to load showtime');
            const data = await response.json();
            showtimeData = data.data || data;
        } catch (error) {
            console.error('Error loading showtime:', error);
            throw error;
        }
    }

    async function loadSeatMap() {
        try {
            const response = await fetch(`/api/v1/showtimes/${showtimeId}/seats`);
            if (!response.ok) throw new Error('Failed to load seats');
            const data = await response.json();
            seatMapData = data.data || data.seats || [];
        } catch (error) {
            console.error('Error loading seat map:', error);
            throw error;
        }
    }

    async function loadProducts() {
        try {
            const response = await fetch('/api/v1/products?type=combo');
            if (response.ok) {
                const data = await response.json();
                products = data.data || data.products || [];
                renderProducts();
            }
        } catch (error) {
            console.error('Error loading products:', error);
        }
    }

    function renderSeatMap() {
        const seatMapSkeleton = document.querySelector('.seat-map-skeleton');
        const seatMap = document.getElementById('seatMap');
        const colLabels = document.getElementById('seatGridColLabels');

        if (!seatMapData || seatMapData.length === 0) {
            if (seatMapSkeleton) seatMapSkeleton.style.display = 'none';
            seatMap.innerHTML = '<p class="text-center text-muted">Không có sơ đồ ghế</p>';
            seatMap.classList.remove('d-none');
            return;
        }

        // Group seats by row
        const seatsByRow = {};
        seatMapData.forEach(seat => {
            if (!seatsByRow[seat.row_label]) {
                seatsByRow[seat.row_label] = [];
            }
            seatsByRow[seat.row_label].push(seat);
        });

        // Sort rows and seats
        const sortedRows = Object.keys(seatsByRow).sort();
        Object.keys(seatsByRow).forEach(row => {
            seatsByRow[row].sort((a, b) => a.col_number - b.col_number);
        });

        // Render seat grid
        let seatMapHTML = '';
        sortedRows.forEach(rowLabel => {
            const seats = seatsByRow[rowLabel];
            seatMapHTML += `<div class="seat-row" data-row="${escapeHtml(rowLabel)}">`;
            seatMapHTML += `<span class="row-label">${escapeHtml(rowLabel)}</span>`;

            seats.forEach(seat => {
                const seatClasses = ['seat'];
                let isSelectable = true;

                // Status classes
                if (seat.status === 'sold' || seat.status === 'holding') {
                    seatClasses.push('seat-holding');
                    isSelectable = false;
                } else if (seat.type === 'vip') {
                    seatClasses.push('seat-vip');
                } else if (seat.type === 'couple') {
                    seatClasses.push('seat-couple');
                } else {
                    seatClasses.push('seat-available');
                }

                const seatId = seat.id;
                const isSelected = selectedSeats.some(s => s.id === seatId);
                if (isSelected) seatClasses.push('seat-selected');

                seatMapHTML += `
                    <button class="${seatClasses.join(' ')}"
                            data-seat-id="${seatId}"
                            data-row="${escapeHtml(seat.row_label)}"
                            data-col="${seat.col_number}"
                            data-type="${escapeHtml(seat.type)}"
                            ${!isSelectable ? 'disabled' : ''}
                            title="${escapeHtml(seat.row_label)}${seat.col_number}">
                        ${seat.col_number}
                    </button>
                `;
            });

            seatMapHTML += '</div>';
        });

        seatMap.innerHTML = seatMapHTML;

        // Render column labels
        if (sortedRows.length > 0) {
            const firstRow = seatsByRow[sortedRows[0]];
            const maxCol = Math.max(...firstRow.map(s => s.col_number));
            let colLabelsHTML = '<span class="row-label-spacer"></span>';
            for (let i = 1; i <= maxCol; i++) {
                colLabelsHTML += `<span class="col-label">${i}</span>`;
            }
            colLabels.innerHTML = colLabelsHTML;
            colLabels.classList.remove('d-none');
        }

        if (seatMapSkeleton) seatMapSkeleton.style.display = 'none';
        seatMap.classList.remove('d-none');
    }

    function renderProducts() {
        const container = document.getElementById('productsContainer');
        if (!container) return;

        if (!products || products.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Không có combo nào</p>';
            return;
        }

        container.innerHTML = products.map(product => `
            <div class="product-card">
                <img src="${escapeHtml(product.image_url || '/images/default-product.jpg')}"
                     alt="${escapeHtml(product.name)}"
                     class="product-image">
                <div class="product-info">
                    <h4 class="product-name">${escapeHtml(product.name)}</h4>
                    <p class="product-description">${escapeHtml(product.description || '')}</p>
                    <p class="product-price">${formatPrice(product.price)}</p>
                </div>
                <div class="product-quantity">
                    <button class="qty-btn" data-action="decrease" data-product-id="${product.id}">
                        <i class="bi bi-dash"></i>
                    </button>
                    <span class="qty-display" data-product-id="${product.id}">0</span>
                    <button class="qty-btn" data-action="increase" data-product-id="${product.id}">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    function setupEventListeners() {
        // Tab navigation
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                switchTab(tab);
            });
        });

        // Seat selection
        document.addEventListener('click', (e) => {
            const seatBtn = e.target.closest('.seat:not([disabled])');
            if (seatBtn) {
                handleSeatClick(seatBtn);
            }
        });

        // Product quantity
        document.addEventListener('click', (e) => {
            const qtyBtn = e.target.closest('.qty-btn');
            if (qtyBtn) {
                const action = qtyBtn.dataset.action;
                const productId = parseInt(qtyBtn.dataset.productId);
                handleProductQuantity(productId, action);
            }
        });

        // Next/Previous buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('#nextStepBtn')) {
                goToNextTab();
            } else if (e.target.closest('#prevStepBtn')) {
                goToPrevTab();
            } else if (e.target.closest('#paymentBtn')) {
                handleConfirmBooking();
            }
        });
    }

    function handleSeatClick(seatBtn) {
        const seatId = parseInt(seatBtn.dataset.seatId);
        const seat = seatMapData.find(s => s.id === seatId);
        if (!seat) return;

        const index = selectedSeats.findIndex(s => s.id === seatId);
        if (index >= 0) {
            // Deselect
            selectedSeats.splice(index, 1);
            seatBtn.classList.remove('seat-selected');
        } else {
            // Select
            selectedSeats.push(seat);
            seatBtn.classList.add('seat-selected');
        }

        updateSummary();
    }

    function handleProductQuantity(productId, action) {
        const current = selectedProducts[productId] || 0;

        if (action === 'increase') {
            selectedProducts[productId] = current + 1;
        } else if (action === 'decrease' && current > 0) {
            selectedProducts[productId] = current - 1;
            if (selectedProducts[productId] === 0) {
                delete selectedProducts[productId];
            }
        }

        // Update display
        const qtyDisplay = document.querySelector(`.qty-display[data-product-id="${productId}"]`);
        if (qtyDisplay) {
            qtyDisplay.textContent = selectedProducts[productId] || 0;
        }

        updateSummary();
    }

    function switchTab(tabName) {
        // Validation before switching
        if (tabName === 'food' && selectedSeats.length === 0) {
            alert('Vui lòng chọn ít nhất một ghế');
            return;
        }

        currentTab = tabName;

        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });

        // Update tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === `tab-${tabName}`);
        });

        // Render summary when switching to confirm tab
        if (tabName === 'confirm') {
            renderConfirmation();
        }
    }

    function goToNextTab() {
        const tabs = ['seats', 'food', 'promotion', 'confirm'];
        const currentIndex = tabs.indexOf(currentTab);
        if (currentIndex < tabs.length - 1) {
            switchTab(tabs[currentIndex + 1]);
        }
    }

    function goToPrevTab() {
        const tabs = ['seats', 'food', 'promotion', 'confirm'];
        const currentIndex = tabs.indexOf(currentTab);
        if (currentIndex > 0) {
            switchTab(tabs[currentIndex - 1]);
        }
    }

    function updateSummary() {
        // Update sidebar summary (if exists)
        const summarySeats = document.getElementById('summarySeats');
        const summaryTotal = document.getElementById('summaryTotal');

        if (summarySeats) {
            summarySeats.textContent = selectedSeats.length;
        }

        if (summaryTotal) {
            const total = calculateTotal();
            summaryTotal.textContent = formatPrice(total);
        }
    }

    function calculateTotal() {
        let total = 0;

        // Seat prices
        selectedSeats.forEach(seat => {
            total += parseFloat(seat.price || 0);
        });

        // Product prices
        Object.keys(selectedProducts).forEach(productId => {
            const product = products.find(p => p.id == productId);
            if (product) {
                total += product.price * selectedProducts[productId];
            }
        });

        return total;
    }

    function renderConfirmation() {
        // Render confirmation summary
        // This would populate the confirm tab with selected items
        console.log('Rendering confirmation:', {
            seats: selectedSeats,
            products: selectedProducts,
            total: calculateTotal()
        });
    }

    async function handleConfirmBooking() {
        if (selectedSeats.length === 0) {
            alert('Vui lòng chọn ít nhất một ghế');
            return;
        }

        try {
            const orderData = {
                showtime_id: showtimeId,
                seat_ids: selectedSeats.map(s => s.id),
                products: Object.keys(selectedProducts).map(id => ({
                    product_id: parseInt(id),
                    quantity: selectedProducts[id]
                })),
                promotion_code: promotionCode || null
            };

            const response = await fetch('/api/v1/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(orderData)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Đặt vé thất bại');
            }

            const result = await response.json();
            const order = result.data || result;

            // Redirect to payment
            window.location.href = `/payment/${order.id}`;
        } catch (error) {
            console.error('Booking error:', error);
            alert(error.message || 'Có lỗi xảy ra khi đặt vé. Vui lòng thử lại.');
        }
    }

    function formatPrice(price) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(price);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Expose for debugging
    window.bookingPage = {
        getState: () => ({
            showtimeId,
            selectedSeats,
            selectedProducts,
            currentTab
        }),
        switchTab,
        reload: init
    };
})();
