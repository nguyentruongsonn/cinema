@extends('layouts.admin')

@section('title', 'Bán vé (POS)')
@section('header_title', 'Bán vé (POS)')

@section('topbar_center')
    <div class="pos-topbar-theater">
        <label class="visually-hidden" for="concessionTheater">Rạp phục vụ</label>
        <select class="admin-select" id="concessionTheater" aria-label="Rạp phục vụ">
            <option value="">Đang tải rạp...</option>
        </select>
    </div>
@endsection

@push('styles')
    <!-- Booking CSS (For Seat Map) -->
    <link rel="stylesheet" href="{{ asset('css/users/pages/booking.css') }}?v={{ filemtime(public_path('css/users/pages/booking.css')) }}">
    <!-- POS CSS -->
    <link rel="stylesheet" href="{{ asset('css/pos/pos.css') }}?v={{ filemtime(public_path('css/pos/pos.css')) }}">
@endpush

@section('content')
<div class="pos-integrated-container">
    {{-- ── Main Layout ─────────────────────────────────────── --}}
    <main class="pos-main">
        {{-- Date Filter (Admin Style) --}}
        <div class="admin-filter-container mb-4">
            <div class="admin-filter-bar align-items-end flex-wrap gap-2">
                <div class="admin-filter-fields align-items-end">
                    <div class="admin-filter-group auto-width">
                        <label for="posFilterDate" class="filter-label mb-1">Ngày chiếu</label>
                        <input type="date" id="posFilterDate" class="admin-filter-input filter-date-md" />
                    </div>
                    <div class="admin-filter-group auto-width">
                        <div class="pos-date-shortcuts d-flex gap-2" role="group" aria-label="Chọn ngày chiếu nhanh">
                            <button class="pos-date-shortcut admin-btn admin-btn-secondary active" data-offset="0" type="button">Hôm nay</button>
                            <button class="pos-date-shortcut admin-btn admin-btn-secondary" data-offset="1" type="button">Ngày mai</button>
                            <button class="pos-date-shortcut admin-btn admin-btn-secondary" data-offset="2" type="button">Ngày kia</button>
                        </div>
                    </div>
                </div>
                <button id="btnApplyDateFilter" class="admin-filter-btn admin-filter-primary-action ms-auto" type="button">
                    <i class="bi bi-search"></i> Lọc Suất Chiếu
                </button>
            </div>
        </div>

        {{-- Step 1: Movie (Showtimes) --}}
    <div class="pos-panel active" id="panel-step-1" role="tabpanel">
        <div id="showtimeContainer">
            <div class="pos-loading"><div class="pos-spinner"></div> Đang tải...</div>
        </div>
    </div>

    {{-- Step 2: Seat Selection --}}
    <div class="pos-panel" id="panel-step-2" role="tabpanel" hidden>
        <div class="pos-seat-layout-wrapper">
            <!-- Seat Map Area -->
            <div class="pos-seat-map-area">
                <!-- SVG Gradient Definitions -->
                <svg class="booking-svg-definitions" width="0" height="0" aria-hidden="true">
                    <defs>
                        <linearGradient id="grad-standard" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#52596b"/>
                            <stop offset="100%" stop-color="#2e3340"/>
                        </linearGradient>
                        <linearGradient id="grad-vip" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#ff6a3d"/>
                            <stop offset="100%" stop-color="#c0392b"/>
                        </linearGradient>
                        <linearGradient id="grad-couple" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#b06ae9"/>
                            <stop offset="100%" stop-color="#7b2fa0"/>
                        </linearGradient>
                        <linearGradient id="grad-selected" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#ff3344"/>
                            <stop offset="100%" stop-color="#c0000f"/>
                        </linearGradient>
                    </defs>
                </svg>

                <div class="screen-display">
                    <div class="screen-label">MÀN HÌNH CHIẾU</div>
                </div>

                <div id="seatPanelContent">
                    <div id="seatMapContainer" class="seat-map-container">
                        <!-- Skeleton Loading -->
                        <div class="seat-map-skeleton w-100" id="seatMapSkeleton"></div>
                        <!-- Actual seat map rendered by JS -->
                        <div id="seatMap" class="seat-grid mx-auto d-none"></div>
                        <!-- Column labels -->
                        <div id="seatGridColLabels" class="seat-grid-col-labels mx-auto mt-2 d-none"></div>
                    </div>
                </div>

                <div class="seat-legend pos-seat-legend-centered">
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
        </div>
    </div>

    {{-- Step 3: Snacks --}}
    <div class="pos-panel" id="panel-step-3" role="tabpanel" hidden>
        <div class="pos-category-filters">
            <button class="pos-cat-btn active" data-cat="all">Tất cả</button>
            <button class="pos-cat-btn" data-cat="combo">Combo</button>
            <button class="pos-cat-btn" data-cat="drink">Nước uống</button>
            <button class="pos-cat-btn" data-cat="food">Đồ ăn</button>
        </div>
        <div class="pos-product-grid" id="productGrid">
            <div class="pos-loading"><div class="pos-spinner"></div> Đang tải...</div>
        </div>
    </div>

    {{-- Step 4: Payment & Customer Info --}}
    <div class="pos-panel" id="panel-step-4" role="tabpanel" hidden>
        <div class="pos-payment-layout">
            <section class="pos-payment-left admin-card" aria-labelledby="customerPanelTitle">
                <div class="admin-card-header">
                    <div>
                        <h3 class="admin-card-title" id="customerPanelTitle">Thông tin khách hàng</h3>
                    </div>
                </div>
                <div class="admin-card-body">
                    <label class="admin-form-label" for="customerPhone">Số điện thoại khách hàng <span>(không bắt buộc)</span></label>
                    <div class="pos-phone-row">
                        <input type="tel" id="customerPhone" class="admin-input" placeholder="Nhập SĐT khách hàng (tùy chọn)..." maxlength="11" autocomplete="off">
                        <button class="admin-btn admin-btn-secondary" id="btnLookup" type="button"><i class="bi bi-search"></i> Tìm</button>
                        <button class="admin-btn admin-btn-secondary admin-btn-icon" id="btnWalkIn" type="button" title="Khách vãng lai" aria-label="Chọn khách vãng lai"><i class="bi bi-person-plus"></i></button>
                    </div>

                <div class="pos-name-form mt-3 pos-ui-hidden" id="newCustomerForm">
                    <div class="pos-customer-create-row">
                        <input type="text" id="customerName" class="admin-input" placeholder="Nhập Họ và tên..." maxlength="100">
                        <button class="admin-btn admin-btn-primary pos-nowrap" id="btnCreateCustomer" type="button"><i class="bi bi-person-check"></i> Lưu và chọn</button>
                    </div>
                </div>

                <div class="pos-customer-card admin-card admin-card-compact mt-3 pos-ui-hidden" id="customerCard">
                    <div class="admin-card-body">
                        <div class="pos-customer-header">
                            <div class="pos-customer-avatar" id="customerAvatar">–</div>
                            <div class="pos-customer-main">
                                <div class="pos-customer-title-row">
                                    <div class="pos-customer-name" id="customerDisplayName">–</div>
                                    <div id="customerTypeTag" class="pos-customer-tag"></div>
                                </div>
                                <div class="pos-customer-phone" id="customerDisplayPhone">–</div>
                            </div>
                            <button class="admin-btn admin-btn-outline admin-btn-sm pos-ui-hidden" id="btnChangeCustomer" type="button"><i class="bi bi-arrow-repeat"></i> Đổi khách</button>
                        </div>

                        <div class="pos-loyalty-bar pos-ui-hidden" id="loyaltyBar">
                            <div>
                                <div class="pos-loyalty-points" id="loyaltyPoints">0</div>
                                <div class="pos-loyalty-label">điểm tích lũy</div>
                            </div>
                            <div class="pos-loyalty-value" id="loyaltyValueVnd"></div>
                        </div>
                        <div class="pos-points-row pos-ui-hidden" id="pointsRedeemRow">
                            <label class="admin-form-label" for="pointsToUse">Dùng điểm</label>
                            <input type="number" id="pointsToUse" class="pos-points-input admin-input admin-input-sm" min="0" value="0">
                            <span id="pointsDiscountDisplay" class="pos-points-discount"></span>
                            <button class="admin-btn admin-btn-secondary admin-btn-sm" id="btnApplyPoints" type="button">Áp dụng</button>
                        </div>
                        <div class="pos-student-row mt-2 d-flex align-items-center gap-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isStudentToggleVisible">
                                <label class="form-check-label text-white" for="isStudentToggleVisible">🎓 Đối tượng Học sinh / Sinh viên</label>
                            </div>
                        </div>
                    </div>
                    <div class="admin-card-footer pos-customer-action-row" id="customerActionRow">
                        <button class="admin-btn admin-btn-primary admin-btn-block" id="btnSelectCustomer" type="button"><i class="bi bi-check-lg"></i> Chọn khách hàng này</button>
                    </div>
                </div>
                </div>
            </section>
            
            <section class="pos-payment-right admin-card" aria-labelledby="paymentPanelTitle">
                <div class="admin-card-header">
                    <div>
                        <h3 class="admin-card-title" id="paymentPanelTitle">Thanh toán</h3>
                        <div class="admin-card-subtitle">Kiểm tra đơn hàng và chọn phương thức thanh toán.</div>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="pos-cart-summary admin-card admin-card-flat">
                        <div class="admin-card-body">
                            <div id="cartSeatItems"></div>
                            <div id="cartProductItems"></div>
                            <div id="cartDiscountItems"></div>
                        </div>
                    </div>

                    <div class="pos-payment-methods mt-4" role="radiogroup" aria-label="Phương thức thanh toán">
                        <button class="pos-payment-method cash active" id="pmCash" type="button" role="radio" aria-checked="true">
                            <div class="pos-payment-method-icon" aria-hidden="true">💵</div>
                            <div class="pos-payment-method-label">Tiền mặt</div>
                        </button>
                        <button class="pos-payment-method qr" id="pmQr" type="button" role="radio" aria-checked="false">
                            <div class="pos-payment-method-icon" aria-hidden="true">📱</div>
                            <div class="pos-payment-method-label">QR PayOS</div>
                        </button>
                    </div>
                    <div class="pos-transaction-actions">
                        <button class="admin-btn admin-btn-outline" id="btnCancelTransaction" type="button">
                            <i class="bi bi-x-circle"></i> Hủy giao dịch
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

