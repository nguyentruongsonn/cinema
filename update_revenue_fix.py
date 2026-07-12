import sys

js_path = 'public/js/admin/pages/revenue.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

old_code = """        charts.trend.updateOptions({ xaxis: { categories } });
        charts.trend.updateSeries(["""

new_code = """        let opts = { xaxis: { categories } };
        if (revenues.length === 1) {
            opts.markers = { size: 6, strokeWidth: 2, hover: { size: 8 } };
        } else {
            opts.markers = { size: 0 };
        }
        charts.trend.updateOptions(opts);
        charts.trend.updateSeries(["""

js_content = js_content.replace(old_code, new_code)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)
print("Updated revenue.js trend chart fix.")
