/**
 * pos-payment.js – Payment handling (cash + QR PayOS)
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;
    const { api, formatVnd, formatDate, formatTime, escapeHtml, toast } = global.PosUtils;

    let paymentMethod = 'cash'; // 'cash' | 'payos_qr'
    let qrPollingTimer = null;
    let currentOrder = null;

    // DOM
    const pmCash       = document.getElementById('pmCash');
    const pmQr         = document.getElementById('pmQr');
    const btnConfirm   = document.getElementById('btnConfirmOrder');
    const btnConfirmTx = document.getElementById('btnConfirmText');
    const btnClear     = document.getElementById('btnClearCart');
    const btnCancelTransaction = document.getElementById('btnCancelTransaction');

    // Modals
    const cashModalEl  = document.getElementById('cashModal');
    const cashModal    = cashModalEl ? bootstrap.Modal.getOrCreateInstance(cashModalEl) : null;
    const cashAmount   = document.getElementById('cashAmount');
    const btnCancelCash = document.getElementById('btnCancelCash');
    const btnConfirmCash = document.getElementById('btnConfirmCash');

    const cancelTransactionModalEl = document.getElementById('cancelTransactionModal');
    const cancelTransactionModal = cancelTransactionModalEl
        ? bootstrap.Modal.getOrCreateInstance(cancelTransactionModalEl)
        : null;
    const btnConfirmCancelTransaction = document.getElementById('btnConfirmCancelTransaction');

    const qrModalEl    = document.getElementById('qrModal');
    const qrModal      = qrModalEl ? bootstrap.Modal.getOrCreateInstance(qrModalEl) : null;
    const qrImage      = document.getElementById('qrImage');
    const qrAmount     = document.getElementById('qrAmount');
    const qrStatus     = document.getElementById('qrStatusText');
    const qrSpinner    = document.getElementById('qrSpinner');
    const btnCancelQr  = document.getElementById('btnCancelQr');
    const btnCancelQrHeader = document.getElementById('btnCancelQrHeader');

    const successModalEl    = document.getElementById('successModal');
    const successModal      = successModalEl ? bootstrap.Modal.getOrCreateInstance(successModalEl) : null;
    const successOrderCode  = document.getElementById('successOrderCode');
    const successDetails    = document.getElementById('successDetails');
    const successPointsEarned = document.getElementById('successPointsEarned');
    const btnNewTransaction = document.getElementById('btnNewTransaction');
    const btnPrintAll = document.getElementById('btnPrintAll');
    const btnPrintTickets = document.getElementById('btnPrintTickets');
    const btnPrintConcessions = document.getElementById('btnPrintConcessions');
    const btnPrintInvoice = document.getElementById('btnPrintInvoice');

    // ── Payment method selection ──────────────────────────
    function setMethod(method) {
        paymentMethod = method;
        pmCash.classList.toggle('active', method === 'cash');
        pmQr.classList.toggle('active', method === 'payos_qr');
        pmCash.classList.toggle('selected', method === 'cash');
        pmQr.classList.toggle('selected', method === 'payos_qr');
        pmCash.dataset.selected = method === 'cash' ? 'true' : 'false';
        pmQr.dataset.selected = method === 'payos_qr' ? 'true' : 'false';
        pmCash.setAttribute('aria-checked', method === 'cash');
        pmQr.setAttribute('aria-checked', method === 'payos_qr');
        if (btnConfirmTx) {
            btnConfirmTx.textContent = method === 'cash' ? 'Thanh toán tiền mặt' : 'Tạo QR thanh toán';
        }
    }

    if (pmCash) pmCash.addEventListener('click', () => setMethod('cash'));
    if (pmQr)   pmQr.addEventListener('click', () => setMethod('payos_qr'));
    [pmCash, pmQr].forEach((methodElement) => {
        methodElement?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            setMethod(methodElement === pmCash ? 'cash' : 'payos_qr');
        });
    });

    function getCheckoutData() {
        const customer = global.PosCustomer?.getCustomer?.();
        const showtime = global.PosShowtime?.getSelected?.();
        const seats    = global.PosSeat?.getSelected?.() || [];
        const cart     = global.PosCart?.getOrderData?.() || {};
        const pointsToRedeem = cart.loyaltyPointsApplied ?? global.PosCustomer?.getPointsToRedeem?.() ?? 0;

        const hasTickets = showtime && seats.length > 0;
        const hasProducts = cart.productItems && cart.productItems.length > 0;
        const theaterId = global.PosApp?.getConcessionTheaterId?.();

        if (!hasTickets && !hasProducts) {
            toast('Vui lòng chọn ghế hoặc bắp nước để thanh toán', 'warning');
            return null;
        }

        if (hasTickets && !seats.length) {
            toast('Vui lòng chọn ghế', 'warning');
            return null;
        }

        if (!hasTickets && !theaterId) {
            toast('Vui lòng chọn rạp phục vụ cho đơn bắp nước', 'warning');
            return null;
        }

        const ticketClassifications = cart.ticketClassifications || {};
        const tickets = seats.map(seat => ({
            seat_id: seat.id,
            audience_type: ticketClassifications[seat.id]?.audience_type || 'adult',
            student_card_verified: Boolean(ticketClassifications[seat.id]?.student_card_verified),
        }));
        const unverifiedStudent = tickets.some(ticket => ticket.audience_type === 'student' && !ticket.student_card_verified);
        if (unverifiedStudent) {
            toast('Vui lòng xác nhận đã xem thẻ sinh viên cho từng vé sinh viên', 'warning');
            return null;
        }

        return {
            total: cart.grandTotal || 0,
            body: {
                showtime_id: showtime ? showtime.id : null,
                theater_id: showtime ? null : theaterId,
                seat_ids: seats.map(s => s.id),
                tickets,
                products: (cart.productItems || []).map(p => ({ id: p.id, quantity: p.quantity, type: p._type })),
                customer_id: customer?.id || null,
                customer_phone: customer?.phone || '',
                customer_name: customer?.name || 'Khách vãng lai',
                customer_mode: customer?.id ? 'member' : 'guest',
                payment_method: paymentMethod,
                loyalty_points_to_use: pointsToRedeem,
            },
        };
    }

    function setCheckoutButtonBusy(isBusy) {
        if (btnConfirm) {
            btnConfirm.disabled = isBusy;
            btnConfirm.innerHTML = isBusy
                ? '<div class="pos-spinner"></div> Đang xử lý...'
                : `<i class="bi bi-check-circle"></i><span id="btnConfirmText">${paymentMethod === 'cash' ? 'Thanh toán tiền mặt' : 'Tạo QR thanh toán'}</span>`;
        } else {
            const footerBtn = document.getElementById('btnFooterNext');
            if (footerBtn) {
                footerBtn.disabled = isBusy;
                footerBtn.innerHTML = isBusy
                    ? '<div class="pos-spinner"></div> Đang xử lý...'
                    : 'XÁC NHẬN ĐƠN HÀNG <i class="bi bi-arrow-right"></i>';
            }
        }
    }

    async function createPendingOrder(checkout) {
        if (currentOrder) return currentOrder;

        const res = await api.post(`${cfg.apiBase}/orders`, checkout.body);
        currentOrder = res.data;
        return currentOrder;
    }

    function isCompletedOrder(order) {
        return Boolean(order) && (
            order.payment_status === 'paid'
            || order.status === 'paid'
            || order.requires_payment === false
            || Number(order.total_amount) <= 0
        );
    }

    // ── Start payment ──────────────────────────────────────
    async function createOrder() {
        const checkout = getCheckoutData();
        if (!checkout) return;

        // Cash does not create an order until staff confirms that payment was received.
        // Staff can safely close this review modal to amend food/drink items without losing seats.
        if (paymentMethod === 'cash') {
            showCashConfirmation(checkout.total);
            return;
        }

        if (currentOrder) {
            toast('Đơn QR đang chờ thanh toán. Hãy hủy giao dịch trước khi tạo đơn mới.', 'warning');
            return;
        }

        setCheckoutButtonBusy(true);
        try {
            const order = await createPendingOrder(checkout);
            if (isCompletedOrder(order)) {
                currentOrder = null;
                showSuccess(order);
                return;
            }

            if (order.checkout_url) {
                window.location.href = order.checkout_url;
            } else {
                toast('Không tìm thấy link thanh toán PayOS', 'error');
            }
        } catch (err) {
            toast(err.message || 'Không thể tạo đơn hàng', 'error');
            if (err.status === 409) await global.PosCart?.loadProducts?.();
        } finally {
            resetButton();
        }
    }

    async function cancelPendingOrder() {
        const orderToCancel = currentOrder;
        if (!orderToCancel) return true;

        try {
            await api.post(`${cfg.apiBase}/orders/${orderToCancel.id}/cancel`);
            currentOrder = null;
            toast('Đã hủy đơn hàng đang chờ thanh toán', 'warning');
            global.PosApp?.clearHold?.();
            global.PosSeat?.clearSelection?.();
            return true;
        } catch (err) {
            console.error('Lỗi khi hủy đơn hàng:', err);
            toast(err.message || 'Không thể hủy đơn đang chờ thanh toán', 'error');
            return false;
        } finally {
            resetButton();
            const showtime = global.PosShowtime?.getSelected?.();
            if (showtime && global.PosSeat?.load) {
                global.PosSeat.load(showtime.id);
            }
        }
    }

    // ── Cash confirmation ─────────────────────────────────
    function showCashConfirmation(total) {
        cashAmount.textContent = formatVnd(total);
        cashModal?.show();
    }

    if (btnCancelCash) btnCancelCash.addEventListener('click', () => {
        cashModal?.hide();
        toast('Bạn có thể tiếp tục chỉnh sửa đơn hàng. Ghế vẫn được giữ.', 'info');
    });

    if (cashModalEl) cashModalEl.addEventListener('hide.bs.modal', (event) => {
        if (!currentOrder || currentOrder.payment_status === 'paid') return;

        event.preventDefault();
        toast('Đơn đang chờ xác nhận. Hãy thử lại hoặc hủy giao dịch trước.', 'warning');
    });

    if (btnConfirmCash) btnConfirmCash.addEventListener('click', async () => {
        const checkout = getCheckoutData();
        if (!checkout) return;

        btnConfirmCash.disabled = true;
        btnConfirmCash.innerHTML = '<div class="pos-spinner"></div>';

        try {
            let confirmedOrder;
            if (currentOrder && !isCompletedOrder(currentOrder)) {
                const res = await api.post(`${cfg.apiBase}/orders/${currentOrder.id}/confirm-cash`);
                confirmedOrder = res.data || currentOrder;
            } else {
                const res = await api.post(`${cfg.apiBase}/orders`, {
                    ...checkout.body,
                    cash_received: true,
                });
                confirmedOrder = res.data;
            }

            if (!isCompletedOrder(confirmedOrder)) {
                throw new Error('Đơn hàng chưa được ghi nhận thanh toán.');
            }

            currentOrder = null;
            cashModal?.hide();
            showSuccess(confirmedOrder);
        } catch (err) {
            toast(err.message || 'Lỗi xác nhận thanh toán', 'error');
            if (err.status === 409) await global.PosCart?.loadProducts?.();
        } finally {
            btnConfirmCash.disabled = false;
            btnConfirmCash.innerHTML = '<i class="bi bi-check-lg"></i> Đã nhận tiền';
        }
    });

    // ── QR Payment ────────────────────────────────────────
    function showQrPayment(order) {
        const qrUrl = order.checkout_url || order.qr_url || '';
        qrAmount.textContent = formatVnd(order.total_amount || order.total || 0);
        
        if (qrUrl) {
            qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}`;
        } else {
            qrImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"/>';
        }
        
        qrStatus.textContent = 'Đang chờ thanh toán...';
        qrSpinner.style.display = 'block';
        qrModal?.show();
        resetButton();

        // Start polling
        startQrPolling(order.id);
    }

    function startQrPolling(orderId) {
        if (qrPollingTimer) clearInterval(qrPollingTimer);
        qrPollingTimer = setInterval(async () => {
            try {
                // Call the new payment-status endpoint to sync with PayOS
                const resStatus = await api.get(`${cfg.apiBase}/orders/${orderId}/payment-status`);
                if (resStatus.data && resStatus.data.paid) {
                    stopQrPolling();
                    // Fetch full order details
                    const resOrder = await api.get(`${cfg.apiBase}/orders/${orderId}`);
                    const order = resOrder.data;
                    qrModal?.hide();
                    showSuccess(order);
                }
            } catch (e) { /* ignore polling errors */ }
        }, 3000);
    }

    function stopQrPolling() {
        if (qrPollingTimer) { clearInterval(qrPollingTimer); qrPollingTimer = null; }
    }

    if (btnCancelQr) btnCancelQr.addEventListener('click', () => {
        stopQrPolling();
        qrModal?.hide();
        cancelPendingOrder();
    });
    if (btnCancelQrHeader) btnCancelQrHeader.addEventListener('click', () => {
        stopQrPolling();
        qrModal?.hide();
        cancelPendingOrder();
    });

    // ── Success ───────────────────────────────────────────
    function showSuccess(order) {
        currentOrder = order;
        successOrderCode.textContent = order.code || order.order_code || '–';

        const paymentLabels = {
            cash: 'Tiền mặt',
            qr_online: 'QR PayOS',
            payos_qr: 'QR PayOS',
            payos: 'PayOS',
            zero_amount: 'Điểm tích lũy',
        };
        const seatLabels = (order.seats || global.PosSeat?.getSelected?.() || []).map(s => s.label || s.name || (s.row + '' + s.number));
        const products = (order.products || []).map(product => `${product.name} × ${product.quantity}`).join(', ');
        const screening = [order.theater_name, order.screen_name].filter(Boolean).join(' · ');
        const movieMeta = [order.movie_duration ? `${order.movie_duration} phút` : '', order.format_name || ''].filter(Boolean).join(' · ');
        const showtimeLabel = order.showtime ? `${formatDate(order.showtime)} · ${formatTime(order.showtime)}` : '';
        const pointDiscount = Number(order.point_discount ?? order.loyalty_discount ?? 0);
        const detailItems = [
            { label: 'Khách hàng', value: order.customer_name },
            { label: 'Số điện thoại', value: order.customer_phone },
            { label: 'Phim', value: order.movie_title ? `${order.movie_title}${movieMeta ? ` · ${movieMeta}` : ''}` : '', wide: true },
            { label: 'Rạp / Phòng', value: screening },
            { label: 'Thời gian', value: showtimeLabel },
            { label: 'Ghế', value: seatLabels.join(', '), wide: seatLabels.length > 4 },
            { label: 'Bắp nước', value: products, wide: true },
        ].filter(item => item.value);
        const detailGrid = detailItems.map(item => `
            <div class="pos-success-detail-item${item.wide ? ' pos-success-detail-item-wide' : ''}">
                <span>${escapeHtml(item.label)}</span>
                <strong>${escapeHtml(String(item.value))}</strong>
            </div>
        `).join('');
        const paymentLabel = paymentLabels[order.payment_method] || order.payment_method || 'Chưa xác định';
        const totalAmount = order.total_amount ?? order.total ?? 0;
        const details = `
            <div class="pos-success-payment-summary">
                <div class="pos-success-payment-row">
                    <span>Phương thức thanh toán</span>
                    <strong>${escapeHtml(paymentLabel)}</strong>
                </div>
                ${pointDiscount > 0 ? `
                    <div class="pos-success-payment-row">
                        <span>Giảm bằng ${Number(order.points_used || 0)} điểm</span>
                        <strong>-${formatVnd(pointDiscount)}</strong>
                    </div>
                ` : ''}
                <div class="pos-success-payment-row pos-success-payment-total">
                    <span>Tổng thanh toán</span>
                    <strong>${formatVnd(totalAmount)}</strong>
                </div>
            </div>
            ${detailGrid ? `<div class="pos-success-detail-grid">${detailGrid}</div>` : ''}
        `;
        successDetails.innerHTML = details;
        successPointsEarned.textContent = order.loyalty_points_earned || Math.floor((order.total_amount || 0) / cfg.earnRate);

        if (btnPrintTickets) btnPrintTickets.hidden = seatLabels.length === 0;
        if (btnPrintConcessions) btnPrintConcessions.hidden = !products;

        successModal?.show();
        toast('Giao dịch thành công! 🎉', 'success');
    }

    // ── New Transaction ─────────────────────────────────────
    if (btnNewTransaction) btnNewTransaction.addEventListener('click', () => {
        successModal?.hide();
        document.dispatchEvent(new CustomEvent('pos:reset'));
    });

    function printCurrentOrder(sections) {
        if (!currentOrder?.id) {
            toast('Không xác định được đơn hàng cần in', 'error');
            return;
        }
        if (!global.OrderPrinting) {
            toast('Chức năng in chưa sẵn sàng. Vui lòng tải lại trang.', 'error');
            return;
        }
        global.OrderPrinting.open(currentOrder.id, sections);
    }

    btnPrintAll?.addEventListener('click', () => {
        const sections = ['invoice'];
        if ((currentOrder?.seats || []).length > 0) sections.push('tickets');
        if ((currentOrder?.products || []).length > 0) sections.push('concessions');
        printCurrentOrder(sections);
    });
    btnPrintTickets?.addEventListener('click', () => printCurrentOrder(['tickets']));
    btnPrintConcessions?.addEventListener('click', () => printCurrentOrder(['concessions']));
    btnPrintInvoice?.addEventListener('click', () => printCurrentOrder(['invoice']));

    // ── Confirm Order Button ──────────────────────────────
    if (btnConfirm) btnConfirm.addEventListener('click', createOrder);

    // ── Clear cart ────────────────────────────────────────
    if (btnClear) btnClear.addEventListener('click', () => {
        cancelTransaction();
    });

    if (btnCancelTransaction) btnCancelTransaction.addEventListener('click', () => {
        cancelTransactionModal?.show();
    });

    if (btnConfirmCancelTransaction) btnConfirmCancelTransaction.addEventListener('click', async () => {
        btnConfirmCancelTransaction.disabled = true;
        try {
            const cancelled = await cancelTransaction();
            if (cancelled) cancelTransactionModal?.hide();
        } finally {
            btnConfirmCancelTransaction.disabled = false;
        }
    });

    async function cancelTransaction() {
        const pendingOrderCancelled = await cancelPendingOrder();
        if (!pendingOrderCancelled) return false;

        await global.PosApp?.releaseTransactionHold?.();
        document.dispatchEvent(new CustomEvent('pos:reset'));
        toast('Đã hủy giao dịch và trả lại ghế đang giữ', 'info');
        return true;
    }

    // ── Helpers ───────────────────────────────────────────
    function resetButton() {
        setCheckoutButtonBusy(false);
    }

    document.addEventListener('pos:reset', () => {
        paymentMethod = 'cash';
        setMethod('cash');
        if (qrPollingTimer) clearInterval(qrPollingTimer);
        currentOrder = null;
        cashModal?.hide();
        qrModal?.hide();
        successModal?.hide();
        cancelTransactionModal?.hide();
        resetButton();
    });

    function reset() {
        currentOrder = null;
        paymentMethod = 'cash';
        setMethod('cash');
        stopQrPolling();
        cashModal?.hide();
        qrModal?.hide();
        successModal?.hide();
        cancelTransactionModal?.hide();
        resetButton();
    }

    function getPaymentMethod() { return paymentMethod; }

    // ── Handle redirect callbacks on page load ────────────
    const urlParams = new URLSearchParams(window.location.search);
    const paymentStatus = urlParams.get('paymentStatus');
    const orderId = urlParams.get('orderId');

    if (paymentStatus === 'success' && orderId) {
        api.get(`${cfg.apiBase}/orders/${orderId}`)
            .then(res => {
                showSuccess(res.data);
                window.history.replaceState({}, document.title, window.location.pathname);
            })
            .catch(err => {
                toast('Lỗi tải thông tin đơn hàng: ' + err.message, 'error');
            });
    } else if (paymentStatus === 'cancel') {
        toast('Thanh toán PayOS đã bị hủy', 'warning');
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    global.PosPayment = {
        getPaymentMethod,
        reset,
        processPayment: createOrder,
        cancelPendingOrder,
        cancelTransaction,
    };

})(window);
