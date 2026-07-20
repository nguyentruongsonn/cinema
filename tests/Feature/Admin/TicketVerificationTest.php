<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_ticket_verification_is_atomic_and_uses_checked_in_at(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/tickets/verify', ['ticket_code' => $ticket->ticket_code])
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => Ticket::STATUS_USED,
        ]);
        $this->assertNotNull($ticket->fresh()->checked_in_at);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/tickets/verify', ['ticket_code' => $ticket->ticket_code])
            ->assertStatus(409);
    }
}
