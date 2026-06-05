<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'integer|distinct|exists:seats,id',
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
