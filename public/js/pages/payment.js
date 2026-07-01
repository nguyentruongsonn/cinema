/**
 * Payment Page Manager
 * Handles payment processing, order expiration timer, and payment method selection
 */

class PaymentManager {
    constructor() {
        this.orderData = window.ORDER_DATA || {};
        this.selectedMethod = 'payos';
        this.timerInterval = null;
        this.isProcessing = false;

        this.init();
    }

    init() {
        // Check if order data exists
        if (!this.orderData.id) {
            this.showToast('Không tìm thấy thông tin đơn hàng', 'error');
            setTimeout(() => window.location.href = '/', 2000);
            return;
        }

        // Initialize components
        this.initTimer();
        this.initPaymentMethods();
        this.initButtons();

        console.log('Payment page initialized for order:', this.orderData.code);
    }

    /**
     * Initialize order expiration timer
     */
    initTimer() {
        if (!this.orderData.expired_at) {
            console.warn('No expiration time set for order');
            return;
        }

        const updateTimer = () => {
            const now = new Date();
            const expiredAt = new Date(this.orderData.expired_at);
            const remaining = Math.max(0, Math.floor((expiredAt - now) / 1000));

            if (remaining <= 0) {
                this.handleOrderExpired();
                return;
            }

            this.displayTime(remaining);

            // Add warning class if less than 3 minutes
            const timerEl = document.getElementById('orderTimer');
            if (remaining <= 180 && timerEl) {
                timerEl.classList.add('warning');
            }
        };

        // Update immediately
        updateTimer();

        // Update every second
        this.timerInterval = setInterval(updateTimer, 1000);
    }

    /**
     * Display time in MM:SS format
     */
    displayTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        const display = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

        const timerEl = document.getElementById('orderTimer');
        if (timerEl) {
            timerEl.textContent = display;
        }
    }

    /**
     * Handle order expiration
     */
    handleOrderExpired() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }

        this.showToast('Đơn hàng đã hết hạn thanh toán', 'error');

        // Disable payment button
        const btnPayment = document.getElementById('btnPayment');
        if (btnPayment) {
            btnPayment.disabled = true;
            btnPayment.innerHTML = '<i class="bi bi-clock-history me-2"></i>Đã hết hạn';
        }

        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = '/';
        }, 3000);
    }

    /**
     * Initialize payment method selection
     */
    initPaymentMethods() {
        const methods = document.querySelectorAll('.payment-method');

        methods.forEach(method => {
            method.addEventListener('click', () => {
                const radio = method.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    this.selectedMethod = radio.value;
                    console.log('Selected payment method:', this.selectedMethod);
                }
            });
        });

        // Set initial method
        const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
        if (checkedRadio) {
            this.selectedMethod = checkedRadio.value;
        }
    }

    /**
     * Initialize action buttons
     */
    initButtons() {
        const btnPayment = document.getElementById('btnPayment');
        const btnCancel = document.getElementById('btnCancelOrder');

        if (btnPayment) {
            btnPayment.addEventListener('click', () => this.handlePayment());
        }

        if (btnCancel) {
            btnCancel.addEventListener('click', () => this.handleCancelOrder());
        }
    }

    /**
     * Handle payment button click
     */
    async handlePayment() {
        if (this.isProcessing) return;

        // Validate
        if (!this.selectedMethod) {
            this.showToast('Vui lòng chọn phương thức thanh toán', 'warning');
            return;
        }

        // Confirm
        const confirmed = confirm('Bạn xác nhận thanh toán đơn hàng này?');
        if (!confirmed) return;

        this.isProcessing = true;
        this.showLoading(true);

        try {
            const data = await window.apiClient.post('/payments', {
                order_id: this.orderData.id,
                payment_method: this.selectedMethod,
                return_url: `${window.location.origin}/payment/callback`
            });

            // Success - redirect to payment gateway
            if (data.data && data.data.checkout_url) {
                this.showToast('Đang chuyển đến cổng thanh toán...', 'success');

                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = data.data.checkout_url;
                }, 1000);
            } else {
                // Payment method doesn't require redirect (e.g., cash)
                this.showToast('Thanh toán thành công!', 'success');
                setTimeout(() => {
                    window.location.href = `/orders/${this.orderData.id}`;
                }, 1500);
            }

        } catch (error) {
            console.error('Payment error:', error);
            this.showToast(error.message || 'Không thể xử lý thanh toán. Vui lòng thử lại.', 'error');
            this.isProcessing = false;
            this.showLoading(false);
        }
    }

    /**
     * Handle cancel order button click
     */
    async handleCancelOrder() {
        if (this.isProcessing) return;

        const confirmed = confirm(
            'Bạn có chắc chắn muốn hủy đơn hàng này?\n' +
            'Ghế đã chọn sẽ được mở lại và đơn hàng không thể khôi phục.'
        );

        if (!confirmed) return;

        this.isProcessing = true;
        this.showLoading(true);

        try {
            await window.apiClient.delete(`/orders/${this.orderData.id}`);

            this.showToast('Đã hủy đơn hàng thành công', 'success');

            // Clear timer
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }

            // Redirect to home after delay
            setTimeout(() => {
                window.location.href = '/';
            }, 1500);

        } catch (error) {
            console.error('Cancel order error:', error);
            this.showToast(error.message || 'Không thể hủy đơn hàng. Vui lòng thử lại.', 'error');
            this.isProcessing = false;
            this.showLoading(false);
        }
    }

    /**
     * Show loading overlay
     */
    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Try to use existing toast system
        if (window.showToast) {
            window.showToast(message, type);
            return;
        }

        // Fallback to alert
        alert(message);
    }

    /**
     * Cleanup
     */
    destroy() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check authentication - if not logged in, show login modal instead of redirect
    if (!window.authManager || !window.authManager.isAuthenticated()) {
        if (window.authManager && window.authManager.showModal) {
            window.authManager.showModal('login');
        } else {
            alert('Vui lòng đăng nhập để tiếp tục');
            window.location.href = '/';
        }
        return;
    }

    // Initialize payment manager
    const paymentManager = new PaymentManager();

    // Store globally for debugging
    window.paymentManager = paymentManager;

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        paymentManager.destroy();
    });
});
