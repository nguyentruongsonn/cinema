<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Food Analytics Service
 *
 * Provides aggregated analytics for food product sales based on paid order data.
 *
 * REPORTING SEMANTICS:
 * - Date range: filters by orders.paid_at (payment completion date)
 * - Revenue source: order_items.total_price for products in paid orders
 * - Paid orders: orders.status = Order::STATUS_CONFIRMED (value 2)
 * - Product filter: order_items.item_type = Product::class (polymorphic type)
 *
 * API COMPATIBILITY NOTE:
 * - Response keys use combo terminology ('top_combos', 'best_combo_name')
 *   for frontend compatibility with ComboAnalyticsService
 * - This is a known contract coupling that should be addressed in a future
 *   versioned API or adapter layer
 *
 * VALIDATION:
 * - Date inputs are strictly parsed as Y-m-d format
 * - Maximum reporting range is 366 days
 * - Invalid type filters are rejected (fail closed)
 *
 * @package App\Services
 */
class FoodAnalyticsService
{
    private const MAX_RANGE_DAYS = 366;

    // Exclude combo from food stats
    private const FOOD_TYPES = ['popcorn', 'drink', 'snack'];

    // Human-readable labels
    private const TYPE_LABELS = [
        'popcorn' => 'Bắp rang',
        'drink'   => 'Đồ uống',
        'snack'   => 'Đồ ăn nhẹ',
    ];

    /**
     * Get all food analytics for the given date range and optional type filter.
     *
     * Returns same structure as ComboAnalyticsService for frontend compatibility.
     *
     * @param string $startDate Date in Y-m-d format
     * @param string $endDate Date in Y-m-d format
     * @param string|null $type Optional product type filter (must be in FOOD_TYPES)
     * @return array Analytics data structure
     * @throws InvalidArgumentException If dates are invalid, reversed, or range too large
     */
    public function getStats(string $startDate, string $endDate, ?string $type = null): array
    {
        // Strict date parsing to prevent invalid/ambiguous inputs
        $start = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end) {
            throw new InvalidArgumentException('Invalid date format. Expected Y-m-d.');
        }

        $start = $start->startOfDay();
        $end = $end->endOfDay();

        // Validate date ordering
        if ($start->gt($end)) {
            throw new InvalidArgumentException('Start date must be before or equal to end date.');
        }

        // Enforce maximum reporting range to protect database performance
        $rangeDays = $start->diffInDays($end);
        if ($rangeDays > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException(
                'Analytics range is too large. Maximum ' . self::MAX_RANGE_DAYS . ' days allowed.'
            );
        }

        // Validate type filter - fail closed if invalid type provided
        if ($type !== null && !in_array($type, self::FOOD_TYPES, true)) {
            throw new InvalidArgumentException(
                'Invalid food type. Allowed types: ' . implode(', ', self::FOOD_TYPES)
            );
        }

        $types = $type ? [$type] : self::FOOD_TYPES;
        $summary = $this->getSummaryCompatible($start, $end, $types);
        $compareEnd = $start->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($rangeDays)->startOfDay();
        $previousTotals = $this->getTotals($compareStart, $compareEnd, $types);

        $summary['trends'] = [
            'total_quantity' => $this->calculateTrend((float) $summary['total_quantity'], (float) $previousTotals['total_quantity']),
            'total_revenue' => $this->calculateTrend((float) $summary['total_revenue'], (float) $previousTotals['total_revenue']),
            'avg_per_day' => $this->calculateTrend((float) $summary['total_quantity'], (float) $previousTotals['total_quantity']),
        ];

        return [
            'summary'           => $summary,
            'top_combos'        => $this->getTopProducts($start, $end, $types), // Renamed for compatibility
            'revenue_by_theater'=> [], // Food stats don't have theater breakdown
            'by_theater_combo'  => ['theater_names' => [], 'combo_names' => [], 'revenue_series' => [], 'qty_series' => []],
            'trend'             => $this->getTrend($start, $end, $types),
        ];
    }

    /**
     * Get summary cards compatible with ComboAnalyticsService response structure.
     */
    private function getSummaryCompatible(Carbon $start, Carbon $end, array $types): array
    {
        $base = $this->baseQuery($start, $end, $types);
        $totals = $this->getTotals($start, $end, $types);

        // Best-selling product by quantity
        $best = (clone $base)
            ->selectRaw('products.name, SUM(order_items.quantity) as qty')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->first();

        // Return money as string to avoid float precision issues
        return [
            'total_revenue'   => $totals['total_revenue'],
            'total_quantity'  => $totals['total_quantity'],
            'best_combo_name' => data_get($best, 'name', '—'),  // "combo" for frontend compatibility
            'best_combo_qty'  => (int) data_get($best, 'qty', 0),
        ];
    }

    private function getTotals(Carbon $start, Carbon $end, array $types): array
    {
        $totals = $this->baseQuery($start, $end, $types)
            ->selectRaw('SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->first();

        return [
            'total_revenue' => number_format((float) ($totals->total_revenue ?? 0), 2, '.', ''),
            'total_quantity' => (int) ($totals->total_qty ?? 0),
        ];
    }

    private function calculateTrend(float $current, float $previous): float
    {
        if ($previous === 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get daily quantity trend.
     *
     * NOTE: Returns only dates with data. Frontend must handle missing dates.
     * Database timezone applies to DATE(orders.paid_at) grouping.
     */
    private function getTrend(Carbon $start, Carbon $end, array $types): array
    {
        $rows = $this->baseQuery($start, $end, $types)
            ->selectRaw('DATE(orders.paid_at) as date, SUM(order_items.quantity) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn($r) => [
            'date'  => $r->date,
            'count' => (int) $r->count,
        ])->toArray();
    }

    /**
     * Get top products by quantity with revenue data.
     */
    private function getTopProducts(Carbon $start, Carbon $end, array $types, int $limit = 10): array
    {
        return $this->baseQuery($start, $end, $types)
            ->selectRaw('products.id, products.name, products.type, SUM(order_items.quantity) as total_qty, SUM(order_items.total_price) as total_revenue')
            ->groupBy('products.id', 'products.name', 'products.type')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'type'          => $r->type,
                'type_label'    => self::TYPE_LABELS[$r->type] ?? $r->type,
                'total_qty'     => (int) $r->total_qty,
                'total_revenue' => number_format((float) $r->total_revenue, 2, '.', ''),
            ])->toArray();
    }

    /**
     * Build base analytics query for paid food orders.
     *
     * Filters:
     * - Paid orders only (orders.status = Order::STATUS_CONFIRMED)
     * - Food product items only (item_type = Product::class)
     * - Specified product types (popcorn, drink, snack)
     * - Date range on orders.paid_at
     *
     * @return Builder Query builder instance
     */
    private function baseQuery(Carbon $start, Carbon $end, array $types): Builder
    {
        return DB::table('order_items')
            ->join('orders',   'orders.id',   '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.item_id')
            ->where('order_items.item_type', Product::class)
            ->whereIn('products.type', $types)
            ->where('orders.status', Order::STATUS_CONFIRMED)
            ->whereBetween('orders.paid_at', [$start, $end]);
    }
}
