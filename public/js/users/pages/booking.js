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
        this.appliedPoints = 0;
        this.checkoutIntent = null;
        this.availablePoints = 0;
        this.registeredPromotions = [];
        this.currentStep = 1; // Track current step (1-5)
        this.steps = ['seats', 'food', 'promotion', 'confirm', 'success'];
        this.isCreatingPayment = false;
        this.lockPromise = null;
        this.checkoutCompleted = false;

        // DOM Elements
        this.seatMapContainer = document.getElementById('seatMap');
        this.seatMapSkeleton = document.getElementById('seatMapSkeleton');
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
        this.voucherContent = document.getElementById('voucherContent');
        this.availableVouchersSection = document.getElementById('availableVouchersSection');
        this.discountAmount = document.getElementById('discountAmount');
        this.proceedBtn = document.getElementById('proceedToPaymentBtn');
        this.cancelBtn = document.getElementById('cancelSelectionBtn');
        this.nextStepBtn = document.getElementById('nextStepBtn');
        this.prevStepBtn = document.getElementById('prevStepBtn');
        this.paymentBtn = document.getElementById('paymentBtn');
        this.sidebarContinueBtn = document.getElementById('sidebarContinueBtn');
        this.timerDisplay = document.getElementById('bookingTimer');
        
        // Loyalty points elements
        this.availablePointsDisplay = document.getElementById('availablePointsDisplay');
        this.pointsInput = document.getElementById('pointsInput');
        this.exchangePointsBtn = document.getElementById('exchangePointsBtn');
        this.loyaltyDiscountDisplay = document.getElementById('loyaltyDiscountDisplay');

        // Progress bar elements
        this.progressSteps = document.querySelectorAll('.progress-step');
        this.loadingOverlay = document.getElementById('loadingOverlay');

        // Mobile bottom sheet (Phase 1A Part 2)
        this.bottomSheet = null;

        this.init();
    }

    async init() {
        if (this.seatMapSkeleton) {
            this.seatMapSkeleton.innerHTML = this.createSeatMapSkeleton();
            this.seatMapSkeleton.classList.remove('d-none');
        }

        // Check URL parameters for payment status
        const shouldContinue = this.checkUrlParams();
        if (!shouldContinue) return;

        this.renderRegisteredPromotions();

        // Setup event listeners
        this.setupEventListeners();

        // Load page data (seats should always load, auth check happens on seat click)        // Load dynamic data in parallel
        await Promise.all([
            this.loadSeats(),
            this.loadProducts(),
            this.loadRegisteredPromotions(),
            this.loadUserPoints()
        ]);

        // Subscribe to real-time WebSocket channels
        this.subscribeToRealtimeChannels();

        // Start polling as fallback to keep seat status in sync
        this.startSeatPolling();

        // Initialize mobile bottom sheet (Phase 1A Part 2)
        if (window.BottomSheetController) {
            this.bottomSheet = new window.BottomSheetController(this);
        }
    }

    /**
     * Subscribe to Laravel Reverb real-time channels.
     * - showtime.{id}   → public channel: seat lock/unlock updates for all viewers
     * - order.{code}    → private channel: payment confirmation for the buyer
     */
    subscribeToRealtimeChannels() {
        const echoOk = window.Echo && typeof window.Echo.channel === 'function';
        const showtimeId = this.config.showtimeId;

        console.log('[Booking WS] Echo available:', echoOk, '| showtimeId:', showtimeId);

        if (!echoOk || !showtimeId) {
            console.warn('[Booking WS] WebSocket skipped — falling back to polling only.');
            return;
        }

        try {
            const channel = window.Echo.channel(`showtime.${showtimeId}`);
            channel.listen('.seat.status.updated', (event) => {
                console.log('[Booking WS] Event received:', event);
                this.wsConnected = true;
                this.applyRealtimeSeatStatus(event.seat_id, event.status, event.user_id);
            });

            // Detect connection state
            if (window.Echo.connector?.pusher) {
                window.Echo.connector.pusher.connection.bind('connected', () => {
                    console.log('[Booking WS] Pusher connected ✅');
                    this.wsConnected = true;
                });
                window.Echo.connector.pusher.connection.bind('disconnected', () => {
                    console.warn('[Booking WS] Pusher disconnected ❌');
                    this.wsConnected = false;
                });
                window.Echo.connector.pusher.connection.bind('error', (err) => {
                    console.error('[Booking WS] Pusher error:', err);
                });
            }

            console.log('[Booking WS] Subscribed to channel showtime.' + showtimeId);
        } catch (err) {
            console.error('[Booking WS] Subscription failed:', err);
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
        if (this.checkoutCompleted || this.currentStep === 5) {
            return;
        }

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
            seat.status = status;
            seat.is_locked = status === 'locked';
            seat.is_available = status === 'available';
        }

        // Find the existing seat element
        const seatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${numericSeatId}"]`);
        if (!seatEl) return;

        // If status is not available, check if the current user had it selected
        if (status !== 'available') {
            if (this.selectedSeats.has(numericSeatId)) {
                this.selectedSeats.delete(numericSeatId);
                this.updateSummary();
                this.showToast('Ghế bạn chọn vừa bị người khác đặt. Vui lòng chọn ghế khác.', 'warning');
            }
        }

        // Re-create the seat element correctly using createSeat to ensure SVG/Gradients are rebuilt
        const hasCoupleCenter = seatEl.classList.contains('seat-couple-center');
        const newSeatEl = this.createSeat(seat);
        if (hasCoupleCenter) {
            newSeatEl.classList.add('seat-couple-center');
        }
        seatEl.replaceWith(newSeatEl);
    }

    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentStatus = urlParams.get('paymentStatus');
        const orderCode = urlParams.get('orderCode');

        if (paymentStatus) {
            // Clean up URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);

            if (paymentStatus === 'success' || paymentStatus === 'PAID' || paymentStatus === 'pending') {
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
        this.markCheckoutCompleted(orderCode);

        // Switch to the 5th tab (Success)
        this.switchTab(5);
        this.updateStepButtons();

        // Hide navigation buttons
        const navButtons = document.querySelector('.booking-nav-buttons');
        navButtons?.classList.add('d-none');

        // Hide sidebar
        const sidebar = document.querySelector('.booking-sidebar');
        sidebar?.classList.add('d-none');
        
        // Adjust layout width
        const container = document.querySelector('.booking-container');
        container?.classList.add('booking-container-result');
        
        const mainCol = document.querySelector('.booking-main');
        mainCol?.classList.add('booking-main-result');

        try {
            const order = await this.fetchVerifiedOrderResult(orderCode);

            if (!order) {
                throw new Error('Không thể tải thông tin đơn hàng');
            }

            if (!this.isPaidOrder(order)) {
                this.renderPendingPaymentResult(order);
                return;
            }

            this.renderSuccessResult(order);
            this.showToast('Thanh toán thành công!', 'success');
        } catch (error) {
            console.error('Lỗi load success screen:', error);
            this.setTextById('successStatusTitle', 'Chưa thể tải thông tin vé');
            this.setTextById('successStatusMessage', 'Thanh toán không bị thay đổi. Vui lòng mở mục Vé của tôi để kiểm tra lại.');
            this.setStatusIcon('bi-exclamation-triangle');
            this.showToast('Không thể tải thông tin vé: ' + error.message, 'danger');
        } finally {
            if (this.checkoutIntent?.state !== 'redirecting') {
                this.isCreatingPayment = false;
                this.hideLoading();
            }
        }
    }

    showFailureScreen(orderCode) {
        // Hide normal booking elements
        const bookingPageEl = document.querySelector('.booking-page');
        bookingPageEl?.classList.add('d-none');

        // Hide sidebar
        const sidebar = document.querySelector('.booking-sidebar');
        sidebar?.classList.add('d-none');
        
        // Adjust layout width
        const container = document.querySelector('.booking-container');
        container?.classList.add('booking-container-result');
        
        const mainCol = document.querySelector('.booking-main');
        mainCol?.classList.add('booking-main-result');

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

        // Progress bar step navigation
        this.progressSteps.forEach(stepEl => {
            stepEl.addEventListener('click', () => {
                const targetStep = parseInt(stepEl.dataset.step);
                if (!targetStep || isNaN(targetStep)) return;

                // Only allow navigating to completed steps or current step
                if (stepEl.classList.contains('step-completed') || stepEl.classList.contains('step-active')) {
                    this.switchTab(targetStep);
                }
            });
        });

        // Step navigation buttons
        this.nextStepBtn?.addEventListener('click', () => this.goToNextStep());
        this.prevStepBtn?.addEventListener('click', () => this.goToPrevStep());
        this.sidebarContinueBtn?.addEventListener('click', () => {
            if (this.currentStep === 4) {
                this.proceedToPayment();
            } else {
                this.goToNextStep();
            }
        });
        this.paymentBtn?.addEventListener('click', () => this.proceedToPayment());

        // Payment method selection styling
        const paymentRadios = document.querySelectorAll('.payment-method-radio');
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                document.querySelectorAll('.payment-method-item').forEach(item => {
                    item.classList.remove('active');
                });
                if (e.target.checked) {
                    e.target.closest('.payment-method-item').classList.add('active');
                }
            });
        });

        // Proceed to payment (legacy)
        this.proceedBtn?.addEventListener('click', () => this.proceedToPayment());

        // Cancel selection
        this.cancelBtn?.addEventListener('click', () => this.cancelSelection());

        // Voucher: input button only registers/saves voucher; discount is applied only
        // when the user explicitly clicks "Áp dụng" on a voucher in voucher-content.
        this.applyPromotionBtn?.addEventListener('click', () => this.registerPromotionFromInput());
        this.promotionCodeInput?.addEventListener('input', () => {
            this.appliedPromotion = null;
            this.setPromotionMessage('Nhập mã để đăng ký voucher. Mã chỉ giảm giá sau khi bạn bấm Áp dụng trong danh sách voucher.', 'text-white-50');
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

        this.exchangePointsBtn?.addEventListener('click', () => {
            const pointsToUse = parseInt(this.pointsInput?.value || 0, 10);
            
            if (pointsToUse < 0 || isNaN(pointsToUse)) {
                this.showToast('Vui lòng nhập số điểm hợp lệ.', 'warning');
                return;
            }
            
            if (pointsToUse > this.availablePoints) {
                this.showToast(`Bạn chỉ có tối đa ${this.availablePoints.toLocaleString('vi-VN')} điểm khả dụng.`, 'warning');
                return;
            }

            this.appliedPoints = pointsToUse;
            this.updateSummary();
            this.showToast(pointsToUse > 0 ? `Áp dụng thành công ${pointsToUse.toLocaleString('vi-VN')} điểm.` : 'Đã huỷ dùng điểm.', 'success');
        });

        // Release an uncommitted hold when the page lifecycle ends. A checkout
        // intent that already has a gateway link must keep its hold alive.
        window.addEventListener('pagehide', () => {
            // Stop timer + polling + close WebSocket channels cleanly
            this.destroy();
            const holdId = this.getCurrentHoldId();
            if (!holdId || this.checkoutCompleted || ['created', 'redirecting'].includes(this.checkoutIntent?.state)) return;

            navigator.sendBeacon(
                `${this.apiUrl}/seats/holds/${holdId}/release`,
                new Blob([JSON.stringify({ hold_id: holdId })], { type: 'application/json' })
            );
        });
    }

    switchTab(step) {
        if (step < 1 || step > 5) return;

        this.currentStep = step;
        const tabName = this.steps[step - 1];

        // Update tab buttons - scoped to booking page only
        document.querySelectorAll('.booking-page .tab-btn').forEach((btn, index) => {
            btn.classList.toggle('active', index === step - 1);
            btn.classList.toggle('completed', index < step - 1);
        });

        // Update modern progress bar state
        this.updateProgressBar();

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

        // Products are loaded when entering food tab
        if (step === 2 && this.products.length === 0) {
            this.loadProducts();
        }
    }

    updateProgressBar() {
        if (!this.progressSteps || this.progressSteps.length === 0) return;

        const connectors = document.querySelectorAll('.step-connector');

        this.progressSteps.forEach((stepEl, index) => {
            const stepNumber = parseInt(stepEl.dataset.step, 10);
            if (!stepNumber || Number.isNaN(stepNumber)) return;

            const isCompleted = stepNumber < this.currentStep;
            const isActive = stepNumber === this.currentStep;

            stepEl.classList.toggle('step-completed', isCompleted);
            stepEl.classList.toggle('step-active', isActive);
            stepEl.classList.toggle('step-pending', stepNumber > this.currentStep);
            stepEl.setAttribute('aria-current', isActive ? 'step' : 'false');
            stepEl.setAttribute('aria-disabled', stepNumber > this.currentStep ? 'true' : 'false');

            // Update connector line (which comes AFTER this step, index matches connector)
            if (connectors[index]) {
                connectors[index].classList.toggle('is-completed', isCompleted);
                connectors[index].classList.toggle('is-active', isActive);
            }
        });
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

        if (this.currentStep === 3 && this.getPayableTotal() <= 0) {
            await this.proceedToPayment();
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
        const isLockingFirstStep = this.currentStep === 1 && this.isLockingSeats;
        const nextLabel = isLockingFirstStep ? 'Đang giữ ghế...' : 'Tiếp tục';
        const isZeroAmountCheckout = this.getPayableTotal() <= 0;
        const sidebarLabel = isLockingFirstStep
            ? 'Đang giữ ghế...'
            : (this.currentStep < 4
                ? 'Tiếp tục <i class="bi bi-arrow-right ms-2"></i>'
                : (isZeroAmountCheckout ? 'Xác nhận đặt vé <i class="bi bi-check2-circle ms-2"></i>' : 'Thanh toán ngay <i class="bi bi-lock-fill ms-2"></i>'));

        if (this.nextStepBtn) {
            this.nextStepBtn.disabled = !canProceed || isLockingFirstStep;
            this.nextStepBtn.classList.toggle('d-none', this.currentStep >= 4);
            this.nextStepBtn.textContent = nextLabel;
            this.nextStepBtn.classList.toggle('is-loading', isLockingFirstStep);
        }

        if (this.sidebarContinueBtn) {
            this.sidebarContinueBtn.disabled = !canProceed || isLockingFirstStep;
            this.sidebarContinueBtn.innerHTML = sidebarLabel;
            this.sidebarContinueBtn.classList.toggle('is-loading', isLockingFirstStep);
            this.sidebarContinueBtn.classList.toggle('d-none', this.currentStep === 5);
        }

        // Update "Quay lại" button
        if (this.prevStepBtn) {
            this.prevStepBtn.classList.toggle('d-none', this.currentStep <= 1);
        }

        // Update "Thanh toán" button
        if (this.paymentBtn) {
            this.paymentBtn.classList.toggle('d-none', this.currentStep !== 4);
            this.paymentBtn.disabled = !canProceed;
        }
    }

    updateSidebarSummary() {
        let seatTotal = 0;
        const seatLabels = [];
        
        // Calculate seats
        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat) {
                seatTotal += Number(seat.price) || 0;
                seatLabels.push(seat.label || `${seat.row}${seat.number}`);
            }
        });

        // Calculate Products
        const productsTotal = this.calculateProductsTotal();
        
        // Calculate Promo
        const subtotal = seatTotal + productsTotal;
        const discountAmount = this.calculateDiscount(subtotal);
        
        // Calculate points usage
        const subtotalAfterVoucher = Math.max(0, subtotal - discountAmount);
        const maxPointsValue = this.appliedPoints * 1000;
        let pointsDiscount = 0;
        
        if (maxPointsValue > subtotalAfterVoucher) {
            // Adjust applied points if they exceed the remaining amount
            this.appliedPoints = Math.ceil(subtotalAfterVoucher / 1000);
            pointsDiscount = this.appliedPoints * 1000;
            if (this.pointsInput) {
                this.pointsInput.value = this.appliedPoints;
            }
        } else {
            pointsDiscount = maxPointsValue;
        }
        
        // Calculate Total
        const total = Math.max(0, subtotal - discountAmount - pointsDiscount);

        // Update Seats Info
        const receiptSeatsInfo = document.getElementById('receiptSeatsInfo');
        const receiptTicketLabel = document.getElementById('receiptTicketLabel');
        const receiptTicketPrice = document.getElementById('receiptTicketPrice');
        
        if (receiptSeatsInfo) {
            receiptSeatsInfo.innerHTML = seatLabels.length > 0 
                ? `${seatLabels.join(', ')} <small class="text-danger">(${seatLabels.length} ghế)</small>` 
                : 'Chưa chọn ghế';
        }
        if (receiptTicketLabel) {
            receiptTicketLabel.textContent = `Vé (x${seatLabels.length})`;
        }
        if (receiptTicketPrice) {
            receiptTicketPrice.textContent = this.formatCurrency(seatTotal);
        }

        // Update Products Info
        const receiptProductsRow = document.getElementById('receiptProductsRow');
        const receiptProductsInfo = document.getElementById('receiptProductsInfo');
        const receiptComboPriceRow = document.getElementById('receiptComboPriceRow');
        const receiptComboPrice = document.getElementById('receiptComboPrice');

        if (productsTotal > 0) {
            const productsList = Array.from(this.selectedProducts.entries())
                .map(([productId, quantity]) => {
                    const product = this.products.find(p => p.id === productId);
                    return product ? `${product.name} x${quantity}` : '';
                }).filter(Boolean).join('<br>');
                
            if (receiptProductsInfo) receiptProductsInfo.innerHTML = productsList;
            receiptProductsRow?.classList.remove('d-none');
            if (receiptComboPrice) receiptComboPrice.textContent = this.formatCurrency(productsTotal);
            receiptComboPriceRow?.classList.remove('d-none');
        } else {
            receiptProductsRow?.classList.add('d-none');
            receiptComboPriceRow?.classList.add('d-none');
        }

        // Update Promo Info
        const receiptPromoRow = document.getElementById('receiptPromoRow');
        const receiptPromoPrice = document.getElementById('receiptPromoPrice');
        
        if (discountAmount > 0) {
            if (receiptPromoPrice) receiptPromoPrice.textContent = `-${this.formatCurrency(discountAmount)}`;
            receiptPromoRow?.classList.remove('d-none');
        } else {
            receiptPromoRow?.classList.add('d-none');
        }

        // Handle points discount display
        if (this.loyaltyDiscountDisplay) {
            this.loyaltyDiscountDisplay.textContent = `- ${this.formatCurrency(pointsDiscount)}`;
        }

        // Update Total Price
        const receiptTotalPrice = document.getElementById('receiptTotalPrice');
        if (receiptTotalPrice) {
            const formatted = this.formatCurrency(total);
            const numPart = formatted.replace(' ₫', '').replace(' đ', '');
            receiptTotalPrice.innerHTML = `${numPart}<small>đ</small>`;
        }
    }

    async loadSeats() {
        try {
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
            this.isCreatingPayment = false;
            this.hideLoading();
        }
    }

    async loadProducts() {
        if (!this.productsContainer) return;
        this.productsContainer.innerHTML = this.createFoodSkeleton();

        try {
            const response = await this.fetchAPI('/products');

            if (!response.success) {
                throw new Error(response.message || 'Không thể tải combo');
            }

            const productsPayload = response.data;
            const products = Array.isArray(productsPayload)
                ? productsPayload
                : (Array.isArray(productsPayload?.data) ? productsPayload.data : []);
            this.products = window.BookingProductRenderer.normalize(products);
            this.renderProducts();
        } catch (error) {
            console.error('Load products error:', error);
            this.productsContainer.innerHTML = '<div class="alert alert-warning mb-0 small">Không thể tải danh sách combo.</div>';
        }
    }

    getProductIcon(name) {
        return new window.BookingProductRenderer(this).icon(name);
    }

    renderProducts() {
        new window.BookingProductRenderer(this).render();
    }

    changeProductQuantity(productId, delta) {
        const product = this.products.find(item => item.id === productId);
        if (!product) return;

        const currentQuantity = Number(this.selectedProducts.get(productId)) || 0;
        const maxQuantity = Number(product.max_quantity) || 0;
        const nextQuantity = Math.max(0, Math.min(maxQuantity, currentQuantity + delta));

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
            let firstSeatRow = seatMap[rowIndex] && Object.values(seatMap[rowIndex]).find(s => s);
            const rowLabelText = firstSeatRow ? firstSeatRow.row : '';

            const rowLabel = document.createElement('div');
            rowLabel.className = 'seat-row-label';
            rowLabel.textContent = rowLabelText;
            this.seatMapContainer.appendChild(rowLabel);

            let isCoupleRow = firstSeatRow && this.isCoupleSeat(firstSeatRow);
            const shiftRight = isCoupleRow && (dimensions.cols % 2 !== 0);

            let colIndex = 0;
            while (colIndex < dimensions.cols) {
                const seat = seatMap[rowIndex]?.[colIndex];

                if (!seat) {
                    this.seatMapContainer.appendChild(this.createEmptySeat());
                    colIndex++;
                    continue;
                }

                const isCouple = this.isCoupleSeat(seat);
                
                if (isCouple && colIndex + 2 > dimensions.cols) {
                    this.seatMapContainer.appendChild(this.createEmptySeat());
                    colIndex++;
                    continue;
                }

                const seatEl = this.createSeat(seat);
                if (shiftRight && isCouple) {
                    seatEl.classList.add('seat-couple-center');
                }
                this.seatMapContainer.appendChild(seatEl);
                colIndex += isCouple ? 2 : 1;
            }
            
            // Add right side label
            const rightRowLabel = document.createElement('div');
            rightRowLabel.className = 'seat-row-label right-label';
            rightRowLabel.textContent = rowLabelText;
            this.seatMapContainer.appendChild(rightRowLabel);
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
        // Disabled based on design reference which has no bottom column numbers
        const parent = this.seatMapContainer?.parentElement;
        if (!parent) return;

        let labels = parent.querySelector('.seat-grid-col-labels');
        if (labels) {
            labels.classList.add('d-none');
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

        // Determine gradient colors based on type + status
        const getGradient = (id, top, bot) =>
            `<defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${top}"/><stop offset="100%" stop-color="${bot}"/></linearGradient></defs>`;

        let gradId, gradTop, gradBot;
        if (status === 'selected') {
            gradId = `gs-${seat.id}`; gradTop = '#ff4455'; gradBot = '#c0000f';
        } else if (isVip) {
            gradId = `gv-${seat.id}`; gradTop = '#ff6a3d'; gradBot = '#c0392b';
        } else if (isCouple) {
            gradId = `gc-${seat.id}`; gradTop = '#b06ae9'; gradBot = '#7b2fa0';
        } else {
            gradId = `gn-${seat.id}`; gradTop = '#52596b'; gradBot = '#2e3340';
        }
        
        // Fix for browser SVG rendering bugs when re-inserting same IDs
        gradId = `${gradId}-${Math.random().toString(36).substring(2, 9)}`;

        const fillAttr = (status === 'holding' || status === 'booked' || status === 'locked' || status === 'maintenance')
            ? 'fill="#2a2d35"'
            : `fill="url(#${gradId})"`;

        const icon = isCouple
            ? `<svg width="2em" height="1em" viewBox="0 0 48 24" ${fillAttr} class="seat-icon-shape">${getGradient(gradId, gradTop, gradBot)}<rect x="5" y="2" width="38" height="11" rx="2"/><rect x="4" y="14" width="40" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="43" y="11" width="3" height="8" rx="1"/></svg>`
            : `<svg width="1em" height="1em" viewBox="0 0 24 24" ${fillAttr} class="seat-icon-shape">${getGradient(gradId, gradTop, gradBot)}<rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>`;

        seatDiv.innerHTML = icon;
        const seatLabel = document.createElement('span');
        seatLabel.className = 'seat-label';
        seatLabel.textContent = String(label ?? '');
        seatDiv.appendChild(seatLabel);

        if (this.isLockingSeats && this.selectedSeats.has(seat.id)) {
            seatDiv.classList.add('seat-pending-hold');
            seatDiv.setAttribute('aria-busy', 'true');
        }

        if (!this.isLockingSeats && (status === 'available' || status === 'selected' || status === 'holding')) {
            seatDiv.addEventListener('click', () => this.handleSeatClick(seat));
            seatDiv.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                this.handleSeatClick(seat);
            });
            seatDiv.setAttribute('role', 'button');
            seatDiv.setAttribute('tabindex', '0');
            seatDiv.setAttribute('aria-label', `Ghế ${seat.row}${seat.number}, ${seat.seat_type?.name || 'Thường'}`);
        } else {
            seatDiv.setAttribute('aria-disabled', 'true');
            if (status === 'maintenance') {
                seatDiv.classList.add('seat-maintenance');
            }
        }

        return seatDiv;
    }

    getSeatStatus(seat) {
        const seatId = seat.id;

        // Check if seat is selected by current user
        if (this.selectedSeats.has(seatId)) {
            return 'selected';
        }

        // If it's held by the current user on the server, but NOT in selectedSeats locally,
        // it means the user just unchecked it. To the user, it should look available.
        if (this.currentHold && this.currentHold.seat_ids?.includes(seatId)) {
            return 'available';
        }

        // Check seat status from API
        if (seat.status === 'booked') {
            return 'booked';
        }

        if (seat.status === 'locked' || seat.status === 'holding') {
            return 'locked';
        }

        if (seat.status === 'maintenance') {
            return 'maintenance';
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

        // Can't click booked, locked, or maintenance seats
        if (status === 'booked' || status === 'locked' || status === 'maintenance') {
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

        // Re-render only the clicked seat element to avoid full map DOM reflows
        const seatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${seatId}"]`);
        if (seatEl) {
            const hasCoupleCenter = seatEl.classList.contains('seat-couple-center');
            const newSeatEl = this.createSeat(seat);
            if (hasCoupleCenter) {
                newSeatEl.classList.add('seat-couple-center');
            }
            seatEl.replaceWith(newSeatEl);
        } else {
            this.renderSeatMap();
        }

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
            await this.lockPromise;

            if (this.isCurrentHoldForSelectedSeats()) {
                return true;
            }
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

        const confirmed = await window.Modal.confirmAsync('Hủy chọn ghế', 'Bạn có chắc muốn hủy toàn bộ ghế đang chọn?', {
            variant: 'warning'
        });
        if (!confirmed) return;

        // Unlock seats
        await this.unlockSeats();

        // Clear selection
        this.selectedSeats.clear();

        // Refresh seats from server so the UI resets statuses correctly
        await this.loadSeats();
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
        const quantity = this.selectedSeats.size;

        this.selectedSeats.forEach(seatId => {
            const seat = this.seats.find(s => s.id === seatId);
            if (seat) {
                seatTotal += Number(seat.price) || 0;
            }
        });
        const productsTotal = this.calculateProductsTotal();
        const subtotal = seatTotal + productsTotal;
        const discountAmount = this.calculateDiscount(subtotal);
        
        // Calculate max points usable (cap at subtotal - discount)
        const subtotalAfterVoucher = Math.max(0, subtotal - discountAmount);
        const maxPointsValue = this.appliedPoints * 1000;
        let pointsDiscount = 0;
        
        if (maxPointsValue > subtotalAfterVoucher) {
            // Adjust applied points if they exceed the remaining amount
            this.appliedPoints = Math.ceil(subtotalAfterVoucher / 1000);
            pointsDiscount = this.appliedPoints * 1000;
            if (this.pointsInput) {
                this.pointsInput.value = this.appliedPoints;
            }
        } else {
            pointsDiscount = maxPointsValue;
        }

        const total = Math.max(0, subtotal - discountAmount - pointsDiscount);

        this.renderSelectedProducts();

        // Update UI
        if (this.seatQuantity) {
            this.seatQuantity.textContent = quantity;
        }

        if (this.seatSurcharge) {
            this.seatSurcharge.textContent = this.formatCurrency(seatTotal);
        }

        if (this.productTotal) {
            this.productTotal.textContent = this.formatCurrency(productsTotal);
        }

        if (this.discountAmount) {
            this.discountAmount.textContent = this.formatCurrency(discountAmount);
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

        // Update mobile bottom sheet (Phase 1A Part 2)
        if (this.bottomSheet) {
            this.bottomSheet.updateSheet();
        }
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

        // Update mobile bottom sheet timer (Phase 1A Part 2)
        if (this.bottomSheet) {
            this.bottomSheet.updateTimer();
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
        if (this.isCreatingPayment) {
            return;
        }

        if (this.lockPromise) {
            this.showLoading('Đang kiểm tra ghế...');
            try {
                await this.lockPromise;
            } catch (e) { }
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
                    type: p.type || 'product',
                    id: p.id,
                    quantity: p.quantity
                });
            });

            const payload = {
                showtime_id: this.config.showtimeId,
                items,
                voucher_code: this.appliedPromotion?.code || null,
                points_used: this.appliedPoints || 0
            };
            const intent = this.getOrCreateCheckoutIntent(payload);

            if (intent.state === 'created' || intent.state === 'redirecting') {
                this.redirectToCheckout(intent);
                return;
            }

            this.isCreatingPayment = true;
            this.showLoading('Đang tạo đơn hàng...');
            intent.state = 'submitting';

            // Create order and payment link. The intent key is stable across
            // retries for the same hold and checkout payload.
            const response = await this.fetchAPI('/payments', {
                method: 'POST',
                body: {
                    idempotency_key: intent.key,
                    ...payload
                }
            });

            const orderCode = response.data?.gateway_order_code;

            const isCompletedWithoutGateway = response.success
                && (
                    response.data?.requires_payment === false
                    || String(response.data?.payment_status || '').toLowerCase() === 'paid'
                    || Number(response.data?.total_amount) <= 0
                )
                && orderCode;

            if (isCompletedWithoutGateway) {
                intent.state = 'created';
                intent.orderCode = orderCode;
                this.currentOrderCode = orderCode;
                this.markCheckoutCompleted(orderCode);
                this.showToast('Đơn 0đ đã được xác nhận.', 'success');
                await this.showSuccessScreen(orderCode);
                return;
            }

            if (response.success && response.data?.checkout_url) {
                intent.state = 'created';
                intent.checkoutUrl = response.data.checkout_url;
                intent.orderCode = orderCode || null;
                this.showToast('Đang chuyển hướng đến cổng thanh toán...', 'success');

                // Store order code and subscribe to private WebSocket channel.
                // If user pays on mobile (QR scan), the desktop browser will receive
                // the OrderPaid event and auto-show the success screen.
                if (orderCode) {
                    this.currentOrderCode = orderCode;
                    this.subscribeToOrderChannel(orderCode);
                }

                // Redirect directly to PayOS checkout while keeping the intent
                // active if navigation is delayed or blocked by the browser.
                this.redirectToCheckout(intent);
            } else if (response.success && orderCode) {
                const order = await this.fetchVerifiedOrderResult(orderCode);
                if (this.isPaidOrder(order)) {
                    intent.state = 'created';
                    intent.orderCode = orderCode;
                    this.currentOrderCode = orderCode;
                    this.markCheckoutCompleted(orderCode);
                    this.showToast('Đơn hàng đã được xác nhận.', 'success');
                    await this.showSuccessScreen(orderCode);
                    return;
                }

                throw new Error(response.message || 'Đơn hàng đã tạo nhưng chưa có liên kết thanh toán. Vui lòng kiểm tra Vé của tôi.');
            } else {
                throw new Error(response.message || 'Không thể tạo đơn hàng');
            }
        } catch (error) {
            console.error('Create order error:', error);
            if (this.checkoutCompleted) {
                return;
            }

            if (this.checkoutIntent?.state === 'submitting') {
                this.checkoutIntent.state = 'retryable';
            }
            this.showToast(error.message || 'Lỗi khi tạo đơn hàng', 'danger');

            // Reload seats to get latest status
            await this.loadSeats();
        } finally {
            this.isCreatingPayment = false;
            this.hideLoading();
        }
    }

    async fetchVerifiedOrderResult(orderCode) {
        const retryDelays = [0, 350, 700, 1200, 1800];
        let latestOrder = null;

        for (const delay of retryDelays) {
            if (delay > 0) {
                await new Promise(resolve => setTimeout(resolve, delay));
            }

            const response = await this.fetchAPI(`/payments/orders/${encodeURIComponent(orderCode)}`, {
                method: 'GET'
            });

            if (!response.success || !response.data) {
                throw new Error(response.message || 'Không thể tải thông tin đơn hàng');
            }

            latestOrder = response.data;
            if (this.isPaidOrder(latestOrder)) {
                return latestOrder;
            }
        }

        return latestOrder;
    }

    isPaidOrder(order) {
        return String(order?.payment_status || '').toLowerCase() === 'paid'
            || String(order?.status || '').toLowerCase() === 'confirmed'
            || Number(order?.status_code) === 2;
    }

    renderSuccessResult(order) {
        const invoice = order.invoice || {};
        const showtime = order.showtime || {};
        const tickets = Array.isArray(invoice.tickets) ? invoice.tickets : [];
        const products = Array.isArray(invoice.products) ? invoice.products : [];
        const scheduledAt = showtime.scheduled_at || order.show_date;
        const seatLabels = tickets
            .map(ticket => ticket.metadata?.seat_label)
            .filter(Boolean);

        this.setTextById('successStatusTitle', 'Thanh toán thành công');
        this.setTextById('successStatusMessage', 'Vé và hóa đơn đã được hệ thống xác nhận.');
        this.setStatusIcon('bi-check-lg');
        this.setTextById('successOrderCode', order.order_code || order.code || order.gateway_order_code || '---');
        this.setTextById('successMovieTitle', order.movie_title || showtime.movie?.title || 'N/A');
        this.setTextById('successMovieFormat', showtime.format?.name || showtime.version_type?.name || '2D');
        this.setTextById('successShowtime', this.formatResultDateTime(scheduledAt));
        this.setTextById(
            'successTheater',
            [order.branch_name || order.theater_name || showtime.screen?.theater?.name, order.screen_name || showtime.screen?.name]
                .filter(Boolean)
                .join(' - ') || 'N/A'
        );
        this.setTextById('successTheaterAddress', order.theater_address || showtime.screen?.theater?.address || 'N/A');
        this.setTextById('successSeatsInfo', seatLabels.join(', ') || 'N/A');
        this.setTextById('successSubtotal', this.formatCurrency(invoice.subtotal ?? order.total_amount));
        this.setTextById('successTotalAmount', this.formatCurrency(order.total_amount));
        this.setTextById('successDate', this.formatResultDateTime(order.created_at));

        this.renderSuccessProducts(products);
        this.renderResultDiscount('successVoucherRow', 'successVoucherLabel', 'successVoucherDiscount', {
            label: invoice.promotion?.code ? `Voucher (${invoice.promotion.code})` : 'Voucher',
            amount: Number(invoice.voucher_discount) || 0,
        });
        this.renderResultDiscount('successPointsRow', 'successPointsLabel', 'successPointsDiscount', {
            label: invoice.points_used ? `Điểm thành viên (${Number(invoice.points_used).toLocaleString('vi-VN')} điểm)` : 'Điểm thành viên',
            amount: Number(invoice.point_discount) || 0,
        });

        const viewTicketBtn = document.getElementById('viewTicketBtn');
        if (viewTicketBtn) {
            viewTicketBtn.href = `/tickets/order/${encodeURIComponent(order.order_code || order.code)}`;
        }
    }

    renderPendingPaymentResult(order) {
        this.setTextById('successStatusTitle', 'Thanh toán đang được xác minh');
        this.setTextById('successStatusMessage', 'Hệ thống chưa nhận được xác nhận cuối cùng. Không thanh toán lại trong lúc chờ đồng bộ.');
        this.setStatusIcon('bi-clock-history');
        this.setTextById('successOrderCode', order?.order_code || order?.code || order?.gateway_order_code || '---');

        const viewTicketBtn = document.getElementById('viewTicketBtn');
        if (viewTicketBtn) {
            viewTicketBtn.href = '/profile#tickets';
            viewTicketBtn.textContent = 'Kiểm tra Vé của tôi';
        }
    }

    renderSuccessProducts(products) {
        const container = document.getElementById('successProductsList');
        if (!container) return;

        container.replaceChildren();
        if (products.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'ticket-value text-muted';
            empty.textContent = 'Không có sản phẩm đi kèm';
            container.appendChild(empty);
            return;
        }

        products.forEach(product => {
            const row = document.createElement('div');
            row.className = 'result-line-item';
            const name = document.createElement('span');
            const amount = document.createElement('strong');
            name.textContent = `${product.metadata?.product_name || product.metadata?.name || 'Sản phẩm'} × ${Number(product.quantity) || 1}`;
            amount.textContent = this.formatCurrency(product.total_price);
            row.append(name, amount);
            container.appendChild(row);
        });
    }

    renderResultDiscount(rowId, labelId, valueId, discount) {
        const row = document.getElementById(rowId);
        if (!row) return;

        const amount = Math.max(0, Number(discount.amount) || 0);
        row.classList.toggle('d-none', amount === 0);
        this.setTextById(labelId, discount.label);
        this.setTextById(valueId, `-${this.formatCurrency(amount)}`);
    }

    setStatusIcon(iconClass) {
        const icon = document.getElementById('successStatusIcon');
        if (icon) icon.className = `bi ${iconClass}`;
    }

    setTextById(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value ?? '---';
    }

    formatResultDateTime(value) {
        if (!value) return 'N/A';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'N/A';
        return `${date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}, ${date.toLocaleDateString('vi-VN')}`;
    }

    getOrCreateCheckoutIntent(payload) {
        const fingerprint = JSON.stringify({
            hold_id: this.getCurrentHoldId(),
            showtime_id: payload.showtime_id,
            items: [...payload.items].sort((left, right) => {
                const leftKey = `${left.type}:${left.id}`;
                const rightKey = `${right.type}:${right.id}`;
                return leftKey.localeCompare(rightKey);
            }),
            voucher_code: payload.voucher_code,
            points_used: payload.points_used
        });

        if (!this.checkoutIntent || this.checkoutIntent.fingerprint !== fingerprint) {
            this.checkoutIntent = {
                key: this.createIdempotencyKey(),
                fingerprint,
                state: 'idle',
                checkoutUrl: null,
                orderCode: null
            };
        }

        return this.checkoutIntent;
    }

    redirectToCheckout(intent) {
        if (!intent?.checkoutUrl) return;

        intent.state = 'redirecting';
        this.isCreatingPayment = true;
        window.location.href = intent.checkoutUrl;
    }

    markCheckoutCompleted(orderCode = null) {
        this.checkoutCompleted = true;
        this.currentOrderCode = orderCode || this.currentOrderCode || null;
        this.currentHold = null;
        this.stopTimer();

        if (this._pollInterval) {
            clearInterval(this._pollInterval);
            this._pollInterval = null;
        }

        if (this.checkoutIntent) {
            this.checkoutIntent.state = 'created';
            this.checkoutIntent.orderCode = this.currentOrderCode;
        }
    }

    fillPromotionCode(code) {
        if (!code || !this.promotionCodeInput) return;

        this.promotionCodeInput.value = code;
        this.appliedPromotion = null;
        this.setPromotionMessage('Đã chọn mã. Bấm Áp dụng trong danh sách voucher để giảm giá.', 'text-white-50');
        this.updateSummary();
        this.renderRegisteredPromotions();
    }

    cancelPromotion() {
        const cancelledCode = this.appliedPromotion?.code || this.promotionCodeInput?.value || '';

        this.appliedPromotion = null;

        if (this.promotionCodeInput) {
            this.promotionCodeInput.value = '';
        }

        if (cancelledCode) {
            this.showToast(`Đã hủy áp dụng mã ${String(cancelledCode).toUpperCase()}.`, 'info');
        }
        this.setPromotionMessage('', '');
        this.updateSummary();
        this.renderRegisteredPromotions();
    }

    async loadUserPoints() {
        if (!this.auth?.isAuthenticated?.()) {
            this.availablePoints = 0;
            this.updatePointsUI();
            return;
        }

        try {
            const response = await this.fetchAPI('/auth/me', { method: 'GET' });
            if (response.success && response.data) {
                this.availablePoints = Number(response.data.loyalty_points || 0);
            }
        } catch (error) {
            console.warn('[Booking] Cannot load user points.', error);
            this.availablePoints = 0;
        }
        this.updatePointsUI();
    }

    updatePointsUI() {
        if (this.availablePointsDisplay) {
            this.availablePointsDisplay.textContent = `${this.availablePoints.toLocaleString('vi-VN')} điểm`;
        }
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
            this.availableVouchersSection?.classList.add('d-none');
            this.voucherContent.innerHTML = '';
            return;
        }

        this.availableVouchersSection?.classList.remove('d-none');
        this.voucherContent.innerHTML = this.renderVoucherItems(promotions, selectedCode, appliedCode);
    }

    renderVoucherSuggestion(promotions, appliedCode) {
        return '';
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
            const expDate = promotion.end_date ? new Date(promotion.end_date).toLocaleDateString('vi-VN', {day: 'numeric', month: 'short'}) : 'Không thời hạn';
            
            return `
                <div class="voucher-card-dark ${isApplied ? 'applied' : ''}" 
                     data-code="${this.escapeHtml(code)}"
                     data-voucher-action="${isApplied ? 'cancel' : 'apply'}">
                    
                    <div class="voucher-badge-row">
                        ${isApplied 
                            ? '<span class="badge-applied"><i class="bi bi-check-circle-fill"></i> Đã áp dụng</span>' 
                            : '<span class="badge-use-now">Dùng ngay</span>'
                        }
                        <span class="voucher-exp">HSD: ${expDate}</span>
                    </div>
                    
                    <h5 class="voucher-title">
                        ${this.escapeHtml(discountText)}
                    </h5>
                    
                    <p class="voucher-desc">
                        ${this.escapeHtml(descriptionText)}
                    </p>
                    
                    <div class="voucher-bg-icon">
                        <i class="bi bi-ticket-perforated-fill"></i>
                    </div>
                    
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
                if (codeOverride) {
                    this.showToast(response.message || 'Áp dụng mã giảm giá thành công.', 'success');
                    this.setPromotionMessage('', '');
                } else {
                    this.setPromotionMessage(response.message || 'Áp dụng mã giảm giá thành công.', 'text-success');
                }
            }

            this.updateSummary();
            this.renderRegisteredPromotions();
        } catch (error) {
            this.appliedPromotion = null;
            if (showMessage) {
                if (codeOverride) {
                    this.showToast(error.message || 'Mã giảm giá không hợp lệ.', 'danger');
                    this.setPromotionMessage('', '');
                } else {
                    this.setPromotionMessage(error.message || 'Mã giảm giá không hợp lệ.', 'text-danger');
                }
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
                seatsTotal += Number(seat.price) || 0;
            }
        });

        return seatsTotal + this.calculateProductsTotal();
    }

    calculateProductsTotal() {
        let total = 0;

        this.selectedProducts.forEach((quantity, productId) => {
            const product = this.products.find(item => item.id === productId);
            if (product) {
                total += (Number(product.price) || 0) * (Number(quantity) || 0);
            }
        });

        return total;
    }

    getPayableTotal() {
        const subtotal = this.calculateSubtotal();
        const discountAmount = this.calculateDiscount(subtotal);
        const subtotalAfterVoucher = Math.max(0, subtotal - discountAmount);
        const pointsDiscount = Math.min((Number(this.appliedPoints) || 0) * 1000, subtotalAfterVoucher);

        return Math.max(0, subtotal - discountAmount - pointsDiscount);
    }
    calculateDiscount(subtotal) {
        if (!this.appliedPromotion) {
            return 0;
        }

        return Math.min(parseFloat(this.appliedPromotion.discount_amount || 0), subtotal);
    }

    getSelectedProductsPayload() {
        return Array.from(this.selectedProducts.entries()).map(([id, quantity]) => {
            const product = this.products.find(item => item.id === id);

            return {
                id: product?.source_id || id,
                type: product?.catalog_type || 'product',
                quantity
            };
        });
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

    safeImageUrl(value) {
        const candidate = String(value || '').trim();
        if (/^\/(?!\/)[A-Za-z0-9_./?=&%-]+$/.test(candidate) && !candidate.includes('..')) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate;
        return '';
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

    createIdempotencyKey() {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID();
        }

        return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (character) => {
            const numericCharacter = Number(character);
            const randomValue = window.crypto.getRandomValues(new Uint8Array(1))[0];

            return (numericCharacter ^ (randomValue & (15 >> (numericCharacter / 4)))).toString(16);
        });
    }
    // Utility Methods
    async fetchAPI(endpoint, options = {}) {
        if (!window.apiClient) {
            throw new Error('API client is not initialized.');
        }

        const method = String(options.method || 'GET').toUpperCase();
        const body = typeof options.body === 'string'
            ? JSON.parse(options.body)
            : options.body ?? null;
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
        const normalizedType = type === 'danger' ? 'error' : type;
        const titles = {
            success: 'Thành công',
            error: 'Không thể thực hiện',
            warning: 'Cần lưu ý',
            info: 'Thông báo',
        };
        const toastMethod = window.Toast?.[normalizedType] || window.Toast?.info;
        toastMethod?.(titles[normalizedType] || titles.info, String(message || ''));
    }

    formatCurrency(amount) {
        const numericAmount = Number(amount);

        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(Number.isFinite(numericAmount) ? numericAmount : 0);
    }

    /**
     * Poll seat status every 5 seconds.
     * Acts as reliable fallback when WebSocket is unavailable or disconnected.
     * Also works alongside WebSocket for extra reliability.
     */
    startSeatPolling() {
        // Clear any existing interval
        if (this._pollInterval) clearInterval(this._pollInterval);

        this._pollInterval = setInterval(async () => {
            // Don't poll if user is in the middle of locking seats
            if (this.checkoutCompleted || this.currentStep === 5 || this.isLockingSeats || this._pollInFlight || document.hidden) return;

            this._pollInFlight = true;
            try {
                const response = await this.fetchAPI(
                    `/seats/showtime/${this.config.encryptedShowtimeId}`
                );

                if (!response.success) return;

                const freshSeats = response.data.seats || [];

                // Compare each seat status with what we have in memory
                // Only update seats that changed AND are not selected by current user
                let changed = false;
                freshSeats.forEach(freshSeat => {
                    const currentSeat = this.seats.find(s => s.id === freshSeat.id);
                    if (!currentSeat) return;

                    // Skip seats the current user has selected
                    if (this.selectedSeats.has(freshSeat.id)) return;

                    // If status changed → update in memory + re-render that seat incrementally
                    if (currentSeat.status !== freshSeat.status) {
                        console.log(`[Polling] Seat ${freshSeat.id} changed: ${currentSeat.status} → ${freshSeat.status}`);
                        this.applyRealtimeSeatStatus(freshSeat.id, freshSeat.status);
                    }
                });
            } catch (e) {
                // Silent fail — polling is best-effort
            } finally {
                this._pollInFlight = false;
            }
        }, 5000); // Poll every 5 seconds
    }

    destroy() {
        this.stopTimer();
        if (this._pollInterval) {
            clearInterval(this._pollInterval);
            this._pollInterval = null;
        }
        // Unsubscribe from Reverb channels to prevent memory/connection leaks
        const showtimeId = this.config.showtimeId;
        if (window.Echo && showtimeId) {
            try {
                window.Echo.leave(`showtime.${showtimeId}`);
            } catch (e) {
                console.warn('[Booking] Error leaving showtime channel:', e);
            }
        }
        if (window.Echo && this.currentOrderCode) {
            try {
                window.Echo.leave(`order.${this.currentOrderCode}`);
            } catch (e) {
                console.warn('[Booking] Error leaving order channel:', e);
            }
        }
    }

    createSeatMapSkeleton() {
        const rows = 10;
        const cols = 15;
        let html = '';
        for (let i = 0; i < rows; i++) {
            html += `<div class="skel-seat-row">`;
            for (let j = 0; j < cols; j++) {
                html += `<div class="skel-seat profile-skeleton"></div>`;
            }
            html += `</div>`;
        }
        return html;
    }

    createFoodSkeleton() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `
                <div class="skel-food-card">
                    <div class="skel-food-img profile-skeleton"></div>
                    <div class="skel-food-info">
                        <div class="skel-food-title profile-skeleton"></div>
                        <div class="skel-food-desc profile-skeleton"></div>
                        <div class="skel-food-price profile-skeleton"></div>
                    </div>
                </div>
            `;
        }
        return html;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.bookingManager = new BookingManager();
});
