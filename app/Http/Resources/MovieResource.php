<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'original_title' => $this->original_title,
            'description' => $this->description,
            'poster_url' => $this->poster_display_url,
            'banner_url' => $this->banner_display_url,
            'trailer_url' => $this->trailer_url,
            'duration' => $this->duration,
            'release_date' => $this->release_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'age_rating' => $this->age_rating,
            'surcharge' => $this->surcharge !== null ? (float) $this->surcharge : null,
            'director' => $this->director,
            'cast' => $this->cast,
            'backdrops' => $this->backdrops,
            'status' => (bool) $this->status,
            'is_hidden' => (bool) $this->is_hidden,
            'is_hot' => (bool) $this->is_hot,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])),
            'formats' => $this->whenLoaded('formats', fn () => $this->formats->map(fn ($format) => [
                'id' => $format->id,
                'name' => $format->name,
            ])),
            'subtitles' => $this->whenLoaded('subtitles', fn () => $this->subtitles->map(fn ($subtitle) => [
                'id' => $subtitle->id,
                'name' => $subtitle->name,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
