<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AuditLog Model & Service Tests
 * Based on: REVIEWS/files/AuditLog_model_review.md
 * Tests immutability, redaction, morph map, mass assignment protection
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected AuditLogService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        $this->auditService = app(AuditLogService::class);
    }

    #[Test]
    public function it_prevents_audit_log_updates()
    {
        $log = AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit logs are immutable');

        // Use direct property assignment to trigger updating event
        $log->action = 'updated';
        $log->save();
    }

    #[Test]
    public function it_prevents_audit_log_deletion()
    {
        $log = AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit logs cannot be deleted');

        $log->delete();
    }

    #[Test]
    public function it_protects_against_mass_assignment()
    {
        // Attempt mass assignment - should only work via forceCreate
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_allows_creation_via_record_method()
    {
        $log = AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertTrue($log->exists);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals('created', $log->action);
    }

    #[Test]
    public function it_uses_morph_map_short_names()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $log = AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'order',
            'auditable_id' => $order->id,
        ]);

        // Should store short name, not FQCN
        $this->assertEquals('order', $log->auditable_type);
        $this->assertNotEquals('App\\Models\\Order', $log->auditable_type);

        // Should be able to resolve relationship
        $this->assertInstanceOf(Order::class, $log->auditable);
        $this->assertEquals($order->id, $log->auditable->id);
    }

    #[Test]
    public function it_hides_sensitive_fields_from_serialization()
    {
        $log = AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $array = $log->toArray();

        $this->assertArrayNotHasKey('ip_address', $array);
        $this->assertArrayNotHasKey('user_agent', $array);
        $this->assertArrayHasKey('action', $array);
    }

    #[Test]
    public function audit_service_redacts_sensitive_fields()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $this->auditService->record(
            $this->user,
            'payment.created',
            $order,
            [
                'status' => 'pending',
                'card_number' => '4111111111111111',
                'cvv' => '123',
            ],
            [
                'status' => 'paid',
                'card_number' => '4111111111111111',
                'cvv' => '456',
            ]
        );

        $log = AuditLog::query()->latest('id')->first();

        // Sensitive fields should be redacted
        $this->assertEquals('[REDACTED]', $log->old_values['card_number']);
        $this->assertEquals('[REDACTED]', $log->old_values['cvv']);
        $this->assertEquals('[REDACTED]', $log->new_values['card_number']);
        $this->assertEquals('[REDACTED]', $log->new_values['cvv']);

        // Non-sensitive fields should be preserved
        $this->assertEquals('pending', $log->old_values['status']);
        $this->assertEquals('paid', $log->new_values['status']);
    }

    #[Test]
    public function audit_service_records_request_metadata()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $this->auditService->record(
            $this->user,
            'order.cancelled',
            $order
        );

        $log = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($log->request_id);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals('order.cancelled', $log->action);
    }

    #[Test]
    public function scope_by_action_filters_correctly()
    {
        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);

        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);

        $created = AuditLog::byAction('created')->count();
        $updated = AuditLog::byAction('updated')->count();

        $this->assertEquals(1, $created);
        $this->assertEquals(1, $updated);
    }

    #[Test]
    public function scope_by_user_filters_correctly()
    {
        $user2 = User::factory()->create();

        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
        ]);

        AuditLog::record([
            'user_id' => $user2->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $user2->id,
        ]);

        $user1Logs = AuditLog::byUser($this->user->id)->count();
        $user2Logs = AuditLog::byUser($user2->id)->count();

        $this->assertEquals(1, $user1Logs);
        $this->assertEquals(1, $user2Logs);
    }

    #[Test]
    public function scope_by_auditable_filters_correctly()
    {
        $order1 = Order::factory()->create(['user_id' => $this->user->id]);
        $order2 = Order::factory()->create(['user_id' => $this->user->id]);

        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'order',
            'auditable_id' => $order1->id,
        ]);

        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'order',
            'auditable_id' => $order2->id,
        ]);

        $order1Logs = AuditLog::byAuditable('order', $order1->id)->count();
        $order2Logs = AuditLog::byAuditable('order', $order2->id)->count();

        $this->assertEquals(1, $order1Logs);
        $this->assertEquals(1, $order2Logs);
    }

    #[Test]
    public function scope_for_request_filters_correctly()
    {
        $requestId = 'test-request-123';

        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
            'request_id' => $requestId,
        ]);

        AuditLog::record([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'auditable_type' => 'user',
            'auditable_id' => $this->user->id,
            'request_id' => 'different-request',
        ]);

        $requestLogs = AuditLog::forRequest($requestId)->count();

        $this->assertEquals(1, $requestLogs);
    }
}