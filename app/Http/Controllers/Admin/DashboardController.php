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
    public function stats()
    {
        try {
            $stats = $this->dashboardService->getStats();

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
