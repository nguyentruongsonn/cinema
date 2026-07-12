@extends('layouts.admin')

@section('title', 'Thống kê Vé bán')
@section('header_title', 'Thống kê Vé bán')
@section('header_subtitle', 'Phân tích lượng vé bán ra, tỉ lệ lấp đầy theo thời gian và rạp chiếu.')

@section('content')

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

    {{-- Card 1: Tổng vé bán ra --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TỔNG VÉ BÁN RA</span>
                <div class="stat-icon"><i class="bi bi-ticket-perforated"></i></div>
            </div>
            <div class="stat-value" id="cardTotalTickets">—</div>
            <div class="stat-trend">
                <span id="cardTotalTicketsTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    {{-- Card 2: Trung bình mỗi ngày --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TRUNG BÌNH MỖI NGÀY</span>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
            <div class="stat-value" id="cardAvgPerDay">—</div>
            <div class="stat-trend">
                <span id="cardAvgPerDayTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>

    {{-- Card 3: Giờ cao điểm bán vé --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">GIỜ CAO ĐIỂM</span>
                <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            </div>
            <div class="stat-value" id="cardPeakHour" style="font-size:1.5rem">—</div>
            <div class="stat-trend mt-2">
                <span class="text-secondary small">thời gian bán nhiều nhất</span>
            </div>
        </div>
    </div>

    {{-- Card 4: Tỉ lệ lấp đầy tổng --}}
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">TỈ LỆ LẤP ĐẦY TRUNG BÌNH</span>
                <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>
            <div class="stat-value" id="cardOccupancyRate" style="font-size:1.5rem">—</div>
            <div class="stat-trend">
                <span id="cardOccupancyRateTrend"><i class="bi bi-dash"></i> 0%</span>
                <span class="trend-text">vs kỳ trước</span>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Xu hướng bán vé & Top Phim ────────────────────────────── --}}
<div class="row g-4 mb-4">
    <div class="col-xl-8 col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Xu hướng lượng vé bán ra</h3>
            </div>
            <div id="chartTicketTrend" style="min-height:300px;"></div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Top phim bán chạy</h3>
            </div>
            <div id="chartTopMovies" style="min-height:300px;"></div>
        </div>
    </div>
</div>

{{-- ── Row 4: Tỉ lệ lấp đầy rạp ─────────────────────────────────────── --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3 class="chart-title">Tỉ lệ lấp đầy theo Rạp</h3>
            </div>
            <div id="chartTheaterOccupancy" style="min-height:350px;"></div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/components/skeleton.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/ticket_stats.js') }}?v={{ time() }}" defer></script>
@endpush
