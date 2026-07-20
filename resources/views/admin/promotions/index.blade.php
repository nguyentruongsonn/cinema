@extends('layouts.admin')

@section('title', 'Quản lý mã giảm giá')
@section('header_title', 'Quản lý mã giảm giá')
@section('header_subtitle', 'Xem và quản lý các mã giảm giá, khuyến mãi.')

@section('content')

{{-- Header & Filter Bar --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold flex-no-shrink">Danh sách mã giảm giá</h5>

        <form id="searchForm" class="d-flex gap-2 flex-grow-1 search-container-lg">
            <select id="categoryFilter" class="admin-filter-input filter-select-xs filter-rounded">
                <option value="all">Tất cả loại</option>
                <option value="ticket">Vé phim</option>
                <option value="food">Đồ ăn</option>
                <option value="combo">Combo</option>
            </select>
            <select id="statusFilter" class="admin-filter-input filter-select-sm filter-rounded">
                <option value="all">Trạng thái</option>
                <option value="1">Hoạt động</option>
                <option value="0">Tạm dừng</option>
            </select>
            <div class="input-group flex-grow-1">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm mã...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn ms-auto" id="btnCreatePromotion">
            <i class="bi bi-plus-lg"></i> Tạo mã giảm giá
        </button>
    </div>
</div>

{{-- Table --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center col-stt">STT</th>
                    <th class="col-code">Mã</th>
                    <th>Tên</th>
                    <th class="text-center col-category-sm">Loại</th>
                    <th class="text-center col-discount">Giảm giá</th>
                    <th class="text-center col-period">Thời hạn</th>
                    <th class="text-center col-min-order">Đơn tối thiểu</th>
                    <th class="text-center col-usage">Sử dụng</th>
                    <th class="text-center col-usage">Trạng thái</th>
                    <th class="text-center col-actions-lg">Hành động</th>
                </tr>
            </thead>
            <tbody id="promotionsTableBody">
                <!-- Skeleton Loading Rows -->
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-w-60 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-70 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-90 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-80 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-50 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-w-60 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-65 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-95 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-75 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-50 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-w-60 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-75 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-85 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-85 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-50 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-90"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-w-60 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-80 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-100 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-70 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-50 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-w-60 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-60 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-90 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-90 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-50 skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- Modal Thêm/Sửa --}}
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="promotionModalLabel"><i class="bi bi-tag me-2"></i>Tạo mã giảm giá mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="promotionForm">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="promotionIdInput" value="">

                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="promotionCode" class="form-label text-secondary">Mã giảm giá <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="promotionCode" required>
                            <small class="text-muted">VD: SUMMER2024, FILM50</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="promotionName" class="form-label text-secondary">Tên mã <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="promotionName" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="promotionCategory" class="form-label text-secondary">Áp dụng cho <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-white border-secondary" id="promotionCategory" required>
                                <option value="all">Tất cả</option>
                                <option value="ticket">Vé phim</option>
                                <option value="food">Đồ ăn</option>
                                <option value="combo">Combo</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="promotionDiscountType" class="form-label text-secondary">Loại giảm <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-white border-secondary" id="promotionDiscountType" required>
                                <option value="percent">Phần trăm (%)</option>
                                <option value="fixed">Cố định (VNĐ)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="promotionDiscountValue" class="form-label text-secondary">Giá trị giảm <span class="text-danger">*</span></label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="promotionDiscountValue" min="0" step="0.01" required>
                            <small class="text-muted" id="discountValueHint">Nhập % giảm (VD: 20 cho 20%)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="promotionMaxDiscountAmount" class="form-label text-secondary">Giảm tối đa (VNĐ)</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="promotionMaxDiscountAmount" min="0">
                            <small class="text-muted">Chỉ dùng khi giảm %</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="promotionDescription" class="form-label text-secondary">Mô tả</label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="promotionDescription" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="promotionMinOrderValue" class="form-label text-secondary">Đơn tối thiểu (VNĐ)</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="promotionMinOrderValue" min="0">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="promotionUsageLimit" class="form-label text-secondary">Giới hạn sử dụng</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="promotionUsageLimit" min="0">
                            <small class="text-muted">Để trống = không giới hạn</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="promotionStartDate" class="form-label text-secondary">Ngày bắt đầu</label>
                            <input type="datetime-local" class="form-control bg-dark text-white border-secondary" id="promotionStartDate">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="promotionEndDate" class="form-label text-secondary">Ngày kết thúc</label>
                            <input type="datetime-local" class="form-control bg-dark text-white border-secondary" id="promotionEndDate">
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="promotionStatus" checked>
                            <label class="form-check-label text-white" for="promotionStatus" id="promotionStatusLabel">Đang hoạt động</label>
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
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/promotions.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
