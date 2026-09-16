<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OperationalAlertService
{
    public function __construct(private readonly CacheRepository $cache) {}

    /** @param array<string, mixed> $health */
    public function send(string $alertKey, array $health): bool
    {
        $url = trim((string) config('observability.alerts.webhook_url'));
        if (! config('observability.alerts.enabled') || $url === '') {
            return false;
        }

        $cooldownSeconds = max(60, (int) config('observability.alerts.cooldown_seconds', 300));
        $cacheKey = 'operations:alert:'.hash('sha256', $alertKey);
        if (! $this->cache->add($cacheKey, true, now()->addSeconds($cooldownSeconds))) {
            return false;
        }

        try {
            Http::asJson()
                ->timeout(max(1, (int) config('observability.alerts.timeout_seconds', 5)))
                ->retry(2, 250)
                ->post($url, [
                    'event' => $alertKey,
                    'status' => 'unhealthy',
                    'application' => (string) config('app.name'),
                    'environment' => (string) app()->environment(),
                    'generated_at' => now()->toIso8601String(),
                    'health' => $health,
                ])
                ->throw();

            return true;
        } catch (Throwable $exception) {
            $this->cache->forget($cacheKey);
            Log::error('Operational alert delivery failed', [
                'event' => $alertKey,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
