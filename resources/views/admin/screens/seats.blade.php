@extends('layouts.admin')

@section('title', 'Sơ đồ ghế')
@section('header_title', 'Sơ đồ ghế')
@section('header_subtitle', 'Đang tải...')

@section('content')

    {{-- ── Header breadcrumb ───────────────────────────────────────── --}}
    <div class="filter-bar mb-4">
        <div class="filter-bar-inner align-items-center w-100">
            <h5 class="mb-0 text-white fw-bold">
                <i class="bi bi-grid-3x3 me-2" style="color:var(--accent-color);"></i>
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
                <div class="text-center mb-4">
                    <div class="screen-bar mx-auto">MÀN HÌNH CHIẾU</div>
                </div>

                {{-- Loading Spinner --}}
                <div id="seatGridLoading" class="text-center py-5 text-muted">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">Đang tải sơ đồ ghế...</div>
                </div>

                {{-- Grid ghế (JS populated) --}}
                <div id="seatGrid" class="seat-grid mx-auto d-none"></div>

                {{-- Nhãn cột (JS populated) --}}
                <div id="seatGridColLabels" class="seat-grid-col-labels mx-auto mt-2 d-none"></div>
            </div>
        </div>

        {{-- ══ CỘT PHẢI: Panel thông tin & điều khiển ════════════════ --}}
        <div class="col-lg-3 col-md-4">

            {{-- ── Thông tin phòng chiếu ──────────────────────────── --}}
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-info-circle me-1" style="color:var(--accent-color);"></i>Thông tin
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
                    <button type="button" class="btn-primary-custom w-100 border-0 py-2 mt-2 btn-update-layout" id="btnUpdateSeats">
                        <i class="bi bi-save me-2"></i>Cập nhật sơ đồ
                    </button>
                </div>
            </div>

            {{-- ── Toggle hoạt động phòng chiếu ────────────────────── --}}
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-toggles me-1" style="color:var(--accent-color);"></i>Hoạt động
                </h6>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="small text-white">Kích hoạt phòng chiếu</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input toggle-switch-lg" type="checkbox" role="switch" id="screenActiveToggle">
                    </div>
                </div>
            </div>

            {{-- ── Chú thích loại ghế ────────────────────────────────── --}}
            <div class="chart-card p-4 mb-3">
                <h6 class="text-secondary fw-semibold mb-3 text-uppercase card-heading-title">
                    <i class="bi bi-bookmark me-1" style="color:var(--accent-color);"></i>Chú thích
                </h6>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="seat-legend-box seat-standard">
                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="5" y="2" width="14" height="11" rx="2" />
                                <rect x="4" y="14" width="16" height="5" rx="1" />
                                <rect x="2" y="11" width="3" height="8" rx="1" />
                                <rect x="19" y="11" width="3" height="8" rx="1" />
                            </svg>
                        </div>
                        <div>
                            <div class="small fw-medium text-white">Ghế thường</div>
                            <div class="small text-secondary" style="font-size:0.72rem;">Standard</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="seat-legend-box seat-vip">
                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="5" y="2" width="14" height="11" rx="2" />
                                <rect x="4" y="14" width="16" height="5" rx="1" />
                                <rect x="2" y="11" width="3" height="8" rx="1" />
                                <rect x="19" y="11" width="3" height="8" rx="1" />
                            </svg>
                        </div>
                        <div>
                            <div class="small fw-medium text-white">Ghế VIP</div>
                            <div class="small text-secondary" style="font-size:0.72rem;">VIP / Premium</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="seat-legend-box seat-couple">
                            <svg width="2em" height="1em" viewBox="0 0 48 24" fill="currentColor">
                                <rect x="5" y="2" width="38" height="11" rx="2" />
                                <rect x="4" y="14" width="40" height="5" rx="1" />
                                <rect x="2" y="11" width="3" height="8" rx="1" />
                                <rect x="43" y="11" width="3" height="8" rx="1" />
                            </svg>
                        </div>
                        <div>
                            <div class="small fw-medium text-white">Ghế đôi</div>
                            <div class="small text-secondary" style="font-size:0.72rem;">Couple </div>
                        </div>
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
    <link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/seat-layout.css') }}?v={{ time() }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/pages/admin/seat-layout.js') }}?v={{ time() }}" defer></script>
@endpush