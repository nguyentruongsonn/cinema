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
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold flex-no-shrink">
            Danh sách bài viết
        </h5>

        <form id="searchForm" class="d-flex flex-grow-1 align-items-center gap-3">
            {{-- Search --}}
            <div class="input-group search-container">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm bài viết...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
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
                <!-- Skeleton Loading Rows -->
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text" style="width: 75%;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="width: 90px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 70%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 70%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text" style="width: 85%;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="width: 90px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 65%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 75%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text" style="width: 65%;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="width: 90px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 80%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 65%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text" style="width: 80%;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="width: 90px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 75%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 80%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 30px; margin: 0 auto;"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text" style="width: 70%;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="width: 90px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 60%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text" style="width: 70%; margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge" style="margin: 0 auto;"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm" style="margin: 0 auto;"></div></td>
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

@push('styles')
@endpush

@push('scripts')
<script src="{{ asset('vendor/summernote/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('vendor/summernote/summernote-lite.min.js') }}"></script>
<script src="{{ asset('js/admin/pages/posts.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush
