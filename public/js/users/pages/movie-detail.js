/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MOVIE DETAIL PAGE
 * Loads and displays movie details, showtimes, and trending movies
 * ═══════════════════════════════════════════════════════════════════════════
 */

import Toast from '../components/toast.js';

(function() {
    'use strict';

    const movieSlug = window.location.pathname.split('/').pop();
    let movieData = null;
    let showtimeGroups = [];
    let selectedDate = null;
    let selectedTheaterId = '';

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        try {
            await Promise.all([
                loadMovieData(),
                loadShowtimes(),
                loadTrendingMovies()
            ]);

            renderHero();
            renderShowtimes();
            setupEventListeners();
            hideSkeletons();
        } catch (error) {
            console.error('Failed to initialize movie detail page:', error);
            showError();
        }
    }

    async function loadMovieData() {
        try {
            const data = await window.apiClient.get(`/movies/${movieSlug}`);
            movieData = data.data || data.movie || data;
        } catch (error) {
            console.error('Error loading movie:', error);
            throw error;
        }
    }

    async function loadShowtimes() {
        try {
            const data = await window.apiClient.get(`/movies/${movieSlug}/showtimes`);
            if (data.success && data.data && data.data.showtimes_grouped) {
                showtimeGroups = data.data.showtimes_grouped;
            } else if (data.showtimes_grouped) {
                showtimeGroups = data.showtimes_grouped;
            } else {
                showtimeGroups = [];
            }

            // Set initial selected date
            const dates = getUniqueDates();
            selectedDate = dates[0] || null;
        } catch (error) {
            console.error('Error loading showtimes:', error);
            showtimeGroups = [];
        }
    }

    function getUniqueDates() {
        const dates = new Set();

        showtimeGroups.forEach((theaterGroup) => {
            (theaterGroup.formats || []).forEach((formatGroup) => {
                (formatGroup.showtimes || []).forEach((showtime) => {
                    const date = showtime.scheduled_date || showtime.showtime_date;
                    if (date) {
                        dates.add(date);
                    }
                });
            });
        });

        return Array.from(dates).sort().slice(0, 5);
    }

    function filterShowtimeGroups() {
        return showtimeGroups
            .filter(group => !selectedTheaterId || String(group.theater.id) === String(selectedTheaterId))
            .map(group => ({
                theater: group.theater,
                formats: (group.formats || [])
                    .map(formatGroup => ({
                        format: formatGroup.format || formatGroup.version_type || {},
                        showtimes: (formatGroup.showtimes || []).filter(showtime => {
                            const date = showtime.scheduled_date || showtime.showtime_date;
                            return date === selectedDate;
                        })
                    }))
                    .filter(formatGroup => formatGroup.showtimes.length > 0)
            }))
            .filter(group => group.formats.length > 0);
    }

    async function loadTrendingMovies() {
        try {
            const data = await window.apiClient.get('/movies?status=now_showing&limit=5');
            const movies = data.data || data.movies || [];

            renderTrendingMovies(movies.slice(0, 5));
        } catch (error) {
            console.error('Error loading trending:', error);
        }
    }

    function renderHero() {
        if (!movieData) return;

        const heroContent = document.getElementById('heroContent');
        if (!heroContent) return;

        const posterUrl = movieData.poster_display_url || movieData.poster_url || 'https://via.placeholder.com/280x420/1a1a2e/ffffff?text=No+Poster';
        const backdropUrl = movieData.banner_display_url || movieData.banner_url || movieData.backdrop_url || posterUrl;

        const metaItems = [];
        if (movieData.duration) metaItems.push(`${movieData.duration} min`);
        if (movieData.genres && movieData.genres.length > 0) {
            metaItems.push(movieData.genres.map(g => escapeHtml(g.name || g)).join(' / '));
        }
        if (movieData.age_rating) {
            metaItems.push(`<span class="movie-detail-badge">${escapeHtml(movieData.age_rating)}</span>`);
        }
        if (movieData.rating) {
            metaItems.push(`<span class="movie-rating-highlight"><i class="bi bi-star"></i> ${movieData.rating}/10</span>`);
        }

        const metaText = metaItems.join('<span class="meta-separator">•</span>');

        // Cast and Crew Mock Data (Since API might not return this structured data yet, we parse it or use mock for visual design)
        let castHtml = '';
        if (movieData.cast) {
            const castList = typeof movieData.cast === 'string' ? movieData.cast.split(',').slice(0, 3) : movieData.cast;
            castHtml = castList.map(actor => `
                <div class="cast-member">
                    <div class="cast-avatar">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <span class="cast-name">${escapeHtml(actor.trim())}</span>
                </div>
            `).join('');
        }
        if (movieData.director) {
            castHtml += `
                <div class="cast-member">
                    <div class="cast-avatar">
                        <i class="bi bi-camera-reels"></i>
                    </div>
                    <span class="cast-name">Director<br>${escapeHtml(movieData.director)}</span>
                </div>
            `;
        }

        heroContent.innerHTML = `
            <div class="movie-detail-backdrop">
                ${backdropUrl ? `<img src="${escapeHtml(backdropUrl)}" alt="">` : ''}
            </div>
            <div class="movie-detail-overlay"></div>
            <div class="movie-detail-content">
                <div class="container">
                    <div class="movie-detail-layout">
                        <div class="movie-detail-poster-col">
                            <img src="${escapeHtml(posterUrl)}" alt="${escapeHtml(movieData.title)}" class="movie-detail-poster">
                        </div>
                        <div class="movie-detail-info">
                            <h1 class="movie-detail-title">${escapeHtml(movieData.title)}</h1>
                            
                            <div class="movie-detail-meta-row">
                                ${metaText}
                            </div>

                            ${movieData.description ? `
                                <p class="movie-detail-description">${escapeHtml(movieData.description)}</p>` : ''}

                            ${castHtml ? `
                                <div class="cast-crew-section">
                                    <h3>Cast & Crew</h3>
                                    <div class="cast-list">
                                        ${castHtml}
                                    </div>
                                </div>
                            ` : ''}

                            <div class="movie-detail-actions">
                                <a href="#showtimes" class="btn-book-tickets">
                                    <i class="bi bi-ticket-perforated-fill"></i>
                                    Book Tickets
                                </a>
                                ${movieData.trailer_url ? `
                                    <button class="btn-watch-trailer" data-bs-toggle="modal" data-bs-target="#trailerModal" data-trailer-url="${escapeHtml(movieData.trailer_url)}">
                                        <i class="bi bi-play-circle"></i>
                                        Watch Trailer
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        heroContent.classList.remove('d-none');
    }

    function renderExtraInfo() {
        if (!movieData) return '';

        const items = [];
        if (movieData.director) items.push({ label: 'Director', value: movieData.director });
        if (movieData.cast) items.push({ label: 'Cast', value: movieData.cast });
        if (movieData.language) items.push({ label: 'Language', value: movieData.language });
        if (movieData.subtitle) items.push({ label: 'Subtitle', value: movieData.subtitle });

        if (items.length === 0) return '';

        return `
            <div class="movie-detail-extra">
                ${items.map(item => `
                    <div class="movie-detail-extra-item">
                        <div class="movie-detail-extra-label">${escapeHtml(item.label)}</div>
                        <div class="movie-detail-extra-value">${escapeHtml(item.value)}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderShowtimes() {
        const content = document.getElementById('showtimesContent');
        const noShowtimes = document.getElementById('noShowtimes');

        if (!content) return;

        if (showtimeGroups.length === 0) {
            content.classList.add('d-none');
            if (noShowtimes) noShowtimes.classList.remove('d-none');
            return;
        }

        if (noShowtimes) noShowtimes.classList.add('d-none');
        content.classList.remove('d-none');

        const dates = getUniqueDates();
        const filteredGroups = filterShowtimeGroups();

        content.innerHTML = `
            <div class="showtime-dates" id="showtimeDates">
                ${dates.map(date => renderDateTab(date)).join('')}
            </div>
            <div id="showtimeGroups">
                ${filteredGroups.length > 0 ?
                    filteredGroups.map(group => renderTheaterGroup(group)).join('') :
                    '<p class="text-muted">No showtimes available for this selection.</p>'
                }
            </div>
        `;

        populateTheaterFilter();
    }

    function renderDateTab(date) {
        const d = new Date(date + 'T00:00:00');
        const day = d.getDate();
        let weekday = d.toLocaleDateString('en-US', { weekday: 'short' });
        
        const today = new Date();
        today.setHours(0,0,0,0);
        if (d.getTime() === today.getTime()) {
            weekday = 'TODAY';
        }
        
        const isActive = date === selectedDate;

        return `
            <button class="showtime-date-btn ${isActive ? 'active' : ''}"
                    data-date="${date}">
                <span class="date-weekday">${weekday}</span>
                <span class="date-day">${day}</span>
            </button>
        `;
    }

    function renderTheaterGroup(group) {
        return `
            <div class="showtime-theater-group">
                <div class="showtime-theater-name">
                    <i class="bi bi-geo-alt-fill"></i>
                    ${escapeHtml(group.theater.name)}
                </div>
                ${group.formats.map(formatGroup => renderFormatGroup(formatGroup)).join('')}
            </div>
        `;
    }

    function renderFormatGroup(formatGroup) {
        const formatName = formatGroup.format.name || formatGroup.format.version_type || 'Standard';

        return `
            <div class="format-showtime-group">
                <h3 class="format-title">
                    ${escapeHtml(formatName)}
                    ${formatGroup.format.description ?
                        `<span class="format-badge">${escapeHtml(formatGroup.format.description)}</span>` : ''
                    }
                </h3>
                <div class="showtime-times">
                    ${formatGroup.showtimes.map(showtime => renderShowtimeCard(showtime)).join('')}
                </div>
            </div>
        `;
    }

    function renderShowtimeCard(showtime) {
        const time = showtime.time || showtime.start_time || showtime.showtime_time || '';
        const showtimeId = showtime.id || showtime.showtime_id || '';
        const bookingUrl = `/booking/${showtime.encrypted_id || showtimeId}`;

        return `
            <a href="${bookingUrl}" class="showtime-time-card">
                <span class="time-value">${escapeHtml(time)}</span>
            </a>
        `;
    }

    function populateTheaterFilter() {
        const filterContainer = document.getElementById('theaterFilterContainer');
        const filterSelect = document.getElementById('theaterFilter');

        if (!filterSelect) return;

        const theaters = showtimeGroups.map(g => g.theater);

        if (theaters.length > 0) {
            filterSelect.innerHTML = `
                <option value="">All Cinemas</option>
                ${theaters.map(t => `
                    <option value="${t.id}">${escapeHtml(t.name)}</option>
                `).join('')}
            `;
            if (filterContainer) filterContainer.classList.remove('d-none');
        }
    }

    function renderTrendingMovies(movies) {
        const skeleton = document.getElementById('trendingSkeleton');
        const content = document.getElementById('trendingContent');

        if (!content || movies.length === 0) return;

        if (skeleton) skeleton.style.display = 'none';
        content.classList.remove('d-none');

        content.innerHTML = movies.map(movie => `
            <a href="/movies/${movie.slug}" class="trending-item">
                <img src="${escapeHtml(movie.poster_display_url || movie.poster_url || '/images/default-poster.jpg')}"
                     alt="${escapeHtml(movie.title)}"
                     class="trending-poster">
                <div class="trending-info">
                    <h4 class="trending-title">${escapeHtml(movie.title)}</h4>
                    ${movie.genres ? `
                        <p class="trending-meta">${escapeHtml(movie.genres.slice(0, 2).map(g => g.name || g).join(' / '))}</p>
                    ` : ''}
                    ${movie.rating ? `
                        <div class="trending-rating">
                            <i class="bi bi-star-fill"></i> ${movie.rating}
                        </div>
                    ` : `<div class="trending-rating"><i class="bi bi-star-fill"></i> 8.5</div>`}
                </div>
            </a>
        `).join('');
    }

    function setupEventListeners() {
        // Trailer Modal Logic
        const trailerModal = document.getElementById('trailerModal');
        if (trailerModal) {
            trailerModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                let trailerUrl = button.getAttribute('data-trailer-url');
                
                // Convert youtube watch URL to embed URL if necessary
                if (trailerUrl && trailerUrl.includes('watch?v=')) {
                    trailerUrl = trailerUrl.replace('watch?v=', 'embed/');
                }
                
                const iframe = document.getElementById('trailerIframe');
                if (iframe && trailerUrl) {
                    iframe.src = trailerUrl + '?autoplay=1';
                }
            });

            trailerModal.addEventListener('hidden.bs.modal', function () {
                const iframe = document.getElementById('trailerIframe');
                if (iframe) {
                    iframe.src = '';
                }
            });
        }

        // Date tab clicks
        document.addEventListener('click', (e) => {
            const dateBtn = e.target.closest('.showtime-date-btn');
            if (dateBtn) {
                selectedDate = dateBtn.dataset.date;
                updateActiveDateTab();
                renderShowtimeGroups();
            }
        });

        // Theater filter
        const theaterFilter = document.getElementById('theaterFilter');
        if (theaterFilter) {
            theaterFilter.addEventListener('change', (e) => {
                selectedTheaterId = e.target.value;
                renderShowtimeGroups();
            });
        }

        // Smooth scroll to showtimes
        document.querySelectorAll('a[href="#showtimes"]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('showtimesContent')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });
    }

    function renderShowtimeGroups() {
        const container = document.getElementById('showtimeGroups');
        if (!container) return;

        const filteredGroups = filterShowtimeGroups();

        container.innerHTML = filteredGroups.length > 0 ?
            filteredGroups.map(group => renderTheaterGroup(group)).join('') :
            '<p class="text-muted">No showtimes available for this selection.</p>';
    }

    function updateActiveDateTab() {
        document.querySelectorAll('.showtime-date-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.date === selectedDate);
        });
    }

    function hideSkeletons() {
        const heroSkeleton = document.getElementById('heroSkeleton');
        const showtimesSkeleton = document.getElementById('showtimesSkeleton');

        if (heroSkeleton) heroSkeleton.style.display = 'none';
        if (showtimesSkeleton) showtimesSkeleton.style.display = 'none';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError() {
        // Use Toast notification for better UX
        if (typeof Toast !== 'undefined') {
            Toast.error(
                'Unable to load movie details',
                'Please try refreshing the page or check your connection.'
            );
        }

        // Hide skeletons and show fallback message
        hideSkeletons();

        const heroContent = document.getElementById('heroContent');
        if (heroContent) {
            heroContent.innerHTML = `
                <div class="container">
                    <div class="alert alert-danger mt-5">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Unable to load movie details. Please try again later.
                    </div>
                </div>
            `;
            heroContent.classList.remove('d-none');
        }
    }

    // Expose for debugging
    window.movieDetailPage = {
        movieData: () => movieData,
        showtimeGroups: () => showtimeGroups,
        reload: init
    };
})();
