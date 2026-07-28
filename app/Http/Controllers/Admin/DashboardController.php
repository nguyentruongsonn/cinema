<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Return summary statistics for the admin dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewDashboardMetrics');

            $validated = $request->validate([
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            $start = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
            $end = $validated['end_date'] ?? now()->toDateString();

            // Enforce maximum 366-day range
            if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) > 366) {
                throw ValidationException::withMessages([
                    'end_date' => 'Date range cannot exceed 366 days.',
                ]);
            }

            $stats = $this->dashboardService->getStats($start, $end);

            return $this->successResponse(
                $stats,
                'Admin dashboard stats retrieved successfully'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid dashboard filter parameters', 422, $e->errors());
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view dashboard metrics', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve dashboard stats', [
                'actor_id' => Auth::id(),
                'start' => $start ?? null,
                'end' => $end ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to retrieve dashboard stats', 500);
        }
    }
}
