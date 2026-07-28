<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Revenue reporting service.
 *
 * IMPORTANT: Revenue calculations are based on successful payment records,
 * not order totals. This ensures accuracy even with refunds, payment failures,
 * and order adjustments.
 *
 * Ticket metrics count actual ticket order items (item_type = 'App\Models\Ticket'),
 * not order counts.
 *
 * Theater/movie revenue reports ticket revenue only, excluding food/combos.
 * Use dashboard service for gross order revenue.
 */
class RevenueService
{
    /** Maximum allowed date range for revenue reports (days) */
    private const MAX_REPORT_RANGE_DAYS = 366;

    /** Date format for strict parsing */
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Get all revenue statistics for the given date range.
     *
     * @param string $startDate Date in Y-m-d format
     * @param string $endDate Date in Y-m-d format
     * @return array Revenue statistics with ticket-based metrics
     * @throws InvalidArgumentException If dates are invalid or range exceeds limit
     */
    public function getStats(string $startDate, string $endDate): array
    {
        [$start, $end] = $this->validateAndParseDates($startDate, $endDate);

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

    /**
     * Validate and parse date range.
     *
     * @throws InvalidArgumentException
     */
    private function validateAndParseDates(string $startDate, string $endDate): array
    {
        try {
            $start = Carbon::createFromFormat(self::DATE_FORMAT, $startDate)->startOfDay();
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid start date format. Expected Y-m-d, got: {$startDate}");
        }

        try {
            $end = Carbon::createFromFormat(self::DATE_FORMAT, $endDate)->endOfDay();
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid end date format. Expected Y-m-d, got: {$endDate}");
        }

        if ($start->gt($end)) {
            throw new InvalidArgumentException('Start date must be before or equal to end date.');
        }

        $rangeDays = $start->diffInDays($end);
        if ($rangeDays > self::MAX_REPORT_RANGE_DAYS) {
            throw new InvalidArgumentException(
                "Revenue report range too large. Maximum " . self::MAX_REPORT_RANGE_DAYS . " days, requested {$rangeDays} days."
            );
        }

        return [$start, $end];
    }

    /**
     * Get revenue summary.
     *
     * Returns total ticket revenue from successful payments and total confirmed orders.
     * Note: This counts ticket revenue only, not including food/combos.
     */
    private function getSummary(Carbon $start, Carbon $end): array
    {
        // Single query for both revenue and order count
        $summary = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->selectRaw('
                SUM(payments.amount) as total_revenue,
                COUNT(DISTINCT orders.id) as total_orders
            ')
            ->first();

        // Count actual tickets sold (not orders)
        $ticketCount = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('order_items.item_type', 'App\\Models\\Ticket')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->sum('order_items.quantity');

        return [
            'total_revenue' => (string) ($summary->total_revenue ?? '0.00'),
            'total_orders'  => (int) ($summary->total_orders ?? 0),
            'total_tickets' => (int) $ticketCount,
        ];
    }

    /**
     * Get top performing theater by ticket revenue.
     */
    private function getTopTheater(Carbon $start, Carbon $end): array
    {
        // Calculate total ticket revenue for percentage
        $totalRevenue = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('order_items.item_type', 'App\\Models\\Ticket')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->sum(DB::raw('order_items.quantity * order_items.unit_price'));

        // Get top theater by ticket revenue
        $top = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->where('order_items.item_type', 'App\\Models\\Ticket')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->selectRaw('
                theaters.id,
                theaters.name,
                SUM(order_items.quantity * order_items.unit_price) as revenue
            ')
            ->groupBy('theaters.id', 'theaters.name')
            ->orderByDesc('revenue')
            ->first();

        if (!$top) {
            return ['name' => 'N/A', 'revenue' => '0.00', 'percentage' => 0];
        }

        $pct = $totalRevenue > 0 ? round(($top->revenue / $totalRevenue) * 100, 1) : 0;

        return [
            'name'       => $top->name,
            'revenue'    => (string) number_format((float) $top->revenue, 2, '.', ''),
            'percentage' => $pct,
        ];
    }

    /**
     * Get top performing movie by ticket revenue.
     */
    private function getTopMovie(Carbon $start, Carbon $end): array
    {
        $top = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('order_items.item_type', 'App\\Models\\Ticket')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->selectRaw('
                movies.id,
                movies.title,
                SUM(order_items.quantity * order_items.unit_price) as revenue,
                SUM(order_items.quantity) as tickets
            ')
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('revenue')
            ->first();

        if (!$top) {
            return ['title' => 'N/A', 'revenue' => '0.00', 'tickets' => 0];
        }

        return [
            'title'   => $top->title,
            'revenue' => (string) number_format((float) $top->revenue, 2, '.', ''),
            'tickets' => (int) $top->tickets,
        ];
    }

    /**
     * Get payment method breakdown.
     *
     * Counts only successful, non-refunded payments.
     */
    private function getPaymentMethods(Carbon $start, Carbon $end): array
    {
        $methods = DB::table('payments')
            ->where('status', Payment::STATUS_SUCCESS)
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('
                method,
                COUNT(DISTINCT order_id) as count,
                SUM(amount) as amount
            ')
            ->groupBy('method')
            ->get();

        $totalCount = $methods->sum('count');
        $totalAmount = $methods->sum('amount');
        $topMethod  = $methods->sortByDesc('amount')->first();

        if (!$topMethod) {
            return [
                'top_method'       => 'N/A',
                'top_method_count' => 0,
                'top_method_amount' => '0.00',
                'top_method_pct'   => 0,
                'breakdown'        => [],
            ];
        }

        $pct = $totalAmount > 0 ? round(($topMethod->amount / $totalAmount) * 100, 1) : 0;

        return [
            'top_method'        => $topMethod->method ?? 'N/A',
            'top_method_count'  => (int) $topMethod->count,
            'top_method_amount' => (string) number_format((float) $topMethod->amount, 2, '.', ''),
            'top_method_pct'    => $pct,
            'total_count'       => (int) $totalCount,
            'total_amount'      => (string) number_format((float) $totalAmount, 2, '.', ''),
            'breakdown'         => $methods->map(fn($m) => [
                'method'        => $m->method ?? 'Khác',
                'count'         => (int) $m->count,
                'amount'        => (string) number_format((float) $m->amount, 2, '.', ''),
                'count_percent' => $totalCount > 0 ? round(($m->count / $totalCount) * 100, 1) : 0,
                'amount_percent' => $totalAmount > 0 ? round(($m->amount / $totalAmount) * 100, 1) : 0,
            ])->values()->toArray(),
        ];
    }

    /**
     * Get ticket revenue breakdown by theater.
     */
    private function getRevenueByTheater(Carbon $start, Carbon $end): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->where('order_items.item_type', 'App\\Models\\Ticket')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->selectRaw('
                theaters.name,
                SUM(order_items.quantity * order_items.unit_price) as revenue
            ')
            ->groupBy('theaters.id', 'theaters.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($r) => [
                'name' => $r->name,
                'revenue' => (string) number_format((float) $r->revenue, 2, '.', ''),
            ])
            ->toArray();
    }

    /**
     * Get ticket revenue breakdown by movie (top 10).
     */
    private function getRevenueByMovie(Carbon $start, Carbon $end): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('order_items.item_type', 'App\\Models\\Ticket')
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->selectRaw('
                movies.title,
                SUM(order_items.quantity * order_items.unit_price) as revenue,
                SUM(order_items.quantity) as tickets
            ')
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'title'   => $r->title,
                'revenue' => (string) number_format((float) $r->revenue, 2, '.', ''),
                'tickets' => (int) $r->tickets,
            ])
            ->toArray();
    }

    /**
     * Get revenue trend over time.
     *
     * Groups by day, week, or month based on range length.
     * Note: Uses database timezone for grouping.
     */
    private function getRevenueTrend(Carbon $start, Carbon $end): array
    {
        $diff = $start->diffInDays($end);

        // Determine grouping granularity
        if ($diff > 60) {
            $groupBy = 'DATE_FORMAT(paid_at, \'%Y-%m\') as period';
        } elseif ($diff > 14) {
            $groupBy = 'DATE_FORMAT(paid_at, \'%Y-%u\') as period';
        } else {
            $groupBy = 'DATE(paid_at) as period';
        }

        return DB::table('payments')
            ->where('status', Payment::STATUS_SUCCESS)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw("{$groupBy}, SUM(amount) as revenue, COUNT(DISTINCT order_id) as orders")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($r) => [
                'period'  => $r->period,
                'revenue' => (string) number_format((float) $r->revenue, 2, '.', ''),
                'orders'  => (int) $r->orders,
            ])
            ->toArray();
    }
}
