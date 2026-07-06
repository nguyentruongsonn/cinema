@extends('layouts.admin')

@section('title', 'Quản lý lịch chiếu')
@section('header_title', 'Quản lý lịch chiếu')

@section('content')

{{-- ── Dòng 1: Header, Filters & Add Button ─────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center justify-content-between w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0; min-width: 120px;">
            Lịch chiếu
        </h5>

        <form id="filterForm" class="d-flex flex-grow-1 align-items-center gap-2" style="max-width: 900px;">
            <select id="branchFilter" class="admin-filter-select" style="min-width: 140px; max-width: 180px;">
                <option value="">Tất cả chi nhánh</option>
            </select>

            <select id="theaterFilter" class="admin-filter-select" style="min-width: 130px; max-width: 170px;">
                <option value="">Tất cả rạp</option>
            </select>

            <input type="date" id="dateFilter" class="admin-filter-input" style="width: 150px;">

            <select id="statusFilter" class="admin-filter-select" style="width: 120px;">
                <option value="">Trạng thái</option>
                <option value="1">Mở bán</option>
                <option value="0">Đóng</option>
            </select>

            <button class="admin-filter-btn" type="submit" style="padding: 0.5rem 1rem;">
                <i class="bi bi-search"></i>
            </button>
        </form>

        <button type="button" class="admin-action-btn ms-auto" id="addShowtimeBtn" style="flex-shrink: 0;">
            <i class="bi bi-plus-circle"></i> Thêm suất chiếu
        </button>
    </div>
</div>

{{-- ── Dòng 2: Bảng danh sách phim ────────────────────── --}}
<div class="admin-table-container mb-4" id="moviesPanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-white fw-semibold mb-0">Danh sách phim</h6>
        <span class="admin-badge admin-badge-info" id="movieCount"></span>
    </div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center movie-stt">STT</th>
                    <th class="movie-info-cell">Phim</th>
                    <th class="text-center movie-duration">Thời lượng</th>
                    <th class="movie-categories">Thể loại</th>
                </tr>
            </thead>
            <tbody id="moviesTableBody">
                <tr>
                    <td colspan="4" class="text-center py-5 text-muted">
                        <div class="spinner-border text-secondary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ── Dòng 3: Bảng danh sách suất chiếu của phim ──────── --}}
<div class="admin-table-container" id="showtimesPanel" style="display: none;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-white fw-semibold mb-0">
            <i class="bi bi-clock-history me-2"></i>Suất chiếu: <span id="selectedMovieTitle" class="text-primary"></span>
        </h6>
        <div class="d-flex align-items-center gap-2">
            <span class="admin-badge admin-badge-info" id="showtimeCount"></span>
            <button type="button" id="backToMoviesBtn" class="admin-action-btn">
                Danh sách phim
            </button>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center showtime-stt-col">STT</th>
                    <th class="showtime-time-col">Thời gian</th>
                    <th class="showtime-screen-col">Phòng</th>
                    <th class="text-center showtime-capacity-col">Ghế</th>
                    <th class="showtime-format-col">Định dạng</th>
                    <th class="text-center showtime-active-col">Hoạt động</th>
                    <th class="text-center showtime-actions-col">Hành động</th>
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

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- ── Modal: Thêm suất chiếu (2 tabs) ─────────────────── --}}
<div class="modal fade" id="addShowtimeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus me-2"></i>Thêm suất chiếu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                {{-- Tabs --}}
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
                                {{-- Left Column: Form Fields (8 cols) --}}
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        {{-- Chọn phim --}}
                                        <div class="col-md-12">
                                            <label class="form-label text-secondary">Phim <span class="text-danger">*</span></label>
                                            <select class="form-select bg-dark text-white border-secondary" id="mMovieId" name="movie_id" required>
                                                <option value="">-- Chọn phim --</option>
                                            </select>
                                        </div>

                                        {{-- Chọn rạp --}}
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary">Rạp chiếu <span class="text-danger">*</span></label>
                                            <select class="form-select bg-dark text-white border-secondary" id="mTheaterId" required>
                                                <option value="">-- Chọn rạp --</option>
                                            </select>
                                        </div>

                                        {{-- Chọn phòng --}}
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary">Phòng chiếu <span class="text-danger">*</span></label>
                                            <select class="form-select bg-dark text-white border-secondary" id="mScreenId" name="screen_id" disabled required>
                                                <option value="">-- Chọn phòng --</option>
                                            </select>
                                        </div>

                                        {{-- Ngày từ / đến --}}
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary">Ngày bắt đầu <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control bg-dark text-white border-secondary" id="mDateFrom" name="date_from" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary">Ngày kết thúc <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control bg-dark text-white border-secondary" id="mDateTo" name="date_to" required>
                                        </div>

                                        {{-- Các giờ chiếu --}}
                                        <div class="col-md-8">
                                            <label class="form-label text-secondary">Giờ chiếu <span class="text-danger">*</span>
                                                <span class="time-input-label-hint ms-1">(Ấn Enter để thêm giờ)</span>
                                            </label>
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2" id="timeSlotTags"></div>
                                            <div class="d-flex gap-2">
                                                <input type="time" class="form-control bg-dark text-white border-secondary time-input-sm" id="mTimeInput">
                                                <button type="button" class="btn btn-sm btn-add-time" id="mAddTimeBtn">
                                                    <i class="bi bi-plus"></i> Thêm giờ
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Định dạng --}}
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary">Định dạng hình ảnh</label>
                                            <select class="form-select bg-dark text-white border-secondary" id="mFormatId" name="format_id">
                                                <option value="">-- Mặc định --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary">Phiên bản phim</label>
                                            <select class="form-select bg-dark text-white border-secondary" id="mVersionTypeId" name="version_type_id">
                                                <option value="">-- Chọn phiên bản --</option>
                                            </select>
                                        </div>

                                        {{-- Submit --}}
                                        <div class="col-12 d-flex justify-content-end">
                                            <button type="button" id="previewMultiBtn" class="btn btn-preview me-2">
                                                <i class="bi bi-eye me-1"></i> Xem trước
                                            </button>
                                            <button type="submit" class="btn-primary-custom border-0">
                                                <i class="bi bi-calendar-plus me-1"></i> Tạo lịch chiếu
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Right Column: Movie Info (4 cols) --}}
                                <div class="col-md-4">
                                    <div id="mMovieInfo" class="movie-info-sidebar">
                                        <img id="mMoviePoster" class="movie-poster-sidebar" src="/images/default-poster.jpg" alt="Poster">
                                        <h6 class="movie-title-sidebar" id="mMovieTitle">Chọn phim để xem thông tin</h6>
                                        <div class="movie-meta-sidebar">
                                            <div class="meta-item">
                                                <i class="bi bi-clock"></i>
                                                <span><span id="mMovieDuration">—</span> phút</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="bi bi-calendar-event"></i>
                                                <span id="mMovieRelease">—</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="bi bi-calendar-x"></i>
                                                <span id="mMovieEnd">—</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Preview block --}}
                            <div id="multiPreviewBlock" class="mt-4 d-none">
                                <div class="preview-label mb-2">
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
                                {{-- Left Column: Form Fields (8 cols) --}}
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        {{-- Chọn phim --}}
                                        <div class="col-md-12">
                                            <label class="form-label text-secondary">Phim <span class="text-danger">*</span></label>
                                            <select class="form-select bg-dark text-white border-secondary" id="sMovieId" name="movie_id" required>
                                                <option value="">-- Chọn phim --</option>
                                            </select>
                                        </div>

                                        {{-- Ngày chiếu --}}
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary">Ngày chiếu <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control bg-dark text-white border-secondary" id="sDate" name="date" required>
                                        </div>

                                        {{-- Định dạng --}}
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary">Định dạng hình ảnh</label>
                                            <select class="form-select bg-dark text-white border-secondary" id="sFormatId" name="format_id">
                                                <option value="">-- Mặc định --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary">Phiên bản phim</label>
                                            <select class="form-select bg-dark text-white border-secondary" id="sVersionTypeId" name="version_type_id">
                                                <option value="">-- Chọn phiên bản --</option>
                                            </select>
                                        </div>

                                        {{-- Danh sách suất chiếu trong ngày --}}
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label text-secondary mb-0">Danh sách suất chiếu
                                                    <span class="time-input-label-hint ms-1">(Thêm nhiều suất trong cùng ngày)</span>
                                                </label>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="sStatusToggle" checked>
                                                    <label class="form-check-label text-white" for="sStatusToggle">Mở bán vé</label>
                                                </div>
                                            </div>
                                            <div id="sSlotRows">
                                                {{-- JS-injected rows --}}
                                            </div>
                                            <button type="button" id="sAddSlotBtn" class="btn btn-sm mt-2 btn-add-slot">
                                                <i class="bi bi-plus-circle me-1"></i> Thêm suất chiếu
                                            </button>
                                        </div>

                                        {{-- Submit --}}
                                        <div class="col-12 d-flex justify-content-end">
                                            <button type="submit" class="btn-primary-custom border-0">
                                                <i class="bi bi-calendar-plus me-1"></i> Tạo lịch chiếu
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Right Column: Movie Info (4 cols) --}}
                                <div class="col-md-4">
                                    <div id="sMovieInfo" class="movie-info-sidebar">
                                        <img id="sMoviePoster" class="movie-poster-sidebar" src="/images/default-poster.jpg" alt="Poster">
                                        <h6 class="movie-title-sidebar" id="sMovieTitle">Chọn phim để xem thông tin</h6>
                                        <div class="movie-meta-sidebar">
                                            <div class="meta-item">
                                                <i class="bi bi-clock"></i>
                                                <span><span id="sMovieDuration">—</span> phút</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="bi bi-calendar-event"></i>
                                                <span id="sMovieRelease">—</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="bi bi-calendar-x"></i>
                                                <span id="sMovieEnd">—</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Modal: Sửa lịch chiếu ─────────────────────────────── --}}
