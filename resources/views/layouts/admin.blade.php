<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="turbo-cache-control" content="no-preview">
    <title>@yield('title', 'Admin Dashboard') - Cinema Premium</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Admin Custom CSS -->
    @vite('resources/css/admin.css')
    @vite(['resources/js/admin-shell.js', 'resources/js/admin-navigation.js', 'resources/js/admin-ticket-scanner-bootstrap.js'])

    @stack('styles')
</head>
<body
    data-staff-role="{{ auth()->user()?->role?->slug ?? '' }}"
    data-can-print-orders="{{ auth()->user()?->hasPermission('tickets.issue') ? 'true' : 'false' }}"
>
    <div class="admin-wrapper">
        <!-- Mobile Header (Tablet & Mobile Only) -->
        <div id="adminMobileHeader" class="mobile-header d-lg-none" data-turbo-permanent>
            <button class="mobile-menu-toggle" aria-label="Open menu" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="brand-text-full">
                CINEMA <span class="premium">PREMIUM</span>
            </div>
        </div>

        <!-- Collapsible Sidebar -->
        <aside id="adminSidebar" class="admin-sidebar d-flex flex-column" role="navigation" aria-label="Main navigation" data-turbo-permanent>
            <!-- Sidebar Header with Toggle -->
            <div class="sidebar-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="brand-wrapper">
                        <!-- Full Logo (Expanded State) -->
                        <div class="brand-text-full">
                            CINEMA<br>
                            <span class="premium">PREMIUM</span><br>
                            <span class="brand-subtitle">ADMIN DASHBOARD</span>
                        </div>
                        <!-- Monogram Logo (Collapsed State) -->
                        <div class="brand-monogram">
                            <span class="monogram-letter">C</span>
                            <span class="monogram-p">P</span>
                        </div>
                    </div>

                    <!-- Desktop Toggle Button -->
                    <button
                        class="sidebar-toggle-btn d-none d-lg-flex"
                        id="sidebarCollapseToggle"
                        type="button"
                        aria-label="Toggle sidebar"
                        aria-expanded="true"
                        aria-controls="adminSidebar">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                </div>
            </div>

            <!-- Navigation Menu -->
            @php
                $adminUser = Auth::user();
                $canAny = function (array $permissions) use ($adminUser): bool {
                    return (bool) $adminUser?->hasAnyPermission($permissions);
                };
                $canRoute = fn (string $routeName): bool => Route::has($routeName);
                $adminMenuGroups = [
                    [
                        'type' => 'single',
                        'label' => 'T&#7893;ng quan',
                        'icon' => 'bi-grid',
                        'route' => 'admin.dashboard',
                        'active' => ['admin.dashboard'],
                        'permissions' => ['dashboard.view'],
                    ],
                    [
                        'label' => 'Th&#7889;ng k&#234;',
                        'icon' => 'bi-bar-chart',
                        'id' => 'menuThongKe',
                        'active' => ['admin.revenue.*', 'admin.tickets.*', 'admin.combos.stats'],
                        'permissions' => ['reports.view', 'analytics.view'],
                        'children' => [
                            ['label' => 'Th&#7889;ng k&#234; doanh thu', 'route' => 'admin.revenue.index', 'active' => ['admin.revenue.index'], 'permissions' => ['reports.view', 'analytics.view']],
                            ['label' => 'Th&#7889;ng k&#234; v&#233;', 'route' => 'admin.tickets.index', 'active' => ['admin.tickets.index'], 'permissions' => ['analytics.view']],
                            ['label' => 'Th&#7889;ng k&#234; s&#7843;n ph&#7849;m', 'route' => 'admin.combos.stats', 'active' => ['admin.combos.stats'], 'permissions' => ['analytics.view']],
                        ],
                    ],
                    [
                        'label' => 'H&#7879; th&#7889;ng r&#7841;p',
                        'icon' => 'bi-buildings',
                        'id' => 'menuRap',
                        'active' => ['admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*', 'admin.pricing-rules.*'],
                        'permissions' => ['branches.view', 'theaters.view', 'screens.view', 'seat_layouts.view', 'pricing.view'],
                        'children' => [
                            ['label' => 'Qu&#7843;n l&#253; chi nh&#225;nh', 'route' => 'admin.branches.index', 'active' => ['admin.branches.*'], 'permissions' => ['branches.view']],
                            ['label' => 'Qu&#7843;n l&#253; r&#7841;p chi&#7871;u', 'route' => 'admin.theaters.index', 'active' => ['admin.theaters.*'], 'permissions' => ['theaters.view']],
                            ['label' => 'Qu&#7843;n l&#253; ph&#242;ng chi&#7871;u', 'route' => 'admin.screens.index', 'active' => ['admin.screens.*'], 'permissions' => ['screens.view']],
                            ['label' => 'M&#7851;u s&#417; &#273;&#7891; gh&#7871;', 'route' => 'admin.seat-layout-templates.index', 'active' => ['admin.seat-layout-templates.*'], 'permissions' => ['seat_layouts.view', 'screens.manage_seats']],
                            ['label' => 'C&#7845;u h&#236;nh b&#7843;ng gi&#225;', 'route' => 'admin.pricing-rules.index', 'active' => ['admin.pricing-rules.*'], 'permissions' => ['pricing.view', 'pricing.update']],
                        ],
                    ],
                    [
                        'label' => 'Phim & Su&#7845;t chi&#7871;u',
                        'icon' => 'bi-film',
                        'id' => 'menuPhim',
                        'active' => ['admin.movies.*', 'admin.showtimes.*', 'admin.orders.*'],
                        'permissions' => ['movies.view', 'showtimes.view', 'orders.view_all', 'orders.view_theater'],
                        'children' => [
                            ['label' => 'Qu&#7843;n l&#253; phim', 'route' => 'admin.movies.index', 'active' => ['admin.movies.index'], 'permissions' => ['movies.view']],
                            ['label' => 'Qu&#7843;n l&#253; su&#7845;t chi&#7871;u', 'route' => 'admin.showtimes.index', 'active' => ['admin.showtimes.index'], 'permissions' => ['showtimes.view']],
                            ['label' => 'Qu&#7843;n l&#253; &#273;&#417;n h&#224;ng', 'route' => 'admin.orders.index', 'active' => ['admin.orders.index'], 'permissions' => ['orders.view_all', 'orders.view_theater']],
                        ],
                    ],
                    [
                        'label' => 'D&#7883;ch v&#7909; & &#431;u &#273;&#227;i',
                        'icon' => 'bi-box-seam',
                        'id' => 'menuDichVu',
                        'active' => ['admin.products.*', 'admin.combos.*', 'admin.promotions.*'],
                        'permissions' => ['products.view', 'combos.view', 'promotions.view'],
                        'children' => [
                            ['label' => 'Qu&#7843;n l&#253; &#273;&#7891; &#259;n & n&#432;&#7899;c u&#7889;ng', 'route' => 'admin.products.index', 'active' => ['admin.products.index'], 'permissions' => ['products.view']],
                            ['label' => 'Qu&#7843;n l&#253; combo', 'route' => 'admin.combos.index', 'active' => ['admin.combos.index'], 'permissions' => ['combos.view']],
                            ['label' => 'M&#227; gi&#7843;m gi&#225;', 'route' => 'admin.promotions.index', 'active' => ['admin.promotions.*'], 'permissions' => ['promotions.view']],
                        ],
                    ],
                    [
                        'label' => 'N&#7897;i dung',
                        'icon' => 'bi-journal-text',
                        'id' => 'menuNoiDung',
                        'active' => ['admin.posts.*', 'admin.banners.*'],
                        'permissions' => ['posts.view', 'banners.view'],
                        'children' => [
                            ['label' => 'Qu&#7843;n l&#253; b&#224;i vi&#7871;t', 'route' => 'admin.posts.index', 'active' => ['admin.posts.*'], 'permissions' => ['posts.view']],
                            ['label' => 'Qu&#7843;n l&#253; banner', 'route' => 'admin.banners.index', 'active' => ['admin.banners.*'], 'permissions' => ['banners.view']],
                        ],
                    ],
                    [
                        'label' => 'T&#224;i kho&#7843;n',
                        'icon' => 'bi-people',
                        'id' => 'menuTaiKhoan',
                        'active' => ['admin.users.*', 'admin.roles-permissions.*', 'admin.audit-logs.*'],
                        'permissions' => ['users.view', 'roles.view', 'permissions.assign', 'audit_logs.view'],
                        'children' => [
                            ['label' => 'Qu&#7843;n l&#253; t&#224;i kho&#7843;n', 'route' => 'admin.users.index', 'active' => ['admin.users.index'], 'permissions' => ['users.view']],
                            ['label' => 'Ph&#226;n quy&#7873;n', 'route' => 'admin.roles-permissions.index', 'active' => ['admin.roles-permissions.*'], 'permissions' => ['roles.view', 'permissions.assign']],
                            ['label' => 'Nh&#7853;t k&#253; ho&#7841;t &#273;&#7897;ng', 'route' => 'admin.audit-logs.index', 'active' => ['admin.audit-logs.*'], 'permissions' => ['audit_logs.view']],
                        ],
                    ],
                ];
            @endphp
            <ul class="nav flex-column sidebar-nav flex-grow-1 mt-3">
                @foreach ($adminMenuGroups as $group)
                    @continue(!$canAny($group['permissions']))

                    @if (($group['type'] ?? 'group') === 'single')
                        @continue(!$canRoute($group['route']))
                        <li class="nav-item">
                            <a href="{{ route($group['route']) }}"
                               class="nav-link {{ request()->routeIs(...$group['active']) ? 'active' : '' }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="right"
                               data-bs-title="{!! $group['label'] !!}">
                                <i class="bi {{ $group['icon'] }}"></i>
                                <span class="nav-text">{!! $group['label'] !!}</span>
                            </a>
                        </li>
                        @continue
                    @endif

                    @php
                        $visibleChildren = collect($group['children'])
                            ->filter(fn ($child) => $canRoute($child['route']) && $canAny($child['permissions']))
                            ->values();
                    @endphp
                    @continue($visibleChildren->isEmpty())

                    <li class="nav-item has-submenu">
                        <a href="#{{ $group['id'] }}"
                           class="nav-link {{ request()->routeIs(...$group['active']) ? 'active' : '' }}"
                           data-bs-toggle="collapse"
                           role="button"
                           aria-expanded="{{ request()->routeIs(...$group['active']) ? 'true' : 'false' }}"
                           aria-controls="{{ $group['id'] }}"
                           data-bs-tooltip="{!! $group['label'] !!}">
                            <i class="bi {{ $group['icon'] }}"></i>
                            <span class="nav-text">{!! $group['label'] !!}</span>
                            <i class="bi bi-chevron-down submenu-arrow"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs(...$group['active']) ? 'show' : '' }}" id="{{ $group['id'] }}">
                            <ul class="nav flex-column submenu">
                                @foreach ($visibleChildren as $child)
                                    <li class="nav-item">
                                        <a href="{{ route($child['route']) }}" class="nav-link {{ request()->routeIs(...$child['active']) ? 'active' : '' }}">
                                            {!! $child['label'] !!}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endforeach
            </ul>

            <!-- User Profile -->
            <a href="{{ route('home') }}"
               class="user-profile-block"
               data-bs-toggle="tooltip"
               data-bs-placement="right"
               data-bs-title="{{ $adminUser->name ?? 'Admin User' }} - {{ $adminUser->role?->display_name ?? $adminUser->role?->name ?? 'Management' }}">
                <img src="https://ui-avatars.com/api/?name={{ urlencode($adminUser->name ?? 'Admin User') }}&background=3f3f46&color=fff"
                     alt="User Avatar"
                     class="user-avatar">
                <div class="user-info">
                    <span class="user-name">{{ $adminUser->name ?? 'Admin User' }}</span>
                    <span class="user-role">{{ $adminUser->role?->display_name ?? $adminUser->role?->name ?? 'Management' }}</span>
                </div>
            </a>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Topbar -->
            <header class="admin-topbar">
                <div class="d-flex align-items-center">
                    <div class="page-title">
                        <h2>@yield('header_title', 'Overview')</h2>
                    </div>
                </div>

                @hasSection('topbar_center')
                    <div class="admin-topbar-center" role="group" aria-label="Ngữ cảnh trang">
                        @yield('topbar_center')
                    </div>
                @endif

                <div class="topbar-actions">
                    @if ($canAny(['tickets.verify', 'tickets.issue']))
                        <button class="btn-icon" id="scanTicketBtn" aria-label="{{ $adminUser?->hasPermission('tickets.issue') ? 'Quét QR vé hoặc hóa đơn' : 'Quét mã vé' }}" title="{{ $adminUser?->hasPermission('tickets.issue') ? 'Quét QR vé hoặc hóa đơn' : 'Quét mã vé' }}">
                            <i class="bi bi-qr-code-scan"></i>
                        </button>
                    @endif
                    <button class="btn-icon" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                    </button>
                    <button class="btn-icon" type="button" data-admin-logout aria-label="Đăng xuất" title="Đăng xuất">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                    @yield('topbar_action')
                </div>
            </header>

            <!-- Page Content -->
            <div id="adminPageContent" class="admin-page-content container-fluid px-4 pb-4 flex-grow-1">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div id="adminSidebarOverlay" class="sidebar-overlay d-lg-none" data-turbo-permanent></div>

    <!-- Toast Container -->
    <div id="adminToastContainer" class="admin-toast-container" data-turbo-permanent></div>

    <!-- Ticket Scanner Modal -->
    <div class="modal fade admin-scanner-modal" id="ticketScannerModal" tabindex="-1" aria-labelledby="ticketScannerModalLabel" aria-hidden="true" data-bs-backdrop="static" data-turbo-permanent>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content admin-scanner-shell">
                <div class="modal-header admin-scanner-header">
                    <div class="admin-scanner-title-wrap">
                        <span class="admin-scanner-title-icon" aria-hidden="true">
                            <i class="bi bi-upc-scan"></i>
                        </span>
                        <div>
                            <p class="admin-scanner-eyebrow mb-1">Ticket Control</p>
                            <h5 class="modal-title" id="ticketScannerModalLabel">{{ $adminUser?->hasPermission('tickets.issue') ? 'Quét QR vé / hóa đơn' : 'Quét mã vé' }}</h5>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body admin-scanner-body">
                    <div class="admin-scanner-mode-tabs" role="group" aria-label="Chọn phương thức quét">
                        <button type="button" class="admin-scanner-tab" id="cameraScanBtn">
                            <i class="bi bi-camera-video"></i>
                            <span>Camera</span>
                        </button>
                        <button type="button" class="admin-scanner-tab active" id="manualScanBtn">
                            <i class="bi bi-keyboard"></i>
                            <span>Nhập mã</span>
                        </button>
                    </div>

                    <div id="cameraScanner" class="scanner-mode admin-scanner-panel d-none">
                        <div class="admin-scanner-camera-frame">
                            <video id="scannerVideo" class="admin-scanner-video" autoplay playsinline></video>
                            <canvas id="scannerCanvas" class="d-none"></canvas>
                            <div class="admin-scanner-reticle" aria-hidden="true">
                                <span></span>
                            </div>
                        </div>
                    </div>

                    <div id="manualScanner" class="scanner-mode admin-scanner-panel">
                        <label for="ticketCodeInput" class="form-label">{{ $adminUser?->hasPermission('tickets.issue') ? 'Mã vé / Booking ID' : 'Mã vé' }}</label>
                        <div class="admin-scanner-input-row">
                            <div class="admin-scanner-input-wrap">
                                <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                                <input type="text" class="form-control form-control-lg" id="ticketCodeInput" placeholder="{{ $adminUser?->hasPermission('tickets.issue') ? 'Nhập mã vé để soát hoặc Booking ID để in...' : 'Nhập mã vé cần xác thực...' }}" autocomplete="off" autofocus>
                            </div>
                            <button type="button" class="admin-scanner-submit" id="verifyTicketBtn">
                                <i class="bi bi-check2-circle"></i>
                                <span>Tiếp tục</span>
                            </button>
                        </div>
                    </div>

                    <div id="scanResult" class="admin-scanner-result d-none" aria-live="polite"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" data-turbo-eval="false"></script>

    <!-- Admin Core JS -->
    <script nonce="{{ request()->attributes->get('csp_nonce') }}" data-turbo-eval="false">
        window.APP_CONFIG = {
            appName: @json(config('app.name', 'Cinema')),
            apiUrl: @json('/api/v1'),
            assetVersion: @json(config('app.asset_version')),
            csrfToken: @json(csrf_token()),
            auth: {
                checked: @json(Auth::guard('web')->check() || !request()->hasCookie('refresh_token')),
                authenticated: @json(Auth::guard('web')->check()),
                user: @json(Auth::guard('web')->user()),
            },
        };
    </script>
    @stack('scripts')
</body>
</html>
