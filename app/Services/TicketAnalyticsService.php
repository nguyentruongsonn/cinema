<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ticket Analytics Service
 *
 * Provides ticket sales analytics and occupancy reporting for a date range.
 *
 * IMPORTANT DATE SEMANTICS:
 * - Ticket sales metrics (total, trend, peak hour, top movies) are based on PAYMENT DATE (orders.paid_at)
 * - Occupancy metrics are based on SHOWTIME DATE (showtimes.scheduled_at)
 * - These are different time dimensions and should be interpreted accordingly
 *
 * TIMEZONE LIMITATION:
 * - Peak hour and trend grouping use database timezone for date/hour extraction
 * - If database timezone differs from business timezone, results may be inaccurate
 * - Consider using explicit timezone conversion or reporting dimension columns for production
 *
 * HISTORICAL OCCUPANCY LIMITATION:
 * - Occupancy uses current seat layout for capacity calculation
 * - If screen layout changed after historical showtimes, occupancy may be inaccurate
 * - Consider using immutable showtime capacity snapshots for production accuracy
 */
class TicketAnalyticsService
{
    /**
     * Maximum allowed reporting range in days.
     * Prevents unbounded analytics queries from overloading the database.
     */
    private const MAX_REPORTING_DAYS = 366;

    /**
     * Get ticket analytics stats for the given date range.
     *
     * @param string $startDate Date in Y-m-d format
     * @param string $endDate Date in Y-m-d format
     * @return array Analytics data with summary, trend, top movies, and theater occupancy
     * @throws \InvalidArgumentException If date format is invalid, range is reversed, or exceeds maximum
     */
    public function getStats(string $startDate, string $endDate): array
    {
        // Strict date parsing to reject ambiguous/invalid inputs
        $start = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end) {
            throw new \InvalidArgumentException('Invalid date format. Expected Y-m-d.');
        }

        $start = $start->startOfDay();
        $end = $end->endOfDay();

        // Validate date ordering
        if ($start->gt($end)) {
            throw new \InvalidArgumentException('Start date must be before or equal to end date.');
        }

        // Enforce maximum reporting range
        $days = $start->diffInDays($end);
        if ($days > self::MAX_REPORTING_DAYS) {
            throw new \InvalidArgumentException(
                sprintf('Ticket analytics range exceeds maximum %d days (requested: %d days).', self::MAX_REPORTING_DAYS, $days)
            );
        }

        $totalDays = $days + 1;

        // 1. Total sold tickets (based on successful payments and valid tickets)
        $totalTickets = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->whereBetween('orders.paid_at', [$start, $end])
            ->count('tickets.id');

        // 2. Average tickets per day
        $avgTicketsPerDay = round($totalTickets / max($totalDays, 1), 1);

        // 3. Peak purchase hour (based on payment time, database timezone)
        $peakHourRow = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('HOUR(orders.paid_at) as hour, COUNT(tickets.id) as ticket_count')
            ->groupBy(DB::raw('HOUR(orders.paid_at)'))
            ->orderByDesc('ticket_count')
            ->first();

        // Format peak hour with proper midnight handling (avoid 24:00)
        if ($peakHourRow) {
            $startHour = str_pad((string) $peakHourRow->hour, 2, '0', STR_PAD_LEFT);
            $endHour = str_pad((string) (((int) $peakHourRow->hour + 1) % 24), 2, '0', STR_PAD_LEFT);
            $peakHour = "{$startHour}:00 - {$endHour}:00";
        } else {
            $peakHour = 'N/A';
        }

        // 4. Occupancy rate (based on showtime date, not payment date)
        $occupancyData = $this->calculateOccupancyRate($start, $end);

