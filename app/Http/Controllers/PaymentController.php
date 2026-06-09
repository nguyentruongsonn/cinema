<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayOSWebhook;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Handles web-facing payment callbacks and webhooks from PayOS.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService
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
            $order = $this->orderService->findByGatewayCode((int) $orderCode);

            // If user is authenticated, ensure order belongs to them (defense in depth)
            // Allow guest access in case session expired during payment
            if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
                $order = null;
            }

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
            $order = $this->orderService->findByGatewayCode((int) $orderCode);

            // If user is authenticated, ensure order belongs to them (defense in depth)
            // Allow guest access in case session expired during payment
            if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
                $order = null;
            }

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
     * Dispatches async job for processing to return immediately.
     */
    public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Dispatch webhook processing to queue (async)
            ProcessPayOSWebhook::dispatch($request->all());

            // Return 200 OK immediately to acknowledge webhook receipt
            return response()->json([
                'success' => true,
                'message' => 'Webhook received and queued for processing',
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to queue webhook'], 500);
        }
    }
}
