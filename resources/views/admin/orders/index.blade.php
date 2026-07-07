@extends('layouts.admin')

@section('title', 'Quản lý Đơn hàng')
@section('header_title', 'Quản lý Đơn hàng')
@section('header_subtitle', 'Theo dõi và quản lý tất cả đơn hàng, vé đã đặt.')

@section('content')

{{-- Filter Bar --}}
<div class="tickets-header mb-4">
    <div class="tickets-header-content">
        <div class="tickets-stats">
            <span id="orderCount" class="tickets-count">0 đơn hàng</span>
        </div>
    </div>

    {{-- Filter Tabs --}}
    <div class="tickets-tabs" role="tablist">
        <button class="tickets-tab active" data-filter-status="all" role="tab">Tất cả</button>
        <button class="tickets-tab" data-filter-status="pending" role="tab">Chờ thanh toán</button>
        <button class="tickets-tab" data-filter-status="paid" role="tab">Đã thanh toán</button>
        <button class="tickets-tab" data-filter-status="confirmed" role="tab">Đã xác nhận</button>
        <button class="tickets-tab" data-filter-status="cancelled" role="tab">Đã hủy</button>
    </div>
</div>

{{-- Advanced Filters --}}
<div class="tickets-filters mb-4">
    <div class="tickets-filters-grid">
        <select id="branchFilter" class="tickets-filter-select">
            <option value="">Tất cả chi nhánh</option>
        </select>

        <select id="theaterFilter" class="tickets-filter-select">
            <option value="">Tất cả rạp</option>
        </select>

        <select id="movieFilter" class="tickets-filter-select">
            <option value="">Tất cả phim</option>
        </select>

        <input type="date" id="dateFilter" class="tickets-filter-input" placeholder="Chọn ngày">

        <input type="text" id="searchFilter" class="tickets-filter-input" placeholder="Tìm mã đơn, email, SĐT...">

        <button type="button" id="btnApplyFilter" class="tickets-filter-btn">
            <i class="bi bi-funnel"></i> Lọc
        </button>
    </div>
</div>

{{-- Loading State (Skeleton Cards) --}}
<div id="ordersLoading" class="tickets-list">
    <div class="admin-skeleton-card">
        <div class="admin-skeleton-card-header">
            <div class="admin-skeleton admin-skeleton-text" style="width: 120px;"></div>
            <div class="admin-skeleton admin-skeleton-badge"></div>
        </div>
        <div class="admin-skeleton-card-body">
            <div class="admin-skeleton admin-skeleton-text" style="width: 80%; margin-bottom: 8px;"></div>
            <div class="admin-skeleton admin-skeleton-text" style="width: 60%; margin-bottom: 12px;"></div>
            <div class="admin-skeleton admin-skeleton-text" style="width: 40%;"></div>
        </div>
    </div>
    <div class="admin-skeleton-card">
        <div class="admin-skeleton-card-header">
            <div class="admin-skeleton admin-skeleton-text" style="width: 110px;"></div>
            <div class="admin-skeleton admin-skeleton-badge"></div>
        </div>
        <div class="admin-skeleton-card-body">
            <div class="admin-skeleton admin-skeleton-text" style="width: 75%; margin-bottom: 8px;"></div>
            <div class="admin-skeleton admin-skeleton-text" style="width: 65%; margin-bottom: 12px;"></div>
            <div class="admin-skeleton admin-skeleton-text" style="width: 45%;"></div>
        </div>
    </div>
    <div class="admin-skeleton-card">
        <div class="admin-skeleton-card-header">
            <div class="admin-skeleton admin-skeleton-text" style="width: 130px;"></div>
            <div class="admin-skeleton admin-skeleton-badge"></div>
        </div>
        <div class="admin-skeleton-card-body">
            <div class="admin-skeleton admin-skeleton-text" style="width: 70%; margin-bottom: 8px;"></div>
            <div class="admin-skeleton admin-skeleton-text" style="width: 55%; margin-bottom: 12px;"></div>
            <div class="admin-skeleton admin-skeleton-text" style="width: 50%;"></div>
        </div>
    </div>
</div>

{{-- Empty State --}}
<div id="ordersEmpty" class="tickets-empty d-none">
    <i class="bi bi-inbox"></i>
    <h3>Không có đơn hàng nào</h3>
    <p class="text-warning small">⚠️ Admin endpoint chưa có. Đang dùng user endpoint tạm thời.</p>
</div>

{{-- Orders List (Card-based) --}}
<div id="ordersGrid" class="tickets-list"></div>

{{-- Pagination --}}
<div id="ordersPagination" class="mt-4"></div>

{{-- Order Detail Modal --}}
<div id="orderDetailModal" class="ticket-modal-overlay" role="dialog" aria-modal="true" aria-label="Chi tiết đơn hàng">
    <div class="ticket-modal">
        <div class="ticket-modal-header">
            <span class="ticket-modal-title">
                <i class="bi bi-receipt me-2"></i>Chi tiết đơn hàng
            </span>
            <button id="orderModalClose" class="ticket-modal-close" aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ticket-modal-body"></div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/components/skeleton.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/users/pages/tickets.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/orders.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/orders.js') }}?v={{ time() }}" defer></script>
@endpush
