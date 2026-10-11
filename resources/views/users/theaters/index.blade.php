@extends('layouts.app')

@section('title', 'Hệ thống Rạp & Bảng Giá Vé - Cinema')
@section('meta_description', 'Khám phá hệ thống cụm rạp hiện đại, xem lịch chiếu phim và bảng giá vé chi tiết.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users/pages/theaters.css') }}?v={{ filemtime(public_path('css/users/pages/theaters.css')) }}">
@endpush

@section('content')
    <section class="theater-section theater-secion hero-section" id="theaterSection" aria-label="Hình ảnh rạp chiếu">
        <div id="heroBackdrop" class="hero-backdrop"></div>

        <div class="hero-gradient-overlay"></div>

        <div class="hero-carousel-controls" aria-label="Điều khiển banner">
            <button type="button" class="hero-carousel-arrow" id="heroPrevious" aria-label="Banner trước">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button type="button" class="hero-carousel-arrow" id="heroNext" aria-label="Banner tiếp theo">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <div id="heroContent" class="hero-content">
            <div class="container">
                <div class="hero-inner">
                    <div class="hero-meta-row">
                        <span class="hero-age-badge">HỆ THỐNG RẠP</span>
                    </div>
                    <h1 class="hero-title" id="heroTitle">Trải Nghiệm Điện Ảnh Đỉnh Cao</h1>
                    <p class="hero-description" id="heroDesc">Hệ thống phòng chiếu chuẩn quốc tế với âm thanh vòm sống động và màn chiếu sắc nét.</p>
                </div>
            </div>
        </div>

        <div id="heroDots" class="hero-carousel-dots" role="tablist" aria-label="Danh sách banner"></div>
    </section>

    <section class="theaters-info-section sticky-top">
        <div class="container">
            <div class="theaters-info-widget quick-booking-widget">
                <div class="theaters-info-bar booking-form">
                    <div class="theaters-info-bar-inner">
                        <div class="theater-summary">
                            <span class="theater-eyebrow">RẠP ĐANG CHỌN</span>
                            <h2 class="theater-current-name" id="currentTheaterName">Đang tải rạp...</h2>
                            <div class="theater-meta">
                                <span id="currentTheaterAddress"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Đang tải địa chỉ...</span>
                                <span id="currentTheaterPhone"><i class="bi bi-telephone-fill text-danger me-1"></i> Hotline: --</span>
                            </div>
                        </div>

                        <div class="theater-controls">
                            <div class="control-item">
                                <label for="citySelect" class="control-label">TỈNH / THÀNH PHỐ</label>
                                <select id="citySelect" class="form-select theater-select">
                                    <option value="">Tất cả khu vực</option>
                                </select>
                            </div>

                            <div class="control-item">
                                <label for="theaterSelect" class="control-label">CHỌN RẠP KHÁC</label>
                                <select id="theaterSelect" class="form-select theater-select">
                                    <option value="">Chọn cụm rạp</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="theaters-showtimes-section">
        <div class="container">
            <div class="section-title-wrap mb-4">
                <h3 class="section-heading">LỊCH CHIẾU PHIM TẠI RẠP</h3>
                <div class="date-tabs-container">
                    <div class="date-tabs-scroll" id="showtimeDateTabs" role="tablist"></div>
                </div>
            </div>

            <div id="showtimesSkeleton" class="row g-4">
                @for ($i = 0; $i < 3; $i++)
                    <div class="col-12">
                        <div class="showtime-card-skeleton p-4 rounded bg-dark border border-secondary">
                            <div class="d-flex gap-4">
                                <div class="skeleton-img rounded" style="width: 120px; height: 170px; background: #2c2c2e;"></div>
                                <div class="flex-grow-1">
                                    <div class="skeleton-line mb-3" style="width: 40%; height: 22px; background: #2c2c2e;"></div>
                                    <div class="skeleton-line mb-4" style="width: 25%; height: 16px; background: #2c2c2e;"></div>
                                    <div class="d-flex gap-2">
                                        <div class="skeleton-pill rounded" style="width: 70px; height: 36px; background: #2c2c2e;"></div>
                                        <div class="skeleton-pill rounded" style="width: 70px; height: 36px; background: #2c2c2e;"></div>
                                        <div class="skeleton-pill rounded" style="width: 70px; height: 36px; background: #2c2c2e;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>

            <div id="theaterMoviesGrid" class="showtimes-movie-list d-none"></div>

            <div id="showtimesEmptyState" class="text-center py-5 d-none">
                <i class="bi bi-film fs-1 text-secondary mb-3 d-block"></i>
                <h4 class="text-white">Không có suất chiếu vào ngày này</h4>
            </div>
        </div>
    </section>

    <section class="theaters-split-section py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="pricing-panel" id="theaterPricingContainer">
                        <div class="section-title-wrap mb-3">
                            <h3 class="section-heading">BẢNG GIÁ VÉ</h3>
                        </div>

                        <div id="pricingContentArea">
                            <div class="text-center py-4 text-secondary">Đang tải bảng giá...</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="details-panel" id="theaterDetailsContainer">
                        <div class="section-title-wrap mb-3">
                            <h3 class="section-heading">THÔNG TIN CHI TIẾT</h3>
                        </div>

                        <div class="details-card">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="details-contact-info mb-3">
                                        <div class="detail-row mb-2">
                                            <strong class="text-white">Địa chỉ:</strong>
                                            <span id="detailAddress" class="text-secondary">--</span>
                                        </div>
                                        <div class="detail-row mb-2">
                                            <strong class="text-white">Điện thoại:</strong>
                                            <span id="detailPhone" class="text-danger fw-bold">--</span>
                                        </div>
                                        <div class="detail-row mb-2">
                                            <strong class="text-white">Email:</strong>
                                            <span id="detailEmail" class="text-secondary">--</span>
                                        </div>
                                    </div>

                                    <div class="details-description mb-3 d-none" id="detailDescription"></div>

                                    <div class="details-amenities d-none" id="detailAmenitiesWrapper">
                                        <h6 class="text-white mb-2">TIỆN ÍCH TẠI RẠP</h6>
                                        <div class="amenities-tags" id="detailAmenities"></div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="theater-map-column" id="theaterMapColumn">
                                        <h6 class="text-white mb-2">BẢN ĐỒ</h6>
                                        <div class="theater-map-container" id="theaterMapContainer">
                                            <div class="theater-map-box" id="theaterMapBox">
                                                <div class="theater-map-placeholder" id="theaterMapPlaceholder">
                                                    <i class="bi bi-geo-alt-fill text-danger fs-2 mb-2"></i>
                                                    <div class="text-white fw-bold mb-1">Vị trí rạp</div>
                                                    <span class="small text-secondary" id="theaterMapAddress"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/users/pages/theaters.js') }}?v={{ config('app.asset_version', time()) }}"></script>
@endpush
