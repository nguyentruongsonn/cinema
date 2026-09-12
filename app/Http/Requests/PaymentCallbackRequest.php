<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orderCode' => ['nullable', 'string', 'max:50'],
            'order_code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function getOrderCode(): ?string
    {
        return $this->validated('orderCode') 
            ?? $this->validated('order_code');
    }

    public function getStatus(): ?string
    {
        return $this->validated('status');
    }

    public function getCode(): ?string
    {
        return $this->validated('code');
    }
}