<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateTheaterRequest - Validation for updating an existing theater
 *
 * Handles validation logic for theater updates following Clean Architecture principles.
 * Separates validation from controllers for better maintainability.
 */
class UpdateTheaterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (admin role required)
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
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string'],
            'city' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.string' => 'Theater name must be a string.',
            'name.max' => 'Theater name cannot exceed 255 characters.',
            'address.string' => 'Theater address must be a string.',
            'city.string' => 'City must be a string.',
            'city.max' => 'City name cannot exceed 100 characters.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'status.in' => 'Status must be either active or inactive.',
            'branch_id.exists' => 'The selected branch does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'theater name',
            'address' => 'theater address',
            'city' => 'city',
            'phone' => 'phone number',
            'email' => 'email address',
            'status' => 'theater status',
            'branch_id' => 'branch',
        ];
    }
}
