<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->code,
            'gateway_order_code' => $this->gateway_order_code,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'total_amount' => (float) $this->total_amount,
            'checkout_url' => $this->checkout_url,
            'created_at' => $this->created_at,
            'showtime' => [
                'id' => $this->showtime->id ?? null,
                'movie_title' => $this->showtime->movie->title ?? null,
                'poster_url' => $this->showtime->movie->poster_url ?? null,
                'scheduled_at' => $this->showtime->scheduled_at ?? null,
                'screen_name' => $this->showtime->screen->name ?? null,
                'theater_name' => $this->showtime->screen->theater->name ?? null,
            ],
            'payload' => $this->payload,
            'items' => $this->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => class_basename($item->item_type),
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'metadata' => $item->metadata,
                ];
            }),
        ];
    }
}
