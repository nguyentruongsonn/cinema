/**
 * Showtimes Management - showtimes.js
 * SPA Architecture — Tab-based add system
 */
(function () {
    'use strict';

    /* ── DOM refs ─────────────────────────────────────────── */
    const els = {
        tableBody: document.getElementById('showtimesTableBody'),
        pagination: document.getElementById('paginationContainer'),
        totalCount: document.getElementById('totalCount'),

        searchForm: document.getElementById('searchForm'),
        dateFilter: document.getElementById('dateFilter'),
        movieFilter: document.getElementById('movieFilter'),
        theaterFilter: document.getElementById('theaterFilter'),

        // Edit modal
        modalEl: document.getElementById('showtimeModal'),
        form: document.getElementById('showtimeForm'),
        formMethod: document.getElementById('showtimeFormMethod'),
        idInput: document.getElementById('showtimeIdInput'),
        formMovieId: document.getElementById('formMovieId'),
        formTheaterId: document.getElementById('formTheaterId'),
        formScreenId: document.getElementById('formScreenId'),
        formScheduledAt: document.getElementById('formScheduledAt'),
        formPrice: document.getElementById('formPrice'),
        formFormatId: document.getElementById('formFormatId'),
        formSoundId: document.getElementById('formSoundId'),
        formSubtitleId: document.getElementById('formSubtitleId'),
        formStatus: document.getElementById('formStatus'),

        // Multi-day tab
        mMovieId: document.getElementById('mMovieId'),
        mTheaterId: document.getElementById('mTheaterId'),
        mScreenId: document.getElementById('mScreenId'),
        mDateFrom: document.getElementById('mDateFrom'),
        mDateTo: document.getElementById('mDateTo'),
        mTimeInput: document.getElementById('mTimeInput'),
        mAddTimeBtn: document.getElementById('mAddTimeBtn'),
        mPrice: document.getElementById('mPrice'),
        mFormatId: document.getElementById('mFormatId'),
        mSoundId: document.getElementById('mSoundId'),
        mSubtitleId: document.getElementById('mSubtitleId'),
        timeSlotTags: document.getElementById('timeSlotTags'),
        previewMultiBtn: document.getElementById('previewMultiBtn'),
        multiPreviewBlock: document.getElementById('multiPreviewBlock'),
        multiPreviewList: document.getElementById('multiPreviewList'),
        multiDayForm: document.getElementById('multiDayForm'),

        // Single-day tab
        sMovieId: document.getElementById('sMovieId'),
        sDate: document.getElementById('sDate'),
        sPrice: document.getElementById('sPrice'),
        sFormatId: document.getElementById('sFormatId'),
        sSoundId: document.getElementById('sSoundId'),
        sSubtitleId: document.getElementById('sSubtitleId'),
        sStatusToggle: document.getElementById('sStatusToggle'),
        sSlotRows: document.getElementById('sSlotRows'),
        sAddSlotBtn: document.getElementById('sAddSlotBtn'),
        singleDayForm: document.getElementById('singleDayForm'),
    };

    let currentPage = 1;
    let cachedMovies = [];
    let cachedTheaters = [];
    let cachedFormats = [];
    let cachedSounds = [];
    let cachedSubtitles = [];
    let multiTimeSlots = []; // array of "HH:MM"
    let singleSlotCount = 0;

    function getModalInstance() {
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Populate helpers ─────────────────────────────────── */
    function fillSelect(el, items, valueKey, labelKey, emptyLabel = '-- Chọn --') {
        if (!el) return;
        el.innerHTML = `<option value="">${emptyLabel}</option>`;
        items.forEach(item => {
            el.innerHTML += `<option value="${item[valueKey]}">${item[labelKey]}</option>`;
        });
    }

    /* ── Fetch Prerequisites ─────────────────────────────── */
    async function fetchPrerequisites() {
        try {
            // Movies
            const mRes = await fetch('/api/v1/movies?per_page=200&status=all', { headers: { Accept: 'application/json' } });
            if (mRes.ok) {
                const mData = await mRes.json();
                cachedMovies = mData.data || [];
                [els.movieFilter, els.formMovieId, els.mMovieId, els.sMovieId].forEach(el =>
                    fillSelect(el, cachedMovies, 'id', 'title', 'Tất cả phim')
                );
                if (els.movieFilter) els.movieFilter.querySelector('option').textContent = 'Tất cả phim';
            }

            // Theaters / formats / sounds from screens meta
            const sRes = await window.AdminCore.apiFetch('/api/v1/admin/screens?per_page=1');
            if (sRes && sRes.ok) {
                const sData = await sRes.json();
                cachedTheaters = sData.theaters || [];
                cachedFormats = sData.formats || [];
                cachedSounds = sData.sounds || [];
                cachedSubtitles = sData.subtitles || [];

                [els.theaterFilter, els.formTheaterId, els.mTheaterId].forEach(el =>
                    fillSelect(el, cachedTheaters, 'id', 'name', 'Tất cả rạp')
                );
                if (els.theaterFilter) els.theaterFilter.querySelector('option').textContent = 'Tất cả rạp';

                [els.formFormatId, els.mFormatId, els.sFormatId].forEach(el =>
                    fillSelect(el, cachedFormats, 'id', 'name', '-- Mặc định --')
                );
                [els.formSoundId, els.mSoundId, els.sSoundId].forEach(el =>
                    fillSelect(el, cachedSounds, 'id', 'name', '-- Mặc định --')
                );
                [els.formSubtitleId, els.mSubtitleId, els.sSubtitleId].forEach(el =>
                    fillSelect(el, cachedSubtitles, 'id', 'name', '-- Không --')
                );
            }
        } catch (err) {
            console.error('fetchPrerequisites error:', err);
        }
    }

    /* ── Load screens for theater ────────────────────────── */
    async function loadScreensForTheater(theaterId, screenEl) {
        if (!screenEl) return;
        screenEl.disabled = true;
        screenEl.innerHTML = '<option value="">Đang tải...</option>';
        if (!theaterId) {
            screenEl.innerHTML = '<option value="">-- Chọn phòng --</option>';
            return;
        }
        try {
            const res = await fetch(`/api/v1/theaters/${theaterId}/screens`, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                fillSelect(screenEl, data.data || [], 'id', 'name', '-- Chọn phòng --');
                screenEl.disabled = false;
            }
        } catch (err) {
            screenEl.innerHTML = '<option value="">-- Chọn phòng --</option>';
        }
    }

    /* ── Multi-day: Time slot tags ───────────────────────── */
    function renderTimeSlotTags() {
        els.timeSlotTags.innerHTML = '';
        multiTimeSlots.forEach((t, i) => {
            const chip = document.createElement('span');
            chip.className = 'time-slot-tag';
            chip.innerHTML = `<i class="bi bi-clock"></i>${t} <button class="remove-tag" data-index="${i}" title="Xóa">×</button>`;
            els.timeSlotTags.appendChild(chip);
        });
    }

    function addTimeSlot(time) {
        if (!time) return;
        if (multiTimeSlots.includes(time)) {
            window.showAdminToast?.(`Giờ ${time} đã có trong danh sách`, 'warning');
            return;
        }
        multiTimeSlots.push(time);
        multiTimeSlots.sort();
        renderTimeSlotTags();
    }

    if (els.mAddTimeBtn) {
        els.mAddTimeBtn.addEventListener('click', () => {
            addTimeSlot(els.mTimeInput.value);
        });
    }
    if (els.mTimeInput) {
        els.mTimeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); addTimeSlot(els.mTimeInput.value); }
        });
    }
    if (els.timeSlotTags) {
        els.timeSlotTags.addEventListener('click', (e) => {
            const btn = e.target.closest('.remove-tag');
            if (btn) {
                multiTimeSlots.splice(parseInt(btn.dataset.index), 1);
                renderTimeSlotTags();
            }
        });
    }

    /* ── Multi-day: Preview ──────────────────────────────── */
    if (els.previewMultiBtn) {
        els.previewMultiBtn.addEventListener('click', () => {
            const from = els.mDateFrom?.value;
            const to = els.mDateTo?.value;
            if (!from || !to || multiTimeSlots.length === 0) {
                window.showAdminToast?.('Chọn đầy đủ ngày và ít nhất 1 giờ chiếu', 'warning');
                return;
            }
            const dates = [];
            let cur = new Date(from);
            const end = new Date(to);
            while (cur <= end) {
                dates.push(cur.toLocaleDateString('vi-VN', { weekday: 'short', day: '2-digit', month: '2-digit' }));
                cur.setDate(cur.getDate() + 1);
            }
            els.multiPreviewList.innerHTML = '';
            dates.forEach(d => {
                multiTimeSlots.forEach(t => {
                    const badge = document.createElement('span');
                    badge.className = 'preview-slot-badge';
                    badge.innerHTML = `<i class="bi bi-calendar3" style="font-size:0.7rem;"></i>${d} ${t}`;
                    els.multiPreviewList.appendChild(badge);
                });
            });
            els.multiPreviewBlock.classList.remove('d-none');
        });
    }

    /* ── Multi-day: Submit ───────────────────────────────── */
    if (els.multiDayForm) {
        els.multiDayForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (multiTimeSlots.length === 0) {
                window.showAdminToast?.('Vui lòng thêm ít nhất 1 giờ chiếu', 'warning');
                return;
            }

            const data = {
                movie_id: els.mMovieId.value,
                screen_id: els.mScreenId.value,
                date_from: els.mDateFrom.value,
                date_to: els.mDateTo.value,
                times: multiTimeSlots,
                price: els.mPrice.value,
                format_id: els.mFormatId.value || null,
                sound_id: els.mSoundId.value || null,
                subtitle_id: els.mSubtitleId.value || null,
                status: 1,
            };

            try {
                const res = await window.AdminCore.apiFetch('/api/v1/admin/showtimes/bulk', {
                    method: 'POST',
                    body: JSON.stringify(data),
                });
                if (res && res.ok) {
                    const result = await res.json();
                    window.showAdminToast?.(
                        `Tạo thành công ${result.data?.created || ''} suất chiếu!`,
                        'success'
                    );
                    multiTimeSlots = [];
                    renderTimeSlotTags();
                    els.multiDayForm.reset();
                    els.multiPreviewBlock.classList.add('d-none');
                    loadData(1);
                } else {
                    const errData = await res.json();
                    alert('Lỗi: ' + JSON.stringify(errData.errors || errData.message));
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    /* ── Single-day: Slot rows ───────────────────────────── */
    function addSlotRow(time = '', screenId = '') {
        singleSlotCount++;
        const idx = singleSlotCount;
        const row = document.createElement('div');
        row.className = 'slot-row';
        row.id = `slot-row-${idx}`;

        const theaterOptions = cachedTheaters.map(t =>
            `<option value="${t.id}">${t.name}</option>`
        ).join('');

        row.innerHTML = `
            <div>
                <label class="form-label text-secondary" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.07em;">Giờ chiếu</label>
                <input type="time" class="filter-input w-100 slot-time-input" value="${time}" required>
            </div>
            <div>
                <label class="form-label text-secondary" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.07em;">Rạp</label>
                <select class="form-select filter-input slot-theater-select">
                    <option value="">-- Chọn rạp --</option>
                    ${theaterOptions}
                </select>
            </div>
            <div>
                <label class="form-label text-secondary" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.07em;">Phòng chiếu</label>
                <select class="form-select filter-input slot-screen-select" disabled>
                    <option value="">-- Chọn phòng --</option>
                </select>
            </div>
            <div>
                <label class="form-label text-secondary d-block" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.07em;opacity:0;">Xóa</label>
                <button type="button" class="slot-remove-btn" data-slot="${idx}">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;

        els.sSlotRows.appendChild(row);

        // Theater → Screen cascade
        const theaterSel = row.querySelector('.slot-theater-select');
        const screenSel = row.querySelector('.slot-screen-select');
        theaterSel.addEventListener('change', () => {
            loadScreensForTheater(theaterSel.value, screenSel);
        });

        // Remove
        row.querySelector('.slot-remove-btn').addEventListener('click', () => {
            row.remove();
        });
    }

    if (els.sAddSlotBtn) {
        els.sAddSlotBtn.addEventListener('click', () => addSlotRow());
    }

    /* ── Single-day: Submit ──────────────────────────────── */
    if (els.singleDayForm) {
        els.singleDayForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const rows = els.sSlotRows.querySelectorAll('.slot-row');
            if (rows.length === 0) {
                window.showAdminToast?.('Thêm ít nhất 1 suất chiếu', 'warning');
                return;
            }

            const slots = [];
            rows.forEach(row => {
                const time = row.querySelector('.slot-time-input')?.value;
                const screen = row.querySelector('.slot-screen-select')?.value;
                if (time && screen) {
                    slots.push({ time, screen_id: screen });
                }
            });

            if (slots.length === 0) {
                window.showAdminToast?.('Vui lòng chọn phòng chiếu cho mỗi suất', 'warning');
                return;
            }

            const data = {
                movie_id: els.sMovieId.value,
                date: els.sDate.value,
                price: els.sPrice.value,
                format_id: els.sFormatId.value || null,
                sound_id: els.sSoundId.value || null,
                subtitle_id: els.sSubtitleId.value || null,
                status: els.sStatusToggle?.checked ? 1 : 0,
                slots,
            };

            try {
                const res = await window.AdminCore.apiFetch('/api/v1/admin/showtimes/bulk-single', {
                    method: 'POST',
                    body: JSON.stringify(data),
                });
                if (res && res.ok) {
                    const result = await res.json();
                    window.showAdminToast?.(
                        `Tạo thành công ${result.data?.created || slots.length} suất chiếu!`,
                        'success'
                    );
                    els.singleDayForm.reset();
                    els.sSlotRows.innerHTML = '';
                    loadData(1);
                } else {
                    const errData = await res.json();
                    alert('Lỗi: ' + JSON.stringify(errData.errors || errData.message));
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    /* ── Theater → Screens (edit modal) ─────────────────── */
    if (els.formTheaterId) {
        els.formTheaterId.addEventListener('change', () => {
            loadScreensForTheater(els.formTheaterId.value, els.formScreenId);
        });
    }
    if (els.mTheaterId) {
        els.mTheaterId.addEventListener('change', () => {
            loadScreensForTheater(els.mTheaterId.value, els.mScreenId);
        });
    }

    /* ── Load & Render table ─────────────────────────────── */
    async function loadData(page = 1) {
        currentPage = page;
        els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;

        try {
            const url = new URL(window.location.origin + '/api/v1/admin/showtimes');
            url.searchParams.append('page', page);
            url.searchParams.append('status', 'all');
            if (els.dateFilter?.value) url.searchParams.append('date', els.dateFilter.value);
            if (els.movieFilter?.value) url.searchParams.append('movie_id', els.movieFilter.value);
            if (els.theaterFilter?.value) url.searchParams.append('theater_id', els.theaterFilter.value);

            const res = await window.AdminCore.apiFetch(url.toString());
            if (!res || !res.ok) throw new Error();

            const json = await res.json();
            // paginatedResponse: { data: [...], pagination: {...} }
            const showtimes = json.data || [];
            const pagination = json.pagination || {};

            renderTable(showtimes, pagination.from ?? 1);
            renderPagination(pagination);
            if (els.totalCount) els.totalCount.textContent = `${pagination.total ?? showtimes.length} suất chiếu`;
        } catch (err) {
            console.error(err);
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(showtimes, startIndex) {
        if (!showtimes || showtimes.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy lịch chiếu nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        showtimes.forEach((st, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const scheduledAt = new Date(st.scheduled_at);
            const timeStr = scheduledAt.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            const dateStr = scheduledAt.toLocaleDateString('vi-VN');
            const priceFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(st.price);
            const statusHtml = st.status
                ? '<span class="badge bg-success">Mở bán</span>'
                : '<span class="badge bg-secondary">Đóng</span>';

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="fw-bold" style="color:#818cf8;font-size:1.1rem;">${timeStr}</div>
                    <div class="small text-white-50">${dateStr}</div>
                </td>
                <td>
                    <div class="fw-medium text-white">${st.movie?.title || '—'}</div>
                </td>
                <td>
                    <div class="fw-medium text-white">${st.screen?.theater?.name || '—'}</div>
                    <div class="small text-white-50">Phòng: ${st.screen?.name || '—'}</div>
                </td>
                <td>
                    <div class="fw-medium text-white">${priceFmt}</div>
                    <div class="small mt-1">
                        ${st.format?.name ? `<span class="badge" style="background:rgba(96,165,250,0.12);color:#60a5fa;">${st.format.name}</span>` : ''}
                        ${st.sound?.name ? `<span class="badge ms-1" style="background:rgba(167,139,250,0.12);color:#a78bfa;">${st.sound.name}</span>` : ''}
                    </div>
                </td>
                <td class="text-center">${statusHtml}</td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-edit-showtime"
                            style="color:var(--text-secondary);background:rgba(255,255,255,0.05);"
                            data-showtime='${JSON.stringify(st).replace(/'/g, "&#39;")}' title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-showtime"
                            style="color:#ef4444;background:rgba(239,68,68,0.1);"
                            data-id="${st.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        if (!els.pagination || !meta || meta.last_page <= 1) {
            if (els.pagination) els.pagination.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination pagination-sm m-0">';
        html += meta.current_page > 1
            ? `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page - 1}">&laquo;</a></li>`
            : `<li class="page-item disabled"><span class="page-link">&laquo;</span></li>`;

        for (let i = 1; i <= meta.last_page; i++) {
            html += i === meta.current_page
                ? `<li class="page-item active"><span class="page-link">${i}</span></li>`
                : `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }

        html += meta.current_page < meta.last_page
            ? `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page + 1}">&raquo;</a></li>`
            : `<li class="page-item disabled"><span class="page-link">&raquo;</span></li>`;
        html += '</ul>';

        els.pagination.innerHTML = html;
        els.pagination.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                loadData(parseInt(a.dataset.page));
            });
        });
    }

    /* ── Table click: Edit / Delete ──────────────────────── */
    els.tableBody.addEventListener('click', async (e) => {
        const btnEdit = e.target.closest('.btn-edit-showtime');
        if (btnEdit) {
            const st = JSON.parse(btnEdit.dataset.showtime);
            els.idInput.value = st.id;

            // Fill dropdowns
            if (els.formMovieId) els.formMovieId.value = st.movie_id || '';
            if (els.formPrice) els.formPrice.value = st.price || '';
            if (els.formFormatId) els.formFormatId.value = st.format_id || '';
            if (els.formSoundId) els.formSoundId.value = st.sound_id || '';
            if (els.formSubtitleId) els.formSubtitleId.value = st.subtitle_id || '';
            if (els.formStatus) els.formStatus.checked = st.status === 1;

            if (st.scheduled_at && els.formScheduledAt) {
                const d = new Date(st.scheduled_at);
                els.formScheduledAt.value = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            }

            if (st.screen?.theater_id && els.formTheaterId) {
                els.formTheaterId.value = st.screen.theater_id;
                await loadScreensForTheater(st.screen.theater_id, els.formScreenId);
                if (els.formScreenId) els.formScreenId.value = st.screen_id || '';
            }

            getModalInstance().show();
            return;
        }

        const btnDel = e.target.closest('.btn-delete-showtime');
        if (btnDel) {
            if (!confirm('Xóa lịch chiếu này? Khách hàng đã đặt vé sẽ bị ảnh hưởng!')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/showtimes/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa thành công', 'success');
                    loadData(currentPage);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) {}
        }
    });

    /* ── Edit modal submit ───────────────────────────────── */
    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = els.idInput.value;
            const url = `/api/v1/admin/showtimes/${id}`;

            const formData = new FormData(els.form);
            const data = Object.fromEntries(formData.entries());
            data.status = els.formStatus.checked ? 1 : 0;

            if (data.scheduled_at) {
                const d = new Date(data.scheduled_at);
                data.scheduled_at = d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0') + ' ' +
                    String(d.getHours()).padStart(2, '0') + ':' +
                    String(d.getMinutes()).padStart(2, '0') + ':00';
            }

            try {
                const res = await window.AdminCore.apiFetch(url, { method: 'PUT', body: JSON.stringify(data) });
                if (res && res.ok) {
                    getModalInstance().hide();
                    window.showAdminToast?.('Cập nhật thành công!', 'success');
                    loadData(currentPage);
                } else {
                    const errData = await res.json();
                    alert('Lỗi: ' + JSON.stringify(errData.errors || errData.message));
                }
            } catch (err) { console.error(err); }
        });
    }

    /* ── Search form ─────────────────────────────────────── */
    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            loadData(1);
        });
    }

    /* ── Init ────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', async () => {
        await fetchPrerequisites();
        // Add 1 default slot row for single-day tab
        addSlotRow();
        loadData(1);
    });

})();
