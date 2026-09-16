<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PayOS webhook payload shape before service processing.
 *
 * SECURITY NOTE: This validates only basic structure. Cryptographic signature
 * verification is delegated to PayOSGateway::verifyWebhook().
 */
class PaymentWebhookRequest extends FormRequest
{
    /**
     * Webhooks are authenticated by the PayOS gateway verifier, not user session.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Basic webhook validation.
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20'],
            'desc' => ['nullable', 'string', 'max:255'],
            'success' => ['nullable', 'boolean'],
            'signature' => ['required', 'string', 'max:512'],
            'data' => ['required', 'array'],
            'data.orderCode' => ['required_without:data.order_code', 'integer', 'min:1'],
            'data.order_code' => ['required_without:data.orderCode', 'integer', 'min:1'],
            'data.status' => ['nullable', 'string', 'max:50'],
            'data.amount' => ['nullable', 'integer', 'min:0'],
            'data.description' => ['nullable', 'string', 'max:255'],
            'data.reference' => ['nullable', 'string', 'max:255'],
            'data.transactionDateTime' => ['nullable', 'string', 'max:50'],
        ];
    }
}
