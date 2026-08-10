/**
 * Admin – Revenue Statistics Page
 * ApexCharts + Fetch API + Date Range Filter
 */
(function () {
    'use strict';

    const API = '/admin/revenue/stats';

    // Palette for charts
    const PALETTE = ['#e50914','#22c55e','#38bdf8','#fbbf24','#a78bfa','#fb7185','#2dd4bf','#f97316','#84cc16','#60a5fa'];
    const PRIMARY_COLOR = '#e50914';
    const SECONDARY_SERIES_COLOR = '#94a3b8';
    const TEXT_COLOR = '#e4e4e7';
    const MUTED_TEXT_COLOR = '#c7c7d1';
    const GRID_COLOR = 'rgba(255,255,255,0.08)';

    /* ── State ─────────────────────────────────────────────────────── */
    let charts = { theater: null, movie: null, payment: null, trend: null };
    let els    = {};
    let pollInterval = null;

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

    function formatPaymentMethod(method) {
        const labels = {
            cash: 'Tiền mặt',
            payos: 'PayOS',
            payos_qr: 'QR PayOS',
            zero_amount: 'Đơn 0đ',
        };

        return labels[method] || method || 'Khác';
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
        document.querySelectorAll('#adminPageContent .stat-value[id], #adminPageContent .stat-trend span[id], #chartTheaterPie, #chartMovieBar, #chartPaymentDonut, #chartRevenueTrend').forEach(el => {
            el.classList.add('admin-skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');
        });
    }
    
    function hideLoading()  { 
        if (els.btnApply) {
            els.btnApply.disabled = false;
            els.btnApply.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        document.querySelectorAll('#adminPageContent .admin-skeleton').forEach(el => {
            el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart', 'admin-skeleton', 'admin-skeleton-text');
        });
    }

    /* ── Chart Initializers ─────────────────────────────────────────── */
    function initTheaterPie() {
        const opts = {
            series: [],
            labels: [],
            chart : { type: 'donut', height: 340, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: PALETTE,
            stroke: { width: 2, colors: ['#1e1e24'] },
            legend: { position: 'bottom', height: 76, offsetY: 40, labels: { colors: TEXT_COLOR }, fontSize: '13px', fontWeight: 600, markers: { width: 10, height: 10, radius: 6 }, itemMargin: { horizontal: 8, vertical: 3 } },
            plotOptions: { pie: { customScale: 0.78, offsetY: -8, donut: { size: '58%' } } },
            dataLabels: { enabled: false },
            tooltip : { theme: 'dark', y: { formatter: formatCurrencyFull } },
        };
        charts.theater = new ApexCharts(els.chartTheaterPie, opts);
        charts.theater.render();
    }

    function initMovieBar() {
        const opts = {
            series: [{ name: 'Doanh thu', data: [] }],
            chart : {
                type: 'bar', height: 300, width: '100%', background: 'transparent',
                fontFamily: 'Inter, sans-serif', toolbar: { show: false },
                parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true,
            },
            colors: [PRIMARY_COLOR],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: {
                    style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 },
                    formatter: v => formatCurrency(v),
                },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 }, maxWidth: 150 } },
            grid : { borderColor: GRID_COLOR, strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: 'dark', x: { show: true }, y: { formatter: formatCurrencyFull } },
        };
        charts.movie = new ApexCharts(els.chartMovieBar, opts);
        charts.movie.render();
    }

    function initPaymentDonut() {
        const opts = {
            series: [],
            labels: [],
            chart : { type: 'donut', height: 280, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: PALETTE,
            stroke : { width: 2, colors: ['#1e1e24'] },
            plotOptions: { pie: { donut: { size: '65%', labels: {
                show: true,
                total: {
                    show: true,
                    label: 'Tổng lượt',
                    color: MUTED_TEXT_COLOR,
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
                { name: 'Doanh thu', data: [], type: 'line' },
                { name: 'Đơn hàng',  data: [], type: 'line' },
            ],
            chart  : { height: 300, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors : [PRIMARY_COLOR, SECONDARY_SERIES_COLOR],
            stroke : { colors: [PRIMARY_COLOR, SECONDARY_SERIES_COLOR], curve: 'smooth', width: [3, 2], dashArray: [0, 4] },
            markers: { size: [4, 4], colors: [PRIMARY_COLOR, SECONDARY_SERIES_COLOR], strokeColors: '#1e1e24', strokeWidth: 2, hover: { size: 7 } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 }, rotate: -30 },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: [
                {
                    title : { text: 'Doanh thu', style: { color: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 } },
                    min   : 0,
                    max   : max => (max < 500000 ? 500000 : max),
                    labels: {
                        style: { colors: MUTED_TEXT_COLOR, fontSize: '12px', fontWeight: 600 },
                        formatter: v => formatCurrency(v),
                    },
                },
                {
                    opposite: true,
                    title : { text: 'Đơn hàng', style: { color: SECONDARY_SERIES_COLOR, fontSize: '11px' } },
                    min   : 0,
                    labels: { style: { colors: SECONDARY_SERIES_COLOR, fontSize: '12px', fontWeight: 600 } },
                },
            ],
            grid   : { borderColor: GRID_COLOR, strokeDashArray: 4 },
            legend : { position: 'top', horizontalAlign: 'center', labels: { colors: TEXT_COLOR }, fontSize: '13px', fontWeight: 700, markers: { width: 10, height: 10, radius: 6 }, itemMargin: { horizontal: 14, vertical: 4 } },
            tooltip: { theme: 'dark', shared: false, intersect: false, followCursor: false,
                fixed: { enabled: true, position: 'topRight', offsetX: -16, offsetY: 12 },
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
        if (els.cardTopTheaterPct)     els.cardTopTheaterPct.textContent     = (t.percentage || 0) + '% tổng DT';

        if (els.cardTopMovieRevenue) els.cardTopMovieRevenue.textContent = formatCurrency(m.revenue);
        if (els.cardTopMovieTitle)   els.cardTopMovieTitle.textContent   = m.title || 'N/A';
        if (els.cardTopMovieTickets) els.cardTopMovieTickets.textContent = (m.tickets || 0).toLocaleString('vi-VN') + ' vé';

        if (els.cardTopPayMethod)    els.cardTopPayMethod.textContent    = formatPaymentMethod(p.top_method) || 'N/A';
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
        const labels  = breakdown.map(b => formatPaymentMethod(b.method));
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
              <span class="pay-legend-dot pay-legend-dot--${i % PALETTE.length}"></span>
                        <span class="text-secondary">${sanitize(formatPaymentMethod(b.method))}</span>
                    </span>
                    <span class="fw-bold">${b.count.toLocaleString('vi-VN')} lượt <span class="text-secondary">(${b.count_percent || 0}%)</span></span>
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
            { name: 'Doanh thu', data: revenues, type: 'line' },
            { name: 'Đơn hàng',  data: orders, type: 'line' },
        ]);
    }

    /* ── Wait for authManager ────────────────────────────────────────── */
    /* ── API call ───────────────────────────────────────────────────── */
    async function loadStats(options = {}) {
        const { showSkeleton = true, skipCache = false } = options;
        const start = els.filterStart?.value;
        const end   = els.filterEnd?.value;
        if (!start || !end) return;

        if (showSkeleton) showLoading();
        try {
            const url = `${API}?start_date=${start}&end_date=${end}`;
            const response = await window.AdminCore.apiFetch(`/api/v1${url}`, {
                requestKey: 'revenue:stats',
                cacheTtl: 30000,
                skipCache,
            });
            if (!response?.ok) throw new Error('Không thể tải thống kê doanh thu.');
            const res = await response.json();

            if (res?.success) {
                const d = res.data;
                renderCards(d);
                renderTheaterPie(d.by_theater);
                renderMovieBar(d.by_movie);
                renderPaymentDonut(d.payment_methods);
                renderTrend(d.by_trend);
            }
        } catch (e) {
            if (e?.name === 'AbortError') return;
            console.error('[Revenue] Error:', e);
        } finally {
            if (showSkeleton) hideLoading();
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
        loadStats();
        pollInterval = setInterval(() => {
            if (!document.hidden) {
                loadStats({ showSkeleton: false, skipCache: true });
            }
        }, 15000);
    }

    window.onAdminPageLoad(() => {
        cacheDoms();
        init();
        window.onAdminPageCleanup?.(() => {
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = null;
            Object.values(charts).forEach((chart) => chart?.destroy?.());
            charts = { theater: null, movie: null, payment: null, trend: null };
            els = {};
        });
    });
})();
