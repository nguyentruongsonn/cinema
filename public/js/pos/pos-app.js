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
        "",
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

    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;
        currentStep = step;

        const progressEl = document.getElementById('stepperProgress');
        if (progressEl) {
            const percentage = ((step - 1) / (totalSteps - 1)) * 100;
            progressEl.style.width = percentage + '%';
        }

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

        const btnNext = document.getElementById('btnFooterNext');
        const btnBack = document.getElementById('btnFooterBack');
        
        if (btnNext) {
            const nextLabel = step === 1 && !global.PosShowtime?.getSelected?.()
                ? 'CHỌN BẮP NƯỚC'
                : nextButtonLabels[step];
            btnNext.innerHTML = `${nextLabel} <i class="bi bi-arrow-right"></i>`;
            btnNext.disabled = true; 
        }

        if (btnBack) {
            btnBack.style.display = step > 1 ? 'inline-block' : 'none';
        }

        if (step === 2) {
            const showtime = global.PosShowtime?.getSelected?.();
            if (showtime) {
                global.PosSeat?.load?.(showtime.id);
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
            if (btnNext) btnNext.disabled = false;
        }
        if (step === 4) {
            global.PosCart?.updateCartUI?.();
            if (btnNext) btnNext.disabled = false;
        }
        
        checkNextButtonState();
    }

    function setupNavigation() {
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
                        prevStep = 1;
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
                            if (global.PosUtils && global.PosUtils.toast) {
                                global.PosUtils.toast('Đã giữ ghế thành công', 'success');
                            }
                            btnNext.disabled = false;
                            btnNext.innerHTML = `${nextButtonLabels[currentStep]} <i class="bi bi-arrow-right"></i>`;
                        } catch (err) {
                            if (global.PosUtils && global.PosUtils.toast) {
                                global.PosUtils.toast(err.message || 'Không thể giữ ghế!', 'error');
                            }
                            btnNext.disabled = false;
                            btnNext.innerHTML = `${nextButtonLabels[currentStep]} <i class="bi bi-arrow-right"></i>`;
                            return;
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
                        nextStep = 3;
                    }
                    goToStep(nextStep);
                } else if (currentStep === totalSteps) {
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
            global.PosApp.enableNext();
        } else if (currentStep === 2) {
            const seats = global.PosSeat?.getSelectedSeats?.() || [];
            if (seats.length > 0) global.PosApp.enableNext();
            else global.PosApp.disableNext();
        } else if (currentStep === 3) {
            global.PosApp.enableNext();
        } else if (currentStep === 4) {
            global.PosApp.enableNext();
        }
    }

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

    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            try {
                await global.PosUtils.api.post(`${cfg.authBase}/logout`);
            } catch (e) {   }
            document.cookie.split(';').forEach(c => {
                const name = c.trim().split('=')[0];
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            });
            localStorage.removeItem('access_token');
            localStorage.removeItem('pos_access_token');
            window.location.href = '/login';
        });
    }

    if (typeof global.onAdminPageLoad === 'function') {
        global.onAdminPageLoad(init);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

})(window);
