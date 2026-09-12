<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TicketAnalyticsService
{
    private const MAX_REPORTING_DAYS = 366;

    public function getStats(string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end) {
            throw new \InvalidArgumentException('Invalid date format. Expected Y-m-d.');
        }

        $start = $start->startOfDay();
        $end = $end->endOfDay();

        if ($start->gt($end)) {
            throw new \InvalidArgumentException('Start date must be before or equal to end date.');
        }

        $days = $start->diffInDays($end);
        if ($days > self::MAX_REPORTING_DAYS) {
            throw new \InvalidArgumentException(
                sprintf('Ticket analytics range exceeds maximum %d days (requested: %d days).', self::MAX_REPORTING_DAYS, $days)
            );
        }

        $totalDays = $days + 1;
        $compareEnd = $start->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days)->startOfDay();

        $totalTickets = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->whereBetween('orders.paid_at', [$start, $end])
            ->count('tickets.id');

        $previousTotalTickets = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereIn('tickets.status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->whereBetween('orders.paid_at', [$compareStart, $compareEnd])
            ->count('tickets.id');

        $avgTicketsPerDay = round($totalTickets / max($totalDays, 1), 1);

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

        if ($peakHourRow) {
            $startHour = str_pad((string) $peakHourRow->hour, 2, '0', STR_PAD_LEFT);
            $endHour = str_pad((string) (((int) $peakHourRow->hour + 1) % 24), 2, '0', STR_PAD_LEFT);
            $peakHour = "{$startHour}:00 - {$endHour}:00";
        } else {
            $peakHour = 'N/A';
        }

        $occupancyData = $this->calculateOccupancyRate($start, $end);
        $previousOccupancyData = $this->calculateOccupancyRate($compareStart, $compareEnd);

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

        $trend = $trendRows->map(fn ($row) => [
            'date' => $row->date,
            'ticket_count' => (int) $row->ticket_count,
        ])->toArray();

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
                'trends' => [
                    'total_tickets' => $this->calculateTrend($totalTickets, $previousTotalTickets),
                    'avg_per_day' => $this->calculateTrend($avgTicketsPerDay, round($previousTotalTickets / max($totalDays, 1), 1)),
                    'occupancy_rate' => $this->calculateTrend(
                        (float) $occupancyData['overall_rate'],
                        (float) $previousOccupancyData['overall_rate']
                    ),
                ],
            ],
            'trend' => $trend,
            'top_movies' => $topMovies,
            'theater_occupancy' => $occupancyData['by_theater'],
        ];
    }

    private function calculateOccupancyRate(Carbon $start, Carbon $end): array
    {
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

        $seatsByScreen = DB::table('seats')
            ->whereIn('screen_id', $screenIds)
            ->selectRaw('screen_id, count(id) as total_seats')
            ->groupBy('screen_id')
            ->pluck('total_seats', 'screen_id');

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

    private function calculateTrend(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0.0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }
}
