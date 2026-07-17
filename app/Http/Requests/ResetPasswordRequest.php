<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $token = $this->input('token');

        if (is_string($email)) {
            $this->merge(['email' => strtolower(trim($email))]);
        }

        if (is_string($token)) {
            $this->merge(['token' => trim($token)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            // CRITICAL: Password policy must match registration to prevent downgrade attacks
            'password' => [
                'required',
                'string',
                'max:1024',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }
}
