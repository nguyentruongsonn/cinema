<?php

namespace App\Http\Requests;

use App\Models\Movie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Movie::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('movies', 'slug'),
            ],
            'original_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'trailer_url' => ['nullable', 'url', 'max:2048'],
            'poster_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'duration' => ['required', 'integer', 'min:1', 'max:600'],
            'release_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:release_date'],
            'age_rating' => ['nullable', 'string', 'max:50'],
            'surcharge' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'director' => ['nullable', 'string', 'max:255'],
            'cast' => ['nullable', 'string', 'max:2000'],
            'backdrops' => ['nullable', 'array', 'max:20'],
            'backdrops.*' => ['string', 'url', 'max:2048'],
            'status' => ['sometimes', 'boolean'],
            'is_hidden' => ['sometimes', 'boolean'],
            'manual_override_status' => ['nullable', 'integer', 'min:0', 'max:3'],
            'is_hot' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array', 'max:20'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Movie title is required.',
            'title.max' => 'Movie title cannot exceed 255 characters.',
            'slug.regex' => 'Movie slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This slug is already taken.',
            'poster_url.url' => 'Poster URL must be a valid URL.',
            'trailer_url.url' => 'Trailer URL must be a valid URL.',
            'poster_file.mimes' => 'Poster must be a JPG, JPEG, PNG, or WEBP image.',
            'poster_file.max' => 'Poster cannot exceed 5MB.',
            'banner_file.mimes' => 'Banner must be a JPG, JPEG, PNG, or WEBP image.',
            'banner_file.max' => 'Banner cannot exceed 8MB.',
            'duration.required' => 'Movie duration is required.',
            'duration.integer' => 'Duration must be a valid number.',
            'duration.min' => 'Duration must be at least 1 minute.',
            'release_date.required' => 'Release date is required.',
            'release_date.date' => 'Release date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to release date.',
            'surcharge.numeric' => 'Surcharge must be a valid number.',
            'surcharge.min' => 'Surcharge cannot be negative.',
            'category_ids.array' => 'Categories must be an array.',
            'category_ids.*.distinct' => 'Duplicate categories are not allowed.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'movie title',
            'original_title' => 'original title',
            'poster_url' => 'poster URL',
            'trailer_url' => 'trailer URL',
            'poster_file' => 'poster file',
            'banner_file' => 'banner file',
            'release_date' => 'release date',
            'end_date' => 'end date',
            'age_rating' => 'age rating',
            'category_ids' => 'categories',
        ];
    }
}
