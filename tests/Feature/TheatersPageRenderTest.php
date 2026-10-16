<?php

namespace Tests\Feature;

use Tests\TestCase;

class TheatersPageRenderTest extends TestCase
{
    public function test_theaters_page_renders_all_required_sections(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
        $response->assertSee('theater-section', false);
        $response->assertSee('hero-section', false);
        $response->assertSee('theaters-info-bar', false);
        $response->assertSee('theaters-showtimes-section', false);
        $response->assertSee('theaters-split-section', false);
        $response->assertSee('theaterPricingContainer', false);
        $response->assertSee('theaterDetailsContainer', false);
        $response->assertSee('heading-accent', false);
        $response->assertSee('btn-directions', false);
        $response->assertSee('theaterMapBox', false);
        $response->assertSee('showtime-skeleton-card', false);
        $response->assertDontSee('id="heroContent"', false);
        $response->assertDontSee('badge-pricing-update', false);
        $response->assertDontSee('30 Bờ Bao Tân Thắng', false);
    }
}
