@extends('layouts.admin')

@section('title', 'Quản lý chi nhánh')
@section('header_title', 'Quản lý chi nhánh')
@section('header_subtitle', 'Xem và quản lý danh sách các chi nhánh rạp chiếu.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold flex-no-shrink">Danh sách chi nhánh</h5>

        <form id="searchForm" class="flex-grow-1 search-container-lg">
            <div class="input-group">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm chi nhánh...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn ms-auto" id="btnCreateBranch">
            <i class="bi bi-plus-lg"></i> Tạo chi nhánh
        </button>
    </div>
</div>

{{-- ── Dòng 3: Table ───────────────────────────────────────────────── --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center col-stt">STT</th>
                    <th>Tên chi nhánh</th>
                    <th class="text-center">Hoạt động</th>
                    <th>Ngày tạo</th>
                    <th>Ngày cập nhật</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="branchesTableBody">
                <!-- Skeleton Loading Rows -->
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- Modal Thêm/Sửa --}}
<div class="modal fade" id="branchModal" tabindex="-1" aria-labelledby="branchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="branchModalLabel">Tạo chi nhánh mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="branchForm">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="branchIdInput" value="">

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="branchName" class="form-label text-secondary">Tên chi nhánh <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="branchName" name="name" required>
                    </div>

                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="branchIsActive" name="is_active" value="1" checked>
                            <label class="form-check-label text-white" for="branchIsActive">Kích hoạt hoạt động</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/components/skeleton.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/admin-modals.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/branches.js') }}?v={{ time() }}"></script>
@endpush
