<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SeatLayoutTemplate */
class SeatLayoutTemplateResource extends JsonResource
{
    /**
     * Transform the seat layout template resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_name' => $this->template_name,
            'description' => $this->description,
            'seat_matrix' => $this->seat_matrix,
            'regular_seat_rows' => $this->regular_seat_rows,
            'vip_seat_rows' => $this->vip_seat_rows,
            'couple_seat_rows' => $this->couple_seat_rows,
            'custom_matrix' => $this->custom_matrix,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Include screens count when requested
            'screens_count' => $this->when(
                $this->relationLoaded('screens'),
                fn() => $this->screens->count()
            ),
        ];
    }
}
