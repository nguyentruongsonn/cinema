@extends('layouts.admin')

@section('title', 'Quản lý sản phẩm')
@section('header_title', 'Quản lý sản phẩm')

@section('content')

{{-- ── Filter Bar ─────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3 flex-wrap">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0;">
            Danh sách Sản phẩm
        </h5>

        <form id="searchForm" class="flex-grow-1" style="max-width: 400px;">
            <div class="input-group">
                <input type="text" id="search" name="search" class="admin-filter-input" placeholder="Tên sản phẩm..." style="border-radius: 8px 0 0 8px;">
                <button class="admin-filter-btn" style="border-radius: 0 8px 8px 0;" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <select id="typeFilter" class="admin-filter-select" style="width: auto; min-width: 140px;">
            <option value="all">Tất cả loại</option>
            <option value="combo">Combo</option>
            <option value="food">Đồ ăn</option>
            <option value="drink">Đồ uống</option>
        </select>

        <select id="statusFilter" class="admin-filter-select" style="width: auto; min-width: 160px;">
            <option value="all">Tất cả trạng thái</option>
            <option value="active">Đang bán</option>
            <option value="inactive">Ngừng bán</option>
        </select>

        <button type="button" id="btnOpenCreateProduct" class="admin-action-btn ms-auto">
            <i class="bi bi-plus-lg"></i> Thêm Sản phẩm
        </button>
    </div>
</div>

{{-- ── Table ─────────────────────────────────────────────────── --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 60px;">STT</th>
                    <th style="width: 80px;">Hình ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Loại & Tồn kho</th>
                    <th>Giá bán</th>
                    <th class="text-center" style="width: 120px;">Trạng thái</th>
                    <th class="text-center" style="width: 140px;">Hành động</th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
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

{{-- ── Modal: Thêm / Sửa Sản phẩm ─────────────────────────────── --}}
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel">
                    <i class="bi bi-box-seam me-2"></i>Thêm sản phẩm mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="productForm">
                <input type="hidden" id="productFormMethod" value="POST">
                <input type="hidden" name="product_id" id="productIdInput" value="">

                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-4">
                        {{-- Cột trái --}}
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label for="productName" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="productName" name="name" required>
                            </div>
                            
                            <div class="row mb-3 g-3">
                                <div class="col-md-6">
                                    <label for="productType" class="form-label">Phân loại <span class="text-danger">*</span></label>
                                    <select class="form-select" id="productType" name="type" required>
                                        <option value="combo">Combo</option>
                                        <option value="food">Đồ ăn</option>
                                        <option value="drink">Đồ uống</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="productPrice" class="form-label">Giá bán (VNĐ) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="productPrice" name="price" min="0" step="1000" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="productStock" class="form-label">Tồn kho <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="productStock" name="stock" min="0" value="999" required>
                            </div>

                            <div class="mb-3">
                                <label for="productDescription" class="form-label">Mô tả chi tiết</label>
                                <textarea class="form-control" id="productDescription" name="description" rows="3" placeholder="Bao gồm 1 bắp + 1 nước..."></textarea>
                            </div>
                            

                        </div>
                        
                        {{-- Cột phải (Ảnh) --}}
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label">Hình ảnh sản phẩm</label>
                                <div class="poster-upload-box mb-2" id="imageUploadBox"
                                     style="border: 2px dashed rgba(255,255,255,0.15); border-radius: 10px; min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: border-color 0.2s;">
                                    <img id="imagePreview" src="" alt="Preview"
                                         style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; border-radius: 8px; display: none; background: rgba(0,0,0,0.5);">
                                    <div id="imagePlaceholder" class="text-center text-white-50 p-3">
                                        <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>
                                        <div class="small fw-semibold">Kéo thả hoặc click để chọn</div>
                                        <div style="font-size:0.72rem;">JPG, PNG, WEBP · Tối đa 5MB</div>
                                    </div>
                                    <input type="file" id="productImageFile" name="image_file"
                                           accept="image/jpeg,image/png,image/webp"
                                           style="position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;">
                                </div>
                                <button type="button" id="clearImageBtn"
                                        class="btn btn-sm w-100 d-none"
                                        style="background: rgba(255,255,255,0.06); color:#a1a1aa; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; font-size:0.78rem;">
                                    <i class="bi bi-x me-1"></i>Xóa ảnh
                                </button>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label mb-0">Trạng thái xuất bản</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="productStatus" name="status" value="1" checked>
                                    <label class="form-check-label small" for="productStatus" id="productStatusLabel">Đang bán</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom" id="productSubmitBtn">Lưu sản phẩm</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/admin-modals.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script>
    window.ADMIN_PRODUCT_PAGE = {
        type: 'all',
        allowedTypes: null,
        createTitle: 'Thêm sản phẩm mới',
        editTitle: 'Cập nhật sản phẩm',
        createSuccess: 'Thêm sản phẩm thành công!',
        updateSuccess: 'Cập nhật sản phẩm thành công!',
    };
</script>
<script src="{{ asset('js/pages/admin/products.js') }}?v={{ time() }}" defer></script>
@endpush