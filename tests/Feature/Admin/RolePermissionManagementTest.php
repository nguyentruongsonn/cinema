<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_role_permission_catalog(): void
    {
        $this->seedRbac();
        $admin = $this->makeUserWithRole('admin');

        $rolesResponse = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/roles-permissions/roles');

        $rolesResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['slug' => 'ticket_seller'])
            ->assertJsonFragment(['is_readonly' => true]);

        $permissionsResponse = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/roles-permissions/permissions');

        $permissionsResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['slug' => 'orders.create']);
    }

    public function test_admin_can_update_role_permissions_and_audit_change(): void
    {
        $this->seedRbac();
        $admin = $this->makeUserWithRole('admin');
        $role = Role::query()->where('slug', 'ticket_seller')->firstOrFail();

        $response = $this->actingAs($admin, 'api')
            ->putJson("/api/v1/admin/roles-permissions/roles/{$role->id}", [
                'permissions' => [
                    'orders.view_theater',
                    'orders.create',
                    'payments.process',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.slug', 'ticket_seller')
            ->assertJsonPath('data.permissions.0', 'orders.create');

        $this->assertTrue($role->fresh()->permissions()->where('slug', 'orders.view_theater')->exists());
        $this->assertTrue($role->fresh()->permissions()->where('slug', 'create_orders')->exists());

        $auditLog = AuditLog::query()
            ->where('action', 'role.permissions.updated')
            ->where('auditable_type', 'role')
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame($role->slug, $auditLog->new_values['role_slug']);
        $this->assertContains('payments.process', $auditLog->new_values['permissions']);
    }

    public function test_non_admin_cannot_manage_role_permissions(): void
    {
        $this->seedRbac();
        $customer = $this->makeUserWithRole('customer');

        $response = $this->actingAs($customer, 'api')
            ->getJson('/api/v1/admin/roles-permissions/roles');

        $response->assertForbidden();
    }

    public function test_admin_role_permissions_are_locked_for_regular_admin(): void
    {
        $this->seedRbac();
        $admin = $this->makeUserWithRole('admin');
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $response = $this->actingAs($admin, 'api')
            ->putJson("/api/v1/admin/roles-permissions/roles/{$adminRole->id}", [
                'permissions' => ['orders.view'],
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_view_audit_log_list_and_detail(): void
    {
        $this->seedRbac();
        $admin = $this->makeUserWithRole('admin');

        $auditLog = AuditLog::record([
            'user_id' => $admin->id,
            'action' => 'role.permissions.updated',
            'auditable_type' => 'role',
            'auditable_id' => Role::query()->where('slug', 'ticket_seller')->value('id'),
            'old_values' => ['permissions' => ['orders.view']],
            'new_values' => ['permissions' => ['orders.view', 'orders.create']],
            'changes' => ['permissions' => ['added' => ['orders.create']]],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'request_id' => 'test-request-id',
        ]);

        $listResponse = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/audit-logs?auditable_type=role');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['request_id' => 'test-request-id'])
            ->assertJsonMissing(['old_values' => ['permissions' => ['orders.view']]]);

        $detailResponse = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/admin/audit-logs/{$auditLog->id}");

        $detailResponse->assertOk()
            ->assertJsonPath('data.old_values.permissions.0', 'orders.view')
            ->assertJsonPath('data.new_values.permissions.1', 'orders.create');
    }

    private function seedRbac(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function makeUserWithRole(string $roleSlug): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => true,
        ]);
    }
}
