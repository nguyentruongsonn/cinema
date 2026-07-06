@extends('layouts.admin')

@section('title', 'Quản lý Combo')
@section('header_title', 'Quản lý Combo')
@section('header_subtitle', 'Quản lý các combo đồ ăn và nước uống.')

@section('content')

{{-- ── Filter Bar ─────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3 flex-wrap">
        <h5 class="mb-0 text-white fw-bold combo-page-title">
            Danh sách Combo
        </h5>

        <form id="searchForm" class="flex-grow-1 combo-search-form">
            <div class="input-group">
                <input type="text" id="search" name="search" class="admin-filter-input combo-search-input" placeholder="Tên combo...">
                <button class="admin-filter-btn combo-search-btn" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <select id="statusFilter" class="admin-filter-select combo-status-filter">
            <option value="all">Tất cả trạng thái</option>
            <option value="active">Đang bán</option>
            <option value="inactive">Ngừng bán</option>
        </select>

        <button type="button" id="btnOpenCreateCombo" class="admin-action-btn ms-auto">
            <i class="bi bi-plus-lg"></i> Thêm Combo
        </button>
    </div>
</div>

{{-- ── Table ─────────────────────────────────────────────────── --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center combo-col-stt">STT</th>
                    <th class="combo-col-image">Hình ảnh</th>
                    <th>Tên combo</th>
                    <th>Tồn kho</th>
                    <th>Giá gốc</th>
                    <th>Giá bán</th>
                    <th class="text-center combo-col-status">Trạng thái</th>
                    <th class="text-center combo-col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="combosTableBody">
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

    <div class="d-flex justify-content-end mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- ── Modal: Thêm / Sửa Combo ─────────────────────────────── --}}
<div class="modal fade" id="comboModal" tabindex="-1" aria-labelledby="comboModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="comboModalLabel">
                    <i class="bi bi-box-seam me-2"></i>Thêm combo mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="comboForm">
                <input type="hidden" id="comboFormMethod" value="POST">
                <input type="hidden" name="combo_id" id="comboIdInput" value="">

                <div class="modal-body">
                    <div class="row g-4">
                        {{-- Cột trái: Thông tin combo --}}
                        <div class="col-md-7">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="comboName" class="form-label text-secondary">Tên combo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" id="comboName" name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="comboPrice" class="form-label text-secondary">Giá bán (VNĐ) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control bg-dark text-white border-secondary" id="comboPrice" name="price" min="0" step="1000" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="comboDescription" class="form-label text-secondary">Mô tả chi tiết</label>
                                <textarea class="form-control bg-dark text-white border-secondary" id="comboDescription" name="description" rows="3" placeholder="Mô tả combo..."></textarea>
                            </div>

                            <hr class="border-secondary">

                            <div class="mb-3">
                                <label class="form-label text-secondary">Sản phẩm trong combo <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2 mb-2">
                                    <select id="availableProducts" class="form-select bg-dark text-white border-secondary flex-grow-1">
                                        <option value="">-- Chọn sản phẩm --</option>
                                    </select>
                                    <button type="button" id="btnAddComboItem" class="btn-primary-custom border-0">
                                        <i class="bi bi-plus-lg"></i> Thêm
                                    </button>
                                </div>
                            </div>

                            <div id="comboItemsContainer" class="border border-secondary rounded p-3 combo-items-container">
                                <div id="emptyComboItems" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    <p class="mb-0">Chưa có sản phẩm nào trong combo</p>
                                    <small>Thêm sản phẩm bằng nút "Thêm" bên trên</small>
                                </div>
                                <div id="comboItemsList"></div>
                            </div>

                            <div class="alert alert-info mt-3 mb-0 combo-stock-alert">
                                <i class="bi bi-info-circle me-2"></i>
                                <small>Tồn kho combo sẽ được tính tự động dựa trên tồn kho của các sản phẩm bên trong.</small>
                            </div>
                        </div>

                        {{-- Cột phải: Hình ảnh và trạng thái --}}
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label text-secondary">Hình ảnh combo</label>
                                <div class="poster-upload-box combo-image-upload-box mb-2" id="imageUploadBox">
                                    <img id="imagePreview" class="combo-image-preview" src="" alt="Preview">
                                    <div id="imagePlaceholder" class="text-center text-white-50 p-3">
                                        <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>
                                        <div class="small fw-semibold">Kéo thả hoặc click để chọn</div>
                                        <div class="combo-upload-hint">JPG, PNG, WEBP · Tối đa 5MB</div>
                                    </div>
                                    <input type="file" id="comboImageFile" name="image_file"
                                           accept="image/jpeg,image/png,image/webp"
                                           class="combo-file-input">
                                </div>
                                <button type="button" id="clearImageBtn" class="btn btn-sm w-100 d-none combo-clear-image-btn">
                                    <i class="bi bi-x me-1"></i>Xóa ảnh
                                </button>
                            </div>

                            <hr class="border-secondary">

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label text-secondary mb-0">Trạng thái xuất bản</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="comboStatus" name="status" value="1" checked>
                                    <label class="form-check-label text-white small" for="comboStatus" id="comboStatusLabel">Đang bán</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="comboSubmitBtn">Lưu combo</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/admin-common.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/admin-modals.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/combos.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script>
    window.ADMIN_COMBO_PAGE = {
        createTitle: 'Thêm combo mới',
        editTitle: 'Cập nhật combo',
        createSuccess: 'Thêm combo thành công!',
        updateSuccess: 'Cập nhật combo thành công!',
    };
</script>
<script src="{{ asset('js/pages/admin/combos.js') }}?v={{ time() }}" defer></script>
@endpush