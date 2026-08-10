/**
 * Ticket Statistics Dashboard Script
 */
(function () {
    'use strict';

    const API = '/admin/tickets/stats';

    // Shared admin chart palette: keep ticket stats visually aligned with revenue/dashboard pages.
    const PALETTE = ['#e50914','#22c55e','#38bdf8','#fbbf24','#a78bfa','#fb7185','#2dd4bf','#f97316','#84cc16','#60a5fa'];
    const PRIMARY_COLOR = '#e50914';
    const SURFACE_STROKE_COLOR = '#1e1e24';
    const TEXT_COLOR = '#e4e4e7';
    const MUTED_TEXT_COLOR = '#c7c7d1';
    const GRID_COLOR = 'rgba(255,255,255,0.08)';

    /* ── DOM Elements ───────────────────────────────────────────────── */
    const els = {
        filterStart: null,
        filterEnd: null,
        shortcuts: [],
        btnApply: null,

        // Cards
        cardTotalTickets: null,
        cardTotalTicketsTrend: null,
        cardAvgPerDay: null,
        cardAvgPerDayTrend: null,
        cardPeakHour: null,
        cardOccupancyRate: null,
        cardOccupancyRateTrend: null,

        // Chart Containers
        chartTicketTrend: null,
        chartTopMovies: null,
        chartTheaterOccupancy: null,
    };

    // Chart instances
    const charts = {
        trend: null,
        movies: null,
        occupancy: null,
    };

    function cacheDoms() {
        els.filterStart = document.getElementById('filterStart');
        els.filterEnd   = document.getElementById('filterEnd');
        els.shortcuts   = document.querySelectorAll('[data-range]');
        els.btnApply    = document.getElementById('btnApplyFilter');

        els.cardTotalTickets  = document.getElementById('cardTotalTickets');
        els.cardTotalTicketsTrend = document.getElementById('cardTotalTicketsTrend');
        els.cardAvgPerDay     = document.getElementById('cardAvgPerDay');
        els.cardAvgPerDayTrend = document.getElementById('cardAvgPerDayTrend');
        els.cardPeakHour      = document.getElementById('cardPeakHour');
        els.cardOccupancyRate = document.getElementById('cardOccupancyRate');
        els.cardOccupancyRateTrend = document.getElementById('cardOccupancyRateTrend');

        els.chartTicketTrend      = document.getElementById('chartTicketTrend');
        els.chartTopMovies        = document.getElementById('chartTopMovies');
        els.chartTheaterOccupancy = document.getElementById('chartTheaterOccupancy');
    }

    /* ── Helpers ────────────────────────────────────────────────────── */
    function toDateStr(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function setDateRange(range) {
        const now = new Date();
        let start, end = new Date(now);

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
                start = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1);
                break;
            case 'year':
                start = new Date(now.getFullYear(), 0, 1);
                break;
            default:
                start = new Date(now);
        }

        if (els.filterStart && els.filterEnd) {
            els.filterStart.value = toDateStr(start);
            els.filterEnd.value   = toDateStr(end);
        }
    }

    function showLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = true;
            els.btnApply.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cập nhật...';
        }
        document.querySelectorAll('#adminPageContent .stat-value[id], #adminPageContent .stat-trend span[id], #chartTicketTrend, #chartTopMovies, #chartTheaterOccupancy').forEach(el => {
            el.classList.add('admin-skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');
        });
    }

    function renderTrend(element, value) {
        if (!element) return;

        const trend = Number(value || 0);
        const absoluteTrend = Math.abs(trend).toLocaleString('vi-VN', { maximumFractionDigits: 1 });
        element.className = trend > 0 ? 'text-success fw-bold' : trend < 0 ? 'text-danger fw-bold' : 'text-secondary fw-bold';
        element.innerHTML = trend > 0
            ? `<i class="bi bi-graph-up-arrow"></i> +${absoluteTrend}%`
            : trend < 0
                ? `<i class="bi bi-graph-down-arrow"></i> -${absoluteTrend}%`
                : '<i class="bi bi-dash"></i> 0%';
    }

    function hideLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = false;
            els.btnApply.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        document.querySelectorAll('#adminPageContent .admin-skeleton').forEach(el => {
            el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart', 'admin-skeleton', 'admin-skeleton-text');
        });
    }

    /* ── Chart Initializers ─────────────────────────────────────────── */
    function initTrendChart() {
        if (!els.chartTicketTrend) return;
        const opts = {
            series: [{ name: 'Vé bán ra', data: [] }],
            chart: { height: 300, width: '100%', type: 'area', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: [PRIMARY_COLOR],
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 0.6, opacityFrom: 0.22, opacityTo: 0.02, stops: [0, 100] },
            },
            markers: { size: 4, strokeWidth: 2, hover: { size: 7, sizeOffset: 2 } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                title: { text: 'Số lượng vé', style: { color: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
                labels: { style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
            },
            theme: { mode: 'dark' },
            grid: { borderColor: GRID_COLOR, strokeDashArray: 4 },
            tooltip: { theme: 'dark', shared: false, intersect: false, followCursor: false, fixed: { enabled: true, position: 'topRight', offsetX: -16, offsetY: 12 } },
        };
        charts.trend = new ApexCharts(els.chartTicketTrend, opts);
        charts.trend.render();
    }

    function initTopMoviesChart() {
        if (!els.chartTopMovies) return;
        const opts = {
            series: [],
            chart: { type: 'donut', height: 340, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            labels: [],
            colors: PALETTE,
            stroke: { show: true, colors: [SURFACE_STROKE_COLOR], width: 2 },
            plotOptions: { pie: { customScale: 0.78, offsetY: -8, donut: { size: '58%' } } },
            dataLabels: { enabled: false },
            theme: { mode: 'dark' },
            legend: { position: 'bottom', height: 76, offsetY: 40, labels: { colors: TEXT_COLOR }, fontSize: '13px', fontWeight: 600, markers: { width: 10, height: 10, radius: 6 }, itemMargin: { horizontal: 8, vertical: 3 } },
            tooltip: { theme: 'dark' },
        };
        charts.movies = new ApexCharts(els.chartTopMovies, opts);
        charts.movies.render();
    }

    function initTheaterOccupancyChart() {
        if (!els.chartTheaterOccupancy) return;
        const opts = {
            series: [{ name: 'Tỉ lệ lấp đầy (%)', data: [] }],
            chart: { type: 'bar', height: 350, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: [PRIMARY_COLOR],
            plotOptions: {
                bar: { horizontal: false, columnWidth: '45%', borderRadius: 4 }
            },
            dataLabels: { 
                enabled: true, 
                formatter: val => val + '%',
                style: { fontSize: '11px', fontWeight: 700, colors: ['#ffffff'] },
                dropShadow: { enabled: false }
            },
            xaxis: {
                categories: [],
                labels: { style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                max: 100,
                title: { text: 'Tỉ lệ (%)', style: { color: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
                labels: { style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
            },
            theme: { mode: 'dark' },
            grid: { borderColor: GRID_COLOR, strokeDashArray: 4 },
            tooltip: { theme: 'dark' },
        };
        charts.occupancy = new ApexCharts(els.chartTheaterOccupancy, opts);
        charts.occupancy.render();
    }

    /* ── Render functions ───────────────────────────────────────────── */
    function renderCards(summary) {
        if (!summary) return;
        const trends = summary.trends || {};
        if (els.cardTotalTickets)  els.cardTotalTickets.textContent  = (summary.total_tickets || 0).toLocaleString('vi-VN');
        if (els.cardAvgPerDay)     els.cardAvgPerDay.textContent     = summary.avg_per_day || 0;
        if (els.cardPeakHour)      els.cardPeakHour.textContent      = summary.peak_hour || 'N/A';
        if (els.cardOccupancyRate) {
            const occupancyRate = Number(summary.occupancy_rate || 0);
            els.cardOccupancyRate.textContent = `${occupancyRate.toLocaleString('vi-VN', { maximumFractionDigits: 1 })}%`;
        }
        renderTrend(els.cardTotalTicketsTrend, trends.total_tickets);
        renderTrend(els.cardAvgPerDayTrend, trends.avg_per_day);
        renderTrend(els.cardOccupancyRateTrend, trends.occupancy_rate);
    }

    function renderTrendChart(trend) {
        if (!charts.trend || !trend) return;
        const categories = trend.map(r => r.date);
        const data       = trend.map(r => r.ticket_count);
        charts.trend.updateOptions({ xaxis: { categories } });
        charts.trend.updateSeries([{ name: 'Vé bán ra', data }]);
    }

    function renderTopMoviesChart(movies) {
        if (!charts.movies || !movies) return;
        const labels = movies.map(m => m.title.length > 25 ? m.title.substring(0,25)+'...' : m.title);
        const series = movies.map(m => m.ticket_count);
        charts.movies.updateOptions({ labels });
        charts.movies.updateSeries(series);
    }

    function renderTheaterOccupancyChart(theaters) {
        if (!charts.occupancy || !theaters) return;
        const categories = theaters.map(t => t.name);
        const data       = theaters.map(t => t.occupancy_rate);
        charts.occupancy.updateOptions({ xaxis: { categories } });
        charts.occupancy.updateSeries([{ name: 'Tỉ lệ lấp đầy (%)', data }]);
    }

    /* ── API call ───────────────────────────────────────────────────── */
    async function loadStats() {
        const start = els.filterStart?.value;
        const end   = els.filterEnd?.value;
        if (!start || !end) return;

        showLoading();
        try {
            const url = `${API}?start_date=${start}&end_date=${end}`;
            const response = await window.AdminCore.apiFetch(`/api/v1${url}`, {
                requestKey: 'tickets:stats',
                cacheTtl: 30000,
            });
            if (!response?.ok) throw new Error('Không thể tải thống kê vé.');
            const res = await response.json();

            if (res?.success) {
                const d = res.data;
                renderCards(d.summary);
                renderTrendChart(d.trend);
                renderTopMoviesChart(d.top_movies);
                renderTheaterOccupancyChart(d.theater_occupancy);
            }
        } catch (e) {
            if (e?.name === 'AbortError') return;
            console.error('[Tickets] Error:', e);
        } finally {
            hideLoading();
        }
    }

    /* ── Wait for authManager ────────────────────────────────────────── */
    /* ── Events ─────────────────────────────────────────────────────── */
    function bindEvents() {
        // Shortcut buttons
        els.shortcuts.forEach(btn => {
            btn.addEventListener('click', () => {
                els.shortcuts.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                setDateRange(btn.dataset.range);
                loadStats();
            });
        });

        // Apply button
        els.btnApply?.addEventListener('click', loadStats);

        // Also reload when Enter pressed in date inputs
        [els.filterStart, els.filterEnd].forEach(inp => {
            inp?.addEventListener('change', loadStats);
        });
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    function init() {
        setDateRange('week'); // default: this week
        initTrendChart();
        initTopMoviesChart();
        initTheaterOccupancyChart();
        bindEvents();
        loadStats();
    }

    window.onAdminPageLoad(() => {
        cacheDoms();
        init();
        window.onAdminPageCleanup?.(() => {
            Object.values(charts).forEach((chart) => chart?.destroy?.());
            Object.keys(charts).forEach((key) => { charts[key] = null; });
        });
    });

})();
