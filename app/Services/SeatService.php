<?php

namespace App\Services;

use App\Events\SeatStatusUpdated;
use App\Exceptions\SeatConflictException;
use App\Models\OrderItem;
use App\Services\TicketPricingService;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeatService
{
    private const STATUS_PENDING = 1;
    private const STATUS_CONFIRMED = 2;
    private const HOLD_MINUTES = 10;
    private const TRANSACTION_ATTEMPTS = 3;

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
                ->with('items')
                ->valid()
                ->where('showtime_id', $showtimeId)
                ->where('user_id', $userId)
                ->get();

            $currentUserHoldSeatIds = $currentUserHolds
                ->flatMap(fn (SeatHold $hold) => $hold->normalizedSeatIds())
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $otherUserHolds = SeatHold::query()
            ->with('items')
            ->valid()
            ->where('showtime_id', $showtimeId)
            ->when($userId, fn ($query) => $query->where('user_id', '!=', $userId))
            ->get();

        $otherUserHoldSeatIds = $otherUserHolds
            ->flatMap(fn (SeatHold $hold) => $hold->normalizedSeatIds())
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
                'seat_ids' => $hold->normalizedSeatIds(),
                'held_until' => $hold->held_until->toISOString(),
                'expires_in_seconds' => $this->getEvenExpiresInSeconds($hold),
            ])->values()->all(),
        ];
    }

    public function lock(array $data, $user): array
    {
        $lockResult = DB::transaction(function () use ($data, $user) {
            // Cleanup MUST happen inside transaction with row locks to prevent TOCTOU race conditions
            $this->cleanupExpiredReservationsAtomic((int) $data['showtime_id']);

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

            $bookedSeatIds = $this->getBookedSeatIds($showtime->id, $seatIds, true);
            if (!empty($bookedSeatIds)) {
                throw new \RuntimeException('Một số ghế đã được đặt hoặc đang chờ thanh toán: ' . implode(', ', $bookedSeatIds));
            }

            $conflictingSeatIds = $this->getConflictingHeldSeatIds(
                showtimeId: $showtime->id,
                seatIds: $seatIds,
                excludeUserId: $user->id,
                lockForUpdate: true
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
                ->with('items')
                ->where('showtime_id', $showtime->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            $releasedSeatIds = $previousHolds
                ->flatMap(fn (SeatHold $hold) => $hold->normalizedSeatIds())
                ->map(fn ($id) => (int) $id)
                ->diff($seatIds)
                ->unique()
                ->values()
                ->all();

            if ($previousHolds->isNotEmpty()) {
                SeatHoldItem::query()
                    ->whereIn('seat_hold_id', $previousHolds->pluck('id')->all(), 'and', false)
                    ->where('status', SeatHoldItem::STATUS_ACTIVE)
                    ->update([
                        'status' => SeatHoldItem::STATUS_EXPIRED,
                        'active_lock_key' => null,
                        'updated_at' => now(),
                    ]);

                SeatHold::query()
                    ->whereIn('id', $previousHolds->pluck('id')->all(), 'and', false)
                    ->delete();
            }

            $heldUntil = now()->addMinutes(self::HOLD_MINUTES);

            $hold = SeatHold::create([
                'showtime_id' => $showtime->id,
                'user_id' => $user->id,
                // seat_ids removed - using normalized SeatHoldItem records instead
                'held_until' => $heldUntil,
            ]);

            $timestamp = now();
            SeatHoldItem::query()->insert(array_map(
                fn (int $seatId) => [
                    'seat_hold_id' => $hold->id,
                    'showtime_id' => $showtime->id,
                    'seat_id' => $seatId,
                    'status' => SeatHoldItem::STATUS_ACTIVE,
                    'active_lock_key' => SeatHoldItem::activeLockKey($showtime->id, $seatId),
                    'expires_at' => $heldUntil,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $seatIds
            ));

            $hold->load('items');

            return [
                'hold' => $hold,
                'released_seat_ids' => $releasedSeatIds,
            ];
        }, self::TRANSACTION_ATTEMPTS);

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

            foreach ($hold->normalizedSeatIds() as $seatId) {
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
            'seat_ids'         => $hold->normalizedSeatIds(),
            'held_until'       => $hold->held_until->toISOString(),
            'expires_in_seconds' => $this->getEvenExpiresInSeconds($hold),
        ];
    }

    public function unlock(int $holdId, $user): array
    {
        $hold = SeatHold::query()->with('items')->find($holdId);

        if (!$hold) {
            throw new \RuntimeException('Seat hold not found', 404);
        }

        if ((int) $hold->user_id !== (int) $user->id) {
            throw new \RuntimeException('Unauthorized', 403);
        }

        $showtimeId = $hold->showtime_id;
        $seatIds    = $hold->normalizedSeatIds();

        DB::transaction(function () use ($hold): void {
            $lockedHold = SeatHold::query()
                ->whereKey($hold->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedHold) {
                return;
            }

            SeatHoldItem::query()
                ->where('seat_hold_id', $lockedHold->getKey())
                ->where('status', SeatHoldItem::STATUS_ACTIVE)
                ->update([
                    'status' => SeatHoldItem::STATUS_EXPIRED,
                    'active_lock_key' => null,
                    'updated_at' => now(),
                ]);

            $lockedHold->delete();
        }, self::TRANSACTION_ATTEMPTS);

        // Broadcast real-time unlock to all connected clients
        // Wrap in try/catch: development environments may not have Reverb/Pusher running
        try {
            foreach ($seatIds as $seatId) {
                broadcast(new SeatStatusUpdated(
                    showtimeId: $showtimeId,
                    seatId:     (int) $seatId,
                    status:     'available',
                    userId:     null,
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
            ->with('items')
            ->expired()
            ->when($showtimeId, fn ($query) => $query->where('showtime_id', $showtimeId))
            ->get();

        if ($expiredHolds->isEmpty()) {
            return 0;
        }

        $expiredHoldIds = $expiredHolds->pluck('id')->all();

        SeatHoldItem::query()
            ->whereIn('seat_hold_id', $expiredHoldIds, 'and', false)
            ->where('status', SeatHoldItem::STATUS_ACTIVE)
            ->update([
                'status' => SeatHoldItem::STATUS_EXPIRED,
                'active_lock_key' => null,
                'updated_at' => now(),
            ]);

        SeatHold::query()
            ->whereIn('id', $expiredHoldIds, 'and', false)
            ->delete();

        if ($broadcast) {
            $this->broadcastExpiredSeatHoldsAsAvailable($expiredHolds);
        }

        return count($expiredHoldIds);
    }

    private function cleanupExpiredReservations(?int $showtimeId = null): void
    {
        $this->cleanupExpiredSeatHolds($showtimeId);
    }

    /**
     * Cleanup expired holds inside the caller transaction while holding row locks.
     *
     * This method is intentionally separate from cleanupExpiredReservations() because
     * seat locking must not perform availability cleanup outside the transaction that
     * validates and creates the new hold.
     */
    private function cleanupExpiredReservationsAtomic(int $showtimeId): void
    {
        $expiredHolds = SeatHold::query()
            ->expired()
            ->where('showtime_id', $showtimeId)
            ->lockForUpdate()
            ->get(['id']);

        if ($expiredHolds->isNotEmpty()) {
            SeatHold::query()
                ->whereIn('id', $expiredHolds->pluck('id')->all(), 'and', false)
                ->delete();
        }

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
                foreach ($hold->normalizedSeatIds() as $seatId) {
                    broadcast(new SeatStatusUpdated(
                        showtimeId: (int) $hold->showtime_id,
                        seatId:     (int) $seatId,
                        status:     'available',
                        userId:     null,
                    ));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Expired seat hold broadcast failed (non-critical): ' . $e->getMessage());
        }
    }

    private function getConflictingHeldSeatIds(
        int $showtimeId,
        array $seatIds,
        int $excludeUserId,
        bool $lockForUpdate = false
    ): array {
        $query = SeatHoldItem::query()
            ->active()
            ->where('showtime_id', $showtimeId)
            ->whereIn('seat_id', $seatIds, 'and', false)
            ->whereHas('seatHold', fn ($query) => $query->where('user_id', '!=', $excludeUserId));

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query
            ->pluck('seat_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function getBookedSeatIds(int $showtimeId, ?array $seatIds = null, bool $lockForUpdate = false): array
    {
        $query = OrderItem::query()
            ->where('item_type', Seat::class)
            ->when($seatIds, fn ($query) => $query->whereIn('item_id', $seatIds, 'and', false))
            ->whereHas('order', function ($query) use ($showtimeId) {
                $query->where('showtime_id', $showtimeId)
                    ->where(function ($statusQuery) {
                        $statusQuery->where('status', self::STATUS_CONFIRMED)
                            ->orWhere(function ($pendingQuery) {
                                $pendingQuery->where('status', self::STATUS_PENDING)
                                    ->where(function ($expiryQuery) {
                                        $expiryQuery->whereNull('expired_at')
                                            ->orWhere('expired_at', '>', now());
                                    });
                            });
                    });
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query
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
