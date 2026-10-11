<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnifiedTheatersNavigationTest extends TestCase
{
    public function test_prices_route_permanently_redirects_to_theaters(): void
    {
        $response = $this->get('/prices');
        $response->assertStatus(301);
        $response->assertRedirect('/theaters');
    }

    public function test_theaters_route_is_accessible(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
    }

    public function test_header_contains_only_one_theater_link_without_prices_link(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
        $response->assertSee('Rạp');
        $response->assertSee('GÓC ĐIỆN ẢNH');
        $response->assertDontSee('>Giá vé<', false);
    }
}
