<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
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

            // Include role when loaded
            'role' => $this->when(
                $this->relationLoaded('role'),
                fn() => [
                    'id' => $this->role?->id,
                    'name' => $this->role?->name,
                    'display_name' => $this->role?->display_name,
                ]
            ),

            // Include role_id for admin forms
            'role_id' => $this->when(
                $this->relationLoaded('role'),
                fn() => $this->role?->id
            ),

            // Include order statistics when loaded
            'orders_count' => $this->when(
                $this->relationLoaded('orders'),
                fn() => $this->orders->count()
            ),

            // Include recent orders when loaded (for admin detail view)
            'recent_orders' => $this->when(
                $this->relationLoaded('orders') && $request->user()?->can('viewAny', \App\Models\User::class),
                fn() => $this->orders->take(10)->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_code' => $order->order_code,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at?->toISOString(),
                    ];
                })
            ),
        ];
    }
}
