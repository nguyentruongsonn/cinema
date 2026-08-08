<?php $__env->startSection('title', 'Bảng giá vé - Cinema'); ?>
<?php $__env->startSection('meta_description', 'Xem bảng giá vé chi tiết cho từng rạp chiếu phim và định dạng phim.'); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/users/skeleton.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/users/pages/prices.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <!-- Header Section -->
    <section class="pricing-header">
        <div class="container">
            <div class="pricing-badge">Bảng giá vé</div>
            <h1 class="pricing-title">Trải nghiệm điện ảnh đỉnh cao</h1>
            <p class="pricing-desc">
                Giá vé có thể thay đổi tùy theo từng rạp, định dạng phim và thời điểm xem phim. 
                Vui lòng chọn rạp bên dưới để xem bảng giá chi tiết.
            </p>
        </div>
    </section>

    <!-- Tabs Section -->
    <section class="pricing-tabs-container">
        <div class="container">
            <!-- Tabs Skeleton -->
            <div class="pricing-tabs" id="pricingTabsSkeleton">
                <?php for($i = 0; $i < 4; $i++): ?>
                    <div class="skeleton skeleton-button"></div>
                <?php endfor; ?>
            </div>
            
            <!-- Tabs Content -->
            <div class="pricing-tabs d-none" id="pricing-tabs-container" role="tablist" aria-label="Chọn rạp" data-tabs>
                <!-- Dynamically loaded tabs -->
            </div>
        </div>
    </section>

    <!-- Pricing Table Section -->
    <section class="pricing-table-section">
        <div class="container">
            <!-- Table Skeleton -->
            <div id="pricingTableSkeleton">
                <div class="theater-pricing-wrapper">
                    <div class="pricing-card">
                        <div class="skeleton skeleton-title"></div>
                        
                        <table class="galaxy-table mb-2">
                            <thead>
                                <tr>
                                    <th class="col-room"><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                    <th><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                    <th><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                    <th><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($j = 0; $j < 3; $j++): ?>
                                <tr class="row-light">
                                    <td class="col-title">
                                        <div class="skeleton skeleton-text"></div>
                                        <div class="skeleton skeleton-text-short"></div>
                                    </td>
                                    <td class="price-col"><div class="skeleton skeleton-text mx-auto"></div></td>
                                    <td class="price-col"><div class="skeleton skeleton-text mx-auto"></div></td>
                                    <td class="price-col"><div class="skeleton skeleton-text mx-auto"></div></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                        
                        <div class="skeleton skeleton-text skeleton-text-lg"></div>

                        <div class="table-notes mt-4">
                            <div class="skeleton skeleton-title"></div>
                            <div class="skeleton skeleton-text"></div>
                            <div class="skeleton skeleton-text skeleton-text-short"></div>
                            <div class="skeleton skeleton-text skeleton-text-xs"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Content -->
            <div id="pricing-tables-container" class="d-none">
                <!-- Dynamically loaded pricing tables -->
            </div>
        </div>
    </section>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script type="module" src="<?php echo e(asset('js/users/pages/prices.js')); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cinema\resources\views/users/prices/index.blade.php ENDPATH**/ ?>