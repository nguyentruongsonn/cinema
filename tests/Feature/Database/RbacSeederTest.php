<?php

namespace Tests\Feature\Database;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Theater;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_cinema_roles_and_permissions_from_catalog(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        foreach (array_keys(config('rbac.roles')) as $roleSlug) {
            $this->assertDatabaseHas('roles', ['slug' => $roleSlug]);
        }

        foreach (['movies.view', 'tickets.verify', 'concessions.fulfill', 'dashboard.view'] as $permissionSlug) {
            $this->assertDatabaseHas('permissions', ['slug' => $permissionSlug]);
        }
    }

    #[Test]
    public function admin_receives_every_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = Role::where('slug', 'admin')->firstOrFail();

        $this->assertSame(Permission::count(), $admin->permissions()->count());
    }

    #[Test]
    public function operational_roles_receive_their_expected_boundaries(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $ticketSeller = Role::where('slug', 'ticket_seller')->firstOrFail();
        $ticketChecker = Role::where('slug', 'ticket_checker')->firstOrFail();
        $concessionStaff = Role::where('slug', 'concession_staff')->firstOrFail();
        $customer = Role::where('slug', 'customer')->firstOrFail();

        $this->assertTrue($ticketSeller->permissions()->where('slug', 'booking.hold_seats')->exists());
        $this->assertTrue($ticketSeller->permissions()->where('slug', 'payments.process')->exists());
        $this->assertFalse($ticketSeller->permissions()->where('slug', 'showtimes.update')->exists());

        $this->assertTrue($ticketChecker->permissions()->where('slug', 'tickets.verify')->exists());
        $this->assertTrue($ticketChecker->permissions()->where('slug', 'tickets.mark_used')->exists());
        $this->assertFalse($ticketChecker->permissions()->where('slug', 'orders.refund')->exists());

        $this->assertTrue($concessionStaff->permissions()->where('slug', 'concessions.fulfill')->exists());
        $this->assertFalse($concessionStaff->permissions()->where('slug', 'booking.hold_seats')->exists());

        $this->assertTrue($customer->permissions()->where('slug', 'orders.view_own')->exists());
        $this->assertFalse($customer->permissions()->where('slug', 'orders.view_all')->exists());
    }

    #[Test]
    public function legacy_permission_slugs_are_available_as_aliases(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $manager = Role::where('slug', 'theater_manager')->firstOrFail();

        $this->assertTrue($manager->permissions()->where('slug', 'view_dashboard')->exists());
        $this->assertTrue($manager->permissions()->where('slug', 'dashboard.view')->exists());
    }

    #[Test]
    public function theater_manager_screen_access_is_limited_to_assigned_theaters(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $managerRole = Role::where('slug', 'theater_manager')->firstOrFail();
        $manager = \App\Models\User::factory()->create(['role_id' => $managerRole->id]);
        $assignedTheater = Theater::factory()->create();
        $otherTheater = Theater::factory()->create();
        $assignedScreen = Screen::factory()->create(['theater_id' => $assignedTheater->id]);
        $otherScreen = Screen::factory()->create(['theater_id' => $otherTheater->id]);

        $manager->theaters()->attach($assignedTheater->id);

        $this->assertTrue(Gate::forUser($manager)->allows('update', $assignedScreen));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $otherScreen));
    }
}