{{-- ── Bottom Footer ─────────────────────────────────────── --}}
<footer class="pos-footer">
    <div class="pos-footer-left">
        <div class="pos-total-label">TỔNG CỘNG</div>
        <div class="pos-total-amount" id="footerTotal">0đ</div>
        
        {{-- Hidden summary values required by JS logic --}}
        <div class="pos-ui-hidden">
            <span id="summarySubtotal">0đ</span>
            <span id="summaryStudentDiscount">-0đ</span>
            <span id="summaryPointsDiscount">-0đ</span>
            <span id="summaryTotal">0đ</span>
            <span id="summaryEarnPoints">0</span>
            <span id="cartItemCount">0</span>
            <input type="checkbox" id="isStudentToggle">
            <button id="btnClearCart"></button>
            <button id="btnConfirmOrder"></button>
        </div>
    </div>
    <div class="pos-footer-right">
        <button class="pos-btn-footer-back pos-ui-hidden" id="btnFooterBack">Quay lại</button>
        <button class="pos-btn-footer-next" id="btnFooterNext" disabled>CHỌN GHẾ <i class="bi bi-arrow-right"></i></button>
    </div>
</footer>

{{-- ── Bootstrap Modals (QR, Cash, Success) ────────────────────────── --}}
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Thanh toán QR</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="btnCancelQrHeader"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="qrContainer" class="mb-3">
                    <img id="qrImage" src="" alt="QR" class="img-thumbnail bg-white pos-qr-image">
                </div>
                <div class="fs-3 fw-bold text-warning mb-2" id="qrAmount">0đ</div>
                <div class="text-secondary small">
                    <div class="spinner-border spinner-border-sm me-2 text-warning" role="status" id="qrSpinner"></div>
                    <span id="qrStatusText">Đang chờ thanh toán...</span>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btnCancelQr">Hủy</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cashModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Xác nhận tiền mặt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="fs-1 mb-3">💵</div>
                <p class="text-secondary mb-1">Số tiền cần thu của khách:</p>
                <div class="fs-3 fw-bold text-success" id="cashAmount">0đ</div>
                <p class="text-secondary small mb-0 mt-3">Quay lại chỉnh đơn sẽ giữ nguyên ghế và các sản phẩm đã chọn.</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btnCancelCash">Quay lại chỉnh đơn</button>
                <button type="button" class="btn btn-success" id="btnConfirmCash">Đã nhận tiền</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelTransactionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Hủy giao dịch?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-secondary">Đơn hàng chưa thanh toán sẽ bị hủy. Các ghế đang giữ sẽ được trả lại để khách khác có thể chọn.</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại</button>
                <button type="button" class="btn btn-danger" id="btnConfirmCancelTransaction">Hủy giao dịch</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success"><i class="bi bi-check-circle-fill me-2"></i>Giao dịch thành công!</h5>
            </div>
            <div class="modal-body py-4">
                <div class="text-center mb-3">
                    <div class="display-4 text-success"><i class="bi bi-check-lg"></i></div>
                    <h4 class="mt-2" id="successOrderCode">Mã đơn: –</h4>
                </div>
                <div id="successDetails" class="border border-secondary rounded p-3 bg-secondary bg-opacity-10 text-secondary">
                </div>
                <div class="pos-ui-hidden"><span id="successPointsEarned">0</span></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-primary w-100" id="btnNewTransaction" data-bs-dismiss="modal">Giao dịch mới</button>
            </div>
        </div>
    </div>
