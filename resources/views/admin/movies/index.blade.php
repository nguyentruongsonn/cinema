@extends('layouts.admin')

@section('title', 'Quản lý phim')
@section('header_title', 'Quản lý phim')
@section('header_subtitle', 'Danh sách các bộ phim chiếu rạp.')

@section('content')

{{-- ── Dòng 1: Header & Search ────────────────────────── --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3">
        <h5 class="mb-0 text-white fw-bold" style="flex-shrink: 0;">
            <i class="bi bi-film me-2"></i>Danh sách Phim
        </h5>

        <form id="searchForm" class="flex-grow-1" style="max-width: 500px;">
            <div class="input-group">
                <input type="text" id="search" name="search" class="admin-filter-input" placeholder="Tên phim..." style="border-radius: 8px 0 0 8px;">
                <button class="admin-filter-btn" style="border-radius: 0 8px 8px 0;" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" id="btnOpenCreateMovie" class="admin-action-btn ms-auto">
            <i class="bi bi-plus-lg"></i> Thêm phim
        </button>
    </div>ch 
</div>

{{-- ── Dòng 2: Tabs + Content ─────────────────────────────────────── --}}
<div class="admin-table-container">
    <ul class="nav nav-tabs combo-tabs mb-4" id="movieTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-status="all" type="button" role="tab">Tất cả</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-status="published" type="button" role="tab">Đã xuất bản</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-status="draft" type="button" role="tab">Nháp</button>
        </li>
    </ul>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background:transparent;">
            <thead style="border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                    <th class="text-secondary fw-semibold border-0" style="width: 70px;">Poster</th>
                    <th class="text-secondary fw-semibold border-0">Thông tin phim</th>
                    <th class="text-secondary fw-semibold border-0 text-center" style="width: 120px;">Trạng thái</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 100px;">Hoạt động</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 90px;">Hot</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 110px;">Action</th>
                </tr>
            </thead>
            <tbody id="moviesTableBody">
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

    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);" id="paginationContainer">
    </div>
</div>

{{-- ── Modal: Thêm / Sửa Phim ─────────────────────────────── --}}
<div class="modal fade" id="movieModal" tabindex="-1" aria-labelledby="movieModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="movieModalLabel">
                    <i class="bi bi-film me-2"></i>Thêm phim mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="movieForm" enctype="multipart/form-data">
                <input type="hidden" id="movieFormMethod" value="POST">
                <input type="hidden" name="movie_id" id="movieIdInput" value="">

                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
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
                                <div class="poster-upload-box mb-2" id="posterUploadBox"
                                     style="border: 2px dashed rgba(255,255,255,0.15); border-radius: 10px; min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: border-color 0.2s;">
                                    <img id="posterPreview" src="" alt="Poster"
                                         style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 8px; display: none;">
                                    <div id="posterPlaceholder" class="text-center text-white-50 p-3">
                                        <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>
                                        <div class="small fw-semibold">Kéo thả hoặc click để chọn</div>
                                        <div style="font-size:0.72rem;">JPG, PNG, WEBP · Tối đa 5MB</div>
                                    </div>
                                    <input type="file" id="moviePosterFile" name="poster_file"
                                           accept="image/jpeg,image/png,image/webp"
                                           style="position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;">
                                </div>
                                <button type="button" id="clearPosterBtn"
                                        class="btn btn-sm w-100 d-none"
                                        style="background: rgba(255,255,255,0.06); color:#a1a1aa; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; font-size:0.78rem;">
                                    <i class="bi bi-x me-1"></i>Xóa ảnh
                                </button>
                            </div>

                            {{-- Banner Upload --}}
                            <div class="mb-3">
                                <label class="form-label text-secondary">Banner / Backdrop (tùy chọn)</label>
                                <div class="banner-upload-box" id="bannerUploadBox"
                                     style="border: 2px dashed rgba(255,255,255,0.1); border-radius: 10px; min-height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: border-color 0.2s;">
                                    <img id="bannerPreview" src="" alt="Banner"
                                         style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 8px; display: none;">
                                    <div id="bannerPlaceholder" class="text-center text-white-50 p-2">
                                        <i class="bi bi-panorama fs-4 d-block mb-1"></i>
                                        <div style="font-size:0.72rem;">Ảnh nền rộng (16:9)</div>
                                    </div>
                                    <input type="file" id="movieBannerFile" name="banner_file"
                                           accept="image/jpeg,image/png,image/webp"
                                           style="position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;">
                                </div>
                                <button type="button" id="clearBannerBtn"
                                        class="btn btn-sm w-100 d-none mt-1"
                                        style="background: rgba(255,255,255,0.06); color:#a1a1aa; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; font-size:0.78rem;">
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
                    <button type="button" class="btn text-white" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.1);">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0" id="movieSubmitBtn">Lưu phim</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/movies.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/movies.js') }}?v={{ time() }}" defer></script>
@endpush
