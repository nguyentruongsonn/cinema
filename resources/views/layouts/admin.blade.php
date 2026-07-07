<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Dashboard') - Cinema Premium</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Admin Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/admin/admin-common.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/style.css') }}?v={{ time() }}">

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <!-- Mobile Header (Tablet & Mobile Only) -->
        <div class="mobile-header d-lg-none">
            <button class="mobile-menu-toggle" aria-label="Open menu" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="brand-text-full">
                CINEMA <span class="premium">PREMIUM</span>
            </div>
        </div>

        <!-- Collapsible Sidebar -->
        <aside class="admin-sidebar d-flex flex-column" role="navigation" aria-label="Main navigation">
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
            <ul class="nav flex-column sidebar-nav flex-grow-1 mt-3">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}"
                       class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                       data-bs-toggle="tooltip"
                       data-bs-placement="right"
                       data-bs-title="Tổng quan">
                        <i class="bi bi-grid"></i>
                        <span class="nav-text">Tổng quan</span>
                    </a>
                </li>

                <!-- Statistics -->
                <li class="nav-item has-submenu">
                    <a href="#menuThongKe"
                       class="nav-link {{ request()->routeIs('admin.revenue.*', 'admin.tickets.*', 'admin.combos.stats') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ request()->routeIs('admin.revenue.*', 'admin.tickets.*', 'admin.combos.stats') ? 'true' : 'false' }}"
                       aria-controls="menuThongKe"
                       data-bs-tooltip="Thống kê">
                        <i class="bi bi-bar-chart"></i>
                        <span class="nav-text">Thống kê</span>
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.revenue.*', 'admin.tickets.*', 'admin.combos.stats') ? 'show' : '' }}" id="menuThongKe">
                        <ul class="nav flex-column submenu">
                            <li class="nav-item">
                                <a href="{{ route('admin.revenue.index') }}" class="nav-link {{ request()->routeIs('admin.revenue.index') ? 'active' : '' }}">
                                    Thống kê doanh thu
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.tickets.index') }}" class="nav-link {{ request()->routeIs('admin.tickets.index') ? 'active' : '' }}">
                                    Thống kê vé
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.combos.stats') }}" class="nav-link {{ request()->routeIs('admin.combos.stats') ? 'active' : '' }}">
                                    Thống kê combo
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Theater System -->
                <li class="nav-item has-submenu">
                    <a href="#menuRap"
                       class="nav-link {{ request()->routeIs('admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ request()->routeIs('admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*') ? 'true' : 'false' }}"
                       aria-controls="menuRap"
                       data-bs-tooltip="Hệ thống rạp">
                        <i class="bi bi-buildings"></i>
                        <span class="nav-text">Hệ thống rạp</span>
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*') ? 'show' : '' }}" id="menuRap">
                        <ul class="nav flex-column submenu">
                            <li class="nav-item"><a href="{{ route('admin.branches.index') }}" class="nav-link {{ request()->routeIs('admin.branches.*') ? 'active' : '' }}">Quản lý chi nhánh</a></li>
                            <li class="nav-item"><a href="{{ route('admin.theaters.index') }}" class="nav-link {{ request()->routeIs('admin.theaters.*') ? 'active' : '' }}">Quản lý rạp chiếu</a></li>
                            <li class="nav-item"><a href="{{ route('admin.screens.index') }}" class="nav-link {{ request()->routeIs('admin.screens.*') ? 'active' : '' }}">Quản lý phòng chiếu</a></li>
                            <li class="nav-item"><a href="{{ route('admin.seat-layout-templates.index') }}" class="nav-link {{ request()->routeIs('admin.seat-layout-templates.*') ? 'active' : '' }}">Mẫu sơ đồ ghế</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Movies & Showtimes -->
                <li class="nav-item has-submenu">
                    <a href="#menuPhim"
                       class="nav-link {{ request()->routeIs('admin.movies.*', 'admin.showtimes.*', 'admin.orders.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ request()->routeIs('admin.movies.*', 'admin.showtimes.*', 'admin.orders.*') ? 'true' : 'false' }}"
                       aria-controls="menuPhim"
                       data-bs-tooltip="Phim & Suất chiếu">
                        <i class="bi bi-film"></i>
                        <span class="nav-text">Phim & Suất chiếu</span>
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.movies.*', 'admin.showtimes.*', 'admin.orders.*') ? 'show' : '' }}" id="menuPhim">
                        <ul class="nav flex-column submenu">
                            <li class="nav-item"><a href="{{ route('admin.movies.index') }}" class="nav-link {{ request()->routeIs('admin.movies.index') ? 'active' : '' }}">Quản lý phim</a></li>
                            <li class="nav-item"><a href="{{ route('admin.showtimes.index') }}" class="nav-link {{ request()->routeIs('admin.showtimes.index') ? 'active' : '' }}">Quản lý suất chiếu</a></li>
                            <li class="nav-item"><a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.index') ? 'active' : '' }}">Quản lý đơn hàng</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Services & Offers -->
                <li class="nav-item has-submenu">
                    <a href="#menuDichVu"
                       class="nav-link {{ request()->routeIs('admin.products.*', 'admin.combos.index', 'admin.promotions.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ request()->routeIs('admin.products.*', 'admin.combos.*', 'admin.promotions.*') ? 'true' : 'false' }}"
                       aria-controls="menuDichVu"
                       data-bs-tooltip="Dịch vụ & Ưu đãi">
                        <i class="bi bi-box-seam"></i>
                        <span class="nav-text">Dịch vụ & Ưu đãi</span>
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.products.*', 'admin.combos.*', 'admin.promotions.*') ? 'show' : '' }}" id="menuDichVu">
                        <ul class="nav flex-column submenu">
                            <li class="nav-item"><a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.index', 'admin.products.foods', 'admin.products.drinks') ? 'active' : '' }}">Quản lý đồ ăn & nước uống</a></li>
                            <li class="nav-item"><a href="{{ route('admin.combos.index') }}" class="nav-link {{ request()->routeIs('admin.combos.index') ? 'active' : '' }}">Quản lý combo</a></li>
                            <li class="nav-item"><a href="{{ route('admin.promotions.index') }}" class="nav-link {{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}">Mã giảm giá</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Content -->
                <li class="nav-item has-submenu">
                    <a href="#menuNoiDung"
                       class="nav-link {{ request()->routeIs('admin.posts.*', 'admin.banners.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ request()->routeIs('admin.posts.*', 'admin.banners.*') ? 'true' : 'false' }}"
                       aria-controls="menuNoiDung"
                       data-bs-tooltip="Nội dung">
                        <i class="bi bi-journal-text"></i>
                        <span class="nav-text">Nội dung</span>
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.posts.*', 'admin.banners.*') ? 'show' : '' }}" id="menuNoiDung">
                        <ul class="nav flex-column submenu">
                            <li class="nav-item"><a href="{{ route('admin.posts.index') }}" class="nav-link {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">Quản lý bài viết</a></li>
                            <li class="nav-item"><a href="{{ route('admin.banners.index') }}" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">Quản lý banner</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Accounts -->
                <li class="nav-item has-submenu">
                    <a href="#menuTaiKhoan"
                       class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ request()->routeIs('admin.users.*') ? 'true' : 'false' }}"
                       aria-controls="menuTaiKhoan"
                       data-bs-tooltip="Tài khoản">
                        <i class="bi bi-people"></i>
                        <span class="nav-text">Tài khoản</span>
                        <i class="bi bi-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.users.*') ? 'show' : '' }}" id="menuTaiKhoan">
                        <ul class="nav flex-column submenu">
                            <li class="nav-item">
                                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.index') ? 'active' : '' }}">
                                    Quản lý tài khoản
                                </a>
                            </li>
                            <li class="nav-item"><a href="#" class="nav-link">Phân quyền</a></li>
                        </ul>
                    </div>
                </li>
            </ul>

            <!-- User Profile -->
            <a href="{{ route('home') }}"
               class="user-profile-block"
               data-bs-toggle="tooltip"
               data-bs-placement="right"
               data-bs-title="{{ Auth::user()->name ?? 'Admin User' }} - System Manager">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'Admin User') }}&background=3f3f46&color=fff"
                     alt="User Avatar"
                     class="user-avatar">
                <div class="user-info">
                    <span class="user-name">{{ Auth::user()->name ?? 'Admin User' }}</span>
                    <span class="user-role">System Manager</span>
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

                <div class="topbar-actions">
                    <button class="btn-icon" id="scanTicketBtn" aria-label="Quét mã vạch vé" title="Quét mã vạch vé">
                        <i class="bi bi-qr-code-scan"></i>
                    </button>
                    <button class="btn-icon" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                    </button>
                    @yield('topbar_action')
                </div>
            </header>

            <!-- Page Content -->
            <div class="container-fluid px-4 pb-4 flex-grow-1">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay d-lg-none"></div>

    <!-- Toast Container -->
    <div id="adminToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Ticket Scanner Modal -->
    <div class="modal fade" id="ticketScannerModal" tabindex="-1" aria-labelledby="ticketScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="ticketScannerModalLabel">
                        <i class="bi bi-qr-code-scan me-2"></i>Quét Mã Vạch Vé
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Scanner Modes -->
                    <div class="btn-group w-100 mb-3" role="group">
                        <button type="button" class="btn btn-outline-light" id="cameraScanBtn">
                            <i class="bi bi-camera"></i> Quét bằng Camera
                        </button>
                        <button type="button" class="btn btn-outline-light active" id="manualScanBtn">
                            <i class="bi bi-keyboard"></i> Nhập thủ công
                        </button>
                    </div>

                    <!-- Camera Scanner -->
                    <div id="cameraScanner" class="scanner-mode" style="display: none;">
                        <div class="position-relative">
                            <video id="scannerVideo" class="w-100 rounded" style="max-height: 400px; background: #000;" autoplay playsinline></video>
                            <canvas id="scannerCanvas" style="display: none;"></canvas>
                            <div class="scanner-overlay position-absolute top-50 start-50 translate-middle">
                                <div class="scanner-frame"></div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>Hướng mã vạch QR/barcode vào camera
                        </div>
                    </div>

                    <!-- Manual Input -->
                    <div id="manualScanner" class="scanner-mode">
                        <div class="mb-3">
                            <label for="ticketCodeInput" class="form-label">Mã vé</label>
                            <input type="text" class="form-control form-control-lg bg-dark text-white border-secondary"
                                   id="ticketCodeInput" placeholder="Nhập hoặc quét mã vé..." autofocus>
                            <small class="text-muted">Có thể quét bằng máy quét mã vạch hoặc nhập thủ công</small>
                        </div>
                        <button type="button" class="btn btn-primary w-100" id="verifyTicketBtn">
                            <i class="bi bi-check-circle me-2"></i>Xác Thực Vé
                        </button>
                    </div>

                    <!-- Scan Result -->
                    <div id="scanResult" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- HTML5 QR Code Scanner (for camera scanning) -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

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
    </script>
    <script src="{{ asset('js/core/api-client.js') }}"></script>
    <script src="{{ asset('js/auth.js') }}"></script>
    <script src="{{ asset('js/admin/app.js') }}?v={{ time() }}"></script>

    <!-- Responsive Menu JS -->
    <script src="{{ asset('js/admin/responsive-menu.js') }}?v={{ time() }}"></script>

    <!-- Mobile Search Toggle JS -->
    <script src="{{ asset('js/admin/mobile-search-toggle.js') }}?v={{ time() }}"></script>

    <!-- Ticket Scanner JS -->
    <script src="{{ asset('js/admin/ticket-scanner.js') }}?v={{ time() }}"></script>

    @stack('scripts')
</body>
</html>
