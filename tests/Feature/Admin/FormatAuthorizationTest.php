<?php

namespace Tests\Feature\Admin;

use App\Models\Format;
use App\Models\Role;
use App\Models\Sound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormatAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_theater_manager_cannot_manage_global_formats(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'Theater Manager',
            'slug' => 'theater_manager',
        ]);
        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        $format = Format::query()->create(['name' => '2D', 'surcharge' => 0]);

        $this->actingAs($manager, 'api')
            ->postJson('/api/v1/admin/formats', ['name' => 'IMAX', 'surcharge' => 50000])
            ->assertForbidden();

        $this->actingAs($manager, 'api')
            ->putJson("/api/v1/admin/formats/{$format->id}", ['name' => '4DX', 'surcharge' => 75000])
            ->assertForbidden();

        $this->actingAs($manager, 'api')
            ->deleteJson("/api/v1/admin/formats/{$format->id}")
            ->assertForbidden();
    }

    public function test_admin_can_manage_global_formats(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $created = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/formats', ['name' => 'IMAX', 'surcharge' => 50000])
            ->assertOk()
            ->assertJsonPath('success', true);

        $format = Format::query()->findOrFail($created->json('data.id'));

        $this->actingAs($admin, 'api')
            ->putJson("/api/v1/admin/formats/{$format->id}", ['name' => 'IMAX Laser', 'surcharge' => 60000])
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->deleteJson("/api/v1/admin/formats/{$format->id}")
            ->assertOk();

        $this->assertDatabaseMissing('formats', ['id' => $format->id]);
    }

    public function test_theater_manager_cannot_manage_global_sounds(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'Theater Manager',
            'slug' => 'theater_manager',
        ]);
        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        $sound = Sound::query()->create(['name' => 'Dolby Atmos']);

        $this->actingAs($manager, 'api')
            ->postJson('/api/v1/admin/sounds', ['name' => 'DTS:X'])
            ->assertForbidden();

        $this->actingAs($manager, 'api')
            ->putJson("/api/v1/admin/sounds/{$sound->id}", ['name' => 'Dolby Cinema'])
            ->assertForbidden();

        $this->actingAs($manager, 'api')
            ->deleteJson("/api/v1/admin/sounds/{$sound->id}")
            ->assertForbidden();
    }
}
