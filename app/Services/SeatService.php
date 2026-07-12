<?php

namespace App\Services;

use App\Events\SeatStatusUpdated;
use App\Exceptions\SeatConflictException;
use App\Models\OrderItem;
use App\Services\TicketPricingService;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeatService
{
    private const STATUS_PENDING = 1;
    private const STATUS_CONFIRMED = 2;
    private const HOLD_MINUTES = 10;

    public function __construct(
        private readonly OrderExpirationService $orderExpirationService,
        private readonly TicketPricingService $ticketPricingService
    ) {
    }

    public function getByShowtime(int $showtimeId, $user): array
    {
        $this->cleanupExpiredReservations($showtimeId);

        $showtime = Showtime::with(['screen.theater', 'format', 'movie'])->findOrFail($showtimeId);

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

        $seatsData = $seats->map(function (Seat $seat) use ($showtime, $bookedSeatIds, $currentUserHoldSeatIds, $otherUserHoldSeatIds) {
            $seatId = $seat->id;
            $status = 'available';

            if (in_array($seatId, $bookedSeatIds, true)) {
                $status = 'booked';
            } elseif (in_array($seatId, $currentUserHoldSeatIds, true)) {
                $status = 'holding';
            } elseif (in_array($seatId, $otherUserHoldSeatIds, true)) {
                $status = 'locked';
            }

            // Calculate dynamic price using TicketPricingService
            $seatTypeName = $seat->seatType?->name ?? '';
            $seatTypeSlug = $seat->seatType?->slug ?? '';
            $isDoubleSeat = $this->isDoubleSeat($seatTypeName, $seatTypeSlug);

            $pricingResult = $this->ticketPricingService->calculate(
                format: $showtime->format?->name ?? '2D',
                scheduledAt: $showtime->scheduled_at,
                customerType: 'adult',
                isDoubleSeat: $isDoubleSeat,
                movieSurcharge: (int)($showtime->movie?->surcharge ?? 0),
                extraHolidays: [],
                formatSurcharge: (int)($showtime->format?->surcharge ?? 0),
                seatSurcharge: (int)($seat->seatType?->surcharge ?? 0),
                theaterPricing: $showtime->screen?->theater?->pricing_profile
            );

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
                'price' => $pricingResult['total_price'],
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
                'expires_in_seconds' => $this->getEvenExpiresInSeconds($hold),
            ])->values()->all(),
        ];
    }

    public function lock(array $data, $user): array
    {
        $lockResult = DB::transaction(function () use ($data, $user) {
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

            $conflictingSeatIds = $this->getConflictingHeldSeatIds(
                showtimeId: $showtime->id,
                seatIds: $seatIds,
                excludeUserId: $user->id
            );

            if (!empty($conflictingSeatIds)) {
                $conflictedSeats = $seats
                    ->whereIn('id', $conflictingSeatIds)
                    ->map(fn (Seat $seat) => [
                        'id' => $seat->id,
                        'label' => $seat->label ?: ($seat->row . $seat->number),
                        'status' => 'locked',
                    ])
                    ->values()
                    ->all();

                throw new SeatConflictException(
                    'Một số ghế vừa được người khác giữ.',
                    $conflictedSeats
                );
            }

            $previousHolds = SeatHold::query()
                ->where('showtime_id', $showtime->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get(['id', 'seat_ids']);

            $releasedSeatIds = $previousHolds
                ->flatMap(fn (SeatHold $hold) => (array) $hold->seat_ids)
                ->map(fn ($id) => (int) $id)
                ->diff($seatIds)
                ->unique()
                ->values()
                ->all();

            if ($previousHolds->isNotEmpty()) {
                SeatHold::query()
                    ->whereIn('id', $previousHolds->pluck('id')->all())
                    ->delete();
            }

            $hold = SeatHold::create([
                'showtime_id' => $showtime->id,
                'user_id' => $user->id,
                'seat_ids' => $seatIds,
                'held_until' => now()->addMinutes(self::HOLD_MINUTES),
            ]);

            return [
                'hold' => $hold,
                'released_seat_ids' => $releasedSeatIds,
            ];
        });

        /** @var SeatHold $hold */
        $hold = $lockResult['hold'];
        $releasedSeatIds = $lockResult['released_seat_ids'];

        // Broadcast real-time seat status to all connected clients
        // Wrap in try/catch: development environments may not have Reverb/Pusher running
        try {
            foreach ($releasedSeatIds as $seatId) {
                broadcast(new SeatStatusUpdated(
                    showtimeId: $hold->showtime_id,
                    seatId:     (int) $seatId,
                    status:     'available',
                    userId:     $hold->user_id,
                ));
            }

            foreach ($hold->seat_ids as $seatId) {
                broadcast(new SeatStatusUpdated(
                    showtimeId: $hold->showtime_id,
                    seatId:     (int) $seatId,
                    status:     'locked',
                    userId:     $hold->user_id,
                ));
            }
        } catch (\Exception $e) {
            Log::warning('Seat broadcast failed (non-critical): ' . $e->getMessage());
        }

        return [
            'hold_id'          => $hold->id,
            'showtime_id'      => $hold->showtime_id,
            'seat_ids'         => $hold->seat_ids,
            'held_until'       => $hold->held_until->toISOString(),
            'expires_in_seconds' => $this->getEvenExpiresInSeconds($hold),
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
            Log::warning('Seat unlock broadcast failed (non-critical): ' . $e->getMessage());
        }

        return ['unlocked_count' => count($seatIds)];
    }

    public function cleanupExpiredSeatHolds(?int $showtimeId = null, bool $broadcast = true): int
    {
        $expiredHolds = SeatHold::query()
            ->expired()
            ->when($showtimeId, fn ($query) => $query->where('showtime_id', $showtimeId))
            ->get(['id', 'showtime_id', 'seat_ids']);

        if ($expiredHolds->isEmpty()) {
            return 0;
        }

        $expiredHoldIds = $expiredHolds->pluck('id')->all();

        SeatHold::query()
            ->whereIn('id', $expiredHoldIds)
            ->delete();

        if ($broadcast) {
            $this->broadcastExpiredSeatHoldsAsAvailable($expiredHolds);
        }

        return count($expiredHoldIds);
    }

    private function cleanupExpiredReservations(?int $showtimeId = null): void
    {
        $this->cleanupExpiredSeatHolds($showtimeId);
        $this->orderExpirationService->expirePendingOrders($showtimeId);
    }

    private function getEvenExpiresInSeconds(SeatHold $hold): int
    {
        $seconds = max(0, (int) now()->diffInSeconds($hold->held_until, false));

        return $seconds - ($seconds % 2);
    }

    private function broadcastExpiredSeatHoldsAsAvailable($expiredHolds): void
    {
        try {
            foreach ($expiredHolds as $hold) {
                foreach ((array) $hold->seat_ids as $seatId) {
                    broadcast(new SeatStatusUpdated(
                        (int) $hold->showtime_id,
                        (int) $seatId,
                        'available',
                        null
                    ));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Expired seat hold broadcast failed (non-critical): ' . $e->getMessage());
        }
    }

    private function getConflictingHeldSeatIds(int $showtimeId, array $seatIds, int $excludeUserId): array
    {
        return SeatHold::query()
            ->valid()
            ->where('showtime_id', $showtimeId)
            ->where('user_id', '!=', $excludeUserId)
            ->where(function ($query) use ($seatIds) {
                foreach ($seatIds as $seatId) {
                    $query->orWhereJsonContains('seat_ids', $seatId);
                }
            })
            ->get(['seat_ids'])
            ->flatMap(function (SeatHold $hold) use ($seatIds) {
                return array_intersect(array_map('intval', (array) $hold->seat_ids), $seatIds);
            })
            ->unique()
            ->values()
            ->all();
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

    private function isDoubleSeat(string $name, string $slug): bool
    {
        $nameLower = mb_strtolower($name);
        $slugLower = mb_strtolower($slug);

        $keywords = ['double', 'couple', 'đôi', 'sweetbox', 'sweet-box'];

        foreach ($keywords as $keyword) {
            if (str_contains($nameLower, $keyword) || str_contains($slugLower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
