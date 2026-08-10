<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ComboAnalyticsService
{
    public const TYPE_COMBO = 'combo';

    private const PRODUCT_TYPE = Product::class;
    private const COMBO_TYPE = Combo::class;
    private const FOOD_TYPES = ['food', 'drink'];
    private const MAX_RANGE_DAYS = 366;
    private const TOP_ITEMS_LIMIT = 20;
    private const MAX_BREAKDOWN_ITEMS = 1000;

    /**
     * Get analytics for the given date range and type.
     *
     * @param string $type 'combo' for combo packages, 'food' for individual food/drinks
     */
    public function getStats(string $startDate, string $endDate, string $type = 'food'): array
    {
        $type = $this->normalizeType($type);
        [$start, $end] = $this->parseDateRange($startDate, $endDate);
        $summary = $this->getSummary($start, $end, $type);
        $days = $start->diffInDays($end);
        $compareEnd = $start->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days)->startOfDay();
        $previousTotals = $this->getTotals($compareStart, $compareEnd, $type);

        $summary['trends'] = [
            'total_quantity' => $this->calculateTrend((float) $summary['total_quantity'], (float) $previousTotals['total_quantity']),
            'total_revenue' => $this->calculateTrend((float) $summary['total_revenue'], (float) $previousTotals['total_revenue']),
            'avg_per_day' => $this->calculateTrend((float) $summary['total_quantity'], (float) $previousTotals['total_quantity']),
        ];

        return [
            'summary' => $summary,
            'top_combos' => $this->getTopCombos($start, $end, $type),
            'revenue_by_theater' => $this->getRevenueByTheater($start, $end, $type),
            'by_theater_combo' => $this->getByTheaterCombo($start, $end, $type),
            'trend' => $this->getTrend($start, $end, $type),
        ];
    }

    private function normalizeType(string $type): string
    {
        if (!in_array($type, ['food', self::TYPE_COMBO], true)) {
            throw new InvalidArgumentException('Invalid analytics type.');
        }

        return $type;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseDateRange(string $startDate, string $endDate): array
    {
        try {
            $start = Carbon::createFromFormat('!Y-m-d', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('!Y-m-d', $endDate)->endOfDay();

            if ($start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
                throw new InvalidArgumentException('Invalid date range.');
            }
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Invalid date range.', 0, $exception);
        }

        if ($start->gt($end)) {
            throw new InvalidArgumentException('Start date must be before end date.');
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException('Date range is too large.');
        }

        return [$start, $end];
    }

    private function getTrend(Carbon $start, Carbon $end, string $type): array
    {
        $rows = $this->baseComboQuery($start, $end, $type)
            ->selectRaw('DATE(orders.paid_at) as date, SUM(order_items.quantity) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn($r) => [
            'date'  => $r->date,
            'count' => (int) $r->count,
        ])->toArray();
    }

    /* ── Summary Cards ─────────────────────────────────────────────── */
    private function getSummary(Carbon $start, Carbon $end, string $type): array
    {
        $base = $this->baseComboQuery($start, $end, $type);
        $totals = $this->getTotals($start, $end, $type);

        // Best-selling combo
        $table = $this->getTableAlias($type);
        $bestCombo = (clone $base)
            ->selectRaw("{$table}.name, SUM(order_items.quantity) as qty")
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderByDesc('qty')
            ->first();

        return [
            'total_revenue'   => $totals['total_revenue'],
            'total_quantity'  => $totals['total_quantity'],
            'best_combo_name' => data_get($bestCombo, 'name', '—'),
            'best_combo_qty'  => (int) data_get($bestCombo, 'qty', 0),
        ];
    }

    private function getTotals(Carbon $start, Carbon $end, string $type): array
    {
        $row = $this->baseComboQuery($start, $end, $type)
            ->selectRaw('SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->first();

        return [
            'total_revenue' => (string) ($row->total_revenue ?? '0.00'),
            'total_quantity' => (int) ($row->total_qty ?? 0),
        ];
    }

    private function calculateTrend(float $current, float $previous): float
    {
        if ($previous === 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /* ── Top Combos (by revenue) ────────────────────────────────────── */
    private function getTopCombos(Carbon $start, Carbon $end, string $type): array
    {
        $table = $this->getTableAlias($type);
        $rows = $this->baseComboQuery($start, $end, $type)
            ->selectRaw("{$table}.id, {$table}.name, SUM(order_items.quantity) as total_qty, SUM(order_items.total_price) as total_revenue")
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderByDesc('total_revenue')
            ->limit(self::TOP_ITEMS_LIMIT)
            ->get();

        return $rows->map(fn($r) => [
            'name'    => $r->name,
            'revenue' => (string) $r->total_revenue,
            'qty'     => (int)   $r->total_qty,
        ])->toArray();
    }

    /* ── Revenue by Theater (without ticket join multiplication) ──────── */
    private function getRevenueByTheater(Carbon $start, Carbon $end, string $type): array
    {
        // Use a distinct order-to-theater mapping to avoid multiplying revenue by ticket count
        $orderTheaterMap = DB::table('tickets')
            ->select('tickets.order_id', 'theaters.id as theater_id', 'theaters.name as theater_name')
            ->join('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->groupBy('tickets.order_id', 'theaters.id', 'theaters.name');

        $rows = $this->baseComboQuery($start, $end, $type)
            ->joinSub($orderTheaterMap, 'order_theaters', 'order_theaters.order_id', '=', 'orders.id')
            ->selectRaw('order_theaters.theater_id, order_theaters.theater_name, SUM(order_items.total_price) as total_revenue')
            ->groupBy('order_theaters.theater_id', 'order_theaters.theater_name')
            ->orderByDesc('total_revenue')
            ->get();

        return $rows->map(fn($r) => [
            'theater_name' => $r->theater_name,
            'revenue'      => (string) $r->total_revenue,
        ])->toArray();
    }

    /* ── By Theater-Combo Breakdown (without ticket join multiplication) ─ */
    private function getByTheaterCombo(Carbon $start, Carbon $end, string $type): array
    {
        $table = $this->getTableAlias($type);

        // Use distinct order-to-theater mapping
        $orderTheaterMap = DB::table('tickets')
            ->select('tickets.order_id', 'theaters.id as theater_id', 'theaters.name as theater_name')
            ->join('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
            ->join('screens', 'screens.id', '=', 'showtimes.screen_id')
            ->join('theaters', 'theaters.id', '=', 'screens.theater_id')
            ->groupBy('tickets.order_id', 'theaters.id', 'theaters.name');

        $rows = $this->baseComboQuery($start, $end, $type)
            ->joinSub($orderTheaterMap, 'order_theaters', 'order_theaters.order_id', '=', 'orders.id')
            ->selectRaw("order_theaters.theater_name, {$table}.name as combo_name, SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty")
            ->groupBy('order_theaters.theater_id', 'order_theaters.theater_name', "{$table}.id", "{$table}.name")
            ->orderBy('order_theaters.theater_name')
            ->limit(self::MAX_BREAKDOWN_ITEMS)
            ->get();

        $theaters = [];
        $combos   = [];

        foreach ($rows as $row) {
            $theaters[$row->theater_name] ??= [];
            $theaters[$row->theater_name][$row->combo_name] = [
                'revenue' => (float) $row->total_revenue,
                'qty'     => (int)   $row->total_qty,
            ];
            $combos[$row->combo_name] = true;
        }

        $comboNames = array_keys($combos);
        sort($comboNames);

        // Build ApexCharts-friendly series arrays
        $revenueSeries = [];
        $qtySeries     = [];

        foreach ($comboNames as $combo) {
            $revData = [];
            $qtyData = [];
            foreach (array_keys($theaters) as $theater) {
                $revData[] = $theaters[$theater][$combo]['revenue'] ?? 0;
                $qtyData[] = $theaters[$theater][$combo]['qty']     ?? 0;
            }
            $revenueSeries[] = ['name' => $combo, 'data' => $revData];
            $qtySeries[]     = ['name' => $combo, 'data' => $qtyData];
        }

        return [
            'theater_names'  => array_keys($theaters),
            'combo_names'    => $comboNames,
            'revenue_series' => $revenueSeries,
            'qty_series'     => $qtySeries,
        ];
    }

    /* ── Query Builders ─────────────────────────────────────────────── */
    private function baseComboQuery(Carbon $start, Carbon $end, string $type): Builder
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id');

        if ($type === self::TYPE_COMBO) {
            // Query actual combo packages.
            $query->join('combos', 'combos.id', '=', 'order_items.item_id')
                ->where('order_items.item_type', self::COMBO_TYPE);
        } else {
            // Query individual food/drink products.
            $query->join('products', 'products.id', '=', 'order_items.item_id')
                ->where('order_items.item_type', self::PRODUCT_TYPE)
                ->whereIn('products.type', self::FOOD_TYPES);
        }

        return $query->where('orders.status', Order::STATUS_CONFIRMED)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$start, $end]);
    }

    private function getTableAlias(string $type): string
    {
        return $type === self::TYPE_COMBO ? 'combos' : 'products';
    }
}
