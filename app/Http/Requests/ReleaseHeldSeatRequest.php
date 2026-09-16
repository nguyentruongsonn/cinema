<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseHeldSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'hold_id' => ['required', 'integer', 'min:1'],
            'seat_id' => ['required', 'integer', 'min:1', 'exists:seats,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'hold_id' => $this->route('holdId'),
            'seat_id' => $this->route('seatId'),
        ]);
    }
}
