(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Configuration                                                      */
    /* ------------------------------------------------------------------ */
    const API_BASE = '/api';
    const SELECTORS = {
        heroSkeleton: '#heroSkeleton',
        heroContent: '#heroContent',
        theaterFilterContainer: '#theaterFilterContainer',
        theaterFilter: '#theaterFilter',
        showtimesSkeleton: '#showtimesSkeleton',
        showtimesContent: '#showtimesContent',
        noShowtimes: '#noShowtimes',
        trendingSkeleton: '#trendingSkeleton',
        trendingContent: '#trendingContent',
    };

    /* ------------------------------------------------------------------ */
    /*  State                                                              */
    /* ------------------------------------------------------------------ */
    const state = {
        movieSlug: null,
        movie: null,
        showtimeGroups: [],
        selectedDate: null,
        selectedTheater: '',
        theaters: [],
        isLoading: false,
    };

    /* ------------------------------------------------------------------ */
    /*  DOM References                                                     */
    /* ------------------------------------------------------------------ */
    let els = {};

    function cacheDoms() {
        els = {
            heroSkeleton: document.querySelector(SELECTORS.heroSkeleton),
            heroContent: document.querySelector(SELECTORS.heroContent),
            theaterFilterContainer: document.querySelector(SELECTORS.theaterFilterContainer),
            theaterFilter: document.querySelector(SELECTORS.theaterFilter),
            showtimesSkeleton: document.querySelector(SELECTORS.showtimesSkeleton),
            showtimesContent: document.querySelector(SELECTORS.showtimesContent),
            noShowtimes: document.querySelector(SELECTORS.noShowtimes),
            trendingSkeleton: document.querySelector(SELECTORS.trendingSkeleton),
            trendingContent: document.querySelector(SELECTORS.trendingContent),
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */
    function sanitize(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function getMovieSlugFromUrl() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        return pathParts[pathParts.length - 1] || null;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function getDateParts(dateStr) {
        const date = new Date(`${dateStr}T00:00:00`);
        return {
            day: date.getDate(),
            month: date.toLocaleDateString('en-US', { month: 'short' }),
            weekday: date.toLocaleDateString('en-US', { weekday: 'short' }),
        };
    }

    function getUniqueDates() {
        const dates = new Set();

        state.showtimeGroups.forEach((theaterGroup) => {
            theaterGroup.formats.forEach((formatGroup) => {
                formatGroup.showtimes.forEach((showtime) => {
                    if (showtime.scheduled_date) {
                        dates.add(showtime.scheduled_date);
                    }
                });
            });
        });

        return Array.from(dates).sort().slice(0, 5);
    }

    function extractTheaters() {
        return state.showtimeGroups.map((group) => group.theater);
    }

    function filterShowtimeGroups() {
        return state.showtimeGroups
            .filter((group) => !state.selectedTheater || String(group.theater.id) === String(state.selectedTheater))
            .map((group) => ({
                theater: group.theater,
                formats: group.formats
                    .map((formatGroup) => ({
                        format: formatGroup.format,
                        showtimes: formatGroup.showtimes.filter(
                            (showtime) => showtime.scheduled_date === state.selectedDate
                        ),
                    }))
                    .filter((formatGroup) => formatGroup.showtimes.length > 0),
            }))
            .filter((group) => group.formats.length > 0);
    }

    function scrollToShowtimes() {
        els.showtimesContent?.scrollIntoView({ behavior: 'smooth' });
    }

    window.scrollToMovieShowtimes = scrollToShowtimes;

    /* ------------------------------------------------------------------ */
    /*  API                                                                */
    /* ------------------------------------------------------------------ */
    async function apiGet(url, params = {}) {
        const query = new URLSearchParams(params).toString();
        const fullUrl = query ? `${url}?${query}` : url;

        const res = await fetch(fullUrl, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!res.ok) {
            await handleApiError(res);
        }

        return res.json();
    }

    async function handleApiError(res) {
        const json = await res.json().catch(() => ({}));
        const message = json.message || 'Đã xảy ra lỗi, vui lòng thử lại';

        if (res.status === 404) {
            throw new Error('Movie not found');
        }

        if (res.status >= 500) {
            throw new Error('Hệ thống đang bảo trì');
        }

        throw new Error(message);
    }

    async function loadMovie() {
        const json = await apiGet(`${API_BASE}/movies/${encodeURIComponent(state.movieSlug)}`);

        if (!json.success) {
            throw new Error(json.message || 'Movie not found');
        }

        state.movie = json.data;
        renderMovieHero(state.movie);
    }

    async function loadShowtimes() {
        const json = await apiGet(`${API_BASE}/movies/${encodeURIComponent(state.movieSlug)}/showtimes`);

        if (!json.success || !json.data) {
            state.showtimeGroups = [];
            showNoShowtimes();
            return;
        }

        state.showtimeGroups = json.data.showtimes_grouped || [];
        state.theaters = extractTheaters();

        const dates = getUniqueDates();
        state.selectedDate = dates[0] || null;

        if (!state.selectedDate || state.showtimeGroups.length === 0) {
            showNoShowtimes();
            return;
        }

        populateTheaterFilter();
        renderShowtimes();
    }

    async function loadTrendingMovies() {
        try {
            const json = await apiGet(`${API_BASE}/movies/now-showing`);

            if (!json.success || !Array.isArray(json.data)) {
                showEmptyTrending();
                return;
            }

            const trending = json.data
                .filter((movie) => movie.id !== state.movie?.id)
                .slice(0, 3);

            renderTrendingMovies(trending);
        } catch (error) {
            showEmptyTrending();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Render                                                             */
    /* ------------------------------------------------------------------ */
    function renderMovieHero(movie) {
        if (!els.heroContent) return;

        const backdropUrl = movie.backdrops?.length ? movie.backdrops[0] : movie.poster_url;
        const posterUrl = movie.poster_url || '/images/placeholder-poster.jpg';
        const categories = Array.isArray(movie.categories) ? movie.categories : [];
        const genresHtml = categories
            .map((category) => `<span class="movie-detail-genre">${sanitize(category.name)}</span>`)
            .join('');

        els.heroContent.innerHTML = `
            ${backdropUrl ? `
                <div class="movie-detail-backdrop">
                    <img src="${sanitize(backdropUrl)}" alt="${sanitize(movie.title)} backdrop">
                </div>
                <div class="movie-detail-overlay"></div>
            ` : ''}

            <div class="movie-detail-content">
                <div class="container">
                    <div class="movie-detail-layout">
                        <div class="movie-detail-poster-col">
                            <img src="${sanitize(posterUrl)}"
                                 alt="${sanitize(movie.title)}"
                                 class="movie-detail-poster">
                        </div>

                        <div class="movie-detail-info">
                            <h1 class="movie-detail-title">${sanitize(movie.title)}</h1>

                            ${movie.original_title && movie.original_title !== movie.title ? `
                                <p class="movie-detail-original-title">${sanitize(movie.original_title)}</p>
                            ` : ''}

                            <div class="movie-detail-meta">
                                ${movie.age_rating ? `
                                    <div class="movie-detail-badge">
                                        <i class="bi bi-shield-check"></i>
                                        ${sanitize(movie.age_rating)}
                                    </div>
                                ` : ''}

                                ${movie.duration ? `
                                    <div class="movie-detail-badge">
                                        <i class="bi bi-clock"></i>
                                        ${sanitize(movie.duration)} min
                                    </div>
                                ` : ''}

                                ${movie.release_date ? `
                                    <div class="movie-detail-badge">
                                        <i class="bi bi-calendar"></i>
                                        ${sanitize(formatDate(movie.release_date))}
                                    </div>
                                ` : ''}
                            </div>

                            ${genresHtml ? `<div class="movie-detail-genres">${genresHtml}</div>` : ''}

                            ${movie.description ? `
                                <p class="movie-detail-description">${sanitize(movie.description)}</p>
                            ` : ''}

                            <div class="movie-detail-extra">
                                ${movie.director ? `
                                    <div class="movie-detail-extra-item">
                                        <div class="movie-detail-extra-label">Director</div>
                                        <div class="movie-detail-extra-value">${sanitize(movie.director)}</div>
                                    </div>
                                ` : ''}

                                ${movie.cast ? `
                                    <div class="movie-detail-extra-item">
                                        <div class="movie-detail-extra-label">Cast</div>
                                        <div class="movie-detail-extra-value">${sanitize(movie.cast)}</div>
                                    </div>
                                ` : ''}
                            </div>

                            <div class="movie-detail-actions">
                                <button type="button" class="btn-book-tickets" onclick="window.scrollToMovieShowtimes()">
                                    <i class="bi bi-ticket-perforated"></i>
                                    Book Tickets
                                </button>

                                ${movie.trailer_url ? `
                                    <a href="${sanitize(movie.trailer_url)}" target="_blank" rel="noopener" class="btn-trailer">
                                        <i class="bi bi-play-circle"></i>
                                        Watch Trailer
                                    </a>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function populateTheaterFilter() {
        if (!els.theaterFilter || !els.theaterFilterContainer) return;

        els.theaterFilter.innerHTML = '<option value="">All Cinemas</option>';
        state.theaters.forEach((theater) => {
            const option = document.createElement('option');
            option.value = theater.id;
            option.textContent = theater.name;
            els.theaterFilter.appendChild(option);
        });

        els.theaterFilterContainer.classList.remove('d-none');
    }

    function renderDateTabs(dates) {
        return `
            <div class="showtime-dates">
                ${dates.map((date) => {
                    const dateParts = getDateParts(date);
                    const isActive = date === state.selectedDate ? 'active' : '';

                    return `
                        <button class="showtime-date-btn ${isActive}" type="button" data-date="${sanitize(date)}">
                            <span class="date-day">${sanitize(dateParts.day)}</span>
                            <span class="date-month">${sanitize(dateParts.month)}</span>
                            <span class="date-weekday">${sanitize(dateParts.weekday)}</span>
                        </button>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderShowtimes() {
        if (!els.showtimesContent) return;

        const dates = getUniqueDates();
        const filteredGroups = filterShowtimeGroups();
        const dateTabsHtml = renderDateTabs(dates);

        if (filteredGroups.length === 0) {
            els.showtimesContent.innerHTML = `
                ${dateTabsHtml}
                <div class="no-filter-results">
                    <i class="bi bi-calendar-x" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p style="margin-top: 16px;">No showtimes available for this selection.</p>
                </div>
            `;
            bindDateTabEvents();
            return;
        }

        const theaterGroupsHtml = filteredGroups.map((theaterGroup) => `
            <div class="showtime-theater-group">
                <h4 class="showtime-theater-name">
                    <i class="bi bi-geo-alt"></i>
                    ${sanitize(theaterGroup.theater.name)}
                </h4>

                ${theaterGroup.formats.map((formatGroup) => `
                    <div class="format-showtime-group">
                        <h3 class="format-title">
                            ${sanitize(formatGroup.format.name)}
                            ${formatGroup.format.description ? `
                                <span class="format-badge">${sanitize(formatGroup.format.description)}</span>
                            ` : ''}
                        </h3>

                        <div class="showtime-times">
                            ${formatGroup.showtimes.map((showtime) => `
                                <a href="/booking/${sanitize(encodeURIComponent(showtime.id))}" class="showtime-time-card">
                                    <span class="time-value">${sanitize(showtime.time)}</span>
                                    <span class="time-extra">${sanitize(showtime.screen?.name || '')}</span>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                `).join('')}
            </div>
        `).join('');

        els.showtimesContent.innerHTML = `${dateTabsHtml}${theaterGroupsHtml}`;
        bindDateTabEvents();
    }

    function bindDateTabEvents() {
        els.showtimesContent?.querySelectorAll('.showtime-date-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.selectedDate = btn.dataset.date;
                renderShowtimes();
            });
        });
    }

    function renderTrendingMovies(movies) {
        if (!els.trendingContent) return;

        if (!movies.length) {
            showEmptyTrending();
            return;
        }

        els.trendingContent.innerHTML = movies.map((movie) => {
            const categories = Array.isArray(movie.categories)
                ? movie.categories.slice(0, 2).map((category) => category.name).join(', ')
                : 'Movie';

            return `
                <a href="/movies/${sanitize(movie.slug || movie.id)}" class="trending-item">
                    <img src="${sanitize(movie.poster_url || '/images/placeholder-poster.jpg')}"
                         alt="${sanitize(movie.title)}"
                         class="trending-poster"
                         loading="lazy">
                    <div class="trending-info">
                        <div class="trending-title">${sanitize(movie.title)}</div>
                        <div class="trending-meta">${sanitize(categories || 'Movie')}</div>
                        ${movie.rating ? `
                            <div class="trending-rating">
                                <i class="bi bi-star-fill"></i>
                                ${sanitize(Number(movie.rating).toFixed(1))}
                            </div>
                        ` : ''}
                    </div>
                </a>
            `;
        }).join('');

        els.trendingSkeleton?.classList.add('d-none');
        els.trendingContent?.classList.remove('d-none');
    }

    function showLoaded() {
        els.heroSkeleton?.classList.add('d-none');
        els.heroContent?.classList.remove('d-none');
        els.showtimesSkeleton?.classList.add('d-none');

        if (state.showtimeGroups.length > 0) {
            els.showtimesContent?.classList.remove('d-none');
            els.noShowtimes?.classList.add('d-none');
        }
    }

    function showNoShowtimes() {
        els.showtimesSkeleton?.classList.add('d-none');
        els.showtimesContent?.classList.add('d-none');
        els.noShowtimes?.classList.remove('d-none');
    }

    function showEmptyTrending() {
        if (!els.trendingContent) return;

        els.trendingContent.innerHTML = '<p class="trending-empty">No trending movies available at the moment.</p>';
        els.trendingSkeleton?.classList.add('d-none');
        els.trendingContent?.classList.remove('d-none');
    }

    function showError(message) {
        if (!els.heroContent) return;

        els.heroSkeleton?.classList.add('d-none');
        els.heroContent.classList.remove('d-none');
        els.heroContent.innerHTML = `
            <div class="movie-detail-content">
                <div class="container">
                    <div class="error-state" style="text-align: center; padding: 60px 20px;">
                        <i class="bi bi-exclamation-circle" style="font-size: 4rem; color: rgba(255,255,255,0.2);"></i>
                        <h2 style="margin-top: 20px;">${sanitize(message)}</h2>
                        <p style="color: rgba(255,255,255,0.6); margin-top: 10px;">The movie you're looking for could not be found.</p>
                        <a href="/movies" class="cinema-primary-btn" style="margin-top: 20px; display: inline-block;">
                            <i class="bi bi-arrow-left"></i>
                            Back to Movies
                        </a>
                    </div>
                </div>
            </div>
        `;

        showNoShowtimes();
        showEmptyTrending();
    }

    /* ------------------------------------------------------------------ */
    /*  Events                                                             */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        els.theaterFilter?.addEventListener('change', (event) => {
            state.selectedTheater = event.target.value;
            renderShowtimes();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Bootstrap                                                          */
    /* ------------------------------------------------------------------ */
    async function init() {
        cacheDoms();
        bindEvents();

        state.movieSlug = getMovieSlugFromUrl();

        if (!state.movieSlug) {
            showError('Movie not found');
            return;
        }

        try {
            state.isLoading = true;

            await loadMovie();
            await Promise.all([
                loadShowtimes(),
                loadTrendingMovies(),
            ]);

            showLoaded();
        } catch (error) {
            showError(error.message || 'Movie not found');
        } finally {
            state.isLoading = false;
        }
    }

    document.addEventListener('DOMContentLoaded', init);
})();
