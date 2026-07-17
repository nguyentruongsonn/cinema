<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Movie;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_update_and_toggle_write_audit_logs(): void
    {
        $admin = $this->makeAdminUser();

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/products', [
            'name' => 'Audit Popcorn',
            'type' => Product::TYPE_FOOD,
            'price' => '45000',
            'stock' => 20,
            'status' => true,
        ]);

        $createResponse->assertStatus(201);
        $product = Product::query()->where('name', 'Audit Popcorn')->firstOrFail();
        $createAudit = $this->auditFor('product.created', $product);

        $this->assertSame($admin->id, $createAudit->user_id);
        $this->assertSame([], $createAudit->old_values);
        $this->assertSame('Audit Popcorn', $createAudit->new_values['name']);
        $this->assertSame('product', $createAudit->auditable_type);
        $this->assertNotEmpty($createAudit->request_id);

        $updateResponse = $this->actingAs($admin, 'api')->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Audit Popcorn Large',
            'type' => Product::TYPE_FOOD,
            'price' => '55000',
            'stock' => 30,
            'status' => true,
        ]);

        $updateResponse->assertStatus(200);
        $updatedAudit = $this->auditFor('product.updated', $product);
        $this->assertSame('Audit Popcorn', $updatedAudit->old_values['name']);
        $this->assertSame('Audit Popcorn Large', $updatedAudit->new_values['name']);
        $this->assertSame(20, $updatedAudit->old_values['stock']);
        $this->assertSame(30, $updatedAudit->new_values['stock']);

        $toggleResponse = $this->actingAs($admin, 'api')->postJson("/api/v1/admin/products/{$product->id}/toggle-active");

        $toggleResponse->assertStatus(200);
        $toggleAudit = $this->auditFor('product.status_toggled', $product);
        $this->assertTrue($toggleAudit->old_values['status']);
        $this->assertFalse($toggleAudit->new_values['status']);
    }

    public function test_product_delete_writes_audit_log_before_file_cleanup_side_effects(): void
    {
        $admin = $this->makeAdminUser();
        $product = Product::createManaged([
            'name' => 'Delete Audit Cola',
            'type' => Product::TYPE_DRINK,
            'price' => 35000,
            'stock' => 10,
            'status' => true,
        ]);

        $response = $this->actingAs($admin, 'api')->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(200);

        $deleteAudit = $this->auditFor('product.deleted', $product);
        $this->assertSame('Delete Audit Cola', $deleteAudit->old_values['name']);
        $this->assertSame([], $deleteAudit->new_values);
    }

    public function test_banner_create_and_toggle_write_audit_logs_without_raw_image_path_changes(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin, 'api')
            ->post('/api/v1/admin/banners', [
                'title' => 'Audit Banner',
                'description' => '<strong>Banner description</strong>',
                'image_paths' => [UploadedFile::fake()->image('banner.jpg')],
                'position' => 'home_slider',
                'display_order' => 3,
                'is_active' => true,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $banner = Banner::query()->where('title', 'Audit Banner')->firstOrFail();
        $createAudit = $this->auditForModel('banner.created', 'banner', $banner->id);

        $this->assertSame($admin->id, $createAudit->user_id);
        $this->assertSame([], $createAudit->old_values);
        $this->assertSame('Audit Banner', $createAudit->new_values['title']);
        $this->assertSame('[image]', $createAudit->new_values['image_path']);

        $toggleResponse = $this->actingAs($admin, 'api')->postJson("/api/v1/admin/banners/{$banner->id}/toggle-active");

        $toggleResponse->assertStatus(200);
        $toggleAudit = $this->auditForModel('banner.status_toggled', 'banner', $banner->id);
        $this->assertTrue($toggleAudit->old_values['is_active']);
        $this->assertFalse($toggleAudit->new_values['is_active']);
    }

    public function test_promotion_create_and_toggle_write_audit_logs_with_normalized_discount_type(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/promotions', [
            'code' => 'audit10',
            'name' => 'Audit Promotion',
            'category' => 'seasonal',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'max_discount_amount' => 50000,
            'start_date' => now()->subDay()->toDateTimeString(),
            'end_date' => now()->addDays(10)->toDateTimeString(),
            'usage_limit' => 100,
            'status' => true,
        ]);

        $response->assertStatus(201);
        $promotion = Promotion::query()->where('code', 'AUDIT10')->firstOrFail();
        $createAudit = $this->auditForModel('promotion.created', 'promotion', $promotion->id);

        $this->assertSame('percentage', $promotion->discount_type);
        $this->assertSame('percentage', $createAudit->new_values['discount_type']);
        $this->assertSame('AUDIT10', $createAudit->new_values['code']);
        $this->assertTrue($createAudit->new_values['status']);

        $toggleResponse = $this->actingAs($admin, 'api')->postJson("/api/v1/admin/promotions/{$promotion->id}/toggle-active");

        $toggleResponse->assertStatus(200);
        $toggleAudit = $this->auditForModel('promotion.status_toggled', 'promotion', $promotion->id);
        $this->assertTrue($toggleAudit->old_values['status']);
        $this->assertFalse($toggleAudit->new_values['status']);
    }

    public function test_movie_create_update_and_hot_toggle_write_audit_logs_without_raw_file_paths(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin, 'api')
            ->post('/api/v1/admin/movies', [
                'title' => 'Audit Movie',
                'duration' => 120,
                'release_date' => now()->addDay()->toDateString(),
                'poster_file' => UploadedFile::fake()->image('poster.jpg'),
                'banner_file' => UploadedFile::fake()->image('banner.jpg'),
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $movie = Movie::query()->where('title', 'Audit Movie')->firstOrFail();
        $createAudit = $this->auditForModel('movie.created', 'movie', $movie->id);

        $this->assertSame($admin->id, $createAudit->user_id);
        $this->assertSame([], $createAudit->old_values);
        $this->assertSame('Audit Movie', $createAudit->new_values['title']);
        $this->assertSame('[image]', $createAudit->new_values['poster_path']);
        $this->assertSame('[image]', $createAudit->new_values['banner_path']);

        $updateResponse = $this->actingAs($admin, 'api')->putJson("/api/v1/admin/movies/{$movie->id}", [
            'title' => 'Audit Movie Updated',
            'duration' => 130,
            'release_date' => now()->addDays(2)->toDateString(),
        ]);

        $updateResponse->assertStatus(200);
        $updateAudit = $this->auditForModel('movie.updated', 'movie', $movie->id);
        $this->assertSame('Audit Movie', $updateAudit->old_values['title']);
        $this->assertSame('Audit Movie Updated', $updateAudit->new_values['title']);
        $this->assertSame(120, $updateAudit->old_values['duration']);
        $this->assertSame(130, $updateAudit->new_values['duration']);

        $toggleResponse = $this->actingAs($admin, 'api')->postJson("/api/v1/admin/movies/{$movie->id}/toggle-hot");

        $toggleResponse->assertStatus(200);
        $toggleAudit = $this->auditForModel('movie.hot_toggled', 'movie', $movie->id);
        $this->assertFalse($toggleAudit->old_values['is_hot']);
        $this->assertTrue($toggleAudit->new_values['is_hot']);
    }

    public function test_user_create_update_status_and_password_reset_write_safe_audit_logs(): void
    {
        $admin = $this->makeAdminUser();

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/users', [
            'name' => 'Audit User',
            'username' => 'audit_user',
            'email' => 'audit-user@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0123456789',
            'status' => true,
        ]);

        $createResponse->assertStatus(201);
        $user = User::query()->where('email', 'audit-user@example.test')->firstOrFail();
        $createAudit = $this->auditForModel('user.created', 'user', $user->id);

        $this->assertSame($admin->id, $createAudit->user_id);
        $this->assertSame([], $createAudit->old_values);
        $this->assertSame('audit-user@example.test', $createAudit->new_values['email']);
        $this->assertArrayNotHasKey('password', $createAudit->new_values);

        $updateResponse = $this->actingAs($admin, 'api')->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => 'Audit User Updated',
            'phone' => '0987654321',
        ]);

        $updateResponse->assertStatus(200);
        $updateAudit = $this->auditForModel('user.updated', 'user', $user->id);
        $this->assertSame('Audit User', $updateAudit->old_values['name']);
        $this->assertSame('Audit User Updated', $updateAudit->new_values['name']);
        $this->assertArrayNotHasKey('password', $updateAudit->new_values);

        $this->grantPermission($admin, 'users.manage_status');
        $toggleResponse = $this->actingAs($admin->fresh(), 'api')->postJson("/api/v1/admin/users/{$user->id}/toggle-status");

        $toggleResponse->assertStatus(200);
        $toggleAudit = $this->auditForModel('user.status_toggled', 'user', $user->id);
        $this->assertTrue($toggleAudit->old_values['status']);
        $this->assertFalse($toggleAudit->new_values['status']);

        $resetResponse = $this->actingAs($admin, 'api')->postJson("/api/v1/admin/users/{$user->id}/reset-password", [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $resetResponse->assertStatus(200);
        $resetAudit = $this->auditForModel('user.password_reset', 'user', $user->id);
        $this->assertTrue($resetAudit->new_values['credential_reset']);
        $this->assertArrayNotHasKey('password', $resetAudit->new_values);
    }

    public function test_audit_log_service_redacts_sensitive_context_values(): void
    {
        $admin = $this->makeAdminUser();

        $auditLog = app(AuditLogService::class)->recordAction($admin, 'security.test', [
            'email' => 'admin@example.test',
            'password' => 'secret',
            'api_token' => 'token-value',
        ]);

        $this->assertSame('admin@example.test', $auditLog->new_values['email']);
        $this->assertSame('[REDACTED]', $auditLog->new_values['password']);
        $this->assertSame('[REDACTED]', $auditLog->new_values['api_token']);
    }

    private function auditFor(string $action, Product $product): AuditLog
    {
        return $this->auditForModel($action, 'product', $product->id);
    }

    private function auditForModel(string $action, string $auditableType, int $auditableId): AuditLog
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->latest('id')
            ->firstOrFail();
    }

    private function makeAdminUser(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Administrator role',
            ]
        );

        $user = User::factory()->create();
        $user->assignRole($role->id);

        return $user->fresh();
    }

    private function grantPermission(User $user, string $permissionSlug): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => $permissionSlug],
            [
                'name' => $permissionSlug,
                'group' => 'tests',
            ]
        );

        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
