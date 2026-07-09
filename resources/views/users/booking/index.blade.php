@extends('layouts.app')

@section('title', 'Đặt vé - ' . $showtime->movie->title)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/pages/booking.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/users/booking-toast.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
@endpush

@section('content')
<div class="booking-page" data-showtime-id="{{ $showtime->id }}" data-showtime-encrypted-id="{{ $showtime->encrypted_id }}">

    {{-- Tab Navigation --}}
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

    <div class="booking-container">
        <div class="booking-main">
            <!-- Tab Content Sections -->
            <div class="tab-contents">
                <!-- Step 1: Seat Selection -->
                <div class="tab-content active" id="tab-seats">
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
                        <div id="seatMap" class="seat-grid mx-auto d-none"></div>
                        <!-- Column labels (populated by JS) -->
                        <div id="seatGridColLabels" class="seat-grid-col-labels mx-auto mt-2 d-none"></div>
                    </div>

                    <!-- Seat Legend - Icon ghế thống nhất, chỉ khác màu -->
                    <div class="seat-legend">
                        <div class="legend-item">
                            <span class="seat-demo seat-available"></span>
                            <span>Ghế trống</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-selected"></span>
                            <span>Đang chọn</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-vip"></span>
                            <span>Ghế VIP</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-holding"></span>
                            <span>Đã bán</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-couple"></span>
                            <span>Ghế đôi</span>
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
                                <button id="applyPromotionBtn" class="promo-btn">Đăng ký</button>
                            </div>
                            <div id="promotionMessage" class="promotion-message"></div>
                        </div>

                        <!-- Available Vouchers -->
                        <div class="voucher-section mt-4">
                            <div class="voucher-heading">
                                <div>
                                    <h5 class="voucher-title">Voucher của tôi</h5>
                                </div>
                            </div>
                            <div class="voucher-content">
                                <div class="empty-voucher">
                                    <p class="text-muted mt-2">Voucher đã đăng ký sẽ hiển thị tại đây.</p>
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
                                <span class="info-value">{{ $showtime->formatted_start_date }}</span>
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
                <button type="button" id="nextStepBtn" class="nav-btn btn-continue" disabled>
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
                    <img src="{{ optional($showtime->movie)->poster_url ?? asset('images/placeholder.jpg') }}"
                         alt="{{ $showtime->movie->title }}"
                         class="poster-img">
                </div>

                <!-- Movie Info -->
                <div class="summary-movie-info">
                    <h4 class="movie-title">{{ $showtime->movie->title }}</h4>
                    <div class="movie-details">
                        <span class="detail-item">
                            <i class="bi bi-tag-fill"></i> {{ $showtime->versionType?->name ?? 'Standard' }} • {{ $showtime->format?->name ?? '2D Standard' }}
                        </span>
                        <span class="detail-item">
                            <i class="bi bi-clock-fill"></i> {{ $showtime->movie->duration ?? 120 }} phút
                        </span>
                        <span class="detail-item">
                            <i class="bi bi-geo-alt-fill"></i> {{ $showtime->screen->theater->name }}
                        </span>
                        <span class="detail-item">
                            <i class="bi bi-camera-reels-fill"></i> Phòng {{ $showtime->screen->name }}
                        </span>
                        <span class="detail-item">
                            <i class="bi bi-calendar-check-fill"></i> {{ $showtime->formatted_start_time ?? $showtime->start_time }}
                        </span>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Selected Seats Info -->
                <div class="summary-section">
                    <div class="summary-row">
                        <span class="summary-label">Ghế đã chọn</span>
                        <span id="selectedSeatsDisplay" class="summary-value text-danger">Chưa chọn ghế</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Giá vé</span>
                        <span id="ticketPriceDisplay" class="summary-value">0 đ</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Phí tiện lợi</span>
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
                <button type="button" id="sidebarContinueBtn" class="sidebar-continue-btn" disabled>
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

    <!-- Payment Result Screens (Hidden by default, populated via JS) -->
    <div id="successScreen" class="payment-result-screen d-none">
        <div class="result-content">
            <h1 class="brand-title">CINEMA PREMIUM</h1>

            <div class="status-icon success-icon">
                <i class="bi bi-check-circle"></i>
            </div>

            <h2 class="status-title">Đặt vé thành công!</h2>

            <p class="status-message">
                Cảm ơn bạn đã đặt vé. Chúng tôi sẽ gửi thông tin vé qua email. Vui lòng có mặt tại rạp trước 15 phút.
            </p>

            <a href="{{ route('home') }}" class="btn-action-primary">
                Về trang chủ <i class="bi bi-arrow-right"></i>
            </a>

            <div class="transaction-info">
                <div class="info-block">
                    <span class="info-label">MÃ GIAO DỊCH</span>
                    <span class="info-value" id="successOrderCode">---</span>
                </div>
                <div class="info-divider"></div>
                <div class="info-block">
                    <span class="info-label">TỔNG TIỀN</span>
                    <span class="info-value" id="successTotalAmount">---</span>
                </div>
                <div class="info-divider"></div>
                <div class="info-block">
                    <span class="info-label">NGÀY ĐẶT</span>
                    <span class="info-value" id="successDate">---</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Failure Screen -->
    <div id="failureScreen" class="payment-result-screen d-none">
        <div class="result-content">
            <h1 class="brand-title">CINEMA PREMIUM</h1>

            <div class="status-icon failure-icon">
                <i class="bi bi-x-circle"></i>
            </div>

            <h2 class="status-title">Thanh toán bị huỷ</h2>

            <p class="status-message">
                Bạn đã huỷ giao dịch hoặc thanh toán không thành công. Vui lòng thử lại.
            </p>

            <a href="{{ route('home') }}" class="btn-action-primary failure-btn">
                Quay về trang chủ <i class="bi bi-arrow-right"></i>
            </a>

            <div class="transaction-info">
                <div class="info-block">
                    <span class="info-label">MÃ GIAO DỊCH</span>
                    <span class="info-value" id="failureOrderCode">---</span>
                </div>
                <div class="info-divider"></div>
                <div class="info-block">
                    <span class="info-label">NGÀY HUỶ</span>
                    <span class="info-value" id="failureDate">---</span>
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

<!-- Toast Notification -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 10000;">
    <div id="bookingToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-info-circle me-2"></i>
            <strong class="me-auto">Thông báo</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <!-- Message will be injected here -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.BOOKING_CONFIG = {
        showtimeId: {{ $showtime->id }},
        encryptedShowtimeId: @json($showtime->encrypted_id),
        basePrice: {{ $showtime->price ?? 0 }},
        screenId: {{ $showtime->screen_id }},
        movieTitle: @json($showtime->movie->title),
        startTime: @json($showtime->start_time ?? $showtime->scheduled_at),
    };
</script>
<script src="{{ asset('js/users/pages/booking.js') }}?v={{ time() }}"></script>
@endpush
