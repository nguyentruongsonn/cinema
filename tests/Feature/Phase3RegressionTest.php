<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 3 Regression Tests
 *
 * Tests for RESTful API migration and caching implementation
 */
class Phase3RegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test promotion validation using GET method (RESTful)
     */
    public function test_promotion_validation_with_get_method(): void
    {
        $promotion = Promotion::factory()->create([
            'code' => 'TEST50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'min_order_value' => 0,
            'max_discount_amount' => null,
            'status' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v1/promotions/TEST50/validate?order_total=1000');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'discount_amount' => 500,
                ],
            ]);
    }

    /**
     * Test promotion validation with invalid code
     */
    public function test_promotion_validation_with_invalid_code(): void
    {
        $response = $this->getJson('/api/v1/promotions/INVALID/validate?order_total=1000');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Promotion not found',
            ]);
    }

    /**
     * Test promotion validation without total parameter
     */
    public function test_promotion_validation_requires_total(): void
    {
        Promotion::factory()->create(['code' => 'TEST50']);

        $response = $this->getJson('/api/v1/promotions/TEST50/validate');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_total']);
    }

    /**
     * Test order cancellation using DELETE method (RESTful)
     */
    public function test_order_cancellation_with_delete_method(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 1, // pending
        ]);

        $response = $this->actingAs($user, 'api')
            ->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Order cancelled successfully',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 0, // cancelled
        ]);
    }

    /**
     * Test unauthorized user cannot cancel order
     */
    public function test_unauthorized_user_cannot_cancel_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => 1, // pending
        ]);

        $response = $this->actingAs($otherUser, 'api')
            ->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertForbidden();
    }

    /**
     * Test movie statistics caching
     */
    public function test_movie_statistics_are_cached(): void
    {
        Movie::factory()->count(5)->create(['is_hidden' => 0]);
        Movie::factory()->count(2)->create(['is_hidden' => 1]);

        // Clear cache
        Cache::flush();

        // First call - cache miss
        $service = app(\App\Services\MovieService::class);
        $stats1 = $service->getMovieStatistics();

        // Verify cache exists
        $this->assertTrue(Cache::has('movies:statistics'));

        // Second call - should use cache
        $stats2 = $service->getMovieStatistics();

        // Should be identical
        $this->assertEquals($stats1, $stats2);
        $this->assertEquals(7, $stats1['total']);
    }

    /**
     * Test movie cache invalidation on update
     */
    public function test_movie_cache_invalidated_on_update(): void
    {
        $movie = Movie::factory()->create(['title' => 'Original Title']);

        // Cache the movie
        $service = app(\App\Services\MovieService::class);
        $cached = $service->getMovie($movie->id);
        $this->assertEquals('Original Title', $cached->title);

        // Verify cache exists
        $this->assertTrue(Cache::has("movie:id:{$movie->id}"));

        // Update movie (should invalidate cache)
        $service->updateMovie($movie->id, ['title' => 'Updated Title']);

        // Cache should be cleared
        $this->assertFalse(Cache::has("movie:id:{$movie->id}"));

        // Next fetch should get updated data
        $updated = $service->getMovie($movie->id);
        $this->assertEquals('Updated Title', $updated->title);
    }

    /**
     * Test movie cache invalidation on delete
     */
    public function test_movie_cache_invalidated_on_delete(): void
    {
        $movie = Movie::factory()->create();

        // Cache the movie
        $service = app(\App\Services\MovieService::class);
        $service->getMovie($movie->id);

        $this->assertTrue(Cache::has("movie:id:{$movie->id}"));

        // Delete movie (should invalidate cache)
        $service->deleteMovie($movie->id);

        // Cache should be cleared
        $this->assertFalse(Cache::has("movie:id:{$movie->id}"));
    }

    /**
     * Test statistics cache invalidated when movie created
     */
    public function test_statistics_cache_invalidated_on_movie_create(): void
    {
        // Warm up cache
        $service = app(\App\Services\MovieService::class);
        $service->getMovieStatistics();
        $this->assertTrue(Cache::has('movies:statistics'));

        // Create movie (should invalidate stats cache)
        $service->createMovie([
            'title' => 'New Movie',
            'slug' => 'new-movie',
            'duration' => 120,
            'release_date' => now(),
        ]);

        // Stats cache should be cleared
        $this->assertFalse(Cache::has('movies:statistics'));
    }
}
