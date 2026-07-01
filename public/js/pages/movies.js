(function() {
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

    let els = {};
    let currentFilters = {
        status: 'active',
        category_id: '',
        sort_by: 'release_date',
        sort_dir: 'desc',
        q: '',
        page: 1,
        per_page: 12
    };
    let categories = [];

    // Cache DOM elements
    function cacheDoms() {
        els = {
            filtersSkeleton: document.getElementById('filtersSkeleton'),
            filtersContent: document.getElementById('filtersContent'),
            moviesSkeleton: document.getElementById('moviesSkeleton'),
            moviesGrid: document.getElementById('moviesGrid'),
            emptyState: document.getElementById('emptyState'),
            paginationContainer: document.getElementById('paginationContainer'),

            statusFilter: document.getElementById('statusFilter'),
            categoryFilter: document.getElementById('categoryFilter'),
            sortFilter: document.getElementById('sortFilter'),
            searchInput: document.getElementById('searchInput'),
            searchBtn: document.getElementById('searchBtn')
        };
    }

    // Parse URL params and set initial filters
    function parseUrlParams() {
        const params = new URLSearchParams(window.location.search);

        if (params.get('status')) currentFilters.status = params.get('status');
        if (params.get('category_id')) currentFilters.category_id = params.get('category_id');
        if (params.get('q')) currentFilters.q = params.get('q');
        if (params.get('page')) currentFilters.page = parseInt(params.get('page'));

        const sort = params.get('sort');
        if (sort) {
            const [sortBy, sortDir] = sort.split('-');
            if (sortBy) currentFilters.sort_by = sortBy;
            if (sortDir) currentFilters.sort_dir = sortDir;
        }
    }

    // Update URL with current filters
    function updateUrl() {
        const params = new URLSearchParams();

        if (currentFilters.status !== 'active') params.set('status', currentFilters.status);
        if (currentFilters.category_id) params.set('category_id', currentFilters.category_id);
        if (currentFilters.q) params.set('q', currentFilters.q);
        if (currentFilters.page > 1) params.set('page', currentFilters.page);

        const sort = `${currentFilters.sort_by}-${currentFilters.sort_dir}`;
        if (sort !== 'release_date-desc') params.set('sort', sort);

        const newUrl = params.toString() ? `?${params.toString()}` : window.location.pathname;
        window.history.pushState({}, '', newUrl);
    }

    // Load categories
    async function loadCategories() {
        try {
            // For now, we'll use hardcoded categories until the Category API is ready
            categories = [
                { id: 1, name: 'Action' },
                { id: 2, name: 'Comedy' },
                { id: 3, name: 'Drama' },
                { id: 4, name: 'Horror' },
                { id: 5, name: 'Sci-Fi' },
                { id: 6, name: 'Romance' }
            ];

            renderCategories();
        } catch (err) {
            console.error('[Movies] Failed to load categories:', err);
        }
    }

    // Render category options
    function renderCategories() {
        if (!els.categoryFilter) return;

        const options = categories.map(cat =>
            `<option value="${escapeAttr(cat.id)}" ${currentFilters.category_id == cat.id ? 'selected' : ''}>
                ${escapeHtml(cat.name)}
            </option>`
        ).join('');

        els.categoryFilter.innerHTML = '<option value="">All Categories</option>' + options;
    }

    // Load movies from API
    async function loadMovies() {
        try {
            const params = new URLSearchParams();

            if (currentFilters.status) params.set('status', currentFilters.status);
            if (currentFilters.category_id) params.set('category_id', currentFilters.category_id);
            if (currentFilters.q) params.set('q', currentFilters.q);
            if (currentFilters.sort_by) params.set('sort_by', currentFilters.sort_by);
            if (currentFilters.sort_dir) params.set('sort_dir', currentFilters.sort_dir);
            params.set('page', currentFilters.page);
            params.set('per_page', currentFilters.per_page);

            const json = await window.apiClient.get(`/movies?${params.toString()}`);

            if (!json.success) throw new Error(json.message || 'Failed to load movies');

            renderMovies(json.data);
            renderPagination(json.pagination);

            setTimeout(showLoaded, 350);
        } catch (err) {
            console.error('[Movies] Load error:', err);
            showError(err.message);
        }
    }

    // Render movies grid
    function renderMovies(movies) {
        if (!els.moviesGrid) return;

        if (movies.length === 0) {
            els.moviesGrid.classList.add('d-none');
            els.emptyState?.classList.remove('d-none');
            els.paginationContainer?.classList.add('d-none');
            return;
        }

        els.moviesGrid.classList.remove('d-none');
        els.emptyState?.classList.add('d-none');

        els.moviesGrid.innerHTML = movies.map(movie => {
            const movieUrl = movie.slug ? '/movies/' + escapeAttr(movie.slug) : '/movies/' + escapeAttr(movie.id);
            const posterUrl = escapeAttr(movie.poster_url || '/images/placeholder-poster.jpg');
            const title = escapeHtml(movie.title);
            const meta = escapeHtml(formatMovieMeta(movie));

            return `
                <div class="movie-card">
                    <a href="${movieUrl}" class="movie-card-link">
                        <img src="${posterUrl}"
                             alt="${title}"
                             class="movie-poster"
                             loading="lazy">
                        <div class="movie-gradient"></div>
                        <div class="movie-info">
                            <h3 class="movie-title">${title}</h3>
                            <p class="movie-meta">${meta}</p>
                        </div>
                    </a>
                </div>
            `;
        }).join('');
    }

    // Format movie metadata
    function formatMovieMeta(movie) {
        const parts = [];

        if (movie.age_rating) parts.push(movie.age_rating);
        if (movie.duration) parts.push(`${movie.duration} min`);

        if (movie.categories && movie.categories.length > 0) {
            parts.push(movie.categories[0].name);
        }

        return parts.join(' • ');
    }

    // Render pagination
    function renderPagination(data) {
        if (!els.paginationContainer) return;

        const { current_page, last_page, from, to, total } = data;

        if (last_page <= 1) {
            els.paginationContainer.classList.add('d-none');
            return;
        }

        els.paginationContainer.classList.remove('d-none');

        let html = `
            <button class="pagination-btn" ${current_page === 1 ? 'disabled' : ''} data-page="${current_page - 1}">
                <i class="bi bi-chevron-left"></i>
            </button>
        `;

        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(last_page, current_page + 2);

        if (startPage > 1) {
            html += `<button class="pagination-page" data-page="1">1</button>`;
            if (startPage > 2) html += `<span class="pagination-info">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-page ${i === current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < last_page) {
            if (endPage < last_page - 1) html += `<span class="pagination-info">...</span>`;
            html += `<button class="pagination-page" data-page="${last_page}">${last_page}</button>`;
        }

        html += `
            <button class="pagination-btn" ${current_page === last_page ? 'disabled' : ''} data-page="${current_page + 1}">
                <i class="bi bi-chevron-right"></i>
            </button>
        `;

        if (from && to && total) {
            html += `<span class="pagination-info">Showing ${from}-${to} of ${total}</span>`;
        }

        els.paginationContainer.innerHTML = html;

        // Add click handlers
        els.paginationContainer.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page);
                if (page !== currentFilters.page) {
                    currentFilters.page = page;
                    updateUrl();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    showSkeleton();
                    loadMovies();
                }
            });
        });
    }

    // Show loaded state
    function showLoaded() {
        els.filtersSkeleton?.classList.add('d-none');
        els.filtersContent?.classList.remove('d-none');
        els.moviesSkeleton?.classList.add('d-none');
        els.moviesGrid?.classList.remove('d-none');
    }

    // Show skeleton state
    function showSkeleton() {
        els.moviesSkeleton?.classList.remove('d-none');
        els.moviesGrid?.classList.add('d-none');
        els.emptyState?.classList.add('d-none');
        els.paginationContainer?.classList.add('d-none');
    }

    // Show error
    function showError(message) {
        els.filtersSkeleton?.classList.add('d-none');
        els.filtersContent?.classList.remove('d-none');
        els.moviesSkeleton?.classList.add('d-none');
        els.moviesGrid?.classList.remove('d-none');
        els.moviesGrid.innerHTML = `
            <div class="error-state" style="grid-column: 1 / -1;">
                <i class="bi bi-exclamation-circle"></i>
                <p>${escapeHtml(message)}</p>
                <button onclick="location.reload()" class="cinema-primary-btn">Retry</button>
            </div>
        `;
    }

    // Setup filter event listeners
    function setupFilters() {
        // Set initial filter values
        if (els.statusFilter) els.statusFilter.value = currentFilters.status;
        if (els.searchInput) els.searchInput.value = currentFilters.q;

        const sortValue = `${currentFilters.sort_by}-${currentFilters.sort_dir}`;
        if (els.sortFilter) els.sortFilter.value = sortValue;

        // Status filter
        els.statusFilter?.addEventListener('change', (e) => {
            currentFilters.status = e.target.value;
            currentFilters.page = 1;
            updateUrl();
            showSkeleton();
            loadMovies();
        });

        // Category filter
        els.categoryFilter?.addEventListener('change', (e) => {
            currentFilters.category_id = e.target.value;
            currentFilters.page = 1;
            updateUrl();
            showSkeleton();
            loadMovies();
        });

        // Sort filter
        els.sortFilter?.addEventListener('change', (e) => {
            const [sortBy, sortDir] = e.target.value.split('-');
            currentFilters.sort_by = sortBy;
            currentFilters.sort_dir = sortDir;
            currentFilters.page = 1;
            updateUrl();
            showSkeleton();
            loadMovies();
        });

        // Search
        const performSearch = () => {
            currentFilters.q = els.searchInput.value.trim();
            currentFilters.page = 1;
            updateUrl();
            showSkeleton();
            loadMovies();
        };

        els.searchBtn?.addEventListener('click', performSearch);
        els.searchInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') performSearch();
        });
    }

    // Initialize
    async function init() {
        cacheDoms();
        parseUrlParams();
        setupFilters();

        await loadCategories();
        await loadMovies();
    }

    // Start when DOM is ready
    document.addEventListener('DOMContentLoaded', init);
})();
