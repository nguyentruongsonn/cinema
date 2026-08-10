<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\OpenApiService;
use App\Services\Observability\MetricsService;
use App\Services\Observability\QueueHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OperationalHealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
        ]);
    }

    public function ready(QueueHealthService $queueHealth): JsonResponse
    {
        $checks = [
            'database' => fn () => DB::select('select 1'),
            'cache' => function (): void {
                $key = 'health:'.Str::uuid();
                Cache::put($key, 'ok', 10);

                if (Cache::pull($key) !== 'ok') {
                    throw new \RuntimeException('Cache read/write check failed.');
                }
            },
        ];
        $results = [];
        $ready = true;

        foreach ($checks as $name => $check) {
            try {
                $check();
                $results[$name] = 'ok';
            } catch (Throwable) {
                $results[$name] = 'unavailable';
                $ready = false;
            }
        }

        if ((bool) config('queue.monitoring.include_in_readiness', true)) {
            $queueSnapshot = $queueHealth->snapshot();
            $results['queue'] = $queueSnapshot['healthy'] ? 'ok' : 'unavailable';
            $ready = $ready && $queueSnapshot['healthy'];
        }

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $results,
        ], $ready ? 200 : 503);
    }

    public function metrics(MetricsService $metrics): Response
    {
        return response($metrics->toPrometheus(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function openApi(OpenApiService $openApi): JsonResponse
    {
        return response()->json($openApi->document(), 200, [
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
