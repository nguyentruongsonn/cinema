<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 4mm; }
        * { box-sizing: border-box; font-family: "DejaVu Sans", sans-serif !important; }
        html, body { background: #fff; color: #111; margin: 0; padding: 0; }
        .thermal-slip { border: 0 !important; margin: 0 auto !important; max-width: 72mm !important; width: 72mm !important; }
    </style>
</head>
<body>
    @include('orders.partials.invoice-slip', [
        'order' => $order,
        'printData' => $printData,
        'invoiceQr' => $orderQr,
        'isEmail' => false,
        'isReprint' => false,
    ])
</body>
</html>
