@extends('layouts.admin')

@section('title', 'Thống kê Combo & Đồ ăn')
@section('header_title', 'Thống kê Combo & Đồ ăn')
@section('header_subtitle', 'Phân tích doanh thu và số lượng combo, thức ăn bán ra theo thời gian.')

@section('content')

{{-- ── Tabs ──────────────────────────────────────────────────────────── --}}
<ul class="nav nav-tabs combo-tabs mb-4" id="comboTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-combo" data-bs-toggle="tab" data-bs-target="#pane-combo" type="button" role="tab">
            <i class="bi bi-box-seam me-2"></i>Thống kê Combo
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-food" data-bs-toggle="tab" data-bs-target="#pane-food" type="button" role="tab">
            <i class="bi bi-cup-straw me-2"></i>Thống kê Đồ ăn
        </button>
    </li>
</ul>

<div class="tab-content" id="comboTabsContent">

    {{-- ════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1: Thống kê Combo                                           --}}
    {{-- ════════════════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade show active" id="pane-combo" role="tabpanel">

        {{-- Row 1: Filter --}}
        <div class="filter-bar mb-4">
            <div class="filter-bar-inner">
                <div class="filter-group">
                    <label for="comboFilterStart" class="filter-label">Từ ngày</label>
                    <input type="date" id="comboFilterStart" class="filter-input" />
                </div>
                <div class="filter-group">
                    <label for="comboFilterEnd" class="filter-label">Đến ngày</label>
                    <input type="date" id="comboFilterEnd" class="filter-input" />
                </div>
                <div class="filter-shortcuts d-flex gap-2" id="comboShortcuts">
                    <button class="btn-shortcut active" data-range="week">Tuần này</button>
                    <button class="btn-shortcut" data-range="month">Tháng này</button>
                    <button class="btn-shortcut" data-range="quarter">Quý này</button>
                    <button class="btn-shortcut" data-range="year">Năm nay</button>
                </div>
                <button id="btnComboApply" class="btn-primary-custom ms-auto">
                    <i class="bi bi-arrow-clockwise"></i> Cập nhật
                </button>
            </div>
        </div>

        {{-- Row 2: Stat Cards --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">TỔNG TIỀN BÁN COMBO</span>
                        <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                    </div>
                    <div class="stat-value skeleton skeleton-text" id="cardComboRevenue"></div>
                    <div class="stat-trend mt-2">
                        <span class="text-secondary small">tổng doanh thu</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">TỔNG SỐ COMBO BÁN RA</span>
                        <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                    </div>
                    <div class="stat-value skeleton skeleton-text" id="cardComboQty"></div>
                    <div class="stat-trend mt-2">
                        <span class="text-secondary small">combo đã bán</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-12">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">COMBO BÁN CHẠY NHẤT</span>
                        <div class="stat-icon"><i class="bi bi-trophy"></i></div>
                    </div>
                    <div class="stat-value skeleton skeleton-text" id="cardBestComboName" style="font-size:1.4rem"></div>
                    <div class="stat-trend mt-2">
                        <span class="skeleton skeleton-text" id="cardBestComboQty"></span>
                        <span class="text-secondary small ms-1">combo đã bán</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 3: Pie (doanh thu theo rạp) + Bar (top combo doanh thu) --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-5 col-lg-5">
                <div class="chart-card h-100">
                    <div class="chart-header">
                        <h3 class="chart-title">Tỉ lệ doanh thu theo Rạp</h3>
                    </div>
                    <div id="chartComboTheaterPie" class="skeleton skeleton-chart" style="min-height:300px;"></div>
                </div>
            </div>
            <div class="col-xl-7 col-lg-7">
                <div class="chart-card h-100">
                    <div class="chart-header">
                        <h3 class="chart-title">Top Combo có Doanh thu cao nhất</h3>
                    </div>
                    <div id="chartTopComboBar" class="skeleton skeleton-chart" style="min-height:300px;"></div>
                </div>
            </div>
        </div>

    </div>{{-- end #pane-combo --}}


    {{-- ════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: Thống kê Đồ ăn                                          --}}
    {{-- ════════════════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade" id="pane-food" role="tabpanel">

        {{-- Row 1: Filter + Loại đồ ăn --}}
        <div class="filter-bar mb-4">
            <div class="filter-bar-inner">
                <div class="filter-group">
                    <label for="foodFilterStart" class="filter-label">Từ ngày</label>
                    <input type="date" id="foodFilterStart" class="filter-input" />
                </div>
                <div class="filter-group">
                    <label for="foodFilterEnd" class="filter-label">Đến ngày</label>
                    <input type="date" id="foodFilterEnd" class="filter-input" />
                </div>
                <div class="filter-group">
                    <label for="foodTypeFilter" class="filter-label">Loại sản phẩm</label>
                    <select id="foodTypeFilter" class="filter-input" style="padding-top:0.45rem;padding-bottom:0.45rem;">
                        <option value="">Tất cả</option>
                        <option value="popcorn">Bắp rang</option>
                        <option value="drink">Đồ uống</option>
                        <option value="snack">Đồ ăn nhẹ</option>
                    </select>
                </div>
                <div class="filter-shortcuts d-flex gap-2" id="foodShortcuts">
                    <button class="btn-shortcut active" data-range="week">Tuần này</button>
                    <button class="btn-shortcut" data-range="month">Tháng này</button>
                    <button class="btn-shortcut" data-range="quarter">Quý này</button>
                    <button class="btn-shortcut" data-range="year">Năm nay</button>
                </div>
                <button id="btnFoodApply" class="btn-primary-custom ms-auto">
                    <i class="bi bi-arrow-clockwise"></i> Cập nhật
                </button>
            </div>
        </div>

        {{-- Row 2: Stat Cards --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">TỔNG SẢN PHẨM ĐÃ BÁN</span>
                        <div class="stat-icon"><i class="bi bi-bag-check"></i></div>
                    </div>
                    <div class="stat-value skeleton skeleton-text" id="cardFoodQty"></div>
                    <div class="stat-trend mt-2">
                        <span class="text-secondary small">sản phẩm đã bán</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">SẢN PHẨM BÁN CHẠY NHẤT</span>
                        <div class="stat-icon"><i class="bi bi-fire"></i></div>
                    </div>
                    <div class="stat-value skeleton skeleton-text" id="cardFoodBestQtyName" style="font-size:1.3rem"></div>
                    <div class="stat-trend mt-2">
                        <span class="skeleton skeleton-text" id="cardFoodBestQtyVal"></span>
                        <span class="text-secondary small ms-1">sản phẩm</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-12">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">DOANH THU CAO NHẤT</span>
                        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                    <div class="stat-value skeleton skeleton-text" id="cardFoodBestRevName" style="font-size:1.3rem"></div>
                    <div class="stat-trend mt-2">
                        <span class="skeleton skeleton-text" id="cardFoodBestRevVal"></span>
                        <span class="text-secondary small ms-1">doanh thu</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 3: Pie (tỉ lệ loại) + Bar (top sản phẩm số lượng) --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-5 col-lg-5">
                <div class="chart-card h-100">
                    <div class="chart-header">
                        <h3 class="chart-title">Tỉ lệ Bắp rang / Đồ uống / Đồ ăn nhẹ</h3>
                    </div>
                    <div id="chartFoodTypePie" class="skeleton skeleton-chart" style="min-height:300px;"></div>
                </div>
            </div>
            <div class="col-xl-7 col-lg-7">
                <div class="chart-card h-100">
                    <div class="chart-header">
                        <h3 class="chart-title">Số lượng bán ra của các Sản phẩm bán chạy nhất</h3>
                    </div>
                    <div id="chartFoodTopQtyBar" class="skeleton skeleton-chart" style="min-height:300px;"></div>
                </div>
            </div>
        </div>

        {{-- Row 4: Horizontal bar – doanh thu theo sản phẩm --}}
        <div class="row g-4">
            <div class="col-12">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Doanh thu theo Sản phẩm bán chạy nhất</h3>
                    </div>
                    <div id="chartFoodRevenueBar" class="skeleton skeleton-chart" style="min-height:340px;"></div>
                </div>
            </div>
        </div>

    </div>{{-- end #pane-food --}}

</div>{{-- end .tab-content --}}

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/combo_stats.js') }}?v={{ time() }}" defer></script>
@endpush
