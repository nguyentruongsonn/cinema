<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:sounds,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên định dạng âm thanh không được để trống.',
            'name.unique' => 'Tên định dạng âm thanh đã tồn tại.',
        ];
    }
}
