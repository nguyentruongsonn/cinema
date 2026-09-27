<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBannerPlacementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_banners_api_filters_by_placement(): void
    {
        Banner::create(['title' => 'Home Banner', 'placement' => 'home', 'is_active' => true]);
        Banner::create(['title' => 'Theater Banner', 'placement' => 'theater', 'is_active' => true]);
        Banner::create(['title' => 'Universal Banner', 'placement' => 'all', 'is_active' => true]);

        $response = $this->getJson('/api/v1/banners?placement=theater');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Theater Banner', $titles);
        $this->assertContains('Universal Banner', $titles);
        $this->assertNotContains('Home Banner', $titles);
    }

    public function test_public_banners_api_returns_all_active_when_placement_omitted(): void
    {
        Banner::create(['title' => 'Home Banner', 'placement' => 'home', 'is_active' => true]);
        Banner::create(['title' => 'Theater Banner', 'placement' => 'theater', 'is_active' => true]);

        $response = $this->getJson('/api/v1/banners');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Home Banner', $titles);
        $this->assertContains('Theater Banner', $titles);
    }
}
