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

(function () {
    'use strict';

    let currentPage = 1;
    let currentCategory = '';
    let currentSearch = '';
    let searchDebounceTimer = null;
    let heroReady = false;
    let sidebarReady = false;

    function init() {
        setupNewsletterForms();
        setupArticleActions();

        if (document.getElementById('postsSpaContainer')) {
            setupSpaEventListeners();
            loadPosts(1, '', '');
        }
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

    function safeAssetUrl(value) {
        const candidate = String(value || '').trim();
        if (!candidate) return '';
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes('..')) return candidate;

        try {
            const parsed = new URL(candidate, window.location.origin);
            return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
        } catch {
            return '';
        }
    }

    /* ─── Tab & Search listeners ──────────────────────────────────────────── */
    function setupSpaEventListeners() {
        // Category tabs
        document.querySelectorAll('#postsFilterTabs .cinema-pill-tab').forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                document.querySelectorAll('#postsFilterTabs .cinema-pill-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('#postsFilterTabs .cinema-pill-tab').forEach(t => t.setAttribute('aria-selected', 'false'));
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                currentCategory = tab.dataset.category || '';
                loadPosts(1, currentCategory, currentSearch);
            });
        });

        // Debounced live search
        const searchInput = document.getElementById('postSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', e => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    currentSearch = e.target.value.trim();
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
                document.querySelectorAll('#postsFilterTabs .cinema-pill-tab').forEach(t => t.classList.remove('active'));
                const allTab = document.querySelector('#postsFilterTabs .cinema-pill-tab[data-category=""]');
                if (allTab) allTab.classList.add('active');
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

            const resp = await fetch(`/api/v1/posts?${qs}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            const data = await resp.json();
            if (!heroReady) {
                renderHero(data.featured_post);
                heroReady = true;
            }
            renderPostsGrid(data.data || []);
            renderPagination(data.pagination || {});
            if (!sidebarReady) {
                renderTrailers(data.sidebar_trailers || []);
                renderTags(data.popular_tags || []);
                sidebarReady = true;
            }
        } catch (err) {
            console.error('SPA posts fetch error:', err);
            Toast.error('Không thể tải bài viết, vui lòng kiểm tra kết nối.');
            hideSkeletons();
        }
    }

    /* ─── Skeleton helpers ────────────────────────────────────────────────── */
    function showSkeletons() {
        if (!heroReady) {
            show('heroSkeleton');
            hide('heroContent');
        }
        show('postsSkeletonGrid'); hide('postsGrid'); hide('postsEmptyState');
        if (!sidebarReady) {
            show('sidebarTrailersSkeleton');
            hide('sidebarTrailersGrid');
        }
    }

    function hideSkeletons() {
        hide('heroSkeleton');
        hide('postsSkeletonGrid');
        hide('sidebarTrailersSkeleton');
    }

    function show(id) { document.getElementById(id)?.classList.remove('d-none'); }
    function hide(id) { document.getElementById(id)?.classList.add('d-none'); }

    /* ─── 1. Hero Banner ──────────────────────────────────────────────────── */
    function renderHero(featured) {
        hide('heroSkeleton');
        const heroEl = document.getElementById('heroContent');
        if (!heroEl) return;

        if (featured) {
            const slugUrl  = `/posts/${esc(featured.slug)}`;
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
            heroEl.classList.remove('d-none');
        } else {
            heroEl.classList.add('d-none');
        }
    }

    /* ─── 2. Horizontal News Cards ────────────────────────────────────────── */
    function renderPostsGrid(posts) {
        hide('postsSkeletonGrid');
        const grid  = document.getElementById('postsGrid');
        const empty = document.getElementById('postsEmptyState');
        if (!grid) return;

        if (!posts.length) {
            grid.classList.add('d-none');
            empty?.classList.remove('d-none');
            return;
        }

        empty?.classList.add('d-none');

        grid.innerHTML = posts.map(post => {
            const slugUrl  = `/posts/${esc(post.slug)}`;
            const thumb    = esc(post.image_url || post.featured_image_url || '');
            const cat      = esc(post.category || '');
            const badge    = post.category_label ? esc(post.category_label).toUpperCase() : '';
            const author   = esc(post.author_name || 'Cinema Premium');
            const initial  = author.charAt(0).toUpperCase();
            const dateStr  = esc(post.published_date || '');
            const readTime = esc(post.reading_time || 5);
            const title    = esc(post.title || '');
            const excerpt  = esc(post.excerpt || '');
            const slug     = esc(post.slug || '');

            return `
<article class="cinema-news-card horizontal-card mb-4">
    <a href="${slugUrl}" class="news-card-img-wrapper d-block flex-shrink-0">
        <img src="${thumb}" alt="${title}" loading="lazy">
    </a>
    <div class="news-card-body d-flex flex-column justify-content-between flex-grow-1">
        <div class="news-card-content">
            ${badge ? `<span class="card-cat-label label-${cat}">${badge}</span>` : ''}
            <h3 class="news-card-title">
                <a href="${slugUrl}">${title}</a>
            </h3>
            <p class="news-card-excerpt">${excerpt}</p>
        </div>
        <div class="news-card-footer mt-3">
            <div class="author-info">
                <span class="author-avatar-circle">${initial}</span>
                <span class="author-name-text">${author}</span>
            </div>
            <div class="card-meta-right">
                ${dateStr ? `<span class="card-meta-item"><i class="bi bi-calendar3 me-1"></i>${dateStr}</span>` : ''}
                <span class="card-meta-item"><i class="bi bi-clock me-1"></i>${readTime} phút đọc</span>
                <button type="button" class="btn-card-action btn-bookmark-post" data-slug="${slug}" title="Lưu bài viết" aria-label="Lưu bài viết"><i class="bi bi-bookmark"></i></button>
                <button type="button" class="btn-card-action btn-share-post" data-slug="${slug}" data-title="${title}" title="Chia sẻ" aria-label="Chia sẻ"><i class="bi bi-share"></i></button>
            </div>
        </div>
    </div>
</article>`;
        }).join('');

        grid.classList.remove('d-none');
        bindCardActions(grid);
    }

    /* ─── Card action bindings (bookmark/share) ───────────────────────────── */
    function bindCardActions(container) {
        container.querySelectorAll('.btn-bookmark-post').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('active');
                if (btn.classList.contains('active')) {
                    Toast.success('Đã lưu bài viết vào danh sách quan tâm!');
                } else {
                    Toast.info('Đã bỏ lưu bài viết.');
                }
            });
        });

        container.querySelectorAll('.btn-share-post').forEach(btn => {
            btn.addEventListener('click', async () => {
                const title = btn.dataset.title || document.title;
                const url   = `${location.origin}/posts/${btn.dataset.slug}`;
                if (navigator.share) {
                    try { await navigator.share({ title, url }); } catch { copyUrl(url); }
                } else {
                    copyUrl(url);
                }
            });
        });
    }

    /* ─── 3. SPA Pagination ───────────────────────────────────────────────── */
    function renderPagination({ current_page, last_page }) {
        const el = document.getElementById('postsPaginationContainer');
        if (!el) return;

        if (!last_page || last_page <= 1) { el.innerHTML = ''; return; }

        const btn = (page, label, disabled = false, active = false) =>
            `<li class="page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}">
                ${disabled || active
                    ? `<span class="page-link">${label}</span>`
                    : `<a class="page-link spa-page-btn" href="#" data-page="${page}">${label}</a>`}
            </li>`;

        let html = '<ul class="pagination mb-0">';
        html += btn(current_page - 1, '&laquo;', current_page === 1);
        for (let p = 1; p <= last_page; p++) html += btn(p, p, false, p === current_page);
        html += btn(current_page + 1, '&raquo;', current_page === last_page);
        html += '</ul>';
        el.innerHTML = html;

        el.querySelectorAll('.spa-page-btn').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const p = +a.dataset.page;
                if (p && p !== currentPage) {
                    loadPosts(p, currentCategory, currentSearch);
                    window.scrollTo({ top: 100, behavior: 'smooth' });
                }
            });
        });
    }

    /* ─── 4. Sidebar: Large Trailer Cards (chuẩn mockup) ─────────────────── */
    function renderTrailers(trailers) {
        hide('sidebarTrailersSkeleton');
        const grid = document.getElementById('sidebarTrailersGrid');
        if (!grid) return;

        if (!trailers.length) {
            grid.innerHTML = '<p class="text-secondary small mb-0">Chưa có trailer nào.</p>';
        } else {
            grid.innerHTML = trailers.map(m => {
                const slug   = esc(m.slug || m.id || '');
                const title  = esc(m.title || '');
                const poster = esc(m.poster_url || '');
                const age    = esc(m.age_rating || '');

                return `
<a href="/movies/${slug}" class="sidebar-trailer-card d-block text-decoration-none mb-3">
    <div class="trailer-thumbnail position-relative">
        <img src="${poster}" alt="" loading="lazy" class="d-none" data-trailer-image>
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

        grid.querySelectorAll('[data-trailer-image]').forEach((image) => {
            image.addEventListener('load', () => image.classList.remove('d-none'), { once: true });
            image.addEventListener('error', () => image.classList.add('d-none'), { once: true });

            if (image.complete && image.naturalWidth > 0) {
                image.classList.remove('d-none');
            }
        });
        grid.classList.remove('d-none');
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
                    Toast.success('Đăng ký bản tin trải nghiệm thành công! Cảm ơn bạn đã đồng hành cùng Poly Cinema.');
                    form.reset();
                }
            });
        });
    }

    /* ─── Article detail actions ──────────────────────────────────────────── */
    function setupArticleActions() {
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
                    btnBookmark.style.color = '#e50914';
                    Toast.success('Đã lưu bài viết vào danh sách quan tâm!');
                } else {
                    btnBookmark.style.color = '';
                    Toast.info('Đã bỏ lưu bài viết.');
                }
            });
        }
    }

    function copyUrl(text) {
        navigator.clipboard.writeText(text)
            .then(() => Toast.success('Đã sao chép liên kết!'))
            .catch(() => Toast.error('Không thể sao chép liên kết.'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
