@extends('layouts.admin')

@section('title', 'Quản lý mẫu sơ đồ ghế')
@section('header_title', 'Quản lý mẫu sơ đồ ghế')
@section('header_subtitle', 'Quản lý các mẫu sơ đồ ghế dùng cho phòng chiếu.')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner align-items-center w-100">
        <h5 class="mb-0 text-white fw-bold me-4"><i class="bi bi-grid-3x3-gap me-2"></i>Danh sách mẫu sơ đồ ghế</h5>

        <form action="{{ route('admin.seat-layout-templates.index') }}" method="GET" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="filter-group flex-grow-1" style="max-width: 420px;">
                <label for="search" class="filter-label" style="display:none;">Tìm kiếm</label>
                <div class="input-group">
                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="filter-input"
                        placeholder="Tên mẫu sơ đồ ghế..."
                        value="{{ request('search') }}"
                        style="border-radius: 6px 0 0 6px;"
                    >
                    <button class="btn btn-outline-secondary border-0 slt-search-btn" type="submit" aria-label="Tìm kiếm">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>

            @if(request('search'))
                <a href="{{ route('admin.seat-layout-templates.index') }}" class="btn-shortcut text-decoration-none d-inline-flex align-items-center">
                    <i class="bi bi-x-circle me-1"></i> Xóa lọc
                </a>
            @endif
        </form>

        <button type="button" id="btnOpenCreateSeatLayoutTemplate" class="btn-primary-custom ms-auto border-0">
            <i class="bi bi-plus-lg"></i> Tạo mẫu sơ đồ ghế
        </button>
    </div>
</div>

