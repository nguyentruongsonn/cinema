<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StatFilterRequest extends FormRequest
{
    /**
     * Only admins/super-admins can access revenue stats.
     * Uses the same hasAnyRole() convention as RoleMiddleware.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'super-admin'])
            : false;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date'        => 'Ngày bắt đầu không hợp lệ.',
            'start_date.date_format' => 'Ngày bắt đầu phải có định dạng YYYY-MM-DD.',
            'end_date.date'          => 'Ngày kết thúc không hợp lệ.',
            'end_date.date_format'   => 'Ngày kết thúc phải có định dạng YYYY-MM-DD.',
            'end_date.after_or_equal'=> 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
