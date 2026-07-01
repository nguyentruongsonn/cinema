/**
 * Seat Layout Admin Page — seat-layout.js
 * SPA Architecture
 */
(function () {
    'use strict';

    const config = window.SEAT_LAYOUT_CONFIG || {};
    const screenId = config.screenId;
    if (!screenId) return;

    /* ── DOM Cache ─────────────────────────────────────────────── */
    const els = {
        title: document.getElementById('screenNameTitle'),
        codeBadge: document.getElementById('screenCodeBadge'),
        theaterSpan: document.getElementById('theaterNameSpan'),
        
        seatGridLoading: document.getElementById('seatGridLoading'),
        seatGrid: document.getElementById('seatGrid'),
        colLabels: document.getElementById('seatGridColLabels'),
        
        infoTheater: document.getElementById('infoTheater'),
        infoFormat: document.getElementById('infoFormat'),
        infoSound: document.getElementById('infoSound'),
        infoMatrix: document.getElementById('infoMatrix'),
        capacityCounter: document.getElementById('capacityCounter'),
        totalSeatsCounter: document.getElementById('totalSeatsCounter'),
        
        screenActiveToggle: document.getElementById('screenActiveToggle'),
        btnUpdateSeats: document.getElementById('btnUpdateSeats'),
    };

    let modifiedSeats = {}; 
    let currentScreen = null;

    /* ── Fetch Data ────────────────────────────────────────────── */
    async function loadSeatLayout() {
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${screenId}/seats`);
            if (res && res.ok) {
                const data = await res.json();
                currentScreen = data.screen;
                renderInfo(data.screen, data.seats);
                renderGrid(data.screen, data.seats);
            }
        } catch (error) {
            console.error('Error loading seats:', error);
            els.seatGridLoading.innerHTML = '<div class="text-danger py-4">Lỗi khi tải dữ liệu sơ đồ ghế.</div>';
        }
    }

    /* ── Render Information ────────────────────────────────────── */
    function renderInfo(screen, seats) {
        els.title.textContent = screen.name;
        els.codeBadge.textContent = screen.code;
        
        const theaterName = screen.theater?.name || '—';
        els.theaterSpan.textContent = `| ${theaterName}`;
        els.infoTheater.textContent = theaterName;
        
        els.infoFormat.textContent = screen.format?.name || '—';
        els.infoSound.textContent = screen.sound?.name || '—';
        
        els.screenActiveToggle.checked = screen.status === 1;

        let rows = 0;
        let cols = 0;
        let activeCount = 0;
        
        if (seats && seats.length > 0) {
            rows = Math.max(...seats.map(s => s.row_index)) + 1;
            cols = Math.max(...seats.map(s => s.column_index)) + 1;
            activeCount = seats.filter(s => s.status === 1).length;
        } else {
            const matrixStr = screen.seat_layout_template?.seat_matrix || '12x12';
            [rows, cols] = matrixStr.split('x').map(Number);
        }

        els.infoMatrix.textContent = `${rows}x${cols}`;
        els.capacityCounter.textContent = activeCount;
        els.totalSeatsCounter.textContent = seats.length;
    }

    /* ── Render Grid ───────────────────────────────────────────── */
    function renderGrid(screen, seats) {
        els.seatGridLoading.classList.add('d-none');
        els.seatGrid.classList.remove('d-none');
        els.colLabels.classList.remove('d-none');

        let rows = 0, cols = 0;
        if (seats.length > 0) {
            rows = Math.max(...seats.map(s => s.row_index)) + 1;
            cols = Math.max(...seats.map(s => s.column_index)) + 1;
        } else {
            const matrixStr = screen.seat_layout_template?.seat_matrix || '12x12';
            [rows, cols] = matrixStr.split('x').map(Number);
        }

        // Map seats for O(1) lookup
        const seatMap = {};
        seats.forEach(s => {
            if (!seatMap[s.row_index]) seatMap[s.row_index] = {};
            seatMap[s.row_index][s.column_index] = s;
        });

        const typeClass = {
            'Standard': 'seat-standard',
            'VIP': 'seat-vip',
            'Couple': 'seat-couple',
            'Sweetbox': 'seat-couple',
            'Premium': 'seat-vip',
            'Accessible': 'seat-standard'
        };

        els.seatGrid.style.setProperty('--cols', cols);
        els.seatGrid.innerHTML = '';

        for (let r = 0; r < rows; r++) {
            // Row label
            const rowLabel = document.createElement('div');
            rowLabel.className = 'seat-row-label';
            rowLabel.textContent = String.fromCharCode(65 + r);
            els.seatGrid.appendChild(rowLabel);

            let c = 0;
            while (c < cols) {
                const seat = seatMap[r] && seatMap[r][c];
                if (seat) {
                    const typeName = seat.seat_type?.name || 'Standard';
                    const cssClass = typeClass[typeName] || 'seat-standard';
                    const isCouple = ['Couple', 'Sweetbox'].includes(typeName);
                    const isDisabled = seat.status === 0;
                    const span = isCouple ? 2 : 1;

                    let classList = `admin-seat ${cssClass}`;
                    if (isCouple) classList += ' seat-couple-span';
                    if (isDisabled) classList += ' seat-disabled';

                    const div = document.createElement('div');
                    div.className = classList;
                    div.setAttribute('data-id', seat.id);
                    div.setAttribute('data-status', seat.status ? '1' : '0');
                    div.setAttribute('data-type', typeName);
                    div.setAttribute('data-label', seat.label);
                    div.title = `${seat.label} · ${typeName}${isDisabled ? ' (hỏng)' : ''}`;

                    if (isDisabled) {
                        div.innerHTML = `<i class="bi bi-slash-circle"></i>`;
                    } else if (isCouple) {
                        div.innerHTML = `<svg width="2em" height="1em" viewBox="0 0 48 24" fill="currentColor" class="svg-icon-lg"><rect x="5" y="2" width="38" height="11" rx="2" /><rect x="4" y="14" width="40" height="5" rx="1" /><rect x="2" y="11" width="3" height="8" rx="1" /><rect x="43" y="11" width="3" height="8" rx="1" /></svg><span class="seat-label">${seat.label}</span>`;
                    } else {
                        div.innerHTML = `<svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" class="seat-icon-shape"><rect x="5" y="2" width="14" height="11" rx="2" /><rect x="4" y="14" width="16" height="5" rx="1" /><rect x="2" y="11" width="3" height="8" rx="1" /><rect x="19" y="11" width="3" height="8" rx="1" /></svg><span class="seat-label">${seat.label}</span>`;
                    }

                    els.seatGrid.appendChild(div);
                    c += span;
                } else {
                    const empty = document.createElement('div');
                    empty.className = 'admin-seat seat-empty';
                    els.seatGrid.appendChild(empty);
                    c++;
                }
            }
        }

        // Col labels
        els.colLabels.style.setProperty('--cols', cols);
        els.colLabels.innerHTML = '<div></div>';
        for (let c = 1; c <= cols; c++) {
            els.colLabels.innerHTML += `<div class="seat-col-label">${c}</div>`;
        }
    }

    /* ── Interaction ───────────────────────────────────────────── */
    els.seatGrid.addEventListener('click', (e) => {
        const seatEl = e.target.closest('.admin-seat:not(.seat-empty)');
        if (!seatEl) return;

        const seatId = seatEl.getAttribute('data-id');
        const currentStatus = seatEl.getAttribute('data-status') === '1';
        const newStatus = !currentStatus;

        seatEl.classList.add('toggling');
        setTimeout(() => seatEl.classList.remove('toggling'), 300);

        seatEl.setAttribute('data-status', newStatus ? '1' : '0');
        modifiedSeats[seatId] = newStatus ? 1 : 0;

        const typeName = seatEl.getAttribute('data-type') || 'Standard';
        const label = seatEl.getAttribute('data-label') || '';
        const isCouple = ['Couple', 'Sweetbox'].includes(typeName);

        if (newStatus) {
            seatEl.classList.remove('seat-disabled');
            seatEl.title = `${label} · ${typeName}`;
            if (isCouple) {
                seatEl.innerHTML = `<svg width="2em" height="1em" viewBox="0 0 48 24" fill="currentColor" class="svg-icon-lg"><rect x="5" y="2" width="38" height="11" rx="2" /><rect x="4" y="14" width="40" height="5" rx="1" /><rect x="2" y="11" width="3" height="8" rx="1" /><rect x="43" y="11" width="3" height="8" rx="1" /></svg><span class="seat-label">${label}</span>`;
            } else {
                seatEl.innerHTML = `<svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" class="seat-icon-shape"><rect x="5" y="2" width="14" height="11" rx="2" /><rect x="4" y="14" width="16" height="5" rx="1" /><rect x="2" y="11" width="3" height="8" rx="1" /><rect x="19" y="11" width="3" height="8" rx="1" /></svg><span class="seat-label">${label}</span>`;
            }
        } else {
            seatEl.classList.add('seat-disabled');
            seatEl.innerHTML = `<i class="bi bi-slash-circle"></i>`;
            seatEl.title = `${label} · ${typeName} (hỏng)`;
        }

        // Update capacity counter
        const active = document.querySelectorAll('.admin-seat:not(.seat-disabled):not(.seat-empty)').length;
        els.capacityCounter.textContent = active;
    });

    els.btnUpdateSeats.addEventListener('click', async () => {
        if (Object.keys(modifiedSeats).length === 0) {
            window.showAdminToast?.('Không có thay đổi nào cần lưu.', 'info');
            return;
        }

        els.btnUpdateSeats.disabled = true;
        els.btnUpdateSeats.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${screenId}/seats/update`, {
                method: 'POST',
                body: JSON.stringify({ seats: modifiedSeats })
            });

            if (res && res.ok) {
                window.showAdminToast?.('Đã cập nhật sơ đồ ghế', 'success');
                modifiedSeats = {}; // Reset changes
            } else {
                window.showAdminToast?.('Cập nhật thất bại', 'error');
            }
        } catch (error) {
            window.showAdminToast?.('Lỗi hệ thống', 'error');
        } finally {
            els.btnUpdateSeats.disabled = false;
            els.btnUpdateSeats.innerHTML = '<i class="bi bi-save me-2"></i>Cập nhật sơ đồ';
        }
    });

    els.screenActiveToggle.addEventListener('change', async (e) => {
        const isActive = e.target.checked;
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${screenId}/toggle-active`, { method: 'POST' });
            if (!res || !res.ok) throw new Error();
            window.showAdminToast?.(isActive ? 'Đã bật hoạt động phòng chiếu' : 'Đã tạm dừng phòng chiếu', 'success');
        } catch (error) {
            window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
            e.target.checked = !isActive;
        }
    });

    /* ── Init ──────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', loadSeatLayout);

})();
