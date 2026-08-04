@extends('layouts.app')

@section('title', 'Hệ thống Rạp - Cinema')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/pages/theaters.css') }}?v={{ filemtime(public_path('css/users/pages/theaters.css')) }}">
@endpush

@section('content')
    <!-- Header Section -->
    <section class="theaters-header">
        <div class="container cinema-shell" id="theatersDataRegion" data-state="loading" aria-busy="true">
            <h1 class="page-title text-center text-md-start">Hệ thống Rạp Chiếu</h1>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="theaters-filters-section sticky-top">
        <div class="container cinema-shell">
            <div class="filters-content">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label class="filter-label" for="branchFilter">Chi nhánh</label>
                            <select id="branchFilter" class="filter-select">
                                <option value="">Tất cả chi nhánh</option>
                                <!-- Rendered by JS -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6 offset-md-3">
                        <div class="filter-group">
                            <label class="filter-label" for="searchInput">Tìm kiếm</label>
                            <div class="search-box">
                                <input type="text" id="searchInput" class="search-input" placeholder="Tìm theo tên rạp...">
                                <button type="button" id="searchBtn" class="search-btn" aria-label="Tìm kiếm">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Theaters Listing -->
    <section class="theaters-listing-section">
        <div class="container cinema-shell">
            
            <!-- Skeleton Loader -->
            <div id="theatersSkeleton" class="row g-4" data-state-panel="loading">
                @for ($i = 0; $i < 6; $i++)
                    <div class="col-md-6 col-lg-4">
                        <div class="theater-card-skeleton">
                            <div class="skeleton-img"></div>
                            <div class="skeleton-content">
                                <div class="skeleton-title"></div>
                                <div class="skeleton-text"></div>
                                <div class="skeleton-text short"></div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>

            <!-- Theaters Grid -->
            <div id="theatersGrid" class="row g-4 d-none" data-state-panel="ready">
                <!-- Rendered by JS -->
            </div>

            <!-- Empty State -->
            <x-user.data-state
                id="emptyState"
                type="empty"
                icon="bi-building-slash"
                title="Không tìm thấy rạp chiếu nào"
                message="Vui lòng thử điều chỉnh bộ lọc hoặc từ khóa tìm kiếm của bạn."
            />

            <x-user.data-state
                id="theatersErrorState"
                type="error"
                icon="bi-wifi-off"
                title="Không thể tải danh sách rạp"
                message="Vui lòng kiểm tra kết nối và thử lại."
            />

            <!-- Pagination -->
            <div id="paginationContainer" class="cinema-pagination d-none">
                <!-- Rendered by JS -->
            </div>
            
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/users/pages/theaters.js') }}?v={{ config('app.asset_version', time()) }}"></script>
@endpush
