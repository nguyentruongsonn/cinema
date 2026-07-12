<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FoodAnalyticsService
{
    private const PRODUCT_MODEL = 'App\Models\Product';
    private const ORDER_PAID    = 2;

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
     * Returns same structure as ComboAnalyticsService for frontend compatibility.
     */
    public function getStats(string $startDate, string $endDate, ?string $type = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        // Validate type filter against allowed food types
        $types = ($type && in_array($type, self::FOOD_TYPES, true))
            ? [$type]
            : self::FOOD_TYPES;

        return [
            'summary'           => $this->getSummaryCompatible($start, $end, $types),
            'top_combos'        => $this->getTopProducts($start, $end, $types), // Renamed for compatibility
            'revenue_by_theater'=> [], // Food stats don't have theater breakdown
            'by_theater_combo'  => ['theater_names' => [], 'combo_names' => [], 'revenue_series' => [], 'qty_series' => []],
            'trend'             => $this->getTrend($start, $end, $types), // New method for daily trend
        ];
    }

    /* ── Summary Cards (Compatible with ComboAnalyticsService) ──────── */
    private function getSummaryCompatible(Carbon $start, Carbon $end, array $types): array
    {
        $base = $this->baseQuery($start, $end, $types);

        $totals = (clone $base)
            ->selectRaw('SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->first();

        // Best-selling product
        $best = (clone $base)
            ->selectRaw('products.name, SUM(order_items.quantity) as qty')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->first();

        return [
            'total_revenue'   => (float) ($totals->total_revenue ?? 0),
            'total_quantity'  => (int)   ($totals->total_qty ?? 0),
            'best_combo_name' => $best?->name ?? '—',  // "combo" for compatibility
            'best_combo_qty'  => (int) ($best?->qty ?? 0),
        ];
    }

    /* ── Daily Trend (Compatible with ComboAnalyticsService) ──────────── */
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

    /* ── Summary Cards (Original structure - kept for backward compatibility) ──────── */
    private function getSummary(Carbon $start, Carbon $end, array $types): array
    {
        $base = $this->baseQuery($start, $end, $types);

        $totals = (clone $base)
            ->selectRaw('SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->first();

        // Best-selling (by quantity)
        $bestByQty = (clone $base)
            ->selectRaw('products.name, SUM(order_items.quantity) as qty')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->first();

        // Highest revenue product
        $bestByRevenue = (clone $base)
            ->selectRaw('products.name, SUM(order_items.total_price) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->first();

        return [
            'total_qty'             => (int)   ($totals->total_qty     ?? 0),
            'total_revenue'         => (float) ($totals->total_revenue ?? 0),
            'best_qty_name'         => $bestByQty?->name    ?? '—',
            'best_qty_value'        => (int)   ($bestByQty?->qty      ?? 0),
            'best_revenue_name'     => $bestByRevenue?->name    ?? '—',
            'best_revenue_value'    => (float) ($bestByRevenue?->revenue ?? 0),
        ];
    }

    /* ── Food vs Drink ratio (Pie chart — always show all types) ─────── */
    private function getTypeRatio(Carbon $start, Carbon $end): array
    {
        $rows = $this->baseQuery($start, $end, self::FOOD_TYPES)
            ->selectRaw('products.type, SUM(order_items.quantity) as total_qty, SUM(order_items.total_price) as total_revenue')
            ->groupBy('products.type')
            ->get();

        return $rows->map(fn($r) => [
            'type'          => $r->type,
            'label'         => self::TYPE_LABELS[$r->type] ?? $r->type,
            'total_qty'     => (int)   $r->total_qty,
            'total_revenue' => (float) $r->total_revenue,
        ])->toArray();
    }

    /* ── Top Products by quantity (Bar chart) ─────────────────────── */
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
                'total_qty'     => (int)   $r->total_qty,
                'total_revenue' => (float) $r->total_revenue,
            ])->toArray();
    }

    /* ── Revenue trend by product (Row 4 – horizontal bar) ──────────── */
    private function getRevenueTrend(Carbon $start, Carbon $end, array $types, int $limit = 8): array
    {
        return $this->baseQuery($start, $end, $types)
            ->selectRaw('products.id, products.name, products.type, SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->groupBy('products.id', 'products.name', 'products.type')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'name'          => $r->name,
                'type_label'    => self::TYPE_LABELS[$r->type] ?? $r->type,
                'total_revenue' => (float) $r->total_revenue,
                'total_qty'     => (int)   $r->total_qty,
            ])->toArray();
    }

    /* ── Base Query Builder ─────────────────────────────────────────── */
    private function baseQuery(Carbon $start, Carbon $end, array $types)
    {
        return DB::table('order_items')
            ->join('orders',   'orders.id',   '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.item_id')
            ->where('order_items.item_type', self::PRODUCT_MODEL)
            ->whereIn('products.type', $types)
            ->where('orders.status', self::ORDER_PAID)
            ->whereBetween('orders.paid_at', [$start, $end]);
    }
}
