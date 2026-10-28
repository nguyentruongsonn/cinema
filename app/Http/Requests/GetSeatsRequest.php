<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'encrypted_showtime_id' => ['required', 'string', 'max:512'],
        ];
    }

    public function messages(): array
    {
        return [
            'encrypted_showtime_id.required' => __('validation.required', ['attribute' => 'showtime']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'encrypted_showtime_id' => $this->route('encryptedShowtimeId'),
        ]);
    }
}
