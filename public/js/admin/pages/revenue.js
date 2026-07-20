/**
 * Admin – Revenue Statistics Page
 * ApexCharts + Fetch API + Date Range Filter
 */
(function () {
    'use strict';

    const API = '/admin/revenue/stats';

    // Palette for charts
    const PALETTE = ['#e50914','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#a3e635','#06b6d4'];

    /* ── State ─────────────────────────────────────────────────────── */
    let charts = { theater: null, movie: null, payment: null, trend: null };
    let els    = {};

    /* ── DOM Cache ──────────────────────────────────────────────────── */
    function cacheDoms() {
        els = {
            filterStart         : document.getElementById('filterStart'),
            filterEnd           : document.getElementById('filterEnd'),
            btnApply            : document.getElementById('btnApplyFilter'),
            shortcuts           : document.querySelectorAll('.btn-shortcut'),
            loadingOverlay      : document.getElementById('revenueLoadingOverlay'),

            // Cards
            cardTotalRevenue    : document.getElementById('cardTotalRevenue'),
            cardTotalOrders     : document.getElementById('cardTotalOrders'),
            cardTopTheaterRevenue: document.getElementById('cardTopTheaterRevenue'),
            cardTopTheaterName  : document.getElementById('cardTopTheaterName'),
            cardTopTheaterPct   : document.getElementById('cardTopTheaterPct'),
            cardTopMovieRevenue : document.getElementById('cardTopMovieRevenue'),
            cardTopMovieTitle   : document.getElementById('cardTopMovieTitle'),
            cardTopMovieTickets : document.getElementById('cardTopMovieTickets'),
            cardTopPayMethod    : document.getElementById('cardTopPayMethod'),
            cardTopPayMethodPct : document.getElementById('cardTopPayMethodPct'),
            badgeTheaterCount   : document.getElementById('badgeTheaterCount'),

            // Chart containers
            chartTheaterPie    : document.getElementById('chartTheaterPie'),
            chartMovieBar      : document.getElementById('chartMovieBar'),
            chartPaymentDonut  : document.getElementById('chartPaymentDonut'),
            chartRevenueTrend  : document.getElementById('chartRevenueTrend'),
            paymentLegend      : document.getElementById('paymentLegend'),
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

    function showLoading()  { 
        if (els.btnApply) {
            els.btnApply.disabled = true;
            els.btnApply.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cập nhật...';
        }
        document.querySelectorAll('.stat-value, .stat-trend span, #chartTheaterPie, #chartMovieBar, #chartPaymentDonut, #chartRevenueTrend').forEach(el => {
            el.classList.add('admin-skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');
        });
    }
    
    function hideLoading()  { 
        if (els.btnApply) {
            els.btnApply.disabled = false;
            els.btnApply.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        document.querySelectorAll('.skeleton, .admin-skeleton').forEach(el => {
            el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart', 'admin-skeleton', 'admin-skeleton-text');
        });
    }

    /* ── Chart Initializers ─────────────────────────────────────────── */
    function initTheaterPie() {
        const opts = {
            series: [],
            labels: [],
            chart : { type: 'pie', height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: PALETTE,
            stroke: { width: 2, colors: ['#1e1e24'] },
            legend: { position: 'bottom', labels: { colors: '#a1a1aa' }, fontSize: '12px' },
            dataLabels: { style: { fontSize: '11px' }, dropShadow: { enabled: false } },
            tooltip : { theme: 'dark', y: { formatter: formatCurrencyFull } },
        };
        charts.theater = new ApexCharts(els.chartTheaterPie, opts);
        charts.theater.render();
    }

    function initMovieBar() {
        const opts = {
            series: [{ name: 'Doanh thu', data: [] }],
            chart : {
                type: 'bar', height: 300, background: 'transparent',
                fontFamily: 'Inter, sans-serif', toolbar: { show: false },
            },
            colors: ['#e50914'],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: {
                    style: { colors: '#a1a1aa', fontSize: '11px' },
                    formatter: v => formatCurrency(v),
                },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: '#a1a1aa', fontSize: '11px' }, maxWidth: 150 } },
            grid : { borderColor: '#2e2e33', strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: 'dark', x: { show: true }, y: { formatter: formatCurrencyFull } },
        };
        charts.movie = new ApexCharts(els.chartMovieBar, opts);
        charts.movie.render();
    }

    function initPaymentDonut() {
        const opts = {
            series: [],
            labels: [],
            chart : { type: 'donut', height: 280, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: PALETTE,
            stroke : { width: 2, colors: ['#1e1e24'] },
            plotOptions: { pie: { donut: { size: '65%', labels: {
                show: true,
                total: {
                    show: true,
                    label: 'Tổng lượt',
                    color: '#a1a1aa',
                    fontSize: '13px',
                    formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('vi-VN'),
                },
                value: { color: '#ffffff', fontSize: '20px', fontWeight: 700, formatter: v => Number(v).toLocaleString('vi-VN') },
            } } } },
            dataLabels: { enabled: false },
            legend  : { show: false },
            tooltip : { theme: 'dark' },
        };
        charts.payment = new ApexCharts(els.chartPaymentDonut, opts);
        charts.payment.render();
    }

    function initTrendArea() {
        const opts = {
            series: [
                { name: 'Doanh thu', data: [], type: 'area' },
                { name: 'Đơn hàng',  data: [], type: 'line' },
            ],
            chart  : { height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
            colors : ['#e50914', '#3b82f6'],
            stroke : { curve: 'smooth', width: [3, 2], dashArray: [0, 4] },
            fill   : {
                type: ['gradient', 'none'],
                gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] },
            },
            markers: { size: [0, 4], colors: ['#e50914','#3b82f6'], strokeColors: '#1e1e24', strokeWidth: 2 },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' }, rotate: -30 },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: [
                {
                    title : { text: 'Doanh thu', style: { color: '#a1a1aa', fontSize: '11px' } },
                    min   : 0,
                    max   : max => (max < 500000 ? 500000 : max),
                    labels: {
                        style: { colors: '#a1a1aa', fontSize: '11px' },
                        formatter: v => formatCurrency(v),
                    },
                },
                {
                    opposite: true,
                    title : { text: 'Đơn hàng', style: { color: '#3b82f6', fontSize: '11px' } },
                    min   : 0,
                    labels: { style: { colors: '#3b82f6', fontSize: '11px' } },
                },
            ],
            grid   : { borderColor: '#2e2e33', strokeDashArray: 4 },
            legend : { position: 'top', labels: { colors: '#a1a1aa' } },
            tooltip: { theme: 'dark', shared: true, intersect: false,
                y: [
                    { formatter: formatCurrencyFull },
                    { formatter: v => v + ' đơn' },
                ],
            },
        };
        charts.trend = new ApexCharts(els.chartRevenueTrend, opts);
        charts.trend.render();
    }

    /* ── Render functions ───────────────────────────────────────────── */
    function renderCards(data) {
        const s = data.summary || {};
        const t = data.top_theater || {};
        const m = data.top_movie || {};
        const p = data.payment_methods || {};

        if (els.cardTotalRevenue)  els.cardTotalRevenue.textContent  = formatCurrencyFull(s.total_revenue);
        if (els.cardTotalOrders)   els.cardTotalOrders.textContent   = (s.total_orders || 0).toLocaleString('vi-VN') + ' đơn hàng';

        if (els.cardTopTheaterRevenue) els.cardTopTheaterRevenue.textContent = formatCurrency(t.revenue);
        if (els.cardTopTheaterName)    els.cardTopTheaterName.textContent    = t.name || 'N/A';
        if (els.cardTopTheaterPct)     els.cardTopTheaterPct.textContent     = 'chiếm ' + (t.percentage || 0) + '% tổng DT';

        if (els.cardTopMovieRevenue) els.cardTopMovieRevenue.textContent = formatCurrency(m.revenue);
        if (els.cardTopMovieTitle)   els.cardTopMovieTitle.textContent   = m.title || 'N/A';
        if (els.cardTopMovieTickets) els.cardTopMovieTickets.textContent = (m.tickets || 0).toLocaleString('vi-VN') + ' vé bán ra';

        if (els.cardTopPayMethod)    els.cardTopPayMethod.textContent    = p.top_method || 'N/A';
        if (els.cardTopPayMethodPct) els.cardTopPayMethodPct.textContent = (p.top_method_pct || 0) + '%';
    }

    function renderTheaterPie(byTheater) {
        if (!charts.theater || !byTheater?.length) return;
        const labels  = byTheater.map(r => r.name);
        const series  = byTheater.map(r => parseFloat(r.revenue));
        if (els.badgeTheaterCount) els.badgeTheaterCount.textContent = labels.length + ' rạp';
        charts.theater.updateOptions({ labels });
        charts.theater.updateSeries(series);
    }

    function renderMovieBar(byMovie) {
        if (!charts.movie || !byMovie?.length) return;
        const categories = byMovie.map(r => r.title.length > 25 ? r.title.slice(0, 25) + '…' : r.title);
        const data       = byMovie.map(r => parseFloat(r.revenue));
        charts.movie.updateOptions({ xaxis: { categories } });
        charts.movie.updateSeries([{ name: 'Doanh thu', data }]);
    }

    function renderPaymentDonut(paymentMethods) {
        if (!charts.payment) return;
        const breakdown = paymentMethods?.breakdown || [];
        const labels  = breakdown.map(b => b.method || 'Khác');
        const series  = breakdown.map(b => b.count);

        charts.payment.updateOptions({ labels });
        charts.payment.updateSeries(series);

        // Custom legend
        if (els.paymentLegend) {
            const sanitize = str => {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            };

            els.paymentLegend.innerHTML = breakdown.map((b, i) => `
                <div class="pay-legend-row">
                    <span>
                        <span class="pay-legend-dot" style="background:${PALETTE[i % PALETTE.length]}"></span>
                        <span class="text-secondary">${sanitize(b.method || 'Khác')}</span>
                    </span>
                    <span class="fw-bold">${b.count.toLocaleString('vi-VN')} lượt <span class="text-secondary">(${b.percent}%)</span></span>
                </div>
            `).join('');
        }
    }

    function renderTrend(byTrend) {
        if (!charts.trend || !byTrend?.length) return;
        const categories = byTrend.map(r => r.period);
        const revenues   = byTrend.map(r => parseFloat(r.revenue));
        const orders     = byTrend.map(r => parseInt(r.orders));
        charts.trend.updateOptions({ xaxis: { categories } });
        charts.trend.updateSeries([
            { name: 'Doanh thu', data: revenues },
            { name: 'Đơn hàng',  data: orders   },
        ]);
    }

    /* ── Wait for authManager ────────────────────────────────────────── */
    function waitForAuth(callback, retries = 50) {
        if (typeof authManager !== 'undefined' && authManager.authChecked) {
            callback();
            return;
        }
        if (retries <= 0) {
            console.error('[Revenue] authManager check timed out. loadStats aborted.');
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

        showLoading();
        try {
            const url = `${API}?start_date=${start}&end_date=${end}`;
            console.log('[Revenue] Fetching:', url);
            const res = await authManager.fetchAPI(url, { silentAuth: true });
            console.log('[Revenue] Response:', res);

            if (res?.success) {
                const d = res.data;
                renderCards(d);
                renderTheaterPie(d.by_theater);
                renderMovieBar(d.by_movie);
                renderPaymentDonut(d.payment_methods);
                renderTrend(d.by_trend);
            }
        } catch (e) {
            console.error('[Revenue] Error:', e);
        } finally {
            hideLoading();
        }
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
        initTheaterPie();
        initMovieBar();
        initPaymentDonut();
        initTrendArea();
        bindEvents();
        // Wait for authManager to finish auth check before first API call
        waitForAuth(loadStats);
    }

    window.onAdminPageLoad(() => {
        cacheDoms();
        init();
    });
})();
