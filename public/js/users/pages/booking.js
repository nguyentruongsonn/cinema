 /**
 * Booking Page JavaScript
 * Handles seat selection, locking, timer, and order creation
 */

class BookingManager {
    constructor() {
        this.config = window.BOOKING_CONFIG || {};
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        this.auth = window.authManager; // From auth.js

        // State
        this.seats = [];
        this.selectedSeats = new Set();
        this.currentHold = null;
        this.isLockingSeats = false;
        this.timer = null;
        this.timerSeconds = 600; // 10 minutes
        this.basePrice = parseFloat(this.config.basePrice) || 0;
        this.products = [];
        this.selectedProducts = new Map();
        this.appliedPromotion = null;
        this.registeredPromotions = [];
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
        this.voucherContent = document.querySelector('.voucher-content');
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

        this.renderRegisteredPromotions();

        // Setup event listeners
        this.setupEventListeners();

        // Load page data (seats should always load, auth check happens on seat click)
        await Promise.all([
            this.loadSeats(),
            this.loadProducts(),
            this.loadRegisteredPromotions()
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
        if (typeof window.Echo === 'undefined' || !window.Echo || typeof window.Echo.channel !== 'function') {
            return;
        }

        const showtimeId = this.config.showtimeId; // Use unencrypted ID for WebSocket channel
        if (!showtimeId) return;

        // 1. Real-time seat status (public – no auth needed)
        try {
            const showtimeChannel = window.Echo.channel(`showtime.${showtimeId}`);

            if (!showtimeChannel || typeof showtimeChannel.listen !== 'function') {
                console.warn('[Booking] Realtime showtime channel unavailable; continuing without realtime seat updates.');
                return;
            }

            showtimeChannel.listen('.seat.status.updated', (event) => {
                this.applyRealtimeSeatStatus(event.seat_id, event.status, event.user_id);
            });
        } catch (error) {
            console.warn('[Booking] Realtime subscription failed; continuing without realtime seat updates.', error);
            return;
        }

        // 2. Real-time payment result (private – requires auth)
        //    Subscribe only when user has initiated a payment (orderCode present).
        //    The orderCode is stored on `this.currentOrderCode` after fetchAPI payment.
        if (this.currentOrderCode) {
            this.subscribeToOrderChannel(this.currentOrderCode);
        }
    }

    subscribeToOrderChannel(orderCode) {
        if (!window.Echo || !orderCode) return;

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
    applyRealtimeSeatStatus(seatId, status, eventUserId = null) {
        const numericSeatId = parseInt(seatId, 10);
        const numericEventUserId = eventUserId === null || eventUserId === undefined ? null : parseInt(eventUserId, 10);
        const currentUserId = this.auth?.getUser?.()?.id ? parseInt(this.auth.getUser().id, 10) : null;
        const isOwnEvent = numericEventUserId !== null && currentUserId !== null && numericEventUserId === currentUserId;

        // A lock event for a seat currently selected in this browser must not remove
        // the user's selection. Some broadcasts may not include user_id, so selectedSeats
        // is the most reliable client-side ownership signal.
        if (status === 'locked' && (isOwnEvent || this.selectedSeats.has(numericSeatId))) {
            const ownSeat = this.seats.find(s => s.id === numericSeatId);
            if (ownSeat) {
                ownSeat.status = 'holding';
                ownSeat.is_locked = true;
                ownSeat.is_available = false;
            }

            const ownSeatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${numericSeatId}"]`);
            if (ownSeatEl) {
                ownSeatEl.classList.remove('seat-available', 'seat-locked', 'seat-booked', 'seat-holding');
                ownSeatEl.classList.add('seat-selected');
            }

            return;
        }

        // Update in-memory seat data
        const seat = this.seats.find(s => s.id === numericSeatId);
        if (seat) {
            seat.status      = status;
            seat.is_locked    = status === 'locked';
            seat.is_available = status === 'available';
        }

        // Update DOM element directly
        const seatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${numericSeatId}"]`);
        if (!seatEl) return;

        // Remove all status classes
        seatEl.classList.remove('seat-available', 'seat-locked', 'seat-booked', 'seat-holding', 'seat-selected');

        if (status === 'available') {
            // Only restore to available if this seat is not selected by current user
            if (!this.selectedSeats.has(numericSeatId)) {
                seatEl.classList.add('seat-available');
                seatEl.setAttribute('role', 'button');
                seatEl.setAttribute('tabindex', '0');
                seatEl.removeAttribute('aria-disabled');
                // Re-attach click handler by re-rendering (safe since seat data updated)
                const freshSeat = this.seats.find(s => s.id === numericSeatId);
                if (freshSeat) {
                    seatEl.onclick = () => this.handleSeatClick(freshSeat);
                }
            }
        } else {
            // Locked by someone else – remove from selection if user had it
            if (this.selectedSeats.has(numericSeatId)) {
                this.selectedSeats.delete(numericSeatId);
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
        const bookingPageEl = document.querySelector('.booking-page');
        if (bookingPageEl) bookingPageEl.style.display = 'none';

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
        const bookingPageEl = document.querySelector('.booking-page');
        if (bookingPageEl) bookingPageEl.style.display = 'none';

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

        // Voucher: input button only registers/saves voucher; discount is applied only
        // when the user explicitly clicks "Áp dụng" on a voucher in voucher-content.
        this.applyPromotionBtn?.addEventListener('click', () => this.registerPromotionFromInput());
        this.promotionCodeInput?.addEventListener('input', () => {
            this.appliedPromotion = null;
            this.setPromotionMessage('Nhập mã để đăng ký voucher. Mã chỉ giảm giá sau khi bạn bấm Áp dụng trong danh sách voucher.', 'text-muted');
            this.updateSummary();
            this.renderRegisteredPromotions();
        });

        this.voucherContent?.addEventListener('click', (event) => {
            const applyBtn = event.target.closest('[data-voucher-action="apply"]');
            const cancelBtn = event.target.closest('[data-voucher-action="cancel"]');

            if (applyBtn) {
                this.validatePromotion(applyBtn.dataset.code, {
                    syncInput: true,
                    showMessage: true,
                    registerBeforeValidate: false
                });
            }

            if (cancelBtn) {
                this.cancelPromotion();
            }
        });
        this.promotionCodeInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.registerPromotionFromInput();
            }
        });

        // Handle page unload (unlock seats)
        window.addEventListener('beforeunload', () => {
            const holdId = this.getCurrentHoldId();
            if (!holdId) return;

            // sendBeacon only sends POST reliably, so use the legacy _method override
            // route if middleware supports it. Normal user cancellation still uses DELETE.
            navigator.sendBeacon(
                `${this.apiUrl}/seats/unlock/${holdId}`,
                JSON.stringify({ _method: 'DELETE' })
            );
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

    async goToNextStep() {
        if (!this.validateCurrentStep()) {
            return;
        }

        if (this.currentStep === 1) {
            const locked = await this.ensureSeatsHeldBeforeContinue();
            if (!locked) {
                return;
            }
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
        const isLockingFirstStep = this.currentStep === 1 && this.isLockingSeats;
        const nextLabel = isLockingFirstStep ? 'Đang giữ ghế...' : 'Tiếp tục';
        const sidebarLabel = isLockingFirstStep
            ? 'Đang giữ ghế...'
            : (this.currentStep < 4 ? 'Tiếp tục' : 'Thanh toán');

        if (this.nextStepBtn) {
            this.nextStepBtn.disabled = !canProceed || isLockingFirstStep;
            this.nextStepBtn.style.display = this.currentStep < 4 ? 'block' : 'none';
            this.nextStepBtn.textContent = nextLabel;
            this.nextStepBtn.classList.toggle('is-loading', isLockingFirstStep);
        }

        if (this.sidebarContinueBtn) {
            this.sidebarContinueBtn.disabled = !canProceed || isLockingFirstStep;
            this.sidebarContinueBtn.textContent = sidebarLabel;
            this.sidebarContinueBtn.classList.toggle('is-loading', isLockingFirstStep);
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
                        const price = seat.price || 0;  // Use dynamic price from API
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

        // Calculate prices using dynamic prices from API
        let seatTotal = 0;
        let quantity = this.selectedSeats.size;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat) {
                seatTotal += seat.price || 0;  // Use dynamic price from API response
            }
        });
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
                `/seats/showtime/${this.config.encryptedShowtimeId}`
            );

            if (response.success) {
                this.seats = response.data.seats || [];
                this.currentHold = this.normalizeHold(response.data.current_user_holds?.[0] || null);

                // If user has existing hold, restore selection
                if (this.currentHold && this.currentHold.seat_ids) {
                    this.currentHold.seat_ids.forEach(id => {
                        this.selectedSeats.add(parseInt(id, 10));
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

        this.seatMapSkeleton?.classList.add('d-none');
        this.seatMapContainer.classList.remove('d-none');
        this.seatMapContainer.classList.add('seat-grid');
        this.seatMapContainer.innerHTML = '';

        const dimensions = this.getSeatGridDimensions();
        this.seatMapContainer.style.setProperty('--cols', dimensions.cols);
        this.renderSeatColumnLabels(dimensions.cols);

        const seatMap = this.buildSeatPositionMap();

        for (let rowIndex = 0; rowIndex < dimensions.rows; rowIndex++) {
            const rowLabel = document.createElement('div');
            rowLabel.className = 'seat-row-label';
            rowLabel.textContent = this.getSeatRowLabel(rowIndex);
            this.seatMapContainer.appendChild(rowLabel);

            let colIndex = 0;
            while (colIndex < dimensions.cols) {
                const seat = seatMap[rowIndex]?.[colIndex];

                if (!seat) {
                    this.seatMapContainer.appendChild(this.createEmptySeat());
                    colIndex++;
                    continue;
                }

                this.seatMapContainer.appendChild(this.createSeat(seat));
                colIndex += this.isCoupleSeat(seat) ? 2 : 1;
            }
        }
    }

    getSeatGridDimensions() {
        if (!this.seats.length) {
            return { rows: 0, cols: 0 };
        }

        return {
            rows: Math.max(...this.seats.map(seat => this.getSeatRowIndex(seat))) + 1,
            cols: Math.max(...this.seats.map(seat => this.getSeatColumnIndex(seat))) + 1,
        };
    }

    buildSeatPositionMap() {
        return this.seats.reduce((map, seat) => {
            const rowIndex = this.getSeatRowIndex(seat);
            const columnIndex = this.getSeatColumnIndex(seat);

            if (!map[rowIndex]) {
                map[rowIndex] = {};
            }

            map[rowIndex][columnIndex] = seat;
            return map;
        }, {});
    }

    getSeatRowIndex(seat) {
        if (Number.isInteger(seat.row_index)) return seat.row_index;
        if (Number.isInteger(seat.rowIndex)) return seat.rowIndex;

        const row = String(seat.row || '').trim().toUpperCase();
        return row ? row.charCodeAt(0) - 65 : 0;
    }

    getSeatColumnIndex(seat) {
        if (Number.isInteger(seat.column_index)) return seat.column_index;
        if (Number.isInteger(seat.columnIndex)) return seat.columnIndex;

        return Math.max(0, (parseInt(seat.number, 10) || 1) - 1);
    }

    getSeatRowLabel(rowIndex) {
        return String.fromCharCode(65 + rowIndex);
    }

    renderSeatColumnLabels(cols) {
        const parent = this.seatMapContainer?.parentElement;
        if (!parent) return;

        let labels = parent.querySelector('.seat-grid-col-labels');
        if (!labels) {
            labels = document.createElement('div');
            labels.className = 'seat-grid-col-labels mx-auto mt-2';
            this.seatMapContainer.insertAdjacentElement('afterend', labels);
        }

        labels.classList.remove('d-none');
        labels.style.setProperty('--cols', cols);
        labels.innerHTML = '<div></div>';

        for (let col = 1; col <= cols; col++) {
            const label = document.createElement('div');
            label.className = 'seat-col-label';
            label.textContent = col;
            labels.appendChild(label);
        }
    }

    createEmptySeat() {
        const empty = document.createElement('div');
        empty.className = 'seat admin-seat seat-empty';
        empty.setAttribute('aria-hidden', 'true');
        return empty;
    }

    isCoupleSeat(seat) {
        const seatTypeName = (seat.seat_type?.name || '').toLowerCase();

        return seatTypeName.includes('đôi')
            || seatTypeName.includes('doi')
            || seatTypeName.includes('couple')
            || seatTypeName.includes('double')
            || seatTypeName.includes('sweetbox');
    }

    createSeat(seat) {
        const seatDiv = document.createElement('div');
        seatDiv.className = 'seat admin-seat seat-standard';
        seatDiv.dataset.seatId = seat.id;
        seatDiv.dataset.row = seat.row;
        seatDiv.dataset.number = seat.number;
        seatDiv.dataset.seatTypeId = seat.seat_type_id || '';
        seatDiv.dataset.surcharge = seat.seat_type?.surcharge || 0;

        const status = this.getSeatStatus(seat);
        seatDiv.classList.add(`seat-${status}`);

        const seatTypeName = (seat.seat_type?.name || '').toLowerCase();
        const isVip = seatTypeName.includes('vip') || seatTypeName.includes('premium');
        const isCouple = this.isCoupleSeat(seat);

        if (isVip) {
            seatDiv.classList.remove('seat-standard');
            seatDiv.classList.add('seat-vip');
        }

        if (isCouple) {
            seatDiv.classList.remove('seat-standard');
            seatDiv.classList.add('seat-couple', 'seat-couple-span');
        }

        const label = seat.label || `${seat.row}${seat.number}`;
        const icon = isCouple
            ? '<svg width="2em" height="1em" viewBox="0 0 48 24" fill="currentColor" class="seat-icon-shape"><rect x="5" y="2" width="38" height="11" rx="2"/><rect x="4" y="14" width="40" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="43" y="11" width="3" height="8" rx="1"/></svg>'
            : '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" class="seat-icon-shape"><rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>';

        seatDiv.innerHTML = `${icon}<span class="seat-label">${label}</span>`;

        if (this.isLockingSeats && this.selectedSeats.has(seat.id)) {
            seatDiv.classList.add('seat-pending-hold');
            seatDiv.setAttribute('aria-busy', 'true');
        }

        if (!this.isLockingSeats && (status === 'available' || status === 'selected' || status === 'holding')) {
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
    }

    async ensureSeatsHeldBeforeContinue() {
        if (this.selectedSeats.size === 0) {
            this.showToast('Vui lòng chọn ghế trước', 'warning');
            return false;
        }

        if (this.isCurrentHoldForSelectedSeats()) {
            return true;
        }

        if (this.lockPromise) {
            return false;
        }

        this.isLockingSeats = true;
        this.updateStepButtons();
        this.renderSeatMap();
        this.lockPromise = this.lockSeats();

        try {
            await this.lockPromise;
            return !!this.currentHold;
        } catch (error) {
            return false;
        } finally {
            this.lockPromise = null;
            this.isLockingSeats = false;
            this.updateStepButtons();
            this.renderSeatMap();
        }
    }

    isCurrentHoldForSelectedSeats() {
        if (!this.currentHold?.seat_ids || this.selectedSeats.size === 0) {
            return false;
        }

        const heldSeatIds = this.currentHold.seat_ids.map(id => parseInt(id, 10)).sort((a, b) => a - b);
        const selectedSeatIds = Array.from(this.selectedSeats).map(id => parseInt(id, 10)).sort((a, b) => a - b);

        return heldSeatIds.length === selectedSeatIds.length
            && heldSeatIds.every((id, index) => id === selectedSeatIds[index]);
    }

    async lockSeats() {
        if (this.selectedSeats.size === 0) return false;

        try {
            const response = await this.fetchAPI('/seats/lock', {
                method: 'POST',
                body: JSON.stringify({
                    showtime_id: this.config.showtimeId,
                    seat_ids: Array.from(this.selectedSeats)
                })
            });

            if (response.success) {
                this.currentHold = this.normalizeHold(response.data, Array.from(this.selectedSeats));

                this.currentHold.seat_ids.forEach(id => this.selectedSeats.add(id));
                this.startTimer(response.data.expires_in_seconds || 600);
                this.renderSeatMap();
                this.updateSummary();
                this.showToast('Đã giữ ghế cho bạn trong 10 phút', 'success');
                return true;
            } else {
                throw new Error(response.message || 'Không thể giữ ghế');
            }
        } catch (error) {
            console.error('Lock seats error:', error);

            if (this.handleSeatLockConflict(error)) {
                return false;
            }

            this.showToast(error.message || 'Lỗi khi giữ ghế', 'danger');

            // Reset selection on non-conflict errors because hold state is no longer reliable.
            this.selectedSeats.clear();
            this.currentHold = null;
            this.renderSeatMap();
            this.updateSummary();

            return false;
        }
    }

    handleSeatLockConflict(error) {
        const conflictedSeats = error?.data?.data?.conflicted_seats || error?.data?.conflicted_seats || [];

        if (!Array.isArray(conflictedSeats) || conflictedSeats.length === 0) {
            return false;
        }

        const conflictedIds = conflictedSeats
            .map(seat => parseInt(seat.id, 10))
            .filter(Number.isFinite);

        conflictedIds.forEach(seatId => {
            this.selectedSeats.delete(seatId);

            const localSeat = this.seats.find(seat => parseInt(seat.id, 10) === seatId);
            if (localSeat) {
                localSeat.status = 'locked';
                localSeat.is_locked = true;
                localSeat.is_available = false;
                localSeat.is_holding = false;
            }
        });

        this.currentHold = null;
        this.renderSeatMap();
        this.updateSummary();

        const labels = conflictedSeats
            .map(seat => seat.label)
            .filter(Boolean)
            .join(', ');

        this.showToast(
            labels
                ? `Ghế ${labels} vừa được người khác giữ. Vui lòng chọn ghế khác.`
                : (error.message || 'Một số ghế vừa được người khác giữ. Vui lòng chọn ghế khác.'),
            'warning'
        );

        return true;
    }

    async unlockSeats() {
        const holdId = this.getCurrentHoldId();
        if (!holdId) return;

        try {
            const response = await this.fetchAPI(
                `/seats/unlock/${holdId}`,
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

        // Calculate prices using dynamic prices from API
        let seatTotal = 0;
        let quantity = this.selectedSeats.size;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat) {
                seatTotal += seat.price || 0;  // Use dynamic price from API response
            }
        });
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
        if (this.lockPromise) {
            this.showLoading('Đang kiểm tra ghế...');
            try {
                await this.lockPromise;
            } catch (e) {}
            this.hideLoading();
        }

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

    fillPromotionCode(code) {
        if (!code || !this.promotionCodeInput) return;

        this.promotionCodeInput.value = code;
        this.appliedPromotion = null;
        this.setPromotionMessage('Đã chọn mã. Bấm Áp dụng trong danh sách voucher để giảm giá.', 'text-muted');
        this.updateSummary();
        this.renderRegisteredPromotions();
    }

    cancelPromotion() {
        const cancelledCode = this.appliedPromotion?.code || this.promotionCodeInput?.value || '';

        this.appliedPromotion = null;

        if (this.promotionCodeInput) {
            this.promotionCodeInput.value = '';
        }

        this.setPromotionMessage(
            cancelledCode ? `Đã hủy áp dụng mã ${String(cancelledCode).toUpperCase()}.` : 'Đã hủy mã giảm giá.',
            'text-muted'
        );
        this.updateSummary();
        this.renderRegisteredPromotions();
    }

    async loadRegisteredPromotions() {
        if (!this.auth?.isAuthenticated?.()) {
            this.registeredPromotions = [];
            this.renderRegisteredPromotions();
            return;
        }

        try {
            const response = await this.fetchAPI('/promotions/registered', {
                method: 'GET'
            });

            if (!response.success) {
                throw new Error(response.message || 'Không thể tải Kho Voucher.');
            }

            this.registeredPromotions = this.normalizeRegisteredPromotions(response.data);
            this.renderRegisteredPromotions();
        } catch (error) {
            console.warn('[Booking] Cannot load registered vouchers from user_promotion.', error);
            this.registeredPromotions = [];
            this.renderRegisteredPromotions();
        }
    }

    normalizeRegisteredPromotions(promotions) {
        if (!Array.isArray(promotions)) {
            return [];
        }

        const uniquePromotions = new Map();

        promotions
            .filter(item => item?.code && this.isVoucherUsable(item))
            .forEach(item => {
                const normalizedCode = String(item.code).trim().toUpperCase();
                if (!normalizedCode) return;

                uniquePromotions.set(normalizedCode, {
                    ...item,
                    code: normalizedCode
                });
            });

        return Array.from(uniquePromotions.values());
    }

    isVoucherUsable(promotion) {
        if (!promotion) return false;

        const status = promotion.status ?? promotion.pivot?.status;
        if (status !== undefined && status !== null && Number(status) !== 1) {
            return false;
        }

        const usedAt = promotion.used_at ?? promotion.pivot?.used_at;
        if (usedAt) {
            return false;
        }

        const userUsageCount = Number(promotion.usage_count ?? promotion.pivot?.usage_count ?? 0);
        if (userUsageCount > 0) {
            return false;
        }

        const endDate = promotion.end_date ? new Date(promotion.end_date) : null;
        if (endDate && !Number.isNaN(endDate.getTime()) && endDate < new Date()) {
            return false;
        }

        const usageLimit = promotion.usage_limit ?? null;
        const totalUsageCount = Number(promotion.total_usage_count ?? promotion.promotion_usage_count ?? 0);
        if (usageLimit !== null && usageLimit !== undefined && Number(usageLimit) > 0 && totalUsageCount >= Number(usageLimit)) {
            return false;
        }

        return true;
    }

    async registerPromotion(code) {
        if (!code) return null;

        if (!this.auth?.isAuthenticated?.()) {
            this.showToast('Vui lòng đăng nhập để đăng ký voucher.', 'warning');
            return null;
        }

        const response = await this.fetchAPI('/promotions/register', {
            method: 'POST',
            body: JSON.stringify({
                code
            })
        });

        if (!response.success) {
            throw new Error(response.message || 'Không thể đăng ký voucher.');
        }

        const promotion = response.data;
        if (promotion?.code) {
            const normalizedCode = String(promotion.code).trim().toUpperCase();
            this.registeredPromotions = [
                {
                    ...promotion,
                    code: normalizedCode
                },
                ...this.registeredPromotions.filter(item => String(item.code).toUpperCase() !== normalizedCode)
            ];
            this.renderRegisteredPromotions();
        }

        return promotion;
    }

    renderRegisteredPromotions() {
        if (!this.voucherContent) return;

        const selectedCode = (this.promotionCodeInput?.value || '').trim().toUpperCase();
        const appliedCode = this.appliedPromotion?.code ? String(this.appliedPromotion.code).toUpperCase() : '';
        const promotions = this.registeredPromotions.filter(item => this.isVoucherUsable(item));

        if (promotions.length === 0) {
            this.voucherContent.innerHTML = `
                <div class="empty-voucher">
                    <p class="text-muted mt-2">Chưa có voucher đã lưu. Nhập mã ở trên để đăng ký voucher.</p>
                    <small class="voucher-hint">Gợi ý: Sau khi đăng ký, voucher sẽ xuất hiện tại đây. Chỉ voucher được bấm “Áp dụng” mới làm giảm tổng tiền.</small>
                </div>
            `;
            return;
        }

        const suggestion = this.renderVoucherSuggestion(promotions, appliedCode);
        this.voucherContent.innerHTML = `${suggestion}${this.renderVoucherItems(promotions, selectedCode, appliedCode)}`;
    }

    renderVoucherSuggestion(promotions, appliedCode) {
        if (appliedCode) {
            return '<div class="voucher-suggestion">Voucher đang áp dụng sẽ được trừ ở phần tổng tiền. Bạn có thể bấm Hủy để chọn mã khác.</div>';
        }

        const subtotal = this.calculateSubtotal();
        const bestPromotion = promotions
            .map(promotion => ({
                promotion,
                discount: this.estimatePromotionDiscount(promotion, subtotal)
            }))
            .filter(item => item.discount > 0)
            .sort((a, b) => b.discount - a.discount)[0];

        if (bestPromotion) {
            return `<div class="voucher-suggestion">Gợi ý: Mã <strong>${this.escapeHtml(bestPromotion.promotion.code)}</strong> có thể giảm khoảng ${this.formatCurrency(bestPromotion.discount)} cho đơn hiện tại. Bấm Áp dụng để dùng mã.</div>`;
        }

        return '<div class="voucher-suggestion">Chọn “Áp dụng” trên voucher phù hợp để hệ thống kiểm tra điều kiện và giảm giá.</div>';
    }

    estimatePromotionDiscount(promotion, subtotal) {
        const minOrderValue = Number(promotion.min_order_value || 0);
        if (minOrderValue > 0 && subtotal < minOrderValue) {
            return 0;
        }

        const discountType = String(promotion.discount_type || '').toLowerCase();
        let discount = 0;

        if (['percent', 'percentage'].includes(discountType)) {
            discount = subtotal * (Number(promotion.discount_value || 0) / 100);
            const maxDiscount = Number(promotion.max_discount_amount || 0);
            if (maxDiscount > 0) {
                discount = Math.min(discount, maxDiscount);
            }
        } else {
            discount = Number(promotion.discount_value || promotion.discount_amount || 0);
        }

        return Math.max(0, Math.min(discount, subtotal));
    }

    renderVoucherItems(promotions, selectedCode, appliedCode) {
        return promotions.map(promotion => {
            const code = String(promotion.code || '').toUpperCase();
            const isSelected = selectedCode === code;
            const isApplied = appliedCode === code;
            const isPercentDiscount = ['percent', 'percentage'].includes(String(promotion.discount_type || '').toLowerCase());
            const discountText = isPercentDiscount
                ? `Giảm ${promotion.discount_value}%${promotion.max_discount_amount ? `, tối đa ${this.formatCurrency(promotion.max_discount_amount)}` : ''}`
                : `Giảm ${this.formatCurrency(promotion.discount_value || promotion.discount_amount || 0)}`;
            const minOrderText = promotion.min_order_value > 0
                ? `Đơn tối thiểu ${this.formatCurrency(promotion.min_order_value)}`
                : 'Không yêu cầu giá trị tối thiểu';
            const descriptionText = promotion.description || promotion.name || 'Voucher đã đăng ký trong Kho Voucher của bạn.';

            return `
                <div class="voucher-item ${isApplied ? 'is-applied' : ''}">
                    <div class="voucher-item-main">
                        <div class="voucher-code-wrap">
                            <span class="voucher-code">${this.escapeHtml(code)}</span>
                            ${isApplied ? '<span class="voucher-status">Đang áp dụng</span>' : (isSelected ? '<span class="voucher-status is-pending">Đã chọn, chưa giảm</span>' : '<span class="voucher-status is-pending">Đã đăng ký</span>')}
                        </div>
                        <div class="voucher-discount">${this.escapeHtml(discountText)}</div>
                        <div class="voucher-condition">${this.escapeHtml(minOrderText)}</div>
                        <small class="voucher-description">${this.escapeHtml(descriptionText)}</small>
                    </div>
                    <button type="button"
                            class="voucher-action-btn ${isApplied ? 'is-cancel' : ''}"
                            data-voucher-action="${isApplied ? 'cancel' : 'apply'}"
                            data-code="${this.escapeHtml(code)}">
                        ${isApplied ? 'Hủy' : 'Áp dụng'}
                    </button>
                </div>
            `;
        }).join('');
    }

    async registerPromotionFromInput() {
        const code = (this.promotionCodeInput?.value || '').trim();

        if (!code) {
            this.setPromotionMessage('Vui lòng nhập mã giảm giá để đăng ký voucher.', 'text-warning');
            return;
        }

        try {
            if (this.applyPromotionBtn) {
                this.applyPromotionBtn.disabled = true;
                this.applyPromotionBtn.textContent = 'Đang đăng ký...';
            }

            const promotion = await this.registerPromotion(code);

            if (!promotion) {
                throw new Error('Không thể đăng ký voucher.');
            }

            this.appliedPromotion = null;
            this.setPromotionMessage('Đã đăng ký voucher thành công. Bấm Áp dụng trong danh sách voucher để giảm giá.', 'text-success');
            this.updateSummary();
            this.renderRegisteredPromotions();
        } catch (error) {
            this.appliedPromotion = null;
            this.setPromotionMessage(error.message || 'Không thể đăng ký voucher.', 'text-danger');
            this.updateSummary();
            this.renderRegisteredPromotions();
        } finally {
            if (this.applyPromotionBtn) {
                this.applyPromotionBtn.disabled = false;
                this.applyPromotionBtn.textContent = 'Đăng ký';
            }
        }
    }

    async validatePromotion(codeOverride = null, options = {}) {
        const {
            syncInput = true,
            showMessage = true,
            registerBeforeValidate = true
        } = options;
        const code = (codeOverride || this.promotionCodeInput?.value || '').trim();

        if (!code) {
            this.appliedPromotion = null;
            if (showMessage) {
                this.setPromotionMessage('Vui lòng chọn voucher để áp dụng.', 'text-warning');
            }
            this.updateSummary();
            return;
        }

        try {
            if (syncInput && this.applyPromotionBtn) {
                this.applyPromotionBtn.disabled = true;
                this.applyPromotionBtn.textContent = 'Đang kiểm tra...';
            }

            if (registerBeforeValidate) {
                const registeredPromotion = await this.registerPromotion(code);
                if (!registeredPromotion) {
                    throw new Error('Không thể đăng ký voucher.');
                }
            }

            const subtotal = this.calculateSubtotal();
            const response = await this.fetchAPI(`/promotions/${encodeURIComponent(code)}/validate?order_total=${subtotal}`, {
                method: 'GET'
            });

            if (!response.success) {
                throw new Error(response.message || 'Mã giảm giá không hợp lệ.');
            }

            this.appliedPromotion = response.data;

            if (syncInput && this.promotionCodeInput) {
                this.promotionCodeInput.value = code;
            }

            if (showMessage) {
                this.setPromotionMessage(response.message || 'Áp dụng mã giảm giá thành công.', 'text-success');
            }

            this.updateSummary();
            this.renderRegisteredPromotions();
        } catch (error) {
            this.appliedPromotion = null;
            if (showMessage) {
                this.setPromotionMessage(error.message || 'Mã giảm giá không hợp lệ.', 'text-danger');
            }
            this.updateSummary();
            await this.loadRegisteredPromotions();
        } finally {
            if (syncInput && this.applyPromotionBtn) {
                this.applyPromotionBtn.disabled = false;
                this.applyPromotionBtn.textContent = 'Đăng ký';
            }
        }
    }

    calculateSubtotal() {
        let seatsTotal = 0;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat && seat.price) {
                seatsTotal += parseFloat(seat.price);
            }
        });

        return seatsTotal + this.calculateProductsTotal();
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

    normalizeHold(hold, fallbackSeatIds = []) {
        if (!hold) return null;

        const normalized = {
            ...hold,
            hold_id: hold.hold_id ?? hold.id ?? null,
            id: hold.id ?? hold.hold_id ?? null,
            seat_ids: Array.isArray(hold.seat_ids)
                ? hold.seat_ids.map(id => parseInt(id, 10)).filter(Number.isFinite)
                : fallbackSeatIds.map(id => parseInt(id, 10)).filter(Number.isFinite)
        };

        return normalized.hold_id ? normalized : null;
    }

    getCurrentHoldId() {
        return this.currentHold?.hold_id || this.currentHold?.id || null;
    }

    // Utility Methods
    async fetchAPI(endpoint, options = {}) {
        if (!window.apiClient) {
            throw new Error('API client is not initialized.');
        }

        const method = String(options.method || 'GET').toUpperCase();
        const body = options.body ? JSON.parse(options.body) : null;
        const requestOptions = { ...options };

        delete requestOptions.body;

        const client = window.authManager && typeof window.authManager.fetchAPI === 'function'
            ? window.authManager
            : window.apiClient;

        if (client === window.authManager) {
            return client.fetchAPI(endpoint, {
                ...requestOptions,
                method,
                ...(body !== null ? { body } : {})
            });
        }

        if (method === 'GET') {
            return window.apiClient.get(endpoint, requestOptions);
        }

        if (method === 'POST') {
            return window.apiClient.post(endpoint, body, requestOptions);
        }

        if (method === 'PUT') {
            return window.apiClient.put(endpoint, body, requestOptions);
        }

        if (method === 'PATCH') {
            return window.apiClient.patch(endpoint, body, requestOptions);
        }

        if (method === 'DELETE') {
            return window.apiClient.delete(endpoint, requestOptions);
        }

        return window.apiClient.request(endpoint, options);
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

        // Set data-type attribute so CSS can apply type-specific styling
        toastEl.setAttribute('data-type', type);

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
