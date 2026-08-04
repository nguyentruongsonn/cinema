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
            'invoice' => $this->invoiceSnapshot(),
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
            'tickets' => $this->whenLoaded('tickets', fn () => $this->tickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toISOString(),
                'seat' => $ticket->relationLoaded('seat') && $ticket->seat ? [
                    'id' => $ticket->seat->id,
                    'label' => $ticket->seat->label,
                    'row' => $ticket->seat->row,
                    'number' => $ticket->seat->number,
                    'seat_type' => $ticket->seat->relationLoaded('seatType') && $ticket->seat->seatType ? [
                        'id' => $ticket->seat->seatType->id,
                        'name' => $ticket->seat->seatType->name,
                    ] : null,
                ] : null,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceSnapshot(): array
    {
        $payload = (array) $this->payload;
        $promotion = (array) ($payload['voucher'] ?? $payload['promotion'] ?? []);

        return [
            'subtotal' => (float) ($payload['subtotal'] ?? $this->total_amount),
            'seat_total' => (float) ($payload['seat_total'] ?? 0),
            'product_total' => (float) ($payload['product_total'] ?? 0),
            'voucher_discount' => (float) ($payload['voucher_discount'] ?? 0),
            'point_discount' => (float) ($payload['point_discount'] ?? 0),
            'discount_amount' => (float) ($payload['discount_amount'] ?? 0),
            'points_used' => (int) ($payload['points_used'] ?? 0),
            'promotion' => array_filter([
                'id' => $promotion['id'] ?? null,
                'code' => $promotion['code'] ?? null,
                'name' => $promotion['name'] ?? null,
                'type' => $promotion['type'] ?? $promotion['discount_type'] ?? null,
                'value' => $promotion['value'] ?? $promotion['discount_value'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            'total' => (float) $this->total_amount,
        ];
    }
}
