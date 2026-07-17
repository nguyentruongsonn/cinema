<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Authorize profile update.
     *
     * Users can only update their own profile.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare input for validation.
     *
     * Normalize and sanitize user input before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        // Trim and normalize name
        if ($this->has('name') && is_string($this->input('name'))) {
            $normalized['name'] = trim($this->input('name'));
        }

        // Trim and normalize phone
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $normalized['phone'] = trim($this->input('phone'));
        }

        // Trim and normalize avatar URL
        if ($this->has('avatar_url') && is_string($this->input('avatar_url'))) {
            $normalized['avatar_url'] = trim($this->input('avatar_url'));
        }

        // Trim and normalize address
        if ($this->has('address') && is_string($this->input('address'))) {
            $normalized['address'] = trim($this->input('address'));
        }

        if (!empty($normalized)) {
            $this->merge($normalized);
        }
    }

    /**
     * Get validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Name: minimum 2 chars, maximum 255 chars, trimmed
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],

            // Phone: Vietnamese phone format with normalization
            // Accepts: 0xxxxxxxxx or +84xxxxxxxxx (9-10 digits after prefix)
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+84|0)[0-9]{9,10}$/', 'max:20'],

            // Birthday: must be past date, reasonable bounds
            'birthday' => ['sometimes', 'nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],

            // Gender: restricted enum values
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],

            // Avatar URL: restrict to real HTTP(S) URLs only
            'avatar_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],

            // Address: reasonable length, trimmed
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
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

    /**
     * Configure validator to reject empty profile updates.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowedFields = ['name', 'phone', 'birthday', 'gender', 'avatar_url', 'address'];

            $hasProfileField = collect($allowedFields)
                ->contains(fn (string $field): bool => $this->has($field));

            // Reject completely empty update payload
            if (!$hasProfileField) {
                $validator->errors()->add(
                    'profile',
                    'Vui lòng cung cấp ít nhất một trường thông tin để cập nhật.'
                );
            }
        });
    }
}
