<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Services\PaymentService;
use App\Services\PayOS\PayOSService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PayOSService $payOSService
    ) {
    }

    /**
     * Display payment page for an order.
     */
    public function index($orderId)
    {
        try {
            $user = Auth::user();
            
            // Get order with all relationships
            $order = $user->orders()
                ->with([
                    'showtime.movie',
                    'showtime.screen.theater',
                    'showtime.format',
                    'showtime.sound',
                    'showtime.subtitle',
                    'items.seat.seatType',
                    'payment'
                ])
                ->findOrFail($orderId);

            // Check if order is expired
            if ($order->status === 'expired') {
                return redirect('/')->with('error', 'Đơn hàng đã hết hạn');
            }

            // Check if already paid
            if ($order->status === 'confirmed' && $order->payment) {
                return redirect('/orders/' . $order->id)->with('info', 'Đơn hàng đã được thanh toán');
            }

            return view('users.payment.index', compact('order'));
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Không tìm thấy đơn hàng');
        }
    }

    /**
     * Create a new payment for an order.
     */
    public function store(StorePaymentRequest $request)
    {
        try {
            $user = Auth::user();
            $payment = $this->paymentService->create($request->validated(), $user);

            return $this->successResponse(
                $this->paymentService->format($payment),
                'Payment created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $payment = $this->paymentService->findForUser((int) $id, $user);

            return $this->successResponse(
                $this->paymentService->format($payment),
                'Payment retrieved successfully'
            );
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode() === 403 ? 403 : 404;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Payment not found', 404);
        }
    }

    /**
     * Verify payment status.
     */
    public function verify(VerifyPaymentRequest $request, $id)
    {
        try {
            $user = Auth::user();
            $payment = $this->paymentService->verify((int) $id, $request->validated(), $user);

            return $this->successResponse(
                $this->paymentService->format($payment),
                'Payment verified successfully'
            );
        } catch (\RuntimeException $e) {
            $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to verify payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle PayOS payment callback (return URL).
     */
    public function payosCallback(Request $request)
    {
        try {
            $webhookData = $request->all();
            
            $result = $this->payOSService->verifyWebhook($webhookData);
            
            $orderCode = $result['order_code'];
            $status = $result['status'];
            
            if ($status === 'completed') {
                return redirect('/orders/' . $orderCode)
                    ->with('success', 'Thanh toán thành công!');
            } else {
                return redirect('/payment/' . $orderCode)
                    ->with('error', 'Thanh toán thất bại. Vui lòng thử lại.');
            }
        } catch (\Exception $e) {
            Log::error('PayOS callback error', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return redirect('/')
                ->with('error', 'Có lỗi xảy ra trong quá trình xác nhận thanh toán.');
        }
    }

    /**
     * Handle PayOS payment cancellation.
     */
    public function payosCancel(Request $request)
    {
        $orderCode = $request->query('orderCode');
        
        return redirect('/payment/' . $orderCode)
            ->with('info', 'Bạn đã hủy thanh toán.');
    }

    /**
     * Handle PayOS webhook notification.
     */
    public function payosWebhook(Request $request)
    {
        try {
            $webhookData = $request->all();
            
            Log::info('PayOS webhook received', ['data' => $webhookData]);
            
            $result = $this->payOSService->verifyWebhook($webhookData);
            
            $orderCode = $result['order_code'];
            $status = $result['status'];
            
            // Find payment by order ID
            $payment = \App\Models\Payment::where('order_id', $orderCode)->firstOrFail();
            
            // Use system user for webhook verification
            $systemUser = new class {
                public $id = 0;
                public function roles() {
                    return new class {
                        public function whereIn() {
                            return new class {
                                public function exists() {
                                    return true;
                                }
                            };
                        }
                    };
                }
            };
            
            $this->paymentService->verify($payment->id, ['status' => $status], $systemUser);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('PayOS webhook error', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
