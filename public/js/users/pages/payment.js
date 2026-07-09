/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PAYMENT PAGE MODULE
 * Handles payment method selection, order countdown, and payment processing
 * ═══════════════════════════════════════════════════════════════════════════
 */

import Toast from '../components/toast.js';

(function() {
    'use strict';

    let orderData = null;
    let selectedPaymentMethod = 'vnpay';
    let countdownInterval = null;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Get order data from window
        orderData = window.ORDER_DATA;
        if (!orderData) {
            console.error('Order data not found');
            return;
        }

        // Start countdown timer
        startCountdown();

        // Setup event listeners
        setupEventListeners();
    }

    function setupEventListeners() {
        // Payment method selection
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                selectedPaymentMethod = e.target.value;
                updatePaymentMethodUI();
            });
        });

        // Payment button
        const btnPayment = document.getElementById('btnPayment');
        if (btnPayment) {
            btnPayment.addEventListener('click', handlePayment);
        }

        // Cancel order button
        const btnCancel = document.getElementById('btnCancelOrder');
        if (btnCancel) {
            btnCancel.addEventListener('click', handleCancelOrder);
        }
    }

    function updatePaymentMethodUI() {
        document.querySelectorAll('.payment-method').forEach(method => {
            const isSelected = method.dataset.method === selectedPaymentMethod;
            method.classList.toggle('selected', isSelected);
        });
    }

    function startCountdown() {
        if (!orderData.expired_at) return;

        const updateTimer = () => {
            const now = new Date().getTime();
            const expiredTime = new Date(orderData.expired_at).getTime();
            const distance = expiredTime - now;

            if (distance < 0) {
                // Order expired
                clearInterval(countdownInterval);
                handleOrderExpired();
                return;
            }

            // Calculate minutes and seconds
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Display timer
            const timerEl = document.getElementById('orderTimer');
            if (timerEl) {
                timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

                // Change color when less than 2 minutes
                if (minutes < 2) {
                    timerEl.style.color = '#dc3545';
                }
            }
        };

        // Update immediately
        updateTimer();

        // Update every second
        countdownInterval = setInterval(updateTimer, 1000);
    }

    function handleOrderExpired() {
        if (typeof Toast !== 'undefined') {
            Toast.error(
                'Đơn hàng đã hết hạn',
                'Bạn sẽ được chuyển về trang chủ trong giây lát.'
            );
        }

        setTimeout(() => {
            window.location.href = '/';
        }, 2000);
    }

    async function handlePayment() {
        const btnPayment = document.getElementById('btnPayment');
        const loadingOverlay = document.getElementById('loadingOverlay');

        try {
            // Disable button and show loading
            btnPayment.disabled = true;
            if (loadingOverlay) loadingOverlay.style.display = 'flex';

            // Create payment
            const response = await fetch(`/api/v1/orders/${orderData.id}/payment`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    payment_method: selectedPaymentMethod
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Không thể tạo thanh toán');
            }

            const result = await response.json();
            const payment = result.data || result;

            // Redirect to payment gateway
            if (payment.payment_url) {
                window.location.href = payment.payment_url;
            } else {
                throw new Error('Không nhận được URL thanh toán');
            }

        } catch (error) {
            console.error('Payment error:', error);

            if (typeof Toast !== 'undefined') {
                Toast.error(
                    'Lỗi thanh toán',
                    error.message || 'Có lỗi xảy ra khi xử lý thanh toán. Vui lòng thử lại.'
                );
            } else {
                alert(error.message || 'Có lỗi xảy ra khi xử lý thanh toán. Vui lòng thử lại.');
            }

            // Re-enable button and hide loading
            btnPayment.disabled = false;
            if (loadingOverlay) loadingOverlay.style.display = 'none';
        }
    }

    async function handleCancelOrder() {
        if (!confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) {
            return;
        }

        const btnCancel = document.getElementById('btnCancelOrder');

        try {
            btnCancel.disabled = true;
            btnCancel.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Đang hủy...';

            const response = await fetch(`/api/v1/orders/${orderData.id}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Không thể hủy đơn hàng');
            }

            if (typeof Toast !== 'undefined') {
                Toast.success(
                    'Hủy đơn hàng thành công',
                    'Bạn sẽ được chuyển về trang chủ trong giây lát.'
                );
            }

            setTimeout(() => {
                window.location.href = '/';
            }, 2000);

        } catch (error) {
            console.error('Cancel order error:', error);

            if (typeof Toast !== 'undefined') {
                Toast.error(
                    'Lỗi hủy đơn hàng',
                    error.message || 'Có lỗi xảy ra khi hủy đơn hàng. Vui lòng thử lại.'
                );
            } else {
                alert(error.message || 'Có lỗi xảy ra khi hủy đơn hàng. Vui lòng thử lại.');
            }

            // Re-enable button
            btnCancel.disabled = false;
            btnCancel.innerHTML = '<i class="bi bi-x-circle me-2"></i>Hủy đơn hàng';
        }
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
    });

    // Expose for debugging
    window.paymentPage = {
        orderData: () => orderData,
        selectedMethod: () => selectedPaymentMethod,
        processPayment: handlePayment
    };
})();
