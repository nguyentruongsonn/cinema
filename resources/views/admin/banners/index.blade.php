@extends('layouts.admin')

@section('title', 'Quản lý banner')
@section('header_title', 'Quản lý banner')
@section('header_subtitle', 'Quản lý banner quảng cáo hiển thị trên website.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/admin-modals.css') }}?v={{ time() }}">
@endpush

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0;">
            Danh sách banner
        </h5>
        
        <form id="searchForm" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="input-group" style="max-width: 400px;">
                <input type="text" id="search" class="admin-filter-input" placeholder="Tìm banner..." style="border-radius: 8px 0 0 8px;">
                <button class="admin-filter-btn" style="border-radius: 0 8px 8px 0;" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            <div class="admin-filter-group">
                <select id="positionFilter" class="admin-filter-select">
                    <option value="all">Tất cả vị trí</option>
                    <option value="home_slider">Slider trang chủ</option>
                    <option value="sidebar">Sidebar</option>
                    <option value="popup">Popup</option>
                    <option value="top_bar">Top bar</option>
                    <option value="footer">Footer</option>
                </select>
            </div>

            <div class="admin-filter-group">
                <select id="statusFilter" class="admin-filter-select">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Tạm dừng</option>
                </select>
            </div>
        </form>

        <button type="button" class="admin-action-btn ms-auto" id="btnCreateBanner">
            <i class="bi bi-plus-lg"></i> Tạo banner
        </button>
    </div>
</div>

{{-- ── Table ───────────────────────────────────────────────────────── --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 60px;">STT</th>
                    <th style="width: 120px;">Hình ảnh</th>
                    <th style="min-width: 200px;">Tiêu đề</th>
                    <th class="text-center" style="width: 130px;">Vị trí</th>
                    <th class="text-center" style="width: 100px;">Thứ tự</th>
                    <th class="text-center" style="width: 110px;">Ngày bắt đầu</th>
                    <th class="text-center" style="width: 110px;">Ngày kết thúc</th>
                    <th class="text-center" style="width: 100px;">Trạng thái</th>
                    <th class="text-center" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody id="bannersTableBody">
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
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

{{-- ── Modal: Thêm / Sửa Banner ─────────────────────────────────────── --}}
<div class="modal fade" id="bannerModal" tabindex="-1" aria-labelledby="bannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bannerModalLabel">
                    <i class="bi bi-badge-ad me-2"></i>Tạo banner mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="bannerForm" enctype="multipart/form-data">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="bannerIdInput">

                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Tiêu đề & Thứ tự --}}
                        <div class="col-md-8">
                            <label class="form-label" for="bannerTitle">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark border-secondary text-white"
                                   id="bannerTitle" required placeholder="Nhập tiêu đề banner...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bannerDisplayOrder">Thứ tự hiển thị</label>
                            <input type="number" class="form-control bg-dark border-secondary text-white"
                                   id="bannerDisplayOrder" value="0" min="0">
                        </div>

                        {{-- Vị trí & Hình ảnh --}}
                        <div class="col-md-6">
                            <label class="form-label" for="bannerPosition">Vị trí <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark border-secondary text-white" id="bannerPosition" required>
                                <option value="">-- Chọn vị trí --</option>
                                <option value="home_slider">Slider trang chủ</option>
                                <option value="sidebar">Sidebar</option>
                                <option value="popup">Popup</option>
                                <option value="top_bar">Top bar</option>
                                <option value="footer">Footer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bannerImage">Hình ảnh <span class="text-danger" id="bannerImageRequired">*</span></label>
                            <input type="file" class="form-control bg-dark border-secondary text-white"
                                   id="bannerImage" accept="image/*" multiple>
                            <small class="text-white-50">Max 5MB. Có thể chọn nhiều ảnh để tạo nhiều banner.</small>
                        </div>

                        {{-- Preview --}}
                        <div class="col-12" id="imagePreviewContainer" style="display: none;">
                            <label class="form-label">Xem trước hình ảnh</label>
                            <div class="preview-wrap p-2">
                                <img id="imagePreview" src="" alt="Preview">
                            </div>
                        </div>

                        {{-- Mô tả --}}
                        <div class="col-12">
                            <label class="form-label" for="bannerDescription">Mô tả</label>
                            <textarea class="form-control bg-dark border-secondary text-white"
                                      id="bannerDescription" rows="2" placeholder="Mô tả ngắn về banner..."></textarea>
                        </div>

                        {{-- Link đích --}}
                        <div class="col-12">
                            <label class="form-label" for="bannerLink">Link đích (URL)</label>
                            <input type="url" class="form-control bg-dark border-secondary text-white"
                                   id="bannerLink" placeholder="https://...">
                        </div>

                        {{-- Ngày bắt đầu & Kết thúc --}}
                        <div class="col-md-6">
                            <label class="form-label" for="bannerStartDate">Ngày bắt đầu</label>
                            <input type="date" class="form-control bg-dark border-secondary text-white"
                                   id="bannerStartDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bannerEndDate">Ngày kết thúc</label>
                            <input type="date" class="form-control bg-dark border-secondary text-white"
                                   id="bannerEndDate">
                        </div>

                        {{-- Trạng thái --}}
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0">Trạng thái kích hoạt</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="bannerIsActive" checked>
                                    <label class="form-check-label" for="bannerIsActive" id="bannerStatusLabel">Đang hoạt động</label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu banner</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/pages/admin/banners.js') }}?v={{ time() }}"></script>
@endpush