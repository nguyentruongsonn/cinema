@extends('layouts.admin')

@section('title', 'Quản lý Đơn hàng')
@section('header_title', 'Quản lý Đơn hàng')
@section('header_subtitle', 'Theo dõi và quản lý tất cả đơn hàng, vé đã đặt.')

@section('content')

{{-- Filter Bar (Status Tabs & Stats) --}}
<div class="tickets-header mb-4">
    <div class="tickets-header-content">
        <div class="tickets-stats">
            <span id="orderCount" class="tickets-count">0 đơn hàng</span>
        </div>
    </div>

    {{-- Filter Tabs --}}
    <div class="tickets-tabs" role="tablist">
        <button class="tickets-tab active" data-filter-status="all" role="tab" aria-selected="true">Tất cả</button>
        <button class="tickets-tab" data-filter-status="pending" role="tab" aria-selected="false">Chờ thanh toán</button>
        <button class="tickets-tab" data-filter-status="paid" role="tab" aria-selected="false">Đã thanh toán</button>
        <button class="tickets-tab" data-filter-status="cancelled" role="tab" aria-selected="false">Đã hủy</button>
        <button class="tickets-tab" data-filter-status="expired" role="tab" aria-selected="false">Hết hạn</button>
    </div>
</div>

{{-- Advanced Filters (Tương tự Quản lý Suất chiếu) --}}
<div class="admin-filter-container mb-4">
    <div class="admin-filter-bar align-items-end flex-wrap gap-2">
        <div class="admin-filter-fields align-items-end flex-wrap gap-2">
            <div class="admin-filter-group auto-width">
                <label for="dateFromFilter" class="filter-label mb-1">Từ ngày</label>
                <input type="date" id="dateFromFilter" class="admin-filter-input filter-date-md" title="Từ ngày">
            </div>

            <div class="admin-filter-group auto-width">
                <label for="dateToFilter" class="filter-label mb-1">Đến ngày</label>
                <input type="date" id="dateToFilter" class="admin-filter-input filter-date-md" title="Đến ngày">
            </div>

            <div class="admin-filter-group auto-width">
                <select id="branchFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả chi nhánh</option>
                </select>
            </div>

            <div class="admin-filter-group auto-width">
                <select id="theaterFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả rạp</option>
                </select>
            </div>

            <div class="admin-filter-group auto-width">
                <select id="movieFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả phim</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search d-flex gap-2 align-items-end">
            <input type="text" id="searchFilter" class="admin-filter-input admin-min-w-240" placeholder="Tìm mã đơn, email, SĐT...">
            <button class="admin-filter-btn" type="submit" id="btnApplyFilter" title="Tìm kiếm">
                <i class="bi bi-search"></i>
                <span>Tìm kiếm</span>
            </button>
            <button class="admin-filter-btn admin-filter-reset" type="button" id="btnResetFilter" title="Đặt lại bộ lọc">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Đặt lại</span>
            </button>
        </form>
    </div>
</div>

{{-- Loading State (Skeleton Cards) --}}
<div id="ordersLoading" class="tickets-list">
    <div class="admin-skeleton-card">
        <div class="admin-skeleton-card-header">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-100"></div>
            <div class="admin-skeleton admin-skeleton-badge"></div>
        </div>
        <div class="admin-skeleton-card-body">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-80 skeleton-mb-sm"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-60 skeleton-mb-md"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-40"></div>
        </div>
    </div>
    <div class="admin-skeleton-card">
        <div class="admin-skeleton-card-header">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-100"></div>
            <div class="admin-skeleton admin-skeleton-badge"></div>
        </div>
        <div class="admin-skeleton-card-body">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-75 skeleton-mb-sm"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-65 skeleton-mb-md"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-45"></div>
        </div>
    </div>
    <div class="admin-skeleton-card">
        <div class="admin-skeleton-card-header">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-100"></div>
            <div class="admin-skeleton admin-skeleton-badge"></div>
        </div>
        <div class="admin-skeleton-card-body">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-70 skeleton-mb-sm"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-55 skeleton-mb-md"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-50"></div>
        </div>
    </div>
</div>

{{-- Empty State --}}
<div id="ordersEmpty" class="tickets-empty d-none">
    <i class="bi bi-inbox"></i>
    <h3>Không có đơn hàng nào</h3>
</div>

{{-- Orders List (Card-based) --}}
<div id="ordersGrid" class="tickets-list"></div>

<div class="d-flex justify-content-center mt-4 pt-3" id="ordersPagination"></div>

<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title d-flex align-items-center gap-2" id="orderDetailModalLabel">
                    <i class="bi bi-receipt me-2 admin-accent-icon"></i>
                    Chi tiết đơn hàng <span id="modalOrderCodeTitle"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 ms-2" id="btnCopyOrderCode" title="Sao chép mã đơn hàng">
                        <i class="bi bi-copy"></i>
                    </button>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailModalBody">
                <!-- JS populated -->
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <div id="modalOrderStatusContainer"></div>
                <div>
                    <button type="button" class="btn-primary-custom border-0" id="btnPrintOrderInvoice">
                        <i class="bi bi-printer me-1"></i> In Hóa Đơn
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/pages/tickets.css') }}?v={{ config('app.asset_version') }}">
    @vite('resources/css/admin/pages/orders.css')
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/orders.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
