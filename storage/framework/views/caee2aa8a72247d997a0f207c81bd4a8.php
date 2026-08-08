<?php $__env->startSection('title', 'Sơ đồ mẫu ghế'); ?>
<?php $__env->startSection('header_title', 'Sơ đồ mẫu ghế'); ?>
<?php $__env->startSection('header_subtitle', 'Đang tải...'); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="filter-bar mb-4">
        <div class="filter-bar-inner align-items-center w-100">
            <h5 class="mb-0 text-white fw-bold">
                <i class="bi bi-grid-3x3 me-2 admin-accent-icon"></i>
                <span id="templateNameTitle">Đang tải...</span>
            </h5>
            <span class="ms-2 small text-white-50" id="templateDescSpan"></span>

            <a href="<?php echo e(route('admin.seat-layout-templates.index')); ?>"
                class="btn btn-sm ms-auto d-inline-flex align-items-center text-white btn-header-back">
                <i class="bi bi-arrow-return-left me-2"></i> Quay lại
            </a>
        </div>
    </div>

    
    <div class="row g-4 align-items-start">

        
        <div class="col-lg-9 col-md-8">
            <div class="chart-card p-4 overflow-x-auto">
                
                <div class="screen-display">
                    <div class="screen-label">MÀN HÌNH CHIẾU</div>
                </div>

                
                <div id="seatMapContainer" class="seat-map-container">
                    
                    <div class="seat-map-skeleton">
                        <div class="skeleton-rows">
                            <div class="skeleton-row" v-for="i in 10">
                                <div class="skeleton-seat" v-for="j in 15"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="seatGrid" class="seat-grid mx-auto d-none"></div>
                    
                    <div id="seatGridColLabels" class="seat-grid-col-labels mx-auto mt-2 d-none"></div>
                </div>

                
                <div class="seat-legend">
                    <div class="legend-item">
                        <span class="seat-demo seat-available"></span>
                        <span>Ghế trống</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-selected"></span>
                        <span>Đang chọn</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-vip"></span>
                        <span>Ghế VIP</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-disabled-legend"></span>
                        <span>Ghế hỏng / Lối đi</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-couple"></span>
                        <span>Ghế đôi</span>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="col-lg-3 col-md-4">

            
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-info-circle me-1 admin-accent-icon"></i>Thông tin mẫu
                </h6>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Ma trận</span>
                        <span class="small text-white fw-semibold" id="infoMatrix">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Ghế thường</span>
                        <span class="badge badge-soft-primary" id="infoRegular">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Ghế VIP</span>
                        <span class="badge badge-soft-warning" id="infoVip">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Ghế Đôi</span>
                        <span class="badge badge-soft-danger" id="infoCouple">--</span>
                    </div>
                    <hr class="opacity-25 my-1">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="small text-secondary">Sức chứa</span>
                        <span class="small text-white">
                            <strong class="text-white" id="capacityCounter">0</strong>
                            / <span id="totalSeatsCounter">0</span> chỗ
                        </span>
                    </div>

                    
                    <button type="button" class="btn-primary-custom w-100 border-0 py-2 mt-2 btn-update-layout"
                        id="btnUpdateSeats">
                        <i class="bi bi-save me-2"></i>Lưu cấu hình mẫu
                    </button>
                </div>
            </div>

            
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-toggles me-1 admin-accent-icon"></i>Hoạt động
                </h6>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="small text-white">Kích hoạt mẫu</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input toggle-switch-lg" type="checkbox" role="switch"
                            id="templateActiveToggle">
                    </div>
                </div>
            </div>

        </div>
    </div>

    
    <script nonce="<?php echo e(request()->attributes->get('csp_nonce')); ?>">
        window.SEAT_LAYOUT_TEMPLATE_CONFIG = {
            templateId: <?php echo json_encode($templateId, 15, 512) ?>
        };
    </script>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/admin/pages/stats.css', 'resources/css/admin/pages/seat-layout.css']); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/admin/pages/seat-layout-template-editor.js')); ?>?v=<?php echo e(config('app.asset_version')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cinema\resources\views/admin/seat-layout-templates/seats.blade.php ENDPATH**/ ?>