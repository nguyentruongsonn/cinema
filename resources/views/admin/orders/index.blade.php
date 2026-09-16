@extends('layouts.admin')

@section('title', 'Quản lý Đơn hàng')
@section('header_title', 'Quản lý Đơn hàng')
@section('header_subtitle', 'Theo dõi và quản lý tất cả đơn hàng, vé đã đặt.')

@section('content')
<div class="admin-orders-page">

{{-- Status overview --}}
<section class="admin-card admin-orders-overview" aria-labelledby="ordersListTitle">
    <div class="admin-orders-overview-copy">
        <span class="admin-orders-eyebrow">Vận hành đơn hàng</span>
        <h3 id="ordersListTitle" class="admin-orders-title">Danh sách đơn hàng</h3>
        <p class="admin-orders-subtitle">
            <span id="orderCount">0 đơn hàng</span> trong phạm vi bạn được phân quyền.
        </p>
    </div>

    <div class="admin-orders-tabs" role="tablist" aria-label="Lọc theo trạng thái đơn hàng">
        <button class="admin-orders-tab active" data-filter-status="all" role="tab" aria-selected="true">Tất cả</button>
        <button class="admin-orders-tab" data-filter-status="pending" role="tab" aria-selected="false">Chờ thanh toán</button>
        <button class="admin-orders-tab" data-filter-status="paid" role="tab" aria-selected="false">Đã thanh toán</button>
        <button class="admin-orders-tab" data-filter-status="cancelled" role="tab" aria-selected="false">Đã hủy</button>
        <button class="admin-orders-tab" data-filter-status="expired" role="tab" aria-selected="false">Hết hạn</button>
    </div>
</section>

{{-- Advanced filters --}}
<section class="admin-filter-container" aria-label="Bộ lọc đơn hàng">
    <div class="admin-filter-bar align-items-end">
        <div class="admin-filter-fields align-items-end">
            <div class="admin-filter-group auto-width">
                <label for="dateFromFilter" class="filter-label mb-1">Từ ngày</label>
                <input type="date" id="dateFromFilter" class="admin-filter-input filter-date-md" title="Từ ngày">
            </div>

            <div class="admin-filter-group auto-width">
                <label for="dateToFilter" class="filter-label mb-1">Đến ngày</label>
                <input type="date" id="dateToFilter" class="admin-filter-input filter-date-md" title="Đến ngày">
            </div>

            <div class="admin-filter-group auto-width">
                <label for="branchFilter" class="visually-hidden">Chi nhánh</label>
                <select id="branchFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả chi nhánh</option>
                </select>
            </div>

            <div class="admin-filter-group auto-width">
                <label for="theaterFilter" class="visually-hidden">Rạp chiếu</label>
                <select id="theaterFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả rạp</option>
                </select>
            </div>

            <div class="admin-filter-group auto-width">
                <label for="movieFilter" class="visually-hidden">Phim</label>
                <select id="movieFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả phim</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            <label for="searchFilter" class="visually-hidden">Tìm kiếm đơn hàng</label>
            <div class="input-group">
                <input type="search" id="searchFilter" class="admin-filter-input search-input-rounded-left" placeholder="Mã đơn, email, SĐT...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit" id="btnApplyFilter" title="Tìm kiếm" aria-label="Tìm kiếm">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button class="admin-filter-btn admin-filter-reset admin-filter-primary-action" type="button" id="btnResetFilter" title="Đặt lại bộ lọc">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Đặt lại</span>
        </button>
    </div>
</section>

{{-- Shared admin table --}}
<section class="admin-table-container" aria-live="polite">
    <div class="admin-table-wrapper">
        <table class="admin-table table-responsive">
            <thead>
                <tr>
                    <th>Đơn hàng</th>
                    <th>Lịch chiếu / dịch vụ</th>
                    <th>Khách hàng</th>
                    <th>Thanh toán</th>
                    <th class="text-center col-status">Trạng thái</th>
                    <th class="text-center">Nguồn</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="ordersTableBody">
                <x-admin.skeleton-table cols="7" rows="5" :hasImage="false" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="ordersPagination"></div>
</section>
</div>

<x-admin.modal
    id="orderDetailModal"
    title-id="orderDetailModalLabel"
    title="Chi tiết đơn hàng"
    icon="bi-receipt"
    size="modal-xl"
    :submit-label="null"
    :cancel-label="null"
>
    <div id="orderDetailModalBody"></div>

    <x-slot:footer>
        <div id="modalOrderStatusContainer"></div>
        <div class="d-flex gap-2 ms-auto">
            <button type="button" class="btn btn-outline-light admin-modal-cancel" data-bs-dismiss="modal">Đóng</button>
            <button type="button" class="btn-primary-custom border-0" id="btnPrintOrderInvoice" disabled>
                <i class="bi bi-printer me-1"></i> In chứng từ
            </button>
        </div>
    </x-slot:footer>
</x-admin.modal>

@endsection

@push('styles')
    @vite('resources/css/admin/pages/orders.css')
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/orders.js') }}?v={{ filemtime(public_path('js/admin/pages/orders.js')) }}" defer></script>
@endpush
