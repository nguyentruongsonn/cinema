@extends('layouts.admin')

@section('title', 'Overview')
@section('header_title', 'Overview')
@section('header_subtitle', "Welcome back. Here's what's happening today.")

@section('topbar_action')
    <button class="btn-primary-custom">
        <i class="bi bi-plus-lg"></i> New Screening
    </button>
@endsection

@section('content')
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
                <span class="trend-text">vs tháng trước</span>
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
                <span class="trend-text">vs tháng trước</span>
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
                <span class="trend-text">vs tháng trước</span>
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
                    <div class="custom-progress-bar" id="statRetentionProgress" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Chart Row -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Doanh Thu Theo Thời Gian</h3>
                <select id="revenueFilter" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;">
                    <option value="week">Tuần này</option>
                    <option value="month" selected>Tháng này</option>
                    <option value="year">Năm nay</option>
                </select>
            </div>
            <div id="revenueChart" style="min-height: 300px;"></div>
        </div>
    </div>
</div>

<!-- Heatmap Row -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Lượng Khách Hàng Theo Giờ & Tuần</h3>
            </div>
            <div id="trafficHeatmap" style="min-height: 350px;"></div>
        </div>
    </div>
</div>

<!-- Top Movies Row -->
<div class="row">
    <div class="col-12">
        <div class="section-header">
            <h3 class="section-title">Top Phim Doanh Thu Cao Nhất</h3>
            <select id="topMoviesFilter" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;">
                <option value="week">Tuần này</option>
                <option value="month" selected>Tháng này</option>
                <option value="year">Năm nay</option>
            </select>
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

@push('scripts')
<script src="{{ asset('js/pages/admin/dashboard.js') }}?v={{ time() }}" defer></script>
@endpush
