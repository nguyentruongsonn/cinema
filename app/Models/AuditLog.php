<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditLog extends Model
{
    public const ALLOWED_AUDITABLE_TYPES = [
        'banner',
        'branch',
        'combo',
        'movie',
        'order',
        'payment',
        'post',
        'product',
        'promotion',
        'role',
        'screen',
        'seat_layout_template',
        'system',
        'theater',
        'user',
    ];

    protected $guarded = ['*'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
    ];

    protected $hidden = [
        'ip_address',
        'user_agent',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit logs are immutable.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit logs cannot be deleted.');
        });
    }

    public static function record(array $attributes): self
    {
        return self::query()->forceCreate($attributes);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAuditable(Builder $query, string $type, int $id): Builder
    {
        return $query->where('auditable_type', $type)
            ->where('auditable_id', $id);
    }

    public function scopeForRequest(Builder $query, string $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }
}
