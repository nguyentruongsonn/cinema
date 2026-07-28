<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ]),
            'showtime' => $this->whenLoaded('showtime', fn () => [
                'id' => $this->showtime?->id,
                'scheduled_at' => $this->showtime?->scheduled_at?->toISOString(),
                'movie' => $this->showtime?->relationLoaded('movie') && $this->showtime?->movie ? [
                    'id' => $this->showtime->movie->id,
                    'title' => $this->showtime->movie->title,
                    'poster_url' => $this->showtime->movie->poster_url,
                    'duration' => $this->showtime->movie->duration,
                    'age_rating' => $this->showtime->movie->age_rating,
                ] : null,
                'screen' => $this->showtime?->relationLoaded('screen') && $this->showtime?->screen ? [
                    'id' => $this->showtime->screen->id,
                    'name' => $this->showtime->screen->name,
                    'theater' => $this->showtime->screen->relationLoaded('theater') && $this->showtime->screen->theater ? [
                        'id' => $this->showtime->screen->theater->id,
                        'name' => $this->showtime->screen->theater->name,
                    ] : null,
                ] : null,
            ]),
            'items' => $this->whenLoaded('orderItems', fn () => $this->orderItems->map(fn ($item) => [
                'id' => $item->id,
                'type' => class_basename($item->item_type),
                'metadata' => $item->metadata,
            ])),
        ];
    }
}
