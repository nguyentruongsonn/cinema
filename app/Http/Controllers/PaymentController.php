<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Handles web-facing payment callbacks and webhooks from PayOS.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Show payment summary page (not used currently — redirect to booking).
     *
     * @param int|string $order Order ID (unused, kept for route compatibility)
     */
    public function index(int|string $order): RedirectResponse
    {
        return redirect()->route('home');
    }

    /**
     * PayOS return URL after successful payment.
     * Redirects back to the booking page with success status.
     */
    public function payosCallback(Request $request): RedirectResponse
    {
        $orderCode = $request->query('orderCode');
        $status    = $request->query('status');

        // Try to find the order's showtime_id for the redirect
        $showtimeId = null;
        if (is_string($orderCode) && $orderCode !== '') {
            $order = Order::query()
                ->where('gateway_order_code', '=', $orderCode)
                ->first();

            $showtimeId = $order?->showtime_id;
        }

        if ($showtimeId && ($status === 'PAID' || $request->query('code') === '00')) {
            return redirect()->route('booking.show', [
                'showtimeId'    => $showtimeId,
                'paymentStatus' => 'success',
                'orderCode'     => $orderCode,
            ], 302, []);
        }

        return redirect()->route('home');
    }

    /**
     * PayOS cancel URL when user cancels payment.
     * Redirects back to the booking page with cancelled status.
     */
    public function payosCancel(Request $request): RedirectResponse
    {
        $orderCode = $request->query('orderCode');

        $showtimeId = null;
        if (is_string($orderCode) && $orderCode !== '') {
            $order = Order::query()
                ->where('gateway_order_code', '=', $orderCode)
                ->first();

            $showtimeId = $order?->showtime_id;
        }

        if ($showtimeId) {
            return redirect()->route('booking.show', [
                'showtimeId'    => $showtimeId,
                'paymentStatus' => 'cancelled',
                'orderCode'     => $orderCode,
            ], 302, []);
        }

        return redirect()->route('home');
    }

    /**
     * PayOS webhook — called by PayOS server to confirm payment.
     */
    public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $result = $this->paymentService->handleWebhook($request->all());

            return response()->json([
                'success' => true,
                'message' => match (true) {
                    $result['already_processed'] ?? false => 'Already processed.',
                    $result['skipped']           ?? false => 'Skipped.',
                    default                               => 'OK',
                },
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Internal error.'], 500);
        }
    }
}
