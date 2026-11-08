(function () {
    'use strict';

    const STORAGE_KEY = 'cinema_recent_searches';
    let trendingMoviesCache = null;
    let debounceTimer = null;
    let abortController = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function safeImageUrl(url) {
        if (!url) return '/images/default-poster.jpg';
        return url;
    }

    function getRecentSearches() {
        try {
            const data = localStorage.getItem(STORAGE_KEY);
            return data ? JSON.parse(data) : [];
        } catch {
            return [];
        }
    }

    function saveRecentSearch(keyword) {
        const trimmed = keyword.trim();
        if (!trimmed) return;
        let list = getRecentSearches().filter(item => item.toLowerCase() !== trimmed.toLowerCase());
        list.unshift(trimmed);
        if (list.length > 5) list = list.slice(0, 5);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch {}
    }

    function removeRecentSearch(keyword) {
        let list = getRecentSearches().filter(item => item !== keyword);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch {}
    }

    function clearAllRecentSearches() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {}
    }

    function init() {
        const trigger = document.getElementById('fbSearchTrigger');
        const panel = document.getElementById('fbSearchPanel');
        const backdrop = document.getElementById('fbSearchBackdrop');
        const input = document.getElementById('fbSearchInput');
        const clearBtn = document.getElementById('fbSearchClearBtn');
        const recentSec = document.getElementById('fbSearchRecent');
        const recentList = document.getElementById('fbSearchRecentList');
        const clearAllBtn = document.getElementById('fbSearchClearAll');
        const trendingSec = document.getElementById('fbSearchTrending');
        const trendingList = document.getElementById('fbSearchTrendingList');
        const resultsSec = document.getElementById('fbSearchResults');
        const resultsList = document.getElementById('fbSearchResultsList');
        const loading = document.getElementById('fbSearchLoading');
        const empty = document.getElementById('fbSearchEmpty');

        if (!trigger || !panel || !backdrop || !input) return;

        function openSearch() {
            panel.classList.remove('d-none');
            backdrop.classList.remove('d-none');
            input.value = '';
            clearBtn.classList.add('d-none');
            showIdleState();
            renderRecent();
            loadTrending();
            setTimeout(() => input.focus(), 50);
        }

        function closeSearch() {
            panel.classList.add('d-none');
            backdrop.classList.add('d-none');
            if (abortController) {
                abortController.abort();
                abortController = null;
            }
        }

        function showIdleState() {
            resultsSec.classList.add('d-none');
            loading.classList.add('d-none');
            empty.classList.add('d-none');
            trendingSec.classList.remove('d-none');
            renderRecent();
        }

        function renderRecent() {
            const recent = getRecentSearches();
            if (recent.length === 0) {
                recentSec.classList.add('d-none');
                return;
            }
            recentSec.classList.remove('d-none');
            recentList.innerHTML = recent.map(kw => `
                <div class="fb-search-recent-item" data-keyword="${escapeHtml(kw)}">
                    <div class="fb-search-recent-left">
                        <div class="fb-search-recent-icon"><i class="bi bi-clock-history"></i></div>
                        <span class="fb-search-recent-text">${escapeHtml(kw)}</span>
                    </div>
                    <button type="button" class="fb-search-recent-del" data-del="${escapeHtml(kw)}" aria-label="Xóa">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `).join('');
        }

        async function loadTrending() {
            if (trendingMoviesCache) {
                renderTrending(trendingMoviesCache);
                return;
            }
            try {
                const res = await fetch('/api/v1/movies?is_hot=1&per_page=4');
                if (res.ok) {
                    const json = await res.json();
                    trendingMoviesCache = json.data || [];
                    renderTrending(trendingMoviesCache);
                }
            } catch {}
        }

        function renderTrending(movies) {
            if (!movies || movies.length === 0) {
                trendingSec.classList.add('d-none');
                return;
            }
            trendingSec.classList.remove('d-none');
            trendingList.innerHTML = movies.map(movie => {
                const categories = (movie.categories || []).map(c => c.name).slice(0, 2).join(', ');
                const year = movie.release_date ? new Date(movie.release_date).getFullYear() : '';
                const age = movie.age_rating || 'P';
                return `
                    <a href="/movies/${escapeHtml(movie.slug)}" class="fb-search-item" data-title="${escapeHtml(movie.title)}">
                        <img src="${safeImageUrl(movie.poster_display_url || movie.poster_url)}" class="fb-search-item-thumb" alt="${escapeHtml(movie.title)}" loading="lazy">
                        <div class="fb-search-item-info">
                            <div class="fb-search-item-title">${escapeHtml(movie.title)}</div>
                            <div class="fb-search-item-meta">
                                <span class="badge-rating">${escapeHtml(age)}</span>
                                <span>${year ? escapeHtml(year) + ' • ' : ''}${escapeHtml(categories || 'Phim rạp')}</span>
                            </div>
                        </div>
                    </a>
                `;
            }).join('');
        }

        function performSearch(query) {
            const trimmed = query.trim();
            if (!trimmed) {
                showIdleState();
                return;
            }

            recentSec.classList.add('d-none');
            trendingSec.classList.add('d-none');
            empty.classList.add('d-none');
            resultsSec.classList.add('d-none');
            loading.classList.remove('d-none');

            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/api/v1/movies?q=${encodeURIComponent(trimmed)}&per_page=5`, {
                        signal: abortController.signal
                    });
                    loading.classList.add('d-none');
                    if (!res.ok) throw new Error('Search failed');

                    const json = await res.json();
                    const movies = json.data || [];

                    if (movies.length === 0) {
                        empty.classList.remove('d-none');
                        resultsSec.classList.add('d-none');
                    } else {
                        empty.classList.add('d-none');
                        resultsSec.classList.remove('d-none');
                        renderResults(movies);
                    }
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        loading.classList.add('d-none');
                        empty.classList.remove('d-none');
                    }
                }
            }, 180);
        }

        function renderResults(movies) {
            resultsList.innerHTML = movies.map(movie => {
                const categories = (movie.categories || []).map(c => c.name).slice(0, 2).join(', ');
                const year = movie.release_date ? new Date(movie.release_date).getFullYear() : '';
                const age = movie.age_rating || 'P';
                return `
                    <a href="/movies/${escapeHtml(movie.slug)}" class="fb-search-item" data-title="${escapeHtml(movie.title)}">
                        <img src="${safeImageUrl(movie.poster_display_url || movie.poster_url)}" class="fb-search-item-thumb" alt="${escapeHtml(movie.title)}" loading="lazy">
                        <div class="fb-search-item-info">
                            <div class="fb-search-item-title">${escapeHtml(movie.title)}</div>
                            <div class="fb-search-item-meta">
                                <span class="badge-rating">${escapeHtml(age)}</span>
                                <span>${year ? escapeHtml(year) + ' • ' : ''}${escapeHtml(categories || 'Phim rạp')}</span>
                            </div>
                        </div>
                    </a>
                `;
            }).join('');
        }

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (panel.classList.contains('d-none')) {
                openSearch();
            } else {
                closeSearch();
            }
        });

        backdrop.addEventListener('click', closeSearch);

        document.addEventListener('click', (e) => {
            if (!panel.classList.contains('d-none')) {
                if (!panel.contains(e.target) && !trigger.contains(e.target)) {
                    closeSearch();
                }
            }
        });

        panel.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        input.addEventListener('input', () => {
            const val = input.value;
            clearBtn.classList.toggle('d-none', !val);
            performSearch(val);
        });

        clearBtn.addEventListener('click', () => {
            input.value = '';
            clearBtn.classList.add('d-none');
            input.focus();
            showIdleState();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSearch();
            } else if (e.key === 'Enter') {
                const query = input.value.trim();
                if (query) {
                    saveRecentSearch(query);
                    window.location.href = `/movies?q=${encodeURIComponent(query)}`;
                }
            }
        });

        recentList.addEventListener('click', (e) => {
            const delBtn = e.target.closest('.fb-search-recent-del');
            if (delBtn) {
                e.stopPropagation();
                const kw = delBtn.getAttribute('data-del');
                removeRecentSearch(kw);
                renderRecent();
                return;
            }
            const item = e.target.closest('.fb-search-recent-item');
            if (item) {
                const kw = item.getAttribute('data-keyword');
                if (kw) {
                    input.value = kw;
                    clearBtn.classList.remove('d-none');
                    performSearch(kw);
                }
            }
        });

        clearAllBtn.addEventListener('click', () => {
            clearAllRecentSearches();
            renderRecent();
        });

        panel.addEventListener('click', (e) => {
            const movieLink = e.target.closest('.fb-search-item');
            if (movieLink) {
                const title = movieLink.getAttribute('data-title');
                if (title) saveRecentSearch(title);
            }
        });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                openSearch();
            } else if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                e.preventDefault();
                openSearch();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
