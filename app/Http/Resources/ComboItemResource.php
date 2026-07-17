<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboItemResource extends JsonResource
{
    /**
     * Transform the combo item resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->whenLoaded('product');

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'product' => $this->when($this->relationLoaded('product') && $this->product, [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type,
                'price' => $product->price,
                'status' => $product->status,
            ]),
        ];
    }
}
