<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Role;
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
            'theater_ids' => ['nullable', 'array', 'max:20'],
            'theater_ids.*' => ['integer', 'distinct', 'exists:theaters,id'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được sử dụng.',
            'username.unique' => 'Tên đăng nhập này đã được sử dụng.',
            'phone.regex' => 'Số điện thoại không đúng định dạng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'birthday.before' => 'Ngày sinh phải nhỏ hơn ngày hiện tại.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'role_id.exists' => 'Vai trò đã chọn không tồn tại.',
            'theater_ids.*.exists' => 'Rạp đã chọn không tồn tại.',
        ];
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roleId = $this->input('role_id');

            if ($roleId === null) {
                return;
            }

            $role = Role::query()->find($roleId);
            if ($role?->slug && in_array($role->slug, ['admin', 'super-admin'], true)
                && !($this->user()?->hasRole('super-admin') ?? false)) {
                $validator->errors()->add('role_id', 'Chỉ super-admin mới được gán vai trò quản trị.');
            }

            if ($this->has('theater_ids') && ! $this->user()?->hasPermission('users.create')) {
                $validator->errors()->add('theater_ids', 'Bạn không có quyền gán rạp cho tài khoản.');
            }
        });
    }

    public function userData(): array
    {
        $validated = $this->validated();

        unset($validated['role_id'], $validated['theater_ids'], $validated['status']);

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

    /**
     * @return array<int, int>
     */
    public function theaterIds(): array
    {
        return array_map('intval', $this->validated()['theater_ids'] ?? []);
    }
}
