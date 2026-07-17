<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
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
