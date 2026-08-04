@extends('layouts.admin')

@section('title', 'Thống kê Combo')
@section('header_title', 'Thống kê Combo')
@section('header_subtitle', 'Phân tích doanh thu và lượng combo bán ra theo thời gian.')

@section('content')

{{-- Shared statistics controls - cards and charts update when the scope changes --}}

{{-- ── Row 1: Date Range Filter ─────────────────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner">
        <div class="filter-group stats-type-filter">
            <span class="filter-label" id="statsTypeLabel">Loại thống kê</span>
            <div class="admin-segmented-tabs" role="tablist" aria-labelledby="statsTypeLabel">
                <button class="admin-segmented-tab" id="food-tab" type="button" role="tab"
                        aria-selected="false" data-stats-type="food">
                    <i class="bi bi-cup-straw"></i>
                    <span>Đồ ăn & Nước uống</span>
                </button>
                <button class="admin-segmented-tab active" id="combo-tab" type="button" role="tab"
                        aria-selected="true" data-stats-type="combo">
                    <i class="bi bi-box-seam"></i>
                    <span>Combo</span>
                </button>
            </div>
        </div>
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

    {{-- Card 1: Tổng combo bán ra --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TỔNG COMBO BÁN RA</span>
                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text" id="cardTotalCombos"></div>
            <div class="stat-trend">
                <span id="cardTotalCombosTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    {{-- Card 2: Doanh thu từ combo --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">DOANH THU TỪ COMBO</span>
                <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text" id="cardRevenue"></div>
            <div class="stat-trend">
                <span id="cardRevenueTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    {{-- Card 3: Trung bình mỗi ngày --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TRUNG BÌNH MỖI NGÀY</span>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text" id="cardAvgPerDay"></div>
            <div class="stat-trend">
                <span id="cardAvgPerDayTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    {{-- Card 4: Combo phổ biến nhất --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">PHỔ BIẾN NHẤT</span>
                <div class="stat-icon"><i class="bi bi-star"></i></div>
            </div>
            <div class="stat-value admin-skeleton admin-skeleton-text admin-stat-value-md" id="cardTopCombo"></div>
            <div class="stat-trend mt-2">
                <span class="text-secondary small">combo bán chạy nhất</span>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Xu hướng bán combo & Top Combos ────────────────────────────── --}}
<div class="row g-4 mb-4">
    <div class="col-xl-8 col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Xu hướng lượng combo bán ra</h3>
            </div>
            <div id="chartComboTrend" class="admin-skeleton admin-skeleton-chart"></div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Top combo bán chạy</h3>
            </div>
            <div id="chartTopCombos" class="admin-skeleton admin-skeleton-chart"></div>
        </div>
    </div>
</div>

{{-- ── Row 4: Chi tiết combo bán ra ─────────────────────────────────────── --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Doanh thu theo combo</h3>
            </div>
            <div id="chartComboRevenue" class="admin-skeleton admin-skeleton-chart admin-skeleton-chart-lg"></div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
<script src="{{ asset('js/admin/pages/combo_stats.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
