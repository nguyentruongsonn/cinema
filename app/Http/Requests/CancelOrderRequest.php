<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelOrderRequest extends FormRequest
{
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

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'confirmation' => ['sometimes', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.string' => 'Lý do hủy phải là chuỗi ký tự.',
            'reason.max' => 'Lý do hủy không được vượt quá 500 ký tự.',
            'confirmation.accepted' => 'Vui lòng xác nhận hủy đơn hàng.',
        ];
    }
}
