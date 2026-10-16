<?php

namespace Tests\Feature;

use App\Http\Resources\TheaterResource;
use App\Models\Theater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TheaterMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_theater_can_store_and_retrieve_map_field(): void
    {
        $theater = Theater::create([
            'name' => 'Cinema Test Map',
            'address' => '123 Đường Test, Quận 1',
            'phone' => '0901234567',
            'email' => 'test@cinema.vn',
            'map' => '<iframe src="https://example.com/map"></iframe>',
            'status' => true,
        ]);

        $this->assertDatabaseHas('theaters', [
            'id' => $theater->id,
            'map' => '<iframe src="https://example.com/map"></iframe>',
        ]);

        $resource = (new TheaterResource($theater))->toArray(new Request());
        $this->assertArrayHasKey('map', $resource);
        $this->assertEquals('<iframe src="https://example.com/map"></iframe>', $resource['map']);
    }
}
