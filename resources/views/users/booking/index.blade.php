@extends('layouts.app')

@section('title', 'Đặt vé - ' . $showtime->movie->title)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/booking.css') }}">
<link rel="stylesheet" href="{{ asset('css/skeleton.css') }}">
@endpush

@section('content')
<div class="booking-page" data-showtime-id="{{ $showtime->id }}">
    <!-- Movie Info Header -->
    <div class="booking-header">
        <div class="container-fluid px-4 py-3">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="{{ url()->previous() }}" class="btn btn-link text-white p-0">
                        <i class="bi bi-arrow-left fs-4"></i>
                    </a>
                </div>
                <div class="col">
                    <h1 class="h5 mb-1 text-white">{{ $showtime->movie->title }}</h1>
                    <div class="small text-white-50">
                        <i class="bi bi-geo-alt"></i> {{ $showtime->screen->theater->name }} - {{ $showtime->screen->name }}
                        <span class="mx-2">|</span>
                        <i class="bi bi-calendar-event"></i> {{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i, d/m/Y') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="booking-container">
        <div class="booking-main">
            <!-- Tab Navigation -->
            <div class="booking-tabs">
                <button class="tab-btn active" data-tab="seats">
                    <span class="tab-number">1</span>
                    <span class="tab-label">Chọn ghế</span>
                </button>
                <button class="tab-btn" data-tab="food">
                    <span class="tab-number">2</span>
                    <span class="tab-label">Chọn thức ăn</span>
                </button>
                <button class="tab-btn" data-tab="promotion">
                    <span class="tab-number">3</span>
                    <span class="tab-label">Khuyến mãi</span>
                </button>
                <button class="tab-btn" data-tab="confirm">
                    <span class="tab-number">4</span>
                    <span class="tab-label">Xác nhận</span>
                </button>
            </div>

            <!-- Tab Content Sections -->
            <div class="tab-contents">
                <!-- Step 1: Seat Selection -->
                <div class="tab-content active" id="tab-seats">
                    <div class="movie-info-card">
                        <h3 class="section-title">{{ $showtime->movie->title }}</h3>
                        <div class="movie-meta">
                            <span class="format-badge">
                                <span class="badge-label">FORMAT</span>
                                <span class="badge-value">2D Standard</span>
                            </span>
                            <span class="theater-info">
                                <i class="bi bi-geo-alt-fill"></i>
                                {{ $showtime->screen->theater->name }} - Phòng {{ $showtime->screen->name }} -
                                {{ $showtime->format?->name ?? '2D Standard' }} -
                                Hôm nay, {{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}
                            </span>
                        </div>
                    </div>

                    <!-- Screen Display -->
                    <div class="screen-display">
                        <div class="screen-label">MÀN HÌNH CHIẾU</div>
                    </div>

                    <!-- Seat Map -->
                    <div id="seatMapContainer" class="seat-map-container">
                        <!-- Skeleton Loading -->
                        <div class="seat-map-skeleton">
                            <div class="skeleton-rows">
                                <div class="skeleton-row" v-for="i in 10">
                                    <div class="skeleton-seat" v-for="j in 15"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Actual seat map rendered by JS -->
                        <div id="seatMap" class="seat-map d-none"></div>
                    </div>

                    <!-- Seat Legend -->
                    <div class="seat-legend">
                        <div class="legend-item">
                            <span class="seat-demo seat-available"></span>
                            <span>Trống</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-selected"></span>
                            <span>Đang chọn</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-holding"></span>
                            <span>Đã bán</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-vip"></span>
                            <span>Ghế Đôi</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Food Selection -->
                <div class="tab-content" id="tab-food">
                    <div class="section-header">
                        <h3 class="section-title">Chọn bắp nước</h3>
                        <p class="section-subtitle">Các tùy chọn ăn uống giúp trải nghiệm điện ảnh thêm trọn vẹn.</p>
                    </div>

                    <div id="productsContainer" class="products-grid">
                        <div class="text-center py-5">
                            <div class="spinner-border text-danger" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Đang tải combo...</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Promotion -->
                <div class="tab-content" id="tab-promotion">
                    <div class="section-header">
                        <h3 class="section-title">Khuyến mãi & Ưu đãi</h3>
                    </div>

                    <!-- Promotion Code Input -->
                    <div class="promotion-section">
                        <div class="promotion-input-box">
                            <label class="promotion-label">Áp dụng mã khuyến mãi</label>
                            <div class="promotion-input-group">
                                <input type="text"
                                       id="promotionCodeInput"
                                       class="promo-input"
                                       placeholder="Nhập mã khuyến mãi">
                                <button id="applyPromotionBtn" class="promo-btn">Áp dụng</button>
                            </div>
                            <div id="promotionMessage" class="promotion-message"></div>
                        </div>

                        <!-- Available Vouchers -->
                        <div class="voucher-section mt-4">
                            <h5 class="voucher-title">Kho Voucher của bạn</h5>
                            <div class="voucher-tabs">
                                <button class="voucher-tab active" data-voucher-tab="all">MÃ VOUCHER</button>
                                <button class="voucher-tab" data-voucher-tab="available">NỘI DUNG</button>
                                <button class="voucher-tab" data-voucher-tab="expired">HẾT HẠN</button>
                                <button class="voucher-tab" data-voucher-tab="history">TRAO TẶC</button>
                            </div>
                            <div class="voucher-content">
                                <div class="empty-voucher">
                                    <i class="bi bi-ticket-perforated fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">Bạn chưa có mã nào đã đăng ký.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Points Section -->
                        <div class="points-section mt-4">
                            <h5 class="points-title">Đổi điểm tích lũy</h5>
                            <div class="points-input-group">
                                <input type="number"
                                       id="pointsInput"
                                       class="points-input"
                                       placeholder="Bạn có 0 điểm"
                                       min="0">
                                <button id="exchangePointsBtn" class="points-btn">Đổi điểm</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Confirm -->
                <div class="tab-content" id="tab-confirm">
                    <div class="section-header">
                        <h3 class="section-title">Xác nhận thông tin</h3>
                    </div>

                    <div class="confirm-details">
                        <div class="confirm-card">
                            <h5 class="confirm-section-title">
                                <i class="bi bi-film"></i> Thông tin phim
                            </h5>
                            <div class="confirm-info-row">
                                <span class="info-label">Tên phim:</span>
                                <span class="info-value">{{ $showtime->movie->title }}</span>
                            </div>
                            <div class="confirm-info-row">
                                <span class="info-label">Rạp:</span>
                                <span class="info-value">{{ $showtime->screen->theater->name }}</span>
                            </div>
                            <div class="confirm-info-row">
                                <span class="info-label">Phòng chiếu:</span>
                                <span class="info-value">{{ $showtime->screen->name }}</span>
                            </div>
                            <div class="confirm-info-row">
                                <span class="info-label">Suất chiếu:</span>
                                <span class="info-value">{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i, d/m/Y') }}</span>
                            </div>
                        </div>

                        <div class="confirm-card mt-3">
                            <h5 class="confirm-section-title">
                                <i class="bi bi-ticket-perforated"></i> Ghế đã chọn
                            </h5>
                            <div id="confirmSeatsInfo" class="confirm-seats">
                                <p class="text-muted">Chưa chọn ghế</p>
                            </div>
                        </div>

                        <div class="confirm-card mt-3" id="confirmProductsCard" style="display: none;">
                            <h5 class="confirm-section-title">
                                <i class="bi bi-cup-straw"></i> Combo đã chọn
                            </h5>
                            <div id="confirmProductsInfo" class="confirm-products"></div>
                        </div>

                        <div class="confirm-card mt-3" id="confirmPromotionCard" style="display: none;">
                            <h5 class="confirm-section-title">
                                <i class="bi bi-tag"></i> Khuyến mãi
                            </h5>
                            <div id="confirmPromotionInfo" class="confirm-promotion"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="booking-nav-buttons">
                <button id="prevStepBtn" class="nav-btn btn-back" style="display: none;">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </button>
                <button id="nextStepBtn" class="nav-btn btn-continue" disabled>
                    Tiếp tục
                </button>
                <button id="paymentBtn" class="nav-btn btn-payment" style="display: none;">
                    <i class="bi bi-credit-card"></i> Thanh toán
                </button>
            </div>
        </div>

        <!-- Sidebar Summary -->
        <div class="booking-sidebar">
            <div class="summary-card">
                <!-- Movie Poster -->
                <div class="summary-poster">
                    <img src="{{ $showtime->movie->poster_url ?? '/images/placeholder.jpg' }}"
                         alt="{{ $showtime->movie->title }}"
                         class="poster-img">
                </div>

                <!-- Movie Info -->
                <div class="summary-movie-info">
                    <h4 class="movie-title">{{ $showtime->movie->title }}</h4>
                    <div class="movie-details">
                        <span class="detail-item">
                            <i class="bi bi-tag-fill"></i> Phụ đề • 2D Standard
                        </span>
                        <span class="detail-item">
                            <i class="bi bi-clock-fill"></i> {{ $showtime->movie->duration ?? 120 }} phút
                        </span>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Selected Seats Info -->
                <div class="summary-section">
                    <div class="summary-row">
                        <span class="summary-label">Selected Seats</span>
                        <span id="selectedSeatsDisplay" class="summary-value text-danger">Chưa chọn ghế</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Ticket Price</span>
                        <span id="ticketPriceDisplay" class="summary-value">0 đ</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Convenience Fee</span>
                        <span id="convenienceFeeDisplay" class="summary-value">0 đ</span>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Total -->
                <div class="summary-total">
                    <span class="total-label">Tổng cộng</span>
                    <span id="totalPriceDisplay" class="total-value">0 đ</span>
                </div>

                <!-- Continue Button in Sidebar -->
                <button id="sidebarContinueBtn" class="sidebar-continue-btn" disabled>
                    Tiếp tục
                </button>

                <!-- Timer -->
                <div class="booking-timer">
                    <i class="bi bi-clock-history"></i>
                    <span>Thời gian giữ ghế: </span>
                    <span id="bookingTimer" class="timer-display">10:00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay d-none">
    <div class="spinner-border text-danger" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Đang xử lý...</p>
</div>
@endsection

@push('scripts')
<script>
    window.BOOKING_CONFIG = {
        showtimeId: {{ $showtime->id }},
        basePrice: {{ $showtime->price ?? 0 }},
        screenId: {{ $showtime->screen_id }},
        movieTitle: @json($showtime->movie->title),
        startTime: @json($showtime->start_time),
    };
</script>
<script src="{{ asset('js/pages/booking.js') }}"></script>
@endpush
