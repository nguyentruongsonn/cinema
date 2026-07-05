@extends('layouts.admin')

@section('title', 'Quản lý phòng chiếu')
@section('header_title', 'Quản lý phòng chiếu')
@section('header_subtitle', 'Cấu hình phòng chiếu, định dạng chiếu và định dạng âm thanh.')

@section('content')

@if(session('success'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.showAdminToast?.(@json(session('success')), 'success');
            });
        </script>
    @endpush
@endif

@if(session('error'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.showAdminToast?.(@json(session('error')), 'error');
            });
        </script>
    @endpush
@endif

{{-- ── Dòng 1: Header ─────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0;">
            <i class="bi bi-display me-2"></i>Danh sách phòng chiếu
        </h5>
        
        {{-- Search --}}
        <form id="screenSearchForm" class="flex-grow-1" style="max-width: 500px;">
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="admin-filter-input" placeholder="Tìm tên phòng, mã phòng..." style="border-radius: 8px 0 0 8px;">
                <button class="admin-filter-btn" style="border-radius: 0 8px 8px 0;" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        {{-- Buttons injected by JS per tab --}}
        <div id="tabHeaderActions" class="ms-auto"></div>
    </div>
</div>

{{-- ── Dòng 2: Tabs + Content ─────────────────────────────────────── --}}
<div class="admin-table-container">
    <ul class="nav nav-tabs combo-tabs mb-4" id="screenTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-screens" data-bs-toggle="tab" data-bs-target="#pane-screens" type="button" role="tab" aria-controls="pane-screens" aria-selected="true">
                <i class="bi bi-grid-3x3-gap me-1"></i> Phòng chiếu
                <span class="badge bg-secondary ms-1" id="count-screens">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-formats" data-bs-toggle="tab" data-bs-target="#pane-formats" type="button" role="tab" aria-controls="pane-formats" aria-selected="false">
                <i class="bi bi-camera-reels me-1"></i> Định dạng chiếu
                <span class="badge bg-secondary ms-1" id="count-formats">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-sounds" data-bs-toggle="tab" data-bs-target="#pane-sounds" type="button" role="tab" aria-controls="pane-sounds" aria-selected="false">
                <i class="bi bi-volume-up me-1"></i> Định dạng âm thanh
                <span class="badge bg-secondary ms-1" id="count-sounds">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="screenTabContent">

        <div class="tab-pane fade show active" id="pane-screens" role="tabpanel" aria-labelledby="tab-screens">

            {{-- Bảng danh sách --}}
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                    <thead style="border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th class="text-center text-secondary fw-semibold border-0" style="width:60px;">STT</th>
                            <th class="text-secondary fw-semibold border-0">Tên phòng</th>
                            <th class="text-secondary fw-semibold border-0">Rạp chiếu</th>
                            <th class="text-secondary fw-semibold border-0">Loại phòng</th>
                            <th class="text-secondary fw-semibold border-0">Sức chứa</th>
                            <th class="text-secondary fw-semibold border-0">Trạng thái</th>
                            <th class="text-center text-secondary fw-semibold border-0">Hoạt động</th>
                            <th class="text-center text-secondary fw-semibold border-0" style="width:140px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="screensTableBody">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div class="spinner-border text-secondary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);" id="paginationContainer">
            </div>
        </div>

        {{-- ── TAB 2: ĐỊNH DẠNG CHIẾU ─────────────────────────────── --}}
        <div class="tab-pane fade" id="pane-formats" role="tabpanel" aria-labelledby="tab-formats">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="background:transparent;">
                    <thead style="border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th class="text-center text-secondary fw-semibold border-0" style="width:60px;">STT</th>
                            <th class="text-secondary fw-semibold border-0">Tên định dạng</th>
                            <th class="text-secondary fw-semibold border-0">Phụ thu vé</th>
                            <th class="text-center text-secondary fw-semibold border-0" style="width:140px;">Hành động</th>
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
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="background:transparent;">
                    <thead style="border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th class="text-center text-secondary fw-semibold border-0" style="width:60px;">STT</th>
                            <th class="text-secondary fw-semibold border-0">Tên định dạng âm thanh</th>
                            <th class="text-center text-secondary fw-semibold border-0" style="width:140px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="soundsTableBody">
                        <!-- JS populated -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- end .tab-content --}}
</div>{{-- end .chart-card --}}


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
                            <label class="form-label text-secondary">Định dạng chiếu <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-white border-secondary" id="screenFormat" name="format_id" required>
                                <option value="">-- Chọn định dạng --</option>
                                <!-- JS populated -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Âm thanh <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-white border-secondary" id="screenSound" name="sound_id" required>
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
                            <input class="form-check-input" type="checkbox" id="screenStatus" name="status" value="1" checked style="cursor:pointer;">
                            <label class="form-check-label text-white" for="screenStatus">Kích hoạt hoạt động</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" style="background:rgba(255,255,255,0.1);" data-bs-dismiss="modal">Hủy bỏ</button>
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
                    <i class="bi bi-camera-reels me-2" style="color:var(--accent-color);"></i>Thêm định dạng chiếu
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
                    <button type="button" class="btn text-white" style="background:rgba(255,255,255,0.1);" data-bs-dismiss="modal">Hủy bỏ</button>
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
                    <i class="bi bi-volume-up me-2" style="color:var(--accent-color);"></i>Thêm định dạng âm thanh
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn text-white" style="background:rgba(255,255,255,0.1);" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu âm thanh</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/screens.js') }}?v={{ time() }}" defer></script>
@endpush
