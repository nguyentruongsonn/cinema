

<?php $__env->startSection('title', 'Movies - Cinema Premium'); ?>
<?php $__env->startSection('meta_description', 'Browse all movies currently showing and coming soon at Cinema Premium.'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/users/skeleton.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/users/pages/movies.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    
    <section class="movies-header">
        <div class="container">
            <h1 class="page-title">Movies</h1>
        </div>
    </section>

    
    <div class="movies-content-wrapper">
        <div class="container">
            
            
            <section class="movie-section special-screenings-section mb-5 d-none" id="specialSection">
                <h2 class="section-title"><i class="bi bi-star-fill text-danger me-2"></i> Suất chiếu đặc biệt</h2>
                <div class="horizontal-scroll-container">
                    <div id="specialMoviesGrid" class="special-movies-grid">
                        
                    </div>
                </div>
            </section>

            
            <section class="movie-section now-showing-section mb-5">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h2 class="section-title mb-0">Phim đang chiếu</h2>
                    <a href="#" class="view-all-link">View All <i class="bi bi-arrow-right"></i></a>
                </div>
                
                
                <div id="nowShowingSkeleton" class="horizontal-scroll-container">
                    <div class="movies-grid-horizontal">
                        <?php for($i = 0; $i < 5; $i++): ?>
                        <div class="skeleton-movie-card">
                            <div class="skeleton skeleton-poster"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                
                <div class="horizontal-scroll-container d-none" id="nowShowingContainer">
                    <div id="nowShowingGrid" class="movies-grid-horizontal">
                        
                    </div>
                </div>
            </section>

            
            <section class="movie-section coming-soon-section mb-5">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h2 class="section-title mb-0">Phim sắp chiếu</h2>
                    <div class="scroll-controls">
                        <button class="btn-scroll" id="scrollPrev" type="button" aria-label="Phim sắp chiếu trước"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <button class="btn-scroll" id="scrollNext" type="button" aria-label="Phim sắp chiếu tiếp theo"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </div>

                
                <div id="comingSoonSkeleton" class="horizontal-scroll-container">
                    <div class="movies-grid-horizontal coming-soon-grid">
                        <?php for($i = 0; $i < 3; $i++): ?>
                        <div class="skeleton-movie-card-wide">
                            <div class="skeleton skeleton-poster-wide"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                
                <div class="horizontal-scroll-container d-none" id="comingSoonContainer">
                    <div id="comingSoonGrid" class="movies-grid-horizontal coming-soon-grid">
                        
                    </div>
                </div>
            </section>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script type="module" src="<?php echo e(asset('js/users/pages/movies.js')); ?>?v=<?php echo e(config('app.asset_version')); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\cinema\resources\views/users/movies/index.blade.php ENDPATH**/ ?>