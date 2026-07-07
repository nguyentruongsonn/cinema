/**
 * Showtimes Management - New Flow
 * Filter → Movies List → Click Movie → Show Showtimes
 */
(function () {
    'use strict';

    /* ── DOM Elements ────────────────────────────────────── */
    const els = {
        // Filter bar
        filterForm: document.getElementById('filterForm'),
        branchFilter: document.getElementById('branchFilter'),
        theaterFilter: document.getElementById('theaterFilter'),
        dateFilter: document.getElementById('dateFilter'),
        statusFilter: document.getElementById('statusFilter'),
        addShowtimeBtn: document.getElementById('addShowtimeBtn'),

        // Movies table
        moviesTableBody: document.getElementById('moviesTableBody'),
        movieCount: document.getElementById('movieCount'),

        // Showtimes panel
        showtimesPanel: document.getElementById('showtimesPanel'),
        selectedMovieTitle: document.getElementById('selectedMovieTitle'),
        showtimesTableBody: document.getElementById('showtimesTableBody'),
        showtimeCount: document.getElementById('showtimeCount'),
        backToMoviesBtn: document.getElementById('backToMoviesBtn'),
        paginationContainer: document.getElementById('paginationContainer'),

        // Add modal
        addModal: document.getElementById('addShowtimeModal'),
        multiDayForm: document.getElementById('multiDayForm'),
        singleDayForm: document.getElementById('singleDayForm'),

        // Multi-day form elements
        mMovieId: document.getElementById('mMovieId'),
        mTheaterId: document.getElementById('mTheaterId'),
        mScreenId: document.getElementById('mScreenId'),
        mDateFrom: document.getElementById('mDateFrom'),
        mDateTo: document.getElementById('mDateTo'),
        mTimeInput: document.getElementById('mTimeInput'),
        mAddTimeBtn: document.getElementById('mAddTimeBtn'),
        mFormatId: document.getElementById('mFormatId'),
        mVersionTypeId: document.getElementById('mVersionTypeId'),
        timeSlotTags: document.getElementById('timeSlotTags'),
        previewMultiBtn: document.getElementById('previewMultiBtn'),
        multiPreviewBlock: document.getElementById('multiPreviewBlock'),
        multiPreviewList: document.getElementById('multiPreviewList'),
        mMoviePoster: document.getElementById('mMoviePoster'),
        mMovieTitle: document.getElementById('mMovieTitle'),
        mMovieDuration: document.getElementById('mMovieDuration'),
        mMovieRelease: document.getElementById('mMovieRelease'),
        mMovieEnd: document.getElementById('mMovieEnd'),

        // Single-day form elements
        sMovieId: document.getElementById('sMovieId'),
        sDate: document.getElementById('sDate'),
        sFormatId: document.getElementById('sFormatId'),
        sVersionTypeId: document.getElementById('sVersionTypeId'),
        sStatusToggle: document.getElementById('sStatusToggle'),
        sSlotRows: document.getElementById('sSlotRows'),
        sAddSlotBtn: document.getElementById('sAddSlotBtn'),
        sMoviePoster: document.getElementById('sMoviePoster'),
        sMovieTitle: document.getElementById('sMovieTitle'),
        sMovieDuration: document.getElementById('sMovieDuration'),
        sMovieRelease: document.getElementById('sMovieRelease'),
        sMovieEnd: document.getElementById('sMovieEnd'),

        // Edit modal
        editModal: document.getElementById('editShowtimeModal'),
        editForm: document.getElementById('editShowtimeForm'),
        editIdInput: document.getElementById('editShowtimeIdInput'),
        editFormMovieId: document.getElementById('editFormMovieId'),
        editFormTheaterId: document.getElementById('editFormTheaterId'),
        editFormScreenId: document.getElementById('editFormScreenId'),
        editFormScheduledAt: document.getElementById('editFormScheduledAt'),
        editFormFormatId: document.getElementById('editFormFormatId'),
        editFormVersionTypeId: document.getElementById('editFormVersionTypeId'),
        editFormStatus: document.getElementById('editFormStatus'),
    };

    let cachedMovies = [];
    let cachedBranches = [];
    let cachedTheaters = [];
    let cachedFormats = [];
    let cachedVersionTypes = [];
    let multiTimeSlots = [];
    let singleSlotCount = 0;
    let selectedMovieId = null;
    let currentShowtimePage = 1;

    /* ── Utility Functions ───────────────────────────────── */
    function fillSelect(el, items, valueKey, labelKey, emptyLabel = '-- Chọn --') {
        if (!el) return;
        
        // Preserve current selected value
        const currentValue = el.value;
        
        el.innerHTML = `<option value="">${emptyLabel}</option>`;
        items.forEach(item => {
            el.innerHTML += `<option value="${item[valueKey]}">${item[labelKey]}</option>`;
        });
        
        // Restore selected value if it still exists in the new options
        if (currentValue) {
            const optionExists = items.some(item => String(item[valueKey]) === String(currentValue));
            if (optionExists) {
                el.value = currentValue;
            }
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function setTodayDate() {
        if (els.dateFilter) {
            const today = new Date().toISOString().split('T')[0];
            els.dateFilter.value = today;
        }
    }

    /* ── Fetch Prerequisites ─────────────────────────────── */
    async function fetchPrerequisites() {
        try {
            // Fetch movies
            const mRes = await fetch('/api/v1/movies?per_page=200&status=all', {
                headers: { Accept: 'application/json' }
            });
            if (mRes.ok) {
                const mData = await mRes.json();
                cachedMovies = mData.data || [];
                [els.mMovieId, els.sMovieId, els.editFormMovieId].forEach(el =>
                    fillSelect(el, cachedMovies, 'id', 'title', '-- Chọn phim --')
                );
            }

            // Fetch branches
            const bRes = await window.AdminCore.apiFetch('/api/v1/admin/branches?per_page=200');
            if (bRes && bRes.ok) {
                const bData = await bRes.json();
                cachedBranches = bData.data || [];
                fillSelect(els.branchFilter, cachedBranches, 'id', 'name', 'Tất cả chi nhánh');
            }

            // Fetch theaters, formats, version types from screens meta
            const sRes = await window.AdminCore.apiFetch('/api/v1/admin/screens?per_page=1');
            if (sRes && sRes.ok) {
                const sData = await sRes.json();
                cachedTheaters = sData.theaters || [];
                cachedFormats = sData.formats || [];
                cachedVersionTypes = sData.version_types || [];

                fillSelect(els.theaterFilter, cachedTheaters, 'id', 'name', 'Tất cả rạp');
                [els.mTheaterId, els.editFormTheaterId].forEach(el =>
                    fillSelect(el, cachedTheaters, 'id', 'name', '-- Chọn rạp --')
                );
                [els.mFormatId, els.sFormatId, els.editFormFormatId].forEach(el =>
                    fillSelect(el, cachedFormats, 'id', 'name', '-- Mặc định --')
                );
                [els.mVersionTypeId, els.sVersionTypeId, els.editFormVersionTypeId].forEach(el =>
                    fillSelect(el, cachedVersionTypes, 'id', 'name', '-- Chọn phiên bản --')
                );
            }
        } catch (err) {
            console.error('fetchPrerequisites error:', err);
        }
    }

    /* ── Load Movies List ────────────────────────────────── */
    async function loadMoviesList() {
        // Skeleton loading is now handled in HTML blade template
        // els.moviesTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;

        try {
            const params = new URLSearchParams();
            params.append('per_page', '200');
            params.append('status', 'all');
            params.append('include', 'categories');

            const res = await fetch(`/api/v1/movies?${params}`, {
                headers: { Accept: 'application/json' }
            });

            if (!res.ok) throw new Error('Failed to load movies');

            const data = await res.json();
            const movies = data.data || [];

            if (movies.length === 0) {
                els.moviesTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không có phim nào.</td></tr>`;
                els.movieCount.textContent = '0 phim';
                return;
            }

            renderMoviesTable(movies);
            els.movieCount.textContent = `${movies.length} phim`;

        } catch (err) {
            console.error('loadMoviesList error:', err);
            els.moviesTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderMoviesTable(movies) {
        els.moviesTableBody.innerHTML = '';

        movies.forEach((movie, index) => {
            const tr = document.createElement('tr');
            tr.className = 'movie-row';
            tr.dataset.movieId = movie.id;

            const posterUrl = movie.poster_path
                ? `/storage/${movie.poster_path}`
                : (movie.poster_url || '/images/default-poster.jpg');

            const categories = movie.categories && movie.categories.length > 0
                ? movie.categories.map(c => c.name).join(', ')
                : '—';

            tr.innerHTML = `
                <td class="text-center text-white-50 movie-stt">${index + 1}</td>
                <td class="movie-info-cell">
                    <div class="d-flex align-items-center gap-3">
                        <img src="${posterUrl}" alt="${movie.title}" class="movie-poster-thumb">
                        <div class="movie-text-info">
                            <div class="movie-title">${movie.title}</div>
                            <div class="movie-release">${formatDate(movie.release_date)}</div>
                        </div>
                    </div>
                </td>
                <td class="text-center text-white movie-duration">${movie.duration || '—'} phút</td>
                <td class="text-white-50 movie-categories">${categories}</td>
            `;

            tr.addEventListener('click', () => {
                selectedMovieId = movie.id;
                showMovieShowtimes(movie);
            });

            els.moviesTableBody.appendChild(tr);
        });
    }

    /* ── Show Showtimes for Selected Movie ───────────────── */
    async function showMovieShowtimes(movie) {
        // Hide movies table, show showtimes panel
        document.getElementById('moviesPanel').style.display = 'none';
        els.showtimesPanel.style.display = 'block';
        els.selectedMovieTitle.textContent = movie.title;

        // Load showtimes
        await loadShowtimesForMovie(movie.id, 1);
    }

    async function loadShowtimesForMovie(movieId, page = 1) {
        currentShowtimePage = page;
        // Skeleton loading is now handled in HTML blade template
        // els.showtimesTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;

        try {
            const url = new URL(window.location.origin + '/api/v1/admin/showtimes');
            url.searchParams.append('page', page);
            url.searchParams.append('movie_id', movieId);
            url.searchParams.append('status', 'all');

            // Apply filters
            if (els.dateFilter?.value) url.searchParams.append('date', els.dateFilter.value);
            if (els.theaterFilter?.value) url.searchParams.append('theater_id', els.theaterFilter.value);
            if (els.statusFilter?.value) url.searchParams.append('status', els.statusFilter.value);

            const res = await window.AdminCore.apiFetch(url.toString());
            if (!res || !res.ok) throw new Error();

            const json = await res.json();
            const showtimes = json.data || [];
            const pagination = json.pagination || {};

            renderShowtimesTable(showtimes, pagination.from ?? 1);
            renderPagination(pagination);
            els.showtimeCount.textContent = `${pagination.total ?? showtimes.length} suất chiếu`;

        } catch (err) {
            console.error(err);
            els.showtimesTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderShowtimesTable(showtimes, startIndex) {
        if (!showtimes || showtimes.length === 0) {
            els.showtimesTableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không có suất chiếu nào.</td></tr>`;
            return;
        }

        els.showtimesTableBody.innerHTML = '';
        showtimes.forEach((st, index) => {
            const tr = document.createElement('tr');
            tr.className = 'showtime-row';

            // Time calculation - end time is calculated from actual movie duration
            const scheduledAt = new Date(st.scheduled_at);
            const movieDuration = st.movie?.duration || 120; // Minutes from database, fallback 120
            const endTime = new Date(scheduledAt.getTime() + movieDuration * 60000); // Convert to milliseconds

            const startTimeStr = scheduledAt.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            const endTimeStr = endTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            const dateStr = scheduledAt.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });

            // Format info
            const formatName = st.format?.name || '2D';
            const versionName = st.version_type?.name || 'Phụ đề';
            const formatDisplay = `${formatName} - ${versionName}`;

            // Capacity
            const capacity = st.screen?.capacity || st.screen?.total_seats || '—';

            // Toggle switch for status
            const toggleId = `toggle-${st.id}`;
            const checked = st.status ? 'checked' : '';

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td class="showtime-time-cell">
                    <div class="showtime-time">${startTimeStr} - ${endTimeStr}</div>
                    <div class="showtime-date">${dateStr}</div>
                </td>
                <td>
                    <div class="screen-name-display">${st.screen?.name || '—'}</div>
                    <div class="theater-name-sub">${st.screen?.theater?.name || '—'}</div>
                </td>
                <td class="text-center text-white">${capacity}</td>
                <td class="format-display">${formatDisplay}</td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input status-toggle" type="checkbox" id="${toggleId}" ${checked} data-id="${st.id}">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-edit-showtime" data-showtime='${JSON.stringify(st).replace(/'/g, "&#39;")}' title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-showtime" data-id="${st.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.showtimesTableBody.appendChild(tr);
        });
    }

    function renderPagination(pag) {
        if (!els.paginationContainer) return;
        if (!pag || pag.total <= pag.per_page) {
            els.paginationContainer.innerHTML = '';
            return;
        }

        let html = '<nav><ul class="pagination mb-0">';
        for (let i = 1; i <= (pag.last_page || 1); i++) {
            const active = i === pag.current_page ? 'active' : '';
            html += `<li class="page-item ${active}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }
        html += '</ul></nav>';
        els.paginationContainer.innerHTML = html;

        els.paginationContainer.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page);
                if (selectedMovieId) {
                    loadShowtimesForMovie(selectedMovieId, page);
                }
            });
        });
    }

    /* ── Back to Movies Button ────────────────────────────── */
    function backToMoviesList() {
        els.showtimesPanel.style.display = 'none';
        document.getElementById('moviesPanel').style.display = 'block';
        selectedMovieId = null;
    }

    /* ── Filter Handlers ─────────────────────────────────── */
    function handleFilterSubmit(e) {
        e.preventDefault();
        if (selectedMovieId) {
            // Reload showtimes with filters
            loadShowtimesForMovie(selectedMovieId, 1);
        } else {
            // Just reload movies list (filters don't affect it in current flow)
            loadMoviesList();
        }
    }

    function handleBranchChange() {
        const branchId = els.branchFilter?.value;
        if (!branchId) {
            fillSelect(els.theaterFilter, cachedTheaters, 'id', 'name', 'Tất cả rạp');
            return;
        }
        const filtered = cachedTheaters.filter(t => t.branch_id == branchId);
        fillSelect(els.theaterFilter, filtered, 'id', 'name', 'Tất cả rạp');
    }

    /* ── Add Modal Management ────────────────────────────── */
    function openAddModal() {
        const bsModal = new bootstrap.Modal(els.addModal);
        bsModal.show();
        resetMultiDayForm();
        resetSingleDayForm();
    }

    function resetMultiDayForm() {
        els.multiDayForm?.reset();
        multiTimeSlots = [];
        renderTimeSlotTags();
        els.multiPreviewBlock?.classList.add('d-none');
        resetMovieInfo('m');
    }

    function resetSingleDayForm() {
        els.singleDayForm?.reset();
        singleSlotCount = 0;
        if (els.sSlotRows) els.sSlotRows.innerHTML = '';
        addSingleSlotRow();
        resetMovieInfo('s');
    }

    function resetMovieInfo(prefix) {
        const poster = document.getElementById(`${prefix}MoviePoster`);
        const title = document.getElementById(`${prefix}MovieTitle`);
        const duration = document.getElementById(`${prefix}MovieDuration`);
        const release = document.getElementById(`${prefix}MovieRelease`);
        const end = document.getElementById(`${prefix}MovieEnd`);

        if (poster) poster.src = '/images/default-poster.jpg';
        if (title) title.textContent = 'Chọn phim để xem thông tin';
        if (duration) duration.textContent = '—';
        if (release) release.textContent = '—';
        if (end) end.textContent = '—';
    }

    /* ── Multi-day Form: Time Slots ──────────────────────── */
    function addTimeSlot() {
        const time = els.mTimeInput?.value;
        if (!time) return;
        if (multiTimeSlots.includes(time)) {
            alert('Giờ này đã được thêm!');
            return;
        }
        multiTimeSlots.push(time);
        multiTimeSlots.sort();
        renderTimeSlotTags();
        if (els.mTimeInput) els.mTimeInput.value = '';
    }

    function removeTimeSlot(time) {
        multiTimeSlots = multiTimeSlots.filter(t => t !== time);
        renderTimeSlotTags();
    }

    function renderTimeSlotTags() {
        if (!els.timeSlotTags) return;
        els.timeSlotTags.innerHTML = '';
        multiTimeSlots.forEach(time => {
            const tag = document.createElement('span');
            tag.className = 'time-slot-tag';
            tag.innerHTML = `${time} <button class="remove-tag" type="button">&times;</button>`;
            tag.querySelector('.remove-tag').addEventListener('click', () => removeTimeSlot(time));
            els.timeSlotTags.appendChild(tag);
        });
    }

    function previewMultiDay() {
        if (!els.mDateFrom?.value || !els.mDateTo?.value || multiTimeSlots.length === 0) {
            alert('Vui lòng điền đủ: Ngày từ, Ngày đến và ít nhất 1 giờ chiếu!');
            return;
        }

        const from = new Date(els.mDateFrom.value);
        const to = new Date(els.mDateTo.value);
        if (from > to) {
            alert('Ngày bắt đầu phải nhỏ hơn ngày kết thúc!');
            return;
        }

        const days = [];
        for (let d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
            days.push(new Date(d));
        }

        els.multiPreviewBlock?.classList.remove('d-none');
        if (els.multiPreviewList) {
            els.multiPreviewList.innerHTML = '';
            days.forEach(day => {
                const dateStr = day.toLocaleDateString('vi-VN');
                multiTimeSlots.forEach(time => {
                    const badge = document.createElement('span');
                    badge.className = 'preview-slot-badge';
                    badge.innerHTML = `<i class="bi bi-calendar-check me-1"></i>${dateStr} ${time}`;
                    els.multiPreviewList.appendChild(badge);
                });
            });
        }
    }

    async function handleMultiDaySubmit(e) {
        e.preventDefault();
        if (multiTimeSlots.length === 0) {
            alert('Vui lòng thêm ít nhất 1 giờ chiếu!');
            return;
        }

        const formData = new FormData(els.multiDayForm);
        formData.append('time_slots', JSON.stringify(multiTimeSlots));
        formData.append('status', '1');

        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/showtimes/bulk', {
                method: 'POST',
                body: formData
            });

            if (!res || !res.ok) throw new Error();
            const json = await res.json();

            window.AdminCore.showToast(json.message || 'Tạo lịch chiếu thành công!', 'success');
            bootstrap.Modal.getInstance(els.addModal)?.hide();
            if (selectedMovieId) {
                loadShowtimesForMovie(selectedMovieId, 1);
            } else {
                loadMoviesList();
            }
        } catch (err) {
            console.error(err);
            window.AdminCore.showToast('Lỗi tạo lịch chiếu!', 'error');
        }
    }

    /* ── Single-day Form: Slot Rows ──────────────────────── */
    function addSingleSlotRow() {
        singleSlotCount++;
        const row = document.createElement('div');
        row.className = 'slot-row';
        row.dataset.slotId = singleSlotCount;

        row.innerHTML = `
            <div class="slot-time"><input type="time" class="filter-input" name="times[]" required></div>
            <div><select class="filter-input slot-theater-select" name="theater_ids[]" required><option value="">-- Chọn rạp --</option></select></div>
            <div><select class="filter-input slot-screen-select" name="screen_ids[]" required disabled><option value="">-- Chọn phòng --</option></select></div>
            <button type="button" class="slot-remove-btn" ${singleSlotCount === 1 ? 'disabled' : ''}>Xóa</button>
        `;

        const theaterSel = row.querySelector('.slot-theater-select');
        const screenSel = row.querySelector('.slot-screen-select');
        fillSelect(theaterSel, cachedTheaters, 'id', 'name', '-- Chọn rạp --');

        theaterSel.addEventListener('change', async () => {
            const tid = theaterSel.value;
            screenSel.disabled = !tid;
            if (!tid) {
                screenSel.innerHTML = '<option value="">-- Chọn phòng --</option>';
                return;
            }

            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens?theater_id=${tid}&per_page=200`);
                if (res && res.ok) {
                    const json = await res.json();
                    fillSelect(screenSel, json.data || [], 'id', 'name', '-- Chọn phòng --');
                }
            } catch (err) {
                console.error(err);
            }
        });

        row.querySelector('.slot-remove-btn').addEventListener('click', () => {
            if (els.sSlotRows.children.length > 1) {
                row.remove();
            }
        });

        els.sSlotRows?.appendChild(row);
    }

    async function handleSingleDaySubmit(e) {
        e.preventDefault();
        const formData = new FormData(els.singleDayForm);
        const status = els.sStatusToggle?.checked ? '1' : '0';
        formData.append('status', status);

        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/showtimes/single-day', {
                method: 'POST',
                body: formData
            });

            if (!res || !res.ok) throw new Error();
            const json = await res.json();

            window.AdminCore.showToast(json.message || 'Tạo lịch chiếu thành công!', 'success');
            bootstrap.Modal.getInstance(els.addModal)?.hide();
            if (selectedMovieId) {
                loadShowtimesForMovie(selectedMovieId, 1);
            } else {
                loadMoviesList();
            }
        } catch (err) {
            console.error(err);
            window.AdminCore.showToast('Lỗi tạo lịch chiếu!', 'error');
        }
    }

    /* ── Movie Info Update ───────────────────────────────── */
    function updateMovieInfo(prefix, movieId) {
        const movie = cachedMovies.find(m => m.id == movieId);
        if (!movie) {
            resetMovieInfo(prefix);
            return;
        }

        const poster = document.getElementById(`${prefix}MoviePoster`);
        const title = document.getElementById(`${prefix}MovieTitle`);
        const duration = document.getElementById(`${prefix}MovieDuration`);
        const release = document.getElementById(`${prefix}MovieRelease`);
        const end = document.getElementById(`${prefix}MovieEnd`);

        const posterUrl = movie.poster_path
            ? `/storage/${movie.poster_path}`
            : (movie.poster_url || '/images/default-poster.jpg');

        if (poster) poster.src = posterUrl;
        if (title) title.textContent = movie.title || '—';
        if (duration) duration.textContent = movie.duration || '—';
        if (release) release.textContent = formatDate(movie.release_date);
        if (end) end.textContent = formatDate(movie.end_date);
    }

    /* ── Edit Modal ──────────────────────────────────────── */
    async function openEditModal(showtime) {
        console.log('[EDIT MODAL] Opening with showtime:', showtime);

        els.editIdInput.value = showtime.id;
        els.editFormMovieId.value = showtime.movie_id || '';
        els.editFormTheaterId.value = showtime.screen?.theater_id || '';
        els.editFormScheduledAt.value = showtime.scheduled_at?.slice(0, 16) || '';
        els.editFormFormatId.value = showtime.format_id || '';
        els.editFormVersionTypeId.value = showtime.version_type_id || '';
        els.editFormStatus.checked = !!showtime.status;

        console.log('[EDIT MODAL] Theater ID:', showtime.screen?.theater_id);
        console.log('[EDIT MODAL] Screen ID to set:', showtime.screen_id);
        console.log('[EDIT MODAL] editFormScreenId element:', els.editFormScreenId);

        // Load screens for theater and wait for completion
        if (showtime.screen?.theater_id) {
            console.log('[EDIT MODAL] Loading screens for theater:', showtime.screen.theater_id);
            await loadScreensForTheater(showtime.screen.theater_id, els.editFormScreenId);
            console.log('[EDIT MODAL] Screens loaded, setting value to:', showtime.screen_id);
            els.editFormScreenId.value = showtime.screen_id || '';
            console.log('[EDIT MODAL] Screen dropdown value after set:', els.editFormScreenId.value);
        }

        const bsModal = new bootstrap.Modal(els.editModal);
        bsModal.show();
    }

    async function loadScreensForTheater(theaterId, screenEl) {
        console.log('[LOAD SCREENS] Called with theaterId:', theaterId, 'element:', screenEl);

        if (!screenEl) {
            console.error('[LOAD SCREENS] Screen element is null!');
            return;
        }

        screenEl.disabled = !theaterId;
        if (!theaterId) {
            screenEl.innerHTML = '<option value="">-- Chọn phòng --</option>';
            return;
        }

        try {
            // Use PUBLIC endpoint (supports theater_id filter) instead of admin endpoint
            const url = `/api/v1/screens?theater_id=${theaterId}&per_page=200&status=all`;
            console.log('[LOAD SCREENS] Fetching from:', url);

            const res = await window.AdminCore.apiFetch(url);
            console.log('[LOAD SCREENS] Response:', res);

            if (res && res.ok) {
                const json = await res.json();
                console.log('[LOAD SCREENS] JSON response:', json);
                console.log('[LOAD SCREENS] json.data:', json.data);
                console.log('[LOAD SCREENS] Number of screens:', json.data?.length);

                // Public endpoint returns { data: [...], pagination: {...} }
                const screens = json.data || [];
                fillSelect(screenEl, screens, 'id', 'name', '-- Chọn phòng --');
                console.log('[LOAD SCREENS] Dropdown populated with', screens.length, 'screens');
            } else {
                console.error('[LOAD SCREENS] API call failed, response:', res);
            }
        } catch (err) {
            console.error('[LOAD SCREENS] Exception:', err);
        }
    }

    async function handleEditSubmit(e) {
        e.preventDefault();
        const id = els.editIdInput.value;
        const formData = new FormData(els.editForm);
        formData.append('status', els.editFormStatus.checked ? '1' : '0');

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/showtimes/${id}`, {
                method: 'PUT',
                body: formData
            });

            if (!res || !res.ok) throw new Error();
            const json = await res.json();

            window.AdminCore.showToast(json.message || 'Cập nhật thành công!', 'success');
            bootstrap.Modal.getInstance(els.editModal)?.hide();
            if (selectedMovieId) {
                loadShowtimesForMovie(selectedMovieId, currentShowtimePage);
            }
        } catch (err) {
            console.error(err);
            window.AdminCore.showToast('Lỗi cập nhật lịch chiếu!', 'error');
        }
    }

    /* ── Delete Showtime ─────────────────────────────────── */
    async function deleteShowtime(id) {
        if (!confirm('Xác nhận xóa suất chiếu này?')) return;

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/showtimes/${id}`, {
                method: 'DELETE'
            });

            if (!res || !res.ok) throw new Error();
            const json = await res.json();

            window.AdminCore.showToast(json.message || 'Xóa thành công!', 'success');
            if (selectedMovieId) {
                loadShowtimesForMovie(selectedMovieId, currentShowtimePage);
            }
        } catch (err) {
            console.error(err);
            window.AdminCore.showToast('Lỗi xóa suất chiếu!', 'error');
        }
    }

    /* ── Event Listeners Setup ───────────────────────────── */
    function setupEventListeners() {
        // Filter form
        els.filterForm?.addEventListener('submit', handleFilterSubmit);
        els.branchFilter?.addEventListener('change', handleBranchChange);

        // Back to movies
        els.backToMoviesBtn?.addEventListener('click', backToMoviesList);

        // Add showtime button
        els.addShowtimeBtn?.addEventListener('click', openAddModal);

        // Multi-day form
        els.mAddTimeBtn?.addEventListener('click', addTimeSlot);
        els.mTimeInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTimeSlot();
            }
        });
        els.previewMultiBtn?.addEventListener('click', previewMultiDay);
        els.multiDayForm?.addEventListener('submit', handleMultiDaySubmit);
        els.mMovieId?.addEventListener('change', () => updateMovieInfo('m', els.mMovieId.value));
        els.mTheaterId?.addEventListener('change', () => {
            const tid = els.mTheaterId.value;
            els.mScreenId.disabled = !tid;
            if (!tid) {
                els.mScreenId.innerHTML = '<option value="">-- Chọn phòng --</option>';
                return;
            }
            loadScreensForTheater(tid, els.mScreenId);
        });

        // Single-day form
        els.sAddSlotBtn?.addEventListener('click', addSingleSlotRow);
        els.singleDayForm?.addEventListener('submit', handleSingleDaySubmit);
        els.sMovieId?.addEventListener('change', () => updateMovieInfo('s', els.sMovieId.value));

        // Edit form
        els.editForm?.addEventListener('submit', handleEditSubmit);
        els.editFormTheaterId?.addEventListener('change', () => {
            const tid = els.editFormTheaterId.value;
            els.editFormScreenId.disabled = !tid;
            if (!tid) {
                els.editFormScreenId.innerHTML = '<option value="">-- Chọn phòng chiếu --</option>';
                return;
            }
            loadScreensForTheater(tid, els.editFormScreenId);
        });

        // Event delegation for edit/delete buttons
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.btn-edit-showtime')) {
                const btn = e.target.closest('.btn-edit-showtime');
                const showtime = JSON.parse(btn.dataset.showtime);
                await openEditModal(showtime); // Await to ensure screens load before modal shows
            }

            if (e.target.closest('.btn-delete-showtime')) {
                const btn = e.target.closest('.btn-delete-showtime');
                const id = btn.dataset.id;
                deleteShowtime(id);
            }
        });

        // Event delegation for status toggle switches
        document.addEventListener('change', async (e) => {
            if (e.target.classList.contains('status-toggle')) {
                const toggle = e.target;
                const id = toggle.dataset.id;
                const newStatus = toggle.checked ? 1 : 0;

                try {
                    const formData = new FormData();
                    formData.append('status', newStatus);

                    const res = await window.AdminCore.apiFetch(`/api/v1/admin/showtimes/${id}/status`, {
                        method: 'PUT',
                        body: formData
                    });

                    if (!res || !res.ok) {
                        // Revert toggle if failed
                        toggle.checked = !toggle.checked;
                        throw new Error('Failed to update status');
                    }

                    const json = await res.json();
                    window.AdminCore.showToast(json.message || 'Cập nhật trạng thái thành công!', 'success');
                } catch (err) {
                    console.error('Toggle status error:', err);
                    window.AdminCore.showToast('Lỗi cập nhật trạng thái!', 'error');
                    // Revert toggle on error
                    toggle.checked = !toggle.checked;
                }
            }
        });
    }

    /* ── Initialize ──────────────────────────────────────── */
    async function init() {
        console.log('[Showtimes] Initializing...');

        // Set default date to today
        setTodayDate();

        // Fetch all prerequisites
        await fetchPrerequisites();

        // Load movies list
        await loadMoviesList();

        // Setup all event listeners
        setupEventListeners();

        console.log('[Showtimes] Ready.');
    }

    // Auto-init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
