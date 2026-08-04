<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function development_users_receive_the_expected_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame('admin', User::where('email', 'admin@example.com')->firstOrFail()->role?->slug);
        $this->assertSame('customer', User::where('email', 'test@example.com')->firstOrFail()->role?->slug);
    }
}
