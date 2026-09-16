<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the authenticated user may initiate a payment/order flow.
     *
     * Authorization must not be deferred to controller comments because
     * FormRequest authorization is the first protection layer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|string|uuid|max:36',
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'items' => 'required|array|min:1|max:50',
            'items.*.type' => 'required|string|in:seat,product,combo',
            'items.*.id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:20',
            'voucher_code' => 'nullable|string|max:50',
            'points_used' => 'nullable|integer|min:0|max:100000',
        ];
    }
}
