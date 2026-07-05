<?php

namespace App\Http\Controllers;

use App\Services\TicketPricingService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TicketPricingService $pricingService
    ) {}

    /**
     * Tính giá 1 vé.
     *
     * GET/POST /api/v1/pricing/calculate
     * Params:
     *   format          string  '2D'|'3D'
     *   scheduled_at    string  'Y-m-d H:i' | 'Y-m-d H:i:s'
     *   customer_type   string  'adult'|'student'|'child'|'senior'  (default: adult)
     *   is_double_seat  bool    0|1                                  (default: 0)
     *   movie_surcharge int     VNĐ                                  (default: 0)
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'format'          => 'required|in:2D,3D',
            'scheduled_at'    => 'required|date',
            'customer_type'   => 'sometimes|in:adult,student,child,senior',
            'is_double_seat'  => 'sometimes|boolean',
            'movie_surcharge' => 'sometimes|integer|min:0',
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
     * Tính giá cho tất cả loại khách cùng 1 lúc.
     *
     * GET/POST /api/v1/pricing/calculate-all
     * Params: format, scheduled_at, is_double_seat, movie_surcharge
     */
    public function calculateAll(Request $request)
    {
        $validated = $request->validate([
            'format'          => 'required|in:2D,3D',
            'scheduled_at'    => 'required|date',
            'is_double_seat'  => 'sometimes|boolean',
            'movie_surcharge' => 'sometimes|integer|min:0',
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
     * Lấy giá nhanh từ showtime_id (tự resolve format + scheduled_at + movie_surcharge).
     *
     * GET /api/v1/pricing/showtime/{id}
     * Params: customer_type, is_double_seat
     */
    public function fromShowtime(Request $request, int $id)
    {
        $validated = $request->validate([
            'customer_type'  => 'sometimes|in:adult,student,child,senior',
            'is_double_seat' => 'sometimes|boolean',
        ]);

        $showtime = \App\Models\Showtime::with(['movie', 'format'])->findOrFail($id);

        // Resolve format label từ format relation hoặc fallback '2D'
        $formatLabel = $showtime->format?->name ?? '2D';
        $formatKey   = str_contains(strtoupper($formatLabel), '3D') ? '3D' : '2D';

        $result = $this->pricingService->calculate(
            format:          $formatKey,
            scheduledAt:     $showtime->scheduled_at,
            customerType:    $validated['customer_type']  ?? 'adult',
            isDoubleSeat:    (bool) ($validated['is_double_seat'] ?? false),
            movieSurcharge:  (int) ($showtime->movie?->surcharge ?? 0),
        );

        // Thêm giá cơ sở từ DB (showtime.price) để so sánh
        $result['showtime_base_price'] = (int) $showtime->price;
        $result['showtime_id']         = $showtime->id;

        return $this->successResponse($result, 'Showtime ticket price calculated');
    }

    /**
     * Lấy bảng giá đầy đủ theo tuần (7 ngày tới) cho 1 format.
     *
     * GET /api/v1/pricing/weekly-table?format=2D
     */
    public function weeklyTable(Request $request)
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
