<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vé xem phim</title>
</head>
<body style="margin:0;background:#111;color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#1b1b1f;border:1px solid #333;border-radius:14px;overflow:hidden;">
            <div style="padding:22px 24px;border-bottom:1px solid #333;">
                <div style="font-size:20px;font-weight:800;color:#ef233c;letter-spacing:.02em;">CINEMA</div>
                <p style="margin:10px 0 0;color:#d4d4d8;font-size:15px;">Cảm ơn bạn đã đặt vé. Vui lòng xuất trình mã vé tại cửa phòng chiếu.</p>
            </div>

            <div style="padding:24px;">
                <h1 style="margin:0 0 8px;font-size:24px;color:#fff;">{{ $movie->title ?? 'Phim' }}</h1>
                <p style="margin:0 0 20px;color:#a1a1aa;font-size:14px;">Mã đơn: <strong style="color:#fff;">{{ $order->code }}</strong></p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-bottom:20px;">
                    <tr>
                        <td style="padding:8px 0;color:#a1a1aa;font-size:13px;">Thời gian</td>
                        <td style="padding:8px 0;color:#fff;font-weight:700;text-align:right;font-size:13px;">{{ $showtime?->scheduled_at?->format('H:i, d/m/Y') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#a1a1aa;font-size:13px;">Rạp / Phòng</td>
                        <td style="padding:8px 0;color:#fff;font-weight:700;text-align:right;font-size:13px;">{{ $theater->name ?? 'N/A' }} - {{ $screen->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#a1a1aa;font-size:13px;">Địa chỉ</td>
                        <td style="padding:8px 0;color:#fff;font-weight:700;text-align:right;font-size:13px;">{{ $theater->address ?? 'N/A' }}</td>
                    </tr>
                </table>

                <div style="display:block;">
                    @foreach($tickets as $ticket)
                        <div style="margin-bottom:14px;padding:16px;border:1px solid #333;border-radius:12px;background:#151518;">
                            <div style="font-size:12px;color:#a1a1aa;text-transform:uppercase;letter-spacing:.08em;">Mã vé</div>
                            <div style="margin-top:6px;font-size:22px;line-height:1.2;font-weight:900;color:#fff;letter-spacing:.04em;">{{ $ticket->ticket_code }}</div>
                            <div style="margin-top:12px;font-size:14px;color:#d4d4d8;">
                                Ghế: <strong style="color:#ef233c;font-size:18px;">{{ $ticket->seat?->label ?? 'N/A' }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p style="margin:18px 0 0;color:#a1a1aa;font-size:13px;line-height:1.6;">Mỗi mã vé chỉ được sử dụng một lần. Không chia sẻ mã vé cho người khác để tránh bị sử dụng trước.</p>
            </div>
        </div>
    </div>
</body>
</html>
