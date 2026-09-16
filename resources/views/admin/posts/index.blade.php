@extends('layouts.admin')

@section('title', 'Quản lý bài viết')
@section('header_title', 'Quản lý bài viết')

@push('styles')
<link href="{{ asset('vendor/summernote/summernote-lite.min.css') }}" rel="stylesheet">
@endpush

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="categoryFilter" class="admin-filter-select">
                    <option value="all">Tất cả danh mục</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đã xuất bản</option>
                    <option value="0">Nháp</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group search-container">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm bài viết...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn admin-filter-primary-action" id="btnCreatePost">
            <i class="bi bi-plus-lg"></i> Tạo bài viết
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
                    <th class="col-min-250">Tiêu đề</th>
                    <th class="text-center col-category">Danh mục</th>
                    <th class="text-center col-author">Tác giả</th>
                    <th class="text-center col-views">Lượt xem</th>
                    <th class="text-center col-date">Ngày tạo</th>
                    <th class="text-center col-status">Trạng thái</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="postsTableBody">
                <x-admin.skeleton-table cols="8" rows="5" :hasImage="false" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- Modal ─────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="postModalLabel">
                    <i class="bi bi-file-text me-2 admin-accent-icon"></i>Tạo bài viết mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="postForm" enctype="multipart/form-data">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="postIdInput">

                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Tiêu đề & Slug --}}
                        <div class="col-md-8">
                            <label class="form-label" for="postTitle">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark border-secondary text-white"
                                   id="postTitle" required placeholder="Nhập tiêu đề bài viết...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="postSlug">Slug</label>
                            <input type="text" class="form-control bg-dark border-secondary text-white"
                                   id="postSlug" placeholder="auto-generated">
                        </div>

                        {{-- Danh mục & Ảnh --}}
                        <div class="col-md-6">
                            <label class="form-label" for="postCategory">Danh mục <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark border-secondary text-white" id="postCategory" required>
                                {{-- Populated by JS --}}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="postImage">Ảnh đại diện</label>
                            <div class="image-upload-box post-image-upload" id="postImageUploadBox" data-media-input>
                                <img id="postImagePreview" class="image-preview" alt="Xem trước ảnh đại diện">
                                <div id="postImagePlaceholder" class="image-placeholder text-white-50">
                                    <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>
                                    <div class="small fw-semibold">Kéo thả hoặc click để chọn</div>
                                </div>
                                <input type="file" class="image-upload-input" id="postImage" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <button type="button" id="clearPostImageBtn" class="btn btn-sm w-100 d-none btn-clear-image mt-2">
                                <i class="bi bi-x me-1"></i>Xóa ảnh
                            </button>
                            <small class="text-white-50">Max 5MB. Khuyến nghị 1200×630px</small>
                        </div>

                        {{-- Tóm tắt --}}
                        <div class="col-12">
                            <label class="form-label" for="postExcerpt">Tóm tắt</label>
                            <textarea class="form-control bg-dark border-secondary text-white"
                                      id="postExcerpt" rows="2" placeholder="Mô tả ngắn về bài viết..."></textarea>
                        </div>

                        {{-- Nội dung (Rich Text Editor) --}}
                        <div class="col-12 mb-4">
                            <label class="form-label" for="summernoteEditor">Nội dung <span class="text-danger">*</span></label>
                            <textarea id="summernoteEditor" name="content" class="form-control bg-dark border-secondary text-white" rows="6" placeholder="Viết nội dung bài viết..."></textarea>
                            <input type="hidden" id="postContent" required>
                        </div>

                        {{-- Ngày xuất bản & Trạng thái --}}
                        <div class="col-md-6">
                            <label class="form-label" for="postPublishedAt">Ngày xuất bản</label>
                            <input type="datetime-local" class="form-control bg-dark border-secondary text-white"
                                   id="postPublishedAt">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <label class="form-label mb-0">Trạng thái xuất bản</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="postIsPublished">
                                    <label class="form-check-label" for="postIsPublished" id="postStatusLabel">Xuất bản ngay</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="submit" class="btn-primary-custom border-0">Lưu bài viết</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link href="{{ asset('vendor/summernote/summernote-lite.min.css') }}" rel="stylesheet">
@vite('resources/css/admin/pages/posts.css')
@endpush

@push('scripts')
<script src="{{ asset('vendor/summernote/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('vendor/summernote/summernote-lite.min.js') }}"></script>
<script src="{{ asset('js/admin/pages/posts.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
