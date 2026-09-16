<?php

namespace App\Http\Requests;

use App\Models\Showtime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSingleDayShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkCreate', Showtime::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
            'version_type_id' => ['nullable', 'integer', 'exists:version_types,id'],
            'status' => ['nullable', Rule::in([0, 1, '0', '1'])],
            'slots' => ['required', 'array', 'min:1', 'max:100'],
            'slots.*.time' => ['required', 'date_format:H:i'],
            'slots.*.screen_id' => ['required', 'integer', 'exists:screens,id'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $slots = $this->input('slots', []);

            if (! is_array($slots)) {
                return;
            }

            $seen = [];
            foreach ($slots as $index => $slot) {
                if (! is_array($slot) || ! isset($slot['screen_id'], $slot['time'])) {
                    continue;
                }

                $key = $slot['screen_id'] . '|' . $slot['time'];

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "slots.$index",
                        'Duplicate screen and time combinations are not allowed.'
                    );
                    continue;
                }

                $seen[$key] = true;
            }
        });
    }
}