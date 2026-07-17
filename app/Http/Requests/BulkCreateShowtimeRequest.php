<?php

namespace App\Http\Requests;

use App\Models\Showtime;
use Illuminate\Foundation\Http\FormRequest;

class BulkCreateShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkCreate', Showtime::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
            'screen_id' => ['required', 'integer', 'exists:screens,id'],
            'date_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:' . now()->addMonths(3)->toDateString()],
            'times' => ['required', 'array', 'min:1', 'max:10'],
            'times.*' => ['required', 'date_format:H:i', 'distinct'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
            'version_type_id' => ['nullable', 'integer', 'exists:version_types,id'],
        ];
    }
}