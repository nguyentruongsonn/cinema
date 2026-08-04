<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'actor' => $this->whenLoaded('user', fn() => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'request_id' => $this->request_id,
            'created_at' => $this->created_at?->toISOString(),
            'old_values' => $this->when($request->route('auditLog') !== null, $this->old_values),
            'new_values' => $this->when($request->route('auditLog') !== null, $this->new_values),
        ];
    }
}
