<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_has_placement_column_with_default_home(): void
    {
        $banner = Banner::create([
            'title' => 'Test Banner',
            'is_active' => true,
        ]);

        $this->assertEquals('home', $banner->fresh()->placement);
    }

    public function test_scope_for_placement_filters_correctly(): void
    {
        Banner::create(['title' => 'Home Only', 'placement' => 'home', 'is_active' => true]);
        Banner::create(['title' => 'Theater Only', 'placement' => 'theater', 'is_active' => true]);
        Banner::create(['title' => 'All Pages', 'placement' => 'all', 'is_active' => true]);

        $theaterBanners = Banner::active()->forPlacement('theater')->pluck('title')->all();

        $this->assertContains('Theater Only', $theaterBanners);
        $this->assertContains('All Pages', $theaterBanners);
        $this->assertNotContains('Home Only', $theaterBanners);
    }
}
