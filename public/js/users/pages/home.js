/**
 * ═══════════════════════════════════════════════════════════════════════════
 * HOME PAGE MODULE
 * Handles hero section, quick booking widget, and movies grid
 * ═══════════════════════════════════════════════════════════════════════════
 */

import Toast from '../components/toast.js';
import Modal from '../components/modal.js';

(function() {
    'use strict';

    let homeData = null;
    let heroIndex = 0;
    let heroTimer = null;

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
            initializeHeroCarousel();
            renderBookingForm();
            renderMoviesGrid();
        } catch (error) {
            console.error('Failed to initialize home page:', error);
            showError();
        }
    }

    async function loadHomeData() {
        try {
            const data = await window.apiClient.get('/home');
            homeData = data.data || data;
        } catch (error) {
            console.error('Error loading home data:', error);
            throw error;
        }
    }

    function renderHero() {
        const heroSkeleton = document.getElementById('heroSkeleton');
        const heroContent = document.getElementById('heroContent');
        const heroBackdrop = document.getElementById('heroBackdrop');
        const banners = homeData?.featured_banners || (homeData?.featured_banner ? [homeData.featured_banner] : []);

        if (!homeData || (!banners.length && !homeData.featured_movie)) {
            heroSkeleton?.classList.add('d-none');
            return;
        }

        const banner = banners.length ? banners[Math.min(heroIndex, banners.length - 1)] : null;
        const movie = homeData.featured_movie;
        const bannerUrl = banner?.image_url || movie?.backdrop_url || movie?.poster_display_url || movie?.poster_url || '/images/default-banner.jpg';

        // Set backdrop image
        if (heroBackdrop) {
            const safeBannerUrl = safeImageUrl(bannerUrl);
            heroBackdrop.style.setProperty('--hero-backdrop-image', safeBannerUrl ? `url("${safeBannerUrl}")` : 'none');
        }

        if (banner) {
            const heroInner = heroContent.querySelector('.hero-inner');
            if (!heroInner) return;
            const safeLink = safeHttpsUrl(banner.link_url);
            heroInner.innerHTML = `
                <div class="hero-meta-row"><span class="hero-age-badge">NỔI BẬT</span></div>
                <h1 id="heroTitle" class="hero-title">${escapeHtml(banner.title)}</h1>
                ${banner.description ? `<p class="hero-description">${escapeHtml(banner.description)}</p>` : ''}
                ${safeLink ? `<div class="hero-actions"><a class="btn-book-now" href="${escapeHtml(safeLink)}"><i class="bi bi-arrow-right-circle"></i> Xem chi tiết</a></div>` : ''}
            `;
            heroSkeleton?.classList.add('d-none');
            heroContent.classList.remove('d-none');
            renderHeroDots(banners.length);
            return;
        }

        // Build genre text
        const genreText = movie.genres && movie.genres.length > 0
            ? movie.genres.slice(0, 2).join(' / ').toUpperCase()
            : '';

        // Generate rating stars
        const rating = movie.rating || 0;
        const fullStars = Math.floor(rating);
        const hasHalfStar = rating % 1 >= 0.5;
        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

        let starsHtml = '';
        for (let i = 0; i < fullStars; i++) {
            starsHtml += '<i class="bi bi-star-fill"></i>';
        }
        if (hasHalfStar) {
            starsHtml += '<i class="bi bi-star-half"></i>';
        }
        for (let i = 0; i < emptyStars; i++) {
            starsHtml += '<i class="bi bi-star"></i>';
        }

        // Build the hero inner HTML
        const heroInner = heroContent.querySelector('.hero-inner');
        if (!heroInner) return;

        heroInner.innerHTML = `
            <div class="hero-meta-row">
                ${movie.age_rating ? `<span class="hero-age-badge">${escapeHtml(movie.age_rating)}</span>` : ''}
                ${genreText ? `<span class="hero-genre">${escapeHtml(genreText)}</span>` : ''}
            </div>

            <h1 id="heroTitle" class="hero-title">${escapeHtml(movie.title)}</h1>

            ${rating > 0 ? `
                <div class="hero-rating">
                    <div class="hero-rating-stars">${starsHtml}</div>
                    <span class="hero-rating-value">${rating.toFixed(1)}/5</span>
                </div>
            ` : ''}

            <p class="hero-description">${escapeHtml(movie.synopsis || movie.description || '')}</p>

            <div class="hero-actions">
                <button class="btn-book-now" type="button" data-scroll-to-booking>
                    <i class="bi bi-ticket-perforated-fill"></i>
                    Book Now
                </button>
                ${movie.trailer_url ? `
                    <a class="btn-trailer" href="${escapeHtml(movie.trailer_url)}" target="_blank" rel="noopener">
                        <i class="bi bi-play-circle"></i>
                        Watch Trailer
                    </a>
                ` : `
                        <button class="btn-trailer btn-trailer--disabled" type="button" disabled>
                        <i class="bi bi-play-circle"></i>
                        Watch Trailer
                    </button>
                `}
            </div>
        `;
        heroInner.querySelector('[data-scroll-to-booking]')?.addEventListener('click', () => {
            document.querySelector('.quick-booking-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        heroSkeleton?.classList.add('d-none');
        heroContent.classList.remove('d-none');
    }

    function initializeHeroCarousel() {
        const banners = homeData?.featured_banners || (homeData?.featured_banner ? [homeData.featured_banner] : []);
        const previous = document.getElementById('heroPrevious');
        const next = document.getElementById('heroNext');

        if (banners.length <= 1) return;

        previous?.addEventListener('click', () => changeHero(-1));
        next?.addEventListener('click', () => changeHero(1));
        heroTimer = window.setInterval(() => changeHero(1), 5000);
    }

    function changeHero(direction) {
        const banners = homeData?.featured_banners || [];
        if (banners.length <= 1) return;
        heroIndex = (heroIndex + direction + banners.length) % banners.length;
        renderHero();
        renderHeroDots(banners.length);
        window.clearInterval(heroTimer);
        heroTimer = window.setInterval(() => changeHero(1), 5000);
    }

    function renderHeroDots(count) {
        const dots = document.getElementById('heroDots');
        const controls = document.querySelector('.hero-carousel-controls');
        if (!dots) return;
        if (count <= 1) {
            dots.innerHTML = '';
            controls?.classList.add('d-none');
            return;
        }
        controls?.classList.remove('d-none');
        dots.innerHTML = Array.from({ length: count }, (_, index) => `
            <button class="hero-carousel-dot${index === heroIndex ? ' active' : ''}" type="button"
                    role="tab" aria-label="Chuyển đến banner ${index + 1}" aria-selected="${index === heroIndex}"></button>
        `).join('');
        dots.querySelectorAll('.hero-carousel-dot').forEach((dot, index) => {
            dot.addEventListener('click', () => {
                heroIndex = index;
                renderHero();
                renderHeroDots(count);
                window.clearInterval(heroTimer);
                heroTimer = window.setInterval(() => changeHero(1), 5000);
            });
        });
    }

    function renderBookingForm() {
        const bookingSkeleton = document.getElementById('bookingSkeleton');
        const bookingWidget = document.getElementById('bookingWidget');

        if (!homeData) {
            bookingSkeleton?.classList.add('d-none');
            return;
        }

        // Populate movies custom dropdown
        populateCustomSelect('movie', homeData.movie_options || [], (movie) => ({
            value: movie.id,
            label: movie.title,
            searchText: movie.title.toLowerCase()
        }), true); // Has search

        // Populate cinemas custom dropdown
        populateCustomSelect('cinema', homeData.cinema_options || [], (theater) => ({
            value: theater.id,
            label: theater.city ? `${theater.name} - ${theater.city}` : theater.name,
            searchText: `${theater.name} ${theater.city || ''}`.toLowerCase()
        }), false);

        // Populate dates custom dropdown
        populateCustomSelect('date', homeData.available_dates || [], (date) => ({
            value: date.value,
            label: date.label,
            searchText: date.label.toLowerCase()
        }), false);

        // Initialize custom dropdown interactions
        initializeCustomDropdowns();

        // Setup Find Seats button
        const findSeatsBtn = document.getElementById('btnFindSeats');
        if (findSeatsBtn) {
            findSeatsBtn.addEventListener('click', handleFindSeats);
        }

        bookingSkeleton?.classList.add('d-none');
        if (bookingWidget) bookingWidget.classList.remove('d-none');
    }

    function handleFindSeats() {
        const movieId = document.getElementById('movieInput')?.value;
        const dateVal = document.getElementById('dateInput')?.value;
        const cinemaId = document.getElementById('cinemaInput')?.value;

        if (!movieId) {
            if (typeof Toast !== 'undefined') {
                Toast.warning('Please select a movie', 'Select a movie to find available seats.');
            }
            return;
        }

        // Navigate to movie detail or booking page
        const movie = homeData?.movie_options?.find(m => String(m.id) === String(movieId));
        if (movie && movie.slug) {
            window.location.href = `/movies/${movie.slug}`;
        } else {
            window.location.href = `/movies`;
        }
    }

    function populateCustomSelect(selectName, options, mapFn, hasSearch) {
        const customSelect = document.querySelector(`.custom-select[data-select="${selectName}"]`);
        if (!customSelect) return;

        const optionsContainer = customSelect.querySelector('.select-options');
        if (!optionsContainer) return;

        // Add options
        optionsContainer.innerHTML = options.map(item => {
            const mapped = mapFn(item);
            return `<div class="select-option" data-value="${mapped.value}" data-label="${mapped.label}" ${hasSearch ? `data-search="${mapped.searchText}"` : ''}>${mapped.label}</div>`;
        }).join('');
    }

    function initializeCustomDropdowns() {
        const customSelects = document.querySelectorAll('.custom-select');

        customSelects.forEach(select => {
            const trigger = select.querySelector('.select-trigger');
            const dropdown = select.querySelector('.select-dropdown');
            const valueSpan = select.querySelector('.select-value');
            const hiddenInput = select.parentElement.querySelector('input[type="hidden"]');
            const searchInput = select.querySelector('.select-search input');
            const options = select.querySelectorAll('.select-option');

            if (!trigger || !dropdown) return;

            // Toggle dropdown
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllDropdowns();
                select.classList.toggle('active');
                trigger.classList.toggle('active');

                // Focus search input if present
                if (select.classList.contains('active') && searchInput) {
                    setTimeout(() => searchInput.focus(), 100);
                }
            });

            // Handle option selection
            options.forEach(option => {
                option.addEventListener('click', () => {
                    const value = option.dataset.value;
                    const label = option.dataset.label;

                    // Update UI
                    if (valueSpan) {
                        valueSpan.textContent = label;
                        valueSpan.classList.remove('placeholder');
                    }

                    // Update hidden input
                    if (hiddenInput) {
                        hiddenInput.value = value;
                    }

                    // Mark as selected
                    options.forEach(opt => opt.classList.remove('selected'));
                    option.classList.add('selected');

                    // Close dropdown
                    select.classList.remove('active');
                    trigger.classList.remove('active');

                    // Handle cascading selections
                    handleCascadingSelection(select.dataset.select, value);
                });
            });

            // Handle search if present
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    options.forEach(option => {
                        const searchText = option.dataset.search || option.textContent.toLowerCase();
                        option.classList.toggle('d-none', !searchText.includes(searchTerm));
                    });
                });

                // Reset search when dropdown opens
                trigger.addEventListener('click', () => {
                    searchInput.value = '';
                    options.forEach(option => option.classList.remove('d-none'));
                });
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.custom-select')) {
                closeAllDropdowns();
            }
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.custom-select.active').forEach(select => {
            select.classList.remove('active');
            const trigger = select.querySelector('.select-trigger');
            if (trigger) trigger.classList.remove('active');
        });
    }

    function handleCascadingSelection(selectName, value) {
        // If movie or cinema is selected, could trigger showtime loading
        // For now, just a placeholder for future enhancement
        if (selectName === 'movie' || selectName === 'cinema' || selectName === 'date') {
            // TODO: Load showtimes based on selections
            console.log(`${selectName} selected:`, value);
        }
    }

    function renderMoviesGrid() {
        const moviesSkeleton = document.getElementById('moviesSkeleton');
        const moviesGrid = document.getElementById('moviesGrid');

        if (!homeData || !homeData.now_showing_movies || homeData.now_showing_movies.length === 0) {
            moviesSkeleton?.classList.add('d-none');
            moviesGrid.innerHTML = '<p class="text-center text-muted">No movies available</p>';
            moviesGrid.classList.remove('d-none');
            return;
        }

        moviesGrid.innerHTML = homeData.now_showing_movies.map(movie => {
            // Build genre + duration meta text
            const genreTag = movie.genres && movie.genres.length > 0
                ? movie.genres[0].toUpperCase()
                : '';
            const durationTag = movie.duration
                ? `${Math.floor(movie.duration / 60)}H ${movie.duration % 60 > 0 ? (movie.duration % 60 + 'M') : ''}`
                : '';
            const metaText = [genreTag, durationTag].filter(Boolean).join(' • ');

            return `
                <a href="/movies/${movie.slug}" class="movie-card">
                    <div class="movie-poster">
                        <img src="${escapeHtml(movie.poster_display_url || movie.poster_url || '/images/default-poster.jpg')}"
                             alt="${escapeHtml(movie.title)}"
                             loading="lazy">
                        ${movie.is_hot ? '<span class="movie-badge-hot">HOT</span>' : ''}
                    </div>
                    <div class="movie-info">
                        <h3 class="movie-title">${escapeHtml(movie.title)}</h3>
                        <p class="movie-meta">
                            ${metaText ? `<span>${escapeHtml(metaText)}</span>` : ''}
                        </p>
                    </div>
                </a>
            `;
        }).join('');

        moviesSkeleton?.classList.add('d-none');
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

    function safeImageUrl(value) {
        const candidate = String(value || '').trim();
        if (/^\/(?!\/)[A-Za-z0-9_./?=&%-]+$/.test(candidate) && !candidate.includes('..')) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate.replace(/[()\\]/g, encodeURIComponent);
        return null;
    }

    function safeHttpsUrl(value) {
        const candidate = String(value || '').trim();
        return /^https:\/\/[^\s"'<>]+$/i.test(candidate) ? candidate : null;
    }

    function showError() {
        const heroSkeleton = document.getElementById('heroSkeleton');
        const bookingSkeleton = document.getElementById('bookingSkeleton');
        const moviesSkeleton = document.getElementById('moviesSkeleton');

        heroSkeleton?.classList.add('d-none');
        bookingSkeleton?.classList.add('d-none');
        moviesSkeleton?.classList.add('d-none');

        // Use Toast notification instead of inline alerts
        if (typeof Toast !== 'undefined') {
            Toast.error(
                'Unable to load content',
                'Please refresh the page or try again later.'
            );
        } else {
            // Fallback to inline alerts if Toast not available
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
    }

    // Expose for debugging
    window.homePage = {
        reload: init,
        data: () => homeData
    };
})();
