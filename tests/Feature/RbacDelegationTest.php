<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StatFilterRequest;
use App\Models\Format;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SeatLayoutTemplate;
use App\Models\Sound;
use App\Models\Theater;
use App\Models\User;
use App\Policies\FormatPolicy;
use App\Policies\SeatLayoutTemplatePolicy;
use App\Policies\SoundPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_configured_staff_role_can_use_delegated_admin_feature(): void
    {
        $actor = $this->makeUser('ticket_checker', ['products.view']);

        $this->actingAs($actor, 'api')
            ->getJson('/api/v1/admin/products')
            ->assertOk();
    }

    public function test_configured_staff_role_is_forbidden_without_feature_permission(): void
    {
        $actor = $this->makeUser('ticket_checker', []);

        $this->actingAs($actor, 'api')
            ->getJson('/api/v1/admin/products')
            ->assertForbidden();
    }

    public function test_customer_cannot_enter_management_api_even_if_permission_is_attached(): void
    {
        $actor = $this->makeUser('customer', ['products.view']);

        $this->actingAs($actor, 'api')
            ->getJson('/api/v1/admin/products')
            ->assertForbidden();
    }

    public function test_dedicated_toggle_permission_can_toggle_product_without_update_permission(): void
    {
        $actor = $this->makeUser('concession_staff', ['products.toggle_status']);
        $product = Product::createManaged([
            'name' => 'RBAC product',
            'type' => Product::TYPE_FOOD,
            'price' => 25000,
            'stock' => 10,
            'status' => true,
        ]);

        $this->actingAs($actor, 'api')
            ->postJson("/api/v1/admin/products/{$product->id}/toggle-active")
            ->assertOk();

        $this->assertFalse((bool) $product->fresh()->status);
        $this->assertFalse($actor->hasPermission('products.update'));
    }

    public function test_canonical_seat_layout_permissions_are_honored(): void
    {
        $viewer = $this->makeUser('ticket_checker', ['seat_layouts.view']);
        $creator = $this->makeUser('theater_manager', ['seat_layouts.create']);
        $template = SeatLayoutTemplate::create([
            'template_name' => 'RBAC layout',
            'status' => true,
        ]);
        $policy = new SeatLayoutTemplatePolicy();

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $template));
        $this->assertTrue($policy->create($creator));
        $this->assertFalse($policy->update($creator, $template));
    }

    public function test_reports_permission_authorizes_shared_stats_request(): void
    {
        $actor = $this->makeUser('theater_manager', ['reports.view']);
        $request = new StatFilterRequest();
        $request->setUserResolver(fn () => $actor);

        $this->assertTrue($request->authorize());
    }

    public function test_delegated_role_permission_manager_can_update_non_admin_role(): void
    {
        $actor = $this->makeUser('ticket_checker', [
            'roles.view',
            'roles.update',
            'permissions.assign',
        ]);
        $targetRole = Role::query()->where('slug', 'concession_staff')->firstOrFail();

        $this->actingAs($actor, 'api')
            ->putJson("/api/v1/admin/roles-permissions/roles/{$targetRole->id}", [
                'permissions' => ['products.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions.0', 'products.view');
    }

    public function test_role_view_permission_does_not_grant_role_updates(): void
    {
        $actor = $this->makeUser('ticket_checker', ['roles.view']);
        $targetRole = Role::query()->where('slug', 'concession_staff')->firstOrFail();

        $this->actingAs($actor, 'api')
            ->getJson('/api/v1/admin/roles-permissions/roles')
            ->assertOk();

        $this->actingAs($actor, 'api')
            ->putJson("/api/v1/admin/roles-permissions/roles/{$targetRole->id}", [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();
    }

    public function test_pos_access_uses_capability_instead_of_ticket_seller_role(): void
    {
        $theater = Theater::forceCreate([
            'name' => 'RBAC POS theater',
            'address' => 'Ha Noi',
        ]);
        $actor = $this->makeUser('concession_staff', ['orders.create']);
        $actor->theaters()->attach($theater->id);

        $this->actingAs($actor)
            ->get('/pos')
            ->assertOk();
    }

    public function test_format_and_sound_policies_are_registered_explicitly(): void
    {
        $this->assertInstanceOf(FormatPolicy::class, Gate::getPolicyFor(Format::class));
        $this->assertInstanceOf(SoundPolicy::class, Gate::getPolicyFor(Sound::class));
    }

    public function test_scoped_user_list_does_not_leak_users_from_other_theaters(): void
    {
        $assignedTheater = Theater::forceCreate([
            'name' => 'Assigned theater',
            'address' => 'Ha Noi',
        ]);
        $otherTheater = Theater::forceCreate([
            'name' => 'Other theater',
            'address' => 'Da Nang',
        ]);
        $actor = $this->makeUser('theater_manager', ['users.view']);
        $visibleUser = $this->makeUser('ticket_seller', []);
        $hiddenUser = $this->makeUser('ticket_seller', []);

        $actor->theaters()->attach($assignedTheater->id);
        $visibleUser->theaters()->attach($assignedTheater->id);
        $hiddenUser->theaters()->attach($otherTheater->id);

        $this->actingAs($actor, 'api')
            ->getJson('/api/v1/admin/users?per_page=50')
            ->assertOk()
            ->assertJsonFragment(['email' => $visibleUser->email])
            ->assertJsonMissing(['email' => $hiddenUser->email]);

        $this->actingAs($actor, 'api')
            ->getJson('/api/v1/admin/users/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    private function makeUser(string $roleSlug, array $permissionSlugs): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id');

        $role->permissions()->sync($permissionIds);

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => true,
        ])->fresh('role.permissions');
    }
}
