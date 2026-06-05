<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Return summary statistics for the admin dashboard.
     */
    public function stats()
    {
        try {
            $confirmedStatus = 2;

            $todayRevenue = (float) DB::table('orders')
                ->where('status', $confirmedStatus)
                ->whereDate('paid_at', today())
                ->sum('total_amount');

            $monthlyRevenue = (float) DB::table('orders')
                ->where('status', $confirmedStatus)
                ->whereYear('paid_at', now()->year)
                ->whereMonth('paid_at', now()->month)
                ->sum('total_amount');

            $recentOrders = Order::query()
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

            $revenueByDay = DB::table('orders')
                ->selectRaw('DATE(paid_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders_count')
                ->where('status', $confirmedStatus)
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', now()->subDays(13)->startOfDay())
                ->groupBy(DB::raw('DATE(paid_at)'))
                ->orderBy('date')
                ->get();

            $topMovies = DB::table('orders')
                ->selectRaw('showtimes.movie_id, movies.title, COUNT(orders.id) as orders_count, SUM(orders.total_amount) as revenue')
                ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
                ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
                ->where('orders.status', $confirmedStatus)
                ->groupBy('showtimes.movie_id', 'movies.title')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get();

            return $this->successResponse([
                'cards' => [
                    'movies' => DB::table('movies')->count(),
                    'theaters' => DB::table('theaters')->count(),
                    'showtimes' => DB::table('showtimes')->count(),
                    'users' => DB::table('users')->count(),
                    'orders' => DB::table('orders')->count(),
                    'payments' => DB::table('payments')->count(),
                    'pending_orders' => DB::table('orders')->where('status', 1)->count(),
                    'confirmed_orders' => DB::table('orders')->where('status', $confirmedStatus)->count(),
                    'today_revenue' => $todayRevenue,
                    'monthly_revenue' => $monthlyRevenue,
                    'total_revenue' => (float) DB::table('orders')->where('status', $confirmedStatus)->sum('total_amount'),
                ],
                'recent_orders' => $recentOrders,
                'revenue_by_day' => $revenueByDay,
                'top_movies' => $topMovies,
            ], 'Admin dashboard stats retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve dashboard stats: ' . $e->getMessage(), 500);
        }
    }

    private function orderStatusLabel(int $status): string
    {
        return match ($status) {
            0 => 'cancelled',
            1 => 'pending',
            2 => 'confirmed',
            default => 'unknown',
        };
    }
}
