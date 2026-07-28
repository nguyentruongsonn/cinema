<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\HtmlContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_sanitizer_keeps_allowed_markup_and_removes_active_content(): void
    {
        $safe = app(HtmlContentSanitizer::class)->sanitize(
            '<p onclick="alert(1)">Nội dung <strong>đậm</strong></p>'
            . '<script>alert(1)</script>'
            . '<a href="javascript:alert(1)">Xấu</a>'
            . '<a href="https://cinema.test/news" target="_blank">Tốt</a>'
        );

        $this->assertStringContainsString('<strong>đậm</strong>', $safe);
        $this->assertStringContainsString('href="https://cinema.test/news"', $safe);
        $this->assertStringNotContainsString('script', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('javascript:', $safe);
        $this->assertStringNotContainsString('target=', $safe);
    }

    public function test_admin_can_schedule_post_but_public_api_only_returns_due_posts(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin, 'api')->post('/api/v1/admin/posts', [
            'title' => '<b>Lịch chiếu đặc biệt</b>',
            'content' => '<p>Nội dung an toàn</p><img src=x onerror=alert(1)>',
            'category' => 'news',
            'is_published' => true,
            'published_at' => now()->addHour()->toDateTimeString(),
            'featured_image' => UploadedFile::fake()->image('post.jpg', 1200, 630),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.publication_status', 'scheduled');

        $post = Post::query()->firstOrFail();
        $this->assertSame('Lịch chiếu đặc biệt', $post->title);
        $this->assertStringNotContainsString('onerror', $post->content);
        $this->getJson('/api/v1/posts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/posts/{$post->slug}")->assertNotFound();

        $post->forceFill(['published_at' => now()->subMinute()])->save();

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $post->slug)
            ->assertJsonPath('data.0.publication_status', 'published');
        $this->getJson("/api/v1/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Lịch chiếu đặc biệt');
    }

    public function test_replacing_active_home_banner_updates_home_snapshot_and_removes_old_file(): void
    {
        Storage::fake('public');
        Cache::flush();
        $admin = $this->makeAdminUser();
        Storage::disk('public')->put('banners/old.jpg', 'old');
        $banner = Banner::create([
            'title' => 'Banner cũ',
            'is_active' => true,
        ]);
        $banner->images()->create(['image_path' => 'banners/old.jpg']);

        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.featured_banner.title', 'Banner cũ');

        $response = $this->actingAs($admin, 'api')->post("/api/v1/admin/banners/{$banner->id}/update", [
            'title' => 'Banner mới',
            'image_paths' => [UploadedFile::fake()->image('replacement.webp', 1600, 700)],
            'is_active' => true,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        Storage::disk('public')->assertMissing('banners/old.jpg');
        Storage::disk('public')->assertExists($banner->images()->firstOrFail()->image_path);
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.featured_banner.title', 'Banner mới');
    }

    public function test_admin_creates_one_banner_row_with_multiple_images(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin, 'api')->post('/api/v1/admin/banners', [
            'title' => 'Banner nhiều ảnh',
            'image_paths' => [
                UploadedFile::fake()->image('first.webp', 1600, 700),
                UploadedFile::fake()->image('second.webp', 1600, 700),
            ],
            'is_active' => true,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'data.images');
        $this->assertDatabaseCount('banners', 1);
        $this->assertDatabaseCount('banner_images', 2);
    }

    private function makeAdminUser(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator role']
        );
        $user = User::factory()->create();
        $user->assignRole($role->id);

        return $user->fresh();
    }
}
