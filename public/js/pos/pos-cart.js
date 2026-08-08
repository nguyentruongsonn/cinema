/**
 * pos-cart.js – Cart management, product selection, pricing summary
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;
    const { api, formatVnd, toast } = global.PosUtils;

    // State
    let seatItems     = [];
    let productItems  = [];
    let products      = [];     // available products from API
    let discounts     = { student: 0, loyalty: 0, voucher: 0 };
    let subtotal      = 0;
    let grandTotal    = 0;
    let earnedPoints  = 0;
    let activeProductCategory = 'all';
    const ticketClassifications = new Map();

    // DOM
    const cartBody        = document.getElementById('cartBody');
    const cartEmpty       = document.getElementById('cartEmpty');
    const seatsSection    = document.getElementById('cartSeatsSection');
    const seatItemsEl     = document.getElementById('cartSeatItems');
    const productsSection = document.getElementById('cartProductsSection');
    const productItemsEl  = document.getElementById('cartProductItems');
    const discountSection = document.getElementById('cartDiscountsSection');
    const discountItemsEl = document.getElementById('cartDiscountItems');
    const summaryEl       = document.getElementById('cartSummary');
    const subTotalEl      = document.getElementById('summarySubtotal');
    const studentRowEl    = document.getElementById('summaryStudentRow');
    const studentDiscEl   = document.getElementById('summaryStudentDiscount');
    const pointsRowEl     = document.getElementById('summaryPointsRow');
    const pointsDiscEl    = document.getElementById('summaryPointsDiscount');
    const totalEl         = document.getElementById('summaryTotal');
    const earnPointsEl    = document.getElementById('summaryEarnPoints');
    const cartBadge       = document.getElementById('cartItemCount');
    const cartFooter      = document.getElementById('cartFooter');
    const productGrid     = document.getElementById('productGrid');

    // ── Listen for seat changes ───────────────────────────
    document.addEventListener('pos:seats:change', e => {
        seatItems = (e.detail?.seats || []).map(s => ({
            id: s.id, name: s.label || (s.row + '' + s.number), type: s.type || 'Thường', price: s.price || 0, student_price: s.student_price || 0, row: s.row, number: s.number
        }));
        const activeIds = new Set(seatItems.map(seat => Number(seat.id)));
        for (const seatId of ticketClassifications.keys()) {
            if (!activeIds.has(Number(seatId))) ticketClassifications.delete(seatId);
        }
        seatItems.forEach(seat => {
            if (!ticketClassifications.has(seat.id)) {
                ticketClassifications.set(seat.id, { audience_type: 'adult', student_card_verified: false });
            }
        });
        refreshCart();
    });

    // ── Listen for customer changes ───────────────────────
    document.addEventListener('pos:customer:change', () => {
        refreshCart();
    });

    // ── Filters ───────────────────────────────────────────
    document.querySelectorAll('.pos-cat-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pos-cat-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeProductCategory = btn.dataset.cat;
            renderProducts();
        });
    });

    // ── Products ──────────────────────────────────────────
    async function loadProducts() {
        productGrid.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div> Đang tải...</div>';
        try {
            const res = await api.get('/api/v1/pos/catalog');
            const payload = res.data?.data ?? res.data ?? {};
            const combos = Array.isArray(payload.combos) ? payload.combos : [];
            const prods = Array.isArray(payload.products) ? payload.products : [];
            products = [...combos.map(c => ({ ...c, _type: 'combo' })), ...prods.map(p => ({ ...p, _type: 'product' }))].filter(p => p.status !== 0);
            renderProducts();
        } catch (err) {
            // Fallback: try public API
            try {
                const res = await api.get('/api/v1/products?per_page=50');
                const pList = Array.isArray(res.data) ? res.data : (res.data?.data || []);
                products = pList.map(p => ({
                    ...p, 
                    _type: p.type === 'combo' ? 'combo' : 'product'
                })).filter(p => p.status !== 0 && p.available !== false);
                renderProducts();
            } catch (e) {
                productGrid.innerHTML = '<div class="pos-empty"><div class="pos-empty-icon">🍿</div><div class="pos-empty-text">Không thể tải sản phẩm</div></div>';
            }
        }
    }

    function renderProducts() {
        const filtered = products.filter(p => {
            if (activeProductCategory === 'all') return true;
            return p._type === activeProductCategory || p.type === activeProductCategory;
        });

        if (!filtered.length) {
            productGrid.innerHTML = '<div class="pos-empty"><div class="pos-empty-icon">🍿</div><div class="pos-empty-text">Chưa có sản phẩm</div></div>';
            return;
        }
        let html = '';
        filtered.forEach(p => {
            const inCart = productItems.find(pi => pi.id === p.id && pi._type === p._type);
            const qty = inCart ? inCart.quantity : 0;
            const imgHtml = p.image_url 
                ? `<img class="pos-product-img" src="${p.image_url}" alt="${p.name}" data-product-image>
                   <div class="pos-product-img product-image-fallback d-none"><i class="bi bi-box2-heart"></i></div>`
                : `<div class="pos-product-img product-image-fallback"><i class="bi bi-box2-heart"></i></div>`;
            html += `
            <div class="pos-product-card ${qty > 0 ? 'active' : ''}" data-product-id="${p.id}" data-product-type="${p._type}">
                ${imgHtml}
                <div class="pos-product-info">
                    <div class="pos-product-header">
                        <div class="pos-product-name">${p.name}</div>
                        <div class="pos-product-price">${formatVnd(p.price || p.total_price || 0)}</div>
                    </div>
                    <div class="pos-qty-controls mt-2">
                        <button class="pos-qty-btn product-grid-btn" data-product-id="${p.id}" data-product-type="${p._type}" data-delta="-1">−</button>
                        <span class="pos-qty-val">${qty}</span>
                        <button class="pos-qty-btn add product-grid-btn" data-product-id="${p.id}" data-product-type="${p._type}" data-delta="1">+</button>
                    </div>
                </div>
            </div>`;
        });
        productGrid.innerHTML = html;
        productGrid.querySelectorAll('[data-product-image]').forEach((image) => {
            image.addEventListener('error', () => {
                image.classList.add('d-none');
                image.nextElementSibling?.classList.remove('d-none');
            }, { once: true });
        });
        // NOTE: click events are handled by single delegation listener below (not re-bound here)
    }

    function removeProduct(productId, productType) {
        productItems = productItems.filter(pi => !(pi.id === productId && pi._type === productType));
        renderProducts();
        refreshCart();
    }

    function changeQty(productId, productType, delta) {
        let item = productItems.find(pi => pi.id === productId && pi._type === productType);
        if (!item) {
            if (delta <= 0) return;
            const p = products.find(pr => pr.id === productId && pr._type === productType);
            if (!p) return;
            item = { id: p.id, name: p.name, price: p.price || p.total_price || 0, quantity: 0, _type: p._type || 'product' };
            productItems.push(item);
        }
        item.quantity = Math.max(0, item.quantity + delta);
        if (item.quantity <= 0) return removeProduct(productId, productType);
        renderProducts();
        refreshCart();
    }

    // Single event delegation on productGrid – bound ONCE, never re-bound on render
    if (productGrid) {
        productGrid.addEventListener('click', (e) => {
            const btn = e.target.closest('.product-grid-btn');
            if (!btn) return;
            e.stopPropagation();
            changeQty(parseInt(btn.dataset.productId, 10), btn.dataset.productType, parseInt(btn.dataset.delta, 10));
        });
    }

    // ── Refresh cart ──────────────────────────────────────
    if (seatItemsEl) {
        seatItemsEl.addEventListener('change', event => {
            const typeSelect = event.target.closest('.pos-ticket-type');
            const verifyInput = event.target.closest('.pos-student-verify');
            const seatId = Number((typeSelect || verifyInput)?.dataset.seatId);
            if (!seatId) return;

            const current = getTicketClassification(seatId);
            if (typeSelect) {
                current.audience_type = typeSelect.value;
                if (current.audience_type !== 'student') current.student_card_verified = false;
            } else {
                current.student_card_verified = verifyInput.checked;
            }
            ticketClassifications.set(seatId, current);
            refreshCart();
        });

        seatItemsEl.addEventListener('click', async event => {
            const removeButton = event.target.closest('.pos-ticket-remove');
            if (!removeButton) return;
            removeButton.disabled = true;
            await global.PosApp?.removeTicket?.(Number(removeButton.dataset.seatId));
        });
    }

    function getTicketClassification(seatId) {
        return ticketClassifications.get(seatId) || { audience_type: 'adult', student_card_verified: false };
    }

    function getTicketPrice(seat) {
        const classification = getTicketClassification(seat.id);
        return classification.audience_type === 'adult'
            ? Number(seat.price || 0)
            : Number(seat.student_price || seat.price || 0);
    }

    function renderTicketControls(seat) {
        const classification = getTicketClassification(seat.id);
        const verified = classification.student_card_verified ? 'checked' : '';
        return `<div class="pos-ticket-controls">
            <select class="pos-ticket-type" data-seat-id="${seat.id}" aria-label="Loại khách cho ghế ${seat.name}">
                <option value="adult" ${classification.audience_type === 'adult' ? 'selected' : ''}>Người lớn</option>
                <option value="student" ${classification.audience_type === 'student' ? 'selected' : ''}>Học sinh / Sinh viên</option>
                <option value="child" ${classification.audience_type === 'child' ? 'selected' : ''}>Trẻ em</option>
                <option value="senior" ${classification.audience_type === 'senior' ? 'selected' : ''}>Người cao tuổi</option>
            </select>
            ${classification.audience_type === 'student' ? `<label class="pos-ticket-verify"><input type="checkbox" class="pos-student-verify" data-seat-id="${seat.id}" ${verified}> Đã xem thẻ</label>` : ''}
        </div>`;
    }

    function refreshCart() {
        const pointsToRedeem = global.PosCustomer?.getPointsToRedeem?.() || 0;

        const hasItems = seatItems.length > 0 || productItems.length > 0;

        // Calculate subtotal (Adult base)
        const seatTotalNormal = seatItems.reduce((sum, s) => sum + (s.price || 0), 0);
        const seatTotal = seatItems.reduce((sum, seat) => sum + getTicketPrice(seat), 0);
        const productTotal = productItems.reduce((sum, p) => sum + (p.price * p.quantity), 0);
        
        subtotal = seatTotalNormal + productTotal;

        // Discounts
        discounts.student = Math.max(0, seatTotalNormal - seatTotal);
        discounts.loyalty = pointsToRedeem * cfg.pointsToVnd;
        discounts.voucher = 0;

        grandTotal = Math.max(0, subtotal - discounts.student - discounts.loyalty - discounts.voucher);
        earnedPoints = Math.floor(grandTotal / cfg.earnRate);

        // Visibility (add null checks)
        if (cartEmpty) cartEmpty.style.display = hasItems ? 'none' : 'block';
        if (seatsSection) seatsSection.style.display = seatItems.length ? 'block' : 'none';
        if (productsSection) productsSection.style.display = productItems.length ? 'block' : 'none';
        if (summaryEl) summaryEl.style.display = hasItems ? 'block' : 'none';
        if (cartFooter) cartFooter.style.display = hasItems ? 'flex' : 'none';
        if (discountSection) discountSection.style.display = (discounts.loyalty > 0 || discounts.student > 0) ? 'block' : 'none';

        // Badge
        if (cartBadge) cartBadge.textContent = seatItems.length + productItems.length;

        // Seat items
        let seatHtml = '';
        seatItems.forEach(s => {
            seatHtml += `<div class="pos-cart-item">
                <div class="pos-cart-item-icon seat"><i class="bi bi-ticket-perforated"></i></div>
                <div class="pos-cart-item-main">
                    <div class="pos-cart-item-name">${s.name}</div>
                    <div class="pos-cart-item-sub">${s.type}</div>
                    ${renderTicketControls(s)}
                </div>
                <div class="pos-cart-item-price">${formatVnd(getTicketPrice(s))}</div>
                <button class="pos-cart-remove pos-ticket-remove" data-seat-id="${s.id}" type="button" title="Bỏ vé" aria-label="Bỏ vé ghế ${s.name}">×</button>
            </div>`;
        });
        if (seatItemsEl) seatItemsEl.innerHTML = seatHtml;

        // Product items
        let prodHtml = '';
        productItems.forEach(p => {
            prodHtml += `<div class="pos-cart-item">
                <div class="pos-cart-item-icon product"><i class="bi bi-cup-straw"></i></div>
                <div class="pos-cart-item-main"><div class="pos-cart-item-name">${p.name}</div>
                    <div class="pos-qty-ctrl">
                        <button class="pos-qty-btn" data-product-id="${p.id}" data-delta="-1" type="button">−</button>
                        <span class="pos-qty-val">${p.quantity}</span>
                        <button class="pos-qty-btn" data-product-id="${p.id}" data-delta="1" type="button">+</button>
                    </div>
                </div>
                <div class="pos-cart-item-price">${formatVnd(p.price * p.quantity)}</div>
                <button class="pos-cart-remove" data-product-id="${p.id}" type="button" title="Xóa">×</button>
            </div>`;
        });
        if (productItemsEl) {
            productItemsEl.innerHTML = prodHtml;

            // Bind qty buttons
            productItemsEl.querySelectorAll('.pos-qty-btn').forEach(btn => {
                btn.addEventListener('click', () => changeQty(parseInt(btn.dataset.productId, 10), parseInt(btn.dataset.delta, 10)));
            });
            productItemsEl.querySelectorAll('.pos-cart-remove').forEach(btn => {
                btn.addEventListener('click', () => removeProduct(parseInt(btn.dataset.productId, 10)));
            });
        }

        // Discounts
        let discHtml = '';
        if (discounts.student > 0) {
            discHtml += `<div class="pos-discount-item"><span class="pos-discount-label">🎓 Ưu đãi sinh viên</span><span class="pos-discount-amount">-${formatVnd(discounts.student)}</span></div>`;
        }
        if (discounts.loyalty > 0) {
            discHtml += `<div class="pos-discount-item"><span class="pos-discount-label">⭐ ${pointsToRedeem} điểm</span><span class="pos-discount-amount">-${formatVnd(discounts.loyalty)}</span></div>`;
        }
        if (discountItemsEl) discountItemsEl.innerHTML = discHtml;

        // Summary
        if (subTotalEl) subTotalEl.textContent = formatVnd(subtotal);
        if (studentRowEl) studentRowEl.style.display = discounts.student > 0 ? 'flex' : 'none';
        if (studentDiscEl) studentDiscEl.textContent = '-' + formatVnd(discounts.student);
        if (pointsRowEl) pointsRowEl.style.display = discounts.loyalty > 0 ? 'flex' : 'none';
        if (pointsDiscEl) pointsDiscEl.textContent = '-' + formatVnd(discounts.loyalty);
        if (totalEl) totalEl.textContent = formatVnd(grandTotal);
        const footerTotalEl = document.getElementById('footerTotal');
        if (footerTotalEl) footerTotalEl.textContent = formatVnd(grandTotal);
        if (earnPointsEl) earnPointsEl.textContent = earnedPoints;

        // Notify
        document.dispatchEvent(new CustomEvent('pos:cart:change', {
            detail: { seatItems, productItems, subtotal, grandTotal, discounts, earnedPoints }
        }));
    }

    function reset() {
        seatItems = []; productItems = []; discounts = { student: 0, loyalty: 0, voucher: 0 };
        ticketClassifications.clear();
        subtotal = 0; grandTotal = 0; earnedPoints = 0;
        refreshCart();
    }

    function getOrderData() {
        return {
            seatItems,
            productItems,
            subtotal,
            grandTotal,
            discounts,
            earnedPoints,
            ticketClassifications: Object.fromEntries(ticketClassifications),
        };
    }

    global.PosCart = { loadProducts, refreshCart, getOrderData, reset };

})(window);
