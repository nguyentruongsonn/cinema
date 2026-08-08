<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use App\Models\Order;
use App\Models\IdempotencyKey;
use App\Http\Resources\OrderResource;
use App\Http\Resources\AdminOrderSummaryResource;
use App\Services\OrderExpirationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderExpirationService $orderExpirationService
    ) {
    }

    /**
     * Create a new order.
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            $user = Auth::user();
            $validated = $request->validated();
            $result = IdempotencyKey::executeIdempotent(
                $validated['idempotency_key'],
                function () use ($validated, $user): array {
                    $order = $this->orderService->create($validated, $user);

                    return [
                        'status' => 201,
                        'data' => $this->orderService->format($order),
                    ];
                },
                [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'data' => $validated,
                ]
            );

            return $this->successResponse(
                $result['data'],
                'Order created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 409, 422], true) ? $e->getCode() : 422;

            Log::warning('Order creation rejected', [
                'user_id' => Auth::id(),
                'status_code' => $statusCode,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                $statusCode === 403 ? 'You are not authorized to create this order.' : 'Order could not be created from the provided data.',
                $statusCode
            );
        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to create order.', 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $order = $this->orderService->findForUser((int) $id, $user);

            return $this->successResponse(
                $this->orderService->format($order),
                'Order retrieved successfully'
            );
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode() === 403 ? 403 : 404;

            Log::notice('Order lookup rejected', [
                'user_id' => Auth::id(),
                'order_id' => (int) $id,
                'status_code' => $statusCode,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                $statusCode === 403 ? 'You are not authorized to view this order.' : 'Order not found.',
                $statusCode
            );
        } catch (\Exception $e) {
            Log::error('Order lookup failed', [
                'user_id' => Auth::id(),
                'order_id' => (int) $id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse('Order not found.', 404);
        }
    }

    /**
     * List user orders.
     */
    public function userOrders(Request $request)
    {
        try {
            $user = Auth::user();
            $validated = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);
            $perPage = (int) ($validated['per_page'] ?? 15);

            $orders = $this->orderService->getUserOrders($user, $perPage);

            return $this->successResponse([
                'data' => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ], 'Orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error('User order list retrieval failed', [
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to retrieve orders.', 500);
        }
    }

    public function adminOrders(Request $request)
    {
        $this->orderExpirationService->expirePendingOrders();

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'string', 'in:all,pending,paid,confirmed,cancelled,expired,failed'],
            'branch_id' => ['nullable', 'integer'],
            'theater_id' => ['nullable', 'integer'],
            'movie_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Order::query()
            ->select([
                'id',
                'code',
                'user_id',
                'showtime_id',
                'total_amount',
                'status',
                'payment_status',
                'created_at',
            ])
            ->with([
                'user:id,name,email,phone',
                'showtime:id,movie_id,screen_id,scheduled_at',
                'showtime.movie:id,title,poster_url,poster_path,duration,age_rating',
                'showtime.screen:id,name,theater_id',
                'showtime.screen.theater:id,name',
                'orderItems:id,order_id,item_type,metadata',
            ])
            ->when(($validated['status'] ?? 'all') !== 'all', function ($query) use ($validated) {
                $status = $validated['status'];
                $paymentStatus = in_array($status, ['paid', 'confirmed'], true) ? 'paid' : $status;

                $query->where('payment_status', $paymentStatus);
            })
            ->when($validated['branch_id'] ?? null, fn ($q, $id) => $q->whereHas('showtime.screen.theater', fn ($theater) => $theater->where('branch_id', $id)))
            ->when($validated['theater_id'] ?? null, fn ($q, $id) => $q->whereHas('showtime.screen', fn ($screen) => $screen->where('theater_id', $id)))
            ->when($validated['movie_id'] ?? null, fn ($q, $id) => $q->whereHas('showtime', fn ($showtime) => $showtime->where('movie_id', $id)))
            ->when($validated['date'] ?? null, fn ($q, $date) => $q->whereBetween('created_at', [
                Carbon::createFromFormat('Y-m-d', $date)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $date)->endOfDay(),
            ]))
            ->when($validated['date_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $date)->startOfDay()))
            ->when($validated['date_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $date)->endOfDay()))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at');

        // Theater scope: non-admin staff only see orders from their theaters
        $actor = Auth::user();
        if ($actor && $actor->requiresTheaterScope()) {
            $actorTheaterIds = $actor->theaters()->pluck('theaters.id');
            $query->whereHas('showtime.screen', fn ($q) => $q->whereIn('theater_id', $actorTheaterIds));
        }

        $orders = $query->paginate($validated['per_page'] ?? 15);

        return $this->successResponse([
            'data' => AdminOrderSummaryResource::collection($orders->items())->resolve(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 'Admin orders retrieved successfully');
    }

    public function adminOrder(int $id)
    {
        $order = Order::query()
            ->with(['user', 'showtime.movie', 'showtime.screen.theater.branch', 'orderItems', 'tickets.seat.seatType', 'payment'])
            ->findOrFail($id);

        $order = $this->orderExpirationService->expireOrder($order)->load([
            'user',
            'showtime.movie',
            'showtime.screen.theater.branch',
            'orderItems',
            'tickets.seat.seatType',
            'payment',
        ]);

        return $this->successResponse((new OrderResource($order))->resolve(), 'Admin order retrieved successfully');
    }

    /**
     * Cancel an order.
     */
    public function cancel(CancelOrderRequest $request, $id)
    {
        try {
            $user = Auth::user();
            $order = $this->orderService->cancel((int) $id, $user);

            return $this->successResponse(
                $this->orderService->format($order),
                'Order cancelled successfully'
            );
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;

            Log::warning('Order cancellation rejected', [
                'user_id' => Auth::id(),
                'order_id' => (int) $id,
                'status_code' => $statusCode,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                $statusCode === 403 ? 'You are not authorized to cancel this order.' : 'Order cannot be cancelled.',
                $statusCode
            );
        } catch (\Exception $e) {
            Log::error('Order cancellation failed', [
                'user_id' => Auth::id(),
                'order_id' => (int) $id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to cancel order.', 500);
        }
    }
}
