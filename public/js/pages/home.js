/**
 * Cinema - Home Page
 * Fetch API + Skeleton Loading
 */
(function () {
    'use strict';

    // Security utilities
    function escapeHtml(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, "\u0026amp;")
            .replace(/</g, "\u0026lt;")
            .replace(/>/g, "\u0026gt;")
            .replace(/"/g, "\u0026quot;")
            .replace(/'/g, "\u0026#039;");
    }

    function escapeAttr(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, "\u0026amp;")
            .replace(/"/g, "\u0026quot;")
            .replace(/'/g, "\u0026#039;");
    }

    const API_HOME = '/api/home';
    const PLACEHOLDER_POSTER = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 300 450%22%3E%3Crect fill=%22%23161618%22 width=%22300%22 height=%22450%22/%3E%3Ctext x=%22150%22 y=%22225%22 text-anchor=%22middle%22 fill=%22%23666%22 font-size=%2220%22%3ENo Poster%3C/text%3E%3C/svg%3E';
    const FALLBACK_BACKDROP = 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=1920&auto=format&fit=crop';

    let els = {};

    function cacheDoms() {
        els = {
            heroSkeleton: document.getElementById('heroSkeleton'),
            heroContent: document.getElementById('heroContent'),
            bookingSkeleton: document.getElementById('bookingSkeleton'),
            bookingForm: document.getElementById('bookingForm'),
            movieSelect: document.getElementById('movie'),
            dateSelect: document.getElementById('date'),
            cinemaSelect: document.getElementById('cinema'),
            moviesSkeleton: document.getElementById('moviesSkeleton'),
            moviesGrid: document.getElementById('moviesGrid'),
        };
    }

    function showLoaded() {
        els.heroSkeleton?.classList.add('d-none');
        els.bookingSkeleton?.classList.add('d-none');
        els.moviesSkeleton?.classList.add('d-none');

        els.heroContent?.classList.remove('d-none');
        els.bookingForm?.classList.remove('d-none');
        els.moviesGrid?.classList.remove('d-none');
    }

    function showError(message) {
        els.heroSkeleton?.classList.add('d-none');
        els.moviesSkeleton?.classList.add('d-none');

        if (els.heroContent) {
            els.heroContent.classList.remove('d-none');
            els.heroContent.innerHTML = `
                <div class="container hero-inner">
                    <div class="hero-copy">
                        <h1 class="hero-title">Không thể tải dữ liệu</h1>
                        <p class="hero-description">${escapeHtml(message)}</p>
                        <button class="btn-book-now" onclick="location.reload()">Thử lại</button>
                    </div>
                </div>
            `;
        }

        if (els.moviesGrid) {
            els.moviesGrid.classList.remove('d-none');
            els.moviesGrid.innerHTML = `<p class="text-danger">Không thể tải danh sách phim.</p>`;
        }
    }

    function getCategories(movie) {
        return (movie.categories || []).map(c => c.name).join(' / ') || 'SCI-FI / THRILLER';
    }

    function getDuration(movie) {
        if (!movie.duration) return '2H 15M';
        const h = Math.floor(movie.duration / 60);
        const m = movie.duration % 60;
        return h > 0 ? `${h}H ${String(m).padStart(2, '0')}M` : `${m}M`;
    }

    function renderHero(movie) {
        if (!els.heroContent) return;

        if (!movie) {
            els.heroContent.innerHTML = `
                <div class="container hero-inner">
                    <div class="hero-copy">
                        <span class="hero-meta"><span class="rating-badge">PG-13</span> SCI-FI / THRILLER</span>
                        <h1 id="heroTitle" class="hero-title">CINEMA PREMIUM</h1>
                        <p class="hero-description">Trải nghiệm đặt vé xem phim trực tuyến nhanh chóng, hiện đại và tối giản.</p>
                    </div>
                </div>
            `;
            return;
        }

        const backdrop = movie.backdrop_url || movie.poster_url || FALLBACK_BACKDROP;
        const safeBackdrop = escapeAttr(backdrop);
        const rating = escapeHtml(movie.age_rating || 'PG-13');
        const categories = escapeHtml(getCategories(movie));
        const title = escapeHtml(movie.title || 'The Stellar Divide');
        const desc = (movie.description || 'A visually stunning journey to the edge of the known universe. Experience the epic conclusion in breathtaking IMAX and Dolby Atmos.');
        const safeDesc = escapeHtml(desc.length > 170 ? desc.substring(0, 170) + '…' : desc);

        els.heroContent.style.backgroundImage = `linear-gradient(90deg, rgba(12,12,14,.96) 0%, rgba(12,12,14,.72) 42%, rgba(12,12,14,.35) 100%), linear-gradient(0deg, #101012 0%, rgba(16,16,18,.45) 45%, rgba(16,16,18,.1) 100%), url("${safeBackdrop}")`;

        var trailerHtml = '';
        if (movie.trailer_url) {
            var safeTrailerUrl = escapeAttr(movie.trailer_url);
            trailerHtml = '<a class="btn-trailer" href="' + safeTrailerUrl + '" target="_blank" rel="noopener">' +
                '<i class="bi bi-play-circle"></i> Watch Trailer</a>';
        } else {
            trailerHtml = '<button class="btn-trailer" type="button"><i class="bi bi-play-circle"></i> Watch Trailer</button>';
        }

        els.heroContent.innerHTML = `
            <div class="container hero-inner">
                <div class="hero-copy">
                    <div class="hero-meta">
                        <span class="rating-badge">${rating}</span>
                        <span>${categories}</span>
                    </div>
                    <h1 id="heroTitle" class="hero-title">${title}</h1>
                    <p class="hero-description">${safeDesc}</p>
                    <div class="hero-actions">
                        <button class="btn-book-now" type="button" onclick="document.getElementById('bookingForm')?.scrollIntoView({behavior:'smooth', block:'center'})">
                            <i class="bi bi-ticket-perforated-fill"></i>
                            Book Now
                        </button>
                        ${trailerHtml}
                    </div>
                </div>
            </div>
        `;
    }

    function populateFilters(data) {
        const movies = data.movie_options || data.now_showing_movies || [];

        if (els.movieSelect) {
            els.movieSelect.innerHTML = movies.map((m, index) =>
                `<option value="${escapeAttr(m.id)}" ${index === 0 ? 'selected' : ''}>${escapeHtml(m.title)}</option>`
            ).join('');
        }

        if (els.dateSelect) {
            const dates = data.available_dates || [
                { value: new Date().toISOString().slice(0, 10), label: 'Today' },
            ];
            els.dateSelect.innerHTML = dates.map((d, index) =>
                `<option value="${escapeAttr(d.value)}" ${index === 0 ? 'selected' : ''}>${escapeHtml(d.label)}</option>`
            ).join('');
        }

        if (els.cinemaSelect) {
            const cinemas = data.cinema_options || [];
            if (cinemas.length) {
                els.cinemaSelect.innerHTML = cinemas.map((c, index) =>
                    `<option value="${escapeAttr(c.id)}" ${index === 0 ? 'selected' : ''}>${escapeHtml(c.name)}</option>`
                ).join('');
            } else {
                els.cinemaSelect.innerHTML = `<option value="">Downtown IMAX</option>`;
            }
        }
    }

    function renderMovies(movies) {
        if (!els.moviesGrid) return;

        const list = movies || [];
        if (!list.length) {
            els.moviesGrid.innerHTML = `<p class="text-muted">Chưa có phim đang chiếu.</p>`;
            return;
        }

        els.moviesGrid.innerHTML = list.map(movie => {
            const poster = movie.poster_url || PLACEHOLDER_POSTER;
            const safePoster = escapeAttr(poster);
            const safeTitle = escapeHtml(movie.title);
            const safeMovieUrl = movie.slug ? '/movies/' + escapeAttr(movie.slug) : '/movies/' + escapeAttr(movie.id);
            const safeCategory = escapeHtml(getCategories(movie).split(' / ')[0]);
            const safeDuration = escapeHtml(getDuration(movie));
            return `
                <article class="movie-card">
                    <a href="${safeMovieUrl}" class="movie-card-link">
                        <img src="${safePoster}" alt="${safeTitle}" class="movie-poster" loading="lazy">
                        <div class="movie-gradient"></div>
                        <div class="movie-info">
                            <h3 class="movie-title">${safeTitle}</h3>
                            <p class="movie-meta">${safeCategory} • ${safeDuration}</p>
                        </div>
                    </a>
                </article>
            `;
        }).join('');
    }

    function initBookingSubmit() {
        if (!els.bookingForm) return;

        els.bookingForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(els.bookingForm));
            window.location.href = `/showtimes?${params.toString()}`;
        });
    }

    async function loadHome() {
        try {
            const res = await fetch(API_HOME, { headers: { Accept: 'application/json' } });
            const json = await res.json();

            if (!json.success) throw new Error(json.message || 'Failed to load home data');

            const data = json.data || {};
            renderHero(data.featured_movie || data.now_showing_movies?.[0]);
            populateFilters(data);
            renderMovies(data.now_showing_movies);

            setTimeout(showLoaded, 350);
        } catch (err) {
            console.error('[Home] Load error:', err);
            showError(err.message || 'Vui lòng thử lại sau.');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        cacheDoms();
        initBookingSubmit();
        loadHome();
    });
})();
