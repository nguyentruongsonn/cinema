<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Theater;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcessionFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_concession_staff_can_fulfill_a_paid_pos_item_once(): void
    {
        [$staff, $theater] = $this->makeStaff();
        $customer = User::factory()->create();
        $product = Product::forceCreate([
            'name' => 'Bắp rang',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 10,
            'status' => true,
        ]);
        $order = Order::forceCreate([
            'code' => 'POS-CONCESSION-1',
            'gateway_order_code' => 920001,
            'payment_provider' => 'internal',
            'user_id' => $customer->id,
            'theater_id' => $theater->id,
            'source' => 'pos',
            'payment_method' => 'cash',
            'total_amount' => 50000,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'payload' => ['source' => 'pos', 'theater_id' => $theater->id],
        ]);
        $item = OrderItem::forceCreate([
            'order_id' => $order->id,
            'item_type' => Product::class,
            'item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'total_price' => 50000,
            'fulfillment_status' => OrderItem::FULFILLMENT_PENDING,
        ]);

        $this->actingAs($staff, 'api')
            ->getJson('/api/v1/staff/concessions/orders/pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id);

        $this->actingAs($staff, 'api')
            ->postJson("/api/v1/staff/concessions/items/{$item->id}/fulfill")
            ->assertOk()
            ->assertJsonPath('data.fulfillment_status', OrderItem::FULFILLMENT_FULFILLED);

        $this->actingAs($staff, 'api')
            ->postJson("/api/v1/staff/concessions/items/{$item->id}/fulfill")
            ->assertOk();

        $this->assertSame(OrderItem::FULFILLMENT_FULFILLED, $item->fresh()->fulfillment_status);
        $this->assertSame($staff->id, $item->fresh()->fulfilled_by_user_id);
    }

    public function test_concession_staff_cannot_fulfill_another_theaters_item(): void
    {
        [$staff, $assignedTheater] = $this->makeStaff();
        $otherTheater = Theater::forceCreate(['name' => 'Rạp khác', 'address' => 'Hà Nội']);
        $customer = User::factory()->create();
        $product = Product::forceCreate(['name' => 'Nước', 'type' => Product::TYPE_DRINK, 'price' => 20000, 'stock' => 5, 'status' => true]);
        $order = Order::forceCreate([
            'code' => 'POS-CONCESSION-2', 'gateway_order_code' => 920002, 'payment_provider' => 'internal',
            'user_id' => $customer->id, 'theater_id' => $otherTheater->id, 'source' => 'pos',
            'payment_method' => 'cash', 'total_amount' => 20000, 'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid', 'payload' => ['source' => 'pos', 'theater_id' => $otherTheater->id],
        ]);
        $item = OrderItem::forceCreate([
            'order_id' => $order->id, 'item_type' => Product::class, 'item_id' => $product->id,
            'quantity' => 1, 'unit_price' => 20000, 'total_price' => 20000,
            'fulfillment_status' => OrderItem::FULFILLMENT_PENDING,
        ]);

        $this->actingAs($staff, 'api')
            ->postJson("/api/v1/staff/concessions/items/{$item->id}/fulfill")
            ->assertForbidden();
        $this->assertSame(OrderItem::FULFILLMENT_PENDING, $item->fresh()->fulfillment_status);
        $this->assertNotSame($assignedTheater->id, $otherTheater->id);
    }

    private function makeStaff(): array
    {
        $theater = Theater::forceCreate(['name' => 'Rạp concession', 'address' => 'Hà Nội']);
        $role = Role::create(['name' => 'Concession staff', 'slug' => 'concession_staff']);
        $permission = Permission::create(['name' => 'Fulfill concessions', 'slug' => 'concessions.fulfill', 'group' => 'concessions']);
        $role->permissions()->attach($permission->id);
        $staff = User::factory()->create(['role_id' => $role->id]);
        $staff->theaters()->attach($theater->id);

        return [$staff->fresh('role.permissions', 'theaters'), $theater];
    }
}
