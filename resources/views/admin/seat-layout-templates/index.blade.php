@extends('layouts.admin')

@section('title', 'Quản lý mẫu sơ đồ ghế')
@section('header_title', 'Quản lý mẫu sơ đồ ghế')
@section('header_subtitle', 'Quản lý các mẫu sơ đồ ghế dùng cho phòng chiếu.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold flex-no-shrink">Danh sách mẫu sơ đồ ghế</h5>

        <form id="searchForm" class="flex-grow-1 search-container-lg">
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

        <button type="button" id="btnOpenCreateSeatLayoutTemplate" class="admin-action-btn ms-auto">
            <i class="bi bi-plus-lg"></i> Tạo mẫu sơ đồ ghế
        </button>
    </div>
</div>

{{-- ── Dòng 3: Tabs + Table ─────────────────────────────────────────── --}}
<div class="admin-table-container">
    {{-- Tabs: client-side Bootstrap --}}
    <ul class="nav nav-tabs combo-tabs mb-4" id="sltTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-all" data-status="all" data-bs-toggle="tab" data-bs-target="#pane-table" type="button" role="tab" aria-selected="true">
                Tất cả <span class="badge bg-secondary ms-1" id="count-all">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-published" data-status="published" data-bs-toggle="tab" data-bs-target="#pane-table" type="button" role="tab" aria-selected="false">
                Đã xuất bản <span class="badge bg-secondary ms-1" id="count-published">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-draft" data-status="draft" data-bs-toggle="tab" data-bs-target="#pane-table" type="button" role="tab" aria-selected="false">
                Bản nháp <span class="badge bg-secondary ms-1" id="count-draft">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="sltTabContent">
        <div class="tab-pane fade show active" id="pane-table" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="background:transparent;">
                    <thead style="border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                            <th class="text-secondary fw-semibold border-0">Tên mẫu &amp; Mô tả</th>
                            <th class="text-secondary fw-semibold border-0">Cấu hình lưới (Matrix)</th>
                            <th class="text-secondary fw-semibold border-0">Chi tiết ghế</th>
                            <th class="text-secondary fw-semibold border-0 text-center" style="width: 120px;">Trạng thái</th>
                            <th class="text-center text-secondary fw-semibold border-0" style="width: 150px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="templatesTableBody">
                        <!-- Skeleton Loading Rows -->
                        <tr class="skeleton-row">
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 70%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 65%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 75%;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                        </tr>
                        <tr class="skeleton-row">
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 85%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 60%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 80%;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                        </tr>
                        <tr class="skeleton-row">
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 65%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 70%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 68%;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                        </tr>
                        <tr class="skeleton-row">
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 75%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 55%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 72%;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                        </tr>
                        <tr class="skeleton-row">
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 80%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 68%;"></div></td>
                            <td><div class="admin-skeleton admin-skeleton-text" style="width: 77%;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                            <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);" id="paginationContainer">
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
                    <i class="bi bi-grid-3x3-gap me-2"></i>Tạo mẫu sơ đồ ghế
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
                                <span class="d-inline-block" style="width:10px;height:10px;border-radius:2px;background:#60a5fa;"></span>
                                Ghế thường
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="regularSeatRows" name="regular_seat_rows" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="vipSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                <span class="d-inline-block" style="width:10px;height:10px;border-radius:2px;background:#f59e0b;"></span>
                                Ghế VIP
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="vipSeatRows" name="vip_seat_rows" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="coupleSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                <span class="d-inline-block" style="width:10px;height:10px;border-radius:2px;background:#ec4899;"></span>
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
                    <button type="button" class="btn text-white slt-btn-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="sltSubmitBtn">Lưu mẫu sơ đồ ghế</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/components/skeleton.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/seat-layout-templates.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/admin-modals.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/seat-layout-templates.js') }}?v={{ time() }}" defer></script>
@endpush
