<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>In đơn {{ $order->code }}</title>
    @vite('resources/css/staff/order-print.css')
</head>
<body data-auto-print="true">
    <div class="print-toolbar" role="toolbar" aria-label="Thao tác in">
        <div>
            <strong>Đơn {{ $order->code }}</strong>
            <span>Lần in {{ $printLog->copy_number }}</span>
        </div>
        <button type="button" id="printDocumentButton">In tài liệu</button>
    </div>

    <main class="print-document">
        @if(in_array('invoice', $sections, true))
            @include('orders.partials.invoice-slip', [
                'order' => $order,
                'printData' => $printData,
                'invoiceQr' => $orderQr,
                'isReprint' => $printLog->is_reprint,
            ])
        @endif

        @if(in_array('tickets', $sections, true))
            @foreach($printData['tickets'] as $ticket)
                @php
                    $scheduledAt = $order->showtime?->scheduled_at;
                    $endsAt = $scheduledAt && $order->showtime?->movie?->duration
                        ? $scheduledAt->copy()->addMinutes((int) $order->showtime->movie->duration)
                        : null;
                    $audienceLabels = ['adult' => 'Người lớn', 'student' => 'Sinh viên', 'child' => 'Trẻ em', 'senior' => 'Người cao tuổi'];
                @endphp
                <article class="thermal-slip movie-ticket" aria-label="Vé xem phim ghế {{ $ticket['seat_label'] }}">
                    @include('orders.partials.slip-watermark', ['rows' => 24, 'columns' => 3])
                    <header class="slip-header">
                        <div class="slip-brand">CINEMA</div>
                        <h1>VÉ XEM PHIM</h1>
                        @if($printLog->is_reprint)<span class="reprint-label">BẢN IN LẠI</span>@endif
                    </header>

                    <section class="slip-section">
                        <strong class="block-title">{{ data_get($printData, 'theater.name', 'CINEMA') }}</strong>
                        <span>{{ data_get($printData, 'theater.address', 'Địa chỉ đang cập nhật') }}</span>
                        <span>Đặt lúc: {{ optional($printData['created_at'])->format('H:i d/m/Y') }}</span>
                        <span>{{ $printData['source_label'] }}</span>
                    </section>

                    <section class="slip-section movie-section">
                        <h2>{{ data_get($printData, 'movie.title', 'Phim') }}</h2>
                        <span>{{ collect([data_get($printData, 'movie.format'), data_get($printData, 'movie.age_rating'), data_get($printData, 'movie.duration') ? data_get($printData, 'movie.duration').' phút' : null])->filter()->implode(' · ') }}</span>
                        <strong>{{ optional($scheduledAt)->format('d/m/Y') }} · {{ optional($scheduledAt)->format('H:i') }}@if($endsAt)–{{ $endsAt->format('H:i') }}@endif</strong>
                        <span>Phòng: {{ $printData['screen'] ?? '—' }}</span>
                    </section>

                    <section class="slip-section ticket-seat-grid">
                        <div>
                            <span>{{ $audienceLabels[$ticket['audience_type']] ?? ucfirst($ticket['audience_type']) }}</span>
                            <strong>{{ $ticket['seat_type'] }}</strong>
                            <span>{{ number_format($ticket['price'], 0, ',', '.') }}đ</span>
                        </div>
                        <div class="seat-highlight">
                            <span>GHẾ</span>
                            <strong>{{ $ticket['seat_label'] }}</strong>
                        </div>
                    </section>

                    <footer class="slip-code">
                        <img src="{{ $ticketQrs->get($ticket['ticket_code']) }}" alt="QR vé {{ $ticket['ticket_code'] }}">
                        <strong>{{ $ticket['ticket_code'] }}</strong>
                        <span>Xuất trình vé này tại cửa phòng chiếu.</span>
                    </footer>
                </article>
            @endforeach
        @endif

        @if(in_array('concessions', $sections, true))
            <article class="thermal-slip concession-slip" aria-label="Phiếu nhận bắp nước">
                @include('orders.partials.slip-watermark', ['rows' => 24, 'columns' => 3])
                <header class="slip-header">
                    <div class="slip-brand">CINEMA</div>
                    <h1>PHIẾU NHẬN BẮP NƯỚC</h1>
                    @if($printLog->is_reprint)<span class="reprint-label">BẢN IN LẠI</span>@endif
                </header>

                <section class="slip-section">
                    <strong class="block-title">{{ data_get($printData, 'theater.name', 'CINEMA') }}</strong>
                    <span>{{ data_get($printData, 'theater.address', 'Địa chỉ đang cập nhật') }}</span>
                    <span>Mã đơn: <strong>{{ $order->code }}</strong></span>
                    <span>Thời gian: {{ optional($printData['paid_at'] ?? $printData['created_at'])->format('H:i d/m/Y') }}</span>
                    @if($printData['served_by'])<span>Nhân viên: {{ $printData['served_by'] }}</span>@endif
                </section>

                <section class="slip-section concession-items">
                    @foreach($printData['concessions'] as $item)
                        <div class="concession-item">
                            <div class="line-item">
                                <strong>{{ $item['name'] }}</strong>
                                <strong>× {{ $item['quantity'] }}</strong>
                            </div>
                            <div class="line-item item-price">
                                <span>{{ number_format($item['unit_price'], 0, ',', '.') }}đ/sản phẩm</span>
                                <span>{{ number_format($item['total_price'], 0, ',', '.') }}đ</span>
                            </div>
                            @if($item['items'] !== [])
                                <ul>
                                    @foreach($item['items'] as $component)
                                        <li>{{ $component['product_name'] ?? $component['name'] ?? 'Sản phẩm' }} × {{ $component['quantity'] ?? 1 }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </section>

                <section class="slip-section totals-section">
                    <div class="line-item grand-total"><span>TỔNG BẮP NƯỚC</span><strong>{{ number_format(collect($printData['concessions'])->sum('total_price'), 0, ',', '.') }}đ</strong></div>
                </section>

                <footer class="slip-code">
                    <img src="{{ $orderQr }}" alt="QR nhận sản phẩm {{ $order->code }}">
                    <strong>{{ $order->code }}</strong>
                    <span>Vui lòng giữ phiếu đến khi nhận đủ sản phẩm.</span>
                </footer>
            </article>
        @endif
    </main>

    <script src="{{ asset('js/staff/order-print.js') }}?v={{ config('app.asset_version') }}" defer></script>
</body>
</html>
