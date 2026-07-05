@extends('layouts.admin')

@php
    $productType = $productType ?? 'all';
    $productTitle = $productTitle ?? 'Quản lý sản phẩm';
    $productHeading = $productHeading ?? 'Danh sách Sản phẩm';
    $productSubtitle = $productSubtitle ?? 'Quản lý toàn bộ đồ ăn, thức uống và combo.';
    $productCreateLabel = $productCreateLabel ?? 'Thêm Sản phẩm';
    $productNameLabel = $productNameLabel ?? 'Tên sản phẩm/Combo';
    $productModalCreateTitle = $productModalCreateTitle ?? 'Thêm sản phẩm mới';
    $productModalEditTitle = $productModalEditTitle ?? 'Cập nhật sản phẩm';
    $productSubmitLabel = $productSubmitLabel ?? 'Lưu sản phẩm';
    $showTypeFilter = $showTypeFilter ?? true;
    $allowedProductTypes = $allowedProductTypes ?? null;
@endphp

@section('title', $productTitle)
@section('header_title', $productTitle)
@section('header_subtitle', $productSubtitle)

@section('content')

{{-- ── Filter Bar ─────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3 flex-wrap">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0;">
            <i class="bi bi-box-seam me-2"></i>{{ $productHeading }}
        </h5>

        <form id="searchForm" class="flex-grow-1" style="max-width: 400px;">
            <div class="input-group">
                <input type="text" id="search" name="search" class="admin-filter-input" placeholder="Tên sản phẩm..." style="border-radius: 8px 0 0 8px;">
                <button class="admin-filter-btn" style="border-radius: 0 8px 8px 0;" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        @if($showTypeFilter)
            <select id="typeFilter" class="admin-filter-select" style="width: auto; min-width: 140px;">
                @if(!$allowedProductTypes || in_array('all', $allowedProductTypes, true))
                    <option value="all">Tất cả loại</option>
                @else
                    <option value="{{ implode(',', $allowedProductTypes) }}">Tất cả loại</option>
                @endif
                @if(!$allowedProductTypes || in_array('combo', $allowedProductTypes, true))
                    <option value="combo">Combo</option>
                @endif
                @if(!$allowedProductTypes || in_array('food', $allowedProductTypes, true))
                    <option value="food">Đồ ăn</option>
                @endif
                @if(!$allowedProductTypes || in_array('drink', $allowedProductTypes, true))
                    <option value="drink">Đồ uống</option>
                @endif
            </select>
        @else
            <input type="hidden" id="typeFilter" value="{{ $productType }}">
        @endif

        <select id="statusFilter" class="admin-filter-select" style="width: auto; min-width: 160px;">
            <option value="all">Tất cả trạng thái</option>
            <option value="active">Đang bán</option>
            <option value="inactive">Ngừng bán</option>
        </select>

        <button type="button" id="btnOpenCreateProduct" class="admin-action-btn ms-auto">
            <i class="bi bi-plus-lg"></i> {{ $productCreateLabel }}
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
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="productModalLabel">
                    <i class="bi bi-box-seam me-2"></i>{{ $productModalCreateTitle }}
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
                                <label for="productName" class="form-label text-secondary">{{ $productNameLabel }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control bg-dark text-white border-secondary" id="productName" name="name" required>
                            </div>
                            
                            <div class="row mb-3 g-3">
                                <div class="col-md-6">
                                    <label for="productType" class="form-label text-secondary">Phân loại <span class="text-danger">*</span></label>
                                    <select class="form-select bg-dark text-white border-secondary" id="productType" name="type" required {{ $productType !== 'all' ? 'disabled' : '' }}>
                                        @if(!$allowedProductTypes || in_array('combo', $allowedProductTypes, true))
                                            <option value="combo" {{ $productType === 'combo' ? 'selected' : '' }}>Combo</option>
                                        @endif
                                        @if(!$allowedProductTypes || in_array('food', $allowedProductTypes, true))
                                            <option value="food" {{ $productType === 'food' ? 'selected' : '' }}>Đồ ăn</option>
                                        @endif
                                        @if(!$allowedProductTypes || in_array('drink', $allowedProductTypes, true))
                                            <option value="drink" {{ $productType === 'drink' ? 'selected' : '' }}>Đồ uống</option>
                                        @endif
                                    </select>
                                    @if($productType !== 'all')
                                        <input type="hidden" id="fixedProductType" value="{{ $productType }}">
                                    @endif
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
                            

                        </div>
                        
                        {{-- Cột phải (Ảnh) --}}
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label text-secondary">Hình ảnh sản phẩm</label>
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

                            <hr class="border-secondary">

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label text-secondary mb-0">Trạng thái xuất bản</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="productStatus" name="status" value="1" checked>
                                    <label class="form-check-label text-white small" for="productStatus" id="productStatusLabel">Đang bán</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="productSubmitBtn">{{ $productSubmitLabel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/products.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script>
    window.ADMIN_PRODUCT_PAGE = {
        type: @json($productType),
        allowedTypes: @json($allowedProductTypes),
        createTitle: @json($productModalCreateTitle),
        editTitle: @json($productModalEditTitle),
        createSuccess: @json($productType === 'food' ? 'Thêm đồ ăn thành công!' : ($productType === 'drink' ? 'Thêm nước uống thành công!' : ($productType === 'combo' ? 'Thêm combo thành công!' : 'Thêm sản phẩm thành công!'))),
        updateSuccess: @json($productType === 'food' ? 'Cập nhật đồ ăn thành công!' : ($productType === 'drink' ? 'Cập nhật nước uống thành công!' : ($productType === 'combo' ? 'Cập nhật combo thành công!' : 'Cập nhật thành công!'))),
    };
</script>
<script src="{{ asset('js/pages/admin/products.js') }}?v={{ time() }}" defer></script>
@endpush