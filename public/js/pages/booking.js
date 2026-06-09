 /**
 * Booking Page JavaScript
 * Handles seat selection, locking, timer, and order creation
 */

class BookingManager {
    constructor() {
        this.config = window.BOOKING_CONFIG || {};
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api';
        this.auth = window.authManager; // From auth.js

        // State
        this.seats = [];
        this.selectedSeats = new Set();
        this.currentHold = null;
        this.timer = null;
        this.timerSeconds = 600; // 10 minutes
        this.basePrice = parseFloat(this.config.basePrice) || 0;
        this.products = [];
        this.selectedProducts = new Map();
        this.appliedPromotion = null;
        this.currentStep = 1; // Track current step (1-4)
        this.steps = ['seats', 'food', 'promotion', 'confirm'];

        // DOM Elements
        this.seatMapContainer = document.getElementById('seatMap');
        this.seatMapSkeleton = document.querySelector('.seat-map-skeleton');
        this.selectedSeatsDisplay = document.getElementById('selectedSeatsDisplay');
        this.ticketPriceDisplay = document.getElementById('ticketPriceDisplay');
        this.convenienceFeeDisplay = document.getElementById('convenienceFeeDisplay');
        this.totalPriceDisplay = document.getElementById('totalPriceDisplay');
        this.selectedSeatsList = document.getElementById('selectedSeatsList');
        this.seatQuantity = document.getElementById('seatQuantity');
        this.seatSurcharge = document.getElementById('seatSurcharge');
        this.totalPrice = document.getElementById('totalPrice');
        this.productTotal = document.getElementById('productTotal');
        this.selectedProductsList = document.getElementById('selectedProductsList');
        this.productsContainer = document.getElementById('productsContainer');
        this.promotionCodeInput = document.getElementById('promotionCodeInput');
        this.applyPromotionBtn = document.getElementById('applyPromotionBtn');
        this.promotionMessage = document.getElementById('promotionMessage');
        this.discountAmount = document.getElementById('discountAmount');
        this.proceedBtn = document.getElementById('proceedToPaymentBtn');
        this.cancelBtn = document.getElementById('cancelSelectionBtn');
        this.nextStepBtn = document.getElementById('nextStepBtn');
        this.prevStepBtn = document.getElementById('prevStepBtn');
        this.paymentBtn = document.getElementById('paymentBtn');
        this.sidebarContinueBtn = document.getElementById('sidebarContinueBtn');
        this.timerDisplay = document.getElementById('bookingTimer');
        this.loadingOverlay = document.getElementById('loadingOverlay');

        this.init();
    }

    async init() {
        // Check URL parameters for payment status
        const shouldContinue = this.checkUrlParams();
        if (!shouldContinue) return;

        // Setup event listeners
        this.setupEventListeners();

        // Load page data (seats should always load, auth check happens on seat click)
        await Promise.all([
            this.loadSeats(),
            this.loadProducts()
        ]);

        // Subscribe to real-time WebSocket channels
        this.subscribeToRealtimeChannels();
    }

    /**
     * Subscribe to Laravel Reverb real-time channels.
     * - showtime.{id}   → public channel: seat lock/unlock updates for all viewers
     * - order.{code}    → private channel: payment confirmation for the buyer
     */
    subscribeToRealtimeChannels() {
        if (typeof window.Echo === 'undefined') return;

        const showtimeId = this.config.showtimeId;
        if (!showtimeId) return;

        // 1. Real-time seat status (public – no auth needed)
        window.Echo.channel(`showtime.${showtimeId}`)
            .listen('.seat.status.updated', (event) => {
                this.applyRealtimeSeatStatus(event.seat_id, event.status);
            });

        // 2. Real-time payment result (private – requires auth)
        //    Subscribe only when user has initiated a payment (orderCode present).
        //    The orderCode is stored on `this.currentOrderCode` after fetchAPI payment.
        if (this.currentOrderCode) {
            this.subscribeToOrderChannel(this.currentOrderCode);
        }
    }

    subscribeToOrderChannel(orderCode) {
        if (typeof window.Echo === 'undefined' || !orderCode) return;

        window.Echo.private(`order.${orderCode}`)
            .listen('.order.paid', () => {
                // Auto-transition to success screen in real-time (no redirect needed)
                this.showSuccessScreen(orderCode);
            });
    }

