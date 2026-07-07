@extends('layouts.admin')

@section('title', 'Quản lý rạp chiếu')
@section('header_title', 'Quản lý rạp chiếu')
@section('header_subtitle', 'Xem và quản lý danh sách các rạp chiếu phim.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold flex-no-shrink">Danh sách rạp chiếu</h5>

        <form id="searchForm" class="flex-grow-1 search-container-lg">
            <div class="input-group">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm rạp chiếu...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn ms-auto" id="btnCreateTheater">
            <i class="bi bi-plus-lg"></i> Thêm rạp chiếu
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
                    <th>Tên rạp</th>
                    <th>Chi nhánh</th>
                    <th>Địa chỉ</th>
                    <th class="text-center">Hoạt động</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="theatersTableBody">
                <!-- Skeleton Loading Rows -->
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-55"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-68"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-78"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination (handled via JS) --}}
    <div class="d-flex justify-content-end mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- ── Modal: Thêm / Sửa Rạp Chiếu ────────────────────────────────── --}}
<div class="modal fade" id="theaterModal" tabindex="-1" aria-labelledby="theaterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="theaterModalLabel">Thêm rạp chiếu mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="theaterForm">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="theaterIdInput" value="">

                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="theaterName" class="form-label text-secondary">Tên rạp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="theaterName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="theaterBranch" class="form-label text-secondary">Chi nhánh <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-white border-secondary" id="theaterBranch" name="branch_id" required>
                                <option value="">-- Chọn chi nhánh --</option>
                                <!-- JS will populate branches here -->
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="theaterAddress" class="form-label text-secondary">Địa chỉ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="theaterAddress" name="address" required>
                    </div>
                    <div class="mb-3">
                        <label for="theaterDescription" class="form-label text-secondary">Mô tả</label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="theaterDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="theaterStatus" name="is_active" value="1" checked>
                            <label class="form-check-label text-white cursor-pointer" for="theaterStatus">Cho phép hoạt động</label>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="theaterPhone" class="form-label text-secondary">Số điện thoại</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="theaterPhone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="theaterEmail" class="form-label text-secondary">Email</label>
                            <input type="email" class="form-control bg-dark text-white border-secondary" id="theaterEmail" name="email">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white btn-modal-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu rạp chiếu</button>
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
<script src="{{ asset('js/admin/pages/theaters.js') }}?v={{ time() }}" defer></script>
@endpush
