<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreScreenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theater_id' => ['required', 'exists:theaters,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'format_id' => ['nullable', 'exists:formats,id'],
            'sound_id' => ['nullable', 'exists:sounds,id'],
            'seat_layout_template_id' => ['required', 'exists:seat_layout_templates,id'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'theater_id.required' => 'Vui lòng chọn rạp chiếu.',
            'theater_id.exists' => 'Rạp chiếu đã chọn không hợp lệ.',
            'name.required' => 'Tên phòng chiếu không được để trống.',
            'code.required' => 'Mã phòng chiếu không được để trống.',
            'format_id.exists' => 'Loại phòng chiếu không hợp lệ.',
            'sound_id.exists' => 'Định dạng âm thanh không hợp lệ.',
            'seat_layout_template_id.required' => 'Vui lòng chọn mẫu sơ đồ ghế.',
            'seat_layout_template_id.exists' => 'Mẫu sơ đồ ghế không hợp lệ.',
        ];
    }
}
