<header class="cinema-header">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container cinema-shell">
            <a class="navbar-brand cinema-brand" href="{{ route('home') }}" aria-label="Cinema home">
                CINEMA
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Open menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="mainNavbar" class="collapse navbar-collapse">
                <ul class="navbar-nav mx-auto cinema-nav gap-lg-4">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('movies*') ? 'active' : '' }}" href="{{ route('movies.index') }}">PHIM</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('theaters*') ? 'active' : '' }}" href="{{ route('theaters.index') }}">RẠP</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">GÓC ĐIỆN ẢNH</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('posts*') ? 'active' : '' }}" href="{{ route('posts.index') }}">
                            TIN TỨC & ƯU ĐÃI
                        </a>
                    </li>
                </ul>

                <div class="cinema-actions d-flex align-items-center gap-3">
                    <div class="fb-search-wrapper" id="fbSearchWrapper">
                        <button type="button" class="cinema-icon-link fb-search-trigger" id="fbSearchTrigger" aria-label="Tìm kiếm phim">
                            <i class="bi bi-search"></i>
                        </button>
                        <div class="fb-search-panel d-none" id="fbSearchPanel">
                            <div class="fb-search-header">
                                <div class="fb-search-input-box">
                                    <i class="bi bi-search fb-search-box-icon"></i>
                                    <input type="text" id="fbSearchInput" class="fb-search-input" placeholder="Tìm kiếm phim, diễn viên..." autocomplete="off">
                                    <button type="button" class="fb-search-clear-btn d-none" id="fbSearchClearBtn" aria-label="Xóa từ khóa">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="fb-search-body" id="fbSearchBody">
                                <div class="fb-search-recent" id="fbSearchRecent">
                                    <div class="fb-search-section-header">
                                        <span class="fb-search-section-title">Tìm kiếm gần đây</span>
                                        <button type="button" class="fb-search-clear-all" id="fbSearchClearAll">Xóa tất cả</button>
                                    </div>
                                    <div class="fb-search-recent-list" id="fbSearchRecentList"></div>
                                </div>
                                <div class="fb-search-trending" id="fbSearchTrending">
                                    <div class="fb-search-section-header">
                                        <span class="fb-search-section-title">Gợi ý phim nổi bật</span>
                                    </div>
                                    <div class="fb-search-trending-list" id="fbSearchTrendingList"></div>
                                </div>
                                <div class="fb-search-results d-none" id="fbSearchResults">
                                    <div class="fb-search-results-list" id="fbSearchResultsList"></div>
                                </div>
                                <div class="fb-search-loading d-none" id="fbSearchLoading">
                                    <div class="fb-search-skeleton-item">
                                        <div class="fb-search-skeleton-avatar"></div>
                                        <div class="fb-search-skeleton-lines">
                                            <div class="fb-search-skeleton-line short"></div>
                                            <div class="fb-search-skeleton-line long"></div>
                                        </div>
                                    </div>
                                    <div class="fb-search-skeleton-item">
                                        <div class="fb-search-skeleton-avatar"></div>
                                        <div class="fb-search-skeleton-lines">
                                            <div class="fb-search-skeleton-line short"></div>
                                            <div class="fb-search-skeleton-line long"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fb-search-empty d-none" id="fbSearchEmpty">
                                    <i class="bi bi-search fb-search-empty-icon"></i>
                                    <div class="fb-search-empty-text">Không tìm thấy kết quả phù hợp</div>
                                </div>
                            </div>
                        </div>
                        <div class="fb-search-backdrop d-none" id="fbSearchBackdrop"></div>
                    </div>
                    <a href="#notifications" class="cinema-icon-link" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                    </a>

                    @if (!Auth::guard('web')->check())
                        <a href="#" class="btn btn-danger cinema-login-btn" data-auth-action="login">
                            Đăng nhập
                        </a>
                    @else
                        @php($currentUser = Auth::guard('web')->user())
                        <div class="dropdown" id="userDropdown">
                            <button class="btn btn-link text-white text-decoration-none dropdown-toggle d-flex align-items-center gap-2"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5"></i>
                                <span class="user-name d-none d-lg-inline">{{ Auth::guard('web')->user()->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile.index') }}">
                                        <i class="bi bi-person me-2"></i>Tài khoản
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile.index') }}#tickets">
                                        <i class="bi bi-ticket-perforated me-2"></i>Vé của tôi
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#orders">
                                        <i class="bi bi-bag me-2"></i>Đơn hàng
                                    </a>
                                </li>
                                @if ($currentUser?->canAccessAdminPanel())
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.entry') }}">
                                            <i class="bi bi-speedometer2 me-2"></i>Giao diện quản lý
                                        </a>
                                    </li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" data-auth-action="logout">
                                        <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </nav>
</header>
