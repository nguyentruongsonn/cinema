<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'name', 'email', 'username', 'status'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => $validated['search'] ?? null,
            'role' => $validated['role'] ?? null,
            'status' => $validated['status'] ?? null,
            'verified' => $validated['verified'] ?? null,
            'sort_by' => $validated['sort_by'] ?? 'created_at',
            'sort_order' => $validated['sort_order'] ?? 'desc',
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 15);
    }
}
