<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const STATUS_CONFIRMED = 2;
    private const STATUS_PENDING = 1;
    private const STATUS_CANCELLED = 0;

    /**
     * Get all dashboard statistics with time range filter.
     */
    public function getStats(string $start, string $end): array
    {
        $cacheKey = "admin:dashboard:stats:{$start}:{$end}";
        // Short cache for near real-time, but prevents DB overload from spam clicks
        return Cache::remember($cacheKey, now()->addSeconds(30), fn () => [
            'cards' => $this->getCardStats($start, $end),
            'revenue_by_day' => $this->getRevenueByDay($start, $end),
            'top_movies' => $this->getTopMovies($start, $end),
            'traffic_heatmap' => $this->getTrafficHeatmap(),
            'recent_orders' => $this->getRecentOrders(),
        ]);
    }

    public function clearStatsCache(): void
    {
        Cache::forget('admin:dashboard:stats:week');
        Cache::forget('admin:dashboard:stats:month');
        Cache::forget('admin:dashboard:stats:year');
        // Legacy key cleanup
        Cache::forget('admin:dashboard:stats');
    }

    /**
     * Get card statistics with smart comparison logic.
     * Compares current period with previous period of same length.
     */
    private function getCardStats(string $start, string $end): array
    {
        $currentStart = Carbon::parse($start)->startOfDay();
        $currentEnd = Carbon::parse($end)->endOfDay();
        
        // Calculate comparison period (same length, immediately before current period)
        $days = $currentStart->diffInDays($currentEnd);
        $compareEnd = $currentStart->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days)->startOfDay();

        // 1. Revenue
        $currentRevenue = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->sum('total_amount');
            
        $lastRevenue = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$compareStart, $compareEnd])
            ->sum('total_amount');
            
        $revenueTrend = $this->calculateTrend($currentRevenue, $lastRevenue);

        // 2. Tickets (Orders count for simplicity as 'tickets sold')
        $currentTickets = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->count();
            
        $lastTickets = DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$compareStart, $compareEnd])
            ->count();
            
        $ticketsTrend = $this->calculateTrend($currentTickets, $lastTickets);

        // 3. New Users
        $currentUsers = DB::table('users')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count();
            
        $lastUsers = DB::table('users')
            ->whereBetween('created_at', [$compareStart, $compareEnd])
            ->count();
            
        $usersTrend = $this->calculateTrend($currentUsers, $lastUsers);

        // 4. Retention Rate (Users with > 1 confirmed order / Total users with any confirmed order)
        $userOrderCounts = DB::table('orders')
            ->select('user_id', DB::raw('count(*) as order_count'))
            ->where('status', self::STATUS_CONFIRMED)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->get();
            
        $totalBuyingUsers = $userOrderCounts->count();
        $returningUsers = $userOrderCounts->where('order_count', '>=', 2)->count();
        $retentionRate = $totalBuyingUsers > 0 ? round(($returningUsers / $totalBuyingUsers) * 100, 1) : 0;

        return [
            'revenue' => [
                'value' => (float)$currentRevenue,
                'trend' => $revenueTrend
            ],
            'tickets' => [
                'value' => $currentTickets,
                'trend' => $ticketsTrend
            ],
            'new_users' => [
                'value' => $currentUsers,
                'trend' => $usersTrend
            ],
            'retention_rate' => [
                'value' => $retentionRate,
                'trend' => 0 // Retention is an overall metric
            ]
        ];
    }

    /**
     * Helper to calculate percentage trend.
     */
    private function calculateTrend(float $current, float $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get revenue line chart data for specified date range.
     */
    private function getRevenueByDay(string $start, string $end)
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();

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
     * Uses Showtime start_time to reflect actual physical presence at the theater.
     */
    private function getTrafficHeatmap()
    {
        // MySQL: DAYOFWEEK() returns 1=Sunday, 2=Monday, ..., 7=Saturday
        // We will adjust it in Frontend or map it here.
        // Cột thực tế trong bảng showtimes là scheduled_at (không phải start_time)
        $traffic = DB::table('orders')
            ->join('showtimes', 'orders.showtime_id', '=', 'showtimes.id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->selectRaw('DAYOFWEEK(showtimes.scheduled_at) as day_of_week, HOUR(showtimes.scheduled_at) as hour, COUNT(orders.id) as customer_count')
            ->whereNotNull('showtimes.scheduled_at')
            ->groupBy(DB::raw('DAYOFWEEK(showtimes.scheduled_at)'), DB::raw('HOUR(showtimes.scheduled_at)'))
            ->get();
            
        return $traffic;
    }

    /**
     * Get top movies by revenue for specified date range.
     */
    private function getTopMovies(string $start, string $end)
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();

        return DB::table('orders')
            ->selectRaw('movies.id, movies.title, movies.poster_url, COUNT(orders.id) as tickets_sold, SUM(orders.total_amount) as revenue')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$startDate, $endDate])
            ->groupBy('movies.id', 'movies.title', 'movies.poster_url')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();
    }

    private function getRecentOrders()
    {
        return Order::query()
            ->with(['user', 'showtime.movie', 'showtime.screen.theater', 'payment'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'code' => $order->code,
                'customer' => $order->user?->name ?? $order->user?->full_name ?? 'N/A',
                'movie' => $order->showtime?->movie?->title ?? 'N/A',
                'total_amount' => (float) $order->total_amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ]);
    }
}
