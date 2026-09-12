<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:payos,credit_card,debit_card,bank_transfer,e_wallet,vnpay,momo',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
