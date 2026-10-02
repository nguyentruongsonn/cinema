<?php

namespace Tests\Feature;

use Tests\TestCase;

class TheatersPageRenderTest extends TestCase
{
    public function test_theaters_page_renders_all_required_sections(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
        $response->assertSee('theaters-hero-slider', false);
        $response->assertSee('theaters-info-bar', false);
        $response->assertSee('theaters-showtimes-section', false);
        $response->assertSee('theaters-split-section', false);
        $response->assertSee('theaterPricingContainer', false);
        $response->assertSee('theaterDetailsContainer', false);
    }
}
