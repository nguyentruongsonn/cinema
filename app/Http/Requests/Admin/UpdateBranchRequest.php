<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('branches')->ignore($this->route('branch'))
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên chi nhánh không được để trống',
            'code.required' => 'Mã chi nhánh không được để trống',
            'code.unique' => 'Mã chi nhánh đã tồn tại trong hệ thống',
        ];
    }
}
