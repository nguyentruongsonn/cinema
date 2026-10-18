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
                <h3 class="section-heading"><span class="heading-accent"></span> LỊCH CHIẾU PHIM TẠI RẠP</h3>
                <div class="date-tabs-container">
                    <div class="date-tabs-scroll" id="showtimeDateTabs" role="tablist"></div>
                </div>
            </div>

            <div id="showtimesSkeleton" class="row g-4">
                @for ($i = 0; $i < 3; $i++)
                    <div class="col-12">
                        <div class="showtime-skeleton-card">
                            <div class="skeleton-poster skeleton"></div>
                            <div class="skeleton-body">
                                <div class="skeleton-title skeleton"></div>
                                <div class="skeleton-badges-row">
                                    <div class="skeleton-badge-pill skeleton"></div>
                                    <div class="skeleton-badge-pill skeleton"></div>
                                </div>
                                <div class="skeleton-format-label skeleton"></div>
                                <div class="skeleton-slots-row">
                                    <div class="skeleton-slot-pill skeleton"></div>
                                    <div class="skeleton-slot-pill skeleton"></div>
                                    <div class="skeleton-slot-pill skeleton"></div>
                                    <div class="skeleton-slot-pill skeleton"></div>
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
                <div class="col-lg-7">
                    <div id="theaterPricingContainer">
                        <div class="section-title-wrap mb-3">
                            <h3 class="section-heading"><span class="heading-accent"></span> BẢNG GIÁ VÉ</h3>
                        </div>

                        <div id="pricingSkeleton" class="pricing-skeleton-wrapper">
                            @for ($c = 0; $c < 2; $c++)
                                <div class="pricing-skeleton-card">
                                    <div class="skeleton-format-title skeleton"></div>
                                    <div class="pricing-skeleton-table">
                                        <div class="skeleton-table-header skeleton"></div>
                                        @for ($r = 0; $r < 3; $r++)
                                            <div class="skeleton-table-row skeleton"></div>
                                        @endfor
                                    </div>
                                    <div class="skeleton-table-note skeleton"></div>
                                </div>
                            @endfor
                        </div>

                        <div id="pricingContentArea" class="d-none"></div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div id="theaterDetailsContainer">
                        <div class="section-title-wrap mb-3">
                            <h3 class="section-heading"><span class="heading-accent"></span> THÔNG TIN CHI TIẾT</h3>
                        </div>

                        <div class="theater-details-card">
                            <div class="detail-map-wrapper mb-3" id="theaterMapWrapper">
                                <div class="theater-map-box" id="theaterMapBox">
                                    <iframe id="theaterMapIframe" src="" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                </div>
                            </div>

                            <div class="detail-group mb-3" id="detailAddressGroup">
                                <label class="detail-label">ĐỊA CHỈ</label>
                                <div class="detail-value text-white fw-semibold" id="detailAddress">--</div>
                            </div>

                            <div class="detail-group mb-3" id="detailPhoneGroup">
                                <label class="detail-label">ĐIỆN THOẠI</label>
                                <div class="detail-value text-danger fw-bold fs-5" id="detailPhone">--</div>
                            </div>

                            <div class="detail-group mb-3" id="detailEmailGroup">
                                <label class="detail-label">EMAIL HỖ TRỢ</label>
                                <div class="detail-value text-light-gray" id="detailEmail">--</div>
                            </div>

                            <div class="detail-group mb-3 d-none" id="detailDescGroup">
                                <label class="detail-label">GIỚI THIỆU RẠP</label>
                                <div class="detail-value detail-desc-text" id="detailDescription"></div>
                            </div>

                            <div class="detail-group mb-4 d-none" id="detailAmenitiesGroup">
                                <label class="detail-label">TIỆN ÍCH TẠI RẠP</label>
                                <div class="amenities-tags" id="detailAmenities"></div>
                            </div>

                            <a href="#" id="theaterDirectionsBtn" target="_blank" rel="noopener noreferrer" class="btn-directions">
                                Chỉ đường tới rạp
                            </a>
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
