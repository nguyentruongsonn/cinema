<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TicketAnalyticsService
{
    /**
     * Get ticket analytics stats for the given date range.
     */
    public function getStats(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();
        
        $totalDays = $start->diffInDays($end) + 1;

        // 1. Tổng vé bán ra (dựa trên order đã thanh toán)
        $totalTickets = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('orders.status', 2)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->count('tickets.id');
        
        // 2. Trung bình mỗi ngày
        $avgTicketsPerDay = round($totalTickets / max($totalDays, 1), 1);

        // 3. Giờ cao điểm bán vé
        $peakHourRow = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('orders.status', 2)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('HOUR(orders.paid_at) as hour, COUNT(tickets.id) as ticket_count')
            ->groupBy(DB::raw('HOUR(orders.paid_at)'))
            ->orderByDesc('ticket_count')
            ->first();
        
        $peakHour = $peakHourRow ? str_pad($peakHourRow->hour, 2, '0', STR_PAD_LEFT) . ':00 - ' . str_pad($peakHourRow->hour + 1, 2, '0', STR_PAD_LEFT) . ':00' : 'N/A';

        // 4. Tỉ lệ lấp đầy (dựa trên lịch chiếu trong khoảng thời gian)
        $occupancyData = $this->calculateOccupancyRate($start, $end);

        // Xu hướng bán vé
        $trend = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('orders.status', 2)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d") as date, COUNT(tickets.id) as ticket_count')
            ->groupBy(DB::raw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d")'))
            ->orderBy('date')
            ->get();

        // Top phim bán chạy
        $topMovies = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('orders.status', 2)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('movies.title, COUNT(tickets.id) as ticket_count')
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('ticket_count')
            ->limit(5)
            ->get();

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
     */
    private function calculateOccupancyRate(Carbon $start, Carbon $end): array
    {
        // Get all showtimes in the period
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

        // Get seat count per screen
        $seatsByScreen = DB::table('seats')
            ->whereIn('screen_id', $screenIds)
            ->selectRaw('screen_id, count(id) as total_seats')
            ->groupBy('screen_id')
            ->pluck('total_seats', 'screen_id');

        // Get sold tickets per showtime
        $showtimeIds = $showtimes->pluck('id');
        $ticketsByShowtime = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->whereIn('tickets.showtime_id', $showtimeIds)
            ->where('orders.status', 2)
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
