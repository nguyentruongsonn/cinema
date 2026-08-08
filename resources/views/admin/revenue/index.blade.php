@extends('layouts.admin')

@section('title', 'Thống kê Doanh thu')
@section('header_title', 'Thống kê Doanh thu')

@section('content')
<div class="admin-stats-page">

{{-- ── Row 1: Date Range Filter ─────────────────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner">
        <div class="filter-group">
            <label for="filterStart" class="filter-label">Từ ngày</label>
            <input type="date" id="filterStart" class="filter-input" />
        </div>
        <div class="filter-group">
            <label for="filterEnd" class="filter-label">Đến ngày</label>
            <input type="date" id="filterEnd" class="filter-input" />
        </div>
        <div class="filter-shortcuts d-flex gap-2">
            <button class="btn-shortcut active" data-range="week">Tuần này</button>
            <button class="btn-shortcut" data-range="month">Tháng này</button>
            <button class="btn-shortcut" data-range="quarter">Quý này</button>
            <button class="btn-shortcut" data-range="year">Năm nay</button>
        </div>
        <button id="btnApplyFilter" class="btn-primary-custom ms-auto">
            <i class="bi bi-arrow-clockwise"></i> Cập nhật
        </button>
    </div>
</div>

{{-- ── Row 2: 4 Summary Cards ───────────────────────────────────────── --}}
<div class="row g-4 mb-4">

    {{-- Card 1: Tổng doanh thu --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TỔNG DOANH THU</span>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text" id="cardTotalRevenue"></div>
            <div class="stat-trend mt-2">
                <span class="text-secondary small admin-skeleton admin-skeleton-text" id="cardTotalOrders"></span>
            </div>
        </div>
    </div>

    {{-- Card 2: Rạp doanh thu cao nhất --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">RẠP DOANH THU CAO NHẤT</span>
                <div class="stat-icon"><i class="bi bi-buildings"></i></div>
            </div>
            <div class="stat-value text-truncate admin-skeleton admin-skeleton-text admin-stat-value-lg" id="cardTopTheaterRevenue"></div>
            <div class="stat-trend mt-2">
                <span class="fw-bold text-info admin-skeleton admin-skeleton-text" id="cardTopTheaterName"></span>
                <span class="trend-text ms-2 admin-skeleton admin-skeleton-text" id="cardTopTheaterPct"></span>
            </div>
        </div>
    </div>

    {{-- Card 3: Phim doanh thu cao nhất --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">PHIM DOANH THU CAO NHẤT</span>
                <div class="stat-icon"><i class="bi bi-film"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text admin-stat-value-lg" id="cardTopMovieRevenue"></div>
            <div class="stat-trend mt-2">
                <span class="fw-bold text-warning text-truncate d-block admin-skeleton admin-skeleton-text admin-max-w-200" id="cardTopMovieTitle"></span>
                <span class="trend-text admin-skeleton admin-skeleton-text" id="cardTopMovieTickets"></span>
            </div>
        </div>
    </div>

    {{-- Card 4: Phương thức thanh toán --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">PHƯƠNG THỨC PHỔ BIẾN NHẤT</span>
                <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text admin-stat-value-lg admin-uppercase" id="cardTopPayMethod"></div>
            <div class="stat-trend mt-2">
                <span class="fw-bold text-success admin-skeleton admin-skeleton-text" id="cardTopPayMethodPct"></span>
                <span class="trend-text ms-2">tổng lượt thanh toán</span>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Pie (Rạp) + Bar (Phim) ───────────────────────────────── --}}
<div class="row g-4 mb-4">
    <div class="col-xl-5 col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Doanh thu theo Rạp</h3>
                <span class="badge bg-secondary" id="badgeTheaterCount"></span>
            </div>
            <div id="chartTheaterPie" class="admin-skeleton admin-skeleton-chart admin-chart-h-300"></div>
        </div>
    </div>
    <div class="col-xl-7 col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Doanh thu theo Phim (Top 10)</h3>
            </div>
            <div id="chartMovieBar" class="admin-skeleton admin-skeleton-chart admin-chart-h-300"></div>
        </div>
    </div>
</div>

{{-- ── Row 4: Donut (Thanh toán) + Area (Xu hướng) ─────────────────── --}}
<div class="row g-4 mb-4">
    <div class="col-xl-4 col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Tỉ lệ Phương thức Thanh toán</h3>
            </div>
            <div id="chartPaymentDonut" class="admin-skeleton admin-skeleton-chart admin-chart-h-280"></div>
            <div id="paymentLegend" class="mt-3 d-flex flex-column gap-2 px-2"></div>
        </div>
    </div>
    <div class="col-xl-8 col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Xu hướng Doanh thu theo Thời gian</h3>
            </div>
            <div id="chartRevenueTrend" class="admin-skeleton admin-skeleton-chart admin-chart-h-300"></div>
        </div>
    </div>
</div>

</div>

@endsection

@push('styles')
@vite(['resources/css/admin/dashboard-redesign.css', 'resources/css/admin/pages/stats.css'])
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
<script src="{{ asset('js/admin/pages/revenue.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
