/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MOVIES LIST PAGE MODULE (NEW UI)
 * ═══════════════════════════════════════════════════════════════════════════
 */

import Toast from '../components/toast.js';

(function() {
    'use strict';

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        try {
            setupScrollControls();
            
            // Fetch all sections in parallel
            await Promise.all([
                loadSpecialScreenings(),
                loadNowShowing(),
                loadComingSoon()
            ]);
        } catch (error) {
            console.error('Failed to initialize movies page:', error);
            Toast.error('Lỗi tải dữ liệu', 'Vui lòng tải lại trang.');
        }
    }

    // 1. Suất chiếu đặc biệt (is_hot = 1)
    async function loadSpecialScreenings() {
        try {
            const response = await fetch('/api/v1/movies?is_hot=1&per_page=4');
            if (!response.ok) throw new Error('Failed to load special screenings');
            const data = await response.json();
            const movies = data.data || data.movies || [];

            if (movies.length > 0) {
                renderSpecialScreenings(movies);
            }
        } catch (error) {
            console.error(error);
        }
    }

    function renderSpecialScreenings(movies) {
        const section = document.getElementById('specialSection');
        const grid = document.getElementById('specialMoviesGrid');
        
        grid.innerHTML = movies.map(movie => `
            <div class="special-card" style="background-image: url('${escapeHtml(movie.banner_display_url || movie.poster_display_url || '/images/default-banner.jpg')}');">
                <div class="special-card-overlay"></div>
                
                <div class="special-badge">
                    <span class="badge bg-danger">EXCLUSIVE PREMIERE</span>
                </div>
                
                <div class="special-card-content">
                    <h3 class="special-title">${escapeHtml(movie.title)}</h3>
                    <p class="special-desc">${escapeHtml(movie.description ? movie.description.substring(0, 100) + '...' : '')}</p>
                    
                    <div class="special-actions">
                        <a href="/movies/${movie.slug}" class="btn btn-danger btn-book-early">
                            <i class="bi bi-ticket-perforated"></i> Book Early Access
                        </a>
                        <a href="/movies/${movie.slug}" class="btn btn-outline-light btn-details">
                            <i class="bi bi-info-circle"></i> Details
                        </a>
                    </div>
                </div>
            </div>
        `).join('');

        section.classList.remove('d-none');
    }

    // 2. Phim đang chiếu (status = now_showing)
    async function loadNowShowing() {
        const skeleton = document.getElementById('nowShowingSkeleton');
        const container = document.getElementById('nowShowingContainer');
        const grid = document.getElementById('nowShowingGrid');

        try {
            const response = await fetch('/api/v1/movies?status=now_showing&per_page=10');
            if (!response.ok) throw new Error('Failed to load now showing');
            const data = await response.json();
            const movies = data.data || data.movies || [];

            grid.innerHTML = movies.map(movie => renderNormalCard(movie, true)).join('');
            
            skeleton.classList.add('d-none');
            container.classList.remove('d-none');
        } catch (error) {
            console.error(error);
            skeleton.classList.add('d-none');
        }
    }

    // 3. Phim sắp chiếu (status = upcoming)
    async function loadComingSoon() {
        const skeleton = document.getElementById('comingSoonSkeleton');
        const container = document.getElementById('comingSoonContainer');
        const grid = document.getElementById('comingSoonGrid');

        try {
            const response = await fetch('/api/v1/movies?status=upcoming&per_page=10');
            if (!response.ok) throw new Error('Failed to load coming soon');
            const data = await response.json();
            const movies = data.data || data.movies || [];

            grid.innerHTML = movies.map(movie => renderNormalCard(movie, false)).join('');
            
            skeleton.classList.add('d-none');
            container.classList.remove('d-none');
        } catch (error) {
            console.error(error);
            skeleton.classList.add('d-none');
        }
    }

    function renderNormalCard(movie, isNowShowing) {
        const categories = movie.categories ? movie.categories.map(c => c.name).join(', ') : '';
        const rating = movie.age_rating || 'N/A';
        const poster = escapeHtml(movie.poster_display_url || '/images/default-poster.jpg');
        
        // Format release date for upcoming
        let releaseText = '';
        if (!isNowShowing && movie.release_date) {
            const date = new Date(movie.release_date);
            const months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
            releaseText = `RELEASING ${months[date.getMonth()]} ${date.getDate()}`;
        }

        if (isNowShowing) {
            return `
                <div class="movie-card-vertical">
                    <a href="/movies/${movie.slug}" class="poster-container">
                        <img src="${poster}" alt="${escapeHtml(movie.title)}">
                    </a>
                    <div class="movie-actions-overlay">
                        <a href="/movies/${movie.slug}" class="btn btn-danger btn-sm w-100 mb-2">Book Now</a>
                        <a href="${movie.trailer_url ? movie.trailer_url : '#'}" target="_blank" class="btn btn-dark btn-sm w-100">Trailer</a>
                    </div>
                    <div class="movie-info-bottom">
                        <h4 class="m-title text-truncate">${escapeHtml(movie.title)}</h4>
                        <div class="d-flex justify-content-between align-items-center m-meta">
                            <span class="m-cat text-truncate">${escapeHtml(categories)}</span>
                            <span class="m-rate"><i class="bi bi-star-fill text-warning me-1"></i> ${rating}</span>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Coming soon layout
            return `
                <div class="movie-card-coming">
                    <div class="coming-poster">
                        <img src="${poster}" alt="${escapeHtml(movie.title)}">
                    </div>
                    <div class="coming-info">
                        <div class="coming-date">${releaseText}</div>
                        <h4 class="coming-title">${escapeHtml(movie.title)}</h4>
                        <p class="coming-desc">${escapeHtml(movie.description ? movie.description.substring(0, 80) + '...' : '')}</p>
                        <button class="btn btn-link btn-remind p-0 mt-auto">
                            <i class="bi bi-bell"></i> Remind Me
                        </button>
                    </div>
                </div>
            `;
        }
    }

    function setupScrollControls() {
        const prevBtn = document.getElementById('scrollPrev');
        const nextBtn = document.getElementById('scrollNext');
        const container = document.getElementById('comingSoonContainer');

        if (prevBtn && nextBtn && container) {
            prevBtn.addEventListener('click', () => {
                container.scrollBy({ left: -300, behavior: 'smooth' });
            });
            nextBtn.addEventListener('click', () => {
                container.scrollBy({ left: 300, behavior: 'smooth' });
            });
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
