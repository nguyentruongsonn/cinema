<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hóa đơn thanh toán CINEMA</title>
</head>
<body style="margin:0;padding:0;background:#f2f2f2;color:#1f1f1f;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f2f2f2;border-collapse:collapse;width:100%;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" width="520" cellspacing="0" cellpadding="0" border="0" style="background:#fff;border:1px solid #dedede;border-collapse:collapse;max-width:520px;width:100%;">
                <tr>
                    <td align="center" style="border-bottom:1px solid #e5e5e5;padding:24px 20px;">
                        <div style="color:#ed1c24;font-size:24px;font-weight:900;letter-spacing:.08em;">CINEMA</div>
                        <h1 style="font-size:20px;line-height:28px;margin:12px 0 0;">Thanh toán thành công</h1>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;line-height:21px;padding:22px 24px;">
                        <p style="margin:0 0 12px;">Xin chào {{ $order->user?->name ?? 'Quý khách' }},</p>
                        <p style="margin:0 0 12px;">Giao dịch cho đơn <strong>{{ $order->code }}</strong> đã được xác nhận thành công.</p>
                        <p style="margin:0 0 12px;">Hóa đơn thanh toán chính thức được đính kèm trong email dưới dạng PDF. File này được tạo trực tiếp từ cùng mẫu hóa đơn sử dụng tại quầy in.</p>
                        <p style="margin:0;"><strong>Lưu ý:</strong> Hóa đơn không phải vé vào phòng chiếu. Vui lòng xuất trình Booking ID tại quầy để nhận vé xem phim chính thức.</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background:#fafafa;border-top:1px solid #e5e5e5;color:#777;font-size:11px;line-height:18px;padding:16px 20px;">
                        Email được gửi tự động từ CINEMA, vui lòng không trả lời email này.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
