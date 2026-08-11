<?php

namespace Tests\Feature\Showtime;

use App\Models\Movie;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\User;
use App\Policies\ShowtimePolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowtimeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_uses_seeded_create_showtimes_permission_slug(): void
    {
        $user = $this->makeUserWithPermissions(['create_showtimes']);
        $policy = new ShowtimePolicy();

        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->bulkCreate($user));
    }

    public function test_policy_uses_seeded_edit_showtimes_permission_slug(): void
    {
        $user = $this->makeUserWithPermissions(['edit_showtimes']);
        $showtime = Showtime::factory()->create();
        $policy = new ShowtimePolicy();

        $this->assertTrue($policy->update($user, $showtime));
    }

    public function test_policy_uses_seeded_delete_showtimes_permission_slug(): void
    {
        $user = $this->makeUserWithPermissions(['delete_showtimes']);
        $showtime = Showtime::factory()->create();
        $policy = new ShowtimePolicy();

        $this->assertTrue($policy->delete($user, $showtime));
    }

    public function test_policy_allows_admin_role_for_showtime_management(): void
    {
        $user = $this->makeUserWithRole('admin');
        $showtime = Showtime::factory()->create();
        $policy = new ShowtimePolicy();

        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->bulkCreate($user));
        $this->assertTrue($policy->update($user, $showtime));
        $this->assertTrue($policy->delete($user, $showtime));
    }

    public function test_showtime_list_defaults_to_upcoming_and_accepts_time_scope_filter(): void
    {
        $admin = $this->makeAdminUser();
        $pastShowtime = Showtime::factory()->create(['scheduled_at' => now()->subMinute()]);
        $upcomingShowtime = Showtime::factory()->create(['scheduled_at' => now()->addMinute()]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/showtimes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $upcomingShowtime->id)
            ->assertJsonPath('pagination.total', 1);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/showtimes?time_scope=past')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pastShowtime->id)
            ->assertJsonPath('pagination.total', 1);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/showtimes?time_scope=all')
            ->assertOk()
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_update_endpoint_accepts_admin_route_id_parameter_for_authorization(): void
    {
        $admin = $this->makeAdminUser();
        $theater = Theater::factory()->create();
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);
        $movie = Movie::factory()->create(['duration' => 120]);
        $currentStart = Carbon::now()->addDays(3)->setTime(10, 0);
        $updatedStart = Carbon::now()->addDays(3)->setTime(13, 0);

        $showtime = Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $currentStart,
        ]);

        $response = $this->actingAs($admin, 'api')->putJson("/api/v1/admin/showtimes/{$showtime->id}", [
            'scheduled_at' => $updatedStart->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('showtimes', [
            'id' => $showtime->id,
            'scheduled_at' => $updatedStart->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_store_endpoint_returns_validation_error_for_overlapping_showtime(): void
    {
        $admin = $this->makeAdminUser();
        $theater = Theater::factory()->create();
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);
        $existingMovie = Movie::factory()->create(['duration' => 120]);
        $newMovie = Movie::factory()->create(['duration' => 90]);
        $existingStart = Carbon::now()->addDays(4)->setTime(10, 0);

        Showtime::factory()->create([
            'movie_id' => $existingMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $existingStart,
        ]);

        $response = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/showtimes', [
            'movie_id' => $newMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => $existingStart->copy()->addHour()->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['scheduled_at']]);
    }

    public function test_bulk_single_day_endpoint_creates_non_conflicting_slots_and_skips_overlaps(): void
    {
        $admin = $this->makeAdminUser();
        $theater = Theater::factory()->create();
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);
        $existingMovie = Movie::factory()->create(['duration' => 120]);
        $newMovie = Movie::factory()->create(['duration' => 90]);
        $date = Carbon::now()->addDays(5)->toDateString();

        Showtime::factory()->create([
            'movie_id' => $existingMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => "{$date} 10:00:00",
        ]);

        $response = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/showtimes/bulk-single', [
            'movie_id' => $newMovie->id,
            'date' => $date,
            'slots' => [
                ['screen_id' => $screen->id, 'time' => '11:00'],
                ['screen_id' => $screen->id, 'time' => '12:00'],
            ],
            'status' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertDatabaseHas('showtimes', [
            'movie_id' => $newMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => "{$date} 12:00:00",
        ]);
    }

    public function test_bulk_date_range_endpoint_creates_non_conflicting_slots_and_skips_overlaps(): void
    {
        $admin = $this->makeAdminUser();
        $theater = Theater::factory()->create();
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);
        $existingMovie = Movie::factory()->create(['duration' => 120]);
        $newMovie = Movie::factory()->create(['duration' => 90]);
        $date = Carbon::now()->addDays(6)->toDateString();

        Showtime::factory()->create([
            'movie_id' => $existingMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => "{$date} 10:00:00",
        ]);

        $response = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/showtimes/bulk', [
            'movie_id' => $newMovie->id,
            'screen_id' => $screen->id,
            'date_from' => $date,
            'date_to' => $date,
            'times' => ['11:00', '12:00'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertDatabaseHas('showtimes', [
            'movie_id' => $newMovie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => "{$date} 12:00:00",
        ]);
    }

    private function makeAdminUser(): User
    {
        return $this->makeUserWithRole('admin');
    }

    private function makeUserWithRole(string $roleSlug): User
    {
        $role = Role::create([
            'name' => str($roleSlug)->headline()->toString(),
            'slug' => $roleSlug,
            'description' => "{$roleSlug} role",
        ]);

        $user = User::factory()->create();
        $user->assignRole($role->id);

        return $user->fresh();
    }

    /**
     * @param array<int, string> $permissionSlugs
     */
    private function makeUserWithPermissions(array $permissionSlugs): User
    {
        $role = Role::create([
            'name' => 'Showtime Staff',
            'slug' => 'showtime-staff-' . substr((string) str()->uuid(), 0, 8),
            'description' => 'Role for showtime permission testing',
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::create([
                'name' => $slug,
                'slug' => $slug,
                'group' => 'showtimes',
            ]);

            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create();
        $user->assignRole($role->id);

        return $user->fresh();
    }
}