        // 5. Ticket sales trend (daily grouping by payment date)
        $trendRows = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d") as date, COUNT(tickets.id) as ticket_count')
            ->groupBy(DB::raw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d")'))
            ->orderBy('date')
            ->get();

        // Normalize trend output to plain arrays with explicit casting
        $trend = $trendRows->map(fn ($row) => [
            'date' => $row->date,
            'ticket_count' => (int) $row->ticket_count,
        ])->toArray();

        // 6. Top selling movies (by ticket count in date range)
        $topMoviesRows = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->join('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('movies.id, movies.title, COUNT(tickets.id) as ticket_count')
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('ticket_count')
            ->limit(5)
            ->get();

        // Normalize top movies output
        $topMovies = $topMoviesRows->map(fn ($row) => [
            'movie_id' => (int) $row->id,
            'title' => $row->title,
            'ticket_count' => (int) $row->ticket_count,
        ])->toArray();

        return [
            'summary' => [
                'total_tickets' => $totalTickets,
                'avg_per_day' => $avgTicketsPerDay,
                'peak_hour' => $peakHour,
                'occupancy_rate' => $occupancyData['overall_rate'],
            ],
            'trend' => $trend,
            'top_movies' => $topMovies,
            'theater_occupancy' => $occupancyData['by_theater'],
        ];
    }

    /**
     * Calculate occupancy rate based on showtimes within the date range.
     *
     * NOTE: Occupancy is calculated by showtime scheduled date, not payment date.
     * This means occupancy metrics reflect when shows occurred, while ticket sales
     * metrics reflect when tickets were purchased.
     *
     * LIMITATION: Uses current seat layout for capacity calculation. If screen layout
     * changed after historical showtimes, occupancy accuracy may be affected.
     *
     * @param Carbon $start Start of date range
     * @param Carbon $end End of date range
     * @return array Occupancy data with overall rate and per-theater breakdown
     */
    private function calculateOccupancyRate(Carbon $start, Carbon $end): array
    {
        // Get all active showtimes in the period (by showtime date, not payment date)
        $showtimes = DB::table('showtimes')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->whereBetween('showtimes.scheduled_at', [$start, $end])
            ->where('showtimes.status', 1)
            ->select('showtimes.id', 'showtimes.screen_id', 'theaters.id as theater_id', 'theaters.name as theater_name')
            ->get();

        if ($showtimes->isEmpty()) {
            return [
                'overall_rate' => 0,
                'by_theater' => []
            ];
        }

        $screenIds = $showtimes->pluck('screen_id')->unique();

        // Get seat count per screen (current layout, not historical snapshot)
        // NOTE: This counts all seats regardless of status. If seats have active/disabled status,
        // consider filtering to active seats only for accurate sellable capacity.
        $seatsByScreen = DB::table('seats')
            ->whereIn('screen_id', $screenIds)
            ->selectRaw('screen_id, count(id) as total_seats')
            ->groupBy('screen_id')
            ->pluck('total_seats', 'screen_id');

        // Get sold tickets per showtime
        // Filter by successful payment and valid/used tickets to exclude refunds/cancellations
        $showtimeIds = $showtimes->pluck('id');
        $ticketsByShowtime = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->whereIn('tickets.showtime_id', $showtimeIds)
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->selectRaw('tickets.showtime_id, count(tickets.id) as sold_seats')
            ->groupBy('tickets.showtime_id')
            ->pluck('sold_seats', 'showtime_id');

        $overallTotalSeats = 0;
        $overallSoldSeats = 0;
        $theaterStats = [];

        foreach ($showtimes as $st) {
            $tSeats = $seatsByScreen[$st->screen_id] ?? 0;
            $sSeats = $ticketsByShowtime[$st->id] ?? 0;

            $overallTotalSeats += $tSeats;
            $overallSoldSeats += $sSeats;

            if (!isset($theaterStats[$st->theater_id])) {
                $theaterStats[$st->theater_id] = [
                    'name' => $st->theater_name,
                    'total_seats' => 0,
                    'sold_seats' => 0,
                ];
            }
            $theaterStats[$st->theater_id]['total_seats'] += $tSeats;
            $theaterStats[$st->theater_id]['sold_seats'] += $sSeats;
        }

        $byTheater = [];
        foreach ($theaterStats as $id => $stat) {
            $rate = $stat['total_seats'] > 0 ? round(($stat['sold_seats'] / $stat['total_seats']) * 100, 1) : 0;
            $byTheater[] = [
                'name' => $stat['name'],
                'total_seats' => $stat['total_seats'],
                'sold_seats' => $stat['sold_seats'],
                'occupancy_rate' => $rate,
            ];
        }

        usort($byTheater, fn($a, $b) => $b['occupancy_rate'] <=> $a['occupancy_rate']);

        $overallRate = $overallTotalSeats > 0 ? round(($overallSoldSeats / $overallTotalSeats) * 100, 1) : 0;

        return [
            'overall_rate' => $overallRate,
            'by_theater' => $byTheater,
        ];
    }
}
