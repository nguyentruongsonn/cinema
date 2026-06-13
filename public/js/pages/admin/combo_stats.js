/**
 * Combo Statistics Dashboard – combo_stats.js
 * Pattern: IIFE, no global scope pollution, skeleton loading, ApexCharts
 */
(function () {
    'use strict';

    /* ── Config ─────────────────────────────────────────────────────── */
    const COMBO_API = '/admin/combos/stats';
    const FOOD_API  = '/admin/food/stats';
    const PALETTE   = ['#e50914','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316'];

    /* ── State ──────────────────────────────────────────────────────── */
    const state = {
        combo: { data: null, loaded: false },
        food:  { data: null, loaded: false }
    };

    /* ── DOM cache ──────────────────────────────────────────────────── */
    const els = {};

    function cacheDoms() {
        // Combo Tab DOMs
        els.comboFilterStart     = document.getElementById('comboFilterStart');
        els.comboFilterEnd       = document.getElementById('comboFilterEnd');
        els.comboShortcuts       = document.querySelectorAll('#comboShortcuts .btn-shortcut');
        els.btnComboApply        = document.getElementById('btnComboApply');

        els.cardComboRevenue     = document.getElementById('cardComboRevenue');
        els.cardComboQty         = document.getElementById('cardComboQty');
        els.cardBestComboName    = document.getElementById('cardBestComboName');
        els.cardBestComboQty     = document.getElementById('cardBestComboQty');

        els.chartComboTheaterPie = document.getElementById('chartComboTheaterPie');
        els.chartTopComboBar     = document.getElementById('chartTopComboBar');

        // Food Tab DOMs
        els.foodFilterStart      = document.getElementById('foodFilterStart');
        els.foodFilterEnd        = document.getElementById('foodFilterEnd');
        els.foodTypeFilter       = document.getElementById('foodTypeFilter');
        els.foodShortcuts        = document.querySelectorAll('#foodShortcuts .btn-shortcut');
        els.btnFoodApply         = document.getElementById('btnFoodApply');

        els.cardFoodQty          = document.getElementById('cardFoodQty');
        els.cardFoodBestQtyName  = document.getElementById('cardFoodBestQtyName');
        els.cardFoodBestQtyVal   = document.getElementById('cardFoodBestQtyVal');
        els.cardFoodBestRevName  = document.getElementById('cardFoodBestRevName');
        els.cardFoodBestRevVal   = document.getElementById('cardFoodBestRevVal');

        els.chartFoodTypePie     = document.getElementById('chartFoodTypePie');
        els.chartFoodTopQtyBar   = document.getElementById('chartFoodTopQtyBar');
        els.chartFoodRevenueBar  = document.getElementById('chartFoodRevenueBar');

        // Tabs
        els.tabCombo = document.getElementById('tab-combo');
        els.tabFood  = document.getElementById('tab-food');
    }

    /* ── Chart instances ────────────────────────────────────────────── */
    const charts = {
        comboTheaterPie: null,
        topComboBar:     null,
        foodTypePie:     null,
        foodTopQtyBar:   null,
        foodRevenueBar:  null,
    };

    /* ── Helpers ────────────────────────────────────────────────────── */
    function toDateStr(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    }

    function formatCurrency(val) {
        const v = parseFloat(val);
        if (v >= 1e9)  return (v / 1e9).toFixed(1)  + ' tỷ ₫';
        if (v >= 1e6)  return (v / 1e6).toFixed(1)  + ' triệu ₫';
        if (v >= 1e3)  return (v / 1e3).toFixed(0)  + 'K ₫';
        return v.toFixed(0) + ' ₫';
    }

    function setDateRange(range, type = 'combo') {
        const now = new Date();
        let start;
        switch (range) {
            case 'week':
                start = new Date(now);
                start.setDate(now.getDate() - now.getDay() + 1);
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

        if (type === 'combo') {
            if (els.comboFilterStart) els.comboFilterStart.value = toDateStr(start);
            if (els.comboFilterEnd)   els.comboFilterEnd.value   = toDateStr(now);
        } else {
            if (els.foodFilterStart) els.foodFilterStart.value = toDateStr(start);
            if (els.foodFilterEnd)   els.foodFilterEnd.value   = toDateStr(now);
        }
    }

    /* ── Loading ────────────────────────────────────────────────────── */
    function showLoading(tab = 'combo') {
        const btn = tab === 'combo' ? els.btnComboApply : els.btnFoodApply;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cập nhật...';
        }
        
        const paneId = tab === 'combo' ? '#pane-combo' : '#pane-food';
        document.querySelectorAll(`${paneId} .stat-value, ${paneId} .stat-trend span, ${paneId} .chart-card > div[id^="chart"]`).forEach(el => {
            el.classList.add('skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('skeleton-text');
        });
    }

    function hideLoading(tab = 'combo') {
        const btn = tab === 'combo' ? els.btnComboApply : els.btnFoodApply;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Cập nhật';
        }
        
        const paneId = tab === 'combo' ? '#pane-combo' : '#pane-food';
        document.querySelectorAll(`${paneId} .skeleton`).forEach(el => {
            el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart');
        });
    }

    /* ── Chart builders ─────────────────────────────────────────────── */
    function darkChartDefaults(type, height) {
        return {
            chart: { type, height, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
            theme: { mode: 'dark' },
            grid:  { borderColor: '#27272a', strokeDashArray: 4 },
            colors: PALETTE,
        };
    }

    function initPieChart(elId) {
        const el = document.getElementById(elId);
        if (!el) return null;
        const c = new ApexCharts(el, {
            ...darkChartDefaults('pie', 300),
            series: [],
            labels: [],
            stroke:      { colors: ['#18181b'], width: 2 },
            dataLabels:  { enabled: true, dropShadow: { enabled: false } },
            legend:      { position: 'bottom', labels: { colors: '#a1a1aa' } },
        });
        c.render();
        return c;
    }

    function initBarChart(elId, formatFn = formatCurrency) {
        const el = document.getElementById(elId);
        if (!el) return null;
        const c = new ApexCharts(el, {
            ...darkChartDefaults('bar', 300),
            series: [{ name: 'Data', data: [] }],
            plotOptions: { bar: { horizontal: false, columnWidth: '45%', borderRadius: 4 } },
            dataLabels:  { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' }, rotate: -20 },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' }, formatter: v => formatFn(v) },
            },
            tooltip: { y: { formatter: v => formatFn(v) } },
        });
        c.render();
        return c;
    }

    function initHorizontalBarChart(elId) {
        const el = document.getElementById(elId);
        if (!el) return null;
        const c = new ApexCharts(el, {
            ...darkChartDefaults('bar', 340),
            series: [{ name: 'Doanh thu', data: [] }],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'top' } } },
            dataLabels:  { 
                enabled: true, 
                offsetX: 25, 
                style: { fontSize: '11px', colors: ['#fff'] },
                formatter: v => formatCurrency(v)
            },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' }, formatter: v => formatCurrency(v) },
            },
            yaxis: {
                labels: { style: { colors: '#a1a1aa', fontSize: '11px' } },
            },
            tooltip: { y: { formatter: v => formatCurrency(v) } },
        });
        c.render();
        return c;
    }

    function initCharts() {
        // Combo Charts
        charts.comboTheaterPie = initPieChart('chartComboTheaterPie');
        charts.topComboBar     = initBarChart('chartTopComboBar', formatCurrency);

        // Food Charts
        charts.foodTypePie     = initPieChart('chartFoodTypePie');
        charts.foodTopQtyBar   = initBarChart('chartFoodTopQtyBar', v => v.toLocaleString('vi-VN'));
        charts.foodRevenueBar  = initHorizontalBarChart('chartFoodRevenueBar');
    }

    /* ── Render Combo ───────────────────────────────────────────────── */
    function renderComboStats(data) {
        if (!data) return;
        
        // Cards
        const s = data.summary;
        if (s) {
            if (els.cardComboRevenue)  els.cardComboRevenue.textContent  = formatCurrency(s.total_revenue ?? 0);
            if (els.cardComboQty)      els.cardComboQty.textContent      = (s.total_quantity ?? 0).toLocaleString('vi-VN');
            if (els.cardBestComboName) els.cardBestComboName.textContent = s.best_combo_name ?? '—';
            if (els.cardBestComboQty)  els.cardBestComboQty.textContent  = (s.best_combo_qty ?? 0).toLocaleString('vi-VN');
        }

        // Theater Pie
        const theaterData = data.revenue_by_theater;
        if (charts.comboTheaterPie && theaterData?.length) {
            charts.comboTheaterPie.updateOptions({ labels: theaterData.map(r => r.theater_name) });
            charts.comboTheaterPie.updateSeries(theaterData.map(r => r.total_revenue));
        }

        // Top Combo Bar
        const topCombos = data.top_combos;
        if (charts.topComboBar && topCombos?.length) {
            const sanitize = str => { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; };
            charts.topComboBar.updateOptions({
                xaxis: { categories: topCombos.map(r => sanitize(r.name)) },
                tooltip: { y: { formatter: v => formatCurrency(v) } },
            });
            charts.topComboBar.updateSeries([{ name: 'Doanh thu', data: topCombos.map(r => r.total_revenue) }]);
        }
    }

    /* ── Render Food ────────────────────────────────────────────────── */
    function renderFoodStats(data) {
        if (!data) return;

        // Cards
        const s = data.summary;
        if (s) {
            if (els.cardFoodQty)         els.cardFoodQty.textContent         = (s.total_qty ?? 0).toLocaleString('vi-VN');
            if (els.cardFoodBestQtyName) els.cardFoodBestQtyName.textContent = s.best_qty_name ?? '—';
            if (els.cardFoodBestQtyVal)  els.cardFoodBestQtyVal.textContent  = (s.best_qty_value ?? 0).toLocaleString('vi-VN');
            if (els.cardFoodBestRevName) els.cardFoodBestRevName.textContent = s.best_revenue_name ?? '—';
            if (els.cardFoodBestRevVal)  els.cardFoodBestRevVal.textContent  = formatCurrency(s.best_revenue_value ?? 0);
        }

        // Type Ratio Pie
        const typeRatio = data.type_ratio;
        if (charts.foodTypePie && typeRatio?.length) {
            charts.foodTypePie.updateOptions({ labels: typeRatio.map(r => r.label) });
            charts.foodTypePie.updateSeries(typeRatio.map(r => r.total_qty)); // Hoặc doanh thu tùy ý, đang hiển thị theo số lượng
        }

        // Top Food By Qty Bar
        const topProducts = data.top_products;
        if (charts.foodTopQtyBar && topProducts?.length) {
            const sanitize = str => { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; };
            charts.foodTopQtyBar.updateOptions({
                xaxis: { categories: topProducts.map(r => sanitize(r.name)) },
            });
            charts.foodTopQtyBar.updateSeries([{ name: 'Số lượng', data: topProducts.map(r => r.total_qty) }]);
        }

        // Revenue Trend Horizontal Bar
        const revenueTrend = data.revenue_trend;
        if (charts.foodRevenueBar && revenueTrend?.length) {
            const sanitize = str => { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; };
            charts.foodRevenueBar.updateOptions({
                xaxis: { categories: revenueTrend.map(r => sanitize(r.name)) },
            });
            charts.foodRevenueBar.updateSeries([{ name: 'Doanh thu', data: revenueTrend.map(r => r.total_revenue) }]);
        }
    }

    /* ── API ────────────────────────────────────────────────────────── */
    async function loadComboStats() {
        if (typeof authManager === 'undefined') return;
        const start = els.comboFilterStart?.value;
        const end   = els.comboFilterEnd?.value;
        if (!start || !end) return;

        showLoading('combo');
        try {
            const url = `${COMBO_API}?start_date=${start}&end_date=${end}`;
            const res = await authManager.fetchAPI(url, { silentAuth: true });

            if (res?.success) {
                state.combo.data = res.data;
                state.combo.loaded = true;
                renderComboStats(res.data);
            }
        } catch (e) {
            console.error('[Combo] Error:', e);
        } finally {
            hideLoading('combo');
        }
    }

    async function loadFoodStats() {
        if (typeof authManager === 'undefined') return;
        const start = els.foodFilterStart?.value;
        const end   = els.foodFilterEnd?.value;
        const type  = els.foodTypeFilter?.value;
        if (!start || !end) return;

        showLoading('food');
        try {
            let url = `${FOOD_API}?start_date=${start}&end_date=${end}`;
            if (type) url += `&type=${type}`;
            
            const res = await authManager.fetchAPI(url, { silentAuth: true });

            if (res?.success) {
                state.food.data = res.data;
                state.food.loaded = true;
                renderFoodStats(res.data);
            }
        } catch (e) {
            console.error('[Food] Error:', e);
        } finally {
            hideLoading('food');
        }
    }

    /* ── Auth wait ──────────────────────────────────────────────────── */
    function waitForAuth(callback, retries = 50) {
        if (typeof authManager !== 'undefined' && authManager.authChecked) {
            callback();
            return;
        }
        if (retries <= 0) {
            console.error('[Stats] authManager timed out');
            return;
        }
        setTimeout(() => waitForAuth(callback, retries - 1), 150);
    }

    /* ── Events ─────────────────────────────────────────────────────── */
    function bindEvents() {
        // Combo Tab Events
        els.comboShortcuts.forEach(btn => {
            btn.addEventListener('click', () => {
                els.comboShortcuts.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                setDateRange(btn.dataset.range, 'combo');
                loadComboStats();
            });
        });

        els.btnComboApply?.addEventListener('click', loadComboStats);
        els.comboFilterStart?.addEventListener('change', loadComboStats);
        els.comboFilterEnd?.addEventListener('change', loadComboStats);

        // Food Tab Events
        els.foodShortcuts.forEach(btn => {
            btn.addEventListener('click', () => {
                els.foodShortcuts.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                setDateRange(btn.dataset.range, 'food');
                loadFoodStats();
            });
        });

        els.btnFoodApply?.addEventListener('click', loadFoodStats);
        els.foodFilterStart?.addEventListener('change', loadFoodStats);
        els.foodFilterEnd?.addEventListener('change', loadFoodStats);
        els.foodTypeFilter?.addEventListener('change', loadFoodStats);

        // Tab Switch Events (Lazy Loading)
        els.tabFood?.addEventListener('shown.bs.tab', () => {
            if (!state.food.loaded) {
                loadFoodStats();
            }
        });
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    function init() {
        setDateRange('week', 'combo');
        setDateRange('week', 'food');
        initCharts();
        bindEvents();
        
        // Initial load for active tab (Combo)
        waitForAuth(loadComboStats);
    }

    document.addEventListener('DOMContentLoaded', () => {
        cacheDoms();
        init();
    });

})();
