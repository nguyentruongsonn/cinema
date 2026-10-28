<?php

namespace App\Http\Requests;

use App\Models\Showtime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Showtime::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
            'screen_id' => ['required', 'integer', 'exists:screens,id'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
            'version_type_id' => ['nullable', 'integer', 'exists:version_types,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:now'],
            'status' => ['required', Rule::in([0, 1, '0', '1'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->has('status') ? (int) $this->input('status') : $this->input('status'),
        ]);
    }
}