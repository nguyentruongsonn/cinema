<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockSeatsRequest extends FormRequest
{
    /**
     * Only authenticated users can unlock their own seat holds.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'hold_id' => ['required', 'integer', 'exists:seat_holds,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'hold_id.required' => __('validation.required', ['attribute' => 'seat hold']),
            'hold_id.integer' => __('validation.integer', ['attribute' => 'seat hold']),
            'hold_id.exists' => __('validation.exists', ['attribute' => 'seat hold']),
        ];
    }

    /**
     * Prepare route parameter for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'hold_id' => $this->route('holdId'),
        ]);
    }
}
