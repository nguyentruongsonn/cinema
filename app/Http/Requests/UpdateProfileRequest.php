<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $normalized['name'] = trim($this->input('name'));
        }

        if ($this->has('phone') && is_string($this->input('phone'))) {
            $normalized['phone'] = trim($this->input('phone'));
        }

        if ($this->has('avatar_url') && is_string($this->input('avatar_url'))) {
            $normalized['avatar_url'] = trim($this->input('avatar_url'));
        }

        if ($this->has('address') && is_string($this->input('address'))) {
            $normalized['address'] = trim($this->input('address'));
        }

        if (!empty($normalized)) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],

            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+84|0)[0-9]{9,10}$/', 'max:20'],

            'birthday' => ['sometimes', 'nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],

            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],

            'avatar_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],

            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Họ và tên không được để trống.',
            'name.min' => 'Họ và tên phải có ít nhất :min ký tự.',
            'name.max' => 'Họ và tên không được vượt quá :max ký tự.',

            'phone.regex' => 'Số điện thoại không đúng định dạng. Vui lòng nhập số điện thoại Việt Nam hợp lệ.',
            'phone.max' => 'Số điện thoại không được vượt quá :max ký tự.',

            'birthday.date' => 'Ngày sinh không hợp lệ.',
            'birthday.before_or_equal' => 'Ngày sinh không thể là ngày trong tương lai.',
            'birthday.after' => 'Ngày sinh không hợp lệ.',

            'gender.in' => 'Giới tính không hợp lệ.',

            'avatar_url.url' => 'URL ảnh đại diện không hợp lệ.',
            'avatar_url.max' => 'URL ảnh đại diện không được vượt quá :max ký tự.',

            'address.max' => 'Địa chỉ không được vượt quá :max ký tự.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowedFields = ['name', 'phone', 'birthday', 'gender', 'avatar_url', 'address'];

            $hasProfileField = collect($allowedFields)
                ->contains(fn (string $field): bool => $this->has($field));

            if (!$hasProfileField) {
                $validator->errors()->add(
                    'profile',
                    'Vui lòng cung cấp ít nhất một trường thông tin để cập nhật.'
                );
            }
        });
    }
}
