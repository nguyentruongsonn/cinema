<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\RevenueService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RevenueController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RevenueService $revenueService) {}

    /**
     * Return revenue statistics for the given validated date range.
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

            // Enforce maximum 366-day range to prevent unbounded financial reporting queries
            if (Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) > 366) {
                throw ValidationException::withMessages([
                    'end_date' => 'Date range cannot exceed 366 days.',
                ]);
            }

            $stats = $this->revenueService->getStats($startDate, $endDate);

            return $this->successResponse($stats, 'Revenue stats retrieved successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid revenue stat filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve revenue stats', [
                'actor_id' => Auth::id(),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to retrieve revenue stats', 500);
        }
    }
}
