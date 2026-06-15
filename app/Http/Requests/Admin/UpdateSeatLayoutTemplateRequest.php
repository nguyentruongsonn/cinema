<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeatLayoutTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'seat_matrix' => ['nullable', 'string'],
            'regular_seat_rows' => ['nullable', 'integer', 'min:0'],
            'vip_seat_rows' => ['nullable', 'integer', 'min:0'],
            'couple_seat_rows' => ['nullable', 'integer', 'min:0'],
            'custom_matrix' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_name.required' => 'Tên mẫu sơ đồ ghế không được để trống.',
            'regular_seat_rows.integer' => 'Số hàng ghế thường phải là số nguyên.',
            'vip_seat_rows.integer' => 'Số hàng ghế VIP phải là số nguyên.',
            'couple_seat_rows.integer' => 'Số hàng ghế đôi phải là số nguyên.',
        ];
    }
}
