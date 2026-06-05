<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'device_name',
        'ip_address',
        'user_agent',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the refresh token
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new refresh token
     */
    public static function generate(
        int $userId,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        // Generate random token (plain text)
        $plainToken = Str::random(64);

        // Hash token for storage
        $hashedToken = hash('sha256', $plainToken);

        // Create token record
        $refreshToken = self::create([
            'user_id' => $userId,
            'token' => $hashedToken,
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => now()->addDays((int) config('auth.refresh_token_ttl', 30)),
        ]);

        return [
            'plain_token' => $plainToken,
            'model' => $refreshToken,
        ];
    }

    /**
     * Find token by plain text token
     */
    public static function findByPlainToken(string $plainToken): ?self
    {
        $hashedToken = hash('sha256', $plainToken);

        return self::query()
            ->where('token', '=', $hashedToken)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at > now();
    }

    /**
     * Revoke the token
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Revoke all tokens for a user
     */
    public static function revokeAllForUser(int $userId): void
    {
        self::query()
            ->where('user_id', '=', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Clean up expired and revoked tokens
     */
    public static function cleanup(): int
    {
        $cutoffDate = now()->subDays(7);

        return self::query()
            ->where(function ($query) use ($cutoffDate) {
                $query->where('expires_at', '<', $cutoffDate)
                    ->orWhere('revoked_at', '<', $cutoffDate);
            })
            ->delete();
    }
}
