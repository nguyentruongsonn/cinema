<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SeatLayoutTemplate;
use App\Models\Sound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatLayoutTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_seat_layout_template_index_page(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);
        $user->refresh();

        $this->actingAs($user);

        $response = $this->get('/admin/seat-layout-templates');

        $response->assertStatus(200);
        $response->assertSee('Quản lý mẫu sơ đồ ghế');
        $response->assertSee('id="templatesTableBody"', false);
        $response->assertSee('id="statusFilter"', false);
    }

    public function test_admin_list_returns_all_status_counts_in_one_response(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);

        SeatLayoutTemplate::query()->create([
            'template_name' => 'Published layout',
            'seat_matrix' => '5x5',
            'status' => true,
        ]);
        SeatLayoutTemplate::query()->create([
            'template_name' => 'Draft layout',
            'seat_matrix' => '4x4',
            'status' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/seat-layout-templates?status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('counts.all', 2)
            ->assertJsonPath('counts.published', 1)
            ->assertJsonPath('counts.draft', 1);
    }

    public function test_screen_list_only_includes_reference_data_when_requested(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/screens')
            ->assertOk()
            ->assertJsonMissingPath('formats')
            ->assertJsonMissingPath('theaters')
            ->assertJsonMissingPath('templates');

        $this->actingAs($user)
            ->getJson('/api/v1/admin/screens?include_references=1')
            ->assertOk()
            ->assertJsonStructure([
                'screens',
                'formats',
                'sounds',
                'version_types',
                'theaters',
                'templates',
            ]);
    }

    public function test_admin_can_manage_sound_formats(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/admin/sounds', ['name' => 'Dolby Atmos']);

        $createResponse->assertCreated()->assertJsonPath('success', true);
        $sound = Sound::query()->where('name', 'Dolby Atmos')->firstOrFail();

        $this->actingAs($user)
            ->putJson("/api/v1/admin/sounds/{$sound->id}", ['name' => 'Dolby Atmos 7.1'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Dolby Atmos 7.1');

        $this->actingAs($user)
            ->deleteJson("/api/v1/admin/sounds/{$sound->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('sounds', ['id' => $sound->id]);
    }

    public function test_admin_can_create_and_update_screen_with_nullable_format_and_sound(): void
    {
        $this->seed(\Database\Seeders\SeatTypeSeeder::class);

        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);

        $theater = \App\Models\Theater::factory()->create();
        $template = \App\Models\SeatLayoutTemplate::query()->create([
            'template_name' => 'Test Standard Matrix',
            'seat_matrix' => '5x5',
            'regular_seat_rows' => 3,
            'vip_seat_rows' => 2,
            'couple_seat_rows' => 0,
            'status' => true,
        ]);

        $screenData = [
            'theater_id' => $theater->id,
            'name' => 'Test Screen Nullable',
            'code' => 'TSN01',
            'seat_layout_template_id' => $template->id,
            'format_id' => null,
            'sound_id' => null,
            'status' => 1,
        ];

        // 1. Create screen
        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/admin/screens', $screenData);

        $createResponse->assertOk()->assertJsonPath('success', true);

        $screen = \App\Models\Screen::where('name', 'Test Screen Nullable')->firstOrFail();
        $screenId = $screen->id;

        $this->assertDatabaseHas('screens', [
            'id' => $screenId,
            'format_id' => null,
            'sound_id' => null,
        ]);

        // 2. Update screen
        $updateData = array_merge($screenData, [
            'name' => 'Updated Test Screen Nullable',
            'code' => 'TSN02',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/admin/screens/{$screenId}", $updateData)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('screens', [
            'id' => $screenId,
            'name' => 'Updated Test Screen Nullable',
            'format_id' => null,
            'sound_id' => null,
        ]);
    }

    public function test_admin_can_create_screen_with_legacy_seat_matrix_format(): void
    {
        $this->seed(\Database\Seeders\SeatTypeSeeder::class);

        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);

        $theater = \App\Models\Theater::factory()->create();
        $template = \App\Models\SeatLayoutTemplate::query()->create([
            'template_name' => 'Legacy Template',
            'seat_matrix' => 'A=11111, B=11111, C=11111, D=11111',
            'regular_seat_rows' => 4,
            'vip_seat_rows' => 0,
            'couple_seat_rows' => 0,
            'status' => true,
        ]);

        $screenData = [
            'theater_id' => $theater->id,
            'name' => 'Test Screen Legacy Matrix',
            'code' => 'TSL01',
            'seat_layout_template_id' => $template->id,
            'format_id' => null,
            'sound_id' => null,
            'status' => 1,
        ];

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/admin/screens', $screenData);

        $createResponse->assertOk()->assertJsonPath('success', true);

        $screen = \App\Models\Screen::where('name', 'Test Screen Legacy Matrix')->firstOrFail();

        $this->assertDatabaseHas('screens', [
            'id' => $screen->id,
            'capacity' => 20, // 4 rows * 5 cols
        ]);
    }

    public function test_updating_screen_seats_updates_capacity(): void
    {
        $this->seed(\Database\Seeders\SeatTypeSeeder::class);

        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $user->assignRole($role->id);

        $theater = \App\Models\Theater::factory()->create();
        $template = \App\Models\SeatLayoutTemplate::query()->create([
            'template_name' => 'Test Capacity Template',
            'seat_matrix' => '3x3',
            'regular_seat_rows' => 3,
            'vip_seat_rows' => 0,
            'couple_seat_rows' => 0,
            'status' => true,
        ]);

        $screen = \App\Models\Screen::create([
            'theater_id' => $theater->id,
            'name' => 'Screen Capacity Test',
            'code' => 'SCT01',
            'seat_layout_template_id' => $template->id,
            'capacity' => 9,
        ]);

        // Seed 9 seats
        $seatType = \App\Models\SeatType::first();
        $seats = [];
        for ($i = 0; $i < 9; $i++) {
            $seats[] = \App\Models\Seat::create([
                'screen_id' => $screen->id,
                'seat_type_id' => $seatType->id,
                'row' => 'A',
                'number' => (string)($i + 1),
                'row_index' => intdiv($i, 3),
                'column_index' => $i % 3,
                'label' => 'A' . ($i + 1),
                'status' => 1,
            ]);
        }

        // Disable 2 seats
        $payload = [
            'seats' => [
                ['id' => $seats[0]->id, 'status' => false],
                ['id' => $seats[1]->id, 'status' => false],
            ]
        ];

        $this->actingAs($user)
            ->postJson("/api/v1/admin/screens/{$screen->id}/seats/update", $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        // Verify capacity is updated to 7 (9 - 2)
        $screen->refresh();
        $this->assertEquals(7, $screen->capacity);
    }
}
