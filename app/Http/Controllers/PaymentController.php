<?php

namespace App\Http\Controllers;

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

        if (!is_string($orderCode) || $orderCode === '') {
            $orderCode = $request->query('orderCode') ?? $request->query('order_code');
        }

        // Try to find the order's showtime encrypted id for the redirect.
        $encryptedShowtimeId = null;
        if (is_string($orderCode) && $orderCode !== '') {
            $order = $this->orderService->findByGatewayCode((int) $orderCode);

            // If user is authenticated, ensure order belongs to them (defense in depth)
            // Allow guest access in case session expired during payment
            if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
                $order = null;
            }

            if ($order) {
                // PayOS return URL có thể về trước webhook/queue worker.
                // Nếu return params báo thành công thì finalize ngay để cập nhật đủ:
                // orders, payments, order_items, tickets, promotion used_count,
                // điểm người dùng, seat_holds.
                $isSuccessfulReturn = $status === 'PAID'
                    || $status === 'success'
                    || $request->query('code') === '00';

                if ($isSuccessfulReturn) {
                    $this->paymentService->markPaidFromReturn($order);
                } else {
                    // Fallback: đồng bộ trực tiếp từ PayOS để tránh đơn kẹt pending.
                    $this->paymentService->syncFromGateway($order);
                }

                $order->refresh();
            }

            $encryptedShowtimeId = $order?->showtime?->encrypted_id;
        }

        if ($encryptedShowtimeId && (
            $status === 'PAID'
            || $status === 'success'
            || $request->query('code') === '00'
            || (isset($order) && $order?->payment_status === 'paid')
        )) {
            return redirect()->route('booking.show', [
                'encryptedShowtimeId' => $encryptedShowtimeId,
                'paymentStatus'       => 'success',
                'orderCode'           => $orderCode,
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

        $encryptedShowtimeId = null;
        if (is_string($orderCode) && $orderCode !== '') {
            $order = $this->orderService->findByGatewayCode((int) $orderCode);

            // If user is authenticated, ensure order belongs to them (defense in depth)
            // Allow guest access in case session expired during payment
            if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
                $order = null;
            }

            if ($order) {
                $this->paymentService->markCancelledFromReturn($order);
                $order->refresh();
            }

            $encryptedShowtimeId = $order?->showtime?->encrypted_id;
        }

        if ($encryptedShowtimeId) {
            return redirect()->route('booking.show', [
                'encryptedShowtimeId' => $encryptedShowtimeId,
                'paymentStatus'       => 'cancelled',
                'orderCode'           => $orderCode,
            ], 302, []);
        }

        return redirect()->route('home');
    }

    /**
     * PayOS webhook — called by PayOS server to confirm payment.
     * Xử lý đồng bộ để đơn hàng không bị kẹt pending khi queue worker chưa chạy.
     */
    public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $result = $this->paymentService->handleWebhook($request->all());

            return response()->json([
                'success' => true,
                'message' => match (true) {
                    $result['already_processed'] ?? false => 'Order already processed',
                    $result['skipped'] ?? false => 'Webhook processed without successful payment',
                    default => 'Payment processed successfully',
                },
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
            ], 500);
        }
    }
}
