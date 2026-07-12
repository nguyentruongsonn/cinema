import sys
import re

blade_path = "resources/views/admin/revenue/index.blade.php"
with open(blade_path, "r", encoding="utf-8") as f:
    blade_content = f.read()

# Add dashboard-redesign.css
new_styles = """@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/components/skeleton.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/dashboard-redesign.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ time() }}">
@endpush"""
blade_content = re.sub(r"@push\('styles'\)\s*<link rel=\"stylesheet\" href=\"\{\{\s*asset\('css/admin/pages/stats\.css'\)\s*\}\}\?v=\{\{\s*time\(\)\s*\}\}\">\s*@endpush", new_styles, blade_content)

# Replace old skeleton classes with admin-skeleton
blade_content = blade_content.replace('skeleton skeleton-text', 'admin-skeleton admin-skeleton-text')
blade_content = blade_content.replace('skeleton skeleton-chart', 'admin-skeleton admin-skeleton-text') # There is no skeleton-chart in skeleton.css, just use text for shimmer effect

with open(blade_path, "w", encoding="utf-8") as f:
    f.write(blade_content)
print("Updated blade.")

js_path = "public/js/admin/pages/revenue.js"
with open(js_path, "r", encoding="utf-8") as f:
    js_content = f.read()

# Update hideLoading and showLoading to handle admin-skeleton classes
js_content = js_content.replace("el.classList.add('skeleton', 'skeleton-text');", "el.classList.add('admin-skeleton', 'admin-skeleton-text');")
js_content = js_content.replace("if (!el.id.startsWith('chart')) el.classList.add('skeleton-text');", "if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');")
js_content = js_content.replace("el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart');", "el.classList.remove('skeleton', 'skeleton-text', 'skeleton-chart', 'admin-skeleton', 'admin-skeleton-text');")

# Inject polling logic
polling_code = """
        // Auto-refresh every 30s
        if (state.pollInterval) clearInterval(state.pollInterval);
        state.pollInterval = setInterval(() => {
            fetchStats();
        }, 30000);
"""
# inject polling logic inside `init()`
if "fetchStats();" in js_content and polling_code not in js_content:
    js_content = js_content.replace("        fetchStats();", "        fetchStats();\n" + polling_code)

# Add state for polling
if "let state" not in js_content: # state object might not exist
    js_content = js_content.replace("let charts = { theater: null, movie: null, payment: null, trend: null };", "let state = { pollInterval: null };\n    let charts = { theater: null, movie: null, payment: null, trend: null };")


# Update chart configs
new_theater_pie = """    function initTheaterPie() {
        const opts = {
            series: [],
            labels: [],
            chart : { type: 'pie', height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: PALETTE,
            stroke: { width: 0 },
            legend: { position: 'bottom', labels: { colors: '#a1a1aa' }, fontSize: '12px', markers: { radius: 12 } },
            dataLabels: { style: { fontSize: '11px', fontWeight: 600 }, dropShadow: { enabled: true, top: 1, left: 1, blur: 2, color: '#000', opacity: 0.8 } },
            tooltip : { theme: 'dark', style: { fontSize: '13px' }, y: { formatter: formatCurrencyFull } },
        };
        charts.theater = new ApexCharts(els.chartTheaterPie, opts);
        charts.theater.render();
    }"""
js_content = re.sub(r"function initTheaterPie\(\) \{.*?(?=function initMovieBar\(\))", new_theater_pie + "\n\n    ", js_content, flags=re.DOTALL)

