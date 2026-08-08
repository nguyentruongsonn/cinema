/**
 * pos-seat.js – Seat map rendering & selection for POS (SVG Version)
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;
    const { api, renderEmptyState, toast } = global.PosUtils;

    let seats = [];
    let selectedSeats = new Map(); // using Map to store by ID
    let showtimeId = null;
    let realtimeChannel = null;
    let pollingTimer = null;
    let pollingInFlight = false;

    const seatMapContainer = document.getElementById('seatMapContainer');
    const seatMap = document.getElementById('seatMap');
    const seatMapSkeleton = document.getElementById('seatMapSkeleton');

    // ── Load seats ────────────────────────────────────────
    async function load(stId) {
        subscribeRealtime(stId);
        if (showtimeId !== stId) {
            selectedSeats.clear();
        }
        showtimeId = stId;
        
        if (seatMapSkeleton) seatMapSkeleton.classList.remove('d-none');
        if (seatMap) {
            seatMap.classList.add('d-none');
            seatMap.innerHTML = '';
        }

        try {
            const res = await api.get(`${cfg.apiBase}/showtimes/${stId}/seats`);
            seats = res.data?.seats || res.data || [];
            
            // Clean up selectedSeats that are no longer available (booked/locked by someone else)
            for (const [seatId, seat] of selectedSeats.entries()) {
                const freshSeat = seats.find(s => s.id === seatId);
                if (freshSeat && ['booked', 'locked', 'maintenance'].includes(freshSeat.status)) {
                    selectedSeats.delete(seatId);
                }
            }

            renderSeatMap();
            updateUI();
        } catch (err) {
            if (seatMap) {
                seatMapSkeleton?.classList.add('d-none');
                seatMap.classList.remove('d-none');
                renderEmptyState(seatMap, err.message || 'Không thể tải sơ đồ ghế');
            }
        }
    }

    // ── Render ─────────────────────────────────────────────
    function renderSeatMap() {
        if (!seatMap) return;

        if (seatMapSkeleton) seatMapSkeleton.classList.add('d-none');
        seatMap.classList.remove('d-none');
        seatMap.classList.add('seat-grid');
        seatMap.innerHTML = '';

        if (!seats.length) {
            seatMap.innerHTML = '<div class="pos-empty"><div class="pos-empty-icon">💺</div><div class="pos-empty-text">Không có dữ liệu ghế</div></div>';
            return;
        }

        const dimensions = getSeatGridDimensions();
        seatMap.style.setProperty('--cols', dimensions.cols);

        const seatPosMap = buildSeatPositionMap();

        for (let rowIndex = 0; rowIndex < dimensions.rows; rowIndex++) {
            let firstSeatRow = seatPosMap[rowIndex] && Object.values(seatPosMap[rowIndex]).find(s => s);
            const rowLabelText = firstSeatRow ? firstSeatRow.row : '';

            const rowLabel = document.createElement('div');
            rowLabel.className = 'seat-row-label';
            rowLabel.textContent = rowLabelText;
            seatMap.appendChild(rowLabel);

            let isCoupleRow = firstSeatRow && isCoupleSeat(firstSeatRow);
            const shiftRight = isCoupleRow && (dimensions.cols % 2 !== 0);

            let colIndex = 0;
            while (colIndex < dimensions.cols) {
                const seat = seatPosMap[rowIndex]?.[colIndex];

                if (!seat) {
                    seatMap.appendChild(createEmptySeat());
                    colIndex++;
                    continue;
                }

                const isCouple = isCoupleSeat(seat);
                
                if (isCouple && colIndex + 2 > dimensions.cols) {
                    seatMap.appendChild(createEmptySeat());
                    colIndex++;
                    continue;
                }

                const seatEl = createSeat(seat);
                if (shiftRight && isCouple) {
                    seatEl.classList.add('seat-couple-center');
                }
                seatMap.appendChild(seatEl);
                colIndex += isCouple ? 2 : 1;
            }
            
            // Add right side label
            const rightRowLabel = document.createElement('div');
            rightRowLabel.className = 'seat-row-label right-label';
            rightRowLabel.textContent = rowLabelText;
            seatMap.appendChild(rightRowLabel);
        }
    }

    function getSeatGridDimensions() {
        if (!seats.length) {
            return { rows: 0, cols: 0 };
        }
        return {
            rows: Math.max(...seats.map(seat => getSeatRowIndex(seat))) + 1,
            cols: Math.max(...seats.map(seat => getSeatColumnIndex(seat))) + 1,
        };
    }

    function buildSeatPositionMap() {
        return seats.reduce((map, seat) => {
            const rowIndex = getSeatRowIndex(seat);
            const columnIndex = getSeatColumnIndex(seat);
            if (!map[rowIndex]) map[rowIndex] = {};
            map[rowIndex][columnIndex] = seat;
            return map;
        }, {});
    }

    function getSeatRowIndex(seat) {
        if (Number.isInteger(seat.row_index)) return seat.row_index;
        if (Number.isInteger(seat.rowIndex)) return seat.rowIndex;
        const row = String(seat.row || '').trim().toUpperCase();
        return row ? row.charCodeAt(0) - 65 : 0;
    }

    function getSeatColumnIndex(seat) {
        if (Number.isInteger(seat.column_index)) return seat.column_index;
        if (Number.isInteger(seat.columnIndex)) return seat.columnIndex;
        return Math.max(0, (parseInt(seat.number, 10) || 1) - 1);
    }

    function createEmptySeat() {
        const empty = document.createElement('div');
        empty.className = 'seat admin-seat seat-empty';
        empty.setAttribute('aria-hidden', 'true');
        return empty;
    }

    function isCoupleSeat(seat) {
        const seatTypeName = (seat.seat_type?.name || seat.type || '').toLowerCase();
        return seatTypeName.includes('đôi')
            || seatTypeName.includes('doi')
            || seatTypeName.includes('couple')
            || seatTypeName.includes('double')
            || seatTypeName.includes('sweetbox');
    }

    function getSeatStatus(seat) {
        if (selectedSeats.has(seat.id)) return 'selected';
        if (seat.is_booked || seat.status === 'booked') return 'booked';
        if (seat.is_locked || seat.status === 'locked') return 'locked';
        if (seat.is_maintenance || seat.status === 'maintenance') return 'maintenance';
        // Note: we check holding after to allow local 'selected' state to override if it's our own hold 
        // that hasn't unlocked yet, but since we unlock first, it should return available anyway.
        if (seat.is_holding || seat.status === 'holding' || seat.is_held || seat.status === 'held') return 'holding';
        
        return 'available';
    }

    function createSeat(seat) {
        const seatDiv = document.createElement('div');
        seatDiv.className = 'seat admin-seat seat-standard pos-seat-interactive';
        seatDiv.dataset.seatId = seat.id;
        
        const status = getSeatStatus(seat);
        seatDiv.classList.add(`seat-${status}`);

        const seatTypeName = (seat.seat_type?.name || seat.type || '').toLowerCase();
        const isVip = seatTypeName.includes('vip') || seatTypeName.includes('premium');
        const isCouple = isCoupleSeat(seat);

        if (isVip) {
            seatDiv.classList.remove('seat-standard');
            seatDiv.classList.add('seat-vip');
        }

        if (isCouple) {
            seatDiv.classList.remove('seat-standard');
            seatDiv.classList.add('seat-couple', 'seat-couple-span');
        }

        const label = seat.label || `${seat.row}${seat.number}`;

        // Gradients
        const getGradient = (id, top, bot) =>
            `<defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${top}"/><stop offset="100%" stop-color="${bot}"/></linearGradient></defs>`;

        let gradId, gradTop, gradBot;
        if (status === 'selected') {
            gradId = `gs-${seat.id}`; gradTop = '#ff4455'; gradBot = '#c0000f';
        } else if (isVip) {
            gradId = `gv-${seat.id}`; gradTop = '#ff6a3d'; gradBot = '#c0392b';
        } else if (isCouple) {
            gradId = `gc-${seat.id}`; gradTop = '#b06ae9'; gradBot = '#7b2fa0';
        } else {
            gradId = `gn-${seat.id}`; gradTop = '#52596b'; gradBot = '#2e3340';
        }
        gradId = `${gradId}-${Math.random().toString(36).substring(2, 9)}`;

        const fillAttr = status === 'holding'
            ? 'fill="#F59E0B"'
            : (status === 'booked' || status === 'locked' || status === 'maintenance')
            ? 'fill="#2a2d35"'
            : `fill="url(#${gradId})"`;

        const icon = isCouple
            ? `<svg width="2em" height="1em" viewBox="0 0 48 24" ${fillAttr} class="seat-icon-shape">${getGradient(gradId, gradTop, gradBot)}<rect x="5" y="2" width="38" height="11" rx="2"/><rect x="4" y="14" width="40" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="43" y="11" width="3" height="8" rx="1"/></svg>`
            : `<svg width="1em" height="1em" viewBox="0 0 24 24" ${fillAttr} class="seat-icon-shape">${getGradient(gradId, gradTop, gradBot)}<rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>`;

        seatDiv.innerHTML = icon;
        const seatLabel = document.createElement('span');
        seatLabel.className = 'seat-label';
        seatLabel.textContent = String(label ?? '');
        seatDiv.appendChild(seatLabel);

        if (status === 'holding' || status === 'booked' || status === 'locked' || status === 'maintenance') {
            const soldMarker = document.createElement('span');
            soldMarker.className = 'sold-marker';
            soldMarker.textContent = '×';
            seatDiv.appendChild(soldMarker);
        }

        if (status === 'available' || status === 'selected') {
            seatDiv.addEventListener('click', () => toggleSeat(seat));
        }

        return seatDiv;
    }

    // ── Toggle seat ───────────────────────────────────────
    function toggleSeat(seat) {
        if (selectedSeats.has(seat.id)) {
            selectedSeats.delete(seat.id);
        } else {
            selectedSeats.set(seat.id, seat);
        }

        renderSeatMap();
        updateUI();
        
        // Notify other modules (e.g. cart)
        document.dispatchEvent(new CustomEvent('pos:seats:change', { detail: { seats: Array.from(selectedSeats.values()) } }));
        document.dispatchEvent(new CustomEvent('pos:seats_changed', { detail: { seats: Array.from(selectedSeats.values()) } }));
    }

    function removeSeat(seatId) {
        selectedSeats.delete(Number(seatId));
        renderSeatMap();
        updateUI();
        notifySelectionChange();
    }

    function clearSelection() {
        selectedSeats.clear();
        renderSeatMap();
        updateUI();
        notifySelectionChange();
    }

    function notifySelectionChange() {
        const detail = { seats: Array.from(selectedSeats.values()) };
        document.dispatchEvent(new CustomEvent('pos:seats:change', { detail }));
        document.dispatchEvent(new CustomEvent('pos:seats_changed', { detail }));
    }

    function subscribeRealtime(stId) {
        stopPolling();
        if (realtimeChannel && global.Echo?.leave) {
            global.Echo.leave(`showtime.${showtimeId}`);
            realtimeChannel = null;
        }
        if (!global.Echo || typeof global.Echo.channel !== 'function') {
            startPolling(stId);
            return;
        }

        realtimeChannel = global.Echo.channel(`showtime.${stId}`);
        realtimeChannel.listen('.seat.status.updated', event => {
            if (Number(event.showtime_id) !== Number(showtimeId)) return;
            const seat = seats.find(item => Number(item.id) === Number(event.seat_id));
            if (!seat) return;

            if (event.status === 'locked' && Number(event.user_id) !== Number(cfg.staffId)) {
                selectedSeats.delete(Number(event.seat_id));
                document.dispatchEvent(new CustomEvent('pos:seats:change', {
                    detail: { seats: Array.from(selectedSeats.values()) },
                }));
            }

            seat.status = event.status;
            seat.is_holding = event.status === 'locked';
            seat.is_locked = event.status === 'locked';
            if (event.status === 'available') {
                seat.is_holding = false;
                seat.is_locked = false;
            }

            renderSeatMap();
            updateUI();
        });

        const connection = global.Echo.connector?.pusher?.connection;
        if (connection?.bind) {
            connection.bind('disconnected', () => startPolling(stId));
            connection.bind('connected', stopPolling);
        }
    }

    function startPolling(stId) {
        pollingTimer = window.setInterval(async () => {
            if (document.hidden || pollingInFlight || Number(showtimeId) !== Number(stId)) return;
            pollingInFlight = true;
            try {
                const res = await api.get(`${cfg.apiBase}/showtimes/${stId}/seats`);
                const payload = res.data || res || {};
                const freshSeats = payload.seats || [];
                seats = freshSeats;
                for (const [seatId] of selectedSeats) {
                    const freshSeat = freshSeats.find(seat => Number(seat.id) === Number(seatId));
                    if (freshSeat && ['booked', 'locked', 'maintenance'].includes(freshSeat.status)) {
                        selectedSeats.delete(seatId);
                    }
                }
                renderSeatMap();
                updateUI();
            } catch (error) {
                console.debug('POS seat polling skipped', error);
            } finally {
                pollingInFlight = false;
            }
        }, 5000);
    }

    function stopPolling() {
        if (pollingTimer) {
            window.clearInterval(pollingTimer);
            pollingTimer = null;
        }
    }

    function updateUI() {
        const countEl = document.getElementById('seatSelectedCount');
        if (countEl) countEl.textContent = selectedSeats.size;
        
        const seatNamesEl = document.getElementById('seatSelectedNames');
        if (seatNamesEl) {
            if (selectedSeats.size > 0) {
                const names = Array.from(selectedSeats.values()).map(s => s.label || `${s.row}${s.number}`).join(', ');
                seatNamesEl.textContent = names;
            } else {
                seatNamesEl.textContent = 'Trống';
            }
        }
        
        // Disable next button if no seats selected
        if (selectedSeats.size === 0) {
            global.PosApp?.disableNext?.();
        } else {
            global.PosApp?.enableNext?.();
        }
    }

    function getSelectedSeats() { 
        return Array.from(selectedSeats.values()); 
    }
    
    // For backward compatibility with other scripts
    function getSelected() { 
        return Array.from(selectedSeats.values()); 
    }

    function reset() {
        if (realtimeChannel && global.Echo?.leave && showtimeId) {
            global.Echo.leave(`showtime.${showtimeId}`);
            realtimeChannel = null;
        }
        stopPolling();
        selectedSeats.clear();
        seats = [];
        showtimeId = null;
        if (seatMap) seatMap.innerHTML = '';
        if (seatMapSkeleton) seatMapSkeleton.classList.remove('d-none');
        updateUI();
    }

    global.PosSeat = {
        load,
        getSelected,
        getSelectedSeats,
        removeSeat,
        clearSelection,
        reset,
    };

})(window);
