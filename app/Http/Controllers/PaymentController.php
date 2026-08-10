<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaymentCallbackRequest;
use App\Http\Requests\PaymentWebhookRequest;
use App\Models\Order;
use App\Exceptions\PaymentGatewayException;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
     * Show payment summary page (kept for route compatibility).
     *
     * @param int|string $order Order ID (unused)
     */
    public function index(int|string $order): RedirectResponse
    {
        return redirect()
            ->route('home')
            ->with('warning', 'Trang thanh toán không còn khả dụng. Vui lòng kiểm tra trạng thái đơn hàng.');
    }

    /**
     * PayOS return URL after payment.
     *
     * SECURITY: Browser return URLs are user-controlled. This method must never
     * mark an order paid based on query parameters. For authenticated owners only,
     * it may ask the gateway for a trusted status sync; verified webhooks remain
     * the primary fulfillment path.
     */
    public function payosCallback(PaymentCallbackRequest $request): RedirectResponse
    {
        $orderCode = $request->getOrderCode();

        Log::info('PayOS callback received', [
            'order_code_present' => filled($orderCode),
            'status' => $request->getStatus(),
            'code' => $request->getCode(),
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $order = $this->resolveOwnedOrderForReturn($orderCode, 'callback');

        if (!$order) {
            return redirect()
                ->route('home')
                ->with('warning', 'Không thể xác minh đơn hàng thanh toán. Vui lòng đăng nhập và kiểm tra lại đơn hàng.');
        }

        try {
            $this->paymentService->syncFromGateway($order);
            $order->refresh();
        } catch (Throwable $e) {
            report($e);

            Log::warning('PayOS callback gateway sync failed', [
                'order_id' => $order->id,
                'gateway_order_code' => $order->gateway_order_code,
                'user_id' => Auth::id(),
            ]);
        }

        if (str_starts_with($order->code, 'POS-')) {
            return redirect()->route('pos.index', [
                'paymentStatus' => $order->payment_status === 'paid' ? 'success' : 'pending',
                'orderId' => $order->id,
            ]);
        }

        $encryptedShowtimeId = $order->showtime?->encrypted_id;

        if (!$encryptedShowtimeId) {
            return redirect()
                ->route('home')
                ->with('warning', 'Không tìm thấy lịch chiếu của đơn hàng. Vui lòng kiểm tra lại đơn hàng.');
        }

        return redirect()->route('booking.show', [
            'encryptedShowtimeId' => $encryptedShowtimeId,
            'paymentStatus' => $order->payment_status === 'paid' ? 'success' : 'pending',
            'orderCode' => $order->gateway_order_code,
        ], 302, []);
    }

    /**
     * PayOS cancel URL when user cancels payment.
     */
    public function payosCancel(PaymentCallbackRequest $request): RedirectResponse
    {
        $orderCode = $request->getOrderCode();

        Log::info('PayOS cancel callback received', [
            'order_code_present' => filled($orderCode),
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $order = $this->resolveOwnedOrderForReturn($orderCode, 'cancel');

        if (!$order) {
            return redirect()
                ->route('home')
                ->with('warning', 'Không thể xác minh đơn hàng bị hủy. Vui lòng đăng nhập và kiểm tra lại đơn hàng.');
        }

        $this->paymentService->markCancelledFromReturn($order);
        $order->refresh();

        if (str_starts_with($order->code, 'POS-')) {
            return redirect()->route('pos.index', [
                'paymentStatus' => 'cancel',
                'orderId' => $order->id,
            ]);
        }

        $encryptedShowtimeId = $order->showtime?->encrypted_id;

        if (!$encryptedShowtimeId) {
            return redirect()
                ->route('home')
                ->with('warning', 'Không tìm thấy lịch chiếu của đơn hàng. Vui lòng kiểm tra lại đơn hàng.');
        }

        return redirect()->route('booking.show', [
            'encryptedShowtimeId' => $encryptedShowtimeId,
            'paymentStatus' => 'cancelled',
            'orderCode' => $order->gateway_order_code,
        ], 302, []);
    }

    /**
     * PayOS webhook — called by PayOS server to confirm payment.
     *
     * Signature verification must be enforced by route middleware and the gateway
     * verifier inside PaymentService::handleWebhook().
     */
    public function payosWebhook(PaymentWebhookRequest $request): JsonResponse
    {
        Log::info('PayOS webhook received', [
            'order_code' => data_get($request->validated(), 'data.orderCode') ?? data_get($request->validated(), 'data.order_code'),
            'code' => $request->validated('code'),
            'success' => $request->validated('success'),
            'ip' => $request->ip(),
        ]);

        try {
            $result = $this->paymentService->handleWebhook($request->validated());

            Log::info('PayOS webhook processed', [
                'already_processed' => $result['already_processed'] ?? false,
                'skipped' => $result['skipped'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => match (true) {
                    $result['already_processed'] ?? false => 'Order already processed',
                    $result['skipped'] ?? false => 'Webhook processed without successful payment',
                    default => 'Payment processed successfully',
                },
            ]);
        } catch (PaymentGatewayException $e) {
            Log::warning('PayOS webhook ignored after gateway verification failure', [
                'order_code' => data_get($request->validated(), 'data.orderCode') ?? data_get($request->validated(), 'data.order_code'),
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook accepted but not processed',
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('PayOS webhook processing failed', [
                'order_code' => data_get($request->validated(), 'data.orderCode') ?? data_get($request->validated(), 'data.order_code'),
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
            ], 500);
        }
    }

    /**
     * Resolve an order from payment return parameters only for authenticated owners.
     */
    private function resolveOwnedOrderForReturn(?string $orderCode, string $action): ?Order
    {
        if (!Auth::check()) {
            Log::warning('Payment return rejected for guest user', [
                'action' => $action,
                'order_code_present' => filled($orderCode),
            ]);

            return null;
        }

        if (!$this->isValidGatewayOrderCode($orderCode)) {
            Log::warning('Payment return rejected due to invalid order code', [
                'action' => $action,
                'user_id' => Auth::id(),
            ]);

            return null;
        }

        $order = $this->orderService->findByGatewayCode((int) $orderCode);

        if (!$order) {
            Log::warning('Payment return order not found', [
                'action' => $action,
                'user_id' => Auth::id(),
            ]);

            return null;
        }

        $user = Auth::user();

        if (! $user || ! $user->can('view', $order)) {
            Log::warning('Payment return ownership check failed', [
                'action' => $action,
                'order_id' => $order->id,
                'order_user_id' => $order->user_id,
                'actor_user_id' => Auth::id(),
            ]);

            return null;
        }

        return $order;
    }

    /**
     * PayOS gateway order codes are numeric and generated server-side.
     */
    private function isValidGatewayOrderCode(?string $orderCode): bool
    {
        return is_string($orderCode)
            && preg_match('/^[1-9][0-9]{5,30}$/', $orderCode) === 1;
    }
}
