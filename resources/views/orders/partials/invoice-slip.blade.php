@php
    $invoiceQr = $invoiceQr ?? '';
    $isEmail = (bool) ($isEmail ?? false);
    $isReprint = $isReprint ?? false;
    $paidAt = $printData['paid_at'] ?? $printData['created_at'];
    $audienceLabels = [
        'adult' => 'Người lớn',
        'student' => 'Sinh viên',
        'child' => 'Trẻ em',
        'senior' => 'Người cao tuổi',
    ];
@endphp

<article
    class="thermal-slip invoice-slip"
    data-invoice-template="shared"
    aria-label="Hóa đơn thanh toán"
    style="background:#fff;border:1px solid #d4d4d4;box-sizing:border-box;color:#111;font-family:'Courier New',Courier,monospace;margin:0 auto;max-width:100%;overflow:hidden;padding:0;position:relative;width:72mm;"
>
    @include('orders.partials.slip-watermark', ['rows' => 24, 'columns' => 3, 'emailMode' => $isEmail])

    <header class="slip-header" style="border-bottom:1px dashed #555;padding:18px 16px;text-align:center;position:relative;z-index:1;">
        <div class="slip-brand" style="font-family:Arial,sans-serif;font-size:16px;font-weight:900;letter-spacing:.12em;">CINEMA</div>
        <h1 style="font-family:'Courier New',Courier,monospace;font-size:16px;letter-spacing:.05em;line-height:1.25;margin:10px 0 0;white-space:nowrap;">HÓA ĐƠN THANH TOÁN</h1>
        @if($isReprint)
            <span class="reprint-label" style="border:1px solid #111;display:inline-block;font-size:10px;font-weight:700;margin-top:8px;padding:3px 6px;">BẢN IN LẠI</span>
        @endif
    </header>

    <section class="slip-section" style="border-bottom:1px dashed #555;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.55;padding:14px 16px;position:relative;z-index:1;">
        <strong class="block-title" style="display:block;font-size:13px;text-transform:uppercase;">{{ data_get($printData, 'theater.name', 'CINEMA') }}</strong>
        <span style="display:block;">{{ data_get($printData, 'theater.address', 'Địa chỉ đang cập nhật') }}</span>
        @if(data_get($printData, 'theater.phone'))
            <span style="display:block;">Điện thoại: {{ data_get($printData, 'theater.phone') }}</span>
        @endif
        <span style="display:block;margin-top:5px;">Mã đơn: <strong>{{ $order->code }}</strong></span>
        <span style="display:block;">Kênh đặt: {{ $printData['source_label'] }}</span>
        <span style="display:block;">Thanh toán: {{ $printData['payment_method_label'] }}</span>
        <span style="display:block;">Thời gian: {{ optional($paidAt)->format('H:i d/m/Y') }}</span>
        @if(data_get($printData, 'customer.name'))
            <span style="display:block;">Khách hàng: {{ data_get($printData, 'customer.name') }}</span>
        @endif
        @if(data_get($printData, 'customer.phone'))
            <span style="display:block;">SĐT: {{ data_get($printData, 'customer.phone') }}</span>
        @endif
        @if($printData['served_by'])
            <span style="display:block;">Nhân viên: {{ $printData['served_by'] }}</span>
        @endif
    </section>

    @if($printData['tickets'] !== [])
        <section class="slip-section" style="border-bottom:1px dashed #555;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;padding:14px 16px;position:relative;z-index:1;">
            <strong class="section-heading" style="display:block;font-size:12px;margin-bottom:5px;">VÉ XEM PHIM</strong>
            @foreach($printData['tickets'] as $ticket)
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;width:100%;">
                    <tr>
                        <td style="padding:3px 8px 3px 0;">Ghế {{ $ticket['seat_label'] }} · {{ $ticket['seat_type'] }} · {{ $audienceLabels[$ticket['audience_type']] ?? ucfirst($ticket['audience_type']) }}</td>
                        <td align="right" style="font-weight:700;padding:3px 0;white-space:nowrap;">{{ number_format($ticket['price'], 0, ',', '.') }}đ</td>
                    </tr>
                </table>
            @endforeach
        </section>
    @endif

    @if($printData['concessions'] !== [])
        <section class="slip-section" style="border-bottom:1px dashed #555;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;padding:14px 16px;position:relative;z-index:1;">
            <strong class="section-heading" style="display:block;font-size:12px;margin-bottom:5px;">BẮP NƯỚC</strong>
            @foreach($printData['concessions'] as $item)
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;width:100%;">
                    <tr>
                        <td style="padding:3px 8px 3px 0;">{{ $item['name'] }} × {{ $item['quantity'] }}</td>
                        <td align="right" style="font-weight:700;padding:3px 0;white-space:nowrap;">{{ number_format($item['total_price'], 0, ',', '.') }}đ</td>
                    </tr>
                </table>
            @endforeach
        </section>
    @endif

    <section class="slip-section totals-section" style="border-bottom:1px dashed #555;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;padding:14px 16px;position:relative;z-index:1;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;width:100%;">
            <tr><td style="padding:3px 8px 3px 0;">Tạm tính</td><td align="right" style="padding:3px 0;white-space:nowrap;">{{ number_format($printData['subtotal'], 0, ',', '.') }}đ</td></tr>
            @if($printData['voucher_discount'] > 0)
                <tr><td style="padding:3px 8px 3px 0;">Ưu đãi</td><td align="right" style="padding:3px 0;white-space:nowrap;">-{{ number_format($printData['voucher_discount'], 0, ',', '.') }}đ</td></tr>
            @endif
            @if($printData['point_discount'] > 0)
                <tr><td style="padding:3px 8px 3px 0;">Điểm tích lũy ({{ $printData['points_used'] }} điểm)</td><td align="right" style="padding:3px 0;white-space:nowrap;">-{{ number_format($printData['point_discount'], 0, ',', '.') }}đ</td></tr>
            @endif
            @if($printData['other_discount'] > 0)
                <tr><td style="padding:3px 8px 3px 0;">Giảm giá khác</td><td align="right" style="padding:3px 0;white-space:nowrap;">-{{ number_format($printData['other_discount'], 0, ',', '.') }}đ</td></tr>
            @endif
            <tr>
                <td style="font-size:11px;font-weight:900;padding:10px 8px 2px 0;white-space:nowrap;">TỔNG THANH TOÁN</td>
                <td align="right" style="font-size:15px;font-weight:900;padding:10px 0 2px;white-space:nowrap;">{{ number_format($printData['total_amount'], 0, ',', '.') }}đ</td>
            </tr>
            <tr><td colspan="2" align="right" style="color:#555;font-size:9px;padding:0;">Đã bao gồm VAT 5%: {{ number_format($printData['vat_amount'], 0, ',', '.') }}đ</td></tr>
        </table>
    </section>

    <footer class="slip-code" style="display:block;font-family:'Courier New',Courier,monospace;font-size:9px;padding:18px 16px;text-align:center;position:relative;z-index:1;">
        <img src="{{ $invoiceQr }}" width="128" height="128" alt="QR hóa đơn {{ $order->code }}" style="border:0;display:block;height:34mm;image-rendering:crisp-edges;margin:0 auto;width:34mm;">
        <strong style="display:block;font-size:9.5px;letter-spacing:.02em;margin-top:6px;white-space:nowrap;">{{ $order->code }}</strong>
        <span style="display:block;margin-top:5px;">Quét QR tại quầy để tra cứu và in vé.</span>
    </footer>
</article>
