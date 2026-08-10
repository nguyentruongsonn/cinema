<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Banner */
class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $images = $this->images->map(fn ($image) => [
            'id' => $image->id,
            'image_path' => $image->image_path,
            'image_url' => MediaUrl::storage($image->image_path),
        ])->values();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'images' => $images,
            'image_url' => $images->first()['image_url'] ?? null,
            'link_url' => $this->link_url,
            'is_active' => (bool) $this->is_active,
            'start_date' => $this->start_date?->format('Y-m-d\TH:i'),
            'end_date' => $this->end_date?->format('Y-m-d\TH:i'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
