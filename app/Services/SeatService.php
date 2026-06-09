<?php

namespace App\Services;

use App\Events\SeatStatusUpdated;
use App\Models\OrderItem;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SeatService
{
    private const STATUS_PENDING = 1;
    private const STATUS_CONFIRMED = 2;
    private const HOLD_MINUTES = 10;

    public function __construct(
        private readonly OrderExpirationService $orderExpirationService
    ) {
    }

    public function getByShowtime(int $showtimeId, $user): array
    {
        $this->cleanupExpiredReservations($showtimeId);

        $showtime = Showtime::with('screen')->findOrFail($showtimeId);

        $seats = Seat::with('seatType')
            ->where('screen_id', $showtime->screen_id)
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        $bookedSeatIds = $this->getBookedSeatIds($showtimeId);

        $currentUserHolds = collect();
        $currentUserHoldSeatIds = [];
        $userId = $user?->id;

        if ($userId) {
            $currentUserHolds = SeatHold::query()
                ->valid()
                ->where('showtime_id', $showtimeId)
                ->where('user_id', $userId)
                ->get();

            $currentUserHoldSeatIds = $currentUserHolds
                ->flatMap(fn (SeatHold $hold) => (array) $hold->seat_ids)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $otherUserHolds = SeatHold::query()
            ->valid()
            ->where('showtime_id', $showtimeId)
            ->when($userId, fn ($query) => $query->where('user_id', '!=', $userId))
            ->get();

        $otherUserHoldSeatIds = $otherUserHolds
            ->flatMap(fn (SeatHold $hold) => (array) $hold->seat_ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $seatsData = $seats->map(function (Seat $seat) use ($bookedSeatIds, $currentUserHoldSeatIds, $otherUserHoldSeatIds) {
            $seatId = $seat->id;
            $status = 'available';

            if (in_array($seatId, $bookedSeatIds, true)) {
                $status = 'booked';
            } elseif (in_array($seatId, $currentUserHoldSeatIds, true)) {
                $status = 'holding';
            } elseif (in_array($seatId, $otherUserHoldSeatIds, true)) {
                $status = 'locked';
            }

            return [
                'id' => $seat->id,
                'row' => $seat->row,
                'number' => $seat->number,
                'label' => $seat->label ?: ($seat->row . $seat->number),
                'seat_type' => $seat->seatType ? [
                    'id' => $seat->seatType->id,
                    'name' => $seat->seatType->name,
                    'surcharge' => (float) $seat->seatType->surcharge,
                ] : null,
                'seat_type_id' => $seat->seat_type_id,
                'surcharge' => (float) ($seat->seatType?->surcharge ?? 0),
                'status' => $status,
                'is_available' => $status === 'available',
                'is_booked' => $status === 'booked',
                'is_holding' => $status === 'holding',
                'is_locked' => $status === 'locked',
            ];
        });

        return [
            'showtime_id' => $showtimeId,
            'screen_id' => $showtime->screen_id,
            'seats' => $seatsData->values()->all(),
            'current_user_holds' => $currentUserHolds->map(fn (SeatHold $hold) => [
                'id' => $hold->id,
                'seat_ids' => $hold->seat_ids,
                'held_until' => $hold->held_until->toISOString(),
                'expires_in_seconds' => now()->diffInSeconds($hold->held_until, false),
            ])->values()->all(),
        ];
    }

    public function lock(array $data, $user): array
    {
        $hold = DB::transaction(function () use ($data, $user) {
            $this->cleanupExpiredReservations((int) $data['showtime_id']);

            $showtime = Showtime::query()
                ->lockForUpdate()
                ->findOrFail($data['showtime_id']);

            $seatIds = array_values(array_map('intval', $data['seat_ids']));

            $seats = Seat::query()
                ->whereIn('id', $seatIds, 'and', false)
                ->where('screen_id', $showtime->screen_id)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                throw new \RuntimeException('Một hoặc nhiều ghế không thuộc phòng chiếu của suất chiếu này.');
            }

            $bookedSeatIds = $this->getBookedSeatIds($showtime->id, $seatIds);
            if (!empty($bookedSeatIds)) {
                throw new \RuntimeException('Một số ghế đã được đặt hoặc đang chờ thanh toán: ' . implode(', ', $bookedSeatIds));
            }

            $conflictingHold = SeatHold::query()
                ->valid()
                ->where('showtime_id', $showtime->id)
                ->where('user_id', '!=', $user->id)
                ->get()
                ->first(function (SeatHold $hold) use ($seatIds) {
                    return !empty(array_intersect(array_map('intval', (array) $hold->seat_ids), $seatIds));
                });

            if ($conflictingHold) {
                throw new \RuntimeException('Một số ghế đang được người dùng khác giữ tạm thời.');
            }

            SeatHold::query()
                ->where('showtime_id', $showtime->id)
                ->where('user_id', $user->id)
                ->delete();

            return SeatHold::create([
                'showtime_id' => $showtime->id,
                'user_id' => $user->id,
                'seat_ids' => $seatIds,
                'held_until' => now()->addMinutes(self::HOLD_MINUTES),
            ]);
        });

        // Broadcast real-time seat status to all connected clients
        // Wrap in try/catch: development environments may not have Reverb/Pusher running
        try {
            foreach ($hold->seat_ids as $seatId) {
                broadcast(new SeatStatusUpdated(
                    showtimeId: $hold->showtime_id,
                    seatId:     (int) $seatId,
                    status:     'locked',
                    userId:     $hold->user_id,
                ));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Seat broadcast failed (non-critical): ' . $e->getMessage());
        }

        return [
            'hold_id'          => $hold->id,
            'showtime_id'      => $hold->showtime_id,
            'seat_ids'         => $hold->seat_ids,
            'held_until'       => $hold->held_until->toISOString(),
            'expires_in_seconds' => now()->diffInSeconds($hold->held_until, false),
        ];
    }

    public function unlock(int $holdId, $user): array
    {
        $hold = SeatHold::query()->find($holdId);

        if (!$hold) {
            throw new \RuntimeException('Seat hold not found', 404);
        }

        if ((int) $hold->user_id !== (int) $user->id) {
            throw new \RuntimeException('Unauthorized', 403);
        }

        $showtimeId = $hold->showtime_id;
        $seatIds    = (array) $hold->seat_ids;

        SeatHold::query()->whereKey($hold->getKey())->delete();

        // Broadcast real-time unlock to all connected clients
        // Wrap in try/catch: development environments may not have Reverb/Pusher running
        try {
            foreach ($seatIds as $seatId) {
                broadcast(new SeatStatusUpdated(
                    showtimeId: $showtimeId,
                    seatId:     (int) $seatId,
                    status:     'available',
                ));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Seat unlock broadcast failed (non-critical): ' . $e->getMessage());
        }

        return ['unlocked_count' => count($seatIds)];
    }

    private function cleanupExpiredReservations(?int $showtimeId = null): void
    {
        SeatHold::query()->expired()->delete();
        $this->orderExpirationService->expirePendingOrders($showtimeId);
    }

    private function getBookedSeatIds(int $showtimeId, ?array $seatIds = null): array
    {
        return OrderItem::query()
            ->where('item_type', Seat::class)
            ->when($seatIds, fn ($query) => $query->whereIntegerInRaw('item_id', $seatIds))
            ->whereHas('order', function ($query) use ($showtimeId) {
                $query->where('showtime_id', $showtimeId)
                    ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
            })
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
