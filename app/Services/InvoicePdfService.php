<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Dompdf\Dompdf;
use Dompdf\Options;

final class InvoicePdfService
{
    public function __construct(
        private readonly OrderPrintService $orderPrintService,
        private readonly BookingQrCodeService $qrCodeService,
    ) {}

    public function render(Order $order): string
    {
        $printData = $this->orderPrintService->printData($order);
        $html = view('orders.invoice-pdf', [
            'order' => $order,
            'printData' => $printData,
            'orderQr' => $this->qrCodeService->dataUri($order->code, 220),
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('chroot', [base_path(), storage_path()]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($this->paperSize($printData));
        $dompdf->render();

        return $dompdf->output();
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} */
    private function paperSize(array $printData): array
    {
        $heightMm = 225
            + (count($printData['tickets']) * 9)
            + (count($printData['concessions']) * 12)
            + ($printData['discount_amount'] > 0 ? 10 : 0);
        $heightMm = min(600, max(190, $heightMm));
        $pointsPerMillimeter = 72 / 25.4;

        return [
            0,
            0,
            80 * $pointsPerMillimeter,
            $heightMm * $pointsPerMillimeter,
        ];
    }
}
