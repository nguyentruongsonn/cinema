import sys

css_path = 'public/css/admin/admin-common.css'
css_fix = """
/* ── UI FIX FOR FILTER BAR (USER REPORTED BUG) ───────────────────────── */
.admin-filter-container .input-group {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: stretch;
}

.admin-filter-container .input-group .admin-filter-input {
    flex: 1 1 auto !important;
    width: 1% !important; /* Bootstrap standard to share flex space */
    margin-bottom: 0 !important;
    display: block !important; /* Overwrite mobile hidden rule if accidentally triggered */
}

/* Force standard button display in input-group */
.admin-filter-container .input-group .admin-filter-btn {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    margin-left: -1px !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
    width: auto !important;
}

.admin-filter-container .input-group .search-input-rounded-left {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
}

/* Fix vertical alignment of the filter bar wrapper */
.admin-filter-container .d-flex {
    align-items: center !important;
}

.admin-filter-container h5 {
    display: block !important;
    margin-bottom: 0 !important;
    line-height: 1.5;
}

/* Make sure the form doesn't wrap unnecessarily */
.admin-filter-container form {
    min-width: 250px;
    max-width: 400px;
}
"""

with open(css_path, 'a', encoding='utf-8') as f:
    f.write(css_fix)
print("Applied filter UI fix.")
