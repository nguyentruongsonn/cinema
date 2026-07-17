<?php

namespace Tests\Feature\Admin;

use App\Models\Movie;
use App\Models\Product;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\User;
use App\Services\ProductService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieProductControllerRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_with_scheduled_showtime_cannot_be_deleted(): void
    {
        $admin = $this->makeAdminUser();
        $movie = Movie::factory()->create(['duration' => 120]);
        $theater = Theater::factory()->create();
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);

        Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $screen->id,
            'scheduled_at' => Carbon::now()->addDays(3)->setTime(10, 0),
        ]);

        $response = $this->actingAs($admin, 'api')->deleteJson("/api/v1/admin/movies/{$movie->id}");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['movie']]);

        $this->assertDatabaseHas('movies', ['id' => $movie->id]);
    }

    public function test_admin_can_create_product_with_managed_price_stock_and_status(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin, 'api')->postJson('/api/v1/admin/products', [
            'name' => 'Caramel Popcorn',
            'type' => Product::TYPE_FOOD,
            'price' => '45000',
            'stock' => 25,
            'description' => 'Large caramel popcorn',
            'status' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'name' => 'Caramel Popcorn',
            'type' => Product::TYPE_FOOD,
            'price' => 45000,
            'stock' => 25,
            'status' => false,
        ]);
    }

    public function test_admin_can_update_product_managed_fields(): void
    {
        $admin = $this->makeAdminUser();
        $product = Product::createManaged([
            'name' => 'Cola',
            'type' => Product::TYPE_DRINK,
            'price' => 30000,
            'stock' => 10,
            'status' => true,
        ]);

        $response = $this->actingAs($admin, 'api')->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Large Cola',
            'type' => Product::TYPE_DRINK,
            'price' => '35000',
            'stock' => 30,
            'description' => 'Large cold cola',
            'status' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Large Cola',
            'price' => 35000,
            'stock' => 30,
            'status' => false,
        ]);
    }

    public function test_booking_product_service_returns_only_active_in_stock_products(): void
    {
        Product::createManaged([
            'name' => 'Active Popcorn',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 5,
            'status' => true,
        ]);
        Product::createManaged([
            'name' => 'Out Of Stock Popcorn',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 0,
            'status' => true,
        ]);
        Product::createManaged([
            'name' => 'Inactive Popcorn',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 5,
            'status' => false,
        ]);

        $products = app(ProductService::class)->getBookingProducts([
            'type' => Product::TYPE_FOOD,
            'q' => 'Popcorn',
            'per_page' => 1000,
        ]);

        $this->assertSame(1, $products->total());
        $this->assertSame('Active Popcorn', $products->items()[0]->name);
        $this->assertSame(50, $products->perPage());
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
