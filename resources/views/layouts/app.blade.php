<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Cinema - Website đặt vé xem phim trực tuyến, nhanh chóng và tiện lợi.')">

    <title>@yield('title', 'Cinema') - Đặt Vé Xem Phim</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Bootstrap / Icons CDN fallback-friendly --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    {{-- Vite-owned user shell --}}
    @vite(['resources/css/user.css', 'resources/js/user-shell.js'])

    @stack('styles')
</head>
<body>
    <div id="app" class="min-vh-100 d-flex flex-column has-mobile-nav">
        @include('partials.header')

        <main class="flex-grow-1">
            @yield('content')
        </main>

        @include('partials.footer')

        {{-- Mobile Bottom Navigation --}}
        <nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile navigation">
            <a href="{{ route('home') }}" class="mobile-nav-item {{ request()->is('/') ? 'active' : '' }}">
                <i class="bi bi-house-door"></i>
                <span>Trang chủ</span>
            </a>
            <a href="{{ route('movies.index') }}" class="mobile-nav-item {{ request()->is('movies*') ? 'active' : '' }}">
                <i class="bi bi-film"></i>
                <span>Phim</span>
            </a>
            <a href="{{ route('posts.index') }}" class="mobile-nav-item {{ request()->is('posts*') ? 'active' : '' }}">
                <i class="bi bi-newspaper"></i>
                <span>Tin tức</span>
            </a>
            <a href="{{ route('profile.index') }}" class="mobile-nav-item {{ request()->is('profile*') ? 'active' : '' }}">
                <i class="bi bi-person"></i>
                <span>Tài khoản</span>
            </a>
        </nav>
    </div>

    {{-- Auth Modal --}}
    @include('partials.auth-modal')

    <script>
        window.APP_CONFIG = {
            appName: @json(config('app.name', 'Cinema')),
            apiUrl: @json(url('/api/v1')),
            csrfToken: @json(csrf_token()),
            auth: {
                checked: @json(Auth::guard('web')->check() || !request()->hasCookie('refresh_token')),
                authenticated: @json(Auth::guard('web')->check()),
                user: @json(Auth::guard('web')->user()),
            },
        };
        window.REVERB_CONFIG = {
            enabled:   @json((bool) env('REVERB_ENABLED', false)),
            key:       @json(config('broadcasting.connections.reverb.key')),
            host:      @json(config('broadcasting.connections.reverb.options.host', 'localhost')),
            port:      {{ config('broadcasting.connections.reverb.options.port', 8080) }},
            scheme:    @json(config('broadcasting.connections.reverb.options.scheme', 'http')),
            authEndpoint: '/api/v1/broadcasting/auth',
            csrfToken: @json(csrf_token()),
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Laravel Echo + Pusher JS (CDN, no Vite)
         Only load Echo when realtime broadcasting is enabled.
         This prevents loading an incompatible ESM Echo build and avoids
         "Unexpected token 'export'" / "Echo is not defined" on normal pages. --}}
    @if ((bool) env('REVERB_ENABLED', false))
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
        <script>
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
    @else
        <script>
            window.Echo = null;
            window.initEcho = function () {
                return null;
            };
        </script>
    @endif

    @stack('scripts')
</body>
</html>
