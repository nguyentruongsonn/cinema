@extends('layouts.app')

@section('title', 'Thanh toán - ' . config('app.name'))

@section('styles')
<link rel="stylesheet" href="{{ asset('css/users/pages/payment.css') }}">
@endsection

@section('content')
<div class="payment-container">
    <div class="container py-4">
        <div class="row g-4">
            <!-- Left: Order Summary -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt-cutoff me-2"></i>
                            Thông tin đơn hàng
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Movie Info -->
                        <div class="movie-info mb-4">
                            <div class="d-flex gap-3">
                                <img src="{{ $order->showtime->movie->poster_display_url ?? 'https://via.placeholder.com/80x120?text=No+Image' }}"
                                     alt="{{ $order->showtime->movie->title }}"
                                     class="movie-poster">
                                <div class="flex-grow-1">
                                    <h6 class="movie-title mb-2">{{ $order->showtime->movie->title }}</h6>
                                    <div class="movie-details text-muted small">
                                        <div class="mb-1">
                                            <i class="bi bi-building me-1"></i>
                                            <strong>{{ $order->showtime->screen->theater->name }}</strong>
                                        </div>
                                        <div class="mb-1">
                                            <i class="bi bi-tv me-1"></i>
                                            {{ $order->showtime->screen->name }}
                                        </div>
                                        <div class="mb-1">
                                            <i class="bi bi-calendar-event me-1"></i>
                                            {{ \Carbon\Carbon::parse($order->showtime->start_time)->format('d/m/Y') }}
                                            <span class="mx-2">|</span>
                                            <i class="bi bi-clock me-1"></i>
                                            {{ \Carbon\Carbon::parse($order->showtime->start_time)->format('H:i') }}
                                        </div>
                                        <div>
                                            <span class="badge bg-primary me-1">{{ $order->showtime->format->name }}</span>
                                            <span class="badge bg-info">{{ $order->showtime->versionType->name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Seats Info -->
                        <div class="seats-info">
                            <h6 class="mb-3">Ghế đã chọn</h6>
                            <div class="row g-2 mb-3">
                                @foreach($order->items as $item)
                                <div class="col-auto">
                                    <div class="seat-badge {{ $item->seat->seatType->name === 'VIP' ? 'vip' : 'regular' }}">
                                        <i class="bi bi-{{ $item->seat->seatType->name === 'VIP' ? 'star-fill' : 'circle-fill' }} me-1"></i>
                                        {{ $item->seat->label }}
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Price Breakdown -->
                            <div class="price-breakdown">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Số lượng ghế:</span>
                                    <span class="fw-semibold">{{ $order->items->count() }} ghế</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Giá vé:</span>
                                    <span>{{ number_format($order->items->sum('base_price'), 0, ',', '.') }}₫</span>
                                </div>
                                @if($order->items->sum('surcharge') > 0)
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Phụ thu:</span>
                                    <span>{{ number_format($order->items->sum('surcharge'), 0, ',', '.') }}₫</span>
                                </div>
                                @endif
                                <hr>
                                <div class="d-flex justify-content-between fs-5">
                                    <span class="fw-bold">Tổng cộng:</span>
                                    <span class="text-danger fw-bold">{{ number_format($order->total_amount, 0, ',', '.') }}₫</span>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-clock-history me-2"></i>
                            <small>Đơn hàng sẽ tự động hủy sau <strong id="orderTimer">15:00</strong> nếu chưa thanh toán.</small>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-credit-card me-2"></i>
                            Phương thức thanh toán
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="payment-methods" id="paymentMethods">
                            <!-- VNPay -->
                            <div class="payment-method" data-method="vnpay">
                                <input type="radio" id="vnpay" name="payment_method" value="vnpay" checked>
                                <label for="vnpay">
                                    <div class="method-icon">
                                        <img src="https://vnpay.vn/s1/statics.vnpay.vn/2023/9/06ncktiwd6dc1694418196384.png" alt="VNPay">
                                    </div>
                                    <div class="method-info">
                                        <strong>VNPay</strong>
                                        <small class="text-muted d-block">Thanh toán qua VNPay QR, ATM, Visa, Mastercard</small>
                                    </div>
                                </label>
                            </div>

                            <!-- ATM Card -->
                            <div class="payment-method" data-method="atm">
                                <input type="radio" id="atm" name="payment_method" value="atm">
                                <label for="atm">
                                    <div class="method-icon">
                                        <i class="bi bi-credit-card-2-front fs-3 text-primary"></i>
                                    </div>
                                    <div class="method-info">
                                        <strong>Thẻ ATM nội địa</strong>
                                        <small class="text-muted d-block">Thanh toán bằng thẻ ATM có Internet Banking</small>
                                    </div>
                                </label>
                            </div>

                            <!-- Credit Card -->
                            <div class="payment-method" data-method="credit">
                                <input type="radio" id="credit" name="payment_method" value="credit">
                                <label for="credit">
                                    <div class="method-icon">
                                        <i class="bi bi-credit-card fs-3 text-success"></i>
                                    </div>
                                    <div class="method-info">
                                        <strong>Thẻ quốc tế</strong>
                                        <small class="text-muted d-block">Visa, Mastercard, JCB, Amex</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Summary & Actions -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top payment-sticky-summary">
                    <div class="card-body">
                        <div class="order-code mb-3 p-3 bg-light rounded text-center">
                            <small class="text-muted d-block mb-1">Mã đơn hàng</small>
                            <h6 class="mb-0 font-monospace">{{ $order->code }}</h6>
                        </div>

                        <div class="summary-row">
                            <span>Số ghế:</span>
                            <strong>{{ $order->items->count() }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Tổng tiền:</span>
                            <strong class="text-danger">{{ number_format($order->total_amount, 0, ',', '.') }}₫</strong>
                        </div>

                        <hr>

                        <button type="button" class="btn btn-primary btn-lg w-100 mb-2" id="btnPayment">
                            <i class="bi bi-wallet2 me-2"></i>
                            Thanh toán
                        </button>

                        <button type="button" class="btn btn-outline-danger w-100" id="btnCancelOrder">
                            <i class="bi bi-x-circle me-2"></i>
                            Hủy đơn hàng
                        </button>

                        <div class="payment-secure mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                Thanh toán an toàn & bảo mật
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay d-none">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3 text-white">Đang xử lý thanh toán...</p>
</div>
@endsection

@section('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    window.ORDER_DATA = @json([
        'id' => $order->id,
        'code' => $order->code,
        'total_amount' => $order->total_amount,
        'expired_at' => $order->expired_at ? $order->expired_at->toISOString() : null
    ]);
</script>
<script type="module" src="{{ asset('js/users/pages/payment.js') }}?v={{ config('app.asset_version') }}"></script>
@endsection
