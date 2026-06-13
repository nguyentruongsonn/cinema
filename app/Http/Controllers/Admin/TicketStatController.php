<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\TicketAnalyticsService;
use App\Traits\ApiResponse;

class TicketStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TicketAnalyticsService $ticketAnalyticsService) {}

    /**
     * Return ticket statistics for the given date range.
     * GET /api/v1/admin/tickets/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function stats(StatFilterRequest $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate   = $request->input('end_date', now()->toDateString());

            $stats = $this->ticketAnalyticsService->getStats($startDate, $endDate);

            return $this->successResponse($stats, 'Ticket stats retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve ticket stats: ' . $e->getMessage(), 500);
        }
    }
}
