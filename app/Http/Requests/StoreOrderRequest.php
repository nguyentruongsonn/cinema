<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'showtime_id' => 'required|exists:showtimes,id',
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'integer|distinct|exists:seats,id',
            'seat_hold_id' => 'nullable|integer|exists:seat_holds,id',
            'products' => 'nullable|array',
            'products.*.id' => 'required_with:products|integer|distinct|exists:products,id',
            'products.*.quantity' => 'required_with:products|integer|min:1|max:20',
            'promotion_code' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
