<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Showtime;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Display the booking page for a specific showtime.
     */
    public function show(Request $request, string $encryptedShowtimeId)
    {
        try {
            $showtimeId = (int) Crypt::decryptString($encryptedShowtimeId);
        } catch (DecryptException) {
            abort(404, 'Invalid showtime identifier.');
        }
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
        $orderCode     = (string) $request->query('orderCode', '');

        if ($paymentStatus === 'success' && $orderCode) {
            $order = $this->orderService->findByGatewayCode((int) $orderCode);

            // Only check user ownership if authenticated (session may expire during payment)
            if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
                $order = null;
            }

            if ($order && $order->status !== Order::STATUS_PAID) {
                try {
                    $this->paymentService->syncFromGateway($order);
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
        } elseif (($paymentStatus === 'cancelled' || $paymentStatus === 'cancel') && (string) $orderCode) {
            $order = $this->orderService->findByGatewayCode((int) $orderCode);

            // Verify order belongs to current user if authenticated
            if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
                $order = null;
            }

            $isPaymentCancelled = true;
            $paymentData = [
                'orderCode' => $orderCode,
                'date'      => $order ? $order->created_at->format('d/m/Y') : now()->format('d/m/Y'),
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
