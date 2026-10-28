<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosLookupCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('customers.lookup');
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:9', 'max:15', 'regex:/^[0-9+\-\s()]+$/'],
            'name'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex'    => 'Số điện thoại không đúng định dạng.',
        ];
    }
}
