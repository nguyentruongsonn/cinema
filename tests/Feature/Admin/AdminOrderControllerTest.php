<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Role;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_list_orders_across_users_with_bounded_pagination(): void
    {
        $admin = $this->userWithRole('admin');
        $firstUser = User::factory()->create(['name' => 'First Customer']);
        $secondUser = User::factory()->create(['name' => 'Second Customer']);
        $showtime = Showtime::factory()->create();
        Order::factory()->create(['user_id' => $firstUser->id, 'showtime_id' => $showtime->id]);
        Order::factory()->create(['user_id' => $secondUser->id, 'showtime_id' => $showtime->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders?per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'data' => [[
                        'id',
                        'code',
                        'total_amount',
                        'payment_status',
                        'user' => ['id', 'name', 'email', 'phone'],
                        'showtime' => ['id', 'scheduled_at', 'movie', 'screen'],
                        'items',
                    ]],
                ],
            ])
            ->assertJsonMissingPath('data.data.0.tickets')
            ->assertJsonMissingPath('data.data.0.payment');
    }

    #[Test]
    public function non_admin_cannot_list_admin_orders(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->getJson('/api/v1/admin/orders')
            ->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug]);

        return User::factory()->create(['role_id' => $role->id, 'status' => true]);
    }
}
