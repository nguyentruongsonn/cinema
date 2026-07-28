@extends('layouts.admin')

@section('title', 'Quản lý banner')
@section('header_title', 'Quản lý banner')
@section('header_subtitle', 'Quản lý banner quảng cáo hiển thị trên website.')

@push('styles')
@endpush

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="positionFilter" class="admin-filter-select">
                    <option value="all">Tất cả vị trí</option>
                    <option value="home_slider">Slider trang chủ</option>
                    <option value="sidebar">Sidebar</option>
                    <option value="popup">Popup</option>
                    <option value="top_bar">Top bar</option>
                    <option value="footer">Footer</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Tạm dừng</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group search-container">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm banner...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn admin-filter-primary-action" id="btnCreateBanner">
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
                    <th class="text-center col-stt">STT</th>
                    <th class="col-image">Hình ảnh</th>
                    <th class="col-min-200">Tiêu đề</th>
                    <th class="text-center col-category">Vị trí</th>
                    <th class="text-center col-status">Thứ tự</th>
                    <th class="text-center col-date">Ngày bắt đầu</th>
                    <th class="text-center col-date">Ngày kết thúc</th>
                    <th class="text-center col-status">Trạng thái</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="bannersTableBody">
                <x-admin.skeleton-table cols="9" rows="5" :hasImage="true" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
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
                        <div class="col-12 d-none" id="imagePreviewContainer">
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

@push('styles')
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/banners.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
