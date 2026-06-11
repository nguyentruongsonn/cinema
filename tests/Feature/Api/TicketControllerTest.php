<?php

namespace Tests\Feature\Api;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 5: TicketController API Tests
 * Tests ticket listing and detail endpoints with authentication
 */
class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create();
    }

    #[Test]
    public function authenticated_user_can_list_their_tickets()
    {
        // Create tickets for authenticated user
        $tickets = Ticket::factory()
            ->count(3)
            ->create(['user_id' => $this->user->id]);

        // Create tickets for different user (should not be returned)
        Ticket::factory()
            ->count(2)
            ->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'ticket_code',
                            'status',
                            'user_id',
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ]
                ],
                'message',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.data'));
    }

    #[Test]
    public function unauthenticated_user_cannot_list_tickets()
    {
        $response = $this->getJson('/api/v1/tickets');

        $response->assertUnauthorized();
    }

    #[Test]
    public function tickets_are_paginated()
    {
        // Create 20 tickets
        Ticket::factory()
            ->count(20)
            ->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets?per_page=10');

        $response->assertOk();

        $this->assertEquals(10, count($response->json('data.data')));
        $this->assertEquals(1, $response->json('data.meta.current_page'));
        $this->assertEquals(2, $response->json('data.meta.last_page'));
        $this->assertEquals(20, $response->json('data.meta.total'));
    }

    #[Test]
    public function tickets_can_be_filtered_by_status()
    {
        // Create tickets with different statuses
        Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'valid',
        ]);

        Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'used',
        ]);

        Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'cancelled',
        ]);

        // Filter by 'valid' status
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets?status=valid');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('valid', $response->json('data.data.0.status'));
    }

    #[Test]
    public function invalid_status_filter_returns_error()
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets?status=invalid_status');

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    #[Test]
    public function authenticated_user_can_view_their_ticket_details()
    {
        $ticket = Ticket::factory()->create([
            'user_id' => $this->user->id,
            'ticket_code' => 'TEST-123',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets/TEST-123');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'ticket_code',
                    'status',
                    'user_id',
                ],
                'message',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('TEST-123', $response->json('data.ticket_code'));
    }

    #[Test]
    public function user_cannot_view_another_users_ticket()
    {
        $otherUser = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'user_id' => $otherUser->id,
            'ticket_code' => 'OTHER-123',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets/OTHER-123');

        $response->assertNotFound();
        $this->assertFalse($response->json('success'));
    }

    #[Test]
    public function returns_404_for_non_existent_ticket()
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets/NON-EXISTENT');

        $response->assertNotFound();
        $this->assertFalse($response->json('success'));
    }

    #[Test]
    public function tickets_include_related_data()
    {
        $ticket = Ticket::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets');

        $response->assertOk();

        $ticketData = $response->json('data.data.0');

        // Verify relationships are loaded
        $this->assertArrayHasKey('showtime', $ticketData);
        $this->assertArrayHasKey('seat', $ticketData);
        $this->assertArrayHasKey('order', $ticketData);
    }

    #[Test]
    public function per_page_parameter_is_capped_at_50()
    {
        // Create 100 tickets
        Ticket::factory()
            ->count(100)
            ->create(['user_id' => $this->user->id]);

        // Request 100 per page (should be capped at 50)
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/tickets?per_page=100');

        $response->assertOk();

        // Should return max 50 items
        $this->assertLessThanOrEqual(50, count($response->json('data.data')));
        $this->assertEquals(50, $response->json('data.meta.per_page'));
    }
}