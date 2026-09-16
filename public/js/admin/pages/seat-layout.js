/**
 * Seat Layout Admin Page — seat-layout.js
 * SPA Architecture
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const config = window.SEAT_LAYOUT_CONFIG || {};
    const screenId = config.screenId;
    if (!screenId) return;

    /* ── DOM Cache ─────────────────────────────────────────────── */
    const els = {
        title: document.getElementById('screenNameTitle'),
        codeBadge: document.getElementById('screenCodeBadge'),
        theaterSpan: document.getElementById('theaterNameSpan'),

        seatGrid: document.getElementById('seatGrid'),
        colLabels: document.getElementById('seatGridColLabels'),

        infoTheater: document.getElementById('infoTheater'),
        infoFormat: document.getElementById('infoFormat'),
        infoMatrix: document.getElementById('infoMatrix'),
        capacityCounter: document.getElementById('capacityCounter'),
        totalSeatsCounter: document.getElementById('totalSeatsCounter'),

        screenActiveToggle: document.getElementById('screenActiveToggle'),
        btnUpdateSeats: document.getElementById('btnUpdateSeats'),
    };

    let initialSeats = [];
    let modifiedSeats = {};
    let modifiedHiddenRows = [];
    let currentScreen = null;

    /* ── Fetch Data ────────────────────────────────────────────── */
    async function loadSeatLayout() {
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${screenId}/seats`);
            if (res && res.ok) {
                const data = await res.json();
                currentScreen = data.screen;
                initialSeats = data.seats || [];
                modifiedSeats = {};
                modifiedHiddenRows = Array.isArray(data.hidden_rows) ? [...data.hidden_rows] : [];

                renderInfo(data.screen, initialSeats);
                renderGrid(data.screen, initialSeats, modifiedHiddenRows);
            }
        } catch (error) {
            console.error('Error loading seats:', error);
            const skeleton = document.querySelector('.seat-map-skeleton');
            if (skeleton) {
                skeleton.innerHTML = '<div class="text-danger py-4 text-center">Lỗi khi tải dữ liệu sơ đồ ghế.</div>';
            }
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

        els.screenActiveToggle.checked = screen.status === 1;

        let rows = 0;
        let cols = 0;
        let activeCount = 0;

        if (seats && seats.length > 0) {
            rows = Math.max(...seats.map(s => s.row_index)) + 1;
            cols = Math.max(...seats.map(s => s.column_index)) + 1;
            activeCount = seats.filter(s => !!s.status && !modifiedHiddenRows.includes(s.row_index)).length;
        } else {
            const matrixStr = screen.seat_layout_template?.seat_matrix || '12x12';
            [rows, cols] = matrixStr.split('x').map(Number);
        }

        els.infoMatrix.textContent = `${rows}x${cols}`;
        els.capacityCounter.textContent = activeCount;
        els.totalSeatsCounter.textContent = seats.length;
    }

    /* ── Helper ────────────────────────────────────────────────── */
    function generateSeatHtml(seatId, typeName, label, isDisabled) {
        const isVip = typeName.includes('VIP') || typeName.includes('Premium');
        const isCouple = ['Couple', 'Sweetbox'].includes(typeName);

        const getGradient = (id, top, bot) =>
            `<defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${top}"/><stop offset="100%" stop-color="${bot}"/></linearGradient></defs>`;

        let gradId, gradTop, gradBot;
        if (isVip) {
            gradId = `gv-${seatId}`; gradTop = '#ff6a3d'; gradBot = '#c0392b';
        } else if (isCouple) {
            gradId = `gc-${seatId}`; gradTop = '#b06ae9'; gradBot = '#7b2fa0';
        } else {
            gradId = `gn-${seatId}`; gradTop = '#52596b'; gradBot = '#2e3340';
        }
        
        gradId = `${gradId}-${Math.random().toString(36).substring(2, 9)}`;
        const fillAttr = isDisabled ? 'fill="#2a2d35"' : `fill="url(#${gradId})"`;

        const iconCouple = `<svg width="2em" height="1em" viewBox="0 0 48 24" ${fillAttr} class="seat-icon-shape">${getGradient(gradId, gradTop, gradBot)}<rect x="5" y="2" width="38" height="11" rx="2"/><rect x="4" y="14" width="40" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="43" y="11" width="3" height="8" rx="1"/></svg>`;
        const iconStandard = `<svg width="1em" height="1em" viewBox="0 0 24 24" ${fillAttr} class="seat-icon-shape">${getGradient(gradId, gradTop, gradBot)}<rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>`;

        if (isDisabled) {
            return (isCouple ? iconCouple : iconStandard) + 
                    `<span class="seat-label seat-label--muted">${escapeHtml(label)}</span>`;
        } else {
            return (isCouple ? iconCouple : iconStandard) + 
                   `<span class="seat-label">${escapeHtml(label)}</span>`;
        }
    }

    /* ── Render Grid ───────────────────────────────────────────── */
    function renderGrid(screen, seats, hiddenRowsList = []) {
        document.querySelector('.seat-map-skeleton')?.classList.add('d-none');
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
        els.seatGrid.style.gridTemplateColumns = `28px repeat(${cols}, minmax(36px, 40px)) 28px 40px`;
        els.colLabels.style.gridTemplateColumns = `28px repeat(${cols}, minmax(36px, 40px)) 28px 40px`;
        els.seatGrid.innerHTML = '';

        for (let r = 0; r < rows; r++) {
            const isHidden = hiddenRowsList.includes(r);
            const opacity = isHidden ? '0.2' : '1';
            const pointerEvents = isHidden ? 'none' : 'auto';

            // Row label
            const rowLabel = document.createElement('div');
            rowLabel.className = 'seat-row-label';
            rowLabel.textContent = isHidden ? '' : String.fromCharCode(65 + (r - hiddenRowsList.filter(h => h < r).length));
            rowLabel.style.opacity = opacity;
            els.seatGrid.appendChild(rowLabel);

            let firstSeatRow = seatMap[r] && Object.values(seatMap[r]).find(s => s);
            let isCoupleRow = firstSeatRow && ['Couple', 'Sweetbox'].includes(firstSeatRow.seat_type?.name);
            const shiftRight = isCoupleRow && (cols % 2 !== 0);

            let c = 0;
            while (c < cols) {
                const seat = seatMap[r] && seatMap[r][c];
                if (seat) {
                    const typeName = seat.seat_type?.name || 'Standard';
                    const cssClass = typeClass[typeName] || 'seat-standard';
                    const isCouple = ['Couple', 'Sweetbox'].includes(typeName);
                    const isDisabled = !seat.status || seat.status === 0 || isHidden;
                    const span = isCouple ? 2 : 1;

                    if (c + span > cols) {
                        const empty = document.createElement('div');
                        empty.className = 'seat admin-seat seat-empty';
                        els.seatGrid.appendChild(empty);
                        c++;
                        continue;
                    }

                    let classList = `seat admin-seat ${cssClass}`;
                    if (isCouple) classList += ' seat-couple-span';
                    if (shiftRight) classList += ' seat-couple-center';
                    if (isDisabled) classList += ' seat-disabled';

                    const div = document.createElement('div');
                    div.className = classList;
                    div.setAttribute('data-id', seat.id);
                    div.setAttribute('data-status', seat.status ? '1' : '0');
                    div.setAttribute('data-type', typeName);
                    div.setAttribute('data-label', seat.label);
                    div.title = `${seat.label} · ${typeName}${isDisabled ? ' (hỏng)' : ''}`;

                    div.innerHTML = generateSeatHtml(seat.id, typeName, seat.label, isDisabled);
                    div.style.opacity = opacity;
                    div.style.pointerEvents = pointerEvents;

                    els.seatGrid.appendChild(div);
                    c += span;
                } else {
                    const empty = document.createElement('div');
                    empty.className = 'seat admin-seat seat-empty';
                    els.seatGrid.appendChild(empty);
                    c++;
                }
            }
            // Right Row label
            const rightLabel = document.createElement('div');
            rightLabel.className = 'seat-row-label right-label';
            rightLabel.textContent = rowLabel.textContent;
            rightLabel.style.opacity = opacity;
            els.seatGrid.appendChild(rightLabel);

            // Action button
            const actionBtn = document.createElement('div');
            actionBtn.className = 'd-flex align-items-center justify-content-center';
            if (isHidden) {
                actionBtn.innerHTML = `<button type="button" class="btn btn-sm btn-success btn-toggle-row" data-row="${r}" title="Thêm hàng"><i class="bi bi-plus-lg"></i></button>`;
            } else {
                actionBtn.innerHTML = `<button type="button" class="btn btn-sm btn-outline-danger btn-toggle-row border-0" data-row="${r}" title="Ẩn xóa hàng"><i class="bi bi-dash-lg"></i></button>`;
            }
            els.seatGrid.appendChild(actionBtn);
        }

        // Col labels
        els.colLabels.style.setProperty('--cols', cols);
        els.colLabels.innerHTML = '<div></div>';
        for (let c = 1; c <= cols; c++) {
            els.colLabels.innerHTML += `<div class="seat-col-label">${c}</div>`;
        }
        els.colLabels.innerHTML += '<div></div><div></div>';

        // Sync capacity counter
        let activeCount = seats.filter(s => !!s.status && !hiddenRowsList.includes(s.row_index)).length;
        if (els.capacityCounter) {
            els.capacityCounter.textContent = activeCount;
        }
    }

    /* ── Interaction ───────────────────────────────────────────── */
    let isDirty = false;
    els.seatGrid.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('.btn-toggle-row');
        if (toggleBtn) {
            const rowIdx = parseInt(toggleBtn.getAttribute('data-row'), 10);
            if (modifiedHiddenRows.includes(rowIdx)) {
                modifiedHiddenRows = modifiedHiddenRows.filter(r => r !== rowIdx);
            } else {
                modifiedHiddenRows.push(rowIdx);
            }
            modifiedHiddenRows.sort((a, b) => a - b);
            isDirty = true;
            
            // Re-render grid
            if (currentScreen) {
                const updatedSeats = initialSeats.map(s => {
                    const seatCopy = { ...s };
                    if (modifiedSeats[seatCopy.id] !== undefined) {
                        seatCopy.status = modifiedSeats[seatCopy.id];
                    }
                    return seatCopy;
                });
                renderGrid(currentScreen, updatedSeats, modifiedHiddenRows);
            }
            return;
        }
        
        const seatEl = e.target.closest('.admin-seat:not(.seat-empty)');
        if (!seatEl) return;

        const seatId = seatEl.getAttribute('data-id');
        const currentStatus = seatEl.getAttribute('data-status') === '1';
        const newStatus = !currentStatus;

        seatEl.classList.add('toggling');
        setTimeout(() => seatEl.classList.remove('toggling'), 300);

        seatEl.setAttribute('data-status', newStatus ? '1' : '0');
        modifiedSeats[seatId] = newStatus ? 1 : 0;
        isDirty = true;

        const typeName = seatEl.getAttribute('data-type') || 'Standard';
        const label = seatEl.getAttribute('data-label') || '';
        const isCouple = ['Couple', 'Sweetbox'].includes(typeName);

        if (newStatus) {
            seatEl.classList.remove('seat-disabled');
            seatEl.title = `${label} · ${typeName}`;
        } else {
            seatEl.classList.add('seat-disabled');
            seatEl.title = `${label} · ${typeName} (hỏng)`;
        }
        
        seatEl.innerHTML = generateSeatHtml(seatId, typeName, label, !newStatus);

        // Update capacity counter
        const updatedSeats = initialSeats.map(s => {
            const seatCopy = { ...s };
            if (modifiedSeats[seatCopy.id] !== undefined) {
                seatCopy.status = modifiedSeats[seatCopy.id];
            }
            return seatCopy;
        });
        let activeCount = updatedSeats.filter(s => !!s.status && !modifiedHiddenRows.includes(s.row_index)).length;
        if (els.capacityCounter) {
            els.capacityCounter.textContent = activeCount;
        }
    });

    els.btnUpdateSeats.addEventListener('click', async () => {
        if (!isDirty && Object.keys(modifiedSeats).length === 0) {
            window.showAdminToast?.('Không có thay đổi nào cần lưu.', 'info');
            return;
        }

        els.btnUpdateSeats.disabled = true;
        els.btnUpdateSeats.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';

        try {
            // Build final seats payload
            const finalSeatsPayload = initialSeats.map(s => ({
                id: s.id,
                status: modifiedSeats[s.id] !== undefined ? !!modifiedSeats[s.id] : !!s.status
            }));

            const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${screenId}/seats/update`, {
                method: 'POST',
                body: JSON.stringify({ 
                    seats: finalSeatsPayload,
                    hidden_rows: modifiedHiddenRows 
                })
            });

            if (res && res.ok) {
                window.showAdminToast?.('Đã cập nhật sơ đồ ghế', 'success');
                isDirty = false;
                // Update local initial state
                initialSeats = initialSeats.map(s => {
                    const seatCopy = { ...s };
                    if (modifiedSeats[seatCopy.id] !== undefined) {
                        seatCopy.status = modifiedSeats[seatCopy.id];
                    }
                    return seatCopy;
                });
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
    window.onAdminPageLoad(loadSeatLayout);

})();
