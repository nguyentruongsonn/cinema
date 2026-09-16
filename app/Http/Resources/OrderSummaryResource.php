<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
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
            'showtime' => $this->whenLoaded('showtime', fn () => $this->showtimeSnapshot()),
            'invoice' => $this->invoiceSnapshot(),
            'items' => $this->whenLoaded('orderItems', fn () => $this->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => class_basename($item->item_type),
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'metadata' => $item->metadata,
                ];
            })),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function invoiceSnapshot(): array
    {
        $payload = (array) $this->payload;

        return [
            'subtotal' => (float) ($payload['subtotal'] ?? $this->total_amount),
            'seat_total' => (float) ($payload['seat_total'] ?? 0),
            'product_total' => (float) ($payload['product_total'] ?? 0),
            'voucher_discount' => (float) ($payload['voucher_discount'] ?? 0),
            'point_discount' => (float) ($payload['point_discount'] ?? 0),
            'discount_amount' => (float) ($payload['discount_amount'] ?? 0),
            'points_used' => (int) ($payload['points_used'] ?? 0),
            'total' => (float) $this->total_amount,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function showtimeSnapshot(): ?array
    {
        $showtime = $this->showtime;
        if (! $showtime) {
            return null;
        }

        $movie = $showtime->relationLoaded('movie') ? $showtime->movie : null;
        $screen = $showtime->relationLoaded('screen') ? $showtime->screen : null;
        $theater = $screen?->relationLoaded('theater') ? $screen->theater : null;

        return [
            'id' => $showtime->id,
            'movie_title' => $movie?->title,
            'poster_url' => $movie?->poster_display_url,
            'poster_display_url' => $movie?->poster_display_url,
            'scheduled_at' => $showtime->scheduled_at,
            'screen_name' => $screen?->name,
            'theater_name' => $theater?->name,
        ];
    }
}
