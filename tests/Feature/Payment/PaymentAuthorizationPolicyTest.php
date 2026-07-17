<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PaymentAuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_boolean_status_user_can_create_order_and_payment(): void
    {
        $user = User::factory()->create(['status' => 1])->fresh();

        $this->assertTrue($user->status);
        $this->assertTrue(Gate::forUser($user)->allows('create', Order::class));
        $this->assertTrue((new PaymentPolicy())->create($user));
    }

    public function test_inactive_boolean_status_user_cannot_create_order_or_payment(): void
    {
        $user = User::factory()->create(['status' => 0])->fresh();

        $this->assertFalse($user->status);
        $this->assertFalse(Gate::forUser($user)->allows('create', Order::class));
        $this->assertFalse((new PaymentPolicy())->create($user));
    }
}
