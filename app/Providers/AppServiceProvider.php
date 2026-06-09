<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Authentication endpoints - strict limit
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // General API endpoints
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Order/Booking endpoints - moderate limit
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Payment endpoints - strict limit for financial operations
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Seat lock/unlock - allow rapid seat selection
        RateLimiter::for('seats', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Webhook callbacks - per hour limit by IP
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perHour(100)->by($request->ip());
        });

        $this->configureSlowQueryLogging();
    }

    /**
     * Log slow database queries for production observability.
     *
     * Bindings are intentionally not logged because they may contain PII
     * such as emails, phone numbers, tokens, or customer-entered data.
     */
    private function configureSlowQueryLogging(): void
    {
        if (!filter_var(env('SLOW_QUERY_LOG_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $thresholdMs = (float) env('SLOW_QUERY_THRESHOLD_MS', 100);

        DB::listen(function ($query) use ($thresholdMs) {
            if ($query->time < $thresholdMs) {
                return;
            }

            Log::warning('Slow database query detected', [
                'time_ms' => round($query->time, 2),
                'connection' => $query->connectionName,
                'sql' => Str::limit($query->sql, 1000),
                'route' => request()?->route()?->getName(),
                'method' => request()?->method(),
                'path' => request()?->path(),
                'user_id' => optional(auth()->user())->id,
            ]);
        });
    }
}
