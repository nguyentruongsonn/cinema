<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreenResource extends JsonResource
{
    /**
     * Transform the screen resource into an array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'theater_id' => $this->theater_id,
            'theater' => $this->whenLoaded('theater', function () {
                return [
                    'id' => $this->theater->id,
                    'name' => $this->theater->name,
                    'branch_id' => $this->theater->branch_id,
                ];
            }),
            'name' => $this->name,
            'code' => $this->code,
            'capacity' => $this->capacity,
            'seat_layout_template_id' => $this->seat_layout_template_id,
            'format_id' => $this->format_id,
            'format' => $this->whenLoaded('format', function () {
                return [
                    'id' => $this->format->id,
                    'name' => $this->format->name,
                    'code' => $this->format->code,
                ];
            }),
            'sound_id' => $this->sound_id,
            'sound' => $this->whenLoaded('sound', function () {
                return [
                    'id' => $this->sound->id,
                    'name' => $this->sound->name,
                    'code' => $this->sound->code,
                ];
            }),
            'status' => match ((int) $this->getRawOriginal('status')) {
                1 => 'active',
                2 => 'maintenance',
                default => 'inactive',
            },
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}