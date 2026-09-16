/**
 * pos-app.js – Main POS Kiosk controller
 * Orchestrates steps, navigation, and module initialization
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;
    const { toast } = global.PosUtils;

    let currentStep = 1;
    const totalSteps = 4;
    let currentHoldId = null;
    let concessionTheatersLoaded = false;

    function getConcessionTheaterId() {
        const select = document.getElementById('concessionTheater');
        const theaterId = Number(select?.value || 0);

        return theaterId > 0 ? theaterId : null;
    }

    function updateConcessionContext() {
        const select = document.getElementById('concessionTheater');
        if (!select) return;

        const showtime = global.PosShowtime?.getSelected?.();
        const theaterId = Number(showtime?.screen?.theater_id || showtime?.theater_id || 0);

        if (showtime) {
            if (theaterId > 0) select.value = String(theaterId);
            select.disabled = true;
            return;
        }

        select.disabled = false;
    }

    async function loadConcessionTheaters() {
        const select = document.getElementById('concessionTheater');
        if (!select || concessionTheatersLoaded) return;

        select.disabled = true;

        try {
            const response = await global.PosUtils.api.get(`${cfg.apiBase}/theaters`);
            const payload = response?.data?.data ?? response?.data ?? [];
            const theaters = Array.isArray(payload) ? payload : [];

            select.replaceChildren();

            if (theaters.length > 1) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Chọn rạp phục vụ';
                select.appendChild(placeholder);
            }

            theaters.forEach((theater) => {
                const option = document.createElement('option');
                option.value = String(Number(theater.id));
                option.textContent = String(theater.name ?? '');
                select.appendChild(option);
            });

            if (theaters.length === 1) {
                select.value = String(theaters[0].id);
            }

            concessionTheatersLoaded = true;
            updateConcessionContext();
        } catch (error) {
            select.innerHTML = '<option value="">Không thể tải rạp phục vụ</option>';
            toast(error.message || 'Không thể tải rạp phục vụ', 'error');
        } finally {
            select.disabled = false;
        }
    }

    const stepNames = [
        "", // padding
        "Movie",
        "Seat",
        "Snacks",
        "Payment"
    ];

    const nextButtonLabels = [
        "",
        "CHỌN GHẾ",
        "TIẾP TỤC CHỌN BẮP NƯỚC",
        "TIẾN HÀNH THANH TOÁN",
        "XÁC NHẬN ĐƠN HÀNG"
    ];

    // ── Helpers ──────────────────────────────────────────────────────────
    async function unlockSeats() {
        if (!currentHoldId) return;
        try {
            const response = await global.PosUtils.api.delete(`/api/v1/seats/unlock/${currentHoldId}`);
            const payload = response?.data || response || {};
            setCurrentHoldId(null);
            global.PosSeat?.clearSelection?.();
            if (global.PosUtils?.toast) {
                global.PosUtils.toast(`Đã hủy giữ ${payload.released_seat_ids?.length || 0} ghế`, 'info');
            }
            return payload;
        } catch (e) {
            console.error('Lỗi hủy giữ ghế:', e);
            toast(e.message || 'Không thể hủy giữ ghế', 'error');
            throw e;
        }
    }

    // ── Init ─────────────────────────────────────────────────────────────
    async function removeTicket(seatId) {
        const remainingSeats = (global.PosSeat?.getSelectedSeats?.() || [])
            .filter(seat => Number(seat.id) !== Number(seatId));

        if (!currentHoldId) {
            global.PosSeat?.removeSeat?.(seatId);
            return true;
        }

        try {
            const response = await global.PosUtils.api.put(`/api/v1/pos/seat-holds/${currentHoldId}`, {
                seat_ids: remainingSeats.map(seat => seat.id),
            });
            const payload = response?.data || response || {};
            setCurrentHoldId(remainingSeats.length ? (payload.hold_id || currentHoldId) : null);
            global.PosSeat?.removeSeat?.(seatId);
            return true;
        } catch (error) {
            toast(error.message || 'Không thể bỏ vé này', 'error');
            return false;
        }
    }

    let initializedRoot = null;

    function init() {
        const root = document.querySelector('.pos-integrated-container');
        if (!root || initializedRoot === root) return;
        initializedRoot = root;

        setupNavigation();
        setupFooterButtons();
        setupResetListener();
        loadConcessionTheaters();
        
        // Initial load for Step 1
        if (global.PosShowtime?.load) {
            global.PosShowtime.load();
        }
        
        goToStep(1);

        if (typeof global.onAdminPageCleanup === 'function') {
            global.onAdminPageCleanup(() => {
                if (initializedRoot !== root) return;
                initializedRoot = null;
                global.PosSeat?.reset?.();
                global.PosPayment?.reset?.();
                global.AdminCore?.abortAllRequests?.();
            });
        }
    }

    function clearHold() {
        setCurrentHoldId(null);
    }

    function setCurrentHoldId(holdId) {
        currentHoldId = Number(holdId) > 0 ? Number(holdId) : null;
    }

    async function releaseTransactionHold() {
        if (currentHoldId) {
            await unlockSeats();
        }
    }

    // ── Step navigation ──────────────────────────────────────────────────
    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;
        currentStep = step;

        // Update stepper progress bar
        const progressEl = document.getElementById('stepperProgress');
        if (progressEl) {
            // totalSteps = 4, so steps 1, 2, 3, 4 map to 0%, 33.3%, 66.6%, 100%
            const percentage = ((step - 1) / (totalSteps - 1)) * 100;
            progressEl.style.width = percentage + '%';
        }

        // Update stepper UI (if stepper exists)
        for (let i = 1; i <= totalSteps; i++) {
            const tab = document.getElementById(`tab-step-${i}`);
            const panel = document.getElementById(`panel-step-${i}`);

            if (tab) {
                tab.classList.remove('active', 'active-prev');
                if (i < step) tab.classList.add('active-prev');
                if (i === step) tab.classList.add('active');
            }

            if (panel) {
                panel.hidden = i !== step;
            }
        }

        // Update footer buttons
        const btnNext = document.getElementById('btnFooterNext');
        const btnBack = document.getElementById('btnFooterBack');
        
        if (btnNext) {
            const nextLabel = step === 1 && !global.PosShowtime?.getSelected?.()
                ? 'CHỌN BẮP NƯỚC'
                : nextButtonLabels[step];
            btnNext.innerHTML = `${nextLabel} <i class="bi bi-arrow-right"></i>`;
            // Enable/disable logic should be handled by individual modules (pos-showtime, etc.), 
            // but we reset it defensively when changing steps
            btnNext.disabled = true; 
        }

        if (btnBack) {
            btnBack.style.display = step > 1 ? 'inline-block' : 'none';
        }

        // Trigger data load for specific steps
        if (step === 2) {
            const showtime = global.PosShowtime?.getSelected?.();
            if (showtime) {
                global.PosSeat?.load?.(showtime.id);
                // Also update the sidebar info
                const seatMovieInfo = document.getElementById('seatMovieInfo');
                if (seatMovieInfo) {
                    const poster = showtime.movie_poster || '';
                    seatMovieInfo.innerHTML = `
                        <img src="${poster}" class="pos-movie-poster selected pos-seat-movie-poster" alt="Poster">
                        <h3 class="pos-seat-movie-title">${showtime.movie_title || ''}</h3>
                        <p class="pos-seat-movie-room">${showtime.room_name || ''}</p>
                        <p class="pos-seat-movie-time">${showtime.start_time || ''}</p>
                    `;
                }
            }
        }
        if (step === 3) {
            updateConcessionContext();
            if (!global.PosShowtime?.getSelected?.()) {
                loadConcessionTheaters();
            }
            global.PosCart?.loadProducts?.();
            if (btnNext) btnNext.disabled = false; // Snacks are optional
        }
        if (step === 4) {
            // Render cart summary
            global.PosCart?.updateCartUI?.();
            if (btnNext) btnNext.disabled = false; // Payment can always be clicked if there are items
        }
        
        // Re-evaluate next button state
        checkNextButtonState();
    }

    function setupNavigation() {
        // Allow clicking on previous tabs to go back
        for (let i = 1; i <= totalSteps; i++) {
            const tab = document.getElementById(`tab-step-${i}`);
            if (tab) {
                tab.addEventListener('click', async () => {
                    if (i < currentStep) {
                        goToStep(i);
                    }
                });
            }
        }
    }

    function setupFooterButtons() {
        const btnNext = document.getElementById('btnFooterNext');
        const btnBack = document.getElementById('btnFooterBack');
        
        if (btnBack) {
            btnBack.addEventListener('click', async () => {
                if (currentStep > 1) {
                    let prevStep = currentStep - 1;
                    if (currentStep === 3 && !global.PosShowtime?.getSelected?.()) {
                        prevStep = 1; // Skip seat selection when going back
                    }
                    goToStep(prevStep);
                }
            });
        }
        
        if (btnNext) {
            btnNext.addEventListener('click', async () => {
                if (currentStep === 2) {
                    const orderData = global.PosCart?.getOrderData?.();
                    const showtime = global.PosShowtime?.getSelected?.();
                    
                    if (orderData && orderData.seatItems && orderData.seatItems.length > 0 && showtime) {
                        try {
                            btnNext.disabled = true;
                            btnNext.innerHTML = '<span class="spinner-border spinner-border-sm pos-inline-spinner" role="status" aria-hidden="true"></span> Đang giữ ghế...';
                            
                            const seatIds = orderData.seatItems.map(s => s.id);
                            const res = await global.PosUtils.api.post('/api/v1/seats/lock', {
                                showtime_id: showtime.id,
                                seat_ids: seatIds
                            });
                            if (res.data?.hold_id) {
                                setCurrentHoldId(res.data.hold_id);
                            }
                            // Lock successful
                            if (global.PosUtils && global.PosUtils.toast) {
                                global.PosUtils.toast('Đã giữ ghế thành công', 'success');
                            }
                            // Re-enable and reset button immediately so it doesn't stay stuck disabled during UI transition
                            btnNext.disabled = false;
                            btnNext.innerHTML = `${nextButtonLabels[currentStep]} <i class="bi bi-arrow-right"></i>`;
                        } catch (err) {
                            if (global.PosUtils && global.PosUtils.toast) {
                                global.PosUtils.toast(err.message || 'Không thể giữ ghế!', 'error');
                            }
                            btnNext.disabled = false;
                            btnNext.innerHTML = `${nextButtonLabels[currentStep]} <i class="bi bi-arrow-right"></i>`;
                            return; // Block progression
                        }
                    }
                }

                if (currentStep === 3 && !global.PosShowtime?.getSelected?.()) {
                    const cart = global.PosCart?.getOrderData?.() || {};
                    if (!(cart.productItems || []).length) {
                        toast('Vui lòng chọn ít nhất một sản phẩm trước khi thanh toán', 'warning');
                        return;
                    }
                    if (!getConcessionTheaterId()) {
                        toast('Vui lòng chọn rạp phục vụ cho đơn bắp nước', 'warning');
                        document.getElementById('concessionTheater')?.focus();
                        return;
                    }
                }

                if (currentStep < totalSteps) {
                    let nextStep = currentStep + 1;
                    if (currentStep === 1 && !global.PosShowtime?.getSelected?.()) {
                        nextStep = 3; // Skip seat selection
                    }
                    goToStep(nextStep);
                } else if (currentStep === totalSteps) {
                    // Trigger payment logic
                    const btnConfirmOrder = document.getElementById('btnConfirmOrder');
                    if (btnConfirmOrder) {
                        btnConfirmOrder.click();
                    } else {
                        global.PosPayment?.processPayment?.();
                    }
                }
            });
        }
    }

    // Exported function for modules to enable/disable the Next button
    global.PosApp = {
        enableNext: () => {
            const btn = document.getElementById('btnFooterNext');
            if (btn) btn.disabled = false;
        },
        disableNext: () => {
            const btn = document.getElementById('btnFooterNext');
            if (btn) btn.disabled = true;
        },
        getCurrentStep: () => currentStep,
        getConcessionTheaterId,
        getCurrentHoldId: () => currentHoldId,
        removeTicket,
        clearHold,
        releaseTransactionHold,
    };

    document.addEventListener('pos:hold:restored', (event) => {
        setCurrentHoldId(event.detail?.hold_id);
    });

    function checkNextButtonState() {
        if (currentStep === 1) {
            global.PosApp.enableNext(); // Allow skipping movie selection
        } else if (currentStep === 2) {
            const seats = global.PosSeat?.getSelectedSeats?.() || [];
            if (seats.length > 0) global.PosApp.enableNext();
            else global.PosApp.disableNext();
        } else if (currentStep === 3) {
            global.PosApp.enableNext(); // Snacks optional
        } else if (currentStep === 4) {
            global.PosApp.enableNext(); // Can pay
        }
    }

    // Handle custom events emitted by modules
    document.addEventListener('pos:showtime_selected', () => {
        updateConcessionContext();
        if (currentStep === 1) {
            const btnNext = document.getElementById('btnFooterNext');
            if (btnNext) {
                btnNext.innerHTML = 'CHỌN GHẾ <i class="bi bi-arrow-right"></i>';
            }
        }
        checkNextButtonState();
    });
    document.addEventListener('pos:showtime_cleared', () => {
        global.PosSeat?.reset?.();
        updateConcessionContext();
        if (currentStep === 1) {
            const btnNext = document.getElementById('btnFooterNext');
            if (btnNext) {
                btnNext.innerHTML = 'CHỌN BẮP NƯỚC <i class="bi bi-arrow-right"></i>';
            }
        }
        checkNextButtonState();
    });
    document.addEventListener('pos:seats_changed', checkNextButtonState);

    // ── Reset all ────────────────────────────────────────────────────────
    function setupResetListener() {
        document.addEventListener('pos:reset', () => {
            global.PosCustomer?.reset?.();
            global.PosShowtime?.reset?.();
            global.PosSeat?.reset?.();
            global.PosCart?.reset?.();
            global.PosPayment?.reset?.();
            goToStep(1);
            toast('Sẵn sàng giao dịch mới', 'info');
        });
    }

    // ── Logout ───────────────────────────────────────────────────────────
    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            try {
                await global.PosUtils.api.post(`${cfg.authBase}/logout`);
            } catch (e) { /* ignore */ }
            // Clear cookies and storage
            document.cookie.split(';').forEach(c => {
                const name = c.trim().split('=')[0];
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            });
            localStorage.removeItem('access_token');
            localStorage.removeItem('pos_access_token');
            window.location.href = '/login';
        });
    }

    // ── Boot ─────────────────────────────────────────────────────────────
    if (typeof global.onAdminPageLoad === 'function') {
        global.onAdminPageLoad(init);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

})(window);
