<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdempotencyKey extends Model
{
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'key',
        'user_id',
        'payment_id',
        'request_path',
        'request_method',
        'request_data',
        'response_data',
        'response_status',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'payment_id' => 'integer',
        'request_data' => 'json',
        'response_data' => 'json',
        'response_status' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship to Payment model
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Relationship to User model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for valid (non-expired) keys
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to find by key
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * Scope for completed operations
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Generate a secure idempotency key (UUID v4)
     */
    public static function generateKey(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Validate idempotency key format (UUID v4)
     */
    public static function validateKeyFormat(string $key): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $key
        );
    }

    /**
     * Execute operation with idempotency protection.
     *
     * This method ensures that an operation executes exactly once per idempotency key.
     * Duplicate requests with the same key return the cached response.
     *
     * @param string $key Idempotency key (UUID v4 format)
     * @param callable $operation Operation to execute, receives IdempotencyKey record
     * @param array $requestContext Request metadata (path, method, data)
     * @return array ['status' => int, 'data' => mixed, 'idempotent' => bool]
     * @throws \InvalidArgumentException If key format is invalid
     * @throws \DomainException If key reused with different payload
     * @throws \RuntimeException If operation is in progress
     */
    public static function executeIdempotent(
        string $key,
        callable $operation,
        array $requestContext = []
    ): array {
        // Validate key format
        if (!self::validateKeyFormat($key)) {
            throw new \InvalidArgumentException('Invalid idempotency key format. Expected UUID v4.');
        }

        $claimExisting = function (self $existing) use ($requestContext): array {
            if (isset($requestContext['data']) &&
                $existing->request_data !== null &&
                json_encode($requestContext['data']) !== json_encode($existing->request_data)) {
                throw new \DomainException(
                    'Idempotency key reused with different request payload. ' .
                    'Each unique operation must use a unique idempotency key.'
                );
            }

            if ($existing->status === self::STATUS_COMPLETED) {
                return ['result' => [
                    'status' => $existing->response_status ?? 200,
                    'data' => $existing->response_data,
                    'idempotent' => true,
                ]];
            }

            if ($existing->status === self::STATUS_PROCESSING) {
                throw new \RuntimeException(
                    'Operation already in progress. Please retry in a few moments.',
                    409
                );
            }

            $existing->update([
                'status' => self::STATUS_PROCESSING,
                'response_data' => null,
                'response_status' => null,
            ]);

            return ['record' => $existing];
        };

        try {
            $claim = DB::transaction(function () use ($key, $requestContext, $claimExisting): array {
                $existing = self::query()->where('key', $key)->lockForUpdate()->first();

                if ($existing) {
                    return $claimExisting($existing);
                }

                return ['record' => self::create([
                    'key' => $key,
                    'user_id' => Auth::id(),
                    'request_path' => $requestContext['path'] ?? null,
                    'request_method' => $requestContext['method'] ?? null,
                    'request_data' => $requestContext['data'] ?? null,
                    'status' => self::STATUS_PROCESSING,
                    'expires_at' => now()->addHours(24),
                ])];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (! self::isIdempotencyKeyDuplicate($e)) {
                throw $e;
            }

            $claim = DB::transaction(function () use ($key, $claimExisting): array {
                return $claimExisting(
                    self::query()->where('key', $key)->lockForUpdate()->firstOrFail()
                );
            });
        }

        if (isset($claim['result'])) {
            return $claim['result'];
        }

        $record = $claim['record'];

        try {
            $result = $operation($record);

            DB::transaction(function () use ($record, $result): void {
                self::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail()->update([
                    'response_data' => $result['data'] ?? $result,
                    'response_status' => $result['status'] ?? 200,
                    'status' => self::STATUS_COMPLETED,
                    'payment_id' => $result['payment_id'] ?? null,
                ]);
            });

            return [
                'status' => $result['status'] ?? 200,
                'data' => $result['data'] ?? $result,
                'idempotent' => false,
            ];
        } catch (\Throwable $e) {
            DB::transaction(function () use ($record, $e): void {
                self::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail()->update([
                    'status' => self::STATUS_FAILED,
                    'response_data' => [
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                    'response_status' => method_exists($e, 'getStatusCode')
                        ? $e->getStatusCode()
                        : 500,
                ]);
            });

            throw $e;
        }
    }

    /**
     * Determine whether a query exception was caused by the idempotency key unique constraint.
     */
    private static function isIdempotencyKeyDuplicate(\Illuminate\Database\QueryException $e): bool
    {
        $message = $e->getMessage();

        return $e->getCode() === '23000'
            && (
                str_contains($message, 'idempotency_keys_key_unique')
                || str_contains($message, 'idempotency_keys.key')
                || str_contains($message, 'idempotency_keys_key_unique')
                || str_contains($message, 'UNIQUE constraint failed: idempotency_keys.key')
                || str_contains($message, 'Duplicate entry')
            );
    }

    /**
     * Handle a repeated idempotency key.
     */
    private static function handleDuplicateKey(
        string $key,
        callable $operation,
        array $requestContext
    ): array {
        $existing = self::lockForUpdate()->where('key', $key)->firstOrFail();

        if (isset($requestContext['data']) &&
            $existing->request_data !== null &&
            json_encode($requestContext['data']) !== json_encode($existing->request_data)) {
            throw new \DomainException(
                'Idempotency key reused with different request payload. ' .
                'Each unique operation must use a unique idempotency key.'
            );
        }

        if ($existing->status === self::STATUS_PROCESSING) {
            throw new \RuntimeException(
                'Operation already in progress. Please retry in a few moments.',
                409
            );
        }

        if ($existing->status === self::STATUS_FAILED) {
            $existing->update(['status' => self::STATUS_PROCESSING]);

            try {
                $result = $operation($existing);

                $existing->update([
                    'response_data' => $result['data'] ?? $result,
                    'response_status' => $result['status'] ?? 200,
                    'status' => self::STATUS_COMPLETED,
                    'payment_id' => $result['payment_id'] ?? null,
                ]);

                return [
                    'status' => $result['status'] ?? 200,
                    'data' => $result['data'] ?? $result,
                    'idempotent' => false,
                ];
            } catch (\Throwable $e) {
                $existing->update([
                    'status' => self::STATUS_FAILED,
                    'response_data' => [
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                    'response_status' => method_exists($e, 'getStatusCode')
                        ? $e->getStatusCode()
                        : 500,
                ]);

                throw $e;
            }
        }

        if ($existing->status === self::STATUS_COMPLETED) {
            return [
                'status' => $existing->response_status ?? 200,
                'data' => $existing->response_data,
                'idempotent' => true,
            ];
        }

        throw new \RuntimeException('Unexpected idempotency record status: ' . $existing->status);
    }
}
