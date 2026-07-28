<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Showtime;
use App\Services\TicketPricingService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TicketPricingService $pricingService
    ) {}

    /**
     * Calculate single ticket price.
     *
     * POST /api/v1/pricing/calculate
     *
     * Request body:
     *   format          string  '2D'|'3D'                              (required)
     *   scheduled_at    string  'Y-m-d H:i:s' format                   (required)
     *   customer_type   string  'adult'|'student'|'child'|'senior'     (optional, default: adult)
     *   is_double_seat  bool    0|1                                    (optional, default: 0)
     *   movie_surcharge int     VND                                    (optional, default: 0)
     *
     * Note: This endpoint calculates pricing preview only. Actual checkout
     * pricing may differ based on seat type, real-time showtime pricing, and
     * theater-specific rules applied during order creation.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format'          => 'required|in:2D,3D',
            'scheduled_at'    => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after_or_equal:now',
                'before:' . now()->addMonths(6)->toDateTimeString(),
            ],
            'customer_type'   => 'sometimes|in:adult,student,child,senior',
            'is_double_seat'  => 'sometimes|boolean',
            'movie_surcharge' => 'sometimes|integer|min:0|max:100000',
        ]);

        $result = $this->pricingService->calculate(
            format:          $validated['format'],
            scheduledAt:     Carbon::parse($validated['scheduled_at']),
            customerType:    $validated['customer_type']   ?? 'adult',
            isDoubleSeat:    (bool) ($validated['is_double_seat'] ?? false),
            movieSurcharge:  (int)  ($validated['movie_surcharge'] ?? 0),
        );

        return $this->successResponse($result, 'Ticket price calculated successfully');
    }

    /**
     * Calculate all customer type prices at once.
     *
     * POST /api/v1/pricing/calculate-all
     *
     * Request body:
     *   format          string  '2D'|'3D'          (required)
     *   scheduled_at    string  'Y-m-d H:i:s'      (required)
     *   is_double_seat  bool    0|1                (optional)
     *   movie_surcharge int     VND                (optional)
     */
    public function calculateAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format'          => 'required|in:2D,3D',
            'scheduled_at'    => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after_or_equal:now',
                'before:' . now()->addMonths(6)->toDateTimeString(),
            ],
            'is_double_seat'  => 'sometimes|boolean',
            'movie_surcharge' => 'sometimes|integer|min:0|max:100000',
        ]);

        $result = $this->pricingService->calculateAll(
            format:         $validated['format'],
            scheduledAt:    Carbon::parse($validated['scheduled_at']),
            isDoubleSeat:   (bool) ($validated['is_double_seat'] ?? false),
            movieSurcharge: (int)  ($validated['movie_surcharge'] ?? 0),
        );

        return $this->successResponse($result, 'All ticket prices calculated successfully');
    }

    /**
     * Get quick pricing from showtime (auto-resolves format, scheduled_at, and movie surcharge).
     *
     * GET /api/v1/pricing/showtime/{showtime}
     *
     * Query params:
     *   customer_type   string  'adult'|'student'|'child'|'senior'  (optional)
     *   is_double_seat  bool    0|1                                 (optional)
     *
     * Note: This endpoint does NOT include seat-specific pricing. The returned
     * price uses seat_surcharge = 0. For accurate per-seat pricing, use the
     * order creation endpoint which will apply the correct seat type surcharge.
     *
     * Response includes 'showtime_base_price' and 'showtime_id' for reference,
     * but these are informational only and may not reflect final charged price.
     */
    public function fromShowtime(Request $request, Showtime $showtime): JsonResponse
    {
        $validated = $request->validate([
            'customer_type'  => 'sometimes|in:adult,student,child,senior',
            'is_double_seat' => 'sometimes|boolean',
        ]);

        // Resolve format key from format relation or fallback to '2D'
        $formatLabel = $showtime->format?->name ?? '2D';
        $formatKey   = str_contains(strtoupper($formatLabel), '3D') ? '3D' : '2D';

        $result = $this->pricingService->calculate(
            format:          $formatKey,
            scheduledAt:     $showtime->scheduled_at,
            customerType:    $validated['customer_type']  ?? 'adult',
            isDoubleSeat:    (bool) ($validated['is_double_seat'] ?? false),
            movieSurcharge:  (int) ($showtime->movie?->surcharge ?? 0),
            extraHolidays:   [],
            formatSurcharge: (int) ($showtime->format?->surcharge ?? 0),
            seatSurcharge:   0, // Seat type pricing not included - applied at order creation
            theaterPricing:  $showtime->screen?->theater?->pricing_profile
        );

        // Add showtime reference metadata (informational only)
        $result['showtime_base_price'] = (int) $showtime->price;
        $result['showtime_id']         = $showtime->id;

        return $this->successResponse($result, 'Showtime ticket price calculated');
    }

    /**
     * Get full pricing table for next 7 days for a given format.
     *
     * GET /api/v1/pricing/weekly-table?format=2D
     *
     * Returns pricing grid for common showtime slots across the next week.
     * Useful for displaying pricing reference tables to users.
     */
    public function weeklyTable(Request $request): JsonResponse
    {
        $request->validate(['format' => 'required|in:2D,3D']);
        $format = $request->format;

        $table = [];
        for ($i = 0; $i < 7; $i++) {
            $day  = Carbon::today()->addDays($i);
            $rows = [];

            foreach (['10:30', '15:00', '20:00', '23:00'] as $time) {
                $dt = Carbon::parse($day->toDateString() . ' ' . $time);
                $rows[$time] = $this->pricingService->calculateAll($format, $dt);
            }

            $table[] = [
                'date'      => $day->toDateString(),
                'label'     => $day->isoFormat('ddd DD/MM'),
                'day_type'  => $rows['15:00']['adult']['day_type'],
                'prices'    => $rows,
            ];
        }

        return $this->successResponse($table, 'Weekly pricing table');
    }
}