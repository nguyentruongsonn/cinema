<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MediaUrl;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const STATUS_CONFIRMED = Order::STATUS_CONFIRMED;
    private const MAX_RANGE_DAYS = 366;

    /**
     * Get all dashboard statistics with time range filter.
     */
    public function getStats(string $start, string $end): array
    {
        [$startDate, $endDate] = $this->validatedRange($start, $end);

        $cacheKey = sprintf(
            'admin:dashboard:stats:%s:%s',
            $startDate->toDateString(),
            $endDate->toDateString()
        );

        // Short cache for near real-time, but prevents DB overload from spam clicks.
        return Cache::remember($cacheKey, now()->addSeconds(30), fn () => [
            'cards' => $this->getCardStats($startDate, $endDate),
            'revenue_by_day' => $this->getRevenueByDay($startDate, $endDate),
            'top_movies' => $this->getTopMovies($startDate, $endDate),
            'traffic_heatmap' => $this->getTrafficHeatmap($startDate, $endDate),
            'recent_orders' => $this->getRecentOrders(),
        ]);
    }

    public function clearStatsCache(): void
    {
        Cache::forget('admin:dashboard:stats');
        Cache::forget('admin:dashboard:stats:week');
        Cache::forget('admin:dashboard:stats:month');
        Cache::forget('admin:dashboard:stats:year');

        // Dynamic date-range dashboard keys cannot be reliably wildcard-flushed on all cache drivers.
        // Keep dashboard TTL short and prefer Cache::tags(['dashboard'])->flush() when Redis/Memcached tags are enabled.
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function validatedRange(string $start, string $end): array
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();

        if ($endDate->lt($startDate)) {
            throw new \InvalidArgumentException('End date must be greater than or equal to start date.');
        }

        if ($startDate->diffInDays($endDate) > self::MAX_RANGE_DAYS) {
            throw new \InvalidArgumentException('Dashboard date range cannot exceed ' . self::MAX_RANGE_DAYS . ' days.');
        }

        return [$startDate, $endDate];
    }

    /**
     * Get card statistics with smart comparison logic.
     * Compares current period with previous period of same length.
     */
    private function getCardStats(Carbon $currentStart, Carbon $currentEnd): array
    {
        $days = $currentStart->diffInDays($currentEnd);
        $compareEnd = $currentStart->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days)->startOfDay();

        $currentRevenue = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->sum('total_amount');

        $lastRevenue = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$compareStart, $compareEnd])
            ->sum('total_amount');

        $revenueTrend = $this->calculateTrend((float) $currentRevenue, (float) $lastRevenue);

        $currentTickets = DB::table('tickets')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$currentStart, $currentEnd])
            ->count('tickets.id');

        $lastTickets = DB::table('tickets')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$compareStart, $compareEnd])
            ->count('tickets.id');

        $ticketsTrend = $this->calculateTrend((float) $currentTickets, (float) $lastTickets);

        $currentUsers = DB::table('users')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count();

        $lastUsers = DB::table('users')
            ->whereBetween('created_at', [$compareStart, $compareEnd])
            ->count();

        $usersTrend = $this->calculateTrend((float) $currentUsers, (float) $lastUsers);

        $userOrderCounts = DB::table('orders')
            ->select('user_id', DB::raw('count(*) as order_count'))
            ->where('status', self::STATUS_CONFIRMED)
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        $totalBuyingUsers = DB::query()->fromSub(clone $userOrderCounts, 'buyers')->count();
        $returningUsers = DB::query()
            ->fromSub($userOrderCounts->havingRaw('COUNT(*) >= 2'), 'returning_buyers')
            ->count();
        $retentionRate = $totalBuyingUsers > 0 ? round(($returningUsers / $totalBuyingUsers) * 100, 1) : 0;

        return [
            'revenue' => [
                'value' => (float) $currentRevenue,
                'trend' => $revenueTrend,
            ],
            'tickets' => [
                'value' => $currentTickets,
                'trend' => $ticketsTrend,
            ],
            'new_users' => [
                'value' => $currentUsers,
                'trend' => $usersTrend,
            ],
            'retention_rate' => [
                'value' => $retentionRate,
                'trend' => 0,
            ],
        ];
    }

    /**
     * Helper to calculate percentage trend.
     */
    private function calculateTrend(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get revenue line chart data for specified date range.
     */
    private function getRevenueByDay(Carbon $startDate, Carbon $endDate)
    {
        return DB::table('orders')
            ->selectRaw('DATE(paid_at) as date, SUM(total_amount) as revenue')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Get heatmap data for traffic by day of week and hour.
     * Uses showtime scheduled_at to reflect actual physical presence at the theater.
     */
    private function getTrafficHeatmap(Carbon $startDate, Carbon $endDate)
    {
        [$dayExpression, $hourExpression] = match (DB::connection()->getDriverName()) {
            'sqlite' => ["CAST(strftime('%w', showtimes.scheduled_at) AS INTEGER) + 1", "CAST(strftime('%H', showtimes.scheduled_at) AS INTEGER)"],
            'pgsql' => ['EXTRACT(DOW FROM showtimes.scheduled_at) + 1', 'EXTRACT(HOUR FROM showtimes.scheduled_at)'],
            default => ['DAYOFWEEK(showtimes.scheduled_at)', 'HOUR(showtimes.scheduled_at)'],
        };

        return DB::table('orders')
            ->join('showtimes', 'orders.showtime_id', '=', 'showtimes.id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$startDate, $endDate])
            ->whereNotNull('showtimes.scheduled_at')
            ->selectRaw("{$dayExpression} as day_of_week, {$hourExpression} as hour, COUNT(DISTINCT orders.id) as customer_count")
            ->groupByRaw("{$dayExpression}, {$hourExpression}")
            ->get();
    }

    /**
     * Get top movies by revenue for specified date range.
     */
    private function getTopMovies(Carbon $startDate, Carbon $endDate)
    {
        $ticketCounts = DB::table('tickets')
            ->select('order_id', DB::raw('COUNT(*) as tickets_sold'))
            ->groupBy('order_id');

        return DB::table('orders')
            ->selectRaw('movies.id, movies.title, movies.poster_url, movies.poster_path, COALESCE(SUM(ticket_counts.tickets_sold), 0) as tickets_sold, SUM(orders.total_amount) as revenue')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->leftJoinSub($ticketCounts, 'ticket_counts', function ($join) {
                $join->on('ticket_counts.order_id', '=', 'orders.id');
            })
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$startDate, $endDate])
            ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.poster_path')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get()
            ->map(function ($movie) {
                $movie->poster_url = $movie->poster_path
                    ? MediaUrl::storage($movie->poster_path)
                    : $movie->poster_url;
                unset($movie->poster_path);

                return $movie;
            });
    }

    private function getRecentOrders()
    {
        return Order::query()
            ->with(['user:id,name', 'showtime.movie:id,title', 'showtime.screen.theater:id,name', 'payment:id,order_id,status'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'code' => $order->code,
                'customer' => data_get($order, 'user.name', 'N/A'),
                'movie' => data_get($order, 'showtime.movie.title', 'N/A'),
                'total_amount' => (float) $order->total_amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ]);
    }
}
