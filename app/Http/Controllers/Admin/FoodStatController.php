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

class FoodStatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FoodAnalyticsService $foodAnalyticsService) {}

    public function stats(StatFilterRequest $request): JsonResponse
    {
        abort_unless(
            Auth::user()?->isAdmin() || Auth::user()?->hasPermission('analytics.view'),
            403,
            'Unauthorized to access food analytics'
        );

        try {
            $validated = $request->validate([
                'type' => ['nullable', 'string', 'in:popcorn,drink,snack'],
            ]);

            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());
            $type = $validated['type'] ?? null;

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
            Log::warning('Invalid food stat request', [
                'actor_id' => Auth::id(),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'type' => $type ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 422);

        } catch (\Throwable $e) {
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
