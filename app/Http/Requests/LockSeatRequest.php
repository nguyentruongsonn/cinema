<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockSeatRequest extends FormRequest
{
    /**
     * Only authenticated users can lock seats.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],
            'seat_ids' => ['required', 'array', 'min:1', 'max:10'], // Reasonable booking limit
            'seat_ids.*' => ['required', 'integer', 'distinct', 'exists:seats,id'],
            // Service layer will validate seat-showtime-screen relationship atomically with locks
        ];
    }

    public function messages(): array
    {
        return [
            'seat_ids.required' => 'Please select at least one seat.',
            'seat_ids.max' => 'You can select up to 10 seats per booking.',
            'seat_ids.*.distinct' => 'Duplicate seat selections are not allowed.',
        ];
    }
}
