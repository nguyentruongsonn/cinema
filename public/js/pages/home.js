/**
 * ═══════════════════════════════════════════════════════════════════════════
 * HOME PAGE MODULE
 * Handles hero section, quick booking widget, and movies grid
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    let homeData = null;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        try {
            await loadHomeData();
            renderHero();
            renderBookingForm();
            renderMoviesGrid();
        } catch (error) {
            console.error('Failed to initialize home page:', error);
            showError();
        }
    }

    async function loadHomeData() {
        try {
            const response = await fetch('/api/v1/home');
            if (!response.ok) throw new Error('Failed to load home data');

            const data = await response.json();
            homeData = data.data || data;
        } catch (error) {
            console.error('Error loading home data:', error);
            throw error;
        }
    }

    function renderHero() {
        const heroSkeleton = document.getElementById('heroSkeleton');
        const heroContent = document.getElementById('heroContent');

        if (!homeData || !homeData.featured_movie) {
            heroSkeleton.style.display = 'none';
            return;
        }

        const movie = homeData.featured_movie;
        const bannerUrl = movie.backdrop_url || movie.poster_url || '/images/default-banner.jpg';

        heroContent.innerHTML = `
            <div class="hero-backdrop" style="background-image: url('${escapeHtml(bannerUrl)}')">
                <div class="hero-overlay"></div>
            </div>
            <div class="container">
                <div class="hero-copy">
                    <span class="hero-badge">NOW SHOWING</span>
                    <h1 class="hero-title">${escapeHtml(movie.title)}</h1>
                    <p class="hero-description">${escapeHtml(movie.synopsis || '')}</p>
                    <div class="hero-meta">
                        <span><i class="bi bi-clock"></i> ${movie.duration || 'N/A'} min</span>
                        <span><i class="bi bi-star-fill"></i> ${movie.age_rating || 'N/A'}</span>
                    </div>
                    <div class="hero-actions">
                        <button class="btn-book-now" type="button" onclick="document.getElementById('bookingForm')?.scrollIntoView({behavior:'smooth', block:'center'})">
                            <i class="bi bi-ticket-perforated-fill"></i>
                            Book Now
                        </button>
                        ${movie.trailer_url ? `
                            <a class="btn-trailer" href="${escapeHtml(movie.trailer_url)}" target="_blank" rel="noopener">
                                <i class="bi bi-play-circle"></i>
                                Watch Trailer
                            </a>
                        ` : `
                            <button class="btn-trailer" type="button">
                                <i class="bi bi-play-circle"></i>
                                Watch Trailer
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;

        heroSkeleton.style.display = 'none';
        heroContent.classList.remove('d-none');
    }

    function renderBookingForm() {
        const bookingSkeleton = document.getElementById('bookingSkeleton');
        const bookingForm = document.getElementById('bookingForm');

        if (!homeData) {
            bookingSkeleton.style.display = 'none';
            return;
        }

        // Populate movies dropdown
        const movieSelect = document.getElementById('movie');
        if (movieSelect && homeData.movie_options) {
            movieSelect.innerHTML = '<option value="">Select a movie</option>';
            homeData.movie_options.forEach(movie => {
                const option = document.createElement('option');
                option.value = movie.id;
                option.textContent = movie.title;
                movieSelect.appendChild(option);
            });
        }

        // Populate dates dropdown
        const dateSelect = document.getElementById('date');
        if (dateSelect && homeData.available_dates) {
            dateSelect.innerHTML = '<option value="">Select a date</option>';
            homeData.available_dates.forEach(date => {
                const option = document.createElement('option');
                option.value = date.value;
                option.textContent = date.label;
                dateSelect.appendChild(option);
            });
        }

        // Populate cinemas dropdown
        const cinemaSelect = document.getElementById('cinema');
        if (cinemaSelect && homeData.cinema_options) {
            cinemaSelect.innerHTML = '<option value="">Select a cinema</option>';
            homeData.cinema_options.forEach(theater => {
                const option = document.createElement('option');
                option.value = theater.id;
                const displayName = theater.city ? `${theater.name} - ${theater.city}` : theater.name;
                option.textContent = displayName;
                cinemaSelect.appendChild(option);
            });
        }

        bookingSkeleton.style.display = 'none';
        bookingForm.classList.remove('d-none');
    }

    function renderMoviesGrid() {
        const moviesSkeleton = document.getElementById('moviesSkeleton');
        const moviesGrid = document.getElementById('moviesGrid');

        if (!homeData || !homeData.now_showing_movies || homeData.now_showing_movies.length === 0) {
            moviesSkeleton.style.display = 'none';
            moviesGrid.innerHTML = '<p class="text-center text-muted">No movies available</p>';
            moviesGrid.classList.remove('d-none');
            return;
        }

        moviesGrid.innerHTML = homeData.now_showing_movies.map(movie => `
            <a href="/movies/${movie.slug}" class="movie-card">
                <div class="movie-poster">
                    <img src="${escapeHtml(movie.poster_url || '/images/default-poster.jpg')}"
                         alt="${escapeHtml(movie.title)}"
                         loading="lazy">
                    ${movie.is_hot ? '<span class="movie-badge-hot">HOT</span>' : ''}
                </div>
                <div class="movie-info">
                    <h3 class="movie-title">${escapeHtml(movie.title)}</h3>
                    <div class="movie-meta">
                        <span><i class="bi bi-clock"></i> ${movie.duration || 'N/A'} min</span>
                        <span><i class="bi bi-star-fill"></i> ${movie.age_rating || 'N/A'}</span>
                    </div>
                </div>
            </a>
        `).join('');

        moviesSkeleton.style.display = 'none';
        moviesGrid.classList.remove('d-none');
    }

    function formatDate(date) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return `${days[date.getDay()]}, ${months[date.getMonth()]} ${date.getDate()}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError() {
        const heroSkeleton = document.getElementById('heroSkeleton');
        const bookingSkeleton = document.getElementById('bookingSkeleton');
        const moviesSkeleton = document.getElementById('moviesSkeleton');

        if (heroSkeleton) heroSkeleton.style.display = 'none';
        if (bookingSkeleton) bookingSkeleton.style.display = 'none';
        if (moviesSkeleton) moviesSkeleton.style.display = 'none';

        const heroContent = document.getElementById('heroContent');
        const moviesGrid = document.getElementById('moviesGrid');

        if (heroContent) {
            heroContent.innerHTML = '<div class="alert alert-danger">Unable to load content. Please refresh the page.</div>';
            heroContent.classList.remove('d-none');
        }

        if (moviesGrid) {
            moviesGrid.innerHTML = '<div class="alert alert-danger">Unable to load movies. Please refresh the page.</div>';
            moviesGrid.classList.remove('d-none');
        }
    }

    // Expose for debugging
    window.homePage = {
        reload: init,
        data: () => homeData
    };
})();
