<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PayOS payment callback/cancel return URL parameters.
 * 
 * SECURITY NOTE: Return URLs are user-controlled and can be manipulated.
 * This request only validates format - never trust return URLs for payment
 * confirmation. Always verify via gateway API or signed webhooks.
 */
class PaymentCallbackRequest extends FormRequest
{
    /**
     * Callback routes are public (user returning from payment gateway).
     * Authorization is handled in controller based on order ownership.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for payment callback parameters.
     */
    public function rules(): array
    {
        return [
            'orderCode' => ['nullable', 'string', 'max:50'],
            'order_code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get the validated order code (handles both formats).
     */
    public function getOrderCode(): ?string
    {
        return $this->validated('orderCode') 
            ?? $this->validated('order_code');
    }

    /**
     * Get the validated status.
     */
    public function getStatus(): ?string
    {
        return $this->validated('status');
    }

    /**
     * Get the validated code.
     */
    public function getCode(): ?string
    {
        return $this->validated('code');
    }
}