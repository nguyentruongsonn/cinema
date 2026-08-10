<?php

namespace Tests\Feature;

use App\Models\LoyaltyHistory;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PosZeroAmountCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_api_resolves_registered_customer_by_customer_id(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $customerRole = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $permission = Permission::firstOrCreate(
            ['slug' => 'orders.create'],
            ['name' => 'Create orders', 'group' => 'orders']
        );
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);

        $staff = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->create([
            'role_id' => $customerRole->id,
            'name' => 'Khách đã đăng ký',
            'phone' => '0987654321',
            'loyalty_points' => 100,
        ]);
        $theater = Theater::forceCreate(['name' => 'Rạp API POS', 'address' => 'Hà Nội']);
        $product = Product::createManaged([
            'name' => 'Bắp API POS',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 10,
            'status' => 1,
        ]);

        $response = $this->actingAs($staff, 'api')->postJson('/api/v1/pos/orders', [
            'theater_id' => $theater->id,
            'products' => [[
                'id' => $product->id,
                'type' => 'product',
                'quantity' => 1,
            ]],
            'customer_id' => $customer->id,
            'customer_phone' => $customer->phone,
            'customer_name' => $customer->name,
            'customer_mode' => 'member',
            'payment_method' => 'cash',
            'loyalty_points_to_use' => 50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.customer_name', $customer->name)
            ->assertJsonPath('data.customer_phone', $customer->phone)
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.points_used', 50)
            ->assertJsonPath('data.point_discount', 50000)
            ->assertJsonPath('data.total_amount', 0);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame(50, (int) $customer->fresh()->loyalty_points);
    }

    public function test_non_zero_cash_order_does_not_create_a_payos_checkout(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $customerRole = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $staff = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->create(['role_id' => $customerRole->id]);
        $theater = Theater::forceCreate(['name' => 'Rạp tiền mặt', 'address' => 'Hà Nội']);
        $product = Product::createManaged([
            'name' => 'Nước POS',
            'type' => Product::TYPE_DRINK,
            'price' => 50000,
            'stock' => 10,
            'status' => 1,
        ]);

        $this->actingAs($staff);
        $order = app(PosOrderService::class)->createPosOrder([
            'theater_id' => $theater->id,
            'products' => [[
                'id' => $product->id,
                'type' => 'product',
                'quantity' => 1,
            ]],
            'customer_id' => $customer->id,
            'customer_mode' => 'member',
            'payment_method' => 'cash',
            'loyalty_points_to_use' => 0,
        ], $staff, $customer);

        $this->assertTrue($order->isPending());
        $this->assertFalse($order->isPaid());
        $this->assertNull($order->checkout_url);
        $this->assertSame('internal', $order->payment_provider);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('cash', $order->payment->method);

        $confirmed = app(PosOrderService::class)->confirmCashPayment($order, $staff, $customer);

        $this->assertTrue($confirmed->isPaid());
        $this->assertSame('cash', $confirmed->payment->method);
    }

    public function test_cash_received_creates_and_confirms_pos_order_in_one_request(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        foreach ([
            'orders.create' => 'orders',
            'payments.process_cash' => 'payments',
        ] as $slug => $group) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => $group]
            );
            $adminRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $staff = User::factory()->create(['role_id' => $adminRole->id]);
        $theater = Theater::forceCreate(['name' => 'Rạp xác nhận nhanh', 'address' => 'Hà Nội']);
        $product = Product::createManaged([
            'name' => 'Nước xác nhận nhanh',
            'type' => Product::TYPE_DRINK,
            'price' => 50000,
            'stock' => 10,
            'status' => 1,
        ]);

        $response = $this->actingAs($staff, 'api')->postJson('/api/v1/pos/orders', [
            'theater_id' => $theater->id,
            'products' => [[
                'id' => $product->id,
                'type' => 'product',
                'quantity' => 1,
            ]],
            'customer_mode' => 'guest',
            'payment_method' => 'cash',
            'cash_received' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.requires_payment', false);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertTrue($order->isPaid());
        $this->assertSame('cash', $order->payment->method);
    }

    public function test_zero_amount_pos_order_keeps_customer_payment_and_ticket_context(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $customerRole = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $staff = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->create([
            'role_id' => $customerRole->id,
            'name' => 'Khách Thành Viên',
            'phone' => '0912345678',
            'loyalty_points' => 1000,
        ]);
        $theater = Theater::forceCreate(['name' => 'Rạp POS', 'address' => 'Hà Nội']);
        $screen = Screen::forceCreate([
            'name' => 'Phòng POS',
            'code' => 'POS-01',
            'theater_id' => $theater->id,
            'capacity' => 100,
        ]);
        $movie = Movie::forceCreate([
            'title' => 'Phim POS',
            'slug' => 'phim-pos',
            'duration' => 120,
        ]);
        $showtime = Showtime::forceCreate([
            'scheduled_at' => now()->addHours(2),
            'screen_id' => $screen->id,
            'movie_id' => $movie->id,
            'status' => 1,
        ]);
        $seat = Seat::factory()->create(['screen_id' => $showtime->screen_id]);
        $product = Product::createManaged([
            'name' => 'Bắp POS',
            'type' => Product::TYPE_FOOD,
            'price' => 100000,
            'stock' => 10,
            'status' => 1,
        ]);

        $hold = SeatHold::create([
            'user_id' => $staff->id,
            'showtime_id' => $showtime->id,
            'held_until' => now()->addMinutes(10),
        ]);
        SeatHoldItem::create([
            'seat_hold_id' => $hold->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'status' => SeatHoldItem::STATUS_ACTIVE,
            'active_lock_key' => SeatHoldItem::activeLockKey($showtime->id, $seat->id),
            'expires_at' => $hold->held_until,
        ]);

        $this->actingAs($staff);
        $order = app(PosOrderService::class)->createPosOrder([
            'showtime_id' => $showtime->id,
            'seat_ids' => [$seat->id],
            'tickets' => [[
                'seat_id' => $seat->id,
                'audience_type' => 'adult',
                'student_card_verified' => false,
            ]],
            'products' => [[
                'id' => $product->id,
                'type' => 'product',
                'quantity' => 1,
            ]],
            'customer_id' => $customer->id,
            'customer_phone' => $customer->phone,
            'customer_name' => $customer->name,
            'customer_mode' => 'member',
            'payment_method' => 'cash',
            'loyalty_points_to_use' => 1000,
        ], $staff, $customer);

        $details = app(PosOrderService::class)->getPosOrderDetails($order);
        $pointsUsed = (int) data_get($order->payload, 'points_used');

        $this->assertTrue($order->isPaid());
        $this->assertStringStartsWith('POS-', $order->code);
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('cash', $order->payment->method);
        $this->assertSame(0.0, (float) $order->total_amount);
        $this->assertGreaterThan(0, $pointsUsed);
        $this->assertSame(1000 - $pointsUsed, (int) $customer->fresh()->loyalty_points);
        $this->assertSame(1, $order->tickets()->count());
        $this->assertTrue(LoyaltyHistory::query()
            ->where('order_id', $order->id)
            ->where('user_id', $customer->id)
            ->where('type', 'redeem')
            ->where('points', $pointsUsed)
            ->exists());

        $this->assertFalse($details['requires_payment']);
        $this->assertSame('paid', $details['payment_status']);
        $this->assertSame('cash', $details['payment_method']);
        $this->assertSame($customer->name, $details['customer_name']);
        $this->assertSame($customer->phone, $details['customer_phone']);
        $this->assertSame($showtime->movie->title, $details['movie_title']);
        $this->assertSame($showtime->movie->duration, $details['movie_duration']);
        $this->assertSame($showtime->screen->theater->name, $details['theater_name']);
        $this->assertSame($showtime->screen->name, $details['screen_name']);
        $this->assertNotNull($details['showtime']);
        $this->assertCount(1, $details['seats']);
        $this->assertSame($pointsUsed, $details['points_used']);
        $this->assertSame((float) $details['subtotal'], (float) $details['point_discount']);
    }

    public function test_cancelling_pending_pos_order_restores_reserved_points_and_stock(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $customerRole = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $staff = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = User::factory()->create([
            'role_id' => $customerRole->id,
            'loyalty_points' => 100,
        ]);
        $theater = Theater::forceCreate(['name' => 'Rạp hoàn tài nguyên', 'address' => 'Hà Nội']);
        $product = Product::createManaged([
            'name' => 'Sản phẩm hoàn tồn',
            'type' => Product::TYPE_DRINK,
            'price' => 50000,
            'stock' => 10,
            'status' => 1,
        ]);

        $this->actingAs($staff);
        $order = app(PosOrderService::class)->createPosOrder([
            'theater_id' => $theater->id,
            'products' => [[
                'id' => $product->id,
                'type' => 'product',
                'quantity' => 1,
            ]],
            'customer_id' => $customer->id,
            'customer_mode' => 'member',
            'payment_method' => 'cash',
            'loyalty_points_to_use' => 10,
        ], $staff, $customer);

        $this->assertTrue($order->isPending());
        $this->assertSame(90, (int) $customer->fresh()->loyalty_points);
        $this->assertSame(9, (int) $product->fresh()->stock);
        $this->assertTrue(LoyaltyHistory::query()->where('order_id', $order->id)->exists());

        $cancelled = app(OrderService::class)->cancel($order->id, $staff);

        $this->assertTrue($cancelled->isCancelled());
        $this->assertSame(100, (int) $customer->fresh()->loyalty_points);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertFalse(LoyaltyHistory::query()->where('order_id', $order->id)->exists());
        $this->assertFalse((bool) data_get($cancelled->payload, 'points_reserved'));
        $this->assertFalse((bool) data_get($cancelled->payload, 'product_stock_reserved'));
    }
}
