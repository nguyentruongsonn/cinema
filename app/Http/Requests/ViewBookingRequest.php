<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'encryptedShowtimeId' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'encryptedShowtimeId' => $this->route('encryptedShowtimeId'),
        ]);
    }

    public function messages(): array
    {
        return [
            'encryptedShowtimeId.required' => 'Showtime identifier is required.',
            'encryptedShowtimeId.string' => 'Invalid showtime identifier format.',
            'encryptedShowtimeId.max' => 'Showtime identifier is too long.',
        ];
    }
}
