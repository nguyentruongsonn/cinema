<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTheaterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Theater name is required.',
            'name.max' => 'Theater name cannot exceed 255 characters.',
            'address.required' => 'Theater address is required.',
            'city.required' => 'City is required.',
            'city.max' => 'City name cannot exceed 100 characters.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'status.required' => 'Theater status is required.',
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
