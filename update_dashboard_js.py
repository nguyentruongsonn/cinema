import sys

path = "public/js/admin/pages/dashboard.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace Revenue Chart configs
old_revenue = """            colors: ['#e50914'],
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05,
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
                        if (value >= 1000) return (value / 1000).toFixed(0) + 'k';
                        return value;
                    }
                }
            },
            grid: {
                borderColor: '#2e2e33',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                }
            }"""

new_revenue = """            colors: ['#e50914'],
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
                    style: { colors: '#a1a1aa', fontSize: '12px', fontWeight: 500 }
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
                title: {
                    text: 'Thời gian',
                    style: { color: '#71717a', fontSize: '12px', fontWeight: 600 }
                }
            },
            yaxis: {
                min: 0,
                max: (max) => { return max < 500000 ? 500000 : max; },
                title: {
                    text: 'Doanh thu (₫)',
                    style: { color: '#71717a', fontSize: '12px', fontWeight: 600 }
                },
                labels: {
                    style: { colors: '#a1a1aa', fontWeight: 500 },
                    formatter: (value) => {
                        if (value === 0) return '0₫';
                        if (value >= 1000000000) return (value / 1000000000).toFixed(1) + 'B';
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                        if (value >= 1000) return (value / 1000).toFixed(0) + 'k';
                        return value;
                    }
                }
            },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.05)',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } },
                padding: { top: 0, right: 0, bottom: 0, left: 10 }
            },
            tooltip: {
                theme: 'dark',
                style: {
                    fontSize: '13px',
                    fontFamily: 'Inter, sans-serif'
                },
                y: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                },
                marker: { show: true }
            }"""


# Replace Heatmap Chart configs
old_heatmap = """            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.5,
                    radius: 4,
                    useFillColorAsStroke: false,
                    colorScale: {
                        ranges: [
                            { from: 0, to: 0, color: '#1e1e24', name: 'Trống' },
                            { from: 1, to: 10, color: '#4a1216', name: 'Thấp' },
                            { from: 11, to: 50, color: '#911118', name: 'Trung bình' },
                            { from: 51, to: 1000, color: '#e50914', name: 'Cao' }
                        ]
                    }
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 1, colors: ['#2e2e33'] },
            xaxis: {
                categories: ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
                labels: { style: { colors: '#a1a1aa' } }
            },
            yaxis: {
                labels: { style: { colors: '#a1a1aa' } }
            },
            grid: { show: false },
            tooltip: { theme: 'dark' }"""

new_heatmap = """            plotOptions: {
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
            }"""


# Replace Top Movies HTML
old_movies = """            html += `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card" style="background-image: url('${escapeHtml(poster)}')">
                    <div class="movie-tag bg-danger border-0">${revenue}</div>
                    <div class="movie-info">
                        <div class="movie-card-title text-truncate" title="${escapeHtml(movie.title)}">${escapeHtml(movie.title)}</div>
                        <div class="movie-meta">${tickets} vé bán ra</div>
                    </div>
                </div>
            </div>`;"""

new_movies = """            html += `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card" style="background-image: url('${escapeHtml(poster)}')">
                    <div class="movie-tag gradient-red">${revenue}</div>
                    <div class="movie-info">
                        <div class="movie-card-title text-truncate" title="${escapeHtml(movie.title)}">${escapeHtml(movie.title)}</div>
                        <div class="movie-meta"><i class="bi bi-ticket-perforated-fill"></i> ${tickets} vé bán ra</div>
                    </div>
                </div>
            </div>`;"""


if old_revenue in content:
    content = content.replace(old_revenue, new_revenue)
    print("Revenue chart replaced.")
else:
    print("Could not find old_revenue")

if old_heatmap in content:
    content = content.replace(old_heatmap, new_heatmap)
    print("Heatmap chart replaced.")
else:
    print("Could not find old_heatmap")

if old_movies in content:
    content = content.replace(old_movies, new_movies)
    print("Top Movies HTML replaced.")
else:
    print("Could not find old_movies")

with open(path, "w", encoding="utf-8") as f:
    f.write(content)
