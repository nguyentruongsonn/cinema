@extends('layouts.admin')

@section('title', 'Quản lý lịch chiếu')
@section('header_title', 'Quản lý lịch chiếu')
@section('header_subtitle', 'Sắp xếp lịch chiếu phim tại các rạp.')

@section('content')

{{-- ── Dòng 1: Header & Filter ─────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner align-items-center w-100">
        <h5 class="mb-0 text-white fw-bold me-4">
            <i class="bi bi-calendar-event me-2"></i>Lịch chiếu
        </h5>

        <form id="searchForm" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="filter-group">
                <input type="date" id="dateFilter" class="filter-input" style="width: 160px;">
            </div>

            <div class="filter-group" style="max-width: 260px; flex: 1;">
                <select id="movieFilter" class="form-select filter-input">
                    <option value="">Tất cả phim</option>
                </select>
            </div>

            <div class="filter-group" style="max-width: 200px;">
                <select id="theaterFilter" class="form-select filter-input">
                    <option value="">Tất cả rạp</option>
                </select>
            </div>

            <button class="btn btn-outline-secondary border-0" type="submit"
                    style="background: rgba(255,255,255,0.05); color: #fff;">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>
</div>

{{-- ── Dòng 2: Thêm lịch chiếu (2 tab) ────────────────── --}}
<div class="chart-card mb-4" id="addShowtimePanel">
    <ul class="nav nav-tabs combo-tabs mb-4" id="addModeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-multi" data-bs-toggle="tab" data-bs-target="#pane-multi"
                    type="button" role="tab">
                <i class="bi bi-calendar-range me-1"></i> Thêm nhiều ngày
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-single" data-bs-toggle="tab" data-bs-target="#pane-single"
                    type="button" role="tab">
                <i class="bi bi-calendar-date me-1"></i> Thêm theo ngày
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ── Tab 1: Thêm nhiều ngày ──────────────────── --}}
        <div class="tab-pane fade show active" id="pane-multi" role="tabpanel">
            <form id="multiDayForm">
                <div class="row g-3">
                    {{-- Chọn phim --}}
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Phim <span class="text-danger">*</span></label>
                        <select class="form-select filter-input" id="mMovieId" name="movie_id" required>
                            <option value="">-- Chọn phim --</option>
                        </select>
                    </div>

                    {{-- Chọn rạp --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Rạp chiếu <span class="text-danger">*</span></label>
                        <select class="form-select filter-input" id="mTheaterId" required>
                            <option value="">-- Chọn rạp --</option>
                        </select>
                    </div>

                    {{-- Chọn phòng --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Phòng chiếu <span class="text-danger">*</span></label>
                        <select class="form-select filter-input" id="mScreenId" name="screen_id" disabled required>
                            <option value="">-- Chọn phòng --</option>
                        </select>
                    </div>

                    {{-- Ngày từ / đến --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Ngày bắt đầu <span class="text-danger">*</span></label>
                        <input type="date" class="filter-input w-100" id="mDateFrom" name="date_from" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Ngày kết thúc <span class="text-danger">*</span></label>
                        <input type="date" class="filter-input w-100" id="mDateTo" name="date_to" required>
                    </div>

                    {{-- Các giờ chiếu --}}
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Giờ chiếu <span class="text-danger">*</span>
                            <span class="text-white-50 ms-1" style="font-size:0.72rem;">(Ấn Enter để thêm giờ)</span>
                        </label>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2" id="timeSlotTags"></div>
                        <div class="d-flex gap-2">
                            <input type="time" class="filter-input" id="mTimeInput" style="width:130px;">
                            <button type="button" class="btn btn-sm" id="mAddTimeBtn"
                                    style="background:rgba(255,255,255,0.08);color:#fff;border:1px solid rgba(255,255,255,0.12);border-radius:6px;">
                                <i class="bi bi-plus"></i> Thêm giờ
                            </button>
                        </div>
                    </div>

                    {{-- Giá & Định dạng --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Giá vé (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" class="filter-input w-100" id="mPrice" name="price" min="0" step="1000" placeholder="90000" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Định dạng hình ảnh</label>
                        <select class="form-select filter-input" id="mFormatId" name="format_id">
                            <option value="">-- Mặc định --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Định dạng âm thanh</label>
                        <select class="form-select filter-input" id="mSoundId" name="sound_id">
                            <option value="">-- Mặc định --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Phụ đề</label>
                        <select class="form-select filter-input" id="mSubtitleId" name="subtitle_id">
                            <option value="">-- Không --</option>
                        </select>
                    </div>

                    {{-- Submit --}}
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" id="previewMultiBtn"
                                class="btn me-2"
                                style="background:rgba(255,255,255,0.07);color:#d4d4d8;border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 1.25rem;font-size:0.88rem;font-weight:600;">
                            <i class="bi bi-eye me-1"></i> Xem trước
                        </button>
                        <button type="submit" class="btn-primary-custom border-0">
                            <i class="bi bi-calendar-plus me-1"></i> Tạo lịch chiếu
                        </button>
                    </div>
                </div>

                {{-- Preview block --}}
                <div id="multiPreviewBlock" class="mt-4 d-none">
                    <div class="text-secondary fw-semibold mb-2" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.07em;">
                        Xem trước lịch chiếu sẽ được tạo
                    </div>
                    <div id="multiPreviewList" class="d-flex flex-wrap gap-2"></div>
                </div>
            </form>
        </div>

        {{-- ── Tab 2: Thêm theo ngày ───────────────────── --}}
        <div class="tab-pane fade" id="pane-single" role="tabpanel">
            <form id="singleDayForm">
                <div class="row g-3">
                    {{-- Chọn phim --}}
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Phim <span class="text-danger">*</span></label>
                        <select class="form-select filter-input" id="sMovieId" name="movie_id" required>
                            <option value="">-- Chọn phim --</option>
                        </select>
                    </div>

                    {{-- Ngày chiếu --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Ngày chiếu <span class="text-danger">*</span></label>
                        <input type="date" class="filter-input w-100" id="sDate" name="date" required>
                    </div>

                    {{-- Giá vé --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Giá vé (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" class="filter-input w-100" id="sPrice" name="price" min="0" step="1000" placeholder="90000" required>
                    </div>

                    {{-- Định dạng --}}
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Định dạng hình ảnh</label>
                        <select class="form-select filter-input" id="sFormatId" name="format_id">
                            <option value="">-- Mặc định --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Định dạng âm thanh</label>
                        <select class="form-select filter-input" id="sSoundId" name="sound_id">
                            <option value="">-- Mặc định --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Phụ đề</label>
                        <select class="form-select filter-input" id="sSubtitleId" name="subtitle_id">
                            <option value="">-- Không --</option>
                        </select>
                    </div>

                    {{-- Danh sách suất chiếu trong ngày --}}
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="sStatusToggle" checked>
                            <label class="form-check-label text-white" for="sStatusToggle">Mở bán vé</label>
                        </div>
                    </div>

                    {{-- Dynamic suất chiếu rows --}}
                    <div class="col-12">
                        <label class="form-label text-secondary">Danh sách suất chiếu
                            <span class="text-white-50 ms-1" style="font-size:0.72rem;">(Thêm nhiều suất trong cùng ngày)</span>
                        </label>
                        <div id="sSlotRows">
                            {{-- JS-injected rows --}}
                        </div>
                        <button type="button" id="sAddSlotBtn"
                                class="btn btn-sm mt-2"
                                style="background:rgba(255,255,255,0.06);color:#a1a1aa;border:1px dashed rgba(255,255,255,0.15);border-radius:6px;width:100%;padding:0.5rem;">
                            <i class="bi bi-plus-circle me-1"></i> Thêm suất chiếu
                        </button>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn-primary-custom border-0">
                            <i class="bi bi-calendar-plus me-1"></i> Tạo lịch chiếu
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Dòng 3: Bảng danh sách lịch chiếu ───────────────── --}}
<div class="chart-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-white fw-semibold mb-0"><i class="bi bi-list-ul me-2"></i>Danh sách lịch chiếu</h6>
        <span class="badge" style="background:rgba(255,255,255,0.08);color:#a1a1aa;font-size:0.8rem;" id="totalCount"></span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background:transparent;">
            <thead style="border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 50px;">STT</th>
                    <th class="text-secondary fw-semibold border-0">Thời gian chiếu</th>
                    <th class="text-secondary fw-semibold border-0">Phim</th>
                    <th class="text-secondary fw-semibold border-0">Rạp & Phòng chiếu</th>
                    <th class="text-secondary fw-semibold border-0">Giá & Định dạng</th>
                    <th class="text-secondary fw-semibold border-0 text-center" style="width: 110px;">Trạng thái</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody id="showtimesTableBody">
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <div class="spinner-border text-secondary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);" id="paginationContainer"></div>
</div>

{{-- ── Modal: Sửa lịch chiếu ─────────────────────────────── --}}
<div class="modal fade" id="showtimeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0" id="showtimeModalContent">
            <div class="modal-header" id="showtimeModalHeader">
                <h5 class="modal-title" id="showtimeModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Cập nhật lịch chiếu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="showtimeForm">
                <input type="hidden" id="showtimeFormMethod" value="PUT">
                <input type="hidden" name="showtime_id" id="showtimeIdInput" value="">

                <div class="modal-body" id="showtimeModalBody">
                    <div class="row mb-3 g-3">
                        <div class="col-md-12">
                            <label class="form-label text-secondary">Phim <span class="text-danger">*</span></label>
                            <select class="form-select filter-input" id="formMovieId" name="movie_id" required>
                                <option value="">-- Chọn phim --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Rạp chiếu <span class="text-danger">*</span></label>
                            <select class="form-select filter-input" id="formTheaterId">
                                <option value="">-- Chọn rạp --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Phòng chiếu <span class="text-danger">*</span></label>
                            <select class="form-select filter-input" id="formScreenId" name="screen_id" required disabled>
                                <option value="">-- Chọn phòng chiếu --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Thời gian chiếu <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="filter-input w-100" id="formScheduledAt" name="scheduled_at" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Giá vé cơ bản (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" class="filter-input w-100" id="formPrice" name="price" min="0" step="1000" required>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <label class="form-label text-secondary">Định dạng hình ảnh</label>
                            <select class="form-select filter-input" id="formFormatId" name="format_id">
                                <option value="">-- Mặc định --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary">Định dạng âm thanh</label>
                            <select class="form-select filter-input" id="formSoundId" name="sound_id">
                                <option value="">-- Mặc định --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary">Phụ đề</label>
                            <select class="form-select filter-input" id="formSubtitleId" name="subtitle_id">
                                <option value="">-- Trống --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label text-secondary mb-0">Trạng thái bán vé</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="formStatus" name="status" value="1" checked>
                                <label class="form-check-label text-white small" for="formStatus">Mở bán</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="showtimeModalFooter">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal"
                            style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 1.25rem;font-weight:600;">
                        Hủy bỏ
                    </button>
                    <button type="submit" class="btn-primary-custom border-0" id="showtimeSubmitBtn">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/showtimes.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/showtimes.js') }}?v={{ time() }}" defer></script>
@endpush
