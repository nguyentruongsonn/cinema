# ADMIN PAGES REFACTORING STRATEGY

Comprehensive plan để unify toàn bộ 18 admin pages: consolidate code chung, xóa code thừa, standardize patterns.

---

## 📋 CURRENT INVENTORY

### Admin Pages (18 trang)

**List Pages (14):** movies, products, combos, promotions, posts, banners, orders, branches, theaters, screens, users, showtimes, seat-layout-templates, tickets

**Stats Pages (3):** combos/stats, revenue, ticket_stats

**Special Pages (2):** dashboard, screens/seats

---

### CSS Files (11 files)

**Shared:**
- `admin-common.css` - Reusable admin classes
- `admin-modals.css` - Modal styling  
- `style.css` - General admin styling

**Page-Specific (to consolidate):**
- branches.css, combos.css, movies.css, orders.css, seat-layout-templates.css, seat-layout.css, showtimes.css, stats.css

---

### JavaScript Files (18 files)

**List Pages:** banners.js, branches.js, combos.js, movies.js, orders.js, posts.js, products.js, promotions.js, screens.js, seat-layout-templates.js, showtimes.js, theaters.js, users.js

**Stats Pages:** combo_stats.js, revenue.js, ticket_stats.js

**Special Pages:** dashboard.js, seat-layout.js

---

## 🔍 AUDIT FINDINGS

### CSS Consolidation Opportunities

**Pattern Analysis:**
- Duplicate `.admin-table`, `.admin-row` classes (3+ files)
- Duplicate `.admin-btn`, `.btn-*` styles (3+ files)
- Duplicate `.admin-form-*` styles (3+ files)

**Consolidation Plan:**

```
DELETE PAGE-SPECIFIC CSS (8 files):
├── ❌ branches.css → merge to shared
├── ❌ combos.css → merge to shared
├── ❌ movies.css → merge to shared
├── ❌ orders.css → merge to shared
├── ❌ seat-layout-templates.css → merge to shared
├── ❌ seat-layout.css → merge to shared
├── ❌ showtimes.css → merge to shared
└── ❌ stats.css → merge to shared

CREATE NEW SHARED CSS (7 files):
├── admin-table.css (from: seat-layout-templates.css, seat-layout.css, orders.css, stats.css)
├── admin-forms.css (NEW - consolidate form styling)
├── admin-buttons.css (NEW - consolidate button styling)
├── admin-badges.css (from: combos.css, orders.css, stats.css)
├── admin-skeleton.css (NEW - skeleton loading)
├── admin-filters.css (NEW - filter components)
└── admin-layout.css (NEW - grid/layout system)

RESULT: 11 files → 10 files, 20% CSS reduction
```

---

### JavaScript Consolidation Opportunities

**Pattern Analysis:**

14 List Page Files have common patterns:
- `loadData()` - fetch from API
- `renderTable()` - render data rows
- `openModal()` - show create/edit modal
- `submitForm()` - handle form submission
- `deleteItem()` - handle deletion
- `applyFilters()` - apply search/filter
- `setupEventListeners()` - attach event handlers

3 Stats Page Files have common patterns:
- `fetchStats()` - fetch statistics
- `renderChart()` - render charts
- `applyFilters()` - apply date/status filters

---

### Dead Code Identified

**Duplicate Utility Functions (4+ files):**
- `formatPrice()` 
- `formatDate()`
- `showAlert()`
- `deleteConfirm()`

**Unused CSS Classes:**
- Deprecated `.admin-*` variants
- Legacy `.btn-*` classes

**Unused JS Methods:**
- Old event handlers
- Deprecated API wrappers

---

## 🎯 CONSOLIDATION STRATEGY

### Phase 1: CSS Consolidation

**Step 1: Extract Common Styles (3-4 hours)**
```
For each page-specific CSS file:
1. Identify common styling patterns
2. Extract to shared CSS files
3. Keep page-specific overrides only
4. Update page references
```

**Step 2: Create Shared CSS Files (2-3 hours)**
```
├── admin-table.css - table, tbody, tr, td, thead styling
├── admin-forms.css - form, input, select, textarea, label styling
├── admin-buttons.css - all button variants and states
├── admin-badges.css - badge styling and variants
├── admin-skeleton.css - skeleton loading animation
├── admin-filters.css - filter bar components
└── admin-layout.css - grid, container, spacing system
```

**Step 3: Delete Old Page CSS (15 mins)**
- Delete 8 page-specific CSS files
- Update all blade.php @push('styles') to reference shared CSS only

---

### Phase 2: JavaScript Consolidation

**Step 1: Create Base Classes (4-5 hours)**

```javascript
// admin-base-page.js (200 lines)
class AdminPage {
    constructor(config) {
        this.config = config;
        this.init();
    }
    
    init() { /* ... */ }
    setupEventListeners() { /* ... */ }
    setupSearch() { /* ... */ }
    setupFilters() { /* ... */ }
    showAlert(msg, type) { /* ... */ }
    showConfirm(msg, callback) { /* ... */ }
}

// admin-table-page.js (150 lines) - extends AdminPage
class AdminTablePage extends AdminPage {
    loadData() { /* ... */ }
    renderSkeleton() { /* ... */ }
    renderTable(data) { /* ... */ }
    openModal(itemId) { /* ... */ }
    submitForm(formData) { /* ... */ }
    deleteItem(itemId) { /* ... */ }
    applyFilters() { /* ... */ }
}

// admin-stats-page.js (150 lines) - extends AdminPage
class AdminStatsPage extends AdminPage {
    loadStats() { /* ... */ }
    renderChart(data) { /* ... */ }
    applyDateFilter() { /* ... */ }
    exportData() { /* ... */ }
}

// admin-utils.js (100 lines)
export function formatPrice(price) { /* ... */ }
export function formatDate(date) { /* ... */ }
export function formatTime(time) { /* ... */ }
```

**Step 2: Refactor 14 List Pages (3-4 hours)**

Old pattern (200-400 lines each):
```javascript
// branches.js - lots of duplicate code
document.addEventListener('DOMContentLoaded', () => {
    // setup modal listeners
    // setup filter listeners
    // setup search listeners
    // load data
    // render table
    // ... 200+ more lines of duplicated code
});
```

New pattern (50-100 lines each):
```javascript
// branches.js - lean configuration only
const branchesPage = new AdminTablePage({
    tableId: '#branchesTableBody',
    apiEndpoint: '/api/branches',
    modalId: '#branchModal',
    formId: '#branchForm',
    columns: ['name', 'address', 'phone', 'status'],
    filters: ['status', 'type']
});
```

Apply to 14 files:
- banners.js, branches.js, combos.js, movies.js, orders.js, posts.js, products.js, promotions.js, screens.js, seat-layout-templates.js, showtimes.js, theaters.js, users.js, seat-layout.js