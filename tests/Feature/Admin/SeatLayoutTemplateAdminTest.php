<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
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
        $response->assertSee('data-bs-toggle="tab"', false);
        $response->assertSee('data-bs-target="#pane-table"', false);
    }
}
