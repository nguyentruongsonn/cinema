<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\Combo;
use App\Models\Movie;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Policies\MoviePolicy;
use App\Policies\PromotionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_policy_uses_seeded_permission_slugs(): void
    {
        $user = $this->makeUserWithPermissions(['create_movies', 'edit_movies', 'delete_movies']);
        $movie = new Movie();
        $policy = new MoviePolicy();

        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $movie));
        $this->assertTrue($policy->delete($user, $movie));
        $this->assertTrue($policy->toggleStatus($user, $movie));
        $this->assertTrue($policy->toggleHot($user, $movie));
    }

    public function test_promotion_policy_uses_seeded_permission_slugs(): void
    {
        $user = $this->makeUserWithPermissions([
            'view_promotions',
            'create_promotions',
            'edit_promotions',
            'delete_promotions',
        ]);
        $promotion = new Promotion();
        $policy = new PromotionPolicy();

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $promotion));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $promotion));
        $this->assertTrue($policy->delete($user, $promotion));
        $this->assertTrue($policy->toggleStatus($user, $promotion));
        $this->assertFalse($policy->resetUsageCount($user, $promotion));
    }

    public function test_user_policy_uses_seeded_permission_slugs_and_blocks_self_privilege_changes(): void
    {
        $actor = $this->makeUserWithPermissions([
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_user_roles',
        ]);
        $target = $this->makeUserWithRole('customer');
        $policy = new UserPolicy();

        $this->assertTrue($policy->viewAny($actor));
        $this->assertTrue($policy->view($actor, $target));
        $this->assertTrue($policy->create($actor));
        $this->assertTrue($policy->update($actor, $target));
        $this->assertTrue($policy->delete($actor, $target));
        $this->assertTrue($policy->updateRole($actor, $target));
        $this->assertFalse($policy->updateRole($actor, $actor));
        $this->assertFalse($policy->updateStatus($actor, $actor));
    }

    public function test_admin_role_matches_admin_route_policy_expectations(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $targetAdmin = $this->makeUserWithRole('admin');
        $movie = new Movie();
        $promotion = new Promotion();
        $userPolicy = new UserPolicy();

        $this->assertTrue((new MoviePolicy())->create($admin));
        $this->assertTrue((new MoviePolicy())->update($admin, $movie));
        $this->assertTrue((new PromotionPolicy())->create($admin));
        $this->assertTrue((new PromotionPolicy())->resetUsageCount($admin, $promotion));
        $this->assertTrue($userPolicy->delete($admin, $targetAdmin));
    }

    public function test_media_resource_policies_are_registered_for_admin_routes(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $customer = $this->makeUserWithRole('customer');
        $banner = new Banner();
        $product = new Product();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Banner::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Banner::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $banner));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $banner));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $product));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $product));
        $this->assertFalse(Gate::forUser($customer)->allows('create', Banner::class));
        $this->assertFalse(Gate::forUser($customer)->allows('create', Product::class));
    }

    public function test_dashboard_and_combo_authorization_are_registered_for_admins(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $customer = $this->makeUserWithRole('customer');

        $this->assertTrue(Gate::forUser($admin)->allows('viewDashboardMetrics'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Combo::class));
        $this->assertFalse(Gate::forUser($customer)->allows('viewDashboardMetrics'));
        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', Combo::class));
    }

    /**
     * @param array<int, string> $permissionSlugs
     */
    private function makeUserWithPermissions(array $permissionSlugs): User
    {
        $role = Role::create([
            'name' => 'Policy Role ' . Str::random(8),
            'slug' => 'policy-role-' . Str::random(8),
            'description' => 'Role for policy regression tests',
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::create([
                'name' => $slug,
                'slug' => $slug,
                'group' => Str::before($slug, '_') ?: 'policy',
            ]);

            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create();
        $user->assignRole($role->id);

        return $user->fresh();
    }

    private function makeUserWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => Str::headline($slug),
                'description' => "{$slug} role",
            ]
        );

        $user = User::factory()->create();
        $user->assignRole($role->id);

        return $user->fresh();
    }
}
