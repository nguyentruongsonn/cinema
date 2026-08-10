<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCatalogAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_catalog_only_returns_items_that_can_be_sold(): void
    {
        $adminRole = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
        ]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $availableProduct = Product::forceCreate([
            'name' => 'POS available product',
            'type' => Product::TYPE_FOOD,
            'price' => 30000,
            'stock' => 3,
            'status' => true,
        ]);
        $unavailableProduct = Product::forceCreate([
            'name' => 'POS unavailable product',
            'type' => Product::TYPE_DRINK,
            'price' => 25000,
            'stock' => 0,
            'status' => true,
        ]);

        $availableCombo = Combo::forceCreate([
            'name' => 'POS available combo',
            'price' => 50000,
            'original_price' => 60000,
            'status' => true,
        ]);
        ComboItem::create([
            'combo_id' => $availableCombo->id,
            'product_id' => $availableProduct->id,
            'quantity' => 2,
        ]);

        $unavailableCombo = Combo::forceCreate([
            'name' => 'POS unavailable combo',
            'price' => 40000,
            'original_price' => 50000,
            'status' => true,
        ]);
        ComboItem::create([
            'combo_id' => $unavailableCombo->id,
            'product_id' => $unavailableProduct->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/v1/pos/catalog')->assertOk();
        $products = collect($response->json('data.products'));
        $combos = collect($response->json('data.combos'));

        $this->assertTrue($products->contains('id', $availableProduct->id));
        $this->assertFalse($products->contains('id', $unavailableProduct->id));
        $this->assertTrue($combos->contains('id', $availableCombo->id));
        $this->assertFalse($combos->contains('id', $unavailableCombo->id));
        $this->assertSame(1, (int) $combos->firstWhere('id', $availableCombo->id)['available_stock']);
    }
}
