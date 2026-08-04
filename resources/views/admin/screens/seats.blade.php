@extends('layouts.admin')

@section('title', 'Sơ đồ ghế')
@section('header_title', 'Sơ đồ ghế')
@section('header_subtitle', 'Đang tải...')

@section('content')

    {{-- ── Header breadcrumb ───────────────────────────────────────── --}}
    <div class="filter-bar mb-4">
        <div class="filter-bar-inner align-items-center w-100">
            <h5 class="mb-0 text-white fw-bold">
                <i class="bi bi-grid-3x3 me-2 admin-accent-icon"></i>
                <span id="screenNameTitle">Đang tải...</span>
            </h5>
            <span class="ms-2 badge bg-secondary" id="screenCodeBadge">--</span>
            <span class="ms-2 small text-white-50" id="theaterNameSpan"></span>

            <a href="{{ route('admin.screens.index') }}"
                class="btn btn-sm ms-auto d-inline-flex align-items-center text-white btn-header-back">
                <i class="bi bi-arrow-return-left me-2"></i> Quay lại
            </a>
        </div>
    </div>

    {{-- ── Layout 2 cột ──────────────────────────────────────────────── --}}
    <div class="row g-4 align-items-start">

        {{-- ══ CỘT TRÁI: Sơ đồ ghế ══════════════════════════════════ --}}
        <div class="col-lg-9 col-md-8">
            <div class="chart-card p-4 overflow-x-auto">
                {{-- Màn hình chiếu --}}
                <div class="screen-display">
                    <div class="screen-label">MÀN HÌNH CHIẾU</div>
                </div>

                {{-- Seat Map --}}
                <div id="seatMapContainer" class="seat-map-container">
                    {{-- Skeleton Loading --}}
                    <div class="seat-map-skeleton">
                        <div class="skeleton-rows">
                            <div class="skeleton-row" v-for="i in 10">
                                <div class="skeleton-seat" v-for="j in 15"></div>
                            </div>
                        </div>
                    </div>
                    {{-- Actual seat map rendered by JS --}}
                    <div id="seatGrid" class="seat-grid mx-auto d-none"></div>
                    {{-- Column labels (populated by JS) --}}
                    <div id="seatGridColLabels" class="seat-grid-col-labels mx-auto mt-2 d-none"></div>
                </div>

                {{-- Seat Legend - Giống hệt Client --}}
                <div class="seat-legend">
                    <div class="legend-item">
                        <span class="seat-demo seat-available"></span>
                        <span>Ghế trống</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-selected"></span>
                        <span>Đang chọn</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-vip"></span>
                        <span>Ghế VIP</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-disabled-legend"></span>
                        <span>Ghế hỏng / Đã bán</span>
                    </div>
                    <div class="legend-item">
                        <span class="seat-demo seat-couple"></span>
                        <span>Ghế đôi</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ CỘT PHẢI: Panel thông tin & điều khiển ════════════════ --}}
        <div class="col-lg-3 col-md-4">

            {{-- ── Thông tin phòng chiếu ──────────────────────────── --}}
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-info-circle me-1 admin-accent-icon"></i>Thông tin
                </h6>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Rạp chiếu</span>
                        <span class="small text-white fw-medium" id="infoTheater">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Loại phòng</span>
                        <span class="badge badge-soft-primary" id="infoFormat">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Âm thanh</span>
                        <span class="badge badge-soft-purple" id="infoSound">--</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Ma trận</span>
                        <span class="small text-white fw-semibold" id="infoMatrix">--</span>
                    </div>
                    <hr class="opacity-25 my-1">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="small text-secondary">Sức chứa</span>
                        <span class="small text-white">
                            <strong class="text-white" id="capacityCounter">0</strong>
                            / <span id="totalSeatsCounter">0</span> chỗ
                        </span>
                    </div>

                    {{-- Button Cập nhật sơ đồ --}}
                    <button type="button" class="btn-primary-custom w-100 border-0 py-2 mt-2 btn-update-layout"
                        id="btnUpdateSeats">
                        <i class="bi bi-save me-2"></i>Cập nhật sơ đồ
                    </button>
                </div>
            </div>

            {{-- ── Toggle hoạt động phòng chiếu ────────────────────── --}}
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-toggles me-1 admin-accent-icon"></i>Hoạt động
                </h6>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="small text-white">Kích hoạt phòng chiếu</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input toggle-switch-lg" type="checkbox" role="switch"
                            id="screenActiveToggle">
                    </div>
                </div>
            </div>



        </div>
    </div>{{-- end .row --}}

    {{-- Configuration for JS --}}
    <script>
        window.SEAT_LAYOUT_CONFIG = {
            screenId: @json($screenId)
        };
    </script>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/seat-layout.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/admin/pages/seat-layout.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
