(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Configuration & State                                              */
    /* ------------------------------------------------------------------ */

    // fetchAPI trong auth.js tự động prepend this.apiUrl (/api/v1)
    // nên ta chỉ cần truyền path bắt đầu từ sau /api/v1
    const API_ENDPOINTS = {
        stats: '/admin/dashboard/stats'
    };
    
    let state = {
        revenueFilter: 'month',
        topMoviesFilter: 'month',
        pollInterval: null
    };

    let charts = {
        revenue: null,
        heatmap: null
    };

    /* ------------------------------------------------------------------ */
    /*  DOM References                                                     */
    /* ------------------------------------------------------------------ */
    let els = {};

    function cacheDoms() {
        els = {
            statRevenue: document.getElementById('statRevenue'),
            statRevenueTrend: document.getElementById('statRevenueTrend'),
            statTickets: document.getElementById('statTickets'),
            statTicketsTrend: document.getElementById('statTicketsTrend'),
            statNewUsers: document.getElementById('statNewUsers'),
            statUsersTrend: document.getElementById('statUsersTrend'),
            statRetention: document.getElementById('statRetention'),
            statRetentionProgress: document.getElementById('statRetentionProgress'),
            
            revenueChart: document.getElementById('revenueChart'),
            revenueFilter: document.getElementById('revenueFilter'),
            
            trafficHeatmap: document.getElementById('trafficHeatmap'),
            
            topMoviesContainer: document.getElementById('topMoviesContainer'),
            topMoviesFilter: document.getElementById('topMoviesFilter')
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
            series: [{ name: 'Doanh thu', data: [] }],
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif',
                background: 'transparent'
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
                    opacity: 0.3
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.6,
                    opacityTo: 0.0,
                    stops: [0, 100]
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: [],
                labels: {
                    style: { colors: '#a1a1aa', fontSize: '12px' }
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
                title: {
                    text: 'Thời gian',
                    style: { color: '#a1a1aa', fontSize: '12px' }
                }
            },
            yaxis: {
                min: 0,
                max: (max) => { return max < 500000 ? 500000 : max; },
                title: {
                    text: 'Doanh thu (₫)',
                    style: { color: '#a1a1aa', fontSize: '12px' }
                },
                labels: {
                    style: { colors: '#a1a1aa' },
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
                borderColor: '#2e2e33',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } }
            },
            tooltip: {
                theme: 'dark',
                y: { formatter: (val) => formatCurrency(val) }
            }
        };
        
        charts.revenue = new ApexCharts(els.revenueChart, options);
        charts.revenue.render();
    }

    function initHeatmapChart() {
        if (!els.trafficHeatmap) return;

        const options = {
            series: [],
            chart: {
                height: 350,
                type: 'heatmap',
                fontFamily: 'Inter, sans-serif',
                background: 'transparent',
                toolbar: { show: false }
            },
            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.6,
                    radius: 6,
                    useFillColorAsStroke: false,
                    colorScale: {
                        ranges: [
                            { from: 0, to: 0, color: 'rgba(255, 255, 255, 0.02)', name: 'Trống' },
                            { from: 1, to: 10, color: '#312e81', name: 'Rất thấp' },
                            { from: 11, to: 30, color: '#4f46e5', name: 'Thấp' },
                            { from: 31, to: 60, color: '#8b5cf6', name: 'Trung bình' },
                            { from: 61, to: 1000, color: '#d946ef', name: 'Cao' }
                        ]
                    }
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: ['#18181b'] },
            xaxis: {
                categories: ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
                labels: { style: { colors: '#a1a1aa', fontWeight: 500 } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { style: { colors: '#a1a1aa', fontWeight: 500 } }
            },
            grid: { show: false },
            tooltip: { 
                theme: 'dark',
                style: {
                    fontSize: '13px',
                    fontFamily: 'Inter, sans-serif'
                }
            }
        };

        charts.heatmap = new ApexCharts(els.trafficHeatmap, options);
        charts.heatmap.render();
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
        
        let opts = { xaxis: { categories } };
        
        // FIX: Area/Line charts won't render a single data point without markers. 
        // If there's only 1 point, force a marker to show up.
        if (data.length === 1) {
            opts.markers = { size: 6, strokeWidth: 2, hover: { size: 8 } };
        } else {
            opts.markers = { size: 0 };
        }
        
        charts.revenue.updateOptions(opts);
        charts.revenue.updateSeries([{ name: 'Doanh thu', data }]);
    }

    function updateHeatmapChart(heatmapData) {
        if (!charts.heatmap || !heatmapData) return;

        // Init blank matrix: 7 days x 17 hours (7 to 23)
        const days = ['Chủ nhật', 'Thứ 7', 'Thứ 6', 'Thứ 5', 'Thứ 4', 'Thứ 3', 'Thứ 2'];
        const matrix = days.map(day => ({ name: day, data: new Array(17).fill(0) }));

        // Map data to matrix
        // day_of_week in DB: 1=Sun, 2=Mon... 7=Sat
        // Our matrix index: 0=Sun, 1=Sat, 2=Fri... 6=Mon (Apex Heatmap draws bottom-up usually, or top-down based on array order)
        heatmapData.forEach(item => {
            if (item.hour >= 7 && item.hour <= 23) {
                const hourIndex = item.hour - 7;
                let dayIndex;
                switch (item.day_of_week) {
                    case 2: dayIndex = 6; break; // Thứ 2
                    case 3: dayIndex = 5; break;
                    case 4: dayIndex = 4; break;
                    case 5: dayIndex = 3; break;
                    case 6: dayIndex = 2; break;
                    case 7: dayIndex = 1; break; // Thứ 7
                    case 1: dayIndex = 0; break; // Chủ nhật
                }
                if (dayIndex !== undefined) {
                    matrix[dayIndex].data[hourIndex] = parseInt(item.customer_count);
                }
            }
        });

        charts.heatmap.updateSeries(matrix);
    }

    function renderTopMovies(movies) {
        if (!els.topMoviesContainer) return;
        
        if (!movies || movies.length === 0) {
            els.topMoviesContainer.innerHTML = `<div class="col-12 text-center text-muted">Không có dữ liệu trong khoảng thời gian này.</div>`;
            return;
        }

        els.topMoviesContainer.innerHTML = movies.map(movie => {
            const poster = movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
            const revenue = formatCurrency(movie.revenue);
            const tickets = new Intl.NumberFormat('vi-VN').format(movie.tickets_sold);
            
            return `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card" style="background-image: url('${escapeHtml(poster)}')">
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
        const skeletonHtml = '<div class="admin-skeleton admin-skeleton-text" style="width: 60%; height: 28px; margin-bottom: 0;"></div>';
        const trendSkeletonHtml = '<div class="admin-skeleton admin-skeleton-text" style="width: 40px; margin-bottom: 0;"></div>';
        
        els.statRevenue.innerHTML = skeletonHtml;
        els.statRevenueTrend.innerHTML = trendSkeletonHtml;
        
        els.statTickets.innerHTML = skeletonHtml;
        els.statTicketsTrend.innerHTML = trendSkeletonHtml;
        
        els.statNewUsers.innerHTML = skeletonHtml;
        els.statUsersTrend.innerHTML = trendSkeletonHtml;
        
        els.statRetention.innerHTML = skeletonHtml;
    }

    async function fetchStats(target = 'all') {
        if (typeof authManager === 'undefined') return;
        
        try {
            // For real production, you might want to separate APIs so filter doesn't reload everything
            // But for simplicity, we pass ranges and update what's needed
            if (target === 'all' || target === 'cards') showStatsSkeleton();
            const url = `${API_ENDPOINTS.stats}?range=${state.revenueFilter}`;
            const response = await authManager.fetchAPI(url, { silentAuth: true });
            
            if (response && response.success) {
                const data = response.data;
                
                if (target === 'all' || target === 'cards') renderCards(data.cards);
                if (target === 'all' || target === 'revenue') updateRevenueChart(data.revenue_by_day);
                if (target === 'all' || target === 'heatmap') updateHeatmapChart(data.traffic_heatmap);
            }
        } catch (e) {
            console.warn('Dashboard sync error:', e);
        }
    }

    async function fetchTopMovies() {
        if (typeof authManager === 'undefined') return;
        try {
            const url = `${API_ENDPOINTS.stats}?range=${state.topMoviesFilter}`;
            const response = await authManager.fetchAPI(url, { silentAuth: true });
            if (response && response.success) {
                renderTopMovies(response.data.top_movies);
            }
        } catch (e) {
            console.warn('Top movies sync error:', e);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Events & Lifecycle                                                 */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        if (els.revenueFilter) {
            els.revenueFilter.addEventListener('change', (e) => {
                state.revenueFilter = e.target.value;
                fetchStats('revenue');
            });
        }
        
        if (els.topMoviesFilter) {
            els.topMoviesFilter.addEventListener('change', (e) => {
                state.topMoviesFilter = e.target.value;
                        let skeletonHtml = '';
        for(let i=0; i<6; i++) {
            skeletonHtml += `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card admin-skeleton" style="background: var(--admin-surface); border-color: transparent;"></div>
            </div>`;
        }
        els.topMoviesContainer.innerHTML = skeletonHtml;
                fetchTopMovies();
            });
        }
    }

    function init() {
        initRevenueChart();
        initHeatmapChart();
        bindEvents();
        
        // Initial load
        fetchStats('all');
        fetchTopMovies();
        
        // Start polling every 30 seconds
        state.pollInterval = setInterval(() => {
            fetchStats('all');
            fetchTopMovies();
        }, 30000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        cacheDoms();
        init();
    });

})();
