/**
 * Combo Statistics Page JS
 * ApexCharts + Fetch API + Date Range Filter
 */
(function () {
    'use strict';

    const API_COMBO = '/admin/combos/stats';
    const API_FOOD = '/admin/food/stats';
    const PALETTE = ['#e50914','#22c55e','#38bdf8','#fbbf24','#a78bfa','#fb7185','#2dd4bf','#f97316','#84cc16','#60a5fa'];
    const PRIMARY_COLOR = '#e50914';

    let currentType = 'combo'; // Track current tab

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
            statsTabs: document.querySelectorAll('[data-stats-type]'),

            // Cards
            cardTotalCombos: document.getElementById('cardTotalCombos'),
            cardTotalCombosTrend: document.getElementById('cardTotalCombosTrend'),
            cardRevenue: document.getElementById('cardRevenue'),
            cardRevenueTrend: document.getElementById('cardRevenueTrend'),
            cardAvgPerDay: document.getElementById('cardAvgPerDay'),
            cardAvgPerDayTrend: document.getElementById('cardAvgPerDayTrend'),
            cardTopCombo: document.getElementById('cardTopCombo'),

            // Charts
            chartComboTrend: document.getElementById('chartComboTrend'),
            chartTopCombos: document.getElementById('chartTopCombos'),
            chartComboRevenue: document.getElementById('chartComboRevenue'),
            trendTitle: document.querySelector('#chartComboTrend')?.closest('.chart-card')?.querySelector('.chart-title'),
            topTitle: document.querySelector('#chartTopCombos')?.closest('.chart-card')?.querySelector('.chart-title'),
            revenueTitle: document.querySelector('#chartComboRevenue')?.closest('.chart-card')?.querySelector('.chart-title'),
            totalTitle: document.getElementById('cardTotalCombos')?.closest('.stat-card')?.querySelector('.stat-title'),
            revenueCardTitle: document.getElementById('cardRevenue')?.closest('.stat-card')?.querySelector('.stat-title'),
            topCaption: document.getElementById('cardTopCombo')?.closest('.stat-card')?.querySelector('.trend-text, .text-secondary'),
        };

        currentType = document.querySelector('[data-stats-type].active')?.dataset.statsType || 'combo';
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

    function getTypeLabels() {
        const isCombo = currentType === 'combo';

        return {
            unit: isCombo ? 'combo' : 'sản phẩm',
            totalTitle: isCombo ? 'TỔNG COMBO BÁN RA' : 'TỔNG ĐỒ ĂN & NƯỚC UỐNG BÁN RA',
            revenueTitle: isCombo ? 'DOANH THU TỪ COMBO' : 'DOANH THU TỪ ĐỒ ĂN & NƯỚC UỐNG',
            topCaption: isCombo ? 'combo bán chạy nhất' : 'sản phẩm bán chạy nhất',
            trendTitle: isCombo ? 'Xu hướng lượng combo bán ra' : 'Xu hướng lượng đồ ăn & nước uống bán ra',
            topTitle: isCombo ? 'Top combo bán chạy' : 'Top đồ ăn & nước uống bán chạy',
            revenueChartTitle: isCombo ? 'Doanh thu theo combo' : 'Doanh thu theo đồ ăn & nước uống',
            quantityAxis: isCombo ? 'Số lượng combo' : 'Số lượng sản phẩm',
            seriesName: isCombo ? 'Combo bán ra' : 'Sản phẩm bán ra',
        };
    }

    function applyTypeLabels() {
        const labels = getTypeLabels();

        if (els.totalTitle) els.totalTitle.textContent = labels.totalTitle;
        if (els.revenueCardTitle) els.revenueCardTitle.textContent = labels.revenueTitle;
        if (els.topCaption) els.topCaption.textContent = labels.topCaption;
        if (els.trendTitle) els.trendTitle.textContent = labels.trendTitle;
        if (els.topTitle) els.topTitle.textContent = labels.topTitle;
        if (els.revenueTitle) els.revenueTitle.textContent = labels.revenueChartTitle;

        charts.trend?.updateOptions({
            yaxis: {
                title: { text: labels.quantityAxis, style: { color: '#a1a1aa', fontSize: '11px' } },
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
                min: 0
            }
        });
    }

    function normalizeTopItem(item) {
        return {
            name: item?.name || 'N/A',
            total_qty: Number(item?.total_qty ?? item?.qty ?? 0),
            total_revenue: Number(item?.total_revenue ?? item?.revenue ?? 0),
        };
    }

    function showLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = true;
            els.btnApply.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cập nhật...';
        }
        document.querySelectorAll('#adminPageContent .stat-value[id], #chartComboTrend, #chartTopCombos, #chartComboRevenue').forEach(el => {
            el.classList.add('admin-skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');
        });
    }

    function hideLoading() {
        if (els.btnApply) {
            els.btnApply.disabled = false;
            els.btnApply.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        document.querySelectorAll('#adminPageContent .stat-value[id]').forEach(el => {
            el.classList.remove('admin-skeleton', 'admin-skeleton-text');
        });
        document.querySelectorAll('#chartComboTrend, #chartTopCombos, #chartComboRevenue').forEach(el => {
            el.classList.remove('admin-skeleton');
        });
    }

    /* ── Chart Initializers ─────────────────────────────────────────── */
    function initTrendChart() {
        if (!els.chartComboTrend) return;
        const opts = {
            series: [{ name: 'Combo bán ra', data: [] }],
            chart: { height: 300, width: '100%', type: 'area', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: [PRIMARY_COLOR],
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] },
            },
            markers: { size: 4, strokeWidth: 2, hover: { size: 7, sizeOffset: 2 } },
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
            tooltip: { enabled: true, shared: false, intersect: false, followCursor: false, theme: 'dark', fixed: { enabled: true, position: 'topRight', offsetX: -16, offsetY: 12 } },
        };
        charts.trend = new ApexCharts(els.chartComboTrend, opts);
        charts.trend.render();
    }

    function initTopCombosChart() {
        if (!els.chartTopCombos) return;
        const opts = {
            series: [],
            labels: [],
            chart: { type: 'donut', height: 340, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: PALETTE,
            stroke: { show: true, width: 2, colors: ['#1e1e24'] },
            plotOptions: { pie: { customScale: 0.78, offsetY: -8, donut: { size: '58%', labels: {
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
            legend: { position: 'bottom', height: 76, offsetY: 40, labels: { colors: '#e4e4e7' }, fontSize: '13px', fontWeight: 600, markers: { width: 10, height: 10, radius: 6 }, itemMargin: { horizontal: 8, vertical: 3 } },
            tooltip: { theme: 'dark', style: { fontSize: '13px' } },
        };
        charts.topCombos = new ApexCharts(els.chartTopCombos, opts);
        charts.topCombos.render();
    }

    function initRevenueChart() {
        if (!els.chartComboRevenue) return;
        const opts = {
            series: [{ name: 'Doanh thu', data: [] }],
            chart: { type: 'bar', height: 350, width: '100%', background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, parentHeightOffset: 0, redrawOnParentResize: true, redrawOnWindowResize: true },
            colors: [PRIMARY_COLOR],
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
        const trends = summary.trends || {};

        if (els.cardTotalCombos) els.cardTotalCombos.textContent = qty.toLocaleString('vi-VN');
        if (els.cardRevenue)     els.cardRevenue.textContent     = formatCurrencyFull(rev);
        if (els.cardAvgPerDay)    els.cardAvgPerDay.textContent    = avg.toLocaleString('vi-VN', { maximumFractionDigits: 1 });
        if (els.cardTopCombo)     els.cardTopCombo.textContent     = summary.best_combo_name || 'N/A';
        renderTrend(els.cardTotalCombosTrend, trends.total_quantity);
        renderTrend(els.cardRevenueTrend, trends.total_revenue);
        renderTrend(els.cardAvgPerDayTrend, trends.avg_per_day);
    }

    function renderTrendChart(trend) {
        if (!charts.trend) return;
        const labels = getTypeLabels();
        const rows = Array.isArray(trend) ? trend : [];
        const categories = rows.map(r => r.date);
        const data       = rows.map(r => Number(r.count || 0));
        let opts = { xaxis: { categories } };
        if (data.length === 1) {
            opts.markers = { size: 6, strokeWidth: 2, hover: { size: 8 } };
        } else {
            opts.markers = { size: 0 };
        }
        charts.trend.updateOptions(opts);
        charts.trend.updateSeries([{ name: labels.seriesName, data }]);
    }

    function renderTopCombosChart(topCombos) {
        if (!charts.topCombos) return;
        const items = Array.isArray(topCombos) ? topCombos.map(normalizeTopItem) : [];
        const labels = items.map(d => d.name);
        const series = items.map(d => d.total_qty);
        charts.topCombos.updateOptions({ labels });
        charts.topCombos.updateSeries(series);
    }

    function renderRevenueChart(topCombos) {
        if (!charts.revenue) return;
        const items = Array.isArray(topCombos) ? topCombos.map(normalizeTopItem) : [];
        const categories = items.map(d => d.name);
        const data       = items.map(d => d.total_revenue);
        charts.revenue.updateOptions({ xaxis: { categories } });
        charts.revenue.updateSeries([{ name: 'Doanh thu', data }]);
    }

    /* ── Wait for authManager (No longer needed) ────────────────────── */

    /* ── API call ───────────────────────────────────────────────────── */
    async function loadStats(options = {}) {
        const { showSkeleton = true, skipCache = false } = options;
        const start = els.filterStart?.value;
        const end   = els.filterEnd?.value;
        if (!start || !end) return;

        const startD = new Date(start);
        const endD   = new Date(end);
        const diffTime = Math.abs(endD - startD);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1;

        applyTypeLabels();
        if (showSkeleton) showLoading();
        try {
            const api = currentType === 'combo' ? API_COMBO : API_FOOD;
            const url = `${api}?start_date=${start}&end_date=${end}`;
            const response = await window.AdminCore.apiFetch(`/api/v1${url}`, {
                requestKey: 'combos:stats',
                cacheTtl: 30000,
                skipCache,
            });
            if (!response?.ok) throw new Error('Không thể tải thống kê sản phẩm.');
            const res = await response.json();

            if (res?.success) {
                const d = res.data;
                renderCards(d.summary, diffDays);
                renderTrendChart(d.trend);
                renderTopCombosChart(d.top_combos);
                renderRevenueChart(d.top_combos);
            }
        } catch (e) {
            if (e?.name === 'AbortError') return;
            console.error('[Combos] Error:', e);
        } finally {
            if (showSkeleton) hideLoading();
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

        els.statsTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const type = tab.dataset.statsType;
                if (!type || type === currentType) return;

                els.statsTabs.forEach(item => {
                    const isActive = item === tab;
                    item.classList.toggle('active', isActive);
                    item.setAttribute('aria-selected', String(isActive));
                });
                currentType = type;
                applyTypeLabels();
                loadStats();
            });
        });
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    function init() {
        setDateRange('week'); // default: this week
        initTrendChart();
        initTopCombosChart();
        initRevenueChart();
        applyTypeLabels();
        bindEvents();
        loadStats();

        state.pollInterval = setInterval(() => {
            if (!document.hidden) {
                loadStats({ showSkeleton: false, skipCache: true });
            }
        }, 30000);
    }

    window.onAdminPageCleanup(() => {
        if (state.pollInterval) clearInterval(state.pollInterval);
        state.pollInterval = null;
        Object.values(charts).forEach((chart) => chart?.destroy?.());
        charts = { trend: null, topCombos: null, revenue: null };
        els = {};
    });

    window.onAdminPageLoad(() => {
        cacheDoms();
        init();
    });

})();
