<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\BookingQrCodeService;
use App\Services\OrderPrintService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    use ApiResponse;

    public function lookup(Request $request, OrderPrintService $printService): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $order = $printService->findByIdentifier($validated['identifier']);
        $printService->prepare($order, $actor);

        return $this->successResponse(
            $printService->summary($order),
            'Đơn hàng hợp lệ và sẵn sàng in.'
        );
    }

    public function show(
        Request $request,
        Order $order,
        OrderPrintService $printService,
        BookingQrCodeService $qrCodeService,
    ): View {
        /** @var User $actor */
        $actor = $request->user();
        $printService->prepare($order, $actor);

        $availableSections = $printService->summary($order)['available_sections'];
        $requestedSections = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('sections', 'invoice,tickets,concessions'))
        )));
        $sections = array_values(array_intersect($requestedSections, $availableSections));
        abort_if($sections === [], 422, 'Đơn hàng không có nội dung phù hợp để in.');

        $printLog = $printService->recordPrintRequest(
            $order,
            $actor,
            $sections,
            $request->string('reason')->trim()->value() ?: null,
        );
        $printOrder = $printService->prepare($order->fresh(), $actor);
        $printData = $printService->printData($printOrder);
        $orderQr = $qrCodeService->dataUri($order->code, 220);
        $ticketQrs = collect($printData['tickets'])->mapWithKeys(
            fn (array $ticket): array => [
                $ticket['ticket_code'] => $qrCodeService->dataUriForValue($ticket['ticket_code'], 180),
            ]
        );

        return view('staff.orders.print', compact(
            'order',
            'printData',
            'sections',
            'printLog',
            'orderQr',
            'ticketQrs',
        ));
    }
}
