import Toast from '../components/toast.js';

(function() {
    'use strict';

    let orderData = null;
    let selectedPaymentMethod = 'vnpay';
    let countdownInterval = null;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        orderData = window.ORDER_DATA;
        if (!orderData) {
            console.error('Order data not found');
            return;
        }

        startCountdown();

        setupEventListeners();
    }

    function setupEventListeners() {
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                selectedPaymentMethod = e.target.value;
                updatePaymentMethodUI();
            });
        });

        const btnPayment = document.getElementById('btnPayment');
        if (btnPayment) {
            btnPayment.addEventListener('click', handlePayment);
        }

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
                clearInterval(countdownInterval);
                handleOrderExpired();
                return;
            }

            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const timerEl = document.getElementById('orderTimer');
            if (timerEl) {
                timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

                timerEl.classList.toggle('warning', minutes < 2);
            }
        };

        updateTimer();

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
            btnPayment.disabled = true;
            loadingOverlay?.classList.remove('d-none');

            const result = await window.apiClient.post(`/orders/${orderData.id}/payment`, {
                payment_method: selectedPaymentMethod
            });
            const payment = result.data || result;

            if (payment.payment_url) {
                window.location.href = payment.payment_url;
            } else {
                throw new Error('Không nhận được URL thanh toán');
            }

        } catch (error) {
            console.error('Payment error:', error);

            Toast.error(
                'Lỗi thanh toán',
                error.message || 'Có lỗi xảy ra khi xử lý thanh toán. Vui lòng thử lại.'
            );

            btnPayment.disabled = false;
            loadingOverlay?.classList.add('d-none');
        }
    }

    async function handleCancelOrder() {
        if (!await window.Modal.confirmAsync('Hủy đơn hàng', 'Bạn có chắc chắn muốn hủy đơn hàng này?', {
            variant: 'warning'
        })) {
            return;
        }

        const btnCancel = document.getElementById('btnCancelOrder');

        try {
            btnCancel.disabled = true;
            btnCancel.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Đang hủy...';

            await window.apiClient.post(`/orders/${orderData.id}/cancel`);

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

            Toast.error(
                'Lỗi hủy đơn hàng',
                error.message || 'Có lỗi xảy ra khi hủy đơn hàng. Vui lòng thử lại.'
            );

            btnCancel.disabled = false;
            btnCancel.innerHTML = '<i class="bi bi-x-circle me-2"></i>Hủy đơn hàng';
        }
    }

    window.addEventListener('beforeunload', () => {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
    });

    window.paymentPage = {
        orderData: () => orderData,
        selectedMethod: () => selectedPaymentMethod,
        processPayment: handlePayment
    };
})();
