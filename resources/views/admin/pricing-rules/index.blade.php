@extends('layouts.admin')

@section('title', 'Cấu hình bảng giá')
@section('header_title', 'Cấu hình bảng giá')
@section('header_subtitle', 'Quản lý động danh sách ngày lễ, loại ngày theo thứ và khung giờ chiếu phim.')

@section('content')

{{-- ── Dòng 1: Filter Bar (Chứa nút thêm động của các Tab) ────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <span class="text-white-50"><i class="bi bi-gear me-2"></i>Quy tắc cấu hình giá vé</span>
        </div>
        <div id="tabHeaderActions" class="admin-filter-primary-action"></div>
    </div>
</div>

{{-- ── Dòng 2: Tabs + Content ─────────────────────────────────────── --}}
<div class="admin-table-container">
    <ul class="nav nav-tabs combo-tabs mb-4" id="pricingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-holidays" data-bs-toggle="tab" data-bs-target="#pane-holidays" type="button" role="tab" aria-controls="pane-holidays" aria-selected="true">
                Ngày lễ đặc biệt
                <span class="badge ms-1 text-white bg-primary" id="count-holidays">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-dayrules" data-bs-toggle="tab" data-bs-target="#pane-dayrules" type="button" role="tab" aria-controls="pane-dayrules" aria-selected="false">
                Loại ngày theo Thứ
                <span class="badge ms-1 text-white bg-success" id="count-dayrules">7</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-timeslots" data-bs-toggle="tab" data-bs-target="#pane-timeslots" type="button" role="tab" aria-controls="pane-timeslots" aria-selected="false">
                Khung giờ chiếu
                <span class="badge ms-1 text-white bg-warning" id="count-timeslots">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pricingTabContent">
        
        {{-- Tab 1: Holidays --}}
        <div class="tab-pane fade show active" id="pane-holidays" role="tabpanel" aria-labelledby="tab-holidays">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-center col-stt">STT</th>
                            <th>Tên ngày lễ</th>
                            <th class="text-center">Ngày tháng (DD-MM)</th>
                            <th class="text-center">Năm áp dụng</th>
                            <th class="text-end">Mức phụ thu (VNĐ)</th>
                            <th class="text-center admin-col-status">Trạng thái</th>
                            <th class="text-center col-actions">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="holidaysTableBody">
                        <x-admin.skeleton-table cols="7" rows="4" :hasImage="false" />
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 2: Day Rules --}}
        <div class="tab-pane fade" id="pane-dayrules" role="tabpanel" aria-labelledby="tab-dayrules">
            <form id="dayRulesForm">
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Thứ trong tuần</th>
                                <th>Phân loại loại ngày (Rule)</th>
                            <th class="text-end admin-col-surcharge">Phụ thu mặc định (VNĐ)</th>
                            </tr>
                        </thead>
                        <tbody id="dayRulesTableBody">
                            <x-admin.skeleton-table cols="3" rows="7" :hasImage="false" />
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn-primary-custom border-0" id="btnSaveDayRules">
                        <i class="bi bi-save me-2"></i>Lưu thay đổi quy tắc
                    </button>
                </div>
            </form>
        </div>

        {{-- Tab 3: Time Slots --}}
        <div class="tab-pane fade" id="pane-timeslots" role="tabpanel" aria-labelledby="tab-timeslots">
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="text-center col-stt">STT</th>
                            <th>Tên khung giờ</th>
                            <th class="text-center">Giờ bắt đầu</th>
                            <th class="text-center">Giờ kết thúc</th>
                            <th class="text-end">Mức phụ thu (VNĐ)</th>
                            <th class="text-center admin-col-status">Trạng thái</th>
                            <th class="text-center col-actions">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="timeSlotsTableBody">
                        <x-admin.skeleton-table cols="7" rows="3" :hasImage="false" />
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- ── Modal: Tạo / Sửa Ngày Lễ ────────────────────────────────────── --}}
<div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="holidayModalLabel">
                    <i class="bi bi-calendar-event me-2 admin-accent-icon"></i>Tạo ngày lễ mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="holidayForm">
                <input type="hidden" id="holidayFormMethod" name="_method" value="POST">
                <input type="hidden" id="holidayIdInput" name="id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Tên ngày lễ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="holidayName" name="name" placeholder="VD: Quốc Khánh" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Mức phụ thu (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="holidaySurcharge" name="surcharge" required min="0" value="0">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Ngày tháng (DD-MM) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="holidayDate" name="date" placeholder="VD: 02-09" pattern="^[0-3][0-9]-[0-1][0-9]$" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Năm áp dụng</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="holidayYear" name="year" min="2020" max="2100" placeholder="VD: 2026">
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                                <input class="form-check-input admin-form-check-clickable" type="checkbox" id="holidayStatus" name="status" value="1" checked>
                            <label class="form-check-label text-white" for="holidayStatus">Kích hoạt hoạt động</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white admin-modal-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu ngày lễ</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Tạo / Sửa Khung Giờ ──────────────────────────────────── --}}
<div class="modal fade" id="timeSlotModal" tabindex="-1" aria-labelledby="timeSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="timeSlotModalLabel">
                    <i class="bi bi-clock me-2 admin-accent-icon"></i>Tạo khung giờ mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="timeSlotForm">
                <input type="hidden" id="timeSlotFormMethod" name="_method" value="POST">
                <input type="hidden" id="timeSlotIdInput" name="id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Tên khung giờ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="timeSlotName" name="name" placeholder="VD: Khuya muộn" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Mức phụ thu (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="timeSlotSurcharge" name="surcharge" required min="0" value="0">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Giờ bắt đầu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="timeSlotStart" name="start_time" placeholder="VD: 22:00:00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Giờ kết thúc <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-white border-secondary" id="timeSlotEnd" name="end_time" placeholder="VD: 07:59:59" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                                <input class="form-check-input admin-form-check-clickable" type="checkbox" id="timeSlotStatus" name="status" value="1" checked>
                            <label class="form-check-label text-white" for="timeSlotStatus">Kích hoạt hoạt động</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white admin-modal-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu khung giờ</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/admin/pages/pricing-rules.js') }}?v={{ filemtime(public_path('js/admin/pages/pricing-rules.js')) }}" defer></script>
@endpush
