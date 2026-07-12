@extends('layouts.admin')

@section('title', 'Thống kê Combo')
@section('header_title', 'Thống kê Combo')
@section('header_subtitle', 'Phân tích doanh thu và lượng combo bán ra theo thời gian.')

@section('content')

{{-- ── Tab Navigation ─────────────────────────────────────────────────── --}}
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="food-tab" data-bs-toggle="tab" data-bs-target="#food-panel" 
                type="button" role="tab" aria-controls="food-panel" aria-selected="true"
                data-type="food">
            <i class="bi bi-cup-straw me-2"></i>Đồ ăn & Nước uống
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="combo-tab" data-bs-toggle="tab" data-bs-target="#combo-panel" 
                type="button" role="tab" aria-controls="combo-panel" aria-selected="false"
                data-type="combo">
            <i class="bi bi-box-seam me-2"></i>Combo
        </button>
    </li>
</ul>

{{-- Shared UI for both tabs - charts/cards rendered below, data changes on tab switch --}}

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
            <div class="stat-value admin-skeleton admin-skeleton-text" id="cardTopCombo" style="font-size:1.2rem"></div>
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
            <div id="chartComboTrend" class="admin-skeleton" style="min-height:300px;"></div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Top combo bán chạy</h3>
            </div>
            <div id="chartTopCombos" class="admin-skeleton" style="min-height:300px;"></div>
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
            <div id="chartComboRevenue" class="admin-skeleton" style="min-height:350px;"></div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/components/skeleton.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/combo_stats.js') }}?v={{ time() }}" defer></script>
@endpush
