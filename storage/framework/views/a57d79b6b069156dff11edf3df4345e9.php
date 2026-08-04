<?php $__env->startSection('title', 'Cinema Premium - Đặt vé xem phim'); ?>
<?php $__env->startSection('meta_description', 'Cinema premium - đặt vé xem phim trực tuyến với trải nghiệm tối giản, hiện đại và nhanh chóng.'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/users/skeleton.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/users/pages/home.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    
    <section class="hero-section" aria-labelledby="heroTitle">
        
        <div id="heroSkeleton" class="skeleton-hero">
            <div class="skeleton-hero-content">
                <div class="skeleton-hero-copy">
                    <div class="skeleton skeleton-badge"></div>
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text-short"></div>
                    <div class="d-flex gap-3 mt-4">
                        <div class="skeleton skeleton-button"></div>
                        <div class="skeleton skeleton-button"></div>
                    </div>
                </div>
            </div>
        </div>

        
        <div id="heroBackdrop" class="hero-backdrop"></div>

        
        <div class="hero-gradient-overlay"></div>

        <div class="hero-carousel-controls d-none" aria-label="Điều khiển banner">
            <button id="heroPrevious" class="hero-carousel-arrow" type="button" aria-label="Banner trước">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button id="heroNext" class="hero-carousel-arrow" type="button" aria-label="Banner tiếp theo">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        
        <div id="heroContent" class="hero-content d-none">
            <div class="container">
                <div class="hero-inner"></div>
            </div>
        </div>
        <div id="heroDots" class="hero-carousel-dots" role="tablist" aria-label="Chọn banner"></div>
    </section>

    
    <section class="quick-booking-section">
        <div class="container">
            <div class="quick-booking-widget">
                
                <div id="bookingSkeleton" class="skeleton skeleton-booking"></div>

                
                <div id="bookingWidget" class="booking-form d-none">
                    <div class="booking-controls">
                        <div class="booking-control">
                            <label class="booking-label">SELECT MOVIE</label>
                            <input type="hidden" name="movie" id="movieInput">
                            <div class="custom-select" data-select="movie">
                                <div class="select-trigger">
                                    <span class="select-value placeholder">Choose movie</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="select-dropdown">
                                    <div class="select-search">
                                        <input type="search" placeholder="Tìm rạp..." autocomplete="off" aria-label="Tìm rạp trong danh sách">
                                    </div>
                                    <div class="select-options"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-control">
                            <label class="booking-label">DATE</label>
                            <input type="hidden" name="date" id="dateInput">
                            <div class="custom-select" data-select="date">
                                <div class="select-trigger">
                                    <span class="select-value placeholder">Choose date</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="select-dropdown">
                                    <div class="select-options"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-control">
                            <label class="booking-label">CINEMA</label>
                            <input type="hidden" name="cinema" id="cinemaInput">
                            <div class="custom-select" data-select="cinema">
                                <div class="select-trigger">
                                    <span class="select-value placeholder">Choose cinema</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="select-dropdown">
                                    <div class="select-options"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-control-btn">
                            <button type="button" class="btn-find-seats" id="btnFindSeats">
                                <i class="bi bi-search"></i>
                                <span>Find Seats</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <section id="movies" class="movies-section" aria-labelledby="moviesTitle">
        <div class="container">
            <div class="section-header">
                <h2 id="moviesTitle" class="section-title">Now Showing</h2>
                <a href="/movies" class="view-all-link">
                    View All <i class="bi bi-plus"></i>
                </a>
            </div>

            
            <div id="moviesSkeleton" class="skeleton-grid">
                <?php for($i = 0; $i < 4; $i++): ?>
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            
            <div id="moviesGrid" class="movies-grid d-none"></div>
        </div>
    </section>

    
    <?php if(isset($latestPosts) && $latestPosts->isNotEmpty()): ?>
    <section class="home-posts-section content-posts-section py-5" aria-labelledby="postsTitle">
        <div class="container">
            <?php if (isset($component)) { $__componentOriginalf659d20799e6ccbc3c541792a491c919 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf659d20799e6ccbc3c541792a491c919 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user.content-section-header','data' => ['eyebrow' => 'CINEMA JOURNAL','title' => 'Tin Tức & Ưu Đãi Mới','titleId' => 'postsTitle','href' => route('posts.index')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user.content-section-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'CINEMA JOURNAL','title' => 'Tin Tức & Ưu Đãi Mới','title-id' => 'postsTitle','href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('posts.index'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf659d20799e6ccbc3c541792a491c919)): ?>
<?php $attributes = $__attributesOriginalf659d20799e6ccbc3c541792a491c919; ?>
<?php unset($__attributesOriginalf659d20799e6ccbc3c541792a491c919); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf659d20799e6ccbc3c541792a491c919)): ?>
<?php $component = $__componentOriginalf659d20799e6ccbc3c541792a491c919; ?>
<?php unset($__componentOriginalf659d20799e6ccbc3c541792a491c919); ?>
<?php endif; ?>

            <div class="row g-4">
                <?php $__currentLoopData = $latestPosts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-12 col-md-4">
                    <?php if (isset($component)) { $__componentOriginalcc52f7c12a5c28aa902c5df869acebad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcc52f7c12a5c28aa902c5df869acebad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user.post-card','data' => ['post' => $post]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user.post-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['post' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($post)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcc52f7c12a5c28aa902c5df869acebad)): ?>
<?php $attributes = $__attributesOriginalcc52f7c12a5c28aa902c5df869acebad; ?>
<?php unset($__attributesOriginalcc52f7c12a5c28aa902c5df869acebad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcc52f7c12a5c28aa902c5df869acebad)): ?>
<?php $component = $__componentOriginalcc52f7c12a5c28aa902c5df869acebad; ?>
<?php unset($__componentOriginalcc52f7c12a5c28aa902c5df869acebad); ?>
<?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/users/pages/home.js')); ?>?v=<?php echo e(config('app.asset_version')); ?>" type="module"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cinema\resources\views/users/home.blade.php ENDPATH**/ ?>