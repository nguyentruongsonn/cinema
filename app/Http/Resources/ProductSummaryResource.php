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
        $stock = (int) data_get($this->resource, 'stock', 0);

        return [
            'id' => data_get($this->resource, 'id'),
            'catalog_key' => data_get($this->resource, 'catalog_key') ?? 'product:' . data_get($this->resource, 'id'),
            'catalog_type' => data_get($this->resource, 'catalog_type', 'product'),
            'name' => data_get($this->resource, 'name'),
            'type' => data_get($this->resource, 'type'),
            'price' => data_get($this->resource, 'price'),
            'image_url' => data_get($this->resource, 'image_url'),
            'description' => data_get($this->resource, 'description'),
            'available' => $stock > 0,
            'low_stock' => $stock > 0 && $stock <= self::LOW_STOCK_THRESHOLD,
            'max_quantity' => min((int) data_get($this->resource, 'max_quantity', $stock), self::MAX_PUBLIC_QUANTITY),
        ];
    }
}
