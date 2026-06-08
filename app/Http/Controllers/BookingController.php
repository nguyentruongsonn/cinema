<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Showtime;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Throwable;

class BookingController extends Controller
{
    /**
     * Display the booking page for a specific showtime.
     */
    public function show(Request $request, int $showtimeId)
    {
        $showtime = Showtime::with([
            'movie',
            'screen.theater',
            'format',
            'subtitle',
        ])->findOrFail($showtimeId);

        $paymentData = null;
        $isPaymentSuccess = false;
        $isPaymentCancelled = false;

        $paymentStatus = $request->query('paymentStatus');
        $orderCode     = $request->query('orderCode');

        if ($paymentStatus === 'success' && $orderCode) {
            $order = Order::where('gateway_order_code', $orderCode)->first();

            if ($order && $order->status !== Order::STATUS_PAID) {
                try {
                    app(PaymentService::class)->syncFromGateway($order);
                    $order->refresh();
                } catch (Throwable) {
                    // Ignore sync failures — still show success screen
                }
            }

            $isPaymentSuccess = true;
            $paymentData = [
                'orderCode'  => $orderCode,
                'totalAmount'=> $order ? number_format((float)$order->total_amount, 0, ',', '.') . ' đ' : '---',
                'orderNum'   => $order ? ($order->code ?? $orderCode) : $orderCode,
                'date'       => $order ? $order->created_at->format('d/m/Y') : now()->format('d/m/Y'),
            ];
        } elseif (($paymentStatus === 'cancelled' || $paymentStatus === 'cancel') && $orderCode) {
            $isPaymentCancelled = true;
            $paymentData = [
                'orderCode' => $orderCode,
                'date'      => now()->format('d/m/Y'),
            ];
        }

        return view('users.booking.index', [
            'showtime'           => $showtime,
            'isPaymentSuccess'   => $isPaymentSuccess,
            'isPaymentCancelled' => $isPaymentCancelled,
            'paymentData'        => $paymentData,
            'paymentHandled'     => $isPaymentSuccess || $isPaymentCancelled,
        ]);
    }
}
