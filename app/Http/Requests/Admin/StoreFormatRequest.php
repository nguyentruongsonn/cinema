<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:formats,name'],
            'surcharge' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên định dạng phòng chiếu không được để trống.',
            'name.unique' => 'Tên định dạng phòng chiếu đã tồn tại.',
            'surcharge.required' => 'Vui lòng nhập phụ thu.',
            'surcharge.numeric' => 'Phụ thu phải là một số.',
            'surcharge.min' => 'Phụ thu không được âm.',
        ];
    }
}
