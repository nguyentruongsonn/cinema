<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'screen_id' => $this->screen_id,
            'seat_type_id' => $this->seat_type_id,
            'row' => $this->row,
            'number' => $this->number,
            'row_index' => $this->row_index,
            'column_index' => $this->column_index,
            'label' => $this->label,
            'status' => (bool) $this->status,
            'screen' => $this->whenLoaded('screen', fn () => new ScreenResource($this->screen)),
            'seat_type' => $this->whenLoaded('seatType', fn () => [
                'id' => $this->seatType?->id,
                'name' => $this->seatType?->name,
                'price_surcharge' => isset($this->seatType?->price_surcharge)
                    ? (float) $this->seatType->price_surcharge
                    : null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
