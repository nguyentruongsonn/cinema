<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Theater;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator role',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole->id);
    }

    #[Test]
    public function it_requires_authorization_to_view_branches()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/admin/branches');

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Forbidden: insufficient role']);
    }

    #[Test]
    public function it_lists_branches_with_theaters_relationship()
    {
        $branch = Branch::factory()->create(['name' => 'Test Branch']);
        Theater::factory()->create(['branch_id' => $branch->id, 'status' => 1]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/branches');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'is_active', 'theaters_count', 'active_theaters_count'],
                ],
                'pagination',
            ]);
    }

    #[Test]
    public function it_validates_filter_parameters()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/branches?per_page=999');

        $response->assertStatus(422);
    }

    #[Test]
    public function it_requires_authorization_to_create_branch()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/branches', ['name' => 'New Branch']);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_blocks_delete_branch_with_theaters()
    {
        $branch = Branch::factory()->create();
        Theater::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/branches/{$branch->id}");

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Cannot delete branch with existing theaters']);

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    #[Test]
    public function it_blocks_deactivate_branch_with_active_theaters()
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        Theater::factory()->create(['branch_id' => $branch->id, 'status' => 1]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/branches/{$branch->id}/toggle-active");

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Cannot deactivate branch with active theaters']);

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => true]);
    }

    #[Test]
    public function it_allows_toggle_if_no_active_theaters()
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        Theater::factory()->create(['branch_id' => $branch->id, 'status' => 0]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/branches/{$branch->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => false]);
    }
}
