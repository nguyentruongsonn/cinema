<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const STATUS_CONFIRMED = 2;
    private const STATUS_PENDING = 1;
    private const STATUS_CANCELLED = 0;

    /**
     * Get all dashboard statistics.
     *
     * Dashboard data is expensive to compute because it aggregates multiple
     * high-cardinality tables. Cache the complete payload briefly to reduce
     * repeated admin-page query bursts while keeping data fresh enough for
     * operational monitoring.
     */
    public function getStats(): array
    {
        return Cache::remember('admin:dashboard:stats', now()->addMinute(), fn () => [
            'cards' => $this->getCardStats(),
            'recent_orders' => $this->getRecentOrders(),
            'revenue_by_day' => $this->getRevenueByDay(),
            'top_movies' => $this->getTopMovies(),
        ]);
    }

    /**
     * Explicitly clear dashboard statistics cache after critical mutations.
     */
    public function clearStatsCache(): void
    {
        Cache::forget('admin:dashboard:stats');
    }

    /**
     * Get card statistics (counts and revenue).
     */
    private function getCardStats(): array
    {
        return [
            'movies' => DB::table('movies')->count(),
            'theaters' => DB::table('theaters')->count(),
            'showtimes' => DB::table('showtimes')->count(),
            'users' => DB::table('users')->count(),
            'orders' => DB::table('orders')->count(),
            'payments' => DB::table('payments')->count(),
            'pending_orders' => DB::table('orders')
                ->where('status', self::STATUS_PENDING)
                ->count(),
            'confirmed_orders' => DB::table('orders')
                ->where('status', self::STATUS_CONFIRMED)
                ->count(),
            'today_revenue' => $this->getTodayRevenue(),
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'total_revenue' => $this->getTotalRevenue(),
        ];
    }

    /**
     * Get today's revenue from confirmed orders.
     */
    private function getTodayRevenue(): float
    {
        return (float) DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereDate('paid_at', today())
            ->sum('total_amount');
    }

    /**
     * Get current month's revenue from confirmed orders.
     */
    private function getMonthlyRevenue(): float
    {
        return (float) DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('total_amount');
    }

    /**
     * Get total revenue from all confirmed orders.
     */
    private function getTotalRevenue(): float
    {
        return (float) DB::table('orders')
            ->where('status', self::STATUS_CONFIRMED)
            ->sum('total_amount');
    }

    /**
     * Get recent orders with relationships.
     */
    private function getRecentOrders()
    {
        return Order::query()
            ->with(['user', 'showtime.movie', 'showtime.screen.theater', 'payment'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'code' => $order->code,
                'customer' => $order->user?->name ?? $order->user?->full_name ?? 'N/A',
                'movie' => $order->showtime?->movie?->title ?? 'N/A',
                'theater' => $order->showtime?->screen?->theater?->name ?? 'N/A',
                'total_amount' => (float) $order->total_amount,
                'status' => $this->orderStatusLabel((int) $order->status),
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at,
            ]);
    }

    /**
     * Get revenue by day for the last 14 days.
     */
    private function getRevenueByDay()
    {
        return DB::table('orders')
            ->selectRaw('DATE(paid_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders_count')
            ->where('status', self::STATUS_CONFIRMED)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Get top 5 movies by revenue.
     */
    private function getTopMovies()
    {
        return DB::table('orders')
            ->selectRaw('showtimes.movie_id, movies.title, COUNT(orders.id) as orders_count, SUM(orders.total_amount) as revenue')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('orders.status', self::STATUS_CONFIRMED)
            ->groupBy('showtimes.movie_id', 'movies.title')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();
    }

    /**
     * Convert order status code to label.
     */
    private function orderStatusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_CANCELLED => 'cancelled',
            self::STATUS_PENDING => 'pending',
            self::STATUS_CONFIRMED => 'confirmed',
            default => 'unknown',
        };
    }
}
