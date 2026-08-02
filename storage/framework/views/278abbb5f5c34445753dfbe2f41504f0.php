<footer class="cinema-footer">
    <div class="container cinema-shell">
        <div class="row align-items-start gy-4">
            <div class="col-lg-4">
                <a class="cinema-footer-brand text-decoration-none" href="<?php echo e(route('home')); ?>">CINEMA</a>
                <p class="cinema-copyright mb-0 mt-4">
                    © <?php echo e(date('Y')); ?> CINEMA PREMIUM. ALL RIGHTS RESERVED.
                </p>
            </div>

            <div class="col-6 col-lg-3">
                <h2 class="cinema-footer-title">Khám Phá</h2>
                <ul class="list-unstyled cinema-footer-links mb-0">
                    <li><a href="<?php echo e(route('movies.index')); ?>">Phim đang chiếu</a></li>
                    <li><a href="<?php echo e(route('theaters.index')); ?>">Hệ thống rạp</a></li>
                    <li><a href="<?php echo e(route('prices.index')); ?>">Bảng giá vé</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <h2 class="cinema-footer-title">Tin Tức & Ưu Đãi</h2>
                <ul class="list-unstyled cinema-footer-links mb-0">
                    <li><a href="<?php echo e(route('posts.index', ['category' => 'promotion'])); ?>">Ưu đãi & Khuyến mãi</a></li>
                    <li><a href="<?php echo e(route('posts.index', ['category' => 'event'])); ?>">Sự kiện điện ảnh</a></li>
                    <li><a href="<?php echo e(route('posts.index', ['category' => 'news'])); ?>">Tin phim mới nhất</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h2 class="cinema-footer-title">Hỗ Trợ</h2>
                <ul class="list-unstyled cinema-footer-links mb-0">
                    <li><a href="#">Điều khoản</a></li>
                    <li><a href="#">Bảo mật</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/partials/footer.blade.php ENDPATH**/ ?>