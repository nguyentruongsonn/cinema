<?php

namespace Tests\Feature\Admin;

use App\Models\Movie;
use App\Models\Role;
use App\Models\User;
use App\Services\MovieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AdminUploadSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_store_cleans_uploaded_files_when_service_fails_after_upload(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdminUser();

        $this->mock(MovieService::class, function ($mock): void {
            $mock->shouldReceive('createMovie')
                ->once()
                ->andThrow(new \RuntimeException('Simulated create failure'));
        });

        $response = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/movies', [
            'title' => 'Upload Failure Movie',
            'duration' => 120,
            'release_date' => now()->addDay()->toDateString(),
            'poster_file' => UploadedFile::fake()->image('poster.jpg'),
            'banner_file' => UploadedFile::fake()->image('banner.jpg'),
        ]);

        $response->assertStatus(500);

        $this->assertSame([], Storage::disk('public')->allFiles('movies/posters'));
        $this->assertSame([], Storage::disk('public')->allFiles('movies/banners'));
        $this->assertDatabaseMissing('movies', ['title' => 'Upload Failure Movie']);
    }

    public function test_movie_update_preserves_old_files_and_cleans_new_files_when_service_fails_after_upload(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdminUser();
        $movie = Movie::factory()->create([
            'poster_path' => 'movies/posters/old-poster.jpg',
            'banner_path' => 'movies/banners/old-banner.jpg',
        ]);

        Storage::disk('public')->put($movie->poster_path, 'old poster');
        Storage::disk('public')->put($movie->banner_path, 'old banner');

        $this->mock(MovieService::class, function ($mock): void {
            $mock->shouldReceive('updateMovie')
                ->once()
                ->andThrow(new \RuntimeException('Simulated update failure'));
        });

        $response = $this->actingAs($admin, 'api')->putJson("/api/v1/admin/movies/{$movie->id}", [
            'title' => 'Updated Upload Failure Movie',
            'duration' => 120,
            'release_date' => now()->addDay()->toDateString(),
            'poster_file' => UploadedFile::fake()->image('new-poster.jpg'),
            'banner_file' => UploadedFile::fake()->image('new-banner.jpg'),
        ]);

        $response->assertStatus(500);

        Storage::disk('public')->assertExists('movies/posters/old-poster.jpg');
        Storage::disk('public')->assertExists('movies/banners/old-banner.jpg');
        $this->assertSame(['movies/posters/old-poster.jpg'], Storage::disk('public')->allFiles('movies/posters'));
        $this->assertSame(['movies/banners/old-banner.jpg'], Storage::disk('public')->allFiles('movies/banners'));
        $this->assertDatabaseHas('movies', [
            'id' => $movie->id,
            'title' => $movie->title,
            'poster_path' => 'movies/posters/old-poster.jpg',
            'banner_path' => 'movies/banners/old-banner.jpg',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
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
}
