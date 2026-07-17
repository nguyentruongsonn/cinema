<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'gateway_order_code' => $this->gateway_order_code,
            'payment_provider' => $this->payment_provider,
            'user_id' => $this->user_id,
            'showtime_id' => $this->showtime_id,
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'checkout_url' => $this->checkout_url,
            'paid_at' => $this->paid_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'expired_at' => $this->expired_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'showtime' => $this->whenLoaded('showtime', fn () => new ShowtimeResource($this->showtime)),
            'payment' => $this->whenLoaded('payment', fn () => new PaymentResource($this->payment)),
            'items' => $this->whenLoaded('orderItems', fn () => $this->orderItems->map(fn ($item) => [
                'id' => $item->id,
                'type' => class_basename($item->item_type),
                'item_id' => $item->item_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'metadata' => $item->metadata,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
