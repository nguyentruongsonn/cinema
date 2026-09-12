<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'birthday' => $this->birthday?->format('Y-m-d'),
            'gender' => $this->gender,
            'address' => $this->address,
            'loyalty_points' => $this->loyalty_points ?? 0,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'role' => $this->when(
                $this->relationLoaded('role'),
                fn() => [
                    'id' => $this->role?->id,
                    'name' => $this->role?->name,
                    'display_name' => $this->role?->display_name,
                ]
            ),

            'role_id' => $this->when(
                $this->relationLoaded('role'),
                fn() => $this->role?->id
            ),

            'theaters' => $this->when(
                $this->relationLoaded('theaters'),
                fn() => $this->theaters->map(fn($theater) => [
                    'id' => $theater->id,
                    'name' => $theater->name,
                ])->values()
            ),

            'theater_ids' => $this->when(
                $this->relationLoaded('theaters'),
                fn() => $this->theaters->pluck('id')->values()
            ),

            'orders_count' => $this->when(
                $this->relationLoaded('orders'),
                fn() => $this->orders->count()
            ),

            'recent_orders' => $this->when(
                $this->relationLoaded('orders') && $request->user()?->can('viewAny', \App\Models\User::class),
                fn() => $this->orders->take(10)->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_code' => $order->code,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at?->toISOString(),
                    ];
                })
            ),
        ];
    }
}
