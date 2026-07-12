<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\ComboAnalyticsService;
use App\Traits\ApiResponse;

class ComboStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ComboAnalyticsService $comboAnalyticsService) {}

    /**
     * Return combo package statistics for the given date range.
     * GET /api/v1/admin/combos/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function stats(StatFilterRequest $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate   = $request->input('end_date', now()->toDateString());

            $stats = $this->comboAnalyticsService->getStats($startDate, $endDate, 'combo');

            return $this->successResponse($stats, 'Combo package stats retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve combo stats: ' . $e->getMessage(), 500);
        }
    }
}
