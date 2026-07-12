import sys

css_path = 'public/css/admin/dashboard-redesign.css'
css_to_add = """
/* ── ADDITIONAL CONTAINERS BACKGROUND ───────────────────────────────── */
.admin-table-container {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01)) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
"""

with open(css_path, 'a', encoding='utf-8') as f:
    f.write(css_to_add)
print("Updated CSS with admin-table-container.")
