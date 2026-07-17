<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:500'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function userData(): array
    {
        $validated = $this->validated();

        unset($validated['role_id'], $validated['status']);

        return $validated;
    }

    public function roleId(): ?int
    {
        $roleId = $this->validated()['role_id'] ?? null;

        return $roleId === null ? null : (int) $roleId;
    }

    public function status(): bool
    {
        return (bool) ($this->validated()['status'] ?? true);
    }
}
