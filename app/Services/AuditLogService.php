<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'remember_token',
        'refresh_token',
        'reset_token',
        'verification_token',
        'token',
        'secret',
        'api_key',
        'api_secret',
        'webhook_secret',
        'gateway_secret',
        'client_secret',
        'access_token',
        'qr_code',
        'card_number',
        'cvv',
        'pin',
    ];

    private const SYSTEM_AUDITABLE_TYPE = 'system';
    private const SYSTEM_AUDITABLE_ID = 0;

    public function __construct(
        private readonly RequestContextService $requestContext
    ) {}

    /**
     * Record an audit log entry with automatic sensitive data redaction.
     *
     * @param User $actor The authenticated user performing the action
     * @param string $action Action identifier (e.g., 'user.created', 'order.paid')
     * @param Model $auditable The affected model instance
     * @param array $oldValues State before change (will be sanitized)
     * @param array $newValues State after change (will be sanitized)
     * @return AuditLog The created immutable audit record
     */
    public function record(
        User $actor,
        string $action,
        Model $auditable,
        array $oldValues = [],
        array $newValues = []
    ): AuditLog {
        return AuditLog::record([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $this->requestContext->ip(),
            'user_agent' => $this->truncateUserAgent($this->requestContext->userAgent()),
            'request_id' => $this->getRequestId(),
        ]);
    }

    /**
     * Record an audit log for actions without a specific auditable model.
     *
     * @param User $actor The authenticated user performing the action
     * @param string $action Action identifier
     * @param array $context Optional context data (will be sanitized)
     * @return AuditLog The created immutable audit record
     */
    public function recordAction(
        User $actor,
        string $action,
        array $context = []
    ): AuditLog {
        return AuditLog::record([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => self::SYSTEM_AUDITABLE_TYPE,
            'auditable_id' => self::SYSTEM_AUDITABLE_ID,
            'old_values' => null,
            'new_values' => $this->sanitize($context),
            'ip_address' => $this->requestContext->ip(),
            'user_agent' => $this->truncateUserAgent($this->requestContext->userAgent()),
            'request_id' => $this->getRequestId(),
        ]);
    }

    /**
     * Record an audit log for system/automated actions (no authenticated user).
     *
     * @param string $action Action identifier (e.g., 'order.expired', 'seat_hold.cleaned')
     * @param Model|null $auditable The affected model instance
     * @param array $context Optional context data (will be sanitized)
     * @return AuditLog The created immutable audit record
     */
    public function recordSystemAction(
        string $action,
        ?Model $auditable = null,
        array $context = []
    ): AuditLog {
        return AuditLog::record([
            'user_id' => null,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass() ?? self::SYSTEM_AUDITABLE_TYPE,
            'auditable_id' => $auditable?->getKey() ?? self::SYSTEM_AUDITABLE_ID,
            'old_values' => null,
            'new_values' => $this->sanitize($context),
            'ip_address' => null,
            'user_agent' => 'System',
            'request_id' => $this->getRequestId(),
        ]);
    }

    public function recordSystemChange(
        string $action,
        Model $auditable,
        array $oldValues = [],
        array $newValues = []
    ): AuditLog {
        return AuditLog::record([
            'user_id' => null,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => null,
            'user_agent' => 'System',
            'request_id' => $this->getRequestId(),
        ]);
    }

    /**
     * Sanitize an array by redacting sensitive keys.
     *
     * @param array $values Input array potentially containing sensitive data
     * @return array Sanitized array with sensitive values replaced
     */
    private function sanitize(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn ($value, $key) => [
                $key => $this->isSensitiveKey($key) ? '[REDACTED]' : $value,
            ])
            ->all();
    }

    /**
     * Check if a key name indicates sensitive data.
     *
     * @param string $key The array key to check
     * @return bool True if the key should be redacted
     */
    private function isSensitiveKey(string $key): bool
    {
        $lowercaseKey = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($lowercaseKey, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Truncate user agent string to prevent excessively long database values.
     *
     * @param string|null $userAgent Raw user agent from request
     * @return string|null Truncated user agent (max 500 chars)
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return Str::limit($userAgent, 500, '');
    }

    /**
     * Get or generate a request ID for correlation tracking.
     *
     * @return string|null Request correlation ID
     */
    private function getRequestId(): ?string
    {
        return $this->requestContext->requestId();
    }
}
