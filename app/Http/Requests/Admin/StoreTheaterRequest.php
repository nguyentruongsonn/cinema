<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTheaterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'exists:branches,id'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'base_price' => ['nullable', 'integer', 'min:0'],
            'weekend_surcharge' => ['nullable', 'integer', 'min:0'],
            'holiday_surcharge' => ['nullable', 'integer', 'min:0'],
            'happy_day_price' => ['nullable', 'integer', 'min:0'],
            'student_discount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên rạp chiếu không được để trống.',
            'branch_id.required' => 'Vui lòng chọn chi nhánh.',
            'branch_id.exists' => 'Chi nhánh đã chọn không hợp lệ.',
            'address.required' => 'Địa chỉ không được để trống.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
        ];
    }
}
