<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalMetricsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('observability.metrics_token');
        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-Metrics-Token'));

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(404);
        }

        return $next($request);
    }
}
