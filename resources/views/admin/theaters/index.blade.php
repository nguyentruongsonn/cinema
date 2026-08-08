@extends('layouts.admin')

@section('title', 'Quản lý rạp chiếu')
@section('header_title', 'Quản lý rạp chiếu')
@section('header_subtitle', 'Xem và quản lý danh sách các rạp chiếu phim.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="branchFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả chi nhánh</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select filter-select-md">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm rạp chiếu...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn admin-filter-primary-action" id="btnCreateTheater">
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
                <x-admin.skeleton-table cols="6" rows="5" :hasImage="false" />
            </tbody>
        </table>
    </div>

    {{-- Pagination (handled via JS) --}}
    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- ── Modal: Thêm / Sửa Rạp Chiếu ────────────────────────────────── --}}
{{-- ── Modal: Thêm / Sửa Rạp Chiếu ────────────────────────────────── --}}
<div class="modal fade" id="theaterModal" tabindex="-1" aria-labelledby="theaterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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

                    <div class="border-top border-secondary pt-3 mt-3 mb-3">
                        <h6 class="text-white mb-3"><i class="bi bi-cash-coin me-2 text-primary"></i>Cấu hình bảng giá (VNĐ)</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="theaterBasePrice" class="form-label text-secondary">Giá vé cơ bản <span class="text-danger">*</span></label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" id="theaterBasePrice" name="base_price" required min="0" value="70000">
                            </div>
                            <div class="col-md-6">
                                <label for="theaterHappyDayPrice" class="form-label text-secondary">Đồng giá Thứ 3 (Happy Day) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" id="theaterHappyDayPrice" name="happy_day_price" required min="0" value="50000">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="theaterWeekendSurcharge" class="form-label text-secondary">Phụ thu cuối tuần <span class="text-danger">*</span></label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" id="theaterWeekendSurcharge" name="weekend_surcharge" required min="0" value="10000">
                            </div>
                            <div class="col-md-4">
                                <label for="theaterHolidaySurcharge" class="form-label text-secondary">Phụ thu ngày lễ <span class="text-danger">*</span></label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" id="theaterHolidaySurcharge" name="holiday_surcharge" required min="0" value="20000">
                            </div>
                            <div class="col-md-4">
                                <label for="theaterStudentDiscount" class="form-label text-secondary">Giảm giá HSSV/Trẻ em <span class="text-danger">*</span></label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" id="theaterStudentDiscount" name="student_discount" required min="0" value="10000">
                            </div>
                        </div>
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
                    <button type="button" class="btn text-white btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu rạp chiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    @vite('resources/css/admin/pages/stats.css')
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/theaters.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