<div class="modal fade" id="editShowtimeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Cập nhật lịch chiếu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="editShowtimeForm">
                <input type="hidden" id="editShowtimeFormMethod" value="PUT">
                <input type="hidden" name="showtime_id" id="editShowtimeIdInput" value="">

                <div class="modal-body">
                    <div class="row mb-3 g-3">
                        <div class="col-md-12">
                            <label class="form-label text-secondary">Phim <span class="text-danger">*</span></label>
                            <select class="form-select filter-input" id="editFormMovieId" name="movie_id" required>
                                <option value="">-- Chọn phim --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Rạp chiếu <span class="text-danger">*</span></label>
                            <select class="form-select filter-input" id="editFormTheaterId">
                                <option value="">-- Chọn rạp --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Phòng chiếu <span class="text-danger">*</span></label>
                            <select class="form-select filter-input" id="editFormScreenId" name="screen_id" required disabled>
                                <option value="">-- Chọn phòng chiếu --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Thời gian chiếu <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="filter-input w-100" id="editFormScheduledAt" name="scheduled_at" required>
                        </div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-12">
                            <label class="form-label text-secondary">Định dạng hình ảnh</label>
                            <select class="form-select filter-input" id="editFormFormatId" name="format_id">
                                <option value="">-- Mặc định --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Phiên bản phim</label>
                            <select class="form-select filter-input" id="editFormVersionTypeId" name="version_type_id">
                                <option value="">-- Chọn phiên bản --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label text-secondary mb-0">Trạng thái bán vé</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editFormStatus" name="status" value="1" checked>
                                <label class="form-check-label text-white small" for="editFormStatus">Mở bán</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="editShowtimeSubmitBtn">Lưu thay đổi</button>
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
