<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Showtime;
use App\Models\Theater;
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
        $olderOrder = Order::factory()->create([
            'user_id' => $firstUser->id,
            'showtime_id' => $showtime->id,
            'created_at' => now()->subHour(),
        ]);
        $newerOrder = Order::factory()->create([
            'user_id' => $secondUser->id,
            'showtime_id' => $showtime->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders?per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.data.0.id', $newerOrder->id)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'data' => [[
                        'id',
                        'code',
                        'total_amount',
                        'payment_status',
                        'payment_method',
                        'payment_provider',
                        'source',
                        'user' => ['id', 'name', 'email', 'phone'],
                        'showtime' => ['id', 'scheduled_at', 'movie', 'screen'],
                        'items',
                    ]],
                ],
            ])
            ->assertJsonMissingPath('data.data.0.tickets')
            ->assertJsonMissingPath('data.data.0.payment');

        $this->assertNotSame($olderOrder->id, $response->json('data.data.0.id'));
    }

    #[Test]
    public function non_admin_cannot_list_admin_orders(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->getJson('/api/v1/admin/orders')
            ->assertForbidden();
    }

    #[Test]
    public function theater_manager_without_order_permissions_cannot_access_admin_orders(): void
    {
        $manager = $this->userWithRole('theater_manager');
        $order = Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'showtime_id' => Showtime::factory()->create()->id,
        ]);

        $this->actingAs($manager, 'api')
            ->getJson('/api/v1/admin/orders')
            ->assertForbidden();

        $this->actingAs($manager, 'api')
            ->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertForbidden();
    }

    #[Test]
    public function theater_staff_can_list_product_only_pos_orders_for_their_theater(): void
    {
        $manager = $this->userWithRole('theater_manager');
        $permission = Permission::create([
            'name' => 'Xem đơn hàng theo rạp',
            'slug' => 'orders.view_theater',
            'group' => 'orders',
        ]);
        $manager->role->permissions()->attach($permission);
        $theater = Theater::forceCreate(['name' => 'Rạp phụ trách', 'address' => 'Hà Nội']);
        $manager->theaters()->attach($theater);
        $order = Order::factory()->create([
            'showtime_id' => null,
            'theater_id' => $theater->id,
            'source' => 'pos',
        ]);

        $this->actingAs($manager, 'api')
            ->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $order->id)
            ->assertJsonPath('data.data.0.theater.id', $theater->id);
    }

    #[Test]
    public function admin_order_list_expires_overdue_pending_orders_before_returning_data(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'paid_at' => null,
            'expired_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/orders?status=expired');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $order->id)
            ->assertJsonPath('data.data.0.payment_status', 'expired');

        $this->assertSame(Order::STATUS_CANCELLED, (int) $order->fresh()->status);
        $this->assertSame('expired', $order->fresh()->payment_status);
    }

    #[Test]
    public function admin_order_detail_expires_overdue_pending_order_before_returning_data(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'paid_at' => null,
            'expired_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'expired');

        $this->assertSame(Order::STATUS_CANCELLED, (int) $order->fresh()->status);
        $this->assertSame('expired', $order->fresh()->payment_status);
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug]);

        return User::factory()->create(['role_id' => $role->id, 'status' => true]);
    }
}
