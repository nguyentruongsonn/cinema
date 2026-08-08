<?php $__env->startSection('title', 'Hệ thống Rạp - Cinema'); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/users/pages/theaters.css')); ?>?v=<?php echo e(filemtime(public_path('css/users/pages/theaters.css'))); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <!-- Header Section -->
    <section class="theaters-header">
        <div class="container cinema-shell" id="theatersDataRegion" data-state="loading" aria-busy="true">
            <h1 class="page-title text-center text-md-start">Hệ thống Rạp Chiếu</h1>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="theaters-filters-section sticky-top">
        <div class="container cinema-shell">
            <div class="filters-content">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label class="filter-label" for="branchFilter">Chi nhánh</label>
                            <select id="branchFilter" class="filter-select">
                                <option value="">Tất cả chi nhánh</option>
                                <!-- Rendered by JS -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6 offset-md-3">
                        <div class="filter-group">
                            <label class="filter-label" for="searchInput">Tìm kiếm</label>
                            <div class="search-box">
                                <input type="text" id="searchInput" class="search-input" placeholder="Tìm theo tên rạp...">
                                <button type="button" id="searchBtn" class="search-btn" aria-label="Tìm kiếm">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Theaters Listing -->
    <section class="theaters-listing-section">
        <div class="container cinema-shell">
            
            <!-- Skeleton Loader -->
            <div id="theatersSkeleton" class="row g-4" data-state-panel="loading">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="theater-card-skeleton">
                            <div class="skeleton-img"></div>
                            <div class="skeleton-content">
                                <div class="skeleton-title"></div>
                                <div class="skeleton-text"></div>
                                <div class="skeleton-text short"></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Theaters Grid -->
            <div id="theatersGrid" class="row g-4 d-none" data-state-panel="ready">
                <!-- Rendered by JS -->
            </div>

            <!-- Empty State -->
            <?php if (isset($component)) { $__componentOriginal4beae15b2881e01ccce4e3768201a3c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4beae15b2881e01ccce4e3768201a3c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user.data-state','data' => ['id' => 'emptyState','type' => 'empty','icon' => 'bi-building-slash','title' => 'Không tìm thấy rạp chiếu nào','message' => 'Vui lòng thử điều chỉnh bộ lọc hoặc từ khóa tìm kiếm của bạn.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user.data-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'emptyState','type' => 'empty','icon' => 'bi-building-slash','title' => 'Không tìm thấy rạp chiếu nào','message' => 'Vui lòng thử điều chỉnh bộ lọc hoặc từ khóa tìm kiếm của bạn.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4beae15b2881e01ccce4e3768201a3c3)): ?>
<?php $attributes = $__attributesOriginal4beae15b2881e01ccce4e3768201a3c3; ?>
<?php unset($__attributesOriginal4beae15b2881e01ccce4e3768201a3c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4beae15b2881e01ccce4e3768201a3c3)): ?>
<?php $component = $__componentOriginal4beae15b2881e01ccce4e3768201a3c3; ?>
<?php unset($__componentOriginal4beae15b2881e01ccce4e3768201a3c3); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginal4beae15b2881e01ccce4e3768201a3c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4beae15b2881e01ccce4e3768201a3c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user.data-state','data' => ['id' => 'theatersErrorState','type' => 'error','icon' => 'bi-wifi-off','title' => 'Không thể tải danh sách rạp','message' => 'Vui lòng kiểm tra kết nối và thử lại.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user.data-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'theatersErrorState','type' => 'error','icon' => 'bi-wifi-off','title' => 'Không thể tải danh sách rạp','message' => 'Vui lòng kiểm tra kết nối và thử lại.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4beae15b2881e01ccce4e3768201a3c3)): ?>
<?php $attributes = $__attributesOriginal4beae15b2881e01ccce4e3768201a3c3; ?>
<?php unset($__attributesOriginal4beae15b2881e01ccce4e3768201a3c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4beae15b2881e01ccce4e3768201a3c3)): ?>
<?php $component = $__componentOriginal4beae15b2881e01ccce4e3768201a3c3; ?>
<?php unset($__componentOriginal4beae15b2881e01ccce4e3768201a3c3); ?>
<?php endif; ?>

            <!-- Pagination -->
            <div id="paginationContainer" class="cinema-pagination d-none">
                <!-- Rendered by JS -->
            </div>
            
        </div>
    </section>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/users/pages/theaters.js')); ?>?v=<?php echo e(config('app.asset_version', time())); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cinema\resources\views/users/theaters/index.blade.php ENDPATH**/ ?>