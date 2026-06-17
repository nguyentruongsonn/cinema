<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeatLayoutTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name'     => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'seat_matrix'       => ['required', 'string', 'in:12x12,13x13,14x14,15x15'],
            'regular_seat_rows' => ['nullable', 'integer', 'min:0'],
            'vip_seat_rows'     => ['nullable', 'integer', 'min:0'],
            'couple_seat_rows'  => ['nullable', 'integer', 'min:0'],
            'status'            => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_name.required'    => 'Tên mẫu sơ đồ ghế không được để trống.',
            'seat_matrix.required'      => 'Vui lòng chọn ma trận ghế.',
            'seat_matrix.in'            => 'Ma trận ghế phải là một trong: 12x12, 13x13, 14x14, 15x15.',
            'regular_seat_rows.integer' => 'Số hàng ghế thường phải là số nguyên.',
            'vip_seat_rows.integer'     => 'Số hàng ghế VIP phải là số nguyên.',
            'couple_seat_rows.integer'  => 'Số hàng ghế đôi phải là số nguyên.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $matrix  = $this->input('seat_matrix');
            $maxRows = match($matrix) {
                '12x12' => 12,
                '13x13' => 13,
                '14x14' => 14,
                '15x15' => 15,
                default => null,
            };

            if ($maxRows !== null) {
                $regular = (int) $this->input('regular_seat_rows', 0);
                $vip     = (int) $this->input('vip_seat_rows', 0);
                $couple  = (int) $this->input('couple_seat_rows', 0);
                $total   = $regular + $vip + $couple;

                if ($total > $maxRows) {
                    $validator->errors()->add(
                        'regular_seat_rows',
                        "Tổng số hàng (Regular + VIP + Couple = {$total}) không được vượt quá {$maxRows} hàng của ma trận {$matrix}."
                    );
                }
            }
        });
    }
}