new_movie_bar = """    function initMovieBar() {
        const opts = {
            series: [{ name: 'Doanh thu', data: [] }],
            chart : {
                type: 'bar', height: 300, background: 'transparent',
                fontFamily: 'Inter, sans-serif', toolbar: { show: false },
            },
            colors: ['#e50914'],
            plotOptions: { 
                bar: { 
                    horizontal: true, 
                    borderRadius: 4, 
                    barHeight: '60%',
                    dataLabels: { position: 'top' } 
                } 
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: {
                    style: { colors: '#a1a1aa', fontSize: '11px', fontWeight: 500 },
                    formatter: v => formatCurrency(v),
                },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: '#a1a1aa', fontSize: '12px', fontWeight: 600 }, maxWidth: 150 } },
            grid : { borderColor: 'rgba(255, 255, 255, 0.05)', strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: 'dark', style: { fontSize: '13px' }, x: { show: true }, y: { formatter: formatCurrencyFull } },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    type: 'horizontal',
                    shadeIntensity: 0.5,
                    gradientToColors: ['#ff1a1f'],
                    inverseColors: true,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100]
                }
            }
        };
        charts.movie = new ApexCharts(els.chartMovieBar, opts);
        charts.movie.render();
    }"""
js_content = re.sub(r"function initMovieBar\(\) \{.*?(?=function initPaymentDonut\(\))", new_movie_bar + "\n\n    ", js_content, flags=re.DOTALL)

new_payment_donut = """    function initPaymentDonut() {
        const opts = {
            series: [],
            labels: [],
            chart : { type: 'donut', height: 280, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            colors: PALETTE,
            stroke : { width: 0 },
            plotOptions: { pie: { donut: { size: '65%', labels: {
                show: true,
                total: {
                    show: true,
                    label: 'Tổng lượt',
                    color: '#71717a',
                    fontSize: '13px',
                    formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('vi-VN'),
                },
                value: { color: '#ffffff', fontSize: '24px', fontWeight: 700, formatter: v => Number(v).toLocaleString('vi-VN') },
            } } } },
            dataLabels: { enabled: false },
            legend  : { show: false },
            tooltip : { theme: 'dark', style: { fontSize: '13px' } },
        };
        charts.payment = new ApexCharts(els.chartPaymentDonut, opts);
        charts.payment.render();
    }"""
js_content = re.sub(r"function initPaymentDonut\(\) \{.*?(?=function initTrendArea\(\))", new_payment_donut + "\n\n    ", js_content, flags=re.DOTALL)

new_trend_area = """    function initTrendArea() {
        const opts = {
            series: [
                { name: 'Doanh thu', data: [], type: 'area' },
                { name: 'Đơn hàng',  data: [], type: 'line' },
            ],
            chart  : { height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
            colors : ['#e50914', '#3b82f6'],
            stroke : { curve: 'smooth', width: [3, 2], dropShadow: { enabled: true, top: 4, left: 0, blur: 4, opacity: 0.15 } },
            fill: {
                type: ['gradient', 'solid'],
                gradient: { shadeIntensity: 1, opacityFrom: 0.6, opacityTo: 0.0, stops: [0, 100] }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: [],
                labels: { style: { colors: '#a1a1aa', fontSize: '12px', fontWeight: 500 } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: [
                { title: { text: 'Doanh thu (₫)', style: { color: '#71717a', fontSize: '12px', fontWeight: 600 } },
                  labels: { style: { colors: '#a1a1aa', fontWeight: 500 }, formatter: v => formatCurrency(v) } },
                { opposite: true, title: { text: 'Số đơn', style: { color: '#71717a', fontSize: '12px', fontWeight: 600 } },
                  labels: { style: { colors: '#a1a1aa', fontWeight: 500 }, formatter: v => Math.round(v) } },
            ],
            grid : { borderColor: 'rgba(255, 255, 255, 0.05)', strokeDashArray: 4, xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
            tooltip: { theme: 'dark', style: { fontSize: '13px' } },
        };
        charts.trend = new ApexCharts(els.chartRevenueTrend, opts);
        charts.trend.render();
    }"""
js_content = re.sub(r"function initTrendArea\(\) \{.*?(?=function render\(\))", new_trend_area + "\n\n    ", js_content, flags=re.DOTALL)


with open(js_path, "w", encoding="utf-8") as f:
    f.write(js_content)
print("Updated JS.")
