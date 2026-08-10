/**
 * pos-customer.js – Customer lookup, quick POS profile creation, loyalty points selection
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;
    const { api, formatVnd, phoneNormalize, initials, toast } = global.PosUtils;

    let currentCustomer = null;
    let pendingCustomer = null;
    let pendingType     = 'returning';
    let isStudent       = false;
    let pointsToRedeem  = 0;
    let loyaltyInfo     = { points: 0, value_vnd: 0, can_redeem: false };

    // ── DOM refs ──────────────────────────────────────────
    const phoneInput        = document.getElementById('customerPhone');
    const btnLookup         = document.getElementById('btnLookup');
    const btnWalkIn         = document.getElementById('btnWalkIn');
    const newCustomerForm   = document.getElementById('newCustomerForm');
    const nameInput         = document.getElementById('customerName');
    const btnCreate         = document.getElementById('btnCreateCustomer');
    const customerCard      = document.getElementById('customerCard');
    const avatarEl          = document.getElementById('customerAvatar');
    const nameEl            = document.getElementById('customerDisplayName');
    const phoneEl           = document.getElementById('customerDisplayPhone');
    const tagEl             = document.getElementById('customerTypeTag');
    const customerActionRow = document.getElementById('customerActionRow');
    const btnSelectCustomer = document.getElementById('btnSelectCustomer');
    const btnChangeCustomer = document.getElementById('btnChangeCustomer');
    const loyaltyBar        = document.getElementById('loyaltyBar');
    const loyaltyPtsEl      = document.getElementById('loyaltyPoints');
    const loyaltyVndEl      = document.getElementById('loyaltyValueVnd');
    const pointsRow         = document.getElementById('pointsRedeemRow');
    const pointsInput       = document.getElementById('pointsToUse');
    const pointsDisplay     = document.getElementById('pointsDiscountDisplay');
    const btnApplyPoints    = document.getElementById('btnApplyPoints');
    const studentToggle     = document.getElementById('isStudentToggleVisible') || document.getElementById('isStudentToggle');
    const btnNext           = document.getElementById('btnStep1Next');

    // ── Lookup ────────────────────────────────────────────
    async function lookupCustomer() {
        const raw   = phoneInput.value.trim();
        const phone = phoneNormalize(raw);
        if (!phone || phone.length < 9) { toast('Vui lòng nhập số điện thoại hợp lệ', 'warning'); return; }

        btnLookup.disabled = true;
        btnLookup.setAttribute('aria-busy', 'true');
        btnLookup.innerHTML = '<div class="pos-spinner"></div>';

        try {
            const res = await api.post(`${cfg.apiBase}/customers/lookup`, { phone });
            if (res.data) {
                previewCustomer(res.data, res.data.is_new ? 'new' : 'returning');
                toast(`Tìm thấy khách hàng ${res.data.name}`, 'info');
            } else {
                showNewCustomerForm(phone);
            }
        } catch (err) {
            if (err.status === 404) {
                showNewCustomerForm(phone);
            } else {
                toast(err.message || 'Lỗi tìm kiếm khách hàng', 'error');
            }
        } finally {
            btnLookup.disabled = false;
            btnLookup.setAttribute('aria-busy', 'false');
            btnLookup.innerHTML = '<i class="bi bi-search"></i> Tìm';
        }
    }

    function showNewCustomerForm(phone) {
        phoneInput.value = phone;
        newCustomerForm.style.display = 'flex';
        customerCard.style.display = 'none';
        nameInput.focus();
        toast('Khách hàng mới – vui lòng nhập Họ tên để tích điểm', 'info');
    }

    async function createCustomer() {
        const phone = phoneNormalize(phoneInput.value.trim());
        const name  = nameInput.value.trim();
        if (!name) { toast('Vui lòng nhập tên khách hàng', 'warning'); return; }

        btnCreate.disabled = true;
        btnCreate.setAttribute('aria-busy', 'true');
        try {
            const res = await api.post(`${cfg.apiBase}/customers/lookup`, { phone, name });
            if (res.data) {
                confirmSelectCustomer(res.data, 'new');
                newCustomerForm.style.display = 'none';
                toast('Tạo hồ sơ POS & chọn tích điểm thành công!', 'success');
            }
        } catch (err) {
            toast(err.message || 'Lỗi tạo khách hàng', 'error');
        } finally {
            btnCreate.disabled = false;
            btnCreate.setAttribute('aria-busy', 'false');
        }
    }

    function walkInCustomer() {
        reset();
        toast('Tiếp tục với Khách vãng lai', 'info');
    }

    // ── Preview & Confirm Customer Selection ──────────────
    function previewCustomer(customer, type) {
        pendingCustomer = customer;
        pendingType     = type;

        customerCard.style.display = 'block';
        newCustomerForm.style.display = 'none';
        customerCard.classList.remove('new-customer', 'returning');

        const initStr = initials(customer.name);
        avatarEl.textContent = initStr;
        nameEl.textContent   = customer.name;
        phoneEl.textContent  = customer.phone || '–';

        if (type === 'new') {
            avatarEl.className = 'pos-customer-avatar new';
            tagEl.textContent  = 'Khách mới POS';
            tagEl.className    = 'pos-customer-tag admin-badge admin-badge-info new';
            customerCard.classList.add('new-customer');
        } else {
            avatarEl.className = 'pos-customer-avatar returning';
            tagEl.textContent  = customer.account_status === 'unclaimed' ? 'Khách POS' : 'Khách cũ';
            tagEl.className    = 'pos-customer-tag admin-badge admin-badge-success returning';
            customerCard.classList.add('returning');
        }

        // Show "CHỌN KHÁCH HÀNG NÀY" action row
        if (customerActionRow) customerActionRow.style.display = 'flex';
        if (btnChangeCustomer) btnChangeCustomer.style.display = 'none';

        // Hide loyalty redemption until selected
        if (loyaltyBar)   loyaltyBar.style.display = 'none';
        if (pointsRow)    pointsRow.style.display  = 'none';
    }

    function confirmSelectCustomer(customer, type) {
        currentCustomer = customer || pendingCustomer;
        if (!currentCustomer) return;

        isStudent = false;
        pointsToRedeem = 0;
        loyaltyInfo = {
            points:    currentCustomer.loyalty_points || 0,
            value_vnd: (currentCustomer.loyalty_points || 0) * cfg.pointsToVnd,
            can_redeem: (currentCustomer.loyalty_points || 0) >= 10,
        };

        // UI update
        customerCard.style.display = 'block';
        newCustomerForm.style.display = 'none';
        if (customerActionRow) customerActionRow.style.display = 'none';
        if (btnChangeCustomer) btnChangeCustomer.style.display = 'inline-flex';

        const initStr = initials(currentCustomer.name);
        avatarEl.textContent = initStr;
        nameEl.textContent   = currentCustomer.name;
        phoneEl.textContent  = currentCustomer.phone || '–';

        // Lock phone input when selected
        if (phoneInput) {
            phoneInput.value = currentCustomer.phone || '';
            phoneInput.disabled = true;
        }

        // Loyalty controls
        if (loyaltyInfo.points > 0) {
            if (loyaltyBar) {
                loyaltyBar.style.display = 'flex';
                loyaltyPtsEl.textContent = loyaltyInfo.points.toLocaleString('vi-VN');
                loyaltyVndEl.textContent = '= ' + formatVnd(loyaltyInfo.value_vnd);
            }
            if (pointsRow) {
                pointsRow.style.display = loyaltyInfo.can_redeem ? 'grid' : 'none';
                if (pointsInput) pointsInput.max = loyaltyInfo.points;
            }
        } else {
            if (loyaltyBar)  loyaltyBar.style.display = 'none';
            if (pointsRow)   pointsRow.style.display  = 'none';
        }

        if (studentToggle) studentToggle.checked = false;
        if (pointsInput)   pointsInput.value     = 0;
        pointsToRedeem = 0;

        if (btnNext) btnNext.disabled = false;

        toast(`Đã chọn tích điểm cho khách: ${currentCustomer.name}`, 'success');
        notifyChange();
    }

    // ── Student toggle ────────────────────────────────────
    if (studentToggle) {
        studentToggle.addEventListener('change', () => {
            isStudent = studentToggle.checked;
            notifyChange();
        });
    }

    // ── Points ────────────────────────────────────────────
    if (pointsInput) {
        pointsInput.addEventListener('input', () => {
            const val = Math.min(parseInt(pointsInput.value, 10) || 0, loyaltyInfo.points);
            pointsInput.value       = val;
            if (pointsDisplay) pointsDisplay.textContent = '= ' + formatVnd(val * cfg.pointsToVnd);
        });
    }

    if (btnApplyPoints) {
        btnApplyPoints.addEventListener('click', () => {
            pointsToRedeem = Math.min(parseInt(pointsInput.value, 10) || 0, loyaltyInfo.points);
            notifyChange();

            const appliedPoints = global.PosCart?.getOrderData?.().loyaltyPointsApplied;
            if (Number.isInteger(appliedPoints) && appliedPoints < pointsToRedeem) {
                pointsToRedeem = appliedPoints;
                pointsInput.value = appliedPoints;
                if (pointsDisplay) pointsDisplay.textContent = '= ' + formatVnd(appliedPoints * cfg.pointsToVnd);
                notifyChange();
            }

            toast(`Áp dụng ${pointsToRedeem} điểm (${formatVnd(pointsToRedeem * cfg.pointsToVnd)})`, 'success');
        });
    }

    // ── Event bindings ────────────────────────────────────
    if (btnLookup)         btnLookup.addEventListener('click', lookupCustomer);
    if (btnWalkIn)         btnWalkIn.addEventListener('click', walkInCustomer);
    if (btnCreate)         btnCreate.addEventListener('click', createCustomer);
    if (btnSelectCustomer) btnSelectCustomer.addEventListener('click', () => confirmSelectCustomer(pendingCustomer, pendingType));
    if (btnChangeCustomer) btnChangeCustomer.addEventListener('click', walkInCustomer);
    if (phoneInput)        phoneInput.addEventListener('keydown', e => { if (e.key === 'Enter') lookupCustomer(); });

    // ── Notify cart & other modules ───────────────────────
    function notifyChange() {
        document.dispatchEvent(new CustomEvent('pos:customer:change', {
            detail: { customer: currentCustomer, isStudent, pointsToRedeem }
        }));
    }

    // ── Reset ─────────────────────────────────────────────
    function reset() {
        currentCustomer = null;
        pendingCustomer = null;
        isStudent = false;
        pointsToRedeem = 0;
        loyaltyInfo = { points: 0, value_vnd: 0, can_redeem: false };

        if (phoneInput) {
            phoneInput.value = '';
            phoneInput.disabled = false;
        }
        if (nameInput) nameInput.value = '';

        if (customerCard) {
            customerCard.style.display = 'none';
            customerCard.classList.remove('new-customer', 'returning');
        }
        if (newCustomerForm)   newCustomerForm.style.display   = 'none';
        if (customerActionRow) customerActionRow.style.display = 'none';
        if (btnChangeCustomer) btnChangeCustomer.style.display = 'none';
        if (loyaltyBar)        loyaltyBar.style.display        = 'none';
        if (pointsRow)         pointsRow.style.display         = 'none';

        if (studentToggle) studentToggle.checked = false;
        if (pointsInput)   pointsInput.value     = 0;
        if (btnNext)       btnNext.disabled      = true;

        notifyChange();
    }

    // ── Public API ────────────────────────────────────────
    global.PosCustomer = {
        getCustomer:       () => currentCustomer,
        isStudent:         () => isStudent,
        getPointsToRedeem: () => pointsToRedeem,
        getLoyaltyInfo:    () => loyaltyInfo,
        reset,
    };

})(window);
