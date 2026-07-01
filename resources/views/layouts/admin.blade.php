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
    <link rel="stylesheet" href="{{ asset('css/admin/style.css') }}?v={{ time() }}">

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar d-flex flex-column">
            <div class="sidebar-header">
                <div class="brand-text">
                    CINEMA<br>
                    <span class="premium">PREMIUM</span><br>
                    <span class="brand-subtitle">ADMIN DASHBOARD</span>
                </div>
            </div>

            <ul class="nav flex-column sidebar-nav flex-grow-1 mt-3">
                <!-- Tổng quan -->
                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid"></i> Tổng quan
                    </a>
                </li>

                <!-- Thống kê -->
                <li class="nav-item">
                    <a href="#menuThongKe" class="nav-link" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="menuThongKe">
                        <i class="bi bi-bar-chart"></i> Thống kê
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.revenue.*', 'admin.tickets.*', 'admin.combos.*') ? 'show' : '' }}" id="menuThongKe">
                        <ul class="nav flex-column ps-4 pb-2">
                            <li class="nav-item">
                                <a href="{{ route('admin.revenue.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.revenue.index') ? 'text-white fw-semibold' : '' }}">
                                    Thống kê doanh thu
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.tickets.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.tickets.index') ? 'text-white fw-semibold' : '' }}">
                                    Thống kê vé
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.combos.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.combos.index') ? 'text-white fw-semibold' : '' }}">
                                    Thống kê combo
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Hệ thống rạp -->
                <li class="nav-item">
                    <a href="#menuRap" class="nav-link {{ request()->routeIs('admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*') ? 'active' : '' }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*') ? 'true' : 'false' }}" aria-controls="menuRap">
                        <i class="bi bi-buildings"></i> Hệ thống rạp
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.branches.*', 'admin.theaters.*', 'admin.screens.*', 'admin.seat-layout-templates.*') ? 'show' : '' }}" id="menuRap">
                        <ul class="nav flex-column ps-4 pb-2">
                            <li class="nav-item"><a href="{{ route('admin.branches.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.branches.*') ? 'text-white fw-semibold' : '' }}">Quản lý chi nhánh</a></li>
                            <li class="nav-item"><a href="{{ route('admin.theaters.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.theaters.*') ? 'text-white fw-semibold' : '' }}">Quản lý rạp chiếu</a></li>
                            <li class="nav-item"><a href="{{ route('admin.screens.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.screens.*') ? 'text-white fw-semibold' : '' }}">Quản lý phòng chiếu</a></li>
                            <li class="nav-item"><a href="{{ route('admin.seat-layout-templates.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.seat-layout-templates.*') ? 'text-white fw-semibold' : '' }}">Mẫu sơ đồ ghế</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Phim & Suất chiếu -->
                <li class="nav-item">
                    <a href="#menuPhim" class="nav-link {{ request()->routeIs('admin.movies.*', 'admin.showtimes.*') ? 'active' : '' }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('admin.movies.*', 'admin.showtimes.*') ? 'true' : 'false' }}" aria-controls="menuPhim">
                        <i class="bi bi-film"></i> Phim & Suất chiếu
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.movies.*', 'admin.showtimes.*') ? 'show' : '' }}" id="menuPhim">
                        <ul class="nav flex-column ps-4 pb-2">
                            <li class="nav-item"><a href="{{ route('admin.movies.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.movies.index') ? 'text-white' : '' }}">Quản lý phim</a></li>
                            <li class="nav-item"><a href="{{ route('admin.showtimes.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.showtimes.index') ? 'text-white' : '' }}">Quản lý suất chiếu</a></li>
                            <li class="nav-item"><a href="#" class="nav-link py-1">Quản lý hóa đơn</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Dịch vụ và ưu đãi -->
                <li class="nav-item">
                    <a href="#menuDichVu" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('admin.products.*') ? 'true' : 'false' }}" aria-controls="menuDichVu">
                        <i class="bi bi-box-seam"></i> Dịch vụ & Ưu đãi
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.products.*') ? 'show' : '' }}" id="menuDichVu">
                        <ul class="nav flex-column ps-4 pb-2">
                            <li class="nav-item"><a href="{{ route('admin.products.index') }}" class="nav-link py-1 {{ request()->routeIs('admin.products.index') ? 'text-white' : '' }}">Quản lý sản phẩm / Combo</a></li>
                            <li class="nav-item"><a href="#" class="nav-link py-1">Mã giảm giá</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Nội dung -->
                <li class="nav-item">
                    <a href="#menuNoiDung" class="nav-link" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="menuNoiDung">
                        <i class="bi bi-journal-text"></i> Nội dung
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse" id="menuNoiDung">
                        <ul class="nav flex-column ps-4 pb-2">
                            <li class="nav-item"><a href="#" class="nav-link py-1">Quản lý bài viết</a></li>
                        </ul>
                    </div>
                </li>

                <!-- Tài khoản -->
                <li class="nav-item">
                    <a href="#menuTaiKhoan" class="nav-link" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="menuTaiKhoan">
                        <i class="bi bi-people"></i> Tài khoản
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse" id="menuTaiKhoan">
                        <ul class="nav flex-column ps-4 pb-2">
                            <li class="nav-item"><a href="#" class="nav-link py-1">Quản lý tài khoản</a></li>
                            <li class="nav-item"><a href="#" class="nav-link py-1">Phân quyền</a></li>
                        </ul>
                    </div>
                </li>
            </ul>

            <a href="{{ route('home') }}" class="user-profile-block">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'Admin User') }}&background=3f3f46&color=fff" alt="User" width="36" height="36" class="rounded-circle me-3">
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
                    <button class="btn-icon d-lg-none me-3 border-0" id="sidebarToggle">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div class="page-title">
                        <h2>@yield('header_title', 'Overview')</h2>
                        <span class="page-subtitle">@yield('header_subtitle', "Welcome back. Here's what's happening today.")</span>
                    </div>
                </div>

                <div class="topbar-actions">
                    <button class="btn-icon">
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

    <!-- Toast Container -->
    <div id="adminToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/admin-core.js') }}?v={{ time() }}"></script>

    <!-- Admin Core JS -->
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
    @stack('scripts')
</body>
</html>

