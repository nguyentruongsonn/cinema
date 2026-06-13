/**
 * Ticket Statistics Dashboard Script
 */
(function () {
    'use strict';

    const API = '/admin/tickets/stats';

    // Palette for charts
    const PALETTE = ['#e50914','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#a3e635','#06b6d4'];

    /* ── DOM Elements ───────────────────────────────────────────────── */
    const els = {
        filterStart: null,
        filterEnd: null,
        shortcuts: [],
        btnApply: null,

        // Cards
        cardTotalTickets: null,
        cardAvgPerDay: null,
        cardPeakHour: null,
        cardOccupancyRate: null,

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
        els.cardAvgPerDay     = document.getElementById('cardAvgPerDay');
        els.cardPeakHour      = document.getElementById('cardPeakHour');
        els.cardOccupancyRate = document.getElementById('cardOccupancyRate');

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
                start.setDate(now.getDate() - now.getDay() + 1); // Monday
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
        document.querySelectorAll('.stat-value, .stat-trend span, #chartTicketTrend, #chartTopMovies, #chartTheaterOccupancy').forEach(el => {
            el.classList.add('skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('skeleton-text');
        });
    }

    function hideLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = false;
            els.btnApply.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        document.querySelectorAll('.skeleton').forEach(el => {
            el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart');
        });
    }

    /* ── Chart Initializers ─────────────────────────────────────────── */
    function initTrendChart() {
        if (!els.chartTicketTrend) return;
        const opts = {
            series: [{ name: 'Vé bán ra', data: [] }],
            chart: { height: 300, type: 'area', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
            colors: ['#e50914'],
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] },
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                title: { text: 'Số lượng vé', style: { color: '#a1a1aa', fontSize: '11px' } },
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
            },
            theme: { mode: 'dark' },
            grid: { borderColor: '#27272a', strokeDashArray: 4 },
        };
        charts.trend = new ApexCharts(els.chartTicketTrend, opts);
        charts.trend.render();
    }

    function initTopMoviesChart() {
        if (!els.chartTopMovies) return;
        const opts = {
            series: [],
            chart: { type: 'pie', height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif' },
            labels: [],
            colors: PALETTE,
            stroke: { show: true, colors: '#18181b', width: 2 },
            dataLabels: { enabled: true, dropShadow: { enabled: false } },
            theme: { mode: 'dark' },
            legend: { position: 'bottom', labels: { colors: '#a1a1aa' } },
        };
        charts.movies = new ApexCharts(els.chartTopMovies, opts);
        charts.movies.render();
    }

    function initTheaterOccupancyChart() {
        if (!els.chartTheaterOccupancy) return;
        const opts = {
            series: [{ name: 'Tỉ lệ lấp đầy (%)', data: [] }],
            chart: { type: 'bar', height: 350, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: ['#3b82f6'],
            plotOptions: {
                bar: { horizontal: false, columnWidth: '45%', borderRadius: 4 }
            },
            dataLabels: { 
                enabled: true, 
                formatter: val => val + '%',
                style: { fontSize: '10px' }
            },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                max: 100,
                title: { text: 'Tỉ lệ (%)', style: { color: '#a1a1aa', fontSize: '11px' } },
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
            },
            theme: { mode: 'dark' },
            grid: { borderColor: '#27272a', strokeDashArray: 4 },
        };
        charts.occupancy = new ApexCharts(els.chartTheaterOccupancy, opts);
        charts.occupancy.render();
    }

    /* ── Render functions ───────────────────────────────────────────── */
    function renderCards(summary) {
        if (!summary) return;
        if (els.cardTotalTickets)  els.cardTotalTickets.textContent  = (summary.total_tickets || 0).toLocaleString('vi-VN');
        if (els.cardAvgPerDay)     els.cardAvgPerDay.textContent     = summary.avg_per_day || 0;
        if (els.cardPeakHour)      els.cardPeakHour.textContent      = summary.peak_hour || 'N/A';
        if (els.cardOccupancyRate) els.cardOccupancyRate.textContent = summary.occupancy_rate || 0;
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
        if (typeof authManager === 'undefined') return;
        const start = els.filterStart?.value;
        const end   = els.filterEnd?.value;
        if (!start || !end) return;

        showLoading();
        try {
            const url = `${API}?start_date=${start}&end_date=${end}`;
            const res = await authManager.fetchAPI(url, { silentAuth: true });

            if (res?.success) {
                const d = res.data;
                renderCards(d.summary);
                renderTrendChart(d.trend);
                renderTopMoviesChart(d.top_movies);
                renderTheaterOccupancyChart(d.theater_occupancy);
            }
        } catch (e) {
            console.error('[Tickets] Error:', e);
        } finally {
            hideLoading();
        }
    }

    /* ── Wait for authManager ────────────────────────────────────────── */
    function waitForAuth(callback, retries = 50) {
        if (typeof authManager !== 'undefined' && authManager.authChecked) {
            callback();
            return;
        }
        if (retries <= 0) {
            console.error('[Tickets] authManager check timed out. loadStats aborted.');
            return;
        }
        setTimeout(() => waitForAuth(callback, retries - 1), 150);
    }

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
        // Wait for authManager to finish auth check before first API call
        waitForAuth(loadStats);
    }

    document.addEventListener('DOMContentLoaded', () => {
        cacheDoms();
        init();
    });

})();
