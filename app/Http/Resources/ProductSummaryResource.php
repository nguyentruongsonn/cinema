<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSummaryResource extends JsonResource
{
    private const MAX_PUBLIC_QUANTITY = 10;
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * Transform the product into a lightweight booking-catalog payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = (int) $this->stock;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'price' => $this->price,
            'image_url' => $this->image_url,
            'available' => $stock > 0,
            'low_stock' => $stock > 0 && $stock <= self::LOW_STOCK_THRESHOLD,
            'max_quantity' => min($stock, self::MAX_PUBLIC_QUANTITY),
        ];
    }
}
