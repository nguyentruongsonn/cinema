@extends('layouts.admin')

@section('title', 'Quản lý Sản phẩm / Combo')
@section('header_title', 'Sản phẩm & Combo')
@section('header_subtitle', 'Quản lý danh sách đồ ăn, thức uống và combo.')

@section('content')

{{-- ── Header & Filter Bar ────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner align-items-center w-100">
        <h5 class="mb-0 text-white fw-bold me-4"><i class="bi bi-box-seam me-2"></i>Danh sách Sản phẩm</h5>

        <form id="searchForm" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="filter-group flex-grow-1" style="max-width: 400px;">
                <label for="search" class="filter-label" style="display:none;">Tìm kiếm</label>
                <div class="input-group">
                    <input type="text" id="search" name="search" class="filter-input" placeholder="Tên sản phẩm..." style="border-radius: 6px 0 0 6px;">
                    <button class="btn btn-outline-secondary border-0" type="submit" style="background: rgba(255,255,255,0.05); color: #fff;">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="filter-group">
                <select id="typeFilter" class="form-select filter-input">
                    <option value="all">Tất cả loại</option>
                    <option value="combo">Combo</option>
                    <option value="food">Đồ ăn</option>
                    <option value="drink">Đồ uống</option>
                </select>
            </div>

            <div class="filter-group">
                <select id="statusFilter" class="form-select filter-input">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="active">Đang bán</option>
                    <option value="inactive">Ngừng bán</option>
                </select>
            </div>
        </form>

        <button type="button" id="btnOpenCreateProduct" class="btn-primary-custom ms-auto border-0">
            <i class="bi bi-plus-lg"></i> Thêm Sản phẩm
        </button>
    </div>
</div>

{{-- ── Table ─────────────────────────────────────────── --}}
<div class="chart-card">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background:transparent;">
            <thead style="border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                    <th class="text-secondary fw-semibold border-0" style="width: 80px;">Hình ảnh</th>
                    <th class="text-secondary fw-semibold border-0">Tên sản phẩm</th>
                    <th class="text-secondary fw-semibold border-0">Loại &amp; Tồn kho</th>
                    <th class="text-secondary fw-semibold border-0">Giá bán</th>
                    <th class="text-secondary fw-semibold border-0 text-center" style="width: 120px;">Trạng thái</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 150px;">Hành động</th>
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

    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);" id="paginationContainer">
    </div>
</div>

{{-- ── Modal: Thêm / Sửa Sản phẩm ─────────────────────────────── --}}
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="productModalLabel">
                    <i class="bi bi-box-seam me-2"></i>Thêm sản phẩm mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="productForm">
                <input type="hidden" id="productFormMethod" value="POST">
                <input type="hidden" name="product_id" id="productIdInput" value="">

                <div class="modal-body">
                    <div class="row g-4">
                        {{-- Cột trái --}}
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label for="productName" class="form-label text-secondary">Tên sản phẩm/Combo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control bg-dark text-white border-secondary" id="productName" name="name" required>
                            </div>
                            
                            <div class="row mb-3 g-3">
                                <div class="col-md-6">
                                    <label for="productType" class="form-label text-secondary">Phân loại <span class="text-danger">*</span></label>
                                    <select class="form-select bg-dark text-white border-secondary" id="productType" name="type" required>
                                        <option value="combo">Combo</option>
                                        <option value="food">Đồ ăn</option>
                                        <option value="drink">Đồ uống</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="productPrice" class="form-label text-secondary">Giá bán (VNĐ) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control bg-dark text-white border-secondary" id="productPrice" name="price" min="0" step="1000" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="productStock" class="form-label text-secondary">Tồn kho <span class="text-danger">*</span></label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" id="productStock" name="stock" min="0" value="999" required>
                            </div>

                            <div class="mb-3">
                                <label for="productDescription" class="form-label text-secondary">Mô tả chi tiết</label>
                                <textarea class="form-control bg-dark text-white border-secondary" id="productDescription" name="description" rows="3" placeholder="Bao gồm 1 bắp + 1 nước..."></textarea>
                            </div>
                            
                            <div class="mb-0 mt-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="productStatus" name="is_active" value="1" checked>
                                    <label class="form-check-label text-white" for="productStatus">Mở bán trực tuyến</label>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Cột phải (Ảnh) --}}
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label text-secondary">Link Hình ảnh</label>
                                <input type="url" class="form-control bg-dark text-white border-secondary mb-3" id="productImageUrl" name="image_url" placeholder="https://...">
                                <div class="text-center p-3" style="border: 1px dashed rgba(255,255,255,0.2); border-radius: 8px;">
                                    <img id="imagePreview" src="" alt="Preview" class="img-fluid d-none" style="max-height: 200px; border-radius: 4px;">
                                    <div id="imagePlaceholder" class="text-white-50 py-4">
                                        <i class="bi bi-image fs-1"></i><br>
                                        <small>Chưa có hình ảnh</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="productSubmitBtn">Lưu sản phẩm</button>
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
<script src="{{ asset('js/pages/admin/products.js') }}?v={{ time() }}" defer></script>
@endpush
