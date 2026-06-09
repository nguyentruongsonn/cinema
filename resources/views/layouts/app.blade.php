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

    {{-- Bootstrap / Icons CDN fallback-friendly --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    {{-- Local assets --}}
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    @stack('styles')
</head>
<body>
    <div id="app" class="min-vh-100 d-flex flex-column">
        @include('partials.header')

        <main class="flex-grow-1">
            @yield('content')
        </main>

        @include('partials.footer')
    </div>

    {{-- Auth Modal --}}
    @include('partials.auth-modal')

    <script>
        window.APP_CONFIG = {
            appName: @json(config('app.name', 'Cinema')),
            apiUrl: @json(url('/api')),
            csrfToken: @json(csrf_token()),
        };
        window.REVERB_CONFIG = {
            key:       @json(config('broadcasting.connections.reverb.key')),
            host:      @json(config('broadcasting.connections.reverb.host', 'localhost')),
            port:      {{ config('broadcasting.connections.reverb.port', 8080) }},
            scheme:    @json(config('broadcasting.connections.reverb.scheme', 'http')),
            authEndpoint: '/broadcasting/auth',
            csrfToken: @json(csrf_token()),
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Laravel Echo + Pusher JS (CDN, no Vite) --}}
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.js"></script>
    <script>
        /**
         * Initialize Laravel Echo with JWT token from localStorage.
         * Called once on page load, and can be re-called after login.
         */
        function initEcho() {
            const jwtToken = localStorage.getItem('auth_token');
            const cfg = window.REVERB_CONFIG;

            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: cfg.key,
                wsHost: cfg.host,
                wsPort: cfg.port,
                wssPort: cfg.port,
                forceTLS: cfg.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                // Custom auth endpoint protected by JWT middleware
                authEndpoint: '/api/broadcasting/auth',
                auth: {
                    headers: {
                        'Authorization': jwtToken ? `Bearer ${jwtToken}` : '',
                        'X-CSRF-TOKEN': cfg.csrfToken,
                        'Accept': 'application/json',
                    }
                },
            });
        }

        // Boot Echo immediately
        initEcho();

        // Re-init Echo after login so the new JWT token is picked up
        window.addEventListener('echo:reinit', () => initEcho());
    </script>

    {{-- Shared Security Utilities --}}
    <script src="{{ asset('js/utils/security-standalone.js') }}"></script>

    {{-- Auth Module --}}
    <script src="{{ asset('js/auth.js') }}"></script>

    @stack('scripts')
</body>
</html>
