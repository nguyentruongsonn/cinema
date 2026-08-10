<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetUser = $this->route('user');

        return $targetUser instanceof User
            && ($this->user()?->can('update', $targetUser) ?? false);
    }

    public function rules(): array
    {
        $targetUser = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUser?->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($targetUser?->id)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:500'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'theater_ids' => ['nullable', 'array', 'max:20'],
            'theater_ids.*' => ['integer', 'distinct', 'exists:theaters,id'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $targetUser = $this->route('user');
            $actor = $this->user();

            if (!$targetUser instanceof User || !$actor) {
                return;
            }

            if ($this->has('role_id') && !$actor->can('updateRole', $targetUser)) {
                $validator->errors()->add('role_id', 'You are not authorized to change user roles.');
            }

            if ($this->has('role_id')) {
                $role = Role::query()->find($this->input('role_id'));
                if ($role?->slug && in_array($role->slug, ['admin', 'super-admin'], true)
                    && !$actor->hasRole('super-admin')) {
                    $validator->errors()->add('role_id', 'Only a super-admin may assign administrative roles.');
                }
            }

            if ($this->has('theater_ids') && ! $actor->hasPermission('users.update')) {
                $validator->errors()->add('theater_ids', 'You are not authorized to assign theaters.');
            }

            if ($this->has('loyalty_points') && !$actor->can('updateLoyaltyPoints', $targetUser)) {
                $validator->errors()->add('loyalty_points', 'You are not authorized to change loyalty points.');
            }

            if ($this->has('status') && !$actor->can('updateStatus', $targetUser)) {
                $validator->errors()->add('status', 'You are not authorized to change user status.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('username'))) {
            $normalized['username'] = mb_strtolower(trim($this->input('username')));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function userData(): array
    {
        $validated = $this->validated();

        unset($validated['role_id'], $validated['theater_ids'], $validated['status'], $validated['loyalty_points']);

        return $validated;
    }

    public function roleId(): ?int
    {
        $roleId = $this->validated()['role_id'] ?? null;

        return $roleId === null ? null : (int) $roleId;
    }

    public function status(): ?bool
    {
        return array_key_exists('status', $this->validated())
            ? (bool) $this->validated()['status']
            : null;
    }

    public function loyaltyPoints(): ?int
    {
        return array_key_exists('loyalty_points', $this->validated())
            ? (int) $this->validated()['loyalty_points']
            : null;
    }

    /**
     * @return array<int, int>|null
     */
    public function theaterIds(): ?array
    {
        if (! array_key_exists('theater_ids', $this->validated())) {
            return null;
        }

        return array_map('intval', $this->validated()['theater_ids'] ?? []);
    }
}
