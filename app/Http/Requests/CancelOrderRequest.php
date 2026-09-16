<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to cancel this order.
     *
     * Uses OrderPolicy::cancel() to enforce:
     * - User ownership (or admin/staff permission)
     * - Order in cancellable status (pending, not paid)
     */
    public function authorize(): bool
    {
        $orderId = $this->route('id');

        if (!$orderId) {
            return false;
        }

        $order = Order::query()->find($orderId);

        if (!$order) {
            return false;
        }

        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('cancel', $order);
    }

    /**
     * Validation rules for order cancellation.
     *
     * PHASE 2 FIX: Added explicit validation contract.
     * - reason: optional but validated if provided
     * - confirmation: ensures deliberate action
     *
     * NOTE: Idempotency-Key header recommended but not enforced here
     * because OrderService::cancel() already has idempotency check
     * (returns early if order already cancelled). For stricter
     * idempotency, consider requiring header in future iteration.
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'confirmation' => ['sometimes', 'accepted'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'reason.string' => 'Lý do hủy phải là chuỗi ký tự.',
            'reason.max' => 'Lý do hủy không được vượt quá 500 ký tự.',
            'confirmation.accepted' => 'Vui lòng xác nhận hủy đơn hàng.',
        ];
    }
}
