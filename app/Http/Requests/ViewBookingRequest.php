<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates encrypted showtime identifier for booking page access.
 *
 * The encrypted showtime ID is passed as a route parameter and validated here.
 */
class ViewBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Public booking page - no authentication required
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'encryptedShowtimeId' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Merge route parameter into request for validation
        $this->merge([
            'encryptedShowtimeId' => $this->route('encryptedShowtimeId'),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'encryptedShowtimeId.required' => 'Showtime identifier is required.',
            'encryptedShowtimeId.string' => 'Invalid showtime identifier format.',
            'encryptedShowtimeId.max' => 'Showtime identifier is too long.',
        ];
    }
}
