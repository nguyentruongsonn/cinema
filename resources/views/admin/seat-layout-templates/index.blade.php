@extends('layouts.admin')

@section('title', 'Quản lý mẫu sơ đồ ghế')
@section('header_title', 'Quản lý mẫu sơ đồ ghế')
@section('header_subtitle', 'Quản lý các mẫu sơ đồ ghế dùng cho phòng chiếu.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select filter-select-md">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đã xuất bản</option>
                    <option value="0">Bản nháp</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group">
                <input
                type="text"
                id="search"
                name="search"
                class="admin-filter-input"
                placeholder="Tên mẫu sơ đồ ghế..."
                >
                <button class="admin-filter-btn search-btn-rounded-right" type="submit" aria-label="Tìm kiếm">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" id="btnOpenCreateSeatLayoutTemplate" class="admin-action-btn admin-filter-primary-action">
            <i class="bi bi-plus-lg"></i> Tạo mẫu sơ đồ ghế
        </button>
    </div>
</div>
{{-- ── Dòng 3: Tabs + Table ─────────────────────────────────────────── --}}
<div class="admin-table-container">
    <div class="tab-content" id="sltTabContent">
        <div class="tab-pane fade show active" id="pane-table" role="tabpanel">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-center col-stt">STT</th>
                            <th>Tên mẫu &amp; Mô tả</th>
                            <th>Cấu hình lưới (Matrix)</th>
                            <th>Chi tiết ghế</th>
                            <th class="text-center col-status">Trạng thái</th>
                            <th class="text-center col-actions-lg">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="templatesTableBody">
                        <x-admin.skeleton-table cols="6" rows="5" :hasImage="false" />
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer">
            </div>
        </div>
    </div>
</div>

{{-- ── Modal: Thêm / Sửa Mẫu Sơ Đồ Ghế ─────────────────────────────── --}}
<div class="modal fade" id="seatLayoutTemplateModal" tabindex="-1" aria-labelledby="seatLayoutTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="seatLayoutTemplateModalLabel">
                    <i class="bi bi-grid-3x3-gap me-2 admin-accent-icon"></i>Tạo mẫu sơ đồ ghế mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="seatLayoutTemplateForm">
                <input type="hidden" id="seatLayoutTemplateFormMethod" value="POST">
                <input type="hidden" name="seat_layout_template_id" id="seatLayoutTemplateIdInput" value="">

                <div class="modal-body">
                    <div id="seatLayoutTemplateFormAlert" class="alert alert-danger d-none" role="alert"></div>

                    {{-- Tên mẫu --}}
                    <div class="mb-3">
                        <label for="templateName" class="form-label text-secondary">Tên mẫu sơ đồ ghế <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="templateName" name="template_name" required>
                    </div>

                    {{-- Ma trận ghế (dropdown preset) --}}
                    <div class="mb-3">
                        <label for="seatMatrix" class="form-label text-secondary">
                            Ma trận ghế <span class="text-danger">*</span>
                        </label>
                        <select class="form-select bg-dark text-white border-secondary" id="seatMatrix" name="seat_matrix" required>
                            <option value="">-- Chọn ma trận ghế --</option>
                            <option value="12x12" data-rows="12" data-cols="12" data-capacity="144">12×12 — Sức chứa tối đa 144 chỗ ngồi</option>
                            <option value="13x13" data-rows="13" data-cols="13" data-capacity="169">13×13 — Sức chứa tối đa 169 chỗ ngồi</option>
                            <option value="14x14" data-rows="14" data-cols="14" data-capacity="196">14×14 — Sức chứa tối đa 196 chỗ ngồi</option>
                            <option value="15x15" data-rows="15" data-cols="15" data-capacity="225">15×15 — Sức chứa tối đa 225 chỗ ngồi</option>
                        </select>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <label for="regularSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                                <span class="admin-seat-count-dot admin-seat-count-dot--regular"></span>
                                Ghế thường
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="regularSeatRows" name="regular_seat_rows" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="vipSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                                <span class="admin-seat-count-dot admin-seat-count-dot--vip"></span>
                                Ghế VIP
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="vipSeatRows" name="vip_seat_rows" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="coupleSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                                <span class="admin-seat-count-dot admin-seat-count-dot--couple"></span>
                                Ghế đôi
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="coupleSeatRows" name="couple_seat_rows" min="0" value="0">
                        </div>
                    </div>

                    {{-- Mô tả --}}
                    <div class="mb-3">
                        <label for="description" class="form-label text-secondary">Mô tả</label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="description" name="description" rows="2" placeholder="Mô tả ngắn về mẫu sơ đồ..."></textarea>
                    </div>

                    {{-- Trạng thái --}}
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="templateStatus" name="status" value="1" checked>
                            <label class="form-check-label text-white" for="templateStatus">Cho phép hoạt động</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white slt-btn-cancel admin-modal-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="sltSubmitBtn">Lưu mẫu sơ đồ ghế</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/seat-layout-templates.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/seat-layout-templates.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
