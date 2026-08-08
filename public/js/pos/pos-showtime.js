/**
 * pos-showtime.js – Showtime listing & selection for POS (Redesigned)
 */
(function (global) {
    'use strict';

    const cfg = global.POS_CONFIG;
    const { api, formatTime, renderEmptyState, toast } = global.PosUtils;

    let showtimes = [];
    let selectedShowtime = null;
    let selectedMovie = null;
    let selectedDate = new Date();

    const container = document.getElementById('showtimeContainer');
    // ── Bind Filter Bar ──────────────────────────────────────────────────
    const filterInput = document.getElementById('posFilterDate');
    const filterBtn = document.getElementById('btnApplyDateFilter');
    const shortcutBtns = document.querySelectorAll('.pos-date-shortcut');

    function updateDateFilterUI() {
        if (filterInput) filterInput.value = selectedDate.toISOString().split('T')[0];
        
        shortcutBtns.forEach(btn => {
            const offset = parseInt(btn.dataset.offset, 10);
            const targetDate = new Date();
            targetDate.setDate(targetDate.getDate() + offset);
            
            if (targetDate.toDateString() === selectedDate.toDateString()) {
                btn.classList.add('active');
                btn.classList.replace('admin-btn-secondary', 'admin-btn-primary');
            } else {
                btn.classList.remove('active');
                btn.classList.replace('admin-btn-primary', 'admin-btn-secondary');
            }
        });
    }

    // Initialize Filter Bar
    if (filterInput) {
        updateDateFilterUI();
        
        filterInput.addEventListener('change', (e) => {
            selectedDate = new Date(e.target.value);
            updateDateFilterUI();
            load();
        });

        shortcutBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const offset = parseInt(btn.dataset.offset, 10);
                selectedDate = new Date();
                selectedDate.setDate(selectedDate.getDate() + offset);
                updateDateFilterUI();
                load();
            });
        });

        if (filterBtn) {
            filterBtn.addEventListener('click', () => load());
        }
    }

    // ── Load showtimes ──────────────────────────────────────────────────
    async function load() {
        const skeletonHtml = `
            <div class="pos-movie-container">
                ${Array(3).fill(`
                <div class="pos-movie-card">
                    <div class="pos-movie-poster admin-skeleton pos-skeleton-poster"></div>
                    <div class="pos-movie-details w-100">
                        <div class="admin-skeleton admin-skeleton-text skeleton-w-60 skeleton-mb-md"></div>
                        <div class="admin-skeleton admin-skeleton-text skeleton-w-40 skeleton-mb-md"></div>
                        <div class="d-flex gap-2 mt-4">
                            <div class="admin-skeleton admin-skeleton-badge skeleton-w-60"></div>
                            <div class="admin-skeleton admin-skeleton-badge skeleton-w-60"></div>
                            <div class="admin-skeleton admin-skeleton-badge skeleton-w-60"></div>
                        </div>
                    </div>
                </div>
                `).join('')}
            </div>
        `;
        container.innerHTML = skeletonHtml;
        
        try {
            const dateStr = selectedDate.toISOString().split('T')[0];
            const res = await api.get(`${cfg.apiBase}/showtimes?date=${dateStr}`);
            showtimes = res.data || [];
            render();
        } catch (err) {
            renderEmptyState(container, err.message || 'Không thể tải suất chiếu');
        }
    }

    // ── Render ──────────────────────────────────────────────────────────
    function render() {
        let html = '';

        if (!showtimes.length) {
            html += '<div class="pos-empty"><div class="pos-empty-icon">🎬</div><div class="pos-empty-text">Không có suất chiếu cho ngày này</div></div>';
            container.innerHTML = html;
            return;
        }

        html += '<div class="pos-movie-container">';

        // Group by movie
        const movieMap = {};
        showtimes.forEach(s => {
            const mid = s.movie_id || s.movie?.id;
            if (!movieMap[mid]) movieMap[mid] = { movie: s.movie || { title: 'Phim #' + mid }, formats: {} };
            
            // Group by format (Standard, IMAX, etc.)
            const format = s.format?.name || s.room?.format || 'Standard';
            if (!movieMap[mid].formats[format]) movieMap[mid].formats[format] = [];
            movieMap[mid].formats[format].push(s);
        });
        Object.values(movieMap).forEach(group => {
            const m = group.movie;
            const posterUrl = m.poster_display_url || m.poster_url || m.poster_path || '';
            const selMovieCls = selectedMovie === (m.id || m.movie_id) ? ' selected' : '';
            
            html += `
                <div class="pos-movie-card">
                    ${posterUrl ? `<img class="pos-movie-poster${selMovieCls}" src="${posterUrl}" alt="${m.title}" loading="lazy">` : '<div class="pos-movie-poster pos-poster-placeholder">🎬</div>'}
                    
                    <div class="pos-movie-details">
                        <div class="pos-movie-title">${m.title || '–'}</div>
                        <div class="pos-movie-tags">
                            <span class="pos-tag">${m.age_rating || 'PG-13'}</span>
                            <span class="pos-tag">${m.duration || '?'} phút</span>
                            <span class="pos-tag">Phim</span>
                        </div>
            `;
            
            // Render formats and times
            for (const [format, times] of Object.entries(group.formats)) {
                html += `
                        <div class="pos-format-group">
                            <div class="pos-format-title"><i class="bi bi-film"></i> ${format}</div>
                            <div class="pos-times">
                `;
                times.forEach(s => {
                    const t = new Date(s.scheduled_at || s.start_time);
                    const now = new Date();
                    const isPast15Mins = now.getTime() > (t.getTime() + 15 * 60 * 1000);
                    const selTimeCls = selectedShowtime?.id === s.id ? ' active' : '';
                    const timeStr = `${t.getHours().toString().padStart(2, '0')}:${t.getMinutes().toString().padStart(2, '0')}`;
                    
                    if (isPast15Mins) {
                        html += `<button class="pos-time-btn disabled" disabled title="Suất chiếu đã quá 15 phút">${timeStr}</button>`;
                    } else {
                        html += `<button class="pos-time-btn${selTimeCls}" data-showtime-id="${s.id}">${timeStr}</button>`;
                    }
                });
                html += `
                            </div>
                        </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
        });
        
        html += '</div>'; // Close pos-movie-container
        container.innerHTML = html;

        // Bind time clicks (only non-disabled buttons)
        container.querySelectorAll('.pos-time-btn:not(:disabled)').forEach(btn => {
            btn.addEventListener('click', () => selectShowtime(parseInt(btn.dataset.showtimeId, 10)));
        });
    }

    // ── Select ────────────────────────────────────────────
    function selectShowtime(id) {
        if (selectedShowtime?.id === id) {
            selectedShowtime = null;
            selectedMovie = null;
            render();
            document.dispatchEvent(new CustomEvent('pos:showtime_cleared'));
            return;
        }

        selectedShowtime = showtimes.find(s => s.id === id) || null;
        if (!selectedShowtime) return;

        const t = new Date(selectedShowtime.scheduled_at || selectedShowtime.start_time);
        const now = new Date();
        if (now.getTime() > (t.getTime() + 15 * 60 * 1000)) {
            selectedShowtime = null;
            return;
        }

        selectedMovie = selectedShowtime.movie_id || selectedShowtime.movie?.id;
        
        // Add movie info to the selected showtime object to be used by pos-app
        if (selectedShowtime.movie) {
            selectedShowtime.movie_title = selectedShowtime.movie.title;
            selectedShowtime.movie_poster = selectedShowtime.movie.poster_display_url || selectedShowtime.movie.poster_url || selectedShowtime.movie.poster_path;
        }
        
        render(); // re-render to show selected state
        
        document.dispatchEvent(new CustomEvent('pos:showtime_selected', { detail: { showtime: selectedShowtime } }));
    }

    function getSelected() { return selectedShowtime; }

    function reset() {
        selectedShowtime = null;
        selectedMovie = null;
        render();
        global.PosApp?.disableNext?.();
    }

    global.PosShowtime = { load, getSelected, reset };

})(window);
