<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->featured_image ? asset('storage/' . $this->featured_image) : null,
            'is_published' => (bool) $this->is_published,
            'publication_status' => $this->publicationStatus(),
            'published_at' => $this->published_at?->toISOString(),
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
