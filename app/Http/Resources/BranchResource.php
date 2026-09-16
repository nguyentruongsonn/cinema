<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Branch */
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

            'theaters_count' => $this->when(
                isset($this->theaters_count),
                fn () => (int) $this->theaters_count
            ),

            'active_theaters_count' => $this->when(
                $this->getAttribute('active_theaters_count') !== null,
                fn () => (int) $this->getAttribute('active_theaters_count')
            ),
        ];
    }
}
