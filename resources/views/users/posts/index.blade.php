@extends('layouts.app')

@section('title', 'Tin Tức & Sự Kiện Điện Ảnh - Poly Cinema')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}?v={{ config('app.asset_version') }}">
    <link rel="stylesheet" href="{{ asset('css/users/pages/posts-index.css') }}?v={{ config('app.asset_version') }}">
@endpush

@section('content')
<div class="posts-page" id="postsSpaContainer">

    {{-- Shared hero banner: unchanged by tabs, search or pagination --}}
    <section class="posts-hero-shell" aria-label="Bài viết nổi bật">
        <div id="heroSkeleton" class="hero-skeleton-container" aria-hidden="true">
            <div class="skeleton-hero-banner"></div>
        </div>

        <div id="heroContent" class="posts-hero-section d-none">
            <div class="posts-hero-overlay"></div>
            <div class="container posts-hero-container">
                <div class="posts-hero-content">
                    <span class="hero-badge-exclusive" id="heroBadge">NỔI BẬT</span>
                    <h1 class="posts-hero-title">
                        <a href="#" id="heroTitleLink"></a>
                    </h1>
                    <p class="posts-hero-excerpt" id="heroExcerpt"></p>
                    <div class="posts-hero-actions">
                        <a href="#" id="heroReadBtn" class="hero-read-btn">
                            <i class="bi bi-journal-text" aria-hidden="true"></i>
                            <span>Đọc bài viết</span>
                        </a>
                        <span class="hero-read-time">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <span id="heroReadTime">5 phút đọc</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Main Content & Sidebar Layout --}}
    <section class="posts-main-section" id="postsMainSection" aria-labelledby="postsSectionTitle">
        <div class="container">
            <div class="row g-5">

                {{-- Left Column: Latest News & Filter --}}
                <div class="col-lg-8">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                        <h2 class="section-title mb-0" id="postsSectionTitle">Tin tức mới nhất</h2>
                        <div class="posts-search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="search" id="postSearchInput" class="cinema-search-input" placeholder="Tìm bài viết, diễn viên, đạo diễn..." aria-label="Tìm bài viết">
                        </div>
                    </div>

                    {{-- Category Filter Tabs --}}
                    <div class="posts-filter-tabs mb-4" id="postsFilterTabs" role="tablist" aria-label="Lọc bài viết theo chuyên mục" data-tabs>
                        <button type="button" class="cinema-pill-tab active" role="tab" aria-selected="true" data-value="">Tất cả</button>
                        <button type="button" class="cinema-pill-tab" role="tab" aria-selected="false" data-value="news">Tin phim</button>
                        <button type="button" class="cinema-pill-tab" role="tab" aria-selected="false" data-value="blog">Review & Blog</button>
                        <button type="button" class="cinema-pill-tab" role="tab" aria-selected="false" data-value="promotion">Khuyến mãi</button>
                        <button type="button" class="cinema-pill-tab" role="tab" aria-selected="false" data-value="event">Sự kiện</button>
                        <button type="button" class="cinema-pill-tab" role="tab" aria-selected="false" data-value="announcement">Thông báo</button>
                    </div>

                    {{-- Skeleton Loading Grid --}}
                    <div id="postsSkeletonGrid" class="posts-skeleton-grid" aria-hidden="true">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="skeleton-news-card mb-4">
                                <div class="skeleton skeleton-img"></div>
                                <div class="skeleton-info">
                                    <div class="skeleton skeleton-badge-sm mb-2"></div>
                                    <div class="skeleton skeleton-title mb-2"></div>
                                    <div class="skeleton skeleton-text mb-2"></div>
                                    <div class="skeleton skeleton-meta"></div>
                                </div>
                            </div>
                        @endfor
                    </div>

                    {{-- Dynamic SPA Posts Grid --}}
                    <div id="postsGrid" class="posts-list-container d-none">
                        {{-- Populated dynamically via public/js/users/pages/posts.js --}}
                    </div>

                    <div id="postsErrorState" class="posts-error-state d-none text-center py-5" role="alert"></div>

                    {{-- Empty State --}}
                    <div id="postsEmptyState" class="posts-empty-state d-none text-center py-5">
                        <div class="empty-icon-wrap mb-3">
                            <i class="bi bi-journal-x display-3 text-secondary"></i>
                        </div>
                        <h4 class="text-white mb-2">Không tìm thấy bài viết</h4>
                        <p class="text-secondary mb-4">Thử thay đổi từ khóa hoặc bộ lọc chuyên mục khác</p>
                        <button type="button" class="btn btn-outline-danger" id="btnResetFilter">
                            Xem tất cả bài viết
                        </button>
                    </div>

                    {{-- SPA Pagination Container --}}
                    <div id="postsPaginationContainer" class="cinema-pagination mt-5"></div>
                </div>

                {{-- Right Column: Cinematic Sidebar Widgets --}}
                <aside class="col-lg-4">
                    <div class="sidebar-sticky-wrap">

                        {{-- Widget 1: Trailer Thịnh Hành --}}
                        <div class="sidebar-widget-section mb-5">
                            <h3 class="sidebar-widget-title">
                                <i class="bi bi-fire me-2 text-danger"></i>Trailer Thịnh Hành
                            </h3>
                            <div id="sidebarTrailersSkeleton" class="sidebar-trailers-skeleton">
                                @for ($j = 0; $j < 2; $j++)
                                    <div class="skeleton skeleton-trailer-item mb-3"></div>
                                @endfor
                            </div>
                            <div id="sidebarTrailersGrid" class="sidebar-trailer-list d-none">
                                {{-- Populated dynamically via SPA --}}
                            </div>
                        </div>

                        {{-- Widget 2: Chủ Đề Phổ Biến --}}
                        <div class="sidebar-widget-section mb-5">
                            <h3 class="sidebar-widget-title mb-3">Chủ Đề Phổ Biến</h3>
                            <div class="sidebar-tags-cloud d-flex flex-wrap gap-2" id="sidebarTagsContainer">
                                {{-- Populated dynamically via SPA --}}
                            </div>
                        </div>

                        {{-- Widget 3: Newsletter Box --}}
                        <div class="sidebar-widget-card sidebar-newsletter-card">
                            <h3 class="sidebar-widget-title mb-2">Bản Tin Trải Nghiệm</h3>
                            <p class="sidebar-widget-subtitle mb-4">
                                Nhận những tin tức điện ảnh độc quyền và ưu đãi vé xem phim mới nhất.
                            </p>
                            <form id="newsletterForm" class="cinema-newsletter-form" novalidate>
                                <div class="newsletter-input-group mb-3">
                                    <input type="email" class="cinema-newsletter-input" placeholder="Email của bạn" required aria-label="Email đăng ký nhận bản tin">
                                </div>
                                <button type="submit" class="btn-newsletter-submit">
                                    <span>Đăng Ký Nhận Tin</span>
                                </button>
                            </form>
                        </div>

                    </div>
                </aside>

            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/users/pages/posts.js') }}?v={{ config('app.asset_version', time()) }}"></script>
@endpush
