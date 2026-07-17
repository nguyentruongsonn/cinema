<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalize email
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => strtolower(trim($email))]);
        }

        // Normalize username
        $username = $this->input('username');
        if (is_string($username)) {
            $this->merge(['username' => strtolower(trim($username))]);
        }

        // Normalize phone
        $phone = $this->input('phone');
        if (is_string($phone)) {
            $this->merge(['phone' => trim($phone)]);
        }

        // Auto-generate username from email if not provided
        // WARNING: Race-prone - uniqueness validation happens after normalization
        // Consider moving username generation to service layer
        if (! $this->filled('username') && $this->filled('email')) {
            $this->merge([
                'username' => str($this->input('email'))->before('@')->slug('_')->toString(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'regex:/^(0|\+84)[0-9]{9,10}$/', 'max:20'],
            'password' => [
                'required',
                'string',
                'max:1024',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            // NOTE: Terms is nullable. If this is a legal requirement, consider making it required.
            'terms' => ['nullable', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'name.min' => 'Họ tên phải có ít nhất :min ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
            'username.alpha_dash' => 'Tên đăng nhập chỉ được chứa chữ, số, dấu gạch ngang và gạch dưới.',
            'username.unique' => 'Tên đăng nhập đã được sử dụng.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'terms.accepted' => 'Bạn cần đồng ý điều khoản sử dụng.',
        ];
    }
}
