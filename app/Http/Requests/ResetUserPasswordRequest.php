<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetUser = $this->route('user');

        return $targetUser instanceof User
            && ($this->user()?->can('resetPassword', $targetUser) ?? false);
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function password(): string
    {
        return $this->validated('password');
    }
}
