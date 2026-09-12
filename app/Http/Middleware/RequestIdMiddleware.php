<?php

namespace App\Http\Middleware;

use App\Services\Observability\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        if (config('sentry.dsn')) {
            configureScope(function (Scope $scope) use ($requestId): void {
                $scope->setTag('request_id', $requestId);
            });
        }

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $status = isset($response) ? $response->getStatusCode() : 500;

            app(MetricsService::class)->recordRequest($durationMs, $status);

            if (isset($response) && config('observability.server_timing_header')) {
                $response->headers->set('Server-Timing', 'app;dur='.round($durationMs, 2));
            }
        }
    }

    private function resolveRequestId(Request $request): string
    {
        $headerValue = $request->header('X-Request-ID');

        if (is_string($headerValue)) {
            $requestId = Str::limit(trim($headerValue), 36, '');

            if (preg_match('/^[A-Za-z0-9._:-]{1,36}$/', $requestId) === 1) {
                return $requestId;
            }
        }

        return (string) Str::uuid();
    }
}
