<?php

namespace Tests\Feature\Showtime;

use App\Models\Movie;
use App\Models\Screen;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShowtimeServiceSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private ShowtimeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ShowtimeService::class);
    }

    public function test_create_rejects_exact_duplicate_showtime(): void
    {
        $movie = Movie::factory()->create(['duration' => 120]);
        $screen = Screen::factory()->create();
        $scheduledAt = Carbon::now()->addDays(2)->startOfHour();

        Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $scheduledAt,
        ]);

        try {
            $this->service->create([
                'movie_id' => $movie->id,
                'screen_id' => $screen->id,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'status' => 1,
            ]);

            $this->fail('An exact duplicate showtime was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheduled_at', $exception->errors());
        }

        $this->assertSame(1, Showtime::query()
            ->where('screen_id', $screen->id)
            ->where('scheduled_at', $scheduledAt)
            ->count());
    }

    public function test_create_rejects_showtime_starting_during_existing_movie(): void
    {
        $existingMovie = Movie::factory()->create(['duration' => 120]);
        $newMovie = Movie::factory()->create(['duration' => 90]);
        $screen = Screen::factory()->create();
        $existingStart = Carbon::now()->addDays(2)->startOfHour();

        Showtime::factory()->create([
            'movie_id' => $existingMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $existingStart,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create([
            'movie_id' => $newMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $existingStart->copy()->addMinutes(60)->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);
    }

    public function test_create_rejects_showtime_ending_after_existing_movie_starts(): void
    {
        $existingMovie = Movie::factory()->create(['duration' => 90]);
        $newMovie = Movie::factory()->create(['duration' => 120]);
        $screen = Screen::factory()->create();
        $existingStart = Carbon::now()->addDays(2)->setTime(15, 0);

        Showtime::factory()->create([
            'movie_id' => $existingMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $existingStart,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create([
            'movie_id' => $newMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $existingStart->copy()->subMinutes(60)->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);
    }

    public function test_adjacent_showtimes_are_allowed(): void
    {
        $firstMovie = Movie::factory()->create(['duration' => 120]);
        $secondMovie = Movie::factory()->create(['duration' => 90]);
        $screen = Screen::factory()->create();
        $firstStart = Carbon::now()->addDays(2)->startOfHour();

        Showtime::factory()->create([
            'movie_id' => $firstMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $firstStart,
        ]);

        $created = $this->service->create([
            'movie_id' => $secondMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $firstStart->copy()->addMinutes(120)->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);

        $this->assertTrue($created->exists);
        $this->assertSame(2, Showtime::query()->where('screen_id', $screen->id)->count());
    }

    public function test_same_time_is_allowed_on_different_screen(): void
    {
        $movie = Movie::factory()->create(['duration' => 120]);
        $firstScreen = Screen::factory()->create();
        $secondScreen = Screen::factory()->create();
        $scheduledAt = Carbon::now()->addDays(2)->startOfHour();

        Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $firstScreen->id,
            'scheduled_at' => $scheduledAt,
        ]);

        $created = $this->service->create([
            'movie_id' => $movie->id,
            'screen_id' => $secondScreen->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);

        $this->assertTrue($created->exists);
        $this->assertSame($secondScreen->id, $created->screen_id);
    }

    public function test_update_ignores_current_showtime_but_rejects_overlap_with_another(): void
    {
        $movie = Movie::factory()->create(['duration' => 120]);
        $screen = Screen::factory()->create();
        $firstStart = Carbon::now()->addDays(2)->setTime(10, 0);
        $secondStart = Carbon::now()->addDays(2)->setTime(14, 0);

        $first = Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $firstStart,
        ]);

        Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $secondStart,
        ]);

        $unchanged = $this->service->update($first->id, [
            'scheduled_at' => $firstStart->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame($firstStart->format('Y-m-d H:i:s'), $unchanged->scheduled_at->format('Y-m-d H:i:s'));

        $this->expectException(ValidationException::class);

        $this->service->update($first->id, [
            'scheduled_at' => $secondStart->copy()->subMinutes(60)->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_bulk_single_day_skips_overlapping_slots_deterministically(): void
    {
        $existingMovie = Movie::factory()->create(['duration' => 120]);
        $newMovie = Movie::factory()->create(['duration' => 90]);
        $screen = Screen::factory()->create();
        $date = Carbon::now()->addDays(2)->format('Y-m-d');

        Showtime::factory()->create([
            'movie_id' => $existingMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $date . ' 10:00:00',
        ]);

        $result = $this->service->bulkCreateSingleDay([
            'movie_id' => $newMovie->id,
            'date' => $date,
            'slots' => [
                ['screen_id' => $screen->id, 'time' => '11:00'],
                ['screen_id' => $screen->id, 'time' => '12:00'],
            ],
            'status' => 1,
        ]);

        $this->assertSame(['created' => 1, 'skipped' => 1], $result);
        $this->assertDatabaseHas('showtimes', [
            'screen_id' => $screen->id,
            'scheduled_at' => $date . ' 12:00:00',
        ]);
    }
}