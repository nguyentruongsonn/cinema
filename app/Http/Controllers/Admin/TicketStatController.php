<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\TicketAnalyticsService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TicketStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TicketAnalyticsService $ticketAnalyticsService) {}

    /**
     * Return ticket statistics for the given date range.
     * GET /api/v1/admin/tickets/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
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

            // Enforce maximum 366-day range to prevent unbounded analytics queries
            if (Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) > 366) {
                throw ValidationException::withMessages([
                    'end_date' => 'Date range cannot exceed 366 days.',
                ]);
            }

            $stats = $this->ticketAnalyticsService->getStats($startDate, $endDate);

            return $this->successResponse($stats, 'Ticket stats retrieved successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid ticket stat filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve ticket stats', [
                'actor_id' => Auth::id(),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to retrieve ticket stats', 500);
        }
    }
}
