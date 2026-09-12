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

    public function fromShowtime(Request $request, Showtime $showtime): JsonResponse
    {
        $validated = $request->validate([
            'customer_type'  => 'sometimes|in:adult,student,child,senior',
            'is_double_seat' => 'sometimes|boolean',
        ]);

        $formatLabel = $showtime->format->name;
        $formatKey   = str_contains(strtoupper($formatLabel), '3D') ? '3D' : '2D';

        $result = $this->pricingService->calculate(
            format:          $formatKey,
            scheduledAt:     $showtime->scheduled_at,
            customerType:    $validated['customer_type']  ?? 'adult',
            isDoubleSeat:    (bool) ($validated['is_double_seat'] ?? false),
            movieSurcharge:  (int) $showtime->movie->surcharge,
            extraHolidays:   [],
            formatSurcharge: (int) $showtime->format->surcharge,
            seatSurcharge:   0,
            theaterPricing:  $showtime->screen->theater->pricing_profile
        );

        $result['showtime_base_price'] = (int) $showtime->getAttribute('price');
        $result['showtime_id']         = $showtime->id;

        return $this->successResponse($result, 'Showtime ticket price calculated');
    }

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
