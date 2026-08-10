(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Configuration & State                                              */
    /* ------------------------------------------------------------------ */

    const API_ENDPOINTS = {
        stats: '/api/v1/admin/dashboard/stats'
    };
    
    let state = {
        pollInterval: null
    };

    let charts = {
        revenue: null,
        traffic: null
    };

    /* ------------------------------------------------------------------ */
    /*  DOM References                                                     */
    /* ------------------------------------------------------------------ */
    let els = {};

    function cacheDoms() {
        els = {
            // Filter elements
            dashboardFilterStart: document.getElementById('dashboardFilterStart'),
            dashboardFilterEnd: document.getElementById('dashboardFilterEnd'),
            dashboardBtnApply: document.getElementById('dashboardBtnApply'),
            dashboardShortcuts: document.querySelectorAll('.btn-shortcut'),
            
            // Stats cards
            statRevenue: document.getElementById('statRevenue'),
            statRevenueTrend: document.getElementById('statRevenueTrend'),
            statTickets: document.getElementById('statTickets'),
            statTicketsTrend: document.getElementById('statTicketsTrend'),
            statNewUsers: document.getElementById('statNewUsers'),
            statUsersTrend: document.getElementById('statUsersTrend'),
            statRetention: document.getElementById('statRetention'),
            statRetentionProgress: document.getElementById('statRetentionProgress'),
            
            // Charts
            revenueChart: document.getElementById('revenueChart'),
            trafficHeatmap: document.getElementById('trafficHeatmap'),
            topMoviesContainer: document.getElementById('topMoviesContainer')
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */
    function formatTrend(trendValue, element) {
        if (!element) return;
        const value = parseFloat(trendValue);
        const absValue = Math.abs(value).toFixed(1);
        
        element.className = '';
        if (value > 0) {
            element.classList.add('text-success', 'fw-bold');
            element.innerHTML = `<i class="bi bi-graph-up-arrow"></i> +${absValue}%`;
        } else if (value < 0) {
            element.classList.add('text-danger', 'fw-bold');
            element.innerHTML = `<i class="bi bi-graph-down-arrow"></i> -${absValue}%`;
        } else {
            element.classList.add('text-secondary', 'fw-bold');
            element.innerHTML = `<i class="bi bi-dash"></i> 0%`;
        }
    }

    function formatCurrency(amount) {
        return window.formatCurrency ? window.formatCurrency(amount) : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
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
        if (els.dashboardFilterStart) els.dashboardFilterStart.value = toDateStr(start);
        if (els.dashboardFilterEnd) els.dashboardFilterEnd.value = toDateStr(now);
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    /* ------------------------------------------------------------------ */
    /*  Charts Initialization                                              */
    /* ------------------------------------------------------------------ */
    function initRevenueChart() {
        if (!els.revenueChart) return;
        
        const options = {
            series: [{ name: 'Doanh thu', data: [0] }],
            noData: {
                text: 'Chưa có dữ liệu',
                style: { color: '#a1a1aa', fontSize: '14px' }
            },
            chart: {
                type: 'area',
                height: 300,
                width: '100%',
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif',
                background: 'transparent',
                parentHeightOffset: 0,
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
                animations: { enabled: false }
            },
            colors: ['#e50914'],
            stroke: {
                curve: 'smooth',
                width: 3,
                dropShadow: {
                    enabled: true,
                    top: 5,
                    left: 0,
                    blur: 4,
                    color: '#e50914',
                    opacity: 0.18
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.28,
                    opacityTo: 0.02,
                    stops: [0, 100]
                }
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                size: 4,
                strokeWidth: 2,
                hover: { size: 6, sizeOffset: 2 }
            },
            xaxis: {
                categories: [''],
                labels: {
                    style: { colors: '#d4d4d8', fontSize: '12px', fontWeight: 600 }
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
                title: {
                    text: 'Thời gian',
                    style: { color: '#d4d4d8', fontSize: '12px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: (max) => Number.isFinite(max) && max > 0 ? Math.max(max, 500000) : 500000,
                title: {
                    text: 'Doanh thu (₫)',
                    style: { color: '#d4d4d8', fontSize: '12px', fontWeight: 600 }
                },
                labels: {
                    style: { colors: '#d4d4d8', fontWeight: 600 },
                    formatter: (value) => {
                        if (value === 0) return '0₫';
                        if (value >= 1000000000) return (value / 1000000000).toFixed(1) + 'B';
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                        if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                        return value + '₫';
                    }
                }
            },
            grid: {
                borderColor: 'rgba(255,255,255,0.08)',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } }
            },
            tooltip: {
                enabled: true,
                theme: 'dark',
                shared: false,
                intersect: false,
                followCursor: false,
                marker: { show: true },
                fixed: { enabled: true, position: 'topRight', offsetX: -16, offsetY: 12 },
                x: { show: true },
                y: {
                    title: { formatter: () => 'Doanh thu: ' },
                    formatter: (val) => formatCurrency(val)
                }
            }
        };
        
        charts.revenue = new ApexCharts(els.revenueChart, options);
        charts.revenue.render();
    }

    function initTrafficChart() {
        if (!els.trafficHeatmap) return;

        const options = {
            series: [
                { name: 'Sáng 07–11h', data: new Array(7).fill(0) },
                { name: 'Trưa 12–16h', data: new Array(7).fill(0) },
                { name: 'Tối 17–20h', data: new Array(7).fill(0) },
                { name: 'Muộn 21–23h', data: new Array(7).fill(0) },
            ],
            noData: {
                text: 'Chưa có dữ liệu',
                style: { color: '#a1a1aa', fontSize: '14px' }
            },
            chart: {
                height: 360,
                type: 'bar',
                stacked: true,
                width: '100%',
                fontFamily: 'Inter, sans-serif',
                background: 'transparent',
                toolbar: { show: false },
                parentHeightOffset: 0,
                redrawOnParentResize: true,
                redrawOnWindowResize: true,
                animations: { enabled: false }
            },
            colors: ['#e50914', '#ef4444', '#a1a1aa', '#52525b'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '62%',
                    borderRadius: 4,
                    borderRadiusApplication: 'end',
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 1, colors: ['#18181b'] },
            xaxis: {
                categories: ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'],
                labels: {
                    style: { colors: '#d4d4d8', fontWeight: 600 },
                    formatter: (value) => Math.round(Number(value) || 0),
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
                title: {
                    text: 'Số khách',
                    style: { color: '#a1a1aa', fontSize: '12px', fontWeight: 600 },
                },
            },
            yaxis: {
                labels: { style: { colors: '#d4d4d8', fontWeight: 600 } }
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px',
                labels: { colors: '#d4d4d8' },
                markers: { size: 5 },
            },
            grid: {
                borderColor: 'rgba(255,255,255,0.08)',
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: false } },
            },
            tooltip: { 
                theme: 'dark',
                shared: false,
                intersect: false,
                style: {
                    fontSize: '13px',
                    fontFamily: 'Inter, sans-serif'
                },
                y: { formatter: (value) => `${value} khách` }
            }
        };

        charts.traffic = new ApexCharts(els.trafficHeatmap, options);
        charts.traffic.render();
    }

    /* ------------------------------------------------------------------ */
    /*  Data Processing & Rendering                                        */
    /* ------------------------------------------------------------------ */
    
    function renderCards(cards) {
        if (!cards) return;
        
        els.statRevenue.textContent = formatCurrency(cards.revenue.value);
        formatTrend(cards.revenue.trend, els.statRevenueTrend);
        
        els.statTickets.textContent = new Intl.NumberFormat('vi-VN').format(cards.tickets.value);
        formatTrend(cards.tickets.trend, els.statTicketsTrend);
        
        els.statNewUsers.textContent = new Intl.NumberFormat('vi-VN').format(cards.new_users.value);
        formatTrend(cards.new_users.trend, els.statUsersTrend);
        
        els.statRetention.textContent = `${cards.retention_rate.value}%`;
        els.statRetentionProgress.style.width = `${cards.retention_rate.value}%`;
    }

    function updateRevenueChart(revenueData) {
        if (!charts.revenue || !revenueData) return;
        
        const categories = revenueData.map(item => {
            const d = new Date(item.date);
            return `${d.getDate()}/${d.getMonth()+1}`;
        });
        const data = revenueData.map(item => parseFloat(item.revenue));
        
        const upperGridLine = Math.max(...data, 500000);
        let opts = {
            xaxis: { categories },
            annotations: {
                yaxis: [
                    { y: 0, borderColor: 'rgba(255,255,255,0.08)', strokeDashArray: 4 },
                    { y: upperGridLine, borderColor: 'rgba(255,255,255,0.08)', strokeDashArray: 4 },
                ],
            },
        };
        
        // FIX: Area/Line charts won't render a single data point without markers. 
        // If there's only 1 point, force a marker to show up.
        if (data.length === 1) {
            opts.markers = { size: 6, strokeWidth: 2, hover: { size: 8, sizeOffset: 2 } };
        } else {
            opts.markers = { size: 4, strokeWidth: 2, hover: { size: 7, sizeOffset: 2 } };
        }
        
        charts.revenue.updateOptions(opts);
        charts.revenue.updateSeries([{ name: 'Doanh thu', data }]);
    }

    function updateTrafficChart(trafficData) {
        if (!charts.traffic || !Array.isArray(trafficData)) return;

        const series = [
            { name: 'Sáng 07–11h', data: new Array(7).fill(0) },
            { name: 'Trưa 12–16h', data: new Array(7).fill(0) },
            { name: 'Tối 17–20h', data: new Array(7).fill(0) },
            { name: 'Muộn 21–23h', data: new Array(7).fill(0) },
        ];
        const dayIndexes = { 2: 0, 3: 1, 4: 2, 5: 3, 6: 4, 7: 5, 1: 6 };

        trafficData.forEach((item) => {
            const hour = Number(item.hour);
            const dayIndex = dayIndexes[Number(item.day_of_week)];
            if (dayIndex === undefined || hour < 7 || hour > 23) return;

            const bucketIndex = hour <= 11 ? 0 : hour <= 16 ? 1 : hour <= 20 ? 2 : 3;
            series[bucketIndex].data[dayIndex] += Number(item.customer_count) || 0;
        });

        charts.traffic.updateSeries(series);
    }

    function renderTopMovies(movies) {
        if (!els.topMoviesContainer) return;
        
        if (!movies || movies.length === 0) {
            els.topMoviesContainer.innerHTML = `<div class="col-12 text-center text-muted">Không có dữ liệu trong khoảng thời gian này.</div>`;
            return;
        }

        els.topMoviesContainer.innerHTML = movies.map(movie => {
            const poster = movie.poster_display_url || movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
            const revenue = formatCurrency(movie.revenue);
            const tickets = new Intl.NumberFormat('vi-VN').format(movie.tickets_sold);
            
            return `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card">
                    <img class="movie-card-poster" src="${escapeHtml(poster)}" alt="" loading="lazy">
                    <div class="movie-tag gradient-red">${revenue}</div>
                    <div class="movie-info">
                        <div class="movie-card-title text-truncate" title="${escapeHtml(movie.title)}">${escapeHtml(movie.title)}</div>
                        <div class="movie-meta"><i class="bi bi-ticket-perforated-fill"></i> ${tickets} vé bán ra</div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    /* ------------------------------------------------------------------ */
    /*  API Flow                                                           */
    /* ------------------------------------------------------------------ */
        function showStatsSkeleton() {
        const skeletonHtml = '<div class="admin-skeleton admin-skeleton-text dashboard-stat-skeleton"></div>';
        const trendSkeletonHtml = '<div class="admin-skeleton admin-skeleton-text dashboard-trend-skeleton"></div>';
        
        els.statRevenue.innerHTML = skeletonHtml;
        els.statRevenueTrend.innerHTML = trendSkeletonHtml;
        
        els.statTickets.innerHTML = skeletonHtml;
        els.statTicketsTrend.innerHTML = trendSkeletonHtml;
        
        els.statNewUsers.innerHTML = skeletonHtml;
        els.statUsersTrend.innerHTML = trendSkeletonHtml;
        
        els.statRetention.innerHTML = skeletonHtml;
    }

    async function fetchStats(target = 'all', { showSkeleton = true, skipCache = false } = {}) {
        if (!window.AdminCore) return;

        try {
            if (showSkeleton && (target === 'all' || target === 'cards')) showStatsSkeleton();
            
            // Build URL with date range filters
            let url = API_ENDPOINTS.stats;
            const params = new URLSearchParams();
            
            if (els.dashboardFilterStart && els.dashboardFilterStart.value) {
                params.append('start_date', els.dashboardFilterStart.value);
            }
            if (els.dashboardFilterEnd && els.dashboardFilterEnd.value) {
                params.append('end_date', els.dashboardFilterEnd.value);
            }
            
            if (params.toString()) {
                url += '?' + params.toString();
            }
            
            const response = await window.AdminCore.apiFetch(url, {
                requestKey: 'dashboard:stats',
                cacheTtl: 30000,
                skipCache,
            });
            if (!response?.ok) return;

            const payload = await response.json();
            if (payload.success) {
                const data = payload.data;
                
                if (target === 'all' || target === 'cards') renderCards(data.cards);
                if (target === 'all' || target === 'revenue') updateRevenueChart(data.revenue_by_day);
                if (target === 'all' || target === 'traffic') updateTrafficChart(data.traffic_heatmap);
                if (target === 'all' || target === 'topMovies') renderTopMovies(data.top_movies);
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.warn('Dashboard sync error:', e);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Events & Lifecycle                                                 */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        // Date filter shortcuts
        if (els.dashboardShortcuts) {
            els.dashboardShortcuts.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active from all, add to clicked
                    els.dashboardShortcuts.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    // Set date range and fetch
                    setDateRange(btn.dataset.range);
                    fetchStats('all');
                });
            });
        }
        
        // Apply button
        if (els.dashboardBtnApply) {
            els.dashboardBtnApply.addEventListener('click', () => {
                // Remove active from all shortcuts (custom range)
                if (els.dashboardShortcuts) {
                    els.dashboardShortcuts.forEach(b => b.classList.remove('active'));
                }
                
                fetchStats('all');
            });
        }
        
    }

    function init() {
        initRevenueChart();
        initTrafficChart();
        bindEvents();
        
        // Set default date range (week)
        setDateRange('week');
        
        // Initial load
        fetchStats('all');
        
        // Refresh quietly only while the dashboard is visible.
        state.pollInterval = setInterval(() => {
            if (!document.hidden) {
                fetchStats('all', { showSkeleton: false, skipCache: true });
            }
        }, 15000);
    }

    window.onAdminPageCleanup(function () {
        if (state.pollInterval) clearInterval(state.pollInterval);
        state.pollInterval = null;
        charts.revenue?.destroy?.();
        charts.traffic?.destroy?.();
        charts = { revenue: null, traffic: null };
    });

    window.onAdminPageLoad(function () {
        cacheDoms();
        init();
    });

})();
