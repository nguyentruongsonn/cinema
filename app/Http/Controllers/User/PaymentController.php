<?php

namespace App\Http\Controllers\User;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Order;
use App\Models\Showtime;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService
    ) {}

    public function createPayment(CreatePaymentRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->unauthorized();
        }

        $showtime = Showtime::query()
            ->with('screen')
            ->findOrFail($request->validated('showtime_id'));

        try {
            $validated = $request->validated();

            $result = $this->paymentService->initiate(
                $user,
                $showtime,
                $validated,
                url(''),
                $validated['idempotency_key'] ?? throw new \InvalidArgumentException('Idempotency key is required'),
            );

            return $this->ok([
                'checkout_url' => $result['checkout_url'],
                'gateway_order_code' => $result['gateway_order_code'],
                'order_number' => $result['order_number'],
            ], 'Tạo đơn hàng thành công.');
        } catch (PaymentGatewayException $e) {
            report($e);

            return $this->error('Cổng thanh toán tạm thời không khả dụng.', 502);
        } catch (Throwable $e) {
            report($e);

            return $this->error('Đã xảy ra lỗi khi xử lý thanh toán.', 500);
        }
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->paymentService->handleWebhook($request->all());

            return $this->ok([], match (true) {
                $result['already_processed'] ?? false => 'Đơn hàng đã được xử lý trước đó.',
                $result['skipped'] ?? false => 'Bỏ qua webhook không phải thanh toán thành công.',
                default => 'Thanh toán thành công.',
            });
        } catch (\InvalidArgumentException $e) {
            report($e);

            return $this->error('Dữ liệu webhook không hợp lệ.', 400);
        } catch (Throwable $e) {
            report($e);

            return $this->error('Lỗi xử lý webhook.', 500);
        }
    }

    public function showOrderSummary(Request $request, int $orderCode): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->unauthorized();
        }

        $order = $this->orderService->findByGatewayCode($orderCode);

        if (! $order || $order->user_id !== $user->id) {
            return $this->notFound('Không tìm thấy đơn hàng yêu cầu.');
        }

        if (! $order->isPaid()) {
            $this->paymentService->syncFromGateway($order);
        }

        $order->refresh()->load([
            'showtime.movie',
            'showtime.screen',
            'orderItems',
        ]);

        return $this->ok(new OrderSummaryResource($order));
    }
}
