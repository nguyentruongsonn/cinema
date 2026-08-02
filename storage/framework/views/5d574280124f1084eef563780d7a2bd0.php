<?php $__env->startSection('title', 'Tin Tức & Sự Kiện Điện Ảnh - Poly Cinema'); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/users/pages/posts.css')); ?>?v=<?php echo e(config('app.asset_version', time())); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="posts-page" id="postsSpaContainer">

    
    <div id="heroSkeleton" class="hero-skeleton-container" aria-hidden="true">
        <div class="skeleton-hero-banner"></div>
    </div>

    <section id="heroContent" class="posts-hero-section d-none" style="background-image: url('');">
        <div class="posts-hero-overlay"></div>
        <div class="container posts-hero-content">
            <span class="hero-badge-exclusive" id="heroBadge">ĐỘC QUYỀN</span>
            <h1 class="posts-hero-title">
                <a href="#" id="heroTitleLink" class="text-white text-decoration-none"></a>
            </h1>
            <p class="posts-hero-excerpt" id="heroExcerpt"></p>
            <div class="d-flex align-items-center flex-wrap gap-3 mt-3">
                <a href="#" id="heroReadBtn" class="hero-read-btn">
                    <span>Đọc Ngay</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
                <span class="hero-read-time">
                    <i class="bi bi-clock"></i>
                    <span id="heroReadTime">5 phút đọc</span>
                </span>
            </div>
        </div>
    </section>

    
    <section class="posts-main-section py-5">
        <div class="container">
            <div class="row g-5">

                
                <div class="col-lg-8">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                        <h2 class="section-title mb-0">Tin Tức Mới Nhất</h2>
                        <div class="posts-search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="search" id="postSearchInput" class="cinema-search-input" placeholder="Tìm bài viết, diễn viên, đạo diễn..." aria-label="Tìm bài viết">
                        </div>
                    </div>

                    
                    <div class="posts-filter-tabs d-flex flex-wrap gap-2 mb-4" id="postsFilterTabs" role="tablist">
                        <button type="button" class="cinema-pill-tab active" data-category="">Tất cả</button>
                        <button type="button" class="cinema-pill-tab" data-category="news">Review Phim</button>
                        <button type="button" class="cinema-pill-tab" data-category="blog">Hậu Trường</button>
                        <button type="button" class="cinema-pill-tab" data-category="promotion">Khuyến Mãi</button>
                        <button type="button" class="cinema-pill-tab" data-category="event">Sự Kiện</button>
                        <button type="button" class="cinema-pill-tab" data-category="announcement">Thông Báo</button>
                    </div>

                    
                    <div id="postsSkeletonGrid" class="posts-skeleton-grid" aria-hidden="true">
                        <?php for($i = 0; $i < 4; $i++): ?>
                            <div class="skeleton-news-card mb-4">
                                <div class="skeleton skeleton-img"></div>
                                <div class="skeleton-info">
                                    <div class="skeleton skeleton-badge-sm mb-2"></div>
                                    <div class="skeleton skeleton-title mb-2"></div>
                                    <div class="skeleton skeleton-text mb-2"></div>
                                    <div class="skeleton skeleton-meta"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    
                    <div id="postsGrid" class="posts-list-container d-none">
                        
                    </div>

                    
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

                    
                    <div id="postsPaginationContainer" class="mt-5 d-flex justify-content-center"></div>
                </div>

                
                <aside class="col-lg-4">
                    <div class="sidebar-sticky-wrap">

                        
                        <div class="sidebar-widget-card mb-4">
                            <h3 class="sidebar-widget-title">
                                <i class="bi bi-film me-2 text-danger"></i>Trailer Thịnh Hành
                            </h3>
                            <div id="sidebarTrailersSkeleton" class="sidebar-trailers-skeleton">
                                <?php for($j = 0; $j < 2; $j++): ?>
                                    <div class="skeleton skeleton-trailer-item mb-3"></div>
                                <?php endfor; ?>
                            </div>
                            <div id="sidebarTrailersGrid" class="sidebar-trailer-list d-none">
                                
                            </div>
                        </div>

                        
                        <div class="sidebar-widget-card mb-4">
                            <h3 class="sidebar-widget-title">
                                <i class="bi bi-tags me-2 text-danger"></i>Chủ Đề Phổ Biến
                            </h3>
                            <div class="sidebar-tags-cloud d-flex flex-wrap gap-2" id="sidebarTagsContainer">
                                
                            </div>
                        </div>

                        
                        <div class="sidebar-widget-card sidebar-newsletter-card">
                            <div class="newsletter-icon-badge mb-3">
                                <i class="bi bi-envelope-paper-heart"></i>
                            </div>
                            <h3 class="sidebar-widget-title mb-2">Bản Tin Trải Nghiệm</h3>
                            <p class="sidebar-widget-subtitle mb-4">
                                Đăng ký nhận lịch chiếu sớm, vé mời suất công chiếu và bài cảm nhận phim độc quyền mỗi thứ Sáu.
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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script type="module" src="<?php echo e(asset('js/users/pages/posts.js')); ?>?v=<?php echo e(config('app.asset_version', time())); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cinema\resources\views/users/posts/index.blade.php ENDPATH**/ ?>