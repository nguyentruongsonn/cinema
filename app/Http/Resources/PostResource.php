<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
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
            'content' => app(\App\Services\HtmlContentSanitizer::class)->sanitize((string) $this->content),
            'excerpt' => $this->excerpt,
            'category' => $this->category,
            'category_label' => $this->category_label,
            'badge_text' => $this->badge_text,
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->image_url,
            'image_url' => $this->image_url,
            'reading_time' => $this->reading_time,
            'author_name' => $this->author_name,
            'is_published' => (bool) $this->is_published,
            'publication_status' => $this->publicationStatus(),
            'published_at' => $this->published_at?->toISOString(),
            'published_date' => $this->published_at?->format('d/m/Y'),
            'view_count' => $this->view_count,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
