<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Return summary statistics for the admin dashboard.
     */
    public function stats(\Illuminate\Http\Request $request)
    {
        try {
            $start = $request->input('start');
            $end = $request->input('end');
            
            // If no dates provided, default to current month
            if (!$start || !$end) {
                $start = now()->startOfMonth()->toDateString();
                $end = now()->toDateString();
            }
            
            $stats = $this->dashboardService->getStats($start, $end);

            return $this->successResponse(
                $stats,
                'Admin dashboard stats retrieved successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard stats: ' . $e->getMessage(),
                500
            );
        }
    }
}
