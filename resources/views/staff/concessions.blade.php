@extends('layouts.admin')

@section('title', 'Quầy bắp nước')
@section('header_title', 'Quầy bắp nước')
@section('header_subtitle', 'Xử lý các sản phẩm và combo đã thanh toán tại rạp của bạn.')

@section('content')
<section class="staff-concession-page" aria-labelledby="concessionPageTitle">
    <div class="staff-concession-toolbar">
        <div>
            <p class="staff-concession-eyebrow">CONCESSION CONTROL</p>
            <h2 id="concessionPageTitle">Đơn chờ giao</h2>
        </div>
        <button type="button" class="btn-primary-custom" id="refreshConcessionOrders">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Làm mới
        </button>
    </div>
    <div class="staff-concession-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0 staff-concession-table">
                <thead><tr><th>Đơn hàng</th><th>Mặt hàng</th><th>Số lượng</th><th>Thời gian</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody id="concessionOrdersBody"><tr><td colspan="5" class="text-center py-5">Đang tải dữ liệu...</td></tr></tbody>
            </table>
        </div>
        <div id="concessionOrdersEmpty" class="staff-concession-empty d-none">Không có mặt hàng đang chờ giao.</div>
    </div>
</section>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/staff/concessions.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/staff/concessions.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
