<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OrderFulfillmentService;
use App\Services\PosCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCustomerLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
    }

    private function createShowtime(): Showtime
    {
        $theater = Theater::forceCreate(['name' => 'Rạp 1', 'address' => 'HN']);
        $screen = Screen::forceCreate(['name' => 'Phòng 1', 'code' => 'SCR1', 'theater_id' => $theater->id, 'capacity' => 100]);
        $movie = Movie::forceCreate(['title' => 'Phim 1', 'slug' => 'phim-1', 'duration' => 120]);

        return Showtime::forceCreate([
            'scheduled_at' => now()->addHour(),
            'screen_id' => $screen->id,
            'movie_id' => $movie->id,
            'status' => 1,
        ]);
    }

    public function test_pos_walk_in_customer_created_as_unclaimed_without_fake_credentials(): void
    {
        $service = app(PosCustomerService::class);
        $customer = $service->createWalkInCustomer('0912345678', 'Nguyễn Văn POS');

        $this->assertEquals('Nguyễn Văn POS', $customer->name);
        $this->assertEquals('0912345678', $customer->phone);
        $this->assertNull($customer->email);
        $this->assertNull($customer->password);
        $this->assertEquals('unclaimed', $customer->account_status);
        $this->assertEquals(0, $customer->loyalty_points);
    }

    public function test_lookup_matches_legacy_customer_role_and_international_phone_format(): void
    {
        $legacyRole = Role::firstOrCreate(['slug' => 'user'], ['name' => 'Legacy user']);
        $customer = User::factory()->create([
            'role_id' => $legacyRole->id,
            'phone' => '84912345678',
        ]);

        $found = app(PosCustomerService::class)->lookupByPhone('0912345678');

        $this->assertNotNull($found);
        $this->assertSame($customer->id, $found->id);
    }

    public function test_system_guest_customer_is_stable_per_theater_and_has_no_loyalty(): void
    {
        $theater = Theater::forceCreate(['name' => 'Rạp khách vãng lai', 'address' => 'HN']);
        $service = app(PosCustomerService::class);

        $first = $service->resolveGuestCustomer($theater->id);
        $second = $service->resolveGuestCustomer($theater->id);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->isSystemGuest());
        $this->assertSame('system_guest', $first->account_status);
        $this->assertNull($first->phone);
        $this->assertFalse($service->getLoyaltyInfo($first)['can_redeem']);
    }

    public function test_loyalty_points_earned_and_history_recorded_on_order_fulfillment(): void
    {
        $showtime = $this->createShowtime();
        $service = app(PosCustomerService::class);
        $customer = $service->createWalkInCustomer('0912345678', 'Khách Tích Điểm');

        $order = Order::forceCreate([
            'code' => 'POS-TEST-1234',
            'user_id' => $customer->id,
            'showtime_id' => $showtime->id,
            'gateway_order_code' => 9999991,
            'total_amount' => 100000,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'payload' => ['source' => 'pos'],
        ]);

        $fulfillment = app(OrderFulfillmentService::class);
        $fulfillment->finalize(9999991);

        $customer->refresh();
        $this->assertEquals(10, $customer->loyalty_points);

        $this->assertDatabaseHas('loyalty_histories', [
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'earn',
            'points' => 10,
        ]);
    }

    public function test_unclaimed_pos_customer_claimed_on_online_registration(): void
    {
        $service = app(PosCustomerService::class);
        $posCustomer = $service->createWalkInCustomer('0987654321', 'Khách Hàng POS');

        // Simulate order points accumulated at POS
        $posCustomer->increment('loyalty_points', 50);

        // Online registration using the same phone number
        $authService = app(AuthService::class);
        $authService->register([
            'name' => 'Khách Hàng Online',
            'email' => 'khachhang@gmail.com',
            'phone' => '0987654321',
            'password' => 'Password123!',
        ], '127.0.0.1');

        $claimedUser = User::find($posCustomer->id);

        $this->assertEquals($posCustomer->id, $claimedUser->id);
        $this->assertEquals('Khách Hàng Online', $claimedUser->name);
        $this->assertEquals('khachhang@gmail.com', $claimedUser->email);
        $this->assertEquals('claimed', $claimedUser->account_status);
        $this->assertEquals(50, $claimedUser->loyalty_points); // Points retained!
    }
}
