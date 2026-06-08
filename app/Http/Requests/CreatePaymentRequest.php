<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // We check Auth::user() in controller
    }

    public function rules(): array
    {
        return [
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|string|in:seat,product',
            'items.*.id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'voucher_code' => 'nullable|string|max:50',
            'points_used' => 'nullable|integer|min:0',
        ];
    }
}
