<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatFilterRequest;
use App\Services\FoodAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Admin Food Statistics Controller
 *
 * Provides food product sales analytics for admin users.
 * Requires admin role or analytics.view permission.
 *
 * @package App\Http\Controllers\Admin
 */
class FoodStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FoodAnalyticsService $foodAnalyticsService) {}

    /**
     * Return food analytics for the given date range and optional type filter.
     *
     * GET /api/v1/admin/food/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&type=popcorn|drink|snack
     *
     * @param StatFilterRequest $request Validated date range request
     * @return JsonResponse Food analytics data or error response
     */
    public function stats(StatFilterRequest $request): JsonResponse
    {
        // Explicit authorization - require admin role or analytics permission
        abort_unless(
            Auth::user()?->isAdmin() || Auth::user()?->hasPermission('analytics.view'),
            403,
            'Unauthorized to access food analytics'
        );

        try {
            // Inline validation for food-specific type filter
            // (StatFilterRequest only validates date range)
            $validated = $request->validate([
                'type' => ['nullable', 'string', 'in:popcorn,drink,snack'],
            ]);

            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());
            $type = $validated['type'] ?? null;

            // Service performs strict validation:
            // - Date format must be Y-m-d
            // - Start must be <= end
            // - Maximum range 366 days
            // - Type must be valid food type
            $stats = $this->foodAnalyticsService->getStats($startDate, $endDate, $type);

            return $this->successResponse($stats, 'Food stats retrieved successfully');

        } catch (ValidationException $e) {
            Log::warning('Invalid food stat filter', [
                'actor_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);

            return $this->errorResponse(
                'Invalid food stat filter parameters',
                422,
                $e->errors()
            );

        } catch (InvalidArgumentException $e) {
            // Service validation failure - return 422 with specific error
            Log::warning('Invalid food stat request', [
                'actor_id' => Auth::id(),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'type' => $type ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 422);

        } catch (\Throwable $e) {
            // Unexpected service failure - log and return generic error
            Log::error('Failed to retrieve food stats', [
                'actor_id' => Auth::id(),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'type' => $type ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to retrieve food stats', 500);
        }
    }
}
