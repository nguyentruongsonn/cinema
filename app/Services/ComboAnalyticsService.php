<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComboAnalyticsService
{
    private const PRODUCT_TYPE  = 'App\Models\Product';
    private const COMBO_TYPE    = 'combo';
    private const ORDER_PAID    = 2;

    /**
     * Get all combo analytics for the given date range.
     */
    public function getStats(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        return [
            'summary'           => $this->getSummary($start, $end),
            'top_combos'        => $this->getTopCombos($start, $end),
            'revenue_by_theater'=> $this->getRevenueByTheater($start, $end),
            'by_theater_combo'  => $this->getByTheaterCombo($start, $end),
        ];
    }

    /* ── Summary Cards ─────────────────────────────────────────────── */
    private function getSummary(Carbon $start, Carbon $end): array
    {
        $base = $this->baseComboQuery($start, $end);

        $row = (clone $base)
            ->selectRaw('SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->first();

        // Best-selling combo
        $bestCombo = (clone $base)
            ->selectRaw('products.name, SUM(order_items.quantity) as qty')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->first();

        return [
            'total_revenue'   => (float) ($row->total_revenue ?? 0),
            'total_quantity'  => (int)   ($row->total_qty ?? 0),
            'best_combo_name' => $bestCombo?->name ?? '—',
            'best_combo_qty'  => (int) ($bestCombo?->qty ?? 0),
        ];
    }

    /* ── Top Combos (by revenue) ────────────────────────────────────── */
    private function getTopCombos(Carbon $start, Carbon $end): array
    {
        return (array) $this->baseComboQuery($start, $end)
            ->selectRaw('products.id, products.name, SUM(order_items.quantity) as total_qty, SUM(order_items.total_price) as total_revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'total_qty'     => (int)   $r->total_qty,
                'total_revenue' => (float) $r->total_revenue,
            ])->toArray();
    }

    /* ── Revenue by Theater (Pie chart) ─────────────────────────────── */
    private function getRevenueByTheater(Carbon $start, Carbon $end): array
    {
        return (array) $this->baseComboWithTheaterQuery($start, $end)
            ->selectRaw('theaters.id, theaters.name, SUM(order_items.total_price) as total_revenue')
            ->groupBy('theaters.id', 'theaters.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($r) => [
                'theater_name'  => $r->name,
                'total_revenue' => (float) $r->total_revenue,
            ])->toArray();
    }

    /* ── Per-theater breakdown by combo (for grouped bar charts) ────── */
    private function getByTheaterCombo(Carbon $start, Carbon $end): array
    {
        $rows = $this->baseComboWithTheaterQuery($start, $end)
            ->selectRaw('theaters.name as theater_name, products.name as combo_name, SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty')
            ->groupBy('theaters.id', 'theaters.name', 'products.id', 'products.name')
            ->orderBy('theaters.name')
            ->get();

        // Pivot to { theater => { combo => {revenue, qty} } }
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
    private function baseComboQuery(Carbon $start, Carbon $end)
    {
        return DB::table('order_items')
            ->join('orders',   'orders.id',   '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.item_id')
            ->where('order_items.item_type', self::PRODUCT_TYPE)
            ->where('products.type',         self::COMBO_TYPE)
            ->where('orders.status',         self::ORDER_PAID)
            ->whereBetween('orders.paid_at', [$start, $end]);
    }

    private function baseComboWithTheaterQuery(Carbon $start, Carbon $end)
    {
        return $this->baseComboQuery($start, $end)
            ->join('tickets',   'tickets.order_id',   '=', 'orders.id')
            ->join('showtimes', 'showtimes.id',        '=', 'tickets.showtime_id')
            ->join('screens',   'screens.id',          '=', 'showtimes.screen_id')
            ->join('theaters',  'theaters.id',         '=', 'screens.theater_id');
    }
}