</div>


</div> <!-- End pos-integrated-container -->

@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
window.POS_CONFIG = {
    staffId:     {{ auth()->id() ?? 0 }},
    staffName:   @json(auth()->user()?->name ?? ''),
    staffRole:   @json(auth()->user()?->role?->slug ?? ''),
    theaterIds:  @json(auth()->user()?->theaters?->pluck('id') ?? []),
    theaterNames: @json(auth()->user()?->theaters?->pluck('name') ?? []),
    csrfToken:   '{{ csrf_token() }}',
    apiBase:     '/api/v1/pos',
    adminBase:   '/api/v1/admin',
    authBase:    '/api/v1/auth',
    pointsToVnd: 1000,
    earnRate:    10000,
};
</script>

<script src="{{ asset('js/pos/pos-utils.js') }}?v={{ filemtime(public_path('js/pos/pos-utils.js')) }}"></script>
<script src="{{ asset('js/pos/pos-customer.js') }}?v={{ filemtime(public_path('js/pos/pos-customer.js')) }}"></script>
<script src="{{ asset('js/pos/pos-showtime.js') }}?v={{ filemtime(public_path('js/pos/pos-showtime.js')) }}"></script>
<script src="{{ asset('js/pos/pos-seat.js') }}?v={{ filemtime(public_path('js/pos/pos-seat.js')) }}"></script>
<script src="{{ asset('js/pos/pos-cart.js') }}?v={{ filemtime(public_path('js/pos/pos-cart.js')) }}"></script>
<script src="{{ asset('js/pos/pos-payment.js') }}?v={{ filemtime(public_path('js/pos/pos-payment.js')) }}"></script>
<script src="{{ asset('js/pos/pos-app.js') }}?v={{ filemtime(public_path('js/pos/pos-app.js')) }}"></script>
@endpush
