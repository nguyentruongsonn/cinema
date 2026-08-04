<header class="cinema-header">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container cinema-shell">
            <a class="navbar-brand cinema-brand" href="<?php echo e(route('home')); ?>" aria-label="Cinema home">
                CINEMA
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Open menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="mainNavbar" class="collapse navbar-collapse">
                <ul class="navbar-nav mx-auto cinema-nav gap-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->is('movies*') ? 'active' : ''); ?>" href="<?php echo e(route('movies.index')); ?>">Phim</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->is('theaters*') ? 'active' : ''); ?>" href="<?php echo e(route('theaters.index')); ?>">Rạp</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->is('prices*') ? 'active' : ''); ?>" href="<?php echo e(route('prices.index')); ?>">Giá vé</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->is('posts*') ? 'active' : ''); ?>" href="<?php echo e(route('posts.index')); ?>">
                            Tin tức & Ưu đãi
                        </a>
                    </li>
                </ul>

                <div class="cinema-actions d-flex align-items-center gap-3">
                    <a href="#search" class="cinema-icon-link" aria-label="Search">
                        <i class="bi bi-search"></i>
                    </a>
                    <a href="#notifications" class="cinema-icon-link" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                    </a>

                    
                    <?php if(!Auth::guard('web')->check()): ?>
                        
                        <a href="#" class="btn btn-danger cinema-login-btn" data-auth-action="login">
                            Đăng nhập
                        </a>
                    <?php else: ?>
                        <?php ($currentUser = Auth::guard('web')->user()); ?>
                        
                        <div class="dropdown" id="userDropdown">
                            <button class="btn btn-link text-white text-decoration-none dropdown-toggle d-flex align-items-center gap-2"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5"></i>
                                <span class="user-name d-none d-lg-inline"><?php echo e(Auth::guard('web')->user()->name); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <a class="dropdown-item" href="<?php echo e(route('profile.index')); ?>">
                                        <i class="bi bi-person me-2"></i>Tài khoản
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo e(route('profile.index')); ?>#tickets">
                                        <i class="bi bi-ticket-perforated me-2"></i>Vé của tôi
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#orders">
                                        <i class="bi bi-bag me-2"></i>Đơn hàng
                                    </a>
                                </li>
                                <?php if($currentUser?->canAccessAdminPanel()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('admin.entry')); ?>">
                                            <i class="bi bi-speedometer2 me-2"></i>Giao diện quản lý
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" data-auth-action="logout">
                                        <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/partials/header.blade.php ENDPATH**/ ?>