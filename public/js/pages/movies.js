/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MOVIES LIST PAGE MODULE
 * Handles movie listing, filtering, sorting, search, and pagination
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    let moviesData = [];
    let categoriesData = [];
    let currentPage = 1;
    let totalPages = 1;
    let filters = {
        status: 'active',
        category: '',
        search: '',
        sort: 'release_date-desc'
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        try {
            await loadCategories();
            await loadMovies();
            renderFilters();
            renderMovies();
            renderPagination();
            setupEventListeners();
        } catch (error) {
            console.error('Failed to initialize movies page:', error);
            showError();
        }
    }

    async function loadCategories() {
        try {
            const response = await fetch('/api/v1/categories');
            if (response.ok) {
                const data = await response.json();
                categoriesData = data.data || data.categories || [];
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            categoriesData = [];
        }
    }

    async function loadMovies() {
        try {
            // Build query params
            const params = new URLSearchParams({
                page: currentPage,
                per_page: 12
            });

            if (filters.status === 'now_showing') {
                params.append('status', 'now_showing');
            } else if (filters.status === 'upcoming') {
                params.append('status', 'upcoming');
            }

            if (filters.category) {
                params.append('category', filters.category);
            }

            if (filters.search) {
                params.append('search', filters.search);
            }

            if (filters.sort) {
                const [field, direction] = filters.sort.split('-');
                params.append('sort', field);
                params.append('direction', direction);
            }

            const response = await fetch(`/api/v1/movies?${params}`);
            if (!response.ok) throw new Error('Failed to load movies');

            const data = await response.json();
            moviesData = data.data || data.movies || [];

            // Handle pagination
            if (data.meta) {
                currentPage = data.meta.current_page || 1;
                totalPages = data.meta.last_page || 1;
            } else if (data.pagination) {
                currentPage = data.pagination.current_page || 1;
                totalPages = data.pagination.total_pages || 1;
            }
        } catch (error) {
            console.error('Error loading movies:', error);
            moviesData = [];
            throw error;
        }
    }

    function renderFilters() {
        const filtersSkeleton = document.getElementById('filtersSkeleton');
        const filtersContent = document.getElementById('filtersContent');

        // Populate category filter
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter && categoriesData.length > 0) {
            const currentValue = categoryFilter.value;
            categoryFilter.innerHTML = '<option value="">All Categories</option>' +
                categoriesData.map(cat =>
                    `<option value="${cat.id}" ${cat.id == currentValue ? 'selected' : ''}>
                        ${escapeHtml(cat.name)}
                    </option>`
                ).join('');
        }

        filtersSkeleton.style.display = 'none';
        filtersContent.classList.remove('d-none');
    }

    function renderMovies() {
        const moviesSkeleton = document.getElementById('moviesSkeleton');
        const moviesGrid = document.getElementById('moviesGrid');
        const emptyState = document.getElementById('emptyState');

        if (!moviesData || moviesData.length === 0) {
            moviesSkeleton.style.display = 'none';
            moviesGrid.classList.add('d-none');
            emptyState.classList.remove('d-none');
            return;
        }

        moviesGrid.innerHTML = moviesData.map(movie => `
            <a href="/movies/${movie.slug}" class="movie-card">
                <div class="movie-poster">
                    <img src="${escapeHtml(movie.poster_url || '/images/default-poster.jpg')}"
                         alt="${escapeHtml(movie.title)}"
                         loading="lazy">
                    ${movie.is_hot ? '<span class="movie-badge-hot">HOT</span>' : ''}
                    ${movie.status === 'upcoming' ? '<span class="movie-badge-upcoming">Coming Soon</span>' : ''}
                </div>
                <div class="movie-info">
                    <h3 class="movie-title">${escapeHtml(movie.title)}</h3>
                    <div class="movie-meta">
                        <span><i class="bi bi-clock"></i> ${movie.duration || 'N/A'} min</span>
                        <span><i class="bi bi-star-fill"></i> ${movie.age_rating || 'N/A'}</span>
                    </div>
                    ${movie.categories && movie.categories.length > 0 ? `
                        <div class="movie-categories">
                            ${movie.categories.slice(0, 2).map(cat =>
                                `<span class="category-tag">${escapeHtml(cat.name)}</span>`
                            ).join('')}
                        </div>
                    ` : ''}
                </div>
            </a>
        `).join('');

        moviesSkeleton.style.display = 'none';
        moviesGrid.classList.remove('d-none');
        emptyState.classList.add('d-none');
    }

    function renderPagination() {
        const paginationContainer = document.getElementById('paginationContainer');

        if (totalPages <= 1) {
            paginationContainer.classList.add('d-none');
            return;
        }

        let paginationHTML = '<ul class="pagination">';

        // Previous button
        paginationHTML += `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        // Page numbers (show max 7 pages)
        let startPage = Math.max(1, currentPage - 3);
        let endPage = Math.min(totalPages, startPage + 6);

        if (endPage - startPage < 6) {
            startPage = Math.max(1, endPage - 6);
        }

        if (startPage > 1) {
            paginationHTML += `
                <li class="page-item">
                    <button class="page-link" data-page="1">1</button>
                </li>
            `;
            if (startPage > 2) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <button class="page-link" data-page="${i}">${i}</button>
                </li>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            paginationHTML += `
                <li class="page-item">
                    <button class="page-link" data-page="${totalPages}">${totalPages}</button>
                </li>
            `;
        }

        // Next button
        paginationHTML += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;

        paginationHTML += '</ul>';

        paginationContainer.innerHTML = paginationHTML;
        paginationContainer.classList.remove('d-none');
    }

    function setupEventListeners() {
        // Status filter
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', async (e) => {
                filters.status = e.target.value;
                currentPage = 1;
                await reloadMovies();
            });
        }

        // Category filter
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', async (e) => {
                filters.category = e.target.value;
                currentPage = 1;
                await reloadMovies();
            });
        }

        // Sort filter
        const sortFilter = document.getElementById('sortFilter');
        if (sortFilter) {
            sortFilter.addEventListener('change', async (e) => {
                filters.sort = e.target.value;
                currentPage = 1;
                await reloadMovies();
            });
        }

        // Search
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');

        if (searchBtn) {
            searchBtn.addEventListener('click', async () => {
                filters.search = searchInput.value.trim();
                currentPage = 1;
                await reloadMovies();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('keypress', async (e) => {
                if (e.key === 'Enter') {
                    filters.search = searchInput.value.trim();
                    currentPage = 1;
                    await reloadMovies();
                }
            });
        }

        // Pagination
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.page-link[data-page]')) {
                const page = parseInt(e.target.closest('.page-link').dataset.page);
                if (page && page !== currentPage && page >= 1 && page <= totalPages) {
                    currentPage = page;
                    await reloadMovies();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        });
    }

    async function reloadMovies() {
        try {
            await loadMovies();
            renderMovies();
            renderPagination();
        } catch (error) {
            console.error('Error reloading movies:', error);
            showError();
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError() {
        const moviesSkeleton = document.getElementById('moviesSkeleton');
        const moviesGrid = document.getElementById('moviesGrid');
        const emptyState = document.getElementById('emptyState');

        if (moviesSkeleton) moviesSkeleton.style.display = 'none';
        if (moviesGrid) moviesGrid.classList.add('d-none');

        if (emptyState) {
            emptyState.innerHTML = `
                <i class="bi bi-exclamation-circle"></i>
                <h3>Unable to load movies</h3>
                <p>Please try refreshing the page</p>
            `;
            emptyState.classList.remove('d-none');
        }
    }

    // Expose for debugging
    window.moviesPage = {
        reload: reloadMovies,
        data: () => ({ moviesData, filters, currentPage, totalPages })
    };
})();
