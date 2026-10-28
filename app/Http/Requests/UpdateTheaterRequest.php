<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTheaterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
