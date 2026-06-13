<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\FoodAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class FoodStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FoodAnalyticsService $foodAnalyticsService) {}

    /**
     * Return food analytics for the given date range and optional type filter.
     * GET /api/v1/admin/food/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&type=popcorn|drink|snack
     */
    public function stats(StatFilterRequest $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate   = $request->input('end_date', now()->toDateString());
            $type      = $request->input('type');  // optional

            $stats = $this->foodAnalyticsService->getStats($startDate, $endDate, $type);

            return $this->successResponse($stats, 'Food stats retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve food stats: ' . $e->getMessage(), 500);
        }
    }
}