    /**
     * Update a single seat element in the DOM based on a real-time event.
     * This avoids re-rendering the whole seat map.
     */
    applyRealtimeSeatStatus(seatId, status) {
        // Update in-memory seat data
        const seat = this.seats.find(s => s.id === seatId);
        if (seat) {
            seat.status      = status;
            seat.is_locked    = status === 'locked';
            seat.is_available = status === 'available';
        }

        // Update DOM element directly
        const seatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${seatId}"]`);
        if (!seatEl) return;

        // Remove all status classes
        seatEl.classList.remove('seat-available', 'seat-locked', 'seat-booked', 'seat-holding', 'seat-selected');

        if (status === 'available') {
            // Only restore to available if this seat is not selected by current user
            if (!this.selectedSeats.has(seatId)) {
                seatEl.classList.add('seat-available');
                seatEl.setAttribute('role', 'button');
                seatEl.setAttribute('tabindex', '0');
                seatEl.removeAttribute('aria-disabled');
                // Re-attach click handler by re-rendering (safe since seat data updated)
                const freshSeat = this.seats.find(s => s.id === seatId);
                if (freshSeat) {
                    seatEl.onclick = () => this.handleSeatClick(freshSeat);
                }
            }
        } else {
            // Locked by someone else – remove from selection if user had it
            if (this.selectedSeats.has(seatId)) {
                this.selectedSeats.delete(seatId);
                this.updateSummary();
                this.showToast('Ghế bạn chọn vừa bị người khác đặt. Vui lòng chọn ghế khác.', 'warning');
            }
            seatEl.classList.add('seat-locked');
            seatEl.removeAttribute('role');
            seatEl.removeAttribute('tabindex');
            seatEl.setAttribute('aria-disabled', 'true');
            seatEl.onclick = null;
        }
    }

    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentStatus = urlParams.get('paymentStatus');
        const orderCode = urlParams.get('orderCode');

