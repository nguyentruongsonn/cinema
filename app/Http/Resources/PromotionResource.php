<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Promotion */
class PromotionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->relationLoaded('pivot') ? $this->getRelation('pivot') : null;
        $startDate = $this->getAttribute('start_date');
        $endDate = $this->getAttribute('end_date');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount,
            'min_order_value' => $this->min_order_value,
            'start_date' => $startDate?->toISOString(),
            'end_date' => $endDate?->toISOString(),
            'registered_at' => data_get($pivot, 'created_at')?->toISOString(),
            'usage_count' => (int) data_get($pivot, 'usage_count', 0),
        ];
    }
}
