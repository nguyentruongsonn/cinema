<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosCreateOrderRequest;
use App\Http\Requests\Pos\PosLookupCustomerRequest;
use App\Http\Requests\Pos\PosSyncSeatHoldRequest;
use App\Http\Resources\ComboResource;
use App\Http\Resources\ProductResource;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Theater;
use App\Services\PosCustomerService;
use App\Services\PosOrderService;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PosCustomerService $customerService,
        private readonly PosOrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * POS Kiosk main view (Blade).
     */
    public function index()
    {
        return view('pos.index');
    }

    /**
     * Get today's showtimes for staff's assigned theaters.
     */
    public function getShowtimes(Request $request): JsonResponse
    {
        $staff = Auth::user();
        $theaterIds = $staff->isAdmin()
            ? []
            : $staff->theaters()->pluck('theaters.id')->toArray();

        $date = $request->input('date');
        $queryDate = $date ? \Carbon\Carbon::parse($date) : today();

        $showtimes = Showtime::with([
            'movie:id,title,slug,duration,age_rating,poster_url,poster_path,surcharge',
            'screen:id,name,code,theater_id,capacity',
            'screen.theater:id,name',
            'format:id,name,surcharge',
        ])
        ->when(! $staff->isAdmin(), fn ($query) => $query->whereHas(
            'screen',
            fn ($q) => $q->whereIn('theater_id', $theaterIds)
        ))
        ->where('status', 1)
        ->when($queryDate->isToday(), fn ($q) => $q->where('scheduled_at', '>=', now()))
        ->whereDate('scheduled_at', $queryDate)
        ->orderBy('scheduled_at')
        ->get();

        return $this->successResponse($showtimes, 'Showtimes retrieved');
    }

    /**
     * Return the POS catalog without exposing the admin catalog endpoints.
     */
    public function catalog(): JsonResponse
    {
        $products = Product::query()
            ->where('status', 1)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->limit(100)
            ->get();

        $combos = Combo::query()
            ->where('status', 1)
            ->with('comboItems.product')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->filter(fn (Combo $combo): bool => $combo->available_stock > 0)
            ->values();

        return $this->successResponse([
            'products' => ProductResource::collection($products)->resolve(),
            'combos' => ComboResource::collection($combos)->resolve(),
        ], 'POS catalog retrieved');
    }

    /**
     * Return theaters where the current staff member can create a POS order.
     */
    public function theaters(): JsonResponse
    {
        $staff = Auth::user();

        $theaters = Theater::query()
            ->active()
            ->when(! $staff->isAdmin(), fn ($query) => $query->whereIn(
                'id',
                $staff->theaters()->pluck('theaters.id')
            ))
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->successResponse($theaters, 'POS theaters retrieved');
    }

    /**
     * Get seat map for a showtime.
     */
    public function getSeats(int $id, \App\Services\SeatService $seatService): JsonResponse
    {
        $staff = Auth::user();
        $theaterIds = $staff->isAdmin()
            ? []
            : $staff->theaters()->pluck('theaters.id')->toArray();

        $showtime = \App\Models\Showtime::with(['screen.theater'])->findOrFail($id);

        // Verify staff has access to this showtime's theater
        if (! $staff->isAdmin() && ! in_array($showtime->screen->theater_id, $theaterIds, true)) {
            return $this->errorResponse('Bạn không có quyền truy cập suất chiếu này.', 403);
        }

        $data = $seatService->getByShowtime($id, $staff);
        $data['screen_name'] = $showtime->screen->name;

        return $this->successResponse($data, 'Seats retrieved');
    }

    /**
     * Lookup or create customer by phone.
     */
    public function lookupCustomer(PosLookupCustomerRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $name  = $request->validated('name');

        $customer = $this->customerService->lookupByPhone($phone);

        if (!$customer && $name) {
            abort_unless(
                $request->user()->hasPermission('customers.create_walk_in'),
                403,
                'Bạn không có quyền tạo khách vãng lai.'
            );

            // Create new walk-in customer
            $customer = $this->customerService->createWalkInCustomer($phone, $name);
            $loyaltyInfo = $this->customerService->getLoyaltyInfo($customer);

            return $this->successResponse([
                'id'             => $customer->id,
                'name'           => $customer->name,
                'phone'          => $customer->phone,
                'loyalty_points' => $customer->loyalty_points,
                'loyalty_info'   => $loyaltyInfo,
                'account_status' => $customer->account_status ?? 'unclaimed',
                'is_new'         => true,
            ], 'Khách hàng mới được tạo', 201);
        }

        if (!$customer) {
            return $this->successResponse(null, 'Không tìm thấy khách hàng');
        }

        $loyaltyInfo = $this->customerService->getLoyaltyInfo($customer);

        return $this->successResponse([
            'id'             => $customer->id,
            'name'           => $customer->name,
            'phone'          => $customer->phone,
            'loyalty_points' => $customer->loyalty_points,
            'loyalty_info'   => $loyaltyInfo,
            'account_status' => $customer->account_status ?? 'claimed',
            'is_new'         => false,
        ], 'Khách hàng tìm thấy');
    }

    /**
     * Create POS order.
     */
    public function createOrder(PosCreateOrderRequest $request): JsonResponse
    {
        try {
            $staff = Auth::user();
            $validated = $request->validated();

            $showtime = ! empty($validated['showtime_id'])
                ? Showtime::with('screen')->findOrFail((int) $validated['showtime_id'])
                : null;
            $theaterId = (int) ($showtime?->screen->theater_id ?? $validated['theater_id'] ?? 0);

            // Find or create customer
            $customer = null;
            if (($validated['customer_mode'] ?? 'guest') === 'member') {
                if (! empty($validated['customer_id'])) {
                    $customer = $this->customerService->lookupById((int) $validated['customer_id']);
                }

                if (! $customer && ! empty($validated['customer_phone'])) {
                    $customer = $this->customerService->lookupByPhone($validated['customer_phone']);
                }

                if (!$customer && !empty($validated['customer_phone']) && !empty($validated['customer_name'])) {
                    $customer = $this->customerService->createWalkInCustomer(
                        $validated['customer_phone'],
                        $validated['customer_name']
                    );
                }
            }

            if (!$customer && ($validated['customer_mode'] ?? 'guest') === 'guest' && $theaterId > 0) {
                $customer = $this->customerService->resolveGuestCustomer($theaterId);
            }

            if (!$customer) {
                return $this->errorResponse(
                    'Vui lòng chọn khách hàng hoặc nhập số điện thoại và họ tên khách.',
                    422
                );
            }

            $validated['showtime_id'] = $showtime?->id;
            $validated['theater_id'] = $theaterId;
            $cashReceived = ($validated['cash_received'] ?? false)
                && ($validated['payment_method'] ?? null) === 'cash';

            if ($cashReceived && ! $staff->hasAnyPermission(['payments.process_cash', 'payments.process'])) {
                return $this->errorResponse('Bạn không có quyền xác nhận thanh toán tiền mặt.', 403);
            }

            $order = DB::transaction(function () use ($validated, $staff, $customer, $cashReceived): Order {
                $order = $this->orderService->createPosOrder($validated, $staff, $customer);

                if ($cashReceived && ! $order->isPaid()) {
                    $this->authorize('confirmCash', $order);
                    $order = $this->orderService->confirmCashPayment($order, $staff, $customer);
                }

                return $order;
            });

            $details = $this->orderService->getPosOrderDetails($order);
            $message = $cashReceived
                ? 'Thanh toán tiền mặt xác nhận thành công'
                : 'Đơn hàng POS tạo thành công';

            return $this->successResponse($details, $message, 201);
        } catch (\RuntimeException $e) {
            $status = in_array($e->getCode(), [403, 409, 422], true) ? $e->getCode() : 422;
            Log::warning('POS order creation failed', [
                'staff_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);
            return $this->errorResponse($e->getMessage(), $status);
        } catch (\Exception $e) {
            Log::error('POS order creation error', [
                'staff_id' => Auth::id(),
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Không thể tạo đơn hàng.', 500);
        }
    }

    public function syncSeatHold(PosSyncSeatHoldRequest $request, int $holdId): JsonResponse
    {
        try {
            $data = app(\App\Services\SeatService::class)->syncHold(
                $holdId,
                $request->validated('seat_ids', []),
                Auth::user(),
            );

            return $this->successResponse($data, 'Ghế đã được cập nhật.');
        } catch (\RuntimeException $e) {
            $status = in_array($e->getCode(), [403, 404, 409, 422], true) ? $e->getCode() : 422;
            Log::warning('POS seat hold sync failed', [
                'staff_id' => Auth::id(),
                'hold_id' => $holdId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Không thể cập nhật giữ ghế. Vui lòng tải lại sơ đồ ghế và thử lại.', $status);
        }
    }

    /**
     * Confirm cash payment for an order.
     */
    public function confirmCash(int $order): JsonResponse
    {
        try {
            $staff = Auth::user();
            $orderModel = Order::with(['showtime.screen'])->findOrFail($order);
            $this->authorize('confirmCash', $orderModel);

            // Find customer for loyalty points
            $customer = $orderModel->user;

            $confirmed = $this->orderService->confirmCashPayment($orderModel, $staff, $customer);
            $details = $this->orderService->getPosOrderDetails($confirmed);

            return $this->successResponse($details, 'Thanh toán tiền mặt xác nhận thành công');
        } catch (\Exception $e) {
            Log::error('POS cash confirmation failed', ['order_id' => $order, 'error' => $e->getMessage()]);
            return $this->errorResponse('Không thể xác nhận thanh toán.', 422);
        }
    }

    /**
     * Get POS order details.
     */
    public function getOrder(int $order): JsonResponse
    {
        $orderModel = Order::with(['showtime.screen'])->findOrFail($order);
        $this->authorize('viewAtPos', $orderModel);
        $details = $this->orderService->getPosOrderDetails($orderModel);

        return $this->successResponse($details, 'Order details retrieved');
    }

    /**
     * Cancel a pending POS order.
     */
    public function cancelOrder(int $order): JsonResponse
    {
        $staff = Auth::user();
        try {
            $orderModel = Order::with(['showtime.screen'])->findOrFail($order);
            $this->authorize('cancelAtPos', $orderModel);

            $cancelledOrder = app(\App\Services\OrderService::class)->cancel($orderModel->id, $staff);
            return $this->successResponse($cancelledOrder, 'Đơn hàng đã được hủy thành công.');
        } catch (\Exception $e) {
            Log::warning('POS order cancellation failed', [
                'staff_id' => Auth::id(),
                'order_id' => $order,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Không thể hủy đơn hàng. Vui lòng thử lại.', 422);
        }
    }

    /**
     * Get the payment status of an order.
     */
    public function checkPaymentStatus(int $order): JsonResponse
    {
        try {
            $orderModel = Order::with(['showtime.screen'])->findOrFail($order);
            $this->authorize('viewAtPos', $orderModel);
            
            if ($orderModel->status === Order::STATUS_CONFIRMED || $orderModel->payment_status === 'paid') {
                return $this->successResponse(['paid' => true, 'order' => $orderModel]);
            }
            
            if (in_array(($orderModel->payload['payment_method'] ?? null), ['payos', 'payos_qr', 'qr_online'])) {
                $this->paymentService->syncFromGateway($orderModel);
                $orderModel->refresh();
            }
            
            $isPaid = $orderModel->status === Order::STATUS_CONFIRMED || $orderModel->payment_status === 'paid';
            
            return $this->successResponse(['paid' => $isPaid, 'order' => $orderModel]);
        } catch (\Exception $e) {
            return $this->errorResponse('Lỗi kiểm tra trạng thái thanh toán.', 500);
        }
    }
}
