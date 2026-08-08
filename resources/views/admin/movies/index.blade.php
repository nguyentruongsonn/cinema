@extends('layouts.admin')

@section('title', 'Quản lý phim')
@section('header_title', 'Quản lý phim')
@section('header_subtitle', 'Danh sách các bộ phim chiếu rạp.')

@section('content')

{{-- ── Dòng 1: Header & Search ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select filter-select-md">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="1">Đã xuất bản</option>
                    <option value="0">Bản nháp</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group">
                <input type="text" id="search" name="search" class="admin-filter-input search-input-rounded-left" placeholder="Tên phim...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" id="btnOpenCreateMovie" class="admin-action-btn admin-filter-primary-action">
            <i class="bi bi-plus-lg"></i> Thêm phim
        </button>
    </div>
</div>
{{-- ── Dòng 2: Tabs + Content ─────────────────────────────────────── --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center col-stt">STT</th>
                    <th class="col-poster">Poster</th>
                    <th>Thông tin phim</th>
                    <th class="text-center col-status">Trạng thái</th>
                    <th class="text-center col-activity">Hoạt động</th>
                    <th class="text-center col-hot">Hot</th>
                    <th class="text-center col-actions-md">Action</th>
                </tr>
            </thead>
            <tbody id="moviesTableBody">
                <x-admin.skeleton-table cols="7" rows="5" :hasImage="true" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- ── Modal: Thêm / Sửa Phim ─────────────────────────────── --}}
<div class="modal fade" id="movieModal" tabindex="-1" aria-labelledby="movieModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="movieModalLabel">
                    <i class="bi bi-film me-2 admin-accent-icon"></i>Tạo phim mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="movieForm" enctype="multipart/form-data">
                <input type="hidden" id="movieFormMethod" value="POST">
                <input type="hidden" name="movie_id" id="movieIdInput" value="">

                <div class="modal-body admin-modal-scroll">
                    <div class="row g-4">
                        {{-- Cột trái --}}
                        <div class="col-md-8">

                            {{-- Tên phim --}}
                            <div class="row mb-3 g-3">
                                <div class="col-md-6">
                                    <label for="movieTitle" class="form-label text-secondary">Tên phim <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" id="movieTitle" name="title" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="movieOriginalTitle" class="form-label text-secondary">Tên gốc (tiếng Anh)</label>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" id="movieOriginalTitle" name="original_title" placeholder="Original title...">
                                </div>
                            </div>

                            {{-- Thời lượng & Ngày chiếu --}}
                            <div class="row mb-3 g-3">
                                <div class="col-md-4">
                                    <label for="movieDuration" class="form-label text-secondary">Thời lượng (phút) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control bg-dark text-white border-secondary" id="movieDuration" name="duration" min="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="movieReleaseDate" class="form-label text-secondary">Ngày khởi chiếu <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control bg-dark text-white border-secondary" id="movieReleaseDate" name="release_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="movieEndDate" class="form-label text-secondary">Ngày kết thúc</label>
                                    <input type="date" class="form-control bg-dark text-white border-secondary" id="movieEndDate" name="end_date">
                                </div>
                            </div>

                            {{-- Phân loại tuổi & Phụ thu --}}
                            <div class="row mb-3 g-3">
                                <div class="col-md-6">
                                    <label for="movieAgeRating" class="form-label text-secondary">Phân loại tuổi</label>
                                    <select class="form-select bg-dark text-white border-secondary" id="movieAgeRating" name="age_rating">
                                        <option value="P">P - Phổ biến mọi lứa tuổi</option>
                                        <option value="K">K - Dưới 13 tuổi (kèm người lớn)</option>
                                        <option value="T13">T13 - Từ 13 tuổi trở lên</option>
                                        <option value="T16">T16 - Từ 16 tuổi trở lên</option>
                                        <option value="T18">T18 - Từ 18 tuổi trở lên</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="movieSurcharge" class="form-label text-secondary">Phụ thu (VND)</label>
                                    <input type="number" class="form-control bg-dark text-white border-secondary" id="movieSurcharge" name="surcharge" min="0" placeholder="0">
                                </div>
                            </div>

                            {{-- Thể loại --}}
                            <div class="mb-3">
                                <label class="form-label text-secondary">Thể loại phim</label>
                                <div class="d-flex flex-wrap gap-2" id="categoryCheckboxes">
                                    @php
                                    $genres = [
                                        1=>'Action',2=>'Adventure',3=>'Comedy',4=>'Drama',
                                        5=>'Horror',6=>'Thriller',7=>'Sci-Fi',8=>'Fantasy',
                                        9=>'Romance',10=>'Animation',11=>'Documentary',12=>'Crime'
                                    ];
                                    @endphp
                                    @foreach($genres as $id => $name)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input category-cb" type="checkbox"
                                            id="cat_{{ $id }}" value="{{ $id }}" name="category_ids[]">
                                        <label class="form-check-label text-white-50 small" for="cat_{{ $id }}">{{ $name }}</label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Đạo diễn & Diễn viên --}}
                            <div class="row mb-3 g-3">
                                <div class="col-md-6">
                                    <label for="movieDirector" class="form-label text-secondary">Đạo diễn</label>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" id="movieDirector" name="director">
                                </div>
                                <div class="col-md-6">
                                    <label for="movieCast" class="form-label text-secondary">Diễn viên</label>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" id="movieCast" name="cast" placeholder="Ngăn cách bằng dấu phẩy">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="movieTrailer" class="form-label text-secondary">Trailer URL (YouTube)</label>
                                <input type="url" class="form-control bg-dark text-white border-secondary" id="movieTrailer" name="trailer_url" placeholder="https://youtube.com/...">
                            </div>

                            <div class="mb-3">
                                <label for="movieDescription" class="form-label text-secondary">Nội dung / Tóm tắt</label>
                                <textarea class="form-control bg-dark text-white border-secondary" id="movieDescription" name="description" rows="4" placeholder="Mô tả nội dung phim..."></textarea>
                            </div>
                        </div>

                        {{-- Cột phải (Ảnh & Trạng thái) --}}
                        <div class="col-md-4">
                            {{-- Poster Upload --}}
                            <div class="mb-3">
                                <label class="form-label text-secondary">Poster phim</label>
                                <div class="poster-upload-box movie-upload-box movie-upload-box-poster mb-2" id="posterUploadBox" data-media-input>
                                    <img id="posterPreview" class="movie-upload-preview" src="" alt="Poster">
                                    <div id="posterPlaceholder" class="text-center text-white-50 p-3">
                                        <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>
                                        <div class="small fw-semibold">Kéo thả hoặc click để chọn</div>
                                        <div class="movie-upload-help">JPG, PNG, WEBP · Tối đa 5MB</div>
                                    </div>
                                    <input type="file" id="moviePosterFile" class="movie-upload-input" name="poster_file"
                                           accept="image/jpeg,image/png,image/webp">
                                </div>
                                <button type="button" id="clearPosterBtn"
                                        class="btn btn-sm w-100 d-none movie-upload-clear">
                                    <i class="bi bi-x me-1"></i>Xóa ảnh
                                </button>
                            </div>

                            {{-- Banner Upload --}}
                            <div class="mb-3">
                                <label class="form-label text-secondary">Banner / Backdrop (tùy chọn)</label>
                                <div class="banner-upload-box movie-upload-box movie-upload-box-banner" id="bannerUploadBox" data-media-input>
                                    <img id="bannerPreview" class="movie-upload-preview" src="" alt="Banner">
                                    <div id="bannerPlaceholder" class="text-center text-white-50 p-2">
                                        <i class="bi bi-panorama fs-4 d-block mb-1"></i>
                                        <div class="movie-upload-help">Ảnh nền rộng (16:9)</div>
                                    </div>
                                    <input type="file" id="movieBannerFile" class="movie-upload-input" name="banner_file"
                                           accept="image/jpeg,image/png,image/webp">
                                </div>
                                <button type="button" id="clearBannerBtn"
                                        class="btn btn-sm w-100 d-none mt-1 movie-upload-clear">
                                    <i class="bi bi-x me-1"></i>Xóa banner
                                </button>
                            </div>

                            <hr class="border-secondary">

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label text-secondary mb-0">Trạng thái xuất bản</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="movieStatusToggle" name="status" value="1">
                                    <label class="form-check-label text-white small" for="movieStatusToggle" id="movieStatusLabel">Nháp</label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label text-secondary mb-0">Ẩn khỏi trang chủ</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="movieIsHidden" name="is_hidden" value="1">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label text-secondary mb-0"><i class="bi bi-fire text-danger me-1"></i>Phim Hot</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="movieIsHot" name="is_hot" value="1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white admin-modal-cancel" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="movieSubmitBtn">Lưu phim</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    @vite(['resources/css/admin/pages/stats.css', 'resources/css/admin/pages/movies.css'])
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/movies.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
