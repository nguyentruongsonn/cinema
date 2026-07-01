@extends('layouts.admin')

@section('title', 'Quản lý rạp chiếu')
@section('header_title', 'Quản lý rạp chiếu')
@section('header_subtitle', 'Xem và quản lý danh sách các rạp chiếu phim.')

@section('content')

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner align-items-center w-100">
        <h5 class="mb-0 text-white fw-bold me-4"><i class="bi bi-camera-reels me-2"></i>Danh sách rạp chiếu</h5>
        
        <form id="searchForm" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="filter-group flex-grow-1" style="max-width: 400px;">
                <label for="search" class="filter-label" style="display:none;">Tìm kiếm</label>
                <div class="input-group">
                    <input type="text" id="search" class="filter-input" placeholder="Tìm rạp chiếu..." style="border-radius: 6px 0 0 6px;">
                    <button class="btn btn-outline-secondary border-0" style="background: rgba(255,255,255,0.05); border-radius: 0 6px 6px 0;" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>

        <button type="button" class="btn-primary-custom ms-auto border-0" id="btnCreateTheater">
            <i class="bi bi-plus-lg"></i> Thêm rạp chiếu
        </button>
    </div>
</div>

{{-- ── Dòng 3: Table ───────────────────────────────────────────────── --}}
<div class="chart-card">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
            <thead style="border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                    <th class="text-secondary fw-semibold border-0">Tên rạp</th>
                    <th class="text-secondary fw-semibold border-0">Chi nhánh</th>
                    <th class="text-secondary fw-semibold border-0">Địa chỉ</th>
                    <th class="text-center text-secondary fw-semibold border-0">Hoạt động</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody id="theatersTableBody">
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <div class="spinner-border text-secondary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination (handled via JS) --}}
    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);" id="paginationContainer"></div>
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
                            <input class="form-check-input" type="checkbox" id="theaterStatus" name="is_active" value="1" checked style="cursor: pointer;">
                            <label class="form-check-label text-white" for="theaterStatus" style="cursor: pointer;">Cho phép hoạt động</label>
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
                    <button type="button" class="btn text-white" style="background: rgba(255,255,255,0.1);" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu rạp chiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/theaters.js') }}?v={{ time() }}" defer></script>
@endpush
