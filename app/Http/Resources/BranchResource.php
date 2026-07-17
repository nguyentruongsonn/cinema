<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address ?? null,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Include theater count when requested
            'theaters_count' => $this->when(
                $this->relationLoaded('theaters'),
                fn() => $this->theaters->count()
            ),

            // Include active theater count when requested
            'active_theaters_count' => $this->when(
                $this->relationLoaded('theaters'),
                fn() => $this->theaters->where('is_active', true)->count()
            ),
        ];
    }
}
