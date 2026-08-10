<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="description" content="<?php echo $__env->yieldContent('meta_description', 'Cinema - Website đặt vé xem phim trực tuyến, nhanh chóng và tiện lợi.'); ?>">

    <title><?php echo $__env->yieldContent('title', 'Cinema'); ?> - Đặt Vé Xem Phim</title>

    <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/user.css', 'resources/js/user-shell.js']); ?>

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
    <div id="app" class="min-vh-100 d-flex flex-column has-mobile-nav">
        <?php echo $__env->make('partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <main class="flex-grow-1">
            <?php echo $__env->yieldContent('content'); ?>
        </main>

        <?php echo $__env->make('partials.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        
        <nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile navigation">
            <a href="<?php echo e(route('home')); ?>" class="mobile-nav-item <?php echo e(request()->is('/') ? 'active' : ''); ?>">
                <i class="bi bi-house-door"></i>
                <span>Trang chủ</span>
            </a>
            <a href="<?php echo e(route('movies.index')); ?>" class="mobile-nav-item <?php echo e(request()->is('movies*') ? 'active' : ''); ?>">
                <i class="bi bi-film"></i>
                <span>Phim</span>
            </a>
            <a href="<?php echo e(route('posts.index')); ?>" class="mobile-nav-item <?php echo e(request()->is('posts*') ? 'active' : ''); ?>">
                <i class="bi bi-newspaper"></i>
                <span>Tin tức</span>
            </a>
            <a href="<?php echo e(route('profile.index')); ?>" class="mobile-nav-item <?php echo e(request()->is('profile*') ? 'active' : ''); ?>">
                <i class="bi bi-person"></i>
                <span>Tài khoản</span>
            </a>
        </nav>
    </div>

    
    <?php echo $__env->make('partials.auth-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <script nonce="<?php echo e(request()->attributes->get('csp_nonce')); ?>">
        window.APP_CONFIG = {
            appName: <?php echo json_encode(config('app.name', 'Cinema'), 512) ?>,
            apiUrl: <?php echo json_encode('/api/v1', 15, 512) ?>,
            csrfToken: <?php echo json_encode(csrf_token(), 15, 512) ?>,
            auth: {
                checked: <?php echo json_encode(Auth::guard('web')->check() || !request()->hasCookie('refresh_token'), 15, 512) ?>,
                authenticated: <?php echo json_encode(Auth::guard('web')->check(), 15, 512) ?>,
                user: <?php echo json_encode(Auth::guard('web')->user(), 15, 512) ?>,
            },
        };
        window.REVERB_CONFIG = {
            enabled:   <?php echo json_encode((bool) env('REVERB_ENABLED', false), 512) ?>,
            key:       <?php echo json_encode(config('broadcasting.connections.reverb.key'), 15, 512) ?>,
            host:      <?php echo json_encode(config('broadcasting.connections.reverb.options.host', 'localhost'), 512) ?>,
            port:      <?php echo e(config('broadcasting.connections.reverb.options.port', 8080)); ?>,
            scheme:    <?php echo json_encode(config('broadcasting.connections.reverb.options.scheme', 'http'), 512) ?>,
            authEndpoint: '/api/v1/broadcasting/auth',
            csrfToken: <?php echo json_encode(csrf_token(), 15, 512) ?>,
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    
    <?php if((bool) env('REVERB_ENABLED', false)): ?>
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
        <script nonce="<?php echo e(request()->attributes->get('csp_nonce')); ?>">
            /**
             * Initialize Laravel Echo with JWT token from localStorage.
             * Called once on page load, and can be re-called after login.
             */
            function initEcho() {
                const jwtToken = localStorage.getItem('auth_token');
                const cfg = window.REVERB_CONFIG || {};
                // Always use the global Echo class, never the instance
                const EchoClass = (typeof Echo !== 'undefined') ? Echo : null;

                if (!cfg.enabled || !cfg.key) {
                    window.Echo = null;
                    console.warn('[Echo] Reverb not enabled or key missing. cfg=', cfg);
                    return;
                }

                if (!EchoClass) {
                    console.warn('[Echo] Laravel Echo library was not loaded; realtime features are disabled.');
                    window.Echo = null;
                    return;
                }

                if (window.Echo && typeof window.Echo.disconnect === 'function') {
                    window.Echo.disconnect();
                }

                window.Echo = new EchoClass({
                    broadcaster: 'reverb',
                    key: cfg.key,
                    wsHost: cfg.host,
                    wsPort: parseInt(cfg.port),
                    wssPort: parseInt(cfg.port),
                    forceTLS: cfg.scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    authEndpoint: cfg.authEndpoint,
                    auth: {
                        headers: {
                            'Authorization': jwtToken ? `Bearer ${jwtToken}` : '',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                            'Accept': 'application/json',
                        }
                    },
                });
                console.log('[Echo] Initialized. host=' + cfg.host + ':' + cfg.port);
            }

            // Boot Echo immediately
            initEcho();

            // Re-init Echo after login so the new JWT token is picked up
            window.addEventListener('echo:reinit', () => initEcho());
        </script>
    <?php else: ?>
        <script nonce="<?php echo e(request()->attributes->get('csp_nonce')); ?>">
            window.Echo = null;
            window.initEcho = function () {
                return null;
            };
        </script>
    <?php endif; ?>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/layouts/app.blade.php ENDPATH**/ ?>