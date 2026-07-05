/**
 * Combo Statistics Dashboard - combo_stats.js
 * Displays combo sales analytics with charts and metrics
 */
(function () {
    'use strict';

    // Date range elements
    const els = {
        filterStart: document.getElementById('filterStart'),
        filterEnd: document.getElementById('filterEnd'),
        btnApplyFilter: document.getElementById('btnApplyFilter'),
        shortcuts: document.querySelectorAll('.btn-shortcut'),
        
        // Stat cards
        cardTotalCombos: document.getElementById('cardTotalCombos'),
        cardRevenue: document.getElementById('cardRevenue'),
        cardAvgPerDay: document.getElementById('cardAvgPerDay'),
        cardTopCombo: document.getElementById('cardTopCombo'),
        
        // Charts
        chartComboTrend: document.getElementById('chartComboTrend'),
        chartTopCombos: document.getElementById('chartTopCombos'),
        chartComboRevenue: document.getElementById('chartComboRevenue'),
    };

    let charts = {};
    let currentRange = 'week';

    // Helper: Set date range
    function setDateRange(range) {
        const end = new Date();
        const start = new Date();
        
        switch(range) {
            case 'week':
                start.setDate(end.getDate() - 7);
                break;
            case 'month':
                start.setMonth(end.getMonth() - 1);
                break;
            case 'quarter':
                start.setMonth(end.getMonth() - 3);
                break;
            case 'year':
                start.setFullYear(end.getFullYear() - 1);
                break;
        }
        
        els.filterStart.value = start.toISOString().split('T')[0];
        els.filterEnd.value = end.toISOString().split('T')[0];
    }

    // Load stats data
    async function loadStats() {
        const startDate = els.filterStart.value;
        const endDate = els.filterEnd.value;
        
        try {
            // TODO: Implement API call
            // const response = await fetch(`/api/v1/admin/combos/stats?start=${startDate}&end=${endDate}`);
            // const data = await response.json();
            
            // For now, show placeholder
            updateCards({
                totalCombos: 0,
                revenue: 0,
                avgPerDay: 0,
                topCombo: 'Chưa có dữ liệu'
            });
            
            renderCharts({
                trend: [],
                topCombos: [],
                revenue: []
            });
            
        } catch (error) {
            console.error('Error loading stats:', error);
            window.showAdminToast?.('Lỗi tải dữ liệu thống kê', 'error');
        }
    }

    // Update stat cards
    function updateCards(data) {
        els.cardTotalCombos.textContent = data.totalCombos.toLocaleString('vi-VN');
        els.cardRevenue.textContent = new Intl.NumberFormat('vi-VN').format(data.revenue);
        els.cardAvgPerDay.textContent = data.avgPerDay.toLocaleString('vi-VN', { maximumFractionDigits: 1 });
        els.cardTopCombo.textContent = data.topCombo;
    }

    // Render all charts
    function renderCharts(data) {
        renderTrendChart(data.trend);
        renderTopCombosChart(data.topCombos);
        renderRevenueChart(data.revenue);
    }

    // Trend chart
    function renderTrendChart(data) {
        if (charts.trend) charts.trend.destroy();
        
        const options = {
            series: [{
                name: 'Số lượng combo',
                data: data.map(d => d.count) || []
            }],
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                foreColor: '#9ca3af'
            },
            colors: ['#e50914'],
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.1
                }
            },
            xaxis: {
                categories: data.map(d => d.date) || [],
                labels: { style: { colors: '#9ca3af' } }
            },
            yaxis: {
                labels: { style: { colors: '#9ca3af' } }
            },
            grid: { borderColor: '#374151' },
            tooltip: { theme: 'dark' }
        };
        
        charts.trend = new ApexCharts(els.chartComboTrend, options);
        charts.trend.render();
    }

    // Top combos chart
    function renderTopCombosChart(data) {
        if (charts.topCombos) charts.topCombos.destroy();
        
        const options = {
            series: data.map(d => d.count) || [],
            labels: data.map(d => d.name) || [],
            chart: {
                type: 'donut',
                height: 300,
                foreColor: '#9ca3af'
            },
            colors: ['#e50914', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'],
            legend: {
                position: 'bottom',
                labels: { colors: '#9ca3af' }
            },
            tooltip: { theme: 'dark' },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                color: '#9ca3af'
                            }
                        }
                    }
                }
            }
        };
        
        charts.topCombos = new ApexCharts(els.chartTopCombos, options);
        charts.topCombos.render();
    }

    // Revenue chart
    function renderRevenueChart(data) {
        if (charts.revenue) charts.revenue.destroy();
        
        const options = {
            series: [{
                name: 'Doanh thu',
                data: data.map(d => d.revenue) || []
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false },
                foreColor: '#9ca3af'
            },
            colors: ['#e50914'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4
                }
            },
            xaxis: {
                categories: data.map(d => d.name) || [],
                labels: {
                    formatter: (val) => new Intl.NumberFormat('vi-VN').format(val),
                    style: { colors: '#9ca3af' }
                }
            },
            yaxis: {
                labels: { style: { colors: '#9ca3af' } }
            },
            grid: { borderColor: '#374151' },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: (val) => new Intl.NumberFormat('vi-VN', {
                        style: 'currency',
                        currency: 'VND'
                    }).format(val)
                }
            }
        };
        
        charts.revenue = new ApexCharts(els.chartComboRevenue, options);
        charts.revenue.render();
    }

    // Event listeners
    if (els.btnApplyFilter) {
        els.btnApplyFilter.addEventListener('click', loadStats);
    }

    els.shortcuts.forEach(btn => {
        btn.addEventListener('click', () => {
            els.shortcuts.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentRange = btn.dataset.range;
            setDateRange(currentRange);
            loadStats();
        });
    });

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        setDateRange(currentRange);
        loadStats();
    });

})();