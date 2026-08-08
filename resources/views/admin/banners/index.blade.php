@extends('layouts.admin')

@section('title', 'Quản lý banner')
@section('header_title', 'Quản lý banner')
@section('header_subtitle', 'Quản lý banner quảng cáo hiển thị trên website.')

@push('styles')
@vite('resources/css/admin/pages/banners.css')
@endpush

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
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
                <th class="col-min-250 admin-min-w-340">Hình ảnh</th>
                    <th class="col-min-200">Tiêu đề</th>
                    <th class="text-center col-date">Ngày bắt đầu</th>
                    <th class="text-center col-date">Ngày kết thúc</th>
                    <th class="text-center col-status">Trạng thái</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="bannersTableBody">
                <x-admin.skeleton-table cols="7" rows="5" :hasImage="true" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- ── Modal: Thêm / Sửa Banner ─────────────────────────────────────── --}}
<x-admin.modal id="bannerModal" title-id="bannerModalLabel" title="Tạo banner mới" icon="bi-image"
               form-id="bannerForm" form-enctype="multipart/form-data" submit-label="Lưu banner"
               submit-class="btn-primary-custom border-0" cancel-label="">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="bannerIdInput">
                    <div class="row g-3">

                        {{-- Tiêu đề --}}
                        <div class="col-12">
                            <label class="form-label" for="bannerTitle">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark border-secondary text-white"
                                   id="bannerTitle" required placeholder="Nhập tiêu đề banner...">
                        </div>

                        {{-- Hình ảnh --}}
                        <div class="col-12">
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
                            <label class="form-label" for="bannerLink">Liên kết đích (URL)</label>
                            <input type="url" class="form-control bg-dark border-secondary text-white"
                                   id="bannerLink" placeholder="https://example.com/promotion">
                        </div>

                        {{-- Ngày bắt đầu & kết thúc --}}
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
</x-admin.modal>

@endsection

@push('styles')
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/banners.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
