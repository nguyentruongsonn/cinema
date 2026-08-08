<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Theater;
use App\Models\User;
use App\Http\Requests\Pos\PosCreateOrderRequest;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_seller_can_manage_pos_order_in_assigned_theater(): void
    {
        $theater = Theater::forceCreate([
            'name' => 'Rạp POS 1',
            'address' => 'Hà Nội',
        ]);
        $actor = $this->makeActor($theater);
        $order = $this->makePosOrder($actor, $theater->id);
        $policy = new OrderPolicy();

        $this->assertTrue($policy->viewAtPos($actor, $order));
        $this->assertTrue($policy->cancelAtPos($actor, $order));
        $this->assertTrue($policy->confirmCash($actor, $order));
    }

    public function test_legacy_payment_permission_can_confirm_cash_at_pos(): void
    {
        $theater = Theater::forceCreate([
            'name' => 'Rạp POS legacy',
            'address' => 'Hà Nội',
        ]);
        $role = Role::create([
            'name' => 'Legacy POS Seller ' . Str::random(8),
            'slug' => 'ticket_seller',
        ]);
        foreach (['orders.view_theater', 'orders.cancel', 'payments.process'] as $slug) {
            $permission = Permission::create([
                'name' => $slug,
                'slug' => $slug,
                'group' => Str::beforeLast($slug, '.'),
            ]);
            $role->permissions()->attach($permission->id);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role->id);
        $actor->theaters()->attach($theater->id);
        $order = $this->makePosOrder($actor, $theater->id);

        $this->assertTrue((new OrderPolicy())->confirmCash($actor->fresh('role.permissions'), $order));
    }

    public function test_ticket_seller_cannot_manage_pos_order_in_another_theater(): void
    {
        $assignedTheater = Theater::forceCreate([
            'name' => 'Rạp được phân công',
            'address' => 'Hà Nội',
        ]);
        $otherTheater = Theater::forceCreate([
            'name' => 'Rạp ngoài phạm vi',
            'address' => 'Hà Nội',
        ]);
        $actor = $this->makeActor($assignedTheater);
        $order = $this->makePosOrder($actor, $otherTheater->id);
        $policy = new OrderPolicy();

        $this->assertFalse($policy->viewAtPos($actor, $order));
        $this->assertFalse($policy->cancelAtPos($actor, $order));
        $this->assertFalse($policy->confirmCash($actor, $order));
    }

    public function test_non_pos_order_is_not_manageable_through_pos_policy(): void
    {
        $theater = Theater::forceCreate([
            'name' => 'Rạp online',
            'address' => 'Hà Nội',
        ]);
        $actor = $this->makeActor($theater);
        $order = Order::forceCreate([
            'code' => 'ORD-ONLINE-1',
            'gateway_order_code' => 100001,
            'user_id' => $actor->id,
            'showtime_id' => null,
            'total_amount' => 100000,
            'payment_status' => 'pending',
            'status' => Order::STATUS_PENDING,
            'payload' => ['source' => 'web'],
        ]);

        $this->assertFalse((new OrderPolicy())->viewAtPos($actor, $order));
    }

    public function test_pending_order_factory_accepts_product_only_pos_order_without_showtime(): void
    {
        $actor = User::factory()->create();

        $order = Order::createPending([
            'code' => 'POS-' . Str::upper(Str::random(10)),
            'gateway_order_code' => 100002,
            'payment_provider' => 'payos',
            'user_id' => $actor->id,
            'showtime_id' => null,
            'total_amount' => 50000,
            'payload' => [
                'source' => 'pos',
                'theater_id' => 1,
            ],
            'expired_at' => now()->addMinutes(15),
        ]);

        $this->assertNull($order->showtime_id);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
    }

    public function test_pos_order_request_accepts_the_order_permission_without_booking_permission(): void
    {
        $role = Role::create([
            'name' => 'Concession POS seller',
            'slug' => 'ticket_seller',
        ]);
        $permission = Permission::create([
            'name' => 'Create orders',
            'slug' => 'orders.create',
            'group' => 'orders',
        ]);
        $role->permissions()->attach($permission->id);
        $actor = User::factory()->create(['role_id' => $role->id])->fresh('role.permissions');

        $request = new PosCreateOrderRequest();
        $request->setUserResolver(fn () => $actor);

        $this->assertTrue($request->authorize());
    }

    public function test_product_only_pos_payload_does_not_require_a_showtime_or_seat(): void
    {
        $theater = Theater::forceCreate([
            'name' => 'Rạp bán bắp nước',
            'address' => 'Hà Nội',
        ]);
        $payload = [
            'theater_id' => $theater->id,
            'products' => [['id' => 1, 'type' => 'product', 'quantity' => 2]],
            'customer_mode' => 'guest',
            'payment_method' => 'cash',
        ];
        $request = PosCreateOrderRequest::create('/api/v1/pos/orders', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    public function test_admin_can_open_the_pos_without_a_theater_assignment(): void
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->get('/pos')->assertOk();
    }

    private function makeActor(Theater $theater): User
    {
        $role = Role::create([
            'name' => 'POS Seller ' . Str::random(8),
            'slug' => 'ticket_seller',
            'description' => 'POS authorization test role',
        ]);

        foreach (['orders.view_theater', 'orders.cancel', 'payments.process_cash'] as $slug) {
            $permission = Permission::create([
                'name' => $slug,
                'slug' => $slug,
                'group' => Str::beforeLast($slug, '.'),
            ]);
            $role->permissions()->attach($permission->id);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role->id);
        $actor->theaters()->attach($theater->id);

        return $actor->fresh('role.permissions', 'theaters');
    }

    private function makePosOrder(User $actor, int $theaterId): Order
    {
        return Order::forceCreate([
            'code' => 'POS-' . Str::upper(Str::random(10)),
            'gateway_order_code' => random_int(100000, 999999),
            'user_id' => $actor->id,
            'showtime_id' => null,
            'total_amount' => 100000,
            'payment_status' => 'pending',
            'status' => Order::STATUS_PENDING,
            'payload' => [
                'source' => 'pos',
                'theater_id' => $theaterId,
                'payment_method' => 'cash',
            ],
        ]);
    }
}
