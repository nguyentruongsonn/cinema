<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosSyncSeatHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('booking.hold_seats')
            && $user->hasPermission('booking.release_seats');
    }

    public function rules(): array
    {
        return [
            'seat_ids' => ['present', 'array', 'max:20'],
            'seat_ids.*' => ['integer', 'distinct', 'exists:seats,id'],
        ];
    }
}