        if (paymentStatus) {
            // Clean up URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);

            if (paymentStatus === 'success' || paymentStatus === 'PAID') {
                if (orderCode) {
                    this.showSuccessScreen(orderCode);
                    return false; // Stop normal initialization
                }
            } else if (paymentStatus === 'cancelled') {
                if (orderCode) {
                    this.showFailureScreen(orderCode);
                    return false;
                } else {
                    setTimeout(() => {
                        this.showToast('Bạn đã huỷ thanh toán. Vui lòng thử lại.', 'warning');
                    }, 500);
                }
            }
        }
        return true;
    }

    async showSuccessScreen(orderCode) {
        // Hide normal booking elements safely
        const tabsEl = document.querySelector('.booking-tabs');
        if (tabsEl) tabsEl.style.display = 'none';

        const containerEl = document.querySelector('.booking-container');
        if (containerEl) containerEl.style.display = 'none';

        const successScreen = document.getElementById('successScreen');
        if (!successScreen) return;

        successScreen.classList.remove('d-none');
        this.showLoading('Đang tải thông tin vé...');

        try {
            const response = await this.fetchAPI(`/payments/orders/${orderCode}`, {
                method: 'GET'
            });

            if (response.success && response.data) {
                const order = response.data;
                const showtime = order.showtime || {};

                // Show success toast notification
                this.showToast('Thanh toán thành công!', 'success');

                // Populate UI safely with optional chaining
                const movieTitleEl = document.getElementById('successMovieTitle');
                if (movieTitleEl) movieTitleEl.textContent = showtime.movie_title || '---';

                const showDateEl = document.getElementById('successShowDate');
                const showTimeEl = document.getElementById('successShowTime');
                if (showtime.scheduled_at) {
                    const date = new Date(showtime.scheduled_at);
                    if (showDateEl) showDateEl.textContent = date.toLocaleDateString('vi-VN');
                    if (showTimeEl) showTimeEl.textContent = date.toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
                } else {
                    // Use current date if no showtime
                    if (showDateEl) showDateEl.textContent = new Date().toLocaleDateString('vi-VN');
                }

                const theaterEl = document.getElementById('successTheater');
                if (theaterEl) theaterEl.textContent = showtime.theater_name || '---';

                const screenEl = document.getElementById('successScreenName');
                if (screenEl) screenEl.textContent = showtime.screen_name || '---';

                const orderCodeEl = document.getElementById('successOrderCode');
                if (orderCodeEl) orderCodeEl.textContent = order.gateway_order_code || order.order_code;

                const totalAmtEl = document.getElementById('successTotalAmount');
                if (totalAmtEl) totalAmtEl.textContent = this.formatCurrency(order.total_amount);

                // Populate Items (Seats & Products)
                const seatsContainer = document.getElementById('successSeats');
                const productsContainer = document.getElementById('successProducts');
                const productsWrapper = document.getElementById('successProductsContainer');

                if (seatsContainer) seatsContainer.innerHTML = '';
                if (productsContainer) productsContainer.innerHTML = '';
                let hasProducts = false;

                if (order.items && Array.isArray(order.items)) {
                    order.items.forEach(item => {
                        if (item.type === 'Seat' && seatsContainer) {
                            const badge = document.createElement('span');
                            badge.className = 'seat-badge';
                            badge.textContent = item.metadata?.seat_label || `Ghế ID ${item.id}`;
                            seatsContainer.appendChild(badge);
                        } else if (item.type === 'Product' && productsContainer) {
                            hasProducts = true;
                            const prodLine = document.createElement('div');
                            prodLine.textContent = `${item.quantity}x ${item.metadata?.product_name || 'Combo'}`;
                            productsContainer.appendChild(prodLine);
                        }
                    });
                }

                if (hasProducts && productsWrapper) {
                    productsWrapper.style.display = 'block';
                }
            } else {
                throw new Error(response.message || 'Không thể tải thông tin đơn hàng');
            }
        } catch (error) {
            console.error('Lỗi load success screen:', error);
            this.showToast('Có lỗi xảy ra khi tải thông tin vé: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    }

    showFailureScreen(orderCode) {
        // Hide normal booking elements
        const tabsEl = document.querySelector('.booking-tabs');
        if (tabsEl) tabsEl.style.display = 'none';

        const containerEl = document.querySelector('.booking-container');
        if (containerEl) containerEl.style.display = 'none';

        const failureScreen = document.getElementById('failureScreen');
        if (!failureScreen) return;

        failureScreen.classList.remove('d-none');

        const orderCodeEl = document.getElementById('failureOrderCode');
        if (orderCodeEl && orderCode) {
            orderCodeEl.textContent = orderCode;
        }

        const dateEl = document.getElementById('failureDate');
        if (dateEl) {
            dateEl.textContent = new Date().toLocaleDateString('vi-VN');
        }
    }

    setupEventListeners() {
        // Tab navigation - scoped to booking page only, avoid affecting Bootstrap/auth modal tabs
        document.querySelectorAll('.booking-page .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                const stepIndex = this.steps.indexOf(tabName);
                if (stepIndex >= 0) {
                    this.switchTab(stepIndex + 1);
                }
            });
        });

        // Step navigation buttons
        this.nextStepBtn?.addEventListener('click', () => this.goToNextStep());
        this.prevStepBtn?.addEventListener('click', () => this.goToPrevStep());
        this.sidebarContinueBtn?.addEventListener('click', () => this.goToNextStep());
        this.paymentBtn?.addEventListener('click', () => this.proceedToPayment());

        // Proceed to payment (legacy)
        this.proceedBtn?.addEventListener('click', () => this.proceedToPayment());

        // Cancel selection
        this.cancelBtn?.addEventListener('click', () => this.cancelSelection());

        // Promotion
        this.applyPromotionBtn?.addEventListener('click', () => this.validatePromotion());
        this.promotionCodeInput?.addEventListener('input', () => {
            this.appliedPromotion = null;
            this.setPromotionMessage('', '');
            this.updateSummary();
        });
        this.promotionCodeInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.validatePromotion();
            }
        });

        // Handle page unload (unlock seats)
        window.addEventListener('beforeunload', (e) => {
            if (this.currentHold) {
                navigator.sendBeacon(
                    `${this.apiUrl}/seats/unlock/${this.currentHold.hold_id}`,
                    JSON.stringify({ _method: 'DELETE' })
                );
            }
        });
    }

    switchTab(step) {
        if (step < 1 || step > 4) return;

        this.currentStep = step;
        const tabName = this.steps[step - 1];

        // Update tab buttons - scoped to booking page only
        document.querySelectorAll('.booking-page .tab-btn').forEach((btn, index) => {
            btn.classList.toggle('active', index === step - 1);
            btn.classList.toggle('completed', index < step - 1);
        });

        // Update tab content - scoped to booking page only, do not touch auth modal .tab-content
        document.querySelectorAll('.booking-page .tab-content').forEach((content, index) => {
            content.classList.toggle('active', index === step - 1);
        });

        // Update navigation buttons
        this.updateStepButtons();

        // Load products when entering food tab
        if (step === 2 && this.products.length === 0) {
            this.loadProducts();
        }

        // Populate confirm tab when entering
        if (step === 4) {
            this.populateConfirmStep();
        }
    }

    goToNextStep() {
        if (!this.validateCurrentStep()) {
            return;
        }

        if (this.currentStep < 4) {
            this.switchTab(this.currentStep + 1);
        } else {
            this.proceedToPayment();
        }
    }

    goToPrevStep() {
        if (this.currentStep > 1) {
            this.switchTab(this.currentStep - 1);
        }
    }

    validateCurrentStep() {
        switch (this.currentStep) {
            case 1: // Seats
                if (this.selectedSeats.size === 0) {
                    this.showToast('Vui lòng chọn ghế trước', 'warning');
                    return false;
                }
                return true;

            case 2: // Food - optional, always valid
                return true;

            case 3: // Promotion - optional, always valid
                return true;

            case 4: // Confirm - just review
                return true;

            default:
                return false;
        }
    }

    updateStepButtons() {
        // Update "Tiếp tục" button
        const canProceed = this.validateCurrentStep();

        if (this.nextStepBtn) {
            this.nextStepBtn.disabled = !canProceed;
            this.nextStepBtn.style.display = this.currentStep < 4 ? 'block' : 'none';
        }

        if (this.sidebarContinueBtn) {
            this.sidebarContinueBtn.disabled = !canProceed;
            this.sidebarContinueBtn.textContent = this.currentStep < 4 ? 'Tiếp tục' : 'Thanh toán';
        }

        // Update "Quay lại" button
        if (this.prevStepBtn) {
            this.prevStepBtn.style.display = this.currentStep > 1 ? 'block' : 'none';
        }

        // Update "Thanh toán" button
        if (this.paymentBtn) {
            this.paymentBtn.style.display = this.currentStep === 4 ? 'block' : 'none';
            this.paymentBtn.disabled = !canProceed;
        }
    }

    populateConfirmStep() {
        // Populate seats
        const confirmSeatsInfo = document.getElementById('confirmSeatsInfo');
        if (confirmSeatsInfo) {
            if (this.selectedSeats.size > 0) {
                const seatsHtml = Array.from(this.selectedSeats)
                    .map(seatId => {
                        const seat = this.seats.find(s => s.id === seatId);
                        if (!seat) return '';
                        const label = seat.label || `${seat.row}${seat.number}`;
                        const typeName = seat.seat_type?.name || 'Thường';
                        const price = this.basePrice + parseFloat(seat.seat_type?.surcharge || 0);
                        return `
                            <div class="confirm-info-row">
                                <span class="info-label">Ghế ${label} (${typeName})</span>
                                <span class="info-value">${this.formatCurrency(price)}</span>
                            </div>
                        `;
                    })
                    .join('');
                confirmSeatsInfo.innerHTML = seatsHtml;
            } else {
                confirmSeatsInfo.innerHTML = '<p class="text-muted">Chưa chọn ghế</p>';
            }
        }

        // Populate products
        const confirmProductsCard = document.getElementById('confirmProductsCard');
        const confirmProductsInfo = document.getElementById('confirmProductsInfo');
        if (confirmProductsCard && confirmProductsInfo) {
            if (this.selectedProducts.size > 0) {
                const productsHtml = Array.from(this.selectedProducts.entries())
                    .map(([productId, quantity]) => {
                        const product = this.products.find(p => p.id === productId);
                        if (!product) return '';
                        const total = parseFloat(product.price) * quantity;
                        return `
                            <div class="confirm-info-row">
                                <span class="info-label">${this.escapeHtml(product.name)} x${quantity}</span>
                                <span class="info-value">${this.formatCurrency(total)}</span>
                            </div>
                        `;
                    })
                    .join('');
                confirmProductsInfo.innerHTML = productsHtml;
                confirmProductsCard.style.display = 'block';
            } else {
                confirmProductsCard.style.display = 'none';
            }
        }

        // Populate promotion
        const confirmPromotionCard = document.getElementById('confirmPromotionCard');
        const confirmPromotionInfo = document.getElementById('confirmPromotionInfo');
        if (confirmPromotionCard && confirmPromotionInfo) {
            if (this.appliedPromotion) {
                confirmPromotionInfo.innerHTML = `
                    <div class="confirm-info-row">
                        <span class="info-label">${this.escapeHtml(this.appliedPromotion.code)}</span>
                        <span class="info-value text-success">-${this.formatCurrency(this.appliedPromotion.discount_amount)}</span>
                    </div>
                `;
                confirmPromotionCard.style.display = 'block';
            } else {
                confirmPromotionCard.style.display = 'none';
            }
        }
    }

    updateSidebarSummary() {
        // Update selected seats display
        if (this.selectedSeatsDisplay) {
            if (this.selectedSeats.size > 0) {
                const seatLabels = Array.from(this.selectedSeats)
                    .map(seatId => {
                        const seat = this.seats.find(s => s.id === seatId);
                        return seat ? (seat.label || `${seat.row}${seat.number}`) : '';
                    })
                    .filter(label => label)
                    .join(', ');
                this.selectedSeatsDisplay.textContent = seatLabels;
                this.selectedSeatsDisplay.classList.remove('text-danger');
            } else {
                this.selectedSeatsDisplay.textContent = 'Chưa chọn ghế';
                this.selectedSeatsDisplay.classList.add('text-danger');
            }
        }

        // Calculate prices
        let totalSurcharge = 0;
        let quantity = this.selectedSeats.size;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat && seat.seat_type) {
                totalSurcharge += parseFloat(seat.seat_type.surcharge || 0);
            }
        });

        const seatTotal = (this.basePrice * quantity) + totalSurcharge;
        const productsTotal = this.calculateProductsTotal();
        const subtotal = seatTotal + productsTotal;
        const discount = this.calculateDiscount(subtotal);
        const total = Math.max(0, subtotal - discount);

        // Update displays
        if (this.ticketPriceDisplay) {
            this.ticketPriceDisplay.textContent = this.formatCurrency(seatTotal);
        }

        if (this.convenienceFeeDisplay) {
            this.convenienceFeeDisplay.textContent = '0 đ';
        }

        if (this.totalPriceDisplay) {
            this.totalPriceDisplay.textContent = this.formatCurrency(total);
        }
    }

    async loadSeats() {
        try {
            this.showLoading();

            const response = await this.fetchAPI(
                `/seats/showtime/${this.config.showtimeId}`
            );

            if (response.success) {
                this.seats = response.data.seats || [];
                this.currentHold = response.data.current_user_holds?.[0] || null;

                // If user has existing hold, restore selection
                if (this.currentHold && this.currentHold.seat_ids) {
                    this.currentHold.seat_ids.forEach(id => {
                        this.selectedSeats.add(parseInt(id));
                    });
                    this.startTimer(this.currentHold.expires_in_seconds || 600);
                }

                this.renderSeatMap();
                this.updateSummary();
            } else {
                throw new Error(response.message || 'Không thể tải danh sách ghế');
            }
        } catch (error) {
            console.error('Load seats error:', error);
            this.showToast(error.message || 'Lỗi khi tải ghế', 'danger');
        } finally {
            this.hideLoading();
        }
    }

    async loadProducts() {
        if (!this.productsContainer) return;

        try {
            const response = await this.fetchAPI('/products');

            if (!response.success) {
                throw new Error(response.message || 'Không thể tải combo');
            }

            this.products = response.data || [];
            this.renderProducts();
        } catch (error) {
            console.error('Load products error:', error);
            this.productsContainer.innerHTML = '<div class="alert alert-warning mb-0 small">Không thể tải danh sách combo.</div>';
        }
    }

    renderProducts() {
        if (!this.productsContainer) return;

        if (this.products.length === 0) {
            this.productsContainer.innerHTML = '<div class="text-center text-muted py-4">Hiện chưa có combo khả dụng.</div>';
            return;
        }

        this.productsContainer.innerHTML = this.products.map(product => {
            const quantity = this.selectedProducts.get(product.id) || 0;
            const image = product.image_url || '/images/placeholder.jpg';

            return `
                <div class="product-card" data-product-id="${product.id}">
                    <img src="${image}" alt="${this.escapeHtml(product.name)}" class="product-image">
                    <div class="product-info">
                        <div class="product-name">${this.escapeHtml(product.name)}</div>
                        <div class="product-price">${this.formatCurrency(product.price)}</div>
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn minus" data-action="decrease" ${quantity <= 0 ? 'disabled' : ''}>−</button>
                            <span class="quantity-value">${quantity}</span>
                            <button type="button" class="quantity-btn plus" data-action="increase" ${quantity >= product.stock ? 'disabled' : ''}>+</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        this.productsContainer.querySelectorAll('.product-card').forEach(item => {
            const productId = parseInt(item.dataset.productId);
            item.querySelector('[data-action="decrease"]')?.addEventListener('click', () => this.changeProductQuantity(productId, -1));
            item.querySelector('[data-action="increase"]')?.addEventListener('click', () => this.changeProductQuantity(productId, 1));
        });
    }

    changeProductQuantity(productId, delta) {
        const product = this.products.find(item => item.id === productId);
        if (!product) return;

        const currentQuantity = this.selectedProducts.get(productId) || 0;
        const nextQuantity = Math.max(0, Math.min(product.stock, currentQuantity + delta));

        if (nextQuantity === 0) {
            this.selectedProducts.delete(productId);
        } else {
            this.selectedProducts.set(productId, nextQuantity);
        }

        this.appliedPromotion = null;
        this.setPromotionMessage('', '');
        this.renderProducts();
        this.updateSummary();
    }

    renderSeatMap() {
        if (!this.seatMapContainer) return;

        // Hide skeleton, show map
        this.seatMapSkeleton?.classList.add('d-none');
        this.seatMapContainer.classList.remove('d-none');
        this.seatMapContainer.innerHTML = '';

        // Group seats by row
        const seatsByRow = {};
        this.seats.forEach(seat => {
            if (!seatsByRow[seat.row]) {
                seatsByRow[seat.row] = [];
            }
            seatsByRow[seat.row].push(seat);
        });

        // Sort rows
        const rows = Object.keys(seatsByRow).sort();

        // Render each row
        rows.forEach(rowLetter => {
            const rowSeats = seatsByRow[rowLetter].sort((a, b) => a.number - b.number);
            const rowElement = this.createSeatRow(rowLetter, rowSeats);
            this.seatMapContainer.appendChild(rowElement);
        });
    }

    createSeatRow(rowLetter, seats) {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row';

        // Row label
        const label = document.createElement('div');
        label.className = 'seat-row-label';
        label.textContent = rowLetter;
        rowDiv.appendChild(label);

        // Seats
        seats.forEach(seat => {
            const seatElement = this.createSeat(seat);
            rowDiv.appendChild(seatElement);
        });

        return rowDiv;
    }

    createSeat(seat) {
        const seatDiv = document.createElement('div');
        seatDiv.className = 'seat';
        seatDiv.dataset.seatId = seat.id;
        seatDiv.dataset.row = seat.row;
        seatDiv.dataset.number = seat.number;
        seatDiv.dataset.seatTypeId = seat.seat_type_id || '';
        seatDiv.dataset.surcharge = seat.seat_type?.surcharge || 0;

        // Determine seat status
        const status = this.getSeatStatus(seat);
        seatDiv.classList.add(`seat-${status}`);

        // Seat label
        seatDiv.textContent = seat.label || `${seat.row}${seat.number}`;

        // Seat type indicators
        const seatTypeName = (seat.seat_type?.name || '').toLowerCase();

        if (seatTypeName.includes('vip')) {
            seatDiv.classList.add('seat-vip');
        }

        if (
            seatTypeName.includes('đôi') ||
            seatTypeName.includes('doi') ||
            seatTypeName.includes('couple') ||
            seatTypeName.includes('double')
        ) {
            seatDiv.classList.add('seat-couple');
        }

        // Click handler
        if (status === 'available' || status === 'selected' || status === 'holding') {
            seatDiv.addEventListener('click', () => this.handleSeatClick(seat));
            seatDiv.setAttribute('role', 'button');
            seatDiv.setAttribute('tabindex', '0');
            seatDiv.setAttribute('aria-label', `Ghế ${seat.row}${seat.number}, ${seat.seat_type?.name || 'Thường'}`);
        } else {
            seatDiv.setAttribute('aria-disabled', 'true');
        }

        return seatDiv;
    }

    getSeatStatus(seat) {
        const seatId = seat.id;

        // Check if seat is selected by current user
        if (this.selectedSeats.has(seatId)) {
            return 'selected';
        }

        // Check if seat is held by current user (from existing hold)
        if (this.currentHold && this.currentHold.seat_ids?.includes(seatId)) {
            return 'holding';
        }

        // Check seat status from API
        if (seat.status === 'booked') {
            return 'booked';
        }

        if (seat.status === 'locked' || seat.status === 'holding') {
            return 'locked';
        }

        return 'available';
    }

    async handleSeatClick(seat) {
        // Check authentication when user tries to select seats
        if (!this.auth?.isAuthenticated()) {
            if (window.authManager?.showAuthRequired) {
                window.authManager.showAuthRequired();
            } else {
                this.showToast('Vui lòng đăng nhập để chọn ghế', 'warning');
                setTimeout(() => {
                    this.auth?.showModal('login');
                }, 500);
            }
            return;
        }

        const seatId = seat.id;
        const status = this.getSeatStatus(seat);

        // Can't click booked or locked seats
        if (status === 'booked' || status === 'locked') {
            return;
        }

        // Toggle selection
        if (this.selectedSeats.has(seatId)) {
            this.selectedSeats.delete(seatId);
        } else {
            // Limit to 10 seats
            if (this.selectedSeats.size >= 10) {
                this.showToast('Bạn chỉ có thể chọn tối đa 10 ghế', 'warning');
                return;
            }
            this.selectedSeats.add(seatId);
        }

        // Re-render seat map
        this.renderSeatMap();

        // Update summary
        this.updateSummary();

        // Auto-lock seats after selection
        if (this.selectedSeats.size > 0) {
            await this.lockSeats();
        } else {
            // If no seats selected, unlock current hold
            if (this.currentHold) {
                await this.unlockSeats();
            }
        }
    }

    async lockSeats() {
        if (this.selectedSeats.size === 0) return;

        try {
            const response = await this.fetchAPI('/seats/lock', {
                method: 'POST',
                body: JSON.stringify({
                    showtime_id: this.config.showtimeId,
                    seat_ids: Array.from(this.selectedSeats)
                })
            });

            if (response.success) {
                this.currentHold = response.data;
                this.startTimer(response.data.expires_in_seconds || 600);
                this.showToast('Đã giữ ghế cho bạn trong 10 phút', 'success');
            } else {
                throw new Error(response.message || 'Không thể giữ ghế');
            }
        } catch (error) {
            console.error('Lock seats error:', error);
            this.showToast(error.message || 'Lỗi khi giữ ghế', 'danger');

            // Reset selection on error
            this.selectedSeats.clear();
            this.currentHold = null;
            this.renderSeatMap();
            this.updateSummary();
        }
    }

    async unlockSeats() {
        if (!this.currentHold) return;

        try {
            const response = await this.fetchAPI(
                `/seats/unlock/${this.currentHold.hold_id}`,
                { method: 'DELETE' }
            );

            if (response.success) {
                this.currentHold = null;
                this.stopTimer();
                this.showToast('Đã hủy giữ ghế', 'info');
            }
        } catch (error) {
            console.error('Unlock seats error:', error);
        }
    }

    async cancelSelection() {
        if (this.selectedSeats.size === 0) return;

        const confirmed = confirm('Bạn có chắc muốn hủy chọn ghế?');
        if (!confirmed) return;

        // Unlock seats
        await this.unlockSeats();

        // Clear selection
        this.selectedSeats.clear();

        // Re-render
        this.renderSeatMap();
        this.updateSummary();
    }

    updateSummary() {
        // Selected seats list
        if (this.selectedSeatsList) {
            if (this.selectedSeats.size === 0) {
                this.selectedSeatsList.innerHTML = '<p class="text-muted small mb-0">Chưa chọn ghế nào</p>';
            } else {
                const badges = Array.from(this.selectedSeats)
                    .map(seatId => {
                        const seat = this.seats.find(s => s.id === seatId);
                        if (!seat) return '';
                        const label = seat.label || `${seat.row}${seat.number}`;
                        return `<span class="selected-seat-badge">${label}</span>`;
                    })
                    .join('');
                this.selectedSeatsList.innerHTML = badges;
            }
        }

        // Calculate prices
        let totalSurcharge = 0;
        let quantity = this.selectedSeats.size;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat && seat.seat_type) {
                totalSurcharge += parseFloat(seat.seat_type.surcharge || 0);
            }
        });

        const seatTotal = (this.basePrice * quantity) + totalSurcharge;
        const productsTotal = this.calculateProductsTotal();
        const subtotal = seatTotal + productsTotal;
        const discount = this.calculateDiscount(subtotal);
        const total = Math.max(0, subtotal - discount);

        this.renderSelectedProducts();

        // Update UI
        if (this.seatQuantity) {
            this.seatQuantity.textContent = quantity;
        }

        if (this.seatSurcharge) {
            this.seatSurcharge.textContent = this.formatCurrency(totalSurcharge);
        }

        if (this.productTotal) {
            this.productTotal.textContent = this.formatCurrency(productsTotal);
        }

        if (this.discountAmount) {
            this.discountAmount.textContent = this.formatCurrency(discount);
        }

        if (this.totalPrice) {
            this.totalPrice.textContent = this.formatCurrency(total);
        }

        // Enable/disable buttons
        if (this.proceedBtn) {
            this.proceedBtn.disabled = this.selectedSeats.size === 0;
        }

        if (this.cancelBtn) {
            this.cancelBtn.disabled = this.selectedSeats.size === 0;
        }

        // Update sidebar summary
        this.updateSidebarSummary();

        // Update step buttons
        this.updateStepButtons();
    }

    startTimer(seconds) {
        this.stopTimer();
        this.timerSeconds = seconds;
        this.updateTimerDisplay();

        this.timer = setInterval(() => {
            this.timerSeconds--;
            this.updateTimerDisplay();

            if (this.timerSeconds <= 0) {
                this.handleTimerExpired();
            }
        }, 1000);
    }

    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    updateTimerDisplay() {
        if (!this.timerDisplay) return;

        const minutes = Math.floor(this.timerSeconds / 60);
        const seconds = this.timerSeconds % 60;
        this.timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        // Change color based on time remaining
        if (this.timerSeconds <= 60) {
            this.timerDisplay.classList.add('text-danger');
        } else if (this.timerSeconds <= 180) {
            this.timerDisplay.classList.add('text-warning');
            this.timerDisplay.classList.remove('text-danger');
        } else {
            this.timerDisplay.classList.remove('text-danger', 'text-warning');
        }
    }

    handleTimerExpired() {
        this.stopTimer();
        this.currentHold = null;
        this.selectedSeats.clear();
        this.renderSeatMap();
        this.updateSummary();
        this.showToast('Hết thời gian giữ ghế. Vui lòng chọn lại.', 'warning');
    }

    async proceedToPayment() {
        if (this.selectedSeats.size === 0) {
            this.showToast('Vui lòng chọn ghế trước', 'warning');
            return;
        }

        if (!this.currentHold) {
            this.showToast('Phiên giữ ghế đã hết hạn. Vui lòng chọn lại.', 'warning');
            this.selectedSeats.clear();
            this.renderSeatMap();
            this.updateSummary();
            return;
        }

        try {
            this.showLoading('Đang tạo đơn hàng...');

            // Build items array matching CreatePaymentRequest
            const items = [];

            // Add seats
            Array.from(this.selectedSeats).forEach(seatId => {
                items.push({
                    type: 'seat',
                    id: seatId,
                    quantity: 1
                });
            });

            // Add products
            const products = this.getSelectedProductsPayload();
            products.forEach(p => {
                items.push({
                    type: 'product',
                    id: p.id,
                    quantity: p.quantity
                });
            });

            // Create order and payment link
            const response = await this.fetchAPI('/payments', {
                method: 'POST',
                body: JSON.stringify({
                    showtime_id: this.config.showtimeId,
                    items: items,
                    voucher_code: this.appliedPromotion?.code || null,
                    points_used: 0 // Default to 0 for now
                })
            });

            if (response.success && response.data?.checkout_url) {
                this.showToast('Đang chuyển hướng đến cổng thanh toán...', 'success');

                // Store order code and subscribe to private WebSocket channel.
                // If user pays on mobile (QR scan), the desktop browser will receive
                // the OrderPaid event and auto-show the success screen.
                const orderCode = response.data?.gateway_order_code;
                if (orderCode) {
                    this.currentOrderCode = orderCode;
                    this.subscribeToOrderChannel(orderCode);
                }

                // Redirect directly to PayOS checkout
                setTimeout(() => {
                    window.location.href = response.data.checkout_url;
                }, 1000);
            } else {
                throw new Error(response.message || 'Không thể tạo đơn hàng');
            }
        } catch (error) {
            console.error('Create order error:', error);
            this.showToast(error.message || 'Lỗi khi tạo đơn hàng', 'danger');

            // Reload seats to get latest status
            await this.loadSeats();
        } finally {
            this.hideLoading();
        }
    }

    async validatePromotion() {
        const code = (this.promotionCodeInput?.value || '').trim();

        if (!code) {
            this.appliedPromotion = null;
            this.setPromotionMessage('Vui lòng nhập mã khuyến mãi.', 'text-warning');
            this.updateSummary();
            return;
        }

        try {
            this.applyPromotionBtn.disabled = true;
            this.applyPromotionBtn.textContent = 'Đang kiểm tra...';

            const subtotal = this.calculateSubtotal();
            const response = await this.fetchAPI(`/promotions/${encodeURIComponent(code)}/validate?order_total=${subtotal}`, {
                method: 'GET'
            });

            if (!response.success) {
                throw new Error(response.message || 'Mã khuyến mãi không hợp lệ.');
            }

            this.appliedPromotion = response.data;
            this.setPromotionMessage(response.message || 'Áp dụng mã khuyến mãi thành công.', 'text-success');
            this.updateSummary();
        } catch (error) {
            this.appliedPromotion = null;
            this.setPromotionMessage(error.message || 'Mã khuyến mãi không hợp lệ.', 'text-danger');
            this.updateSummary();
        } finally {
            this.applyPromotionBtn.disabled = false;
            this.applyPromotionBtn.textContent = 'Áp dụng';
        }
    }

    calculateSubtotal() {
        let totalSurcharge = 0;
        const quantity = this.selectedSeats.size;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat && seat.seat_type) {
                totalSurcharge += parseFloat(seat.seat_type.surcharge || 0);
            }
        });

        return (this.basePrice * quantity) + totalSurcharge + this.calculateProductsTotal();
    }

    calculateProductsTotal() {
        let total = 0;

        this.selectedProducts.forEach((quantity, productId) => {
            const product = this.products.find(item => item.id === productId);
            if (product) {
                total += parseFloat(product.price || 0) * quantity;
            }
        });

        return total;
    }

    calculateDiscount(subtotal) {
        if (!this.appliedPromotion) {
            return 0;
        }

        return Math.min(parseFloat(this.appliedPromotion.discount_amount || 0), subtotal);
    }

    getSelectedProductsPayload() {
        return Array.from(this.selectedProducts.entries()).map(([id, quantity]) => ({
            id,
            quantity
        }));
    }

    renderSelectedProducts() {
        if (!this.selectedProductsList) return;

        const selected = Array.from(this.selectedProducts.entries())
            .map(([productId, quantity]) => {
                const product = this.products.find(item => item.id === productId);
                if (!product) return '';

                return `
                    <div class="selected-product-row">
                        <span>${this.escapeHtml(product.name)} x${quantity}</span>
                        <span>${this.formatCurrency(parseFloat(product.price || 0) * quantity)}</span>
                    </div>
                `;
            })
            .join('');

        this.selectedProductsList.innerHTML = selected;
    }

    setPromotionMessage(message, className) {
        if (!this.promotionMessage) return;

        this.promotionMessage.textContent = message;
        this.promotionMessage.className = `form-text ${className || ''}`.trim();
    }

    escapeHtml(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, "\u0026amp;")
            .replace(/</g, "\u0026lt;")
            .replace(/>/g, "\u0026gt;")
            .replace(/"/g, "\u0026quot;")
            .replace(/'/g, "\u0026#039;");
    }

    // Utility Methods
    async fetchAPI(endpoint, options = {}) {
        // Get CSRF token for POST/PUT/DELETE requests
        const getCsrfToken = () => {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        };

        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        // Add CSRF token for state-changing requests
        const method = (options.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                defaultOptions.headers['X-CSRF-TOKEN'] = csrfToken;
            }
        }

        const response = await fetch(`${this.apiUrl}${endpoint}`, {
            ...defaultOptions,
            ...options,
            credentials: 'include',
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        });

        if (!response.ok) {
            if (response.status === 401) {
                this.showToast('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.', 'warning');
                setTimeout(() => {
                    window.location.href = '/';
                }, 2000);
                throw new Error('Unauthorized');
            }

            const error = await response.json();
            throw new Error(error.message || 'Request failed');
        }

        return response.json();
    }

    showLoading(message = 'Đang xử lý...') {
        if (this.loadingOverlay) {
            const text = this.loadingOverlay.querySelector('p');
            if (text) text.textContent = message;
            this.loadingOverlay.classList.remove('d-none');
        }
    }

    hideLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.add('d-none');
        }
    }

    showToast(message, type = 'info') {
        const toastEl = document.getElementById('bookingToast');
        if (!toastEl) return;

        const toastBody = toastEl.querySelector('.toast-body');
        const toastHeader = toastEl.querySelector('.toast-header');

        if (toastBody) {
            toastBody.textContent = message;
        }

        // Update icon based on type
        const icon = toastHeader?.querySelector('i');
        if (icon) {
            icon.className = '';
            icon.classList.add('bi', 'me-2');

            switch(type) {
                case 'success':
                    icon.classList.add('bi-check-circle');
                    break;
                case 'danger':
                case 'error':
                    icon.classList.add('bi-exclamation-circle');
                    break;
                case 'warning':
                    icon.classList.add('bi-exclamation-triangle');
                    break;
                default:
                    icon.classList.add('bi-info-circle');
            }
        }

        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 3000
        });
        toast.show();
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.bookingManager = new BookingManager();
});
