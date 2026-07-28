<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\ComboAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ComboStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ComboAnalyticsService $comboAnalyticsService) {}

    /**
     * Return combo package statistics for the given date range.
     * GET /api/v1/admin/combos/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function stats(StatFilterRequest $request): JsonResponse
    {
        abort_unless(
            Auth::user()?->isAdmin() || Auth::user()?->hasPermission('analytics.view'),
            403
        );

        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());

            $stats = $this->comboAnalyticsService->getStats($startDate, $endDate, ComboAnalyticsService::TYPE_COMBO);

            return $this->successResponse($stats, 'Combo package stats retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Combo stats request failed', [
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'actor_id' => Auth::id(),
                'exception' => $e,
            ]);

            return $this->errorResponse('Failed to retrieve combo stats.', 500);
        }
    }
}
