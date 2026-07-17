<?php

namespace Tests\Feature\Seat;

use App\Exceptions\SeatConflictException;
use App\Models\Screen;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\User;
use App\Services\SeatService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeatServiceLockingTest extends TestCase
{
    use RefreshDatabase;

    private SeatService $seatService;

    private User $firstUser;

    private User $secondUser;

    private Showtime $showtime;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->seatService = app(SeatService::class);
        $this->firstUser = User::factory()->create();
        $this->secondUser = User::factory()->create();
        $this->showtime = Showtime::factory()->create();
    }

    #[Test]
    public function locking_seats_creates_normalized_active_hold_items(): void
    {
        $seats = Seat::factory()
            ->count(2)
            ->create(['screen_id' => $this->showtime->screen_id]);

        $result = $this->seatService->lock([
            'showtime_id' => $this->showtime->id,
            'seat_ids' => $seats->pluck('id')->reverse()->values()->all(),
        ], $this->firstUser);

        $this->assertDatabaseHas('seat_holds', [
            'id' => $result['hold_id'],
            'showtime_id' => $this->showtime->id,
            'user_id' => $this->firstUser->id,
        ]);

        foreach ($seats as $seat) {
            $this->assertDatabaseHas('seat_hold_items', [
                'seat_hold_id' => $result['hold_id'],
                'showtime_id' => $this->showtime->id,
                'seat_id' => $seat->id,
                'status' => SeatHoldItem::STATUS_ACTIVE,
                'active_lock_key' => SeatHoldItem::activeLockKey(
                    $this->showtime->id,
                    $seat->id
                ),
            ]);
        }

        $hold = SeatHold::with('items')->findOrFail($result['hold_id']);

        $this->assertEqualsCanonicalizing(
            $seats->pluck('id')->all(),
            $hold->normalizedSeatIds()
        );
        $this->assertCount(2, $hold->items);
    }

    #[Test]
    public function another_user_cannot_hold_a_seat_that_is_already_actively_held(): void
    {
        $seat = Seat::factory()->create([
            'screen_id' => $this->showtime->screen_id,
        ]);

        $firstResult = $this->seatService->lock([
            'showtime_id' => $this->showtime->id,
            'seat_ids' => [$seat->id],
        ], $this->firstUser);

        try {
            $this->seatService->lock([
                'showtime_id' => $this->showtime->id,
                'seat_ids' => [$seat->id],
            ], $this->secondUser);

            $this->fail('A second user was able to hold an actively held seat.');
        } catch (SeatConflictException $exception) {
            $this->assertSame(
                'Một số ghế vừa được người khác giữ.',
                $exception->getMessage()
            );
        }

        $this->assertSame(1, SeatHold::query()->count());
        $this->assertDatabaseHas('seat_hold_items', [
            'seat_hold_id' => $firstResult['hold_id'],
            'seat_id' => $seat->id,
            'status' => SeatHoldItem::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function database_unique_lock_key_prevents_two_active_holds_for_the_same_seat(): void
    {
        $seat = Seat::factory()->create([
            'screen_id' => $this->showtime->screen_id,
        ]);

        $firstHold = SeatHold::create([
            'showtime_id' => $this->showtime->id,
            'user_id' => $this->firstUser->id,
            'held_until' => now()->addMinutes(10),
        ]);

        SeatHoldItem::create([
            'seat_hold_id' => $firstHold->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $seat->id,
            'status' => SeatHoldItem::STATUS_ACTIVE,
            'active_lock_key' => SeatHoldItem::activeLockKey(
                $this->showtime->id,
                $seat->id
            ),
            'expires_at' => now()->addMinutes(10),
        ]);

        $secondHold = SeatHold::create([
            'showtime_id' => $this->showtime->id,
            'user_id' => $this->secondUser->id,
            'held_until' => now()->addMinutes(10),
        ]);

        $this->expectException(QueryException::class);

        SeatHoldItem::create([
            'seat_hold_id' => $secondHold->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $seat->id,
            'status' => SeatHoldItem::STATUS_ACTIVE,
            'active_lock_key' => SeatHoldItem::activeLockKey(
                $this->showtime->id,
                $seat->id
            ),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    #[Test]
    public function user_replacing_a_hold_releases_old_items_and_keeps_only_new_seats(): void
    {
        $seats = Seat::factory()
            ->count(3)
            ->create(['screen_id' => $this->showtime->screen_id]);

        $firstResult = $this->seatService->lock([
            'showtime_id' => $this->showtime->id,
            'seat_ids' => [$seats[0]->id, $seats[1]->id],
        ], $this->firstUser);

        $secondResult = $this->seatService->lock([
            'showtime_id' => $this->showtime->id,
            'seat_ids' => [$seats[1]->id, $seats[2]->id],
        ], $this->firstUser);

        $this->assertNotSame($firstResult['hold_id'], $secondResult['hold_id']);
        $this->assertDatabaseMissing('seat_holds', [
            'id' => $firstResult['hold_id'],
        ]);
        $this->assertDatabaseHas('seat_holds', [
            'id' => $secondResult['hold_id'],
        ]);

        $activeSeatIds = SeatHoldItem::query()
            ->active()
            ->where('seat_hold_id', $secondResult['hold_id'])
            ->pluck('seat_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing(
            [$seats[1]->id, $seats[2]->id],
            $activeSeatIds
        );

        $this->assertDatabaseMissing('seat_hold_items', [
            'seat_hold_id' => $firstResult['hold_id'],
            'seat_id' => $seats[0]->id,
        ]);
    }

    #[Test]
    public function cannot_hold_a_seat_from_another_screen(): void
    {
        $wrongScreen = Screen::factory()->create();
        $wrongSeat = Seat::factory()->create([
            'screen_id' => $wrongScreen->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Một hoặc nhiều ghế không thuộc phòng chiếu của suất chiếu này.'
        );

        $this->seatService->lock([
            'showtime_id' => $this->showtime->id,
            'seat_ids' => [$wrongSeat->id],
        ], $this->firstUser);
    }

    #[Test]
    public function unlock_only_removes_the_owners_hold_and_releases_active_items(): void
    {
        $seat = Seat::factory()->create([
            'screen_id' => $this->showtime->screen_id,
        ]);

        $result = $this->seatService->lock([
            'showtime_id' => $this->showtime->id,
            'seat_ids' => [$seat->id],
        ], $this->firstUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);

        try {
            $this->seatService->unlock(
                $result['hold_id'],
                $this->secondUser
            );
        } finally {
            $this->assertDatabaseHas('seat_holds', [
                'id' => $result['hold_id'],
                'user_id' => $this->firstUser->id,
            ]);
            $this->assertDatabaseHas('seat_hold_items', [
                'seat_hold_id' => $result['hold_id'],
                'seat_id' => $seat->id,
                'status' => SeatHoldItem::STATUS_ACTIVE,
            ]);
        }
    }
}