<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'platform',
        'browser',
        'login_method',
        'success',
        'failure_reason',
        'country',
        'city',
        'session_token',
        'logged_in_at',
        'logged_out_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parse user agent to extract device, platform, browser info.
     */
    public static function parseUserAgent(?string $userAgent): array
    {
        $result = [
            'device_type' => 'desktop',
            'platform' => null,
            'browser' => null,
        ];

        if (!$userAgent) {
            return $result;
        }

        // Device type
        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $userAgent)) {
            $result['device_type'] = preg_match('/ipad|tablet/i', $userAgent) ? 'tablet' : 'mobile';
        }

        // Platform
        if (preg_match('/windows/i', $userAgent)) {
            $result['platform'] = 'Windows';
        } elseif (preg_match('/macintosh|mac os/i', $userAgent)) {
            $result['platform'] = 'macOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $result['platform'] = 'Linux';
        } elseif (preg_match('/android/i', $userAgent)) {
            $result['platform'] = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $result['platform'] = 'iOS';
        }

        // Browser
        if (preg_match('/chrome/i', $userAgent) && !preg_match('/edg/i', $userAgent)) {
            $result['browser'] = 'Chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $result['browser'] = 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) {
            $result['browser'] = 'Safari';
        } elseif (preg_match('/edg/i', $userAgent)) {
            $result['browser'] = 'Edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $result['browser'] = 'Opera';
        }

        return $result;
    }

    /**
     * Record a login attempt.
     */
    public static function record(
        int $userId,
        string $ipAddress,
        ?string $userAgent,
        string $loginMethod,
        bool $success,
        ?string $failureReason = null,
        ?string $sessionToken = null
    ): self {
        $uaInfo = self::parseUserAgent($userAgent);

        return self::create([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $uaInfo['device_type'],
            'platform' => $uaInfo['platform'],
            'browser' => $uaInfo['browser'],
            'login_method' => $loginMethod,
            'success' => $success,
            'failure_reason' => $failureReason,
            'session_token' => $sessionToken,
            'logged_in_at' => now(),
        ]);
    }

    /**
     * Mark a login history record as logged out.
     */
    public function markLoggedOut(): bool
    {
        return $this->update(['logged_out_at' => now()]);
    }
}
