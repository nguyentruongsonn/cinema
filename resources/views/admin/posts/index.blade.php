@extends('layouts.admin')

@section('title', 'Quản lý bài viết')
@section('header_title', 'Quản lý bài viết')

@push('styles')
<link href="{{ asset('vendor/summernote/summernote-lite.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin/posts.css') }}?v={{ config('app.asset_version') }}">
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
                    <i class="bi bi-file-text me-2" style="color:var(--accent-color);"></i>Tạo bài viết mới
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
                            <input type="file" class="form-control bg-dark border-secondary text-white"
                                   id="postImage" accept="image/*">
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
<style>
    .note-editor.note-frame {
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        background: #1a1d20 !important;
        border-radius: 8px !important;
        overflow: hidden;
    }
    .note-editor .note-toolbar {
        background: #212529 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        padding: 8px !important;
        margin: 0 !important;
    }
    .note-editor .note-editing-area .note-editable {
        background: #1a1d20 !important;
        color: #fff !important;
        min-height: 260px;
        line-height: 1.6;
    }
    .note-editor .note-btn {
        background: #2c3034 !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
        border-radius: 6px !important;
    }
    .note-editor .note-btn:hover {
        background: #373b3e !important;
        border-color: rgba(255, 255, 255, 0.25) !important;
    }
    .note-editor .note-dropdown-menu {
        background: #212529 !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        box-shadow: 0 8px 24px rgba(0,0,0,0.5) !important;
    }
    .note-editor .note-dropdown-item {
        color: #fff !important;
    }
    .note-editor .note-dropdown-item:hover {
        background: #373b3e !important;
    }
    .note-placeholder {
        color: #888 !important;
    }
    .note-modal .modal-content {
        background: #212529 !important;
        color: #fff !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
    }
    .note-modal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .note-modal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/summernote/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('vendor/summernote/summernote-lite.min.js') }}"></script>
<script src="{{ asset('js/admin/pages/posts.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
