<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Order creation is a money/booking flow and must require an authenticated actor.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('promotion_code')) {
            $this->merge([
                'promotion_code' => strtoupper(trim((string) $this->input('promotion_code'))),
            ]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;
        $showtimeId = $this->input('showtime_id');

        return [
            'idempotency_key' => ['required', 'string', 'uuid', 'max:36'],

            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],

            'seat_ids' => ['required', 'array', 'min:1', 'max:10'],
            'seat_ids.*' => ['required', 'integer', 'distinct', 'exists:seats,id'],

            'seat_hold_id' => [
                'required',
                'integer',
                Rule::exists('seat_holds', 'id')
                    ->where(function ($query) use ($showtimeId, $userId): void {
                        $query->where('showtime_id', $showtimeId)
                            ->where('user_id', $userId)
                            ->where('held_until', '>', now());
                    }),
            ],

            'products' => ['nullable', 'array', 'max:20'],
            'products.*.id' => [
                'required_with:products',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where(function ($query): void {
                        $query->where('status', 1)
                            ->whereNull('deleted_at')
                            ->where('stock', '>', 0);
                    }),
            ],
            'products.*.quantity' => ['required_with:products', 'integer', 'min:1', 'max:20'],

            'promotion_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'An idempotency key is required to create an order.',
            'idempotency_key.uuid' => 'The idempotency key must be a valid UUID.',
            'seat_ids.required' => 'Please select at least one seat.',
            'seat_ids.max' => 'You can select up to 10 seats per booking.',
            'seat_ids.*.distinct' => 'Duplicate seat selections are not allowed.',
            'seat_hold_id.required' => 'A valid active seat hold is required to create an order.',
            'seat_hold_id.exists' => 'The selected seat hold is invalid, expired, or does not belong to this user/showtime.',
            'products.max' => 'You can add up to 20 products per order.',
            'products.*.id.exists' => 'One or more selected products are unavailable.',
            'promotion_code.regex' => 'Promotion codes may only contain letters, numbers, underscores, and hyphens.',
        ];
    }
}