{{-- ── Dòng 3: Tabs + Table ─────────────────────────────────────────── --}}
<div class="chart-card">
    {{-- Tabs: client-side Bootstrap, không đổi URL --}}
    <ul class="nav nav-tabs combo-tabs mb-4" id="sltTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-all" data-bs-toggle="tab" data-bs-target="#pane-all" type="button" role="tab" aria-controls="pane-all" aria-selected="true">
                Tất cả <span class="badge bg-secondary ms-1">{{ $allTemplates->total() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-published" data-bs-toggle="tab" data-bs-target="#pane-published" type="button" role="tab" aria-controls="pane-published" aria-selected="false">
                Đã xuất bản <span class="badge bg-secondary ms-1">{{ $publishedTemplates->total() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-draft" data-bs-toggle="tab" data-bs-target="#pane-draft" type="button" role="tab" aria-controls="pane-draft" aria-selected="false">
                Bản nháp <span class="badge bg-secondary ms-1">{{ $draftTemplates->total() }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="sltTabContent">
        <div class="tab-pane fade show active" id="pane-all" role="tabpanel" aria-labelledby="tab-all">
            @include('admin.seat-layout-templates.partials.template-table', ['templates' => $allTemplates, 'emptyText' => 'Không tìm thấy mẫu sơ đồ ghế nào.'])
        </div>
        <div class="tab-pane fade" id="pane-published" role="tabpanel" aria-labelledby="tab-published">
            @include('admin.seat-layout-templates.partials.template-table', ['templates' => $publishedTemplates, 'emptyText' => 'Không có mẫu sơ đồ ghế nào đã xuất bản.'])
        </div>
        <div class="tab-pane fade" id="pane-draft" role="tabpanel" aria-labelledby="tab-draft">
            @include('admin.seat-layout-templates.partials.template-table', ['templates' => $draftTemplates, 'emptyText' => 'Không có mẫu sơ đồ ghế nào ở bản nháp.'])
        </div>
    </div>
</div>

{{-- ── Modal: Thêm / Sửa Mẫu Sơ Đồ Ghế ─────────────────────────────── --}}
<div class="modal fade" id="seatLayoutTemplateModal" tabindex="-1" aria-labelledby="seatLayoutTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="seatLayoutTemplateModalLabel">
                    <i class="bi bi-grid-3x3-gap me-2"></i>Tạo mẫu sơ đồ ghế
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="seatLayoutTemplateForm" action="{{ route('admin.seat-layout-templates.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="seatLayoutTemplateFormMethod" value="POST">
                <input type="hidden" name="seat_layout_template_id" id="seatLayoutTemplateIdInput" value="">

                <div class="modal-body">
                    <div id="seatLayoutTemplateFormAlert" class="alert alert-danger d-none" role="alert"></div>

                    {{-- Tên mẫu --}}
                    <div class="mb-3">
                        <label for="templateName" class="form-label text-secondary">Tên mẫu sơ đồ ghế <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="templateName" name="template_name" required>
                        <div class="invalid-feedback" data-error-for="template_name"></div>
                    </div>

                    {{-- Ma trận ghế (dropdown preset) --}}
                    <div class="mb-3">
                        <label for="seatMatrix" class="form-label text-secondary">
                            Ma trận ghế <span class="text-danger">*</span>
                        </label>
                        <select class="form-select bg-dark text-white border-secondary" id="seatMatrix" name="seat_matrix" required>
                            <option value="">-- Chọn ma trận ghế --</option>
                            <option value="12x12" data-rows="12" data-cols="12" data-capacity="144">12×12 — Sức chứa tối đa 144 chỗ ngồi</option>
                            <option value="13x13" data-rows="13" data-cols="13" data-capacity="169">13×13 — Sức chứa tối đa 169 chỗ ngồi</option>
                            <option value="14x14" data-rows="14" data-cols="14" data-capacity="196">14×14 — Sức chứa tối đa 196 chỗ ngồi</option>
                            <option value="15x15" data-rows="15" data-cols="15" data-capacity="225">15×15 — Sức chứa tối đa 225 chỗ ngồi</option>
                        </select>
                        <div class="invalid-feedback" data-error-for="seat_matrix"></div>

                        {{-- Info badge hiển thị khi chọn --}}
                        <div id="matrixInfo" class="mt-2 d-none">
                            <div class="d-flex gap-3 flex-wrap">
                                <span class="badge d-flex align-items-center gap-1" style="background: rgba(229,9,20,0.15); color:#e50914; padding: 6px 12px; font-size:0.8rem;">
                                    <i class="bi bi-grid-3x3"></i>
                                    <span id="matrixSize">—</span>
                                </span>
                                <span class="badge d-flex align-items-center gap-1" style="background: rgba(59,130,246,0.15); color:#60a5fa; padding: 6px 12px; font-size:0.8rem;">
                                    <i class="bi bi-person-fill"></i>
                                    Tối đa <span id="matrixCapacity" class="ms-1 fw-bold">—</span> chỗ
                                </span>
                                <span class="badge d-flex align-items-center gap-1" style="background: rgba(16,185,129,0.15); color:#34d399; padding: 6px 12px; font-size:0.8rem;">
                                    <i class="bi bi-list-ol"></i>
                                    Tối đa <span id="matrixRows" class="ms-1 fw-bold">—</span> hàng
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Phân bổ loại ghế --}}
                    <div class="mb-1">
                        <label class="form-label text-secondary mb-2">
                            Phân bổ hàng ghế
                            <span class="text-white-50 small fw-normal ms-1">(Tổng không vượt quá số hàng của ma trận)</span>
                        </label>
                    </div>

                    {{-- Row sum progress bar --}}
                    <div class="mb-3 p-3 rounded" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-secondary">Tổng hàng đã dùng</span>
                            <span id="rowSumDisplay" class="small fw-bold text-white">
                                <span id="rowSumUsed">0</span> / <span id="rowSumMax">—</span> hàng
                            </span>
                        </div>
                        <div class="progress" style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px;">
                            <div id="rowSumBar" class="progress-bar" role="progressbar" style="width: 0%; border-radius: 3px; background: linear-gradient(90deg, #22c55e, #16a34a); transition: width 0.3s ease;"></div>
                        </div>
                        <div id="rowSumWarning" class="small text-danger mt-2 d-none">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <span id="rowSumWarningText"></span>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <label for="regularSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                <span class="d-inline-block" style="width:10px;height:10px;border-radius:2px;background:#60a5fa;"></span>
                                Ghế thường
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="regularSeatRows" name="regular_seat_rows" min="0" value="0">
                            <div class="invalid-feedback" data-error-for="regular_seat_rows"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="vipSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                <span class="d-inline-block" style="width:10px;height:10px;border-radius:2px;background:#f59e0b;"></span>
                                Ghế VIP
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="vipSeatRows" name="vip_seat_rows" min="0" value="0">
                            <div class="invalid-feedback" data-error-for="vip_seat_rows"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="coupleSeatRows" class="form-label text-secondary d-flex align-items-center gap-1">
                                <span class="d-inline-block" style="width:10px;height:10px;border-radius:2px;background:#ec4899;"></span>
                                Ghế đôi
                            </label>
                            <input type="number" class="form-control bg-dark text-white border-secondary seat-row-input" id="coupleSeatRows" name="couple_seat_rows" min="0" value="0">
                            <div class="invalid-feedback" data-error-for="couple_seat_rows"></div>
                        </div>
                    </div>

                    {{-- Mô tả --}}
                    <div class="mb-3">
                        <label for="description" class="form-label text-secondary">Mô tả</label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="description" name="description" rows="2" placeholder="Mô tả ngắn về mẫu sơ đồ..."></textarea>
                        <div class="invalid-feedback" data-error-for="description"></div>
                    </div>

                    {{-- Trạng thái --}}
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="templateStatus" name="status" value="1" checked>
                            <label class="form-check-label text-white" for="templateStatus">Cho phép hoạt động</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white slt-btn-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="sltSubmitBtn">Lưu mẫu sơ đồ ghế</button>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/seat-layout-templates.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/seat-layout-templates.js') }}?v={{ time() }}" defer></script>
@endpush
