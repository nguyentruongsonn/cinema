/**
 * ═══════════════════════════════════════════════════════════════════════════
 * CINEMA JOURNAL — Customer Posts & Articles Page Controller (SPA Edition)
 * Handles Single-Page Application dynamic rendering for /posts:
 * - Fetch from /api/v1/posts with Skeleton loading
 * - Horizontal News Cards (.cinema-news-card.horizontal-card) using existing CSS
 * - Category Tab Filtering & Live Debounced Search
 * - Dynamic SPA Pagination
 * - Sidebar: Large Trailer Cards + Popular Tags + Newsletter
 * ═══════════════════════════════════════════════════════════════════════════
 */

import Toast from '../components/toast.js';
import HybridPage from '../components/hybrid-page.js';
import CinemaPagination from '../components/pagination.js';

(function () {
    'use strict';

    let currentPage = 1;
    let currentCategory = '';
    let currentSearch = '';
    let searchDebounceTimer = null;
    let heroReady = false;
    let sidebarReady = false;
    let heroRegion = null;
    let postsRegion = null;
    let sidebarRegion = null;

    function init() {
        setupNewsletterForms();
        setupArticleActions();

        if (document.getElementById('postsSpaContainer')) {
            bindHybridRegions();
            hydrateStateFromUrl();
            setupSpaEventListeners();
            syncFilterControls();
            loadPosts(currentPage, currentCategory, currentSearch);
        }
    }

    function bindHybridRegions() {
        heroRegion = HybridPage.bindRegion({
            skeleton: '#heroSkeleton',
            content: '#heroContent',
            busyTarget: '.posts-hero-shell',
        });

        postsRegion = HybridPage.bindRegion({
            skeleton: '#postsSkeletonGrid',
            content: '#postsGrid',
            empty: '#postsEmptyState',
            error: '#postsErrorState',
            busyTarget: '#postsMainSection',
        });

        sidebarRegion = HybridPage.bindRegion({
            skeleton: '#sidebarTrailersSkeleton',
            content: '#sidebarTrailersGrid',
            busyTarget: '.sidebar-widget-section',
        });
    }

    function hydrateStateFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const allowedCategories = new Set(['news', 'blog', 'promotion', 'event', 'announcement']);
        const requestedCategory = params.get('category') || '';
        currentCategory = allowedCategories.has(requestedCategory) ? requestedCategory : '';
        currentSearch = (params.get('search') || '').trim().slice(0, 100);
        currentPage = Math.max(1, Number.parseInt(params.get('page') || '1', 10) || 1);
    }

    function syncFilterControls() {
        const searchInput = document.getElementById('postSearchInput');
        if (searchInput) searchInput.value = currentSearch;

        document.querySelectorAll('#postsFilterTabs .cinema-pill-tab').forEach(tab => {
            const isActive = (tab.dataset.value || '') === currentCategory;
            if (isActive) window.CinemaTabs?.activate(tab, { emit: false });
        });
    }

    /* ─── XSS protection ──────────────────────────────────────────────────── */
    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ─── Tab & Search listeners ──────────────────────────────────────────── */
    function setupSpaEventListeners() {
        // Category tabs
        document.getElementById('postsFilterTabs')?.addEventListener('cinema:tab-change', event => {
            currentCategory = event.detail.value || '';
            currentPage = 1;
            loadPosts(1, currentCategory, currentSearch);
        });

        // Debounced live search
        const searchInput = document.getElementById('postSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', e => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    currentSearch = e.target.value.trim();
                    currentPage = 1;
                    loadPosts(1, currentCategory, currentSearch);
                }, 350);
            });
        }

        // Reset button (shown when empty)
        document.addEventListener('click', e => {
            if (e.target && e.target.id === 'btnResetFilter') {
                e.preventDefault();
                currentCategory = '';
                currentSearch = '';
                if (searchInput) searchInput.value = '';
                syncFilterControls();
                loadPosts(1, '', '');
            }
        });
    }

    /* ─── Main fetch ──────────────────────────────────────────────────────── */
    async function loadPosts(page = 1, category = '', search = '') {
        currentPage = page;
        showSkeletons();

        try {
            const qs = new URLSearchParams({ page: String(page), per_page: '10' });
            if (category) qs.append('category', category);
            if (search)   qs.append('search', search);

            const data = await window.apiClient.get(`/posts?${qs}`);

            if (!heroReady) renderHero(data.featured_post);
            renderPostsGrid(data.data || []);
            renderPagination(data.pagination || {});
            if (!sidebarReady) {
                renderTrailers(data.sidebar_trailers || []);
                renderTags(data.popular_tags || []);
                sidebarReady = true;
            }
        } catch (err) {
            console.error('SPA posts fetch error:', err);
            Toast.error('Không thể tải bài viết', 'Vui lòng kiểm tra kết nối và thử lại.');
            postsRegion?.failed('Khong the tai bai viet. Vui long thu lai sau.');
            hideSkeletons();
        }
    }

    /* ─── Skeleton helpers ────────────────────────────────────────────────── */
    function showSkeletons() {
        if (!heroReady) {
            heroRegion?.loading();
        }
        postsRegion?.loading();
        if (!sidebarReady) {
            sidebarRegion?.loading();
        }
    }

    function hideSkeletons() {
        hide('heroSkeleton');
        hide('postsSkeletonGrid');
        hide('sidebarTrailersSkeleton');
    }

    function hide(id) { document.getElementById(id)?.classList.add('d-none'); }

    /* ─── 1. Hero Banner ──────────────────────────────────────────────────── */
    function renderHero(featured) {
        const heroEl = document.getElementById('heroContent');
        if (!heroEl) return;

        heroReady = true;

        if (featured) {
            const slugUrl = `/posts/${encodeURIComponent(featured.slug || '')}`;
            const bgUrl = safeAssetUrl(featured.image_url || featured.featured_image_url || '');
            heroEl.style.backgroundImage = bgUrl ? `url("${bgUrl.replace(/"/g, '%22')}")` : '';

            const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            const href = (id, url) => { const el = document.getElementById(id); if (el) el.setAttribute('href', url); };

            set('heroBadge',    (featured.category_label || 'ĐỘC QUYỀN').toUpperCase());
            set('heroExcerpt',  featured.excerpt || '');
            set('heroReadTime', `${featured.reading_time || 5} phút đọc`);

            const titleEl = document.getElementById('heroTitleLink');
            if (titleEl) { titleEl.textContent = featured.title || ''; titleEl.setAttribute('href', slugUrl); }

            href('heroReadBtn', slugUrl);
            heroRegion?.ready();
        } else {
            heroRegion?.ready({ empty: true });
        }
    }

    function safeAssetUrl(value) {
        const url = String(value || '').trim();
        if (!url) return '';
        if (url.startsWith('/')) return url;

        try {
            const parsed = new URL(url, window.location.origin);
            return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
        } catch {
            return '';
        }
    }

    /* ─── 2. Horizontal News Cards ────────────────────────────────────────── */
    function renderPostsGrid(posts) {
        const grid  = document.getElementById('postsGrid');
        if (!grid) return;

        if (!posts.length) {
            grid.innerHTML = '';
            postsRegion?.ready({ empty: true });
            return;
        }

        grid.innerHTML = posts.map(post => {
            const slugUrl  = `/posts/${encodeURIComponent(post.slug || '')}`;
            const thumb    = esc(safeAssetUrl(post.image_url || post.featured_image_url || '') || '/images/default-banner.jpg');
            const cat      = esc(post.category || '');
            const badge    = post.category_label ? esc(post.category_label).toUpperCase() : '';
            const author   = esc(post.author_name || 'Cinema Premium');
            const avatar   = `https://ui-avatars.com/api/?name=${encodeURIComponent(author)}&background=2a2a35&color=ffffff&size=64`;
            const readTime = esc(post.reading_time || 5);
            const title    = esc(post.title || '');
            const excerpt  = esc(post.excerpt || '');

            return `
<article class="cinema-news-card horizontal-card mb-4">
    <a href="${slugUrl}" class="news-card-img-wrapper d-block flex-shrink-0">
        <img src="${thumb}" alt="${title}" loading="lazy" data-fallback-src="/images/default-banner.jpg">
    </a>
    <div class="news-card-body d-flex flex-column justify-content-between flex-grow-1">
        <div class="news-card-content">
            ${badge ? `<span class="card-cat-label label-${cat}">${badge}</span>` : ''}
            <h3 class="news-card-title">
                <a href="${slugUrl}">${title}</a>
            </h3>
            <p class="news-card-excerpt">${excerpt}</p>
        </div>
        <div class="news-card-footer mt-3 d-flex align-items-center justify-content-between">
            <div class="author-info d-flex align-items-center">
                <img src="${avatar}" class="author-avatar-img me-2" alt="${author}">
                <span class="author-name-text">${author}</span>
            </div>
            <div class="card-meta-right">
                <span class="card-meta-item"><i class="bi bi-clock me-1"></i>${readTime} phút đọc</span>
            </div>
        </div>
    </div>
</article>`;
        }).join('');

        bindImageFallbacks(grid);
        postsRegion?.ready();
    }

    /* ─── 3. SPA Pagination ───────────────────────────────────────────────── */
    function renderPagination({ current_page, last_page, total, per_page }) {
        CinemaPagination.render({
            container: '#postsPaginationContainer',
            pagination: { current_page, last_page, total, per_page },
            itemLabel: 'bài viết',
            onPageChange(page) {
                if (page && page !== currentPage) {
                    loadPosts(page, currentCategory, currentSearch);
                    document.getElementById('postsMainSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },
        });
    }
    /* ─── 4. Sidebar: Large Trailer Cards (chuẩn mockup) ─────────────────── */
    function renderTrailers(trailers) {
        const grid = document.getElementById('sidebarTrailersGrid');
        if (!grid) return;

        if (!trailers.length) {
            grid.innerHTML = '<p class="text-secondary small mb-0">Chưa có trailer nào.</p>';
        } else {
            grid.innerHTML = trailers.map(m => {
                const slug   = encodeURIComponent(m.slug || m.id || '');
                const title  = esc(m.title || '');
                const poster = esc(safeAssetUrl(m.poster_display_url || m.poster_url || '') || '/images/default-banner.jpg');
                const age = esc(m.age_rating || '');

                return `
<a href="/movies/${slug}" class="sidebar-trailer-card d-block text-decoration-none mb-3">
    <div class="trailer-thumbnail position-relative">
        <img src="${poster}" alt="Poster ${title}" loading="lazy" data-fallback-src="/images/default-poster.jpg">
        <div class="trailer-play-overlay">
            <div class="play-btn-sm"><i class="bi bi-play-fill"></i></div>
        </div>
        ${age ? `<span class="trailer-age-badge">${age}</span>` : ''}
        <div class="trailer-card-caption">
            <h4 class="trailer-caption-title">${title}</h4>
        </div>
    </div>
</a>`;
            }).join('');
        }

        bindImageFallbacks(grid);
        sidebarRegion?.ready();
    }

    function bindImageFallbacks(container) {
        container.querySelectorAll('img[data-fallback-src]').forEach(image => {
            image.addEventListener('error', () => {
                const fallback = image.dataset.fallbackSrc;
                if (!fallback || image.getAttribute('src') === fallback) return;
                image.setAttribute('src', fallback);
            }, { once: true });
        });
    }

    /* ─── 5. Sidebar: Popular Tags ────────────────────────────────────────── */
    function renderTags(tags) {
        const el = document.getElementById('sidebarTagsContainer');
        if (!el) return;

        if (!tags.length) { el.innerHTML = '<span class="text-secondary small">Chưa có chủ đề</span>'; return; }

        el.innerHTML = tags.map(tag => {
            const t = esc(tag);
            return `<a href="#" class="cinema-pill-tag spa-tag-btn" data-tag="${t}">${t}</a>`;
        }).join('');

        el.querySelectorAll('.spa-tag-btn').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const tag = a.dataset.tag || '';
                const inp = document.getElementById('postSearchInput');
                if (inp) inp.value = tag;
                currentSearch = tag;
                loadPosts(1, currentCategory, currentSearch);
                window.scrollTo({ top: 100, behavior: 'smooth' });
            });
        });
    }

    /* ─── Newsletter forms ────────────────────────────────────────────────── */
    function setupNewsletterForms() {
        document.querySelectorAll('#newsletterForm, #detailNewsletterForm').forEach(form => {
            form.addEventListener('submit', e => {
                e.preventDefault();
                const inp = form.querySelector('input[type="email"]');
                if (inp && inp.value.trim()) {
                    Toast.success('Đăng ký thành công', 'Cảm ơn bạn đã đồng hành cùng Poly Cinema.');
                    form.reset();
                }
            });
        });
    }

    /* ─── Article detail actions ──────────────────────────────────────────── */
    function setupArticleActions() {
        document.querySelector('[data-back-to-top]')?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        const btnShare = document.getElementById('btnShareArticle');
        if (btnShare) {
            btnShare.addEventListener('click', async () => {
                const url = location.href;
                if (navigator.share) {
                    try { await navigator.share({ title: document.title, url }); } catch { copyUrl(url); }
                } else {
                    copyUrl(url);
                }
            });
        }

        const btnBookmark = document.querySelector('.btn-article-action[title="Lưu bài viết"]');
        if (btnBookmark) {
            btnBookmark.addEventListener('click', () => {
                btnBookmark.classList.toggle('active');
                if (btnBookmark.classList.contains('active')) {
                    Toast.success('Đã lưu bài viết', 'Bài viết đã được thêm vào danh sách quan tâm.');
                } else {
                    Toast.info('Đã bỏ lưu bài viết');
                }
            });
        }
    }

    function copyUrl(text) {
        navigator.clipboard.writeText(text)
            .then(() => Toast.success('Đã sao chép liên kết'))
            .catch(() => Toast.error('Không thể sao chép liên kết', 'Vui lòng sao chép thủ công từ thanh địa chỉ.'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
