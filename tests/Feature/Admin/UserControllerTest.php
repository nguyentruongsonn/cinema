<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private Role $adminRole;
    private Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->userRole = Role::create(['name' => 'User', 'slug' => 'user']);

        // Create permissions
        $manageUsersPermission = Permission::create(['name' => 'Manage Users', 'slug' => 'manage-users']);
        $this->adminRole->permissions()->attach($manageUsersPermission);

        // Create admin user
        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'status' => true,
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'role_id' => $this->userRole->id,
            'status' => true,
        ]);
    }

    #[Test]
    public function non_admin_cannot_access_user_management_endpoints()
    {
        $this->actingAs($this->regularUser);

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(403);

        $response = $this->postJson('/api/v1/admin/users', []);
        $response->assertStatus(403);

        $response = $this->putJson("/api/v1/admin/users/{$this->regularUser->id}", []);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/v1/admin/users/{$this->regularUser->id}");
        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_list_users()
    {
        $this->actingAs($this->admin);

        User::factory()->count(5)->create(['role_id' => $this->userRole->id]);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'email', 'username', 'status', 'role']
                ],
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                    'from',
                    'to'
                ]
            ]);

        // Sensitive fields should not be exposed
        $response->assertJsonMissing(['password', 'remember_token']);
    }

    #[Test]
    public function admin_can_create_user()
    {
        $this->actingAs($this->admin);

        $userData = [
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0123456789',
            'role_id' => $this->userRole->id,
        ];

        $response = $this->postJson('/api/v1/admin/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'email', 'username', 'role']
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'username' => 'newuser',
            'role_id' => $this->userRole->id,
        ]);

        // Password should be hashed
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    #[Test]
    public function admin_can_update_user_profile()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '0987654321',
        ];

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '0987654321',
        ]);
    }

    #[Test]
    public function admin_can_update_user_role_via_update()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->userRole->id]);
        $updateData = [
            'name' => 'Updated Name',
            'role_id' => $this->adminRole->id,
        ];

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", $updateData);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals($this->adminRole->id, $user->role_id);
    }

    #[Test]
    public function admin_can_update_user_status_via_update()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'role_id' => $this->userRole->id,
            'status' => true,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'status' => false,
        ];

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", $updateData);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertFalse((bool) $user->status);
    }

    #[Test]
    public function admin_can_update_loyalty_points_via_update()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'role_id' => $this->userRole->id,
            'loyalty_points' => 100,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'loyalty_points' => 9999,
        ];

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", $updateData);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals(9999, $user->loyalty_points);
    }

    #[Test]
    public function admin_can_toggle_user_status()
    {
        $manageStatusPermission = Permission::create(['name' => 'Manage User Status', 'slug' => 'users.manage_status']);
        $this->adminRole->permissions()->attach($manageStatusPermission);

        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'role_id' => $this->userRole->id,
            'status' => true,
        ]);

        $response = $this->postJson("/api/v1/admin/users/{$user->id}/toggle-status");

        $response->assertStatus(200);

        $user->refresh();
        $this->assertFalse($user->status);

        // Toggle again
        $response = $this->postJson("/api/v1/admin/users/{$user->id}/toggle-status");
        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->status);
    }

    #[Test]
    public function admin_can_reset_user_password()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        $response = $this->postJson("/api/v1/admin/users/{$user->id}/reset-password", [
            'password' => 'Newpassword123!',
            'password_confirmation' => 'Newpassword123!',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('Newpassword123!', $user->password));
    }

    #[Test]
    public function password_reset_returns_specific_validation_errors()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        $this->postJson("/api/v1/admin/users/{$user->id}/reset-password", [
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function admin_can_delete_user_without_orders()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->userRole->id]);
        $userId = $user->id;

        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    #[Test]
    public function admin_can_get_user_stats()
    {
        $this->actingAs($this->admin);

        User::factory()->count(5)->create(['role_id' => $this->userRole->id, 'status' => true]);
        User::factory()->count(2)->create(['role_id' => $this->userRole->id, 'status' => false]);

        $response = $this->getJson('/api/v1/admin/users/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['total', 'active', 'inactive', 'verified', 'unverified', 'recent']
            ]);
    }

    #[Test]
    public function admin_can_get_roles_list()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/users/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug']
                ]
            ]);
    }

    #[Test]
    public function user_resource_does_not_expose_sensitive_fields()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        $response = $this->getJson("/api/v1/admin/users/{$user->id}");

        $response->assertStatus(200);

        // Should not contain sensitive fields
        $responseData = $response->json('data');
        $this->assertArrayNotHasKey('password', $responseData);
        $this->assertArrayNotHasKey('remember_token', $responseData);
        $this->assertArrayNotHasKey('email_verified_at', $responseData);

        // Should contain expected fields
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('name', $responseData);
        $this->assertArrayHasKey('email', $responseData);
        $this->assertArrayHasKey('role', $responseData);
    }
}
