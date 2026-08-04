@extends('layouts.app')

@section('title', 'Đặt vé - ' . $showtime->movie->title)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/pages/booking.css') }}?v={{ filemtime(public_path('css/users/pages/booking.css')) }}">
<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
@endpush

@section('content')
<div class="booking-page has-progress" data-showtime-id="{{ $showtime->id }}" data-showtime-encrypted-id="{{ $showtime->encrypted_id }}">

    {{-- Modern Progress Bar --}}
    <div class="booking-progress" aria-label="Tiến trình đặt vé">
        <div class="progress-track">
            <button type="button" class="progress-step step-active" data-step="1" data-tab="seats" aria-current="step">
                <span class="step-inner">
                    <span class="step-icon"><i class="bi bi-geo-alt-fill"></i></span>
                    <span class="step-circle">1</span>
                </span>
                <span class="step-label">Chọn ghế</span>
            </button>
            <span class="step-connector"><span class="step-connector-fill"></span></span>
            <button type="button" class="progress-step step-pending" data-step="2" data-tab="food" aria-disabled="true">
                <span class="step-inner">
                    <span class="step-icon"><i class="bi bi-cup-straw"></i></span>
                    <span class="step-circle">2</span>
                </span>
                <span class="step-label">Bắp nước</span>
            </button>
            <span class="step-connector"><span class="step-connector-fill"></span></span>
            <button type="button" class="progress-step step-pending" data-step="3" data-tab="promotion" aria-disabled="true">
                <span class="step-inner">
                    <span class="step-icon"><i class="bi bi-tag-fill"></i></span>
                    <span class="step-circle">3</span>
                </span>
                <span class="step-label">Ưu đãi</span>
            </button>
            <span class="step-connector"><span class="step-connector-fill"></span></span>
            <button type="button" class="progress-step step-pending" data-step="4" data-tab="confirm" aria-disabled="true">
                <span class="step-inner">
                    <span class="step-icon"><i class="bi bi-credit-card-fill"></i></span>
                    <span class="step-circle">4</span>
                </span>
                <span class="step-label">Thanh toán</span>
            </button>
            <span class="step-connector"><span class="step-connector-fill"></span></span>
            <button type="button" class="progress-step step-pending" data-step="5" data-tab="success" aria-disabled="true">
                <span class="step-inner">
                    <span class="step-icon"><i class="bi bi-check2-circle"></i></span>
                    <span class="step-circle">5</span>
                </span>
                <span class="step-label">Xác nhận</span>
            </button>
        </div>
    </div>

    {{-- Tab Navigation (kept for JS compatibility, hidden by .has-progress) --}}
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
                <!-- SVG Gradient Definitions for seat colors -->
                <svg class="booking-svg-definitions" width="0" height="0" aria-hidden="true">
                    <defs>
                        <!-- Ghế thường: xám trung tính -->
                        <linearGradient id="grad-standard" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#52596b"/>
                            <stop offset="100%" stop-color="#2e3340"/>
                        </linearGradient>
                        <!-- Ghế VIP: đỏ cam rực rỡ -->
                        <linearGradient id="grad-vip" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#ff6a3d"/>
                            <stop offset="100%" stop-color="#c0392b"/>
                        </linearGradient>
                        <!-- Ghế đôi: tím hồng -->
                        <linearGradient id="grad-couple" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#b06ae9"/>
                            <stop offset="100%" stop-color="#7b2fa0"/>
                        </linearGradient>
                        <!-- Ghế đang chọn: đỏ tươi -->
                        <linearGradient id="grad-selected" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#ff3344"/>
                            <stop offset="100%" stop-color="#c0000f"/>
                        </linearGradient>
                    </defs>
                </svg>

                <!-- Screen Display -->
                <div class="screen-display">
                    <div class="screen-label">MÀN HÌNH CHIẾU</div>
                </div>

                    <!-- Seat Map -->
                    <div id="seatMapContainer" class="seat-map-container">
                        <!-- Skeleton Loading -->
                        <div class="seat-map-skeleton w-100" id="seatMapSkeleton"></div>
                        <!-- Actual seat map rendered by JS -->
                        <div id="seatMap" class="seat-grid mx-auto d-none"></div>
                        <!-- Column labels (populated by JS) -->
                        <div id="seatGridColLabels" class="seat-grid-col-labels mx-auto mt-2 d-none"></div>
                    </div>

                    <!-- Seat Legend - Icon ghế thống nhất, chỉ khác màu -->
                    <div class="seat-legend">
                        <div class="legend-item">
                            <span class="seat-demo seat-available">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>
                            </span>
                            <span>Ghế trống</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-selected">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>
                            </span>
                            <span>Đang chọn</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-vip">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>
                            </span>
                            <span>Ghế VIP</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-holding">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><rect x="5" y="2" width="14" height="11" rx="2"/><rect x="4" y="14" width="16" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="19" y="11" width="3" height="8" rx="1"/></svg>
                                <span class="sold-marker">×</span>
                            </span>
                            <span>Đã bán</span>
                        </div>
                        <div class="legend-item">
                            <span class="seat-demo seat-couple">
                                <svg viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="1.5" width="44" height="22"><rect x="5" y="2" width="38" height="11" rx="2"/><rect x="4" y="14" width="40" height="5" rx="1"/><rect x="2" y="11" width="3" height="8" rx="1"/><rect x="43" y="11" width="3" height="8" rx="1"/></svg>
                            </span>
                            <span>Ghế đôi</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Food Selection -->
                <div class="tab-content" id="tab-food">
                    <div class="section-header">
                        <h3 class="section-title">Chọn bắp nước</h3>
                    </div>

                    <div id="productsContainer" class="products-grid">
                        <!-- Skeleton will be rendered by JS -->
                    </div>
                </div>

                <!-- Step 3: Promotion -->
                <div class="tab-content" id="tab-promotion">
                    <!-- Top section: Promo Code -->
                    <div class="promotion-section">
                        <h4 class="promo-heading">Nhập mã khuyến mãi</h4>
                        <div class="promo-input-wrapper mt-3">
                            <input type="text"
                                   id="promotionCodeInput"
                                   class="promo-input-dark"
                                   placeholder="VD: CINEMA2024">
                            <button id="applyPromotionBtn" class="promo-btn-dark">Áp dụng</button>
                        </div>
                        <div id="promotionMessage" class="promotion-message"></div>
                    </div>

                    <!-- Middle section: Loyalty Points -->
                    <div class="promotion-section mt-4">
                        <div class="loyalty-card-dark">
                            <div class="loyalty-header-dark">
                                <div class="loyalty-title-wrapper">
                                    <div class="loyalty-icon-red">
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                    <h5 class="loyalty-title-text">Điểm thành viên</h5>
                                </div>
                                <p class="loyalty-subtitle-text">Bạn có <strong id="availablePointsDisplay">0 XP</strong> khả dụng.</p>
                            </div>
                            
                            <div class="loyalty-body-dark">
                                <div class="promo-input-wrapper mt-3">
                                    <input type="number" id="pointsInput" class="promo-input-dark" placeholder="Nhập số điểm cần đổi" min="0">
                                    <button id="exchangePointsBtn" class="promo-btn-dark">Đổi điểm</button>
                                </div>
                                
                                <div class="loyalty-footer-dark mt-3">
                                    <p class="loyalty-rate-text">Quy đổi: <strong id="loyaltyDiscountDisplay">-0 VND</strong></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom section: Available Vouchers -->
                    <div class="promotion-section mt-5" id="availableVouchersSection">
                        <h4 class="promo-heading">Voucher khả dụng</h4>
                        <div class="voucher-grid-dark mt-3" id="voucherContent">
                            <div class="empty-voucher text-center py-4 w-100">
                                <i class="bi bi-inbox text-light booking-empty-icon"></i>
                                <p class="text-light mt-2 mb-0 booking-empty-copy">Chưa có voucher nào khả dụng cho suất chiếu này.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Payment -->
                <div class="tab-content" id="tab-confirm">
                    <div class="payment-split-layout">
                        <!-- Left Column: Payment Methods -->
                        <div class="payment-methods-column">
                            <div class="section-header mb-4">
                                <h3 class="section-title text-white booking-payment-title">Phương thức thanh toán</h3>
                            </div>
                            <div class="payment-methods-list-dark">
                                <!-- Apple Pay -->
                                <label class="payment-method-item">
                                    <input type="radio" name="payment_method" value="applepay" class="payment-method-radio">
                                    <div class="pm-dark-icon-box bg-white"><i class="bi bi-wallet2 text-dark"></i></div>
                                    <div class="pm-dark-info">
                                        <div class="pm-dark-title">Apple Pay</div>
                                        <div class="pm-dark-desc">Thanh toán nhanh một chạm</div>
                                    </div>
                                    <div class="pm-dark-check"></div>
                                </label>

                                <!-- Credit Card -->
                                <label class="payment-method-item disabled">
                                    <input type="radio" name="payment_method" value="card" disabled class="payment-method-radio">
                                    <div class="pm-dark-icon-box"><i class="bi bi-credit-card-2-front"></i></div>
                                    <div class="pm-dark-info">
                                        <div class="pm-dark-title">Thẻ Tín Dụng / Ghi Nợ</div>
                                        <div class="pm-dark-desc">Hỗ trợ Visa, Mastercard, JCB</div>
                                        <div class="pm-dark-badges mt-2">
                                            <span class="pm-badge">VISA</span>
                                            <span class="pm-badge">MASTERCARD</span>
                                        </div>
                                    </div>
                                    <div class="pm-dark-action">
                                        <span class="pm-add-btn"><i class="bi bi-plus"></i> Thêm thẻ mới</span>
                                    </div>
                                    <div class="pm-dark-check"></div>
                                </label>

                                <!-- MoMo / E-Wallet -->
                                <label class="payment-method-item disabled">
                                    <input type="radio" name="payment_method" value="momo" disabled class="payment-method-radio">
                                    <div class="pm-dark-icon-box"><i class="bi bi-wallet2"></i></div>
                                    <div class="pm-dark-info">
                                        <div class="pm-dark-title">Ví Điện Tử</div>
                                        <div class="pm-dark-desc">Momo, ZaloPay, ShopeePay</div>
                                        <div class="pm-dark-badges mt-2">
                                            <span class="pm-badge">Momo</span>
                                            <span class="pm-badge">ZaloPay</span>
                                        </div>
                                    </div>
                                    <div class="pm-dark-check"></div>
                                </label>

                                <!-- PayOS (Bank Transfer) -->
                                <label class="payment-method-item active">
                                    <input type="radio" name="payment_method" value="payos" checked class="payment-method-radio">
                                    <div class="pm-dark-icon-box"><i class="bi bi-bank"></i></div>
                                    <div class="pm-dark-info">
                                        <div class="pm-dark-title">Chuyển Khoản / Thẻ ATM</div>
                                        <div class="pm-dark-desc">Chuyển khoản qua số tài khoản hoặc mã QR</div>
                                    </div>
                                    <div class="pm-dark-check"></div>
                                </label>
                            </div>
                        </div>

                        </div>
                </div>

                <!-- Step 5: Success Screen -->
                <div class="tab-content" id="tab-success">
                    <div class="booking-result-screen text-center" aria-live="polite">
                        <div class="result-icon-wrapper success mb-3 mx-auto">
                            <i class="bi bi-hourglass-split" id="successStatusIcon"></i>
                        </div>
                        <h2 class="result-title mb-2" id="successStatusTitle">Đang xác minh thanh toán</h2>
                        <p class="result-subtitle mb-4" id="successStatusMessage">Đang tải dữ liệu vé đã được xác thực...</p>
                        
                        <div class="result-ticket-card mx-auto text-start">
                            <div class="d-flex mb-3">
                                <div class="ticket-poster me-4">
                                    <img src="{{ $showtime->movie->poster_display_url }}" alt="{{ $showtime->movie->title }}" class="img-fluid rounded">
                                </div>
                                <div class="ticket-details flex-grow-1">
                                    <h4 class="ticket-movie-title mb-2" id="successMovieTitle">{{ $showtime->movie->title }}</h4>
                                    <span class="ticket-format-badge mb-3 d-inline-block" id="successMovieFormat">{{ $showtime->format->name ?? '2D' }}</span>
                                    
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="ticket-label mb-1">THỜI GIAN</div>
                                            <div class="ticket-value" id="successShowtime">---</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="ticket-label mb-1">MÃ ĐƠN</div>
                                            <div class="ticket-value" id="successOrderCode">---</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ticket-divider mb-3"></div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="ticket-label mb-1">RẠP / PHÒNG</div>
                                    <div class="ticket-value" id="successTheater">---</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ticket-label mb-1">GHẾ</div>
                                    <div class="ticket-value text-danger fs-5 fw-bold" id="successSeatsInfo">---</div>
                                </div>
                                <div class="col-12">
                                    <div class="ticket-label mb-1">ĐỊA CHỈ</div>
                                    <div class="ticket-value" id="successTheaterAddress">---</div>
                                </div>
                            </div>

                            <div class="ticket-divider my-3"></div>

                            <div class="ticket-label mb-1">SẢN PHẨM</div>
                            <div id="successProductsList" class="result-line-items mb-3">
                                <div class="ticket-value text-muted">Không có sản phẩm đi kèm</div>
                            </div>

                            <div class="result-price-summary">
                                <div class="result-price-row"><span>Tạm tính</span><strong id="successSubtotal">---</strong></div>
                                <div class="result-price-row d-none" id="successVoucherRow"><span id="successVoucherLabel">Voucher</span><strong id="successVoucherDiscount">---</strong></div>
                                <div class="result-price-row d-none" id="successPointsRow"><span id="successPointsLabel">Điểm thành viên</span><strong id="successPointsDiscount">---</strong></div>
                                <div class="result-price-row result-price-total"><span>Tổng thanh toán</span><strong id="successTotalAmount">---</strong></div>
                                <div class="result-price-row result-meta-row"><span>Ngày đặt</span><span id="successDate">---</span></div>
                            </div>
                        </div>

                        <p class="result-note mt-4 mb-4 mx-auto">Cảm ơn bạn đã đặt vé. Thông báo đã được gửi đến email<br>của bạn, hãy để ý điện thoại nhé.</p>
                        
                        <div class="d-flex justify-content-center gap-3 result-actions">
                            <a href="{{ route('home') }}" class="btn btn-result btn-home">Quay về trang chủ</a>
                            <a href="#" id="viewTicketBtn" class="btn btn-result btn-view-ticket">Xem vé của tôi</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="booking-nav-buttons">
                <button id="prevStepBtn" class="nav-btn btn-back d-none">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </button>
                <button type="button" id="nextStepBtn" class="nav-btn btn-continue" disabled>
                    Tiếp tục
                </button>
                <button id="paymentBtn" class="nav-btn btn-payment d-none">
                    <i class="bi bi-credit-card"></i> Thanh toán
                </button>
            </div>
        </div>

        <!-- Sidebar Receipt -->
        <div class="booking-sidebar">
            <div class="premium-receipt">
                <div class="receipt-header">
                    <img class="receipt-header-image" src="{{ $showtime->movie->poster_display_url }}" alt="" aria-hidden="true">
                    <div class="receipt-header-overlay">
                        <h4 class="receipt-movie-title">{{ $showtime->movie->title }}</h4>
                        <p class="receipt-movie-screen">{{ $showtime->screen->theater->name }} - {{ $showtime->screen->name }}</p>
                    </div>
                </div>
                <div class="receipt-body">
                    <div class="receipt-row mt-2">
                        <span class="r-label">Thời gian</span>
                        <span class="r-value text-right">{{ $showtime->formatted_start_date }}</span>
                    </div>
                    <div class="receipt-row">
                        <span class="r-label">Vị trí</span>
                        <span class="r-value text-right" id="receiptSeatsInfo">Chưa chọn ghế</span>
                    </div>
                    <div class="receipt-row d-none" id="receiptProductsRow">
                        <span class="r-label">Bắp nước</span>
                        <span class="r-value text-right" id="receiptProductsInfo"></span>
                    </div>
                    
                    <hr class="receipt-divider">
                    
                    <div class="receipt-row">
                        <span class="r-label" id="receiptTicketLabel">Vé</span>
                        <span class="r-value text-right" id="receiptTicketPrice">0 đ</span>
                    </div>
                    <div class="receipt-row d-none" id="receiptComboPriceRow">
                        <span class="r-label">Bắp nước</span>
                        <span class="r-value text-right" id="receiptComboPrice">0 đ</span>
                    </div>
                    <div class="receipt-row receipt-promo-row d-none" id="receiptPromoRow">
                        <span class="r-label">Khuyến mãi</span>
                        <span class="r-value text-right" id="receiptPromoPrice">-0 đ</span>
                    </div>

                    <hr class="receipt-divider">
                    
                    <div class="receipt-total-section d-flex justify-content-between align-items-end mt-4 mb-4">
                        <span class="total-label receipt-total-label">Tổng<br>tiền</span>
                        <span class="total-value" id="receiptTotalPrice">0<small>đ</small></span>
                    </div>

                    <button type="button" class="btn-pay-now" id="sidebarContinueBtn" disabled>
                        Tiếp tục <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                    
                    <!-- Timer -->
                    <div class="booking-timer mt-3 text-center">
                        <i class="bi bi-clock-history text-danger"></i>
                        <span class="booking-timer-label">Thời gian giữ ghế: </span>
                        <span id="bookingTimer" class="timer-display text-white booking-timer-value">10:00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Failure Screen -->
    <div id="failureScreen" class="booking-result-screen d-none py-5">
        <div class="result-content text-center">
            <div class="result-icon-wrapper failure mb-4 mx-auto">
                <i class="bi bi-x-lg"></i>
            </div>
            
            <h2 class="result-title mb-2">Thanh toán thất bại</h2>
            <p class="result-subtitle mb-5">Đã có lỗi xảy ra hoặc bạn đã huỷ giao dịch.</p>
            
            <div class="result-ticket-card mx-auto text-start">
                <div class="d-flex mb-4">
                    <div class="ticket-poster me-4">
                        <img src="{{ $showtime->movie->poster_display_url }}" alt="{{ $showtime->movie->title }}" class="img-fluid rounded">
                    </div>
                    <div class="ticket-details flex-grow-1">
                        <h4 class="ticket-movie-title mb-2">{{ $showtime->movie->title }}</h4>
                        <span class="ticket-format-badge mb-4 d-inline-block">{{ $showtime->format->name ?? '2D' }}</span>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="ticket-label mb-1">MÃ GIAO DỊCH</div>
                                <div class="ticket-value text-white" id="failureOrderCode">---</div>
                            </div>
                            <div class="col-6">
                                <div class="ticket-label mb-1">THỜI GIAN</div>
                                <div class="ticket-value text-white" id="failureDate">---</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="result-note mt-5 mb-5 mx-auto text-muted">Vui lòng kiểm tra lại phương thức thanh toán hoặc thử đặt vé lại từ đầu.</p>
            
            <div class="d-flex justify-content-center gap-3 result-actions">
                <a href="{{ route('home') }}" class="btn btn-result btn-home">Quay về trang chủ</a>
                <a href="javascript:location.reload()" class="btn btn-result btn-view-ticket">Thử lại</a>
            </div>
        </div>
    </div>

    {{-- Mobile Bottom Sheet - Phase 1A Part 2 --}}
    <div class="booking-bottom-sheet-backdrop" id="bottomSheetBackdrop"></div>

    <div class="booking-bottom-sheet collapsed" id="bookingBottomSheet">
        {{-- Drag Handle --}}
        <div class="bottom-sheet-handle"></div>

        {{-- Mini Summary Bar (Collapsed State) --}}
        <div class="bottom-sheet-mini-summary" id="miniSummaryBar">
            <div class="mini-summary-left">
                <i class="bi bi-list"></i>
                <span class="mini-summary-items" id="miniSummaryText">
                    <span id="miniSeatsCount">0 ghế</span>
                    <span class="mini-summary-divider">•</span>
                    <span id="miniProductsCount">0 combo</span>
                </span>
            </div>
            <div class="mini-summary-right">
                <span class="mini-summary-total" id="miniTotalPrice">0₫</span>
                <span class="mini-summary-arrow">
                    <i class="bi bi-chevron-up"></i>
                </span>
            </div>
        </div>

        {{-- Full Summary Content (Expanded State) --}}
        <div class="bottom-sheet-content">
            {{-- Selected Seats Section --}}
            <div class="bottom-sheet-section" id="sheetSeatsSection">
                <div class="section-header">
                    <i class="bi bi-box-seat section-icon"></i>
                    <h3 class="section-title">Ghế đã chọn</h3>
                </div>
                <div class="section-content">
                    <div class="bottom-sheet-seats" id="sheetSeatsList">
                        <span class="bottom-sheet-empty-seat">Chưa chọn ghế</span>
                    </div>
                    <div class="section-item">
                        <span class="item-label">Giá vé</span>
                        <span class="item-value" id="sheetSeatsPrice">0₫</span>
                    </div>
                </div>
            </div>

            {{-- Products Section --}}
            <div class="bottom-sheet-section d-none" id="sheetProductsSection">
                <div class="section-header">
                    <i class="bi bi-cup-straw section-icon"></i>
                    <h3 class="section-title">Bắp nước</h3>
                </div>
                <div class="section-content" id="sheetProductsList">
                    <!-- Populated by JS -->
                </div>
            </div>

            {{-- Promotion Section --}}
            <div class="bottom-sheet-section d-none" id="sheetPromotionSection">
                <div class="section-header">
                    <i class="bi bi-gift section-icon"></i>
                    <h3 class="section-title">Ưu đãi</h3>
                </div>
                <div class="section-content">
                    <div class="section-item">
                        <span class="item-label" id="sheetPromotionCode">---</span>
                        <span class="item-value" id="sheetPromotionDiscount">0₫</span>
                    </div>
                </div>
            </div>

            {{-- Total Summary --}}
            <div class="bottom-sheet-total">
                <div class="total-row">
                    <span class="total-label">Tạm tính</span>
                    <span class="total-value" id="sheetSubtotal">0₫</span>
                </div>
                <div class="total-row d-none" id="sheetDiscountRow">
                    <span class="total-label">Giảm giá</span>
                    <span class="total-value" id="sheetDiscount">-0₫</span>
                </div>
                <div class="total-row final">
                    <span class="total-label">Tổng cộng</span>
                    <span class="total-value" id="sheetFinalTotal">0₫</span>
                </div>
            </div>

            {{-- Timer --}}
            <div class="bottom-sheet-timer d-none" id="sheetTimer">
                <i class="bi bi-clock-history timer-icon"></i>
                <span class="timer-text">Còn <span id="sheetTimerDisplay">10:00</span></span>
            </div>

            {{-- Continue Button --}}
            <div class="bottom-sheet-action">
                <button type="button" class="bottom-sheet-btn" id="sheetContinueBtn" disabled>
                    Tiếp tục
                </button>
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
        encryptedShowtimeId: @json($showtime->encrypted_id),
        basePrice: {{ $showtime->price ?? 0 }},
        screenId: {{ $showtime->screen_id }},
        movieTitle: @json($showtime->movie->title),
        startTime: @json($showtime->start_time ?? $showtime->scheduled_at),
    };
</script>
<script src="{{ asset('js/users/components/bottom-sheet.js') }}?v={{ config('app.asset_version') }}"></script>
@vite('resources/js/pages/booking.js')
@endpush
