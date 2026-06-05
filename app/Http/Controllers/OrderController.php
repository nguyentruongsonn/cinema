<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService
    ) {
    }

    /**
     * Create a new order.
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            $user = Auth::user();
            $order = $this->orderService->create($request->validated(), $user);

            return $this->successResponse(
                $this->orderService->format($order),
                'Order created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
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
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }

    /**
     * List user orders.
     */
    public function userOrders(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = (int) $request->input('per_page', 15);

            $orders = $this->orderService->getUserOrders($user, $perPage);

            return $this->successResponse([
                'data' => $orders->items(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ], 'Orders retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
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
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to cancel order: ' . $e->getMessage(), 500);
        }
    }
}
