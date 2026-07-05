@extends('layouts.admin')

@section('title', 'Quản lý bài viết')
@section('header_title', 'Quản lý bài viết')
@section('header_subtitle', 'Quản lý nội dung tin tức, blog và thông báo.')

@push('styles')
<link href="{{ asset('vendor/summernote/summernote-lite.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin/posts.css') }}?v={{ time() }}">
@endpush

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0;">
            <i class="bi bi-file-text me-2"></i>Danh sách bài viết
        </h5>
        
        <form id="searchForm" class="d-flex flex-grow-1 align-items-center gap-3">
            {{-- Search --}}
            <div class="input-group" style="max-width: 400px;">
                <input type="text" id="search" class="admin-filter-input" placeholder="Tìm bài viết..." style="border-radius: 8px 0 0 8px;">
                <button class="admin-filter-btn" style="border-radius: 0 8px 8px 0;" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            {{-- Category Filter --}}
            <div class="admin-filter-group">
                <select id="categoryFilter" class="admin-filter-select">
                    <option value="all">Tất cả danh mục</option>
                </select>
            </div>

            {{-- Status Filter --}}
            <div class="admin-filter-group">
                <select id="statusFilter" class="admin-filter-select">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đã xuất bản</option>
                    <option value="0">Nháp</option>
                </select>
            </div>
        </form>

        <button type="button" class="admin-action-btn ms-auto" id="btnCreatePost">
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
                    <th class="text-center" style="width: 60px;">STT</th>
                    <th style="min-width: 250px;">Tiêu đề</th>
                    <th class="text-center" style="width: 130px;">Danh mục</th>
                    <th class="text-center" style="width: 120px;">Tác giả</th>
                    <th class="text-center" style="width: 100px;">Lượt xem</th>
                    <th class="text-center" style="width: 110px;">Ngày tạo</th>
                    <th class="text-center" style="width: 100px;">Trạng thái</th>
                    <th class="text-center" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody id="postsTableBody">
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

{{-- Modal ─────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="postModalLabel">
                    <i class="bi bi-file-text me-2"></i>Tạo bài viết mới
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
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu bài viết</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/summernote/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('vendor/summernote/summernote-lite.min.js') }}"></script>
<script src="{{ asset('js/pages/admin/posts.js') }}?v={{ time() }}"></script>
@endpush