<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreMovieRequest - Validation for creating a new movie
 *
 * Handles validation logic for movie creation following Clean Architecture principles.
 * Separates validation from controllers for better maintainability.
 */
class StoreMovieRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('movies', 'slug'),
            ],
            'original_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'string', 'max:255'],
            'trailer_url' => ['nullable', 'string', 'max:255'],
            'duration' => ['required', 'integer', 'min:1'],
            'release_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:release_date'],
            'age_rating' => ['nullable', 'string', 'max:50'],
            'surcharge' => ['nullable', 'numeric', 'min:0'],
            'director' => ['nullable', 'string', 'max:255'],
            'cast' => ['nullable', 'string', 'max:255'],
            'backdrops' => ['nullable', 'array'],
            'status' => ['sometimes', 'boolean'],
            'is_hidden' => ['sometimes', 'boolean'],
            'manual_override_status' => ['nullable', 'integer', 'min:0'],
            'is_hot' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
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
            'title.required' => 'Movie title is required.',
            'title.max' => 'Movie title cannot exceed 255 characters.',
            'slug.unique' => 'This slug is already taken.',
            'duration.required' => 'Movie duration is required.',
            'duration.integer' => 'Duration must be a valid number.',
            'duration.min' => 'Duration must be at least 1 minute.',
            'release_date.required' => 'Release date is required.',
            'release_date.date' => 'Release date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to release date.',
            'surcharge.numeric' => 'Surcharge must be a valid number.',
            'surcharge.min' => 'Surcharge cannot be negative.',
            'category_ids.array' => 'Categories must be an array.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
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
            'title' => 'movie title',
            'original_title' => 'original title',
            'poster_url' => 'poster URL',
            'trailer_url' => 'trailer URL',
            'release_date' => 'release date',
            'end_date' => 'end date',
            'age_rating' => 'age rating',
            'category_ids' => 'categories',
        ];
    }
}
