@extends('layouts.admin')

@section('title', 'Audit logs')
@section('header_title', 'Audit logs')

@section('content')
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="auditTypeFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả đối tượng</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <input type="date" id="auditDateFrom" class="admin-filter-input">
            </div>
            <div class="admin-filter-group auto-width">
                <input type="date" id="auditDateTo" class="admin-filter-input">
            </div>
        </div>

        <form id="auditSearchForm" class="admin-filter-search">
            <div class="input-group">
                <input type="text" id="auditSearch" class="admin-filter-input search-input-rounded-left" placeholder="Tìm action, request id, actor...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Người thao tác</th>
                    <th>Action</th>
                    <th>Đối tượng</th>
                    <th>Request ID</th>
                    <th class="text-center col-actions">Chi tiết</th>
                </tr>
            </thead>
            <tbody id="auditLogsTableBody">
                <x-admin.skeleton-table cols="6" rows="6" :hasImage="false" />
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-center mt-4 pt-3" id="auditPagination"></div>
</div>

<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="auditDetailModalLabel">Chi tiết audit log</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div id="auditDetailMeta" class="mb-3 text-secondary"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-white">Trước thay đổi</h6>
                        <pre class="admin-json-preview" id="auditOldValues">{}</pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-white">Sau thay đổi</h6>
                        <pre class="admin-json-preview" id="auditNewValues">{}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module" src="{{ asset('js/admin/pages/audit-logs.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush
