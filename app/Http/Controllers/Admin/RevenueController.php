<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\RevenueService;
use App\Traits\ApiResponse;

class RevenueController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RevenueService $revenueService) {}

    /**
     * Return revenue statistics for the given date range.
     * GET /api/v1/admin/revenue/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function stats(StatFilterRequest $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate   = $request->input('end_date', now()->toDateString());

            $stats = $this->revenueService->getStats($startDate, $endDate);

            return $this->successResponse($stats, 'Revenue stats retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve revenue stats: ' . $e->getMessage(), 500);
        }
    }
}

