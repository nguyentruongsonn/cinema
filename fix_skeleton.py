import sys

js_path = 'public/js/admin/pages/revenue.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

# Fix hideLoading selector
old_selector = "document.querySelectorAll('.skeleton').forEach"
new_selector = "document.querySelectorAll('.skeleton, .admin-skeleton').forEach"
js_content = js_content.replace(old_selector, new_selector)

# Fix showLoading to add correct classes
old_show = """el.classList.add('skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');"""
new_show = """el.classList.add('admin-skeleton');
            if (!el.id.startsWith('chart')) el.classList.add('admin-skeleton-text');"""
js_content = js_content.replace(old_show, new_show)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)
print("Fixed skeleton hiding issue in revenue.js")
