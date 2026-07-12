import sys

js_path = 'public/js/admin/pages/dashboard.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

old_code = """        charts.revenue.updateOptions({ xaxis: { categories } });
        charts.revenue.updateSeries([{ name: 'Doanh thu', data }]);"""

new_code = """        let opts = { xaxis: { categories } };
        
        // FIX: Area/Line charts won't render a single data point without markers. 
        // If there's only 1 point, force a marker to show up.
        if (data.length === 1) {
            opts.markers = { size: 6, strokeWidth: 2, hover: { size: 8 } };
        } else {
            opts.markers = { size: 0 };
        }
        
        charts.revenue.updateOptions(opts);
        charts.revenue.updateSeries([{ name: 'Doanh thu', data }]);"""

js_content = js_content.replace(old_code, new_code)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)
print("Updated dashboard.js revenue chart fix.")
