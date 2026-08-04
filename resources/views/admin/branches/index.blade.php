@extends('layouts.admin')

@section('title', 'Quản lý chi nhánh')
@section('header_title', 'Quản lý chi nhánh')
@section('header_subtitle', 'Xem và quản lý danh sách các chi nhánh rạp chiếu.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<x-admin.filter-bar search-placeholder="Tìm chi nhánh..." btn-create-id="btnCreateBranch" btn-create-label="Tạo chi nhánh">
    <x-slot:filters>
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select filter-select-md">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>
    </x-slot:filters>
</x-admin.filter-bar>
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
                <x-admin.skeleton-table cols="6" rows="5" :hasImage="false" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- Modal Thêm/Sửa --}}
<x-admin.modal id="branchModal" title-id="branchModalLabel" title="Tạo chi nhánh mới" icon="bi-geo-alt"
               size="" form-id="branchForm" submit-label="Lưu chi nhánh" submit-class="btn-primary-custom border-0">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="branchIdInput" value="">
                    <div class="mb-3">
                        <label for="branchName" class="form-label text-secondary">Tên chi nhánh <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="branchName" name="name" required placeholder="VD: Cinema Quận 1">
                    </div>

                    <div class="mb-0">
                        <div class="form-check form-switch">
                                <input class="form-check-input admin-form-check-clickable" type="checkbox" id="branchIsActive" name="is_active" value="1" checked>
                            <label class="form-check-label text-white" for="branchIsActive">Kích hoạt hoạt động</label>
                        </div>
                    </div>
</x-admin.modal>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/branches.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
