<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AuthIdentitySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_create_or_promote_a_super_admin(): void
    {
        [$admin, $regularUser, $superAdminRole] = $this->identityFixture();
        $this->actingAs($admin);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'username' => 'escalated',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role_id' => $superAdminRole->id,
        ])->assertStatus(422)->assertJsonValidationErrors('role_id');

        $this->putJson("/api/v1/admin/users/{$regularUser->id}", [
            'role_id' => $superAdminRole->id,
        ])->assertStatus(422)->assertJsonValidationErrors('role_id');

        $this->assertFalse($regularUser->fresh()->hasRole('super-admin'));
    }

    public function test_admin_cannot_modify_or_reset_super_admin_credentials(): void
    {
        [$admin, , $superAdminRole] = $this->identityFixture();
        $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
        $originalPassword = $superAdmin->password;
        $this->actingAs($admin);

        $this->putJson("/api/v1/admin/users/{$superAdmin->id}", [
            'email' => 'taken-over@example.com',
        ])->assertForbidden();

        $this->postJson("/api/v1/admin/users/{$superAdmin->id}/reset-password", [
            'password' => 'ChangedPassword123',
            'password_confirmation' => 'ChangedPassword123',
        ])->assertForbidden();

        $this->assertSame($originalPassword, $superAdmin->fresh()->password);
    }

    public function test_generic_user_update_cannot_change_password(): void
    {
        [$admin, $regularUser] = $this->identityFixture();
        $originalPassword = $regularUser->password;
        $this->actingAs($admin);

        $this->putJson("/api/v1/admin/users/{$regularUser->id}", [
            'password' => 'ChangedPassword123',
            'password_confirmation' => 'ChangedPassword123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertSame($originalPassword, $regularUser->fresh()->password);
    }

    public function test_forgot_password_response_does_not_enumerate_accounts(): void
    {
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => strtoupper($user->email)]);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_email_verification_token_is_signed_and_expires(): void
    {
        $user = User::factory()->unverified()->create();
        $service = app(AuthService::class);
        $token = $service->createEmailVerificationToken($user);

        $this->postJson('/api/v1/auth/verify-email', [
            'id' => $user->id,
            'hash' => $token,
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);

        $expiredUser = User::factory()->unverified()->create();
        $expiredToken = $service->createEmailVerificationToken($expiredUser, now()->subSecond()->getTimestamp());

        $this->postJson('/api/v1/auth/verify-email', [
            'id' => $expiredUser->id,
            'hash' => $expiredToken,
        ])->assertStatus(400);
        $this->assertNull($expiredUser->fresh()->email_verified_at);
    }

    public function test_verification_notification_is_sent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'api');

        app(AuthService::class)->sendEmailVerification();

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_refresh_token_rotation_rejects_replay(): void
    {
        $user = User::factory()->create(['status' => true]);
        $plainToken = RefreshToken::generate($user->id)['plain_token'];
        $service = app(AuthService::class);

        $result = $service->refreshAccessToken($plainToken, '127.0.0.1', 'test-agent');
        $this->assertNotEmpty($result['refresh_token']);

        $this->expectException(RuntimeException::class);
        $service->refreshAccessToken($plainToken, '127.0.0.1', 'test-agent');
    }

    public function test_registration_normalizes_email_and_rejects_duplicate(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Normalized User',
            'email' => '  Mixed.Case@Example.COM ',
            'username' => 'normalized_user',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'mixed.case@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate User',
            'email' => 'MIXED.CASE@EXAMPLE.COM',
            'username' => 'duplicate_user',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    private function identityFixture(): array
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $userRole = Role::create(['name' => 'User', 'slug' => 'user']);
        $superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);

        return [
            User::factory()->create(['role_id' => $adminRole->id, 'status' => true]),
            User::factory()->create(['role_id' => $userRole->id, 'status' => true]),
            $superAdminRole,
        ];
    }
}
