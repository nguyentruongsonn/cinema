<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowtimeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'screen_id' => $this->screen_id,
            'format_id' => $this->format_id,
            'version_type_id' => $this->version_type_id,
            'price_rule_id' => $this->price_rule_id,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'start_time' => $this->formatted_start_time,
            'start_date' => $this->formatted_start_date,
            'pricing_snapshot' => $this->pricing_snapshot,
            'status' => (bool) $this->status,
            'movie' => $this->whenLoaded('movie', fn () => new MovieResource($this->movie)),
            'screen' => $this->whenLoaded('screen', fn () => new ScreenResource($this->screen)),
            'format' => $this->whenLoaded('format', fn () => [
                'id' => $this->format?->id,
                'name' => $this->format?->name,
            ]),
            'version_type' => $this->whenLoaded('versionType', fn () => [
                'id' => $this->versionType?->id,
                'name' => $this->versionType?->name,
                'slug' => $this->versionType?->slug,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
