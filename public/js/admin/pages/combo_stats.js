/**
 * Combo Statistics Page JS
 * ApexCharts + Fetch API + Date Range Filter
 */
(function () {
    'use strict';

    const API = '/admin/combos/stats';
    const PALETTE = ['#e50914','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#a3e635','#06b6d4'];

    /* ── State ─────────────────────────────────────────────────────── */
    let state = { pollInterval: null };
    let charts = { trend: null, topCombos: null, revenue: null };
    let els = {};

    /* ── DOM Cache ──────────────────────────────────────────────────── */
    function cacheDoms() {
        els = {
            filterStart: document.getElementById('filterStart'),
            filterEnd: document.getElementById('filterEnd'),
            btnApply: document.getElementById('btnApplyFilter'),
            shortcuts: document.querySelectorAll('.btn-shortcut'),

            // Cards
            cardTotalCombos: document.getElementById('cardTotalCombos'),
            cardRevenue: document.getElementById('cardRevenue'),
            cardAvgPerDay: document.getElementById('cardAvgPerDay'),
            cardTopCombo: document.getElementById('cardTopCombo'),

            // Charts
            chartComboTrend: document.getElementById('chartComboTrend'),
            chartTopCombos: document.getElementById('chartTopCombos'),
            chartComboRevenue: document.getElementById('chartComboRevenue'),
        };
    }

    /* ── Helpers ────────────────────────────────────────────────────── */
    function formatCurrency(val) {
        if (val == null || isNaN(val)) return '0 ₫';
        const v = parseFloat(val);
        if (v >= 1e9)  return (v / 1e9).toFixed(1)  + ' tỷ ₫';
        if (v >= 1e6)  return (v / 1e6).toFixed(1)  + ' triệu ₫';
        if (v >= 1e3)  return (v / 1e3).toFixed(0)  + 'K ₫';
        return v.toLocaleString('vi-VN') + ' ₫';
    }

    function formatCurrencyFull(val) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    }

    function toDateStr(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function setDateRange(range) {
        const now = new Date();
        let start;
        switch (range) {
            case 'week':
                start = new Date(now);
                const day = now.getDay();
                const diff = now.getDate() - day + (day === 0 ? -6 : 1);
                start.setDate(diff);
                break;
            case 'month':
                start = new Date(now.getFullYear(), now.getMonth(), 1);
                break;
            case 'quarter':
                const q = Math.floor(now.getMonth() / 3);
                start = new Date(now.getFullYear(), q * 3, 1);
                break;
            case 'year':
                start = new Date(now.getFullYear(), 0, 1);
                break;
            default:
                start = new Date(now.getFullYear(), now.getMonth(), 1);
        }
        els.filterStart.value = toDateStr(start);
        els.filterEnd.value   = toDateStr(now);
    }

    function showLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = true;
            els.btnApply.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cập nhật...';
        }
        document.querySelectorAll('.stat-value, .stat-trend span, #chartComboTrend, #chartTopCombos, #chartComboRevenue').forEach(el => {
            el.classList.add('admin-skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');
        });
    }

    function hideLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = false;
            els.btnApply.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        document.querySelectorAll('.skeleton, .admin-skeleton').forEach(el => {
            el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart', 'admin-skeleton', 'admin-skeleton-text');
        });
    }

    /* ── Chart Initializers ─────────────────────────────────────────── */
    function initTrendChart() {
        if (!els.chartComboTrend) return;
        const opts = {
            series: [{ name: 'Combo bán ra', data: [] }],
            chart: { height: 300, type: 'area', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
            colors: ['#e50914'],
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] },
            },
            markers: { size: 0, hover: { size: 6 } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                title: { text: 'Số lượng combo', style: { color: '#a1a1aa', fontSize: '11px' } },
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
                min: 0
            },
            theme: { mode: 'dark' },
            grid: { borderColor: '#2e2e33', strokeDashArray: 4 },
        };
        charts.trend = new ApexCharts(els.chartComboTrend, opts);
        charts.trend.render();
    }

    function initTopCombosChart() {
        if (!els.chartTopCombos) return;
        const opts = {
            series: [],
            labels: [],
            chart: { type: 'donut', height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: PALETTE,
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '65%', labels: {
                show: true,
                total: {
                    show: true,
                    label: 'Tổng bán',
                    color: '#71717a',
                    fontSize: '13px',
                    formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('vi-VN'),
                },
                value: { color: '#ffffff', fontSize: '24px', fontWeight: 700, formatter: v => Number(v).toLocaleString('vi-VN') },
            } } } },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', labels: { colors: '#a1a1aa' }, fontSize: '12px' },
            tooltip: { theme: 'dark', style: { fontSize: '13px' } },
        };
        charts.topCombos = new ApexCharts(els.chartTopCombos, opts);
        charts.topCombos.render();
    }

    function initRevenueChart() {
        if (!els.chartComboRevenue) return;
        const opts = {
            series: [{ name: 'Doanh thu', data: [] }],
            chart: { type: 'bar', height: 350, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: ['#3b82f6'],
            plotOptions: {
                bar: { horizontal: true, borderRadius: 4, barHeight: '50%' }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: {
                    style: { colors: '#a1a1aa', fontSize: '11px' },
                    formatter: v => formatCurrency(v),
                },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: { style: { colors: '#a1a1aa', fontSize: '11px', fontWeight: 600 } }
            },
            theme: { mode: 'dark' },
            grid: { borderColor: '#2e2e33', strokeDashArray: 4 },
            tooltip: { theme: 'dark', style: { fontSize: '13px' }, y: { formatter: formatCurrencyFull } }
        };
        charts.revenue = new ApexCharts(els.chartComboRevenue, opts);
        charts.revenue.render();
    }

    /* ── Render functions ───────────────────────────────────────────── */
    function renderCards(summary, diffDays) {
        if (!summary) return;
        const qty = summary.total_quantity || 0;
        const rev = summary.total_revenue || 0;
        const avg = diffDays > 0 ? (qty / diffDays) : qty;

        if (els.cardTotalCombos) els.cardTotalCombos.textContent = qty.toLocaleString('vi-VN');
        if (els.cardRevenue)     els.cardRevenue.textContent     = formatCurrencyFull(rev);
        if (els.cardAvgPerDay)    els.cardAvgPerDay.textContent    = avg.toLocaleString('vi-VN', { maximumFractionDigits: 1 });
        if (els.cardTopCombo)     els.cardTopCombo.textContent     = summary.best_combo_name || 'N/A';
    }

    function renderTrendChart(trend) {
        if (!charts.trend || !trend?.length) return;
        const categories = trend.map(r => r.date);
        const data       = trend.map(r => r.count);
        let opts = { xaxis: { categories } };
        if (data.length === 1) {
            opts.markers = { size: 6, strokeWidth: 2, hover: { size: 8 } };
        } else {
            opts.markers = { size: 0 };
        }
        charts.trend.updateOptions(opts);
        charts.trend.updateSeries([{ name: 'Combo bán ra', data }]);
    }

    function renderTopCombosChart(topCombos) {
        if (!charts.topCombos || !topCombos?.length) return;
        const labels = topCombos.map(d => d.name);
        const series = topCombos.map(d => d.total_qty);
        charts.topCombos.updateOptions({ labels });
        charts.topCombos.updateSeries(series);
    }

    function renderRevenueChart(topCombos) {
        if (!charts.revenue || !topCombos?.length) return;
        const categories = topCombos.map(d => d.name);
        const data       = topCombos.map(d => d.total_revenue);
        charts.revenue.updateOptions({ xaxis: { categories } });
        charts.revenue.updateSeries([{ name: 'Doanh thu', data }]);
    }

    /* ── Wait for authManager ────────────────────────────────────────── */
    function waitForAuth(callback, retries = 50) {
        if (typeof authManager !== 'undefined' && authManager.authChecked) {
            callback();
            return;
        }
        if (retries <= 0) {
            console.error('[Combos] authManager check timed out. loadStats aborted.');
            return;
        }
        setTimeout(() => waitForAuth(callback, retries - 1), 150);
    }

    /* ── API call ───────────────────────────────────────────────────── */
    async function loadStats() {
        if (typeof authManager === 'undefined') return;
        const start = els.filterStart?.value;
        const end   = els.filterEnd?.value;
        if (!start || !end) return;

        const startD = new Date(start);
        const endD   = new Date(end);
        const diffTime = Math.abs(endD - startD);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1;

        showLoading();
        try {
            const url = `${API}?start_date=${start}&end_date=${end}`;
            const res = await authManager.fetchAPI(url, { silentAuth: true });

            if (res?.success) {
                const d = res.data;
                renderCards(d.summary, diffDays);
                renderTrendChart(d.trend);
                renderTopCombosChart(d.top_combos);
                renderRevenueChart(d.top_combos);
            }
        } catch (e) {
            console.error('[Combos] Error:', e);
        } finally {
            hideLoading();
        }
    }

    /* ── Events ─────────────────────────────────────────────────────── */
    function bindEvents() {
        els.shortcuts.forEach(btn => {
            btn.addEventListener('click', () => {
                els.shortcuts.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                setDateRange(btn.dataset.range);
                loadStats();
            });
        });

        els.btnApply?.addEventListener('click', loadStats);

        [els.filterStart, els.filterEnd].forEach(inp => {
            inp?.addEventListener('change', loadStats);
        });
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    function init() {
        setDateRange('week'); // default: this week
        initTrendChart();
        initTopCombosChart();
        initRevenueChart();
        bindEvents();
        waitForAuth(loadStats);

        // Auto polling every 30 seconds
        state.pollInterval = setInterval(loadStats, 30000);
    }

    // Cleanup interval on page unload
    window.addEventListener('beforeunload', () => {
        if (state.pollInterval) clearInterval(state.pollInterval);
    });

    document.addEventListener('DOMContentLoaded', () => {
        cacheDoms();
        init();
    });

})();
