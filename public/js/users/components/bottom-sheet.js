class BottomSheetController {
    constructor(bookingManager) {
        this.booking = bookingManager;

        this.sheet = document.getElementById('bookingBottomSheet');
        this.backdrop = document.getElementById('bottomSheetBackdrop');
        this.handle = document.querySelector('.bottom-sheet-handle');
        this.miniBar = document.getElementById('miniSummaryBar');
        this.content = document.querySelector('.bottom-sheet-content');

        this.miniSeatsCount = document.getElementById('miniSeatsCount');
        this.miniProductsCount = document.getElementById('miniProductsCount');
        this.miniTotalPrice = document.getElementById('miniTotalPrice');

        this.sheetSeatsList = document.getElementById('sheetSeatsList');
        this.sheetSeatsPrice = document.getElementById('sheetSeatsPrice');
        this.sheetProductsSection = document.getElementById('sheetProductsSection');
        this.sheetProductsList = document.getElementById('sheetProductsList');
        this.sheetPromotionSection = document.getElementById('sheetPromotionSection');
        this.sheetPromotionCode = document.getElementById('sheetPromotionCode');
        this.sheetPromotionDiscount = document.getElementById('sheetPromotionDiscount');
        this.sheetSubtotal = document.getElementById('sheetSubtotal');
        this.sheetDiscountRow = document.getElementById('sheetDiscountRow');
        this.sheetDiscount = document.getElementById('sheetDiscount');
        this.sheetFinalTotal = document.getElementById('sheetFinalTotal');
        this.sheetTimer = document.getElementById('sheetTimer');
        this.sheetTimerDisplay = document.getElementById('sheetTimerDisplay');
        this.sheetContinueBtn = document.getElementById('sheetContinueBtn');

        this.isExpanded = false;
        this.isDragging = false;
        this.startY = 0;
        this.currentY = 0;
        this.sheetHeight = 0;

        this.DRAG_THRESHOLD = 50;
        this.VELOCITY_THRESHOLD = 0.5;

        this.init();
    }

    init() {
        if (!this.sheet) return;

        this.setupEventListeners();
        this.updateSheet();
    }

    setupEventListeners() {
        if (this.miniBar) {
            this.miniBar.addEventListener('click', () => {
                if (!this.isDragging) {
                    this.toggle();
                }
            });
        }

        if (this.handle) {
            this.handle.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
            this.handle.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
            this.handle.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });
        }

        if (this.content) {
            this.content.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
            this.content.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
            this.content.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });
        }

        if (this.backdrop) {
            this.backdrop.addEventListener('click', () => this.collapse());
        }

        if (this.sheetContinueBtn) {
            this.sheetContinueBtn.addEventListener('click', () => {
                if (this.booking && this.booking.handleContinue) {
                    this.booking.handleContinue();
                }
            });
        }
    }

    handleTouchStart(e) {
        this.isDragging = true;
        this.startY = e.touches[0].clientY;
        this.currentY = this.startY;
        this.startTime = Date.now();
        this.sheetHeight = this.sheet.offsetHeight;

        if (e.target === this.handle || this.content.scrollTop === 0) {
            e.preventDefault();
        }
    }

    handleTouchMove(e) {
        if (!this.isDragging) return;

        this.currentY = e.touches[0].clientY;
        const deltaY = this.currentY - this.startY;

        if ((this.isExpanded && deltaY > 0) || (!this.isExpanded && deltaY < 0)) {
            e.preventDefault();

            const dragAmount = Math.abs(deltaY);
            const rubberBand = dragAmount > 100 ? 100 + (dragAmount - 100) * 0.3 : dragAmount;
            const transform = this.isExpanded ?
                `translateY(${rubberBand}px)` :
                `translateY(calc(100% - 60px - ${rubberBand}px))`;

            this.sheet.style.transition = 'none';
            this.sheet.style.transform = transform;
        }
    }

    handleTouchEnd(e) {
        if (!this.isDragging) return;

        this.isDragging = false;
        const deltaY = this.currentY - this.startY;
        const duration = Date.now() - this.startTime;
        const velocity = Math.abs(deltaY) / duration;

        this.sheet.style.transition = '';
        this.sheet.style.transform = '';

        const shouldToggle = Math.abs(deltaY) > this.DRAG_THRESHOLD || velocity > this.VELOCITY_THRESHOLD;

        if (shouldToggle) {
            if (deltaY > 0 && this.isExpanded) {
                this.collapse();
            } else if (deltaY < 0 && !this.isExpanded) {
                this.expand();
            }
        }
    }

    expand() {
        if (this.isExpanded) return;

        this.isExpanded = true;
        this.sheet.classList.remove('collapsed');
        this.sheet.classList.add('expanded');
        this.backdrop.classList.add('active');

        document.body.style.overflow = 'hidden';
    }

    collapse() {
        if (!this.isExpanded) return;

        this.isExpanded = false;
        this.sheet.classList.remove('expanded');
        this.sheet.classList.add('collapsed');
        this.backdrop.classList.remove('active');

        document.body.style.overflow = '';
    }

    toggle() {
        if (this.isExpanded) {
            this.collapse();
        } else {
            this.expand();
        }
    }

    updateSheet() {
        if (!this.sheet) return;

        const selectedSeats = Array.from(this.booking.selectedSeats || []);
        const selectedProducts = this.booking.selectedProducts || new Map();
        const appliedPromotion = this.booking.appliedPromotion;

        this.updateMiniSummary(selectedSeats, selectedProducts);

        this.updateSeatsSection(selectedSeats);
        this.updateProductsSection(selectedProducts);
        this.updatePromotionSection(appliedPromotion);
        this.updateTotalSection();
        this.updateTimer();
        this.updateContinueButton();
    }

    updateMiniSummary(selectedSeats, selectedProducts) {
        const seatsCount = selectedSeats.length;
        const productsCount = Array.from(selectedProducts.values()).reduce((sum, qty) => sum + qty, 0);
        const total = this.calculateTotal();

        this.miniSeatsCount.textContent = `${seatsCount} ghế`;
        this.miniProductsCount.textContent = `${productsCount} combo`;
        this.miniTotalPrice.textContent = this.formatPrice(total);
    }

    updateSeatsSection(selectedSeats) {
        if (selectedSeats.length === 0) {
            this.sheetSeatsList.innerHTML = '<span class="bottom-sheet-empty-seat">Chưa chọn ghế</span>';
            this.sheetSeatsPrice.textContent = '0₫';
            return;
        }

        const seatTags = selectedSeats.map(seatId => {
            const seat = this.booking.seats.find(s => s.id === seatId);
            if (!seat) return '';
            return `<span class="bottom-sheet-seat-tag">${seat.row_label}${seat.seat_number}</span>`;
        }).join('');

        this.sheetSeatsList.innerHTML = seatTags;

        const seatPrice = this.calculateSeatsPrice();
        this.sheetSeatsPrice.textContent = this.formatPrice(seatPrice);
    }

    updateProductsSection(selectedProducts) {
        if (selectedProducts.size === 0) {
            this.sheetProductsSection.classList.add('d-none');
            return;
        }

        this.sheetProductsSection.classList.remove('d-none');

        const productItems = Array.from(selectedProducts.entries()).map(([productId, quantity]) => {
            const product = this.booking.products.find(p => String(p.id) === String(productId));
            if (!product || quantity === 0) return '';

            const itemTotal = product.price * quantity;
            return `
                <div class="section-item">
                    <span class="item-label">${product.name} x${quantity}</span>
                    <span class="item-value">${this.formatPrice(itemTotal)}</span>
                </div>
            `;
        }).join('');

        this.sheetProductsList.innerHTML = productItems;
    }

    updatePromotionSection(appliedPromotion) {
        if (!appliedPromotion) {
            this.sheetPromotionSection.classList.add('d-none');
            return;
        }

        this.sheetPromotionSection.classList.remove('d-none');
        this.sheetPromotionCode.textContent = appliedPromotion.code || '---';

        const discount = this.calculateDiscount();
        this.sheetPromotionDiscount.textContent = `-${this.formatPrice(discount)}`;
    }

    updateTotalSection() {
        const subtotal = this.calculateSubtotal();
        const discount = this.calculateDiscount();
        const total = subtotal - discount;

        this.sheetSubtotal.textContent = this.formatPrice(subtotal);

        if (discount > 0) {
            this.sheetDiscountRow.classList.remove('d-none');
            this.sheetDiscount.textContent = `-${this.formatPrice(discount)}`;
        } else {
            this.sheetDiscountRow.classList.add('d-none');
        }

        this.sheetFinalTotal.textContent = this.formatPrice(total);
    }

    updateTimer() {
        if (!this.booking.currentHold) {
            this.sheetTimer.classList.add('d-none');
            return;
        }

        this.sheetTimer.classList.remove('d-none');
        const minutes = Math.floor(this.booking.timerSeconds / 60);
        const seconds = this.booking.timerSeconds % 60;
        this.sheetTimerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }

    updateContinueButton() {
        const canContinue = this.booking.selectedSeats && this.booking.selectedSeats.size > 0;
        this.sheetContinueBtn.disabled = !canContinue;
    }

    calculateSeatsPrice() {
        let total = 0;
        for (const seatId of this.booking.selectedSeats) {
            const seat = this.booking.seats.find(s => s.id === seatId);
            if (seat) {
                total += parseFloat(seat.price || this.booking.basePrice);
            }
        }
        return total;
    }

    calculateProductsPrice() {
        let total = 0;
        for (const [productId, quantity] of this.booking.selectedProducts.entries()) {
            const product = this.booking.products.find(p => String(p.id) === String(productId));
            if (product) {
                total += product.price * quantity;
            }
        }
        return total;
    }

    calculateSubtotal() {
        return this.calculateSeatsPrice() + this.calculateProductsPrice();
    }

    calculateDiscount() {
        if (!this.booking.appliedPromotion) return 0;

        const subtotal = this.calculateSubtotal();
        const promotion = this.booking.appliedPromotion;

        if (promotion.discount_type === 'percentage') {
            return Math.floor(subtotal * promotion.discount_value / 100);
        } else {
            return Math.min(promotion.discount_value, subtotal);
        }
    }

    calculateTotal() {
        return Math.max(0, this.calculateSubtotal() - this.calculateDiscount());
    }

    formatPrice(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }
}

window.BottomSheetController = BottomSheetController;
