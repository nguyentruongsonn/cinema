<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Theater;
use App\Models\Screen;
use App\Models\Showtime;
use App\Models\Movie;
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
            ->assertJson(['success' => false, 'message' => 'Forbidden: management role required']);
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
            ])
            ->assertJsonPath('data.0.theaters_count', 1)
            ->assertJsonPath('data.0.active_theaters_count', 1)
            ->assertJsonMissingPath('data.0.theaters');
    }

    #[Test]
    public function it_validates_filter_parameters()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/branches?per_page=999');

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_lightweight_active_branch_options()
    {
        $activeBranch = Branch::factory()->create(['is_active' => true]);
        Branch::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/branches?options=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeBranch->id)
            ->assertJsonMissingPath('pagination')
            ->assertJsonMissingPath('data.0.theaters_count');
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
    public function it_blocks_deactivate_branch_with_future_showtimes()
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $theater = Theater::factory()->create(['branch_id' => $branch->id, 'status' => 1]);
        $screen = Screen::factory()->create(['theater_id' => $theater->id, 'status' => 1]);
        $movie = Movie::factory()->create(['status' => 1]);
        Showtime::factory()->create([
            'screen_id' => $screen->id,
            'movie_id' => $movie->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/branches/{$branch->id}/toggle-active");

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Cannot deactivate branch with future showtimes']);

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => true]);
    }

    #[Test]
    public function it_allows_toggle_if_no_future_showtimes()
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $theater = Theater::factory()->create(['branch_id' => $branch->id, 'status' => 1]);
        $screen = Screen::factory()->create(['theater_id' => $theater->id, 'status' => 1]);
        // No showtimes created

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/branches/{$branch->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => false]);
    }
}
