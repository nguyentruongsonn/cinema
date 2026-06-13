<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueService
{
    private const STATUS_CONFIRMED = 2;

    /**
     * Get all revenue statistics for the given date range.
     */
    public function getStats(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        return [
            'summary'           => $this->getSummary($start, $end),
            'top_theater'       => $this->getTopTheater($start, $end),
            'top_movie'         => $this->getTopMovie($start, $end),
            'payment_methods'   => $this->getPaymentMethods($start, $end),
            'by_theater'        => $this->getRevenueByTheater($start, $end),
            'by_movie'          => $this->getRevenueByMovie($start, $end),
            'by_trend'          => $this->getRevenueTrend($start, $end),
        ];
    }

    private function getSummary(Carbon $start, Carbon $end): array
    {
        $total = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total_amount');

        $totalOrders = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$start, $end])
            ->count();

        return [
            'total_revenue' => (float) $total,
            'total_orders'  => $totalOrders,
        ];
    }

    private function getTopTheater(Carbon $start, Carbon $end): array
    {
        $totalRevenue = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total_amount');

        $top = DB::table('orders')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('theaters.id, theaters.name, SUM(orders.total_amount) as revenue')
            ->groupBy('theaters.id', 'theaters.name')
            ->orderByDesc('revenue')
            ->first();

        if (!$top) {
            return ['name' => 'N/A', 'revenue' => 0, 'percentage' => 0];
        }

        $pct = $totalRevenue > 0 ? round(($top->revenue / $totalRevenue) * 100, 1) : 0;

        return [
            'name'       => $top->name,
            'revenue'    => (float) $top->revenue,
            'percentage' => $pct,
        ];
    }

    private function getTopMovie(Carbon $start, Carbon $end): array
    {
        $top = DB::table('orders')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('movies.id, movies.title, SUM(orders.total_amount) as revenue, COUNT(orders.id) as tickets')
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('revenue')
            ->first();

        if (!$top) {
            return ['title' => 'N/A', 'revenue' => 0, 'tickets' => 0];
        }

        return [
            'title'   => $top->title,
            'revenue' => (float) $top->revenue,
            'tickets' => (int) $top->tickets,
        ];
    }

    private function getPaymentMethods(Carbon $start, Carbon $end): array
    {
        $methods = DB::table('orders')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('payments.method, COUNT(orders.id) as count')
            ->groupBy('payments.method')
            ->get();

        $totalCount = $methods->sum('count');
        $topMethod  = $methods->sortByDesc('count')->first();

        if (!$topMethod) {
            return [
                'top_method'       => 'N/A',
                'top_method_count' => 0,
                'top_method_pct'   => 0,
                'breakdown'        => [],
            ];
        }

        $pct = $totalCount > 0 ? round(($topMethod->count / $totalCount) * 100, 1) : 0;

        return [
            'top_method'       => $topMethod->method ?? 'N/A',
            'top_method_count' => (int) $topMethod->count,
            'top_method_pct'   => $pct,
            'total_count'      => (int) $totalCount,
            'breakdown'        => $methods->map(fn($m) => [
                'method'  => $m->method ?? 'Khác',
                'count'   => (int) $m->count,
                'percent' => $totalCount > 0 ? round(($m->count / $totalCount) * 100, 1) : 0,
            ])->values()->toArray(),
        ];
    }

    private function getRevenueByTheater(Carbon $start, Carbon $end): array
    {
        return DB::table('orders')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('theaters.name, SUM(orders.total_amount) as revenue')
            ->groupBy('theaters.id', 'theaters.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($r) => ['name' => $r->name, 'revenue' => (float) $r->revenue])
            ->toArray();
    }

    private function getRevenueByMovie(Carbon $start, Carbon $end): array
    {
        return DB::table('orders')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw('movies.title, SUM(orders.total_amount) as revenue, COUNT(orders.id) as tickets')
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'title'   => $r->title,
                'revenue' => (float) $r->revenue,
                'tickets' => (int) $r->tickets,
            ])
            ->toArray();
    }

    private function getRevenueTrend(Carbon $start, Carbon $end): array
    {
        $diff = $start->diffInDays($end);

        // If range > 60 days, group by month; if > 14 days, group by week; else group by day
        if ($diff > 60) {
            $groupBy = 'DATE_FORMAT(paid_at, \'%Y-%m\') as period';
        } elseif ($diff > 14) {
            $groupBy = 'DATE_FORMAT(paid_at, \'%Y-%u\') as period';
        } else {
            $groupBy = 'DATE(paid_at) as period';
        }

        return DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw("{$groupBy}, SUM(total_amount) as revenue, COUNT(id) as orders")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($r) => [
                'period'  => $r->period,
                'revenue' => (float) $r->revenue,
                'orders'  => (int) $r->orders,
            ])
            ->toArray();
    }
}
