@extends('layouts.admin')

@section('title', 'Overview')
@section('header_title', 'Overview')

@section('topbar_action')
    <button class="btn-primary-custom">
        <i class="bi bi-plus-lg"></i> New Screening
    </button>
@endsection

@section('content')

{{-- Date Range Filter --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner">
        <div class="filter-group">
            <label for="dashboardFilterStart" class="filter-label">Từ ngày</label>
            <input type="date" id="dashboardFilterStart" class="filter-input" />
        </div>
        <div class="filter-group">
            <label for="dashboardFilterEnd" class="filter-label">Đến ngày</label>
            <input type="date" id="dashboardFilterEnd" class="filter-input" />
        </div>
        <div class="filter-shortcuts d-flex gap-2">
            <button class="btn-shortcut active" data-range="week">Tuần này</button>
            <button class="btn-shortcut" data-range="month">Tháng này</button>
            <button class="btn-shortcut" data-range="quarter">Quý này</button>
            <button class="btn-shortcut" data-range="year">Năm nay</button>
        </div>
        <button id="dashboardBtnApply" class="btn-primary-custom ms-auto">
            <i class="bi bi-arrow-clockwise"></i> Cập nhật
        </button>
    </div>
</div>

<!-- Stats Cards Row -->
<div class="row g-4 mb-4">
    <!-- TOTAL REVENUE -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">DOANH THU</span>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="stat-value" id="statRevenue">0₫</div>
            <div class="stat-trend">
                <span id="statRevenueTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    <!-- TICKETS SOLD -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TỔNG VÉ</span>
                <div class="stat-icon"><i class="bi bi-ticket-perforated"></i></div>
            </div>
            <div class="stat-value" id="statTickets">0</div>
            <div class="stat-trend">
                <span id="statTicketsTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    <!-- NEW USERS -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">KHÁCH MỚI</span>
                <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
            </div>
            <div class="stat-value" id="statNewUsers">0</div>
            <div class="stat-trend">
                <span id="statUsersTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    <!-- RETENTION RATE -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TỈ LỆ QUAY LẠI</span>
                <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
            </div>
            <div class="stat-value" id="statRetention">0%</div>
            <div class="custom-progress-wrapper mt-3">
                <div class="custom-progress">
                            <div class="custom-progress-bar" id="statRetentionProgress"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Chart Row -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="admin-table-container">
            <div class="chart-header">
                <h3 class="chart-title">Doanh Thu Theo Thời Gian</h3>
            </div>
            <div id="revenueChart" class="admin-chart-h-300"></div>
        </div>
    </div>
</div>

<!-- Heatmap Row -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="admin-table-container">
            <div class="chart-header">
                <h3 class="chart-title">Lượng Khách Hàng Theo Giờ & Tuần</h3>
            </div>
            <div id="trafficHeatmap" class="admin-chart-h-350"></div>
        </div>
    </div>
</div>

<!-- Top Movies Row -->
<div class="row">
    <div class="col-12">
        <div class="section-header">
            <h3 class="section-title">Top Phim Doanh Thu Cao Nhất</h3>
        </div>
    </div>
</div>

<div class="row g-4" id="topMoviesContainer">
    <!-- Thẻ phim sẽ được inject từ JS -->
    <div class="col-12 text-center py-5">
        <div class="spinner-border text-danger" role="status">
            <span class="visually-hidden">Đang tải...</span>
        </div>
    </div>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboard-redesign.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
<script src="{{ asset('js/admin/pages/dashboard.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
