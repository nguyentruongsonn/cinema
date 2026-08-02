@extends('layouts.admin')

@section('title', 'Quản lý phòng chiếu')
@section('header_title', 'Quản lý phòng chiếu')
@section('header_subtitle', 'Cấu hình phòng chiếu, định dạng chiếu và định dạng âm thanh.')

@section('content')

@if(session('success'))
    @push('scripts')
        <script>
            window.onAdminPageLoad(function () {
                window.showAdminToast?.(@json(session('success')), 'success');
            });
        </script>
    @endpush
@endif

@if(session('error'))
    @push('scripts')
        <script>
            window.onAdminPageLoad(function () {
                window.showAdminToast?.(@json(session('error')), 'error');
            });
        </script>
    @endpush
@endif

{{-- ── Dòng 1: Header ─────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="branchFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả chi nhánh</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="theaterFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả rạp</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select filter-select-md">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>
        </div>

        <form id="screenSearchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm tên phòng, mã phòng...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        {{-- Buttons injected by JS per tab --}}
        <div id="tabHeaderActions" class="admin-filter-primary-action"></div>
    </div>
</div>

{{-- ── Dòng 2: Tabs + Content ─────────────────────────────────────── --}}
<div class="admin-table-container">
    <ul class="nav nav-tabs combo-tabs mb-4" id="screenTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-screens" data-bs-toggle="tab" data-bs-target="#pane-screens" type="button" role="tab" aria-controls="pane-screens" aria-selected="true">
                Phòng chiếu
                <span class="badge ms-1" id="count-screens">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-formats" data-bs-toggle="tab" data-bs-target="#pane-formats" type="button" role="tab" aria-controls="pane-formats" aria-selected="false">
                Định dạng chiếu
                <span class="badge ms-1" id="count-formats">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-sounds" data-bs-toggle="tab" data-bs-target="#pane-sounds" type="button" role="tab" aria-controls="pane-sounds" aria-selected="false">
                Định dạng âm thanh
                <span class="badge ms-1" id="count-sounds">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="screenTabContent">

        <div class="tab-pane fade show active" id="pane-screens" role="tabpanel" aria-labelledby="tab-screens">

            {{-- Bảng danh sách --}}
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-center col-stt">STT</th>
                            <th>Tên phòng</th>
                            <th>Rạp chiếu</th>
                            <th>Định dạng</th>
                            <th>Sức chứa</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Hoạt động</th>
                            <th class="text-center col-actions">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="screensTableBody">
                        <x-admin.skeleton-table cols="8" rows="5" :hasImage="false" />
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer">
            </div>
        </div>

        {{-- ── TAB 2: ĐỊNH DẠNG CHIẾU ─────────────────────────────── --}}
        <div class="tab-pane fade" id="pane-formats" role="tabpanel" aria-labelledby="tab-formats">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-center col-stt">STT</th>
                            <th>Tên định dạng</th>
                            <th>Phụ thu vé</th>
                            <th class="text-center col-actions">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="formatsTableBody">
                        <!-- JS populated -->
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB 3: ĐỊNH DẠNG ÂM THANH ─────────────────────────── --}}
        <div class="tab-pane fade" id="pane-sounds" role="tabpanel" aria-labelledby="tab-sounds">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-center col-stt">STT</th>
                            <th>Tên định dạng âm thanh</th>
                            <th class="text-center col-actions">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="soundsTableBody">
                        <!-- JS populated -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- end .tab-content --}}
</div>{{-- end .admin-table-container --}}


{{-- ── MODAL: PHÒNG CHIẾU ────────────────────────────────────────── --}}
<div class="modal fade" id="screenModal" tabindex="-1" aria-labelledby="screenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="screenModalLabel">
                    <i class="bi bi-display me-2" style="color:var(--accent-color);"></i>Tạo phòng chiếu mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="screenForm">
                <input type="hidden" id="screenFormMethod" value="POST">
                <input type="hidden" name="screen_id" id="screenIdInput" value="">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <label class="form-label text-secondary">Tên phòng <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="screenName" name="name" placeholder="VD: Phòng chiếu 1" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-secondary">Mã phòng <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="screenCode" name="code" placeholder="VD: P01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Rạp chiếu <span class="text-danger">*</span></label>
                        <select class="form-select bg-dark text-white border-secondary" id="screenTheater" name="theater_id" required>
                            <option value="">-- Chọn rạp --</option>
                            <!-- JS populated -->
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Định dạng chiếu</label>
                            <select class="form-select bg-dark text-white border-secondary" id="screenFormat" name="format_id">
                                <option value="">-- Chọn định dạng --</option>
                                <!-- JS populated -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Âm thanh</label>
                            <select class="form-select bg-dark text-white border-secondary" id="screenSound" name="sound_id">
                                <option value="">-- Chọn âm thanh --</option>
                                <!-- JS populated -->
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Mẫu sơ đồ ghế <span class="text-danger">*</span></label>
                        <select class="form-select bg-dark text-white border-secondary" id="screenTemplate" name="seat_layout_template_id" required>
                            <option value="">-- Chọn mẫu --</option>
                            <!-- JS populated -->
                        </select>
                        <div id="templateDetailBadges" class="mt-2 d-none" style="font-size:0.82rem;">
                            <span class="badge me-1" style="background:rgba(96,165,250,0.12);color:#60a5fa;">Thường: <span id="tplRegular">0</span> hàng</span>
                            <span class="badge me-1" style="background:rgba(245,158,11,0.12);color:#f59e0b;">VIP: <span id="tplVip">0</span> hàng</span>
                            <span class="badge me-1" style="background:rgba(236,72,153,0.12);color:#ec4899;">Đôi: <span id="tplCouple">0</span> hàng</span>
                        </div>
                        <div id="templateEditWarning" class="mt-1 small text-warning d-none">
                            <i class="bi bi-exclamation-triangle me-1"></i>Đổi mẫu sẽ tái tạo toàn bộ sơ đồ ghế!
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="screenStatus" name="status" value="1" checked style="cursor: pointer;">
                            <label class="form-check-label text-white" for="screenStatus">Kích hoạt hoạt động</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu phòng chiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── MODAL: ĐỊNH DẠNG CHIẾU ─────────────────────────────────────── --}}
<div class="modal fade" id="formatModal" tabindex="-1" aria-labelledby="formatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="formatModalLabel">
                    <i class="bi bi-camera-reels me-2" style="color:var(--accent-color);"></i>Tạo định dạng chiếu mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formatForm">
                <input type="hidden" id="formatFormMethod" value="POST">
                <input type="hidden" name="format_id" id="formatIdInput" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Tên định dạng <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="formatName" name="name" placeholder="VD: 3D, IMAX, 4DX" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-secondary">Phụ thu vé (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control bg-dark text-white border-secondary" id="formatSurcharge" name="surcharge" placeholder="VD: 30000" min="0" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu định dạng</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── MODAL: ĐỊNH DẠNG ÂM THANH ──────────────────────────────────── --}}
<div class="modal fade" id="soundModal" tabindex="-1" aria-labelledby="soundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="soundModalLabel">
                    <i class="bi bi-volume-up me-2" style="color:var(--accent-color);"></i>Tạo định dạng âm thanh mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="soundForm">
                <input type="hidden" id="soundFormMethod" value="POST">
                <input type="hidden" name="sound_id" id="soundIdInput" value="">
                <div class="modal-body">
                    <div class="mb-0">
                        <label class="form-label text-secondary">Tên định dạng âm thanh <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="soundName" name="name" placeholder="VD: Dolby Atmos 7.1" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu âm thanh</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/screens.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
