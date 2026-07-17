<?php

namespace App\Http\Requests;

use App\Models\Showtime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routeShowtime = $this->route('showtime');

        $showtime = $routeShowtime instanceof Showtime
            ? $routeShowtime
            : Showtime::find($routeShowtime ?? $this->route('id'));

        return $showtime instanceof Showtime
            && ($this->user()?->can('update', $showtime) ?? false);
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['sometimes', 'required', 'integer', 'exists:movies,id'],
            'screen_id' => ['sometimes', 'required', 'integer', 'exists:screens,id'],
            'format_id' => ['sometimes', 'nullable', 'integer', 'exists:formats,id'],
            'version_type_id' => ['sometimes', 'nullable', 'integer', 'exists:version_types,id'],
            'scheduled_at' => ['sometimes', 'required', 'date_format:Y-m-d H:i:s', 'after:now'],
            'status' => ['sometimes', 'required', Rule::in([0, 1, '0', '1'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge([
                'status' => (int) $this->input('status'),
            ]);
        }
    }
}