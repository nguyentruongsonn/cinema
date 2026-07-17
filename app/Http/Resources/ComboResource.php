<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboResource extends JsonResource
{
    /**
     * Transform the combo resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'discount_percentage' => $this->original_price > 0 
                ? round((($this->original_price - $this->price) / $this->original_price) * 100, 1)
                : 0,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'status' => $this->status,
            'available_stock' => $this->available_stock,
            'items' => ComboItemResource::collection($this->whenLoaded('comboItems')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}