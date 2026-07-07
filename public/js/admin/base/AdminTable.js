/**
 * ═══════════════════════════════════════════════════════════════════════════
 * AdminTable - Reusable Table Component
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Handles common table operations:
 * - Data loading & rendering
 * - Sorting
 * - Filtering  
 * - Pagination
 * - Row selection
 * - Actions (edit, delete, view)
 * 
 * @version 1.0.0
 */

import AdminBasePage from './AdminBasePage.js';

class AdminTable {
    constructor(page, config = {}) {
        this.page = page; // Reference to parent AdminBasePage instance
        this.config = {
            tableSelector: '.admin-table',
            paginationSelector: '.admin-pagination',
            perPage: 10,
            sortable: true,
            selectable: false,
            actions: ['view', 'edit', 'delete'],
            ...config
        };

        this.state = {
            data: [],
            filteredData: [],
            currentPage: 1,
            sortColumn: null,
            sortDirection: 'asc',
            selectedRows: new Set()
        };

        this.elements = {};
        this.init();
    }

    /**
     * Initialize table
     */
    init() {
        this.cacheElements();
        this.attachEventListeners();
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.elements.table = document.querySelector(this.config.tableSelector);
        this.elements.tbody = this.elements.table?.querySelector('tbody');
        this.elements.pagination = document.querySelector(this.config.paginationSelector);
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        if (!this.elements.table) return;

        // Sort headers
        if (this.config.sortable) {
            this.elements.table.querySelectorAll('th[data-sort]').forEach(th => {
                th.addEventListener('click', () => this.handleSort(th.dataset.sort));
            });
        }

        // Row selection
        if (this.config.selectable) {
            this.elements.table.addEventListener('change', (e) => {
                if (e.target.matches('input[type="checkbox"][data-row-id]')) {
                    this.handleRowSelect(e.target);
                }
            });
        }

        // Row actions
        this.elements.table.addEventListener('click', (e) => {
            const actionBtn = e.target.closest('[data-action]');
            if (actionBtn) {
                this.handleAction(actionBtn.dataset.action, actionBtn.dataset.id);
            }
        });
    }

    /**
     * Load data
     */
    async loadData(endpoint) {
        try {
            const response = await this.page.apiRequest(endpoint);
            this.state.data = response.data || [];
            this.state.filteredData = [...this.state.data];
            this.render();
            return this.state.data;
        } catch (error) {
            this.page.handleError('Failed to load table data', error);
            throw error;
        }
    }

    /**
     * Set data directly
     */
    setData(data) {
        this.state.data = data;
        this.state.filteredData = [...data];
        this.render();
    }

    /**
     * Filter data
     */
    filter(predicate) {
        this.state.filteredData = this.state.data.filter(predicate);
        this.state.currentPage = 1;
        this.render();
    }

    /**
     * Sort data
     */
    handleSort(column) {
        if (this.state.sortColumn === column) {
            this.state.sortDirection = this.state.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.state.sortColumn = column;
            this.state.sortDirection = 'asc';
        }

        this.state.filteredData.sort((a, b) => {
            const aVal = this.getNestedValue(a, column);
            const bVal = this.getNestedValue(b, column);
            
            if (aVal < bVal) return this.state.sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return this.state.sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        this.render();
        this.updateSortIndicators();
    }

    /**
     * Get nested object value by path
     */
    getNestedValue(obj, path) {
        return path.split('.').reduce((current, key) => current?.[key], obj);
    }

    /**
     * Update sort indicators in table headers
     */
    updateSortIndicators() {
        this.elements.table.querySelectorAll('th[data-sort]').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
            if (th.dataset.sort === this.state.sortColumn) {
                th.classList.add(`sort-${this.state.sortDirection}`);
            }
        });
    }

    /**
     * Handle row selection
     */
    handleRowSelect(checkbox) {
        const rowId = checkbox.dataset.rowId;
        if (checkbox.checked) {
            this.state.selectedRows.add(rowId);
        } else {
            this.state.selectedRows.delete(rowId);
        }
        
        this.onSelectionChange(this.state.selectedRows);
    }

    /**
     * Get selected rows
     */
    getSelectedRows() {
        return Array.from(this.state.selectedRows);
    }

    /**
     * Clear selection
     */
    clearSelection() {
        this.state.selectedRows.clear();
        this.elements.table.querySelectorAll('input[type="checkbox"][data-row-id]').forEach(cb => {
            cb.checked = false;
        });
    }

    /**
     * Handle actions (view, edit, delete)
     */
    async handleAction(action, id) {
        const item = this.state.data.find(d => d.id == id);
        
        switch (action) {
            case 'view':
                this.onView(item);
                break;
            case 'edit':
                this.onEdit(item);
                break;
            case 'delete':
                await this.handleDelete(item);
                break;
            default:
                this.onCustomAction(action, item);
        }
    }

    /**
     * Handle delete action
     */
    async handleDelete(item) {
        const confirmed = await this.page.confirm({
            title: 'Confirm Delete',
            message: `Are you sure you want to delete this item?`,
            confirmText: 'Delete',
            type: 'danger'
        });

        if (confirmed) {
            try {
                await this.onDelete(item);
                this.page.showToast('Item deleted successfully', 'success');
                this.removeRow(item.id);
            } catch (error) {
                this.page.handleError('Failed to delete item', error);
            }
        }
    }

    /**
     * Remove row from table
     */
    removeRow(id) {
        this.state.data = this.state.data.filter(d => d.id != id);
        this.state.filteredData = this.state.filteredData.filter(d => d.id != id);
        this.render();
    }

    /**
     * Add or update row
     */
    upsertRow(item) {
        const index = this.state.data.findIndex(d => d.id === item.id);
        if (index >= 0) {
            this.state.data[index] = item;
        } else {
            this.state.data.unshift(item);
        }
        this.state.filteredData = [...this.state.data];
        this.render();
    }

    /**
     * Render table
     */
    render() {
        if (!this.elements.tbody) return;

        const start = (this.state.currentPage - 1) * this.config.perPage;
        const end = start + this.config.perPage;
        const pageData = this.state.filteredData.slice(start, end);

        if (pageData.length === 0) {
            this.renderEmpty();
        } else {
            this.elements.tbody.innerHTML = pageData.map(item => 
                this.renderRow(item)
            ).join('');
        }

        this.renderPagination();
    }

    /**
     * Render single row - override in subclass
     */
    renderRow(item) {
        return `<tr><td colspan="100%">Override renderRow() method</td></tr>`;
    }

    /**
     * Render empty state
     */
    renderEmpty() {
        this.elements.tbody.innerHTML = `
            <tr class="admin-table-empty">
                <td colspan="100%">
                    <div class="admin-empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No data available</p>
                    </div>
                </td>
            </tr>
        `;
    }

    /**
     * Render pagination
     */
    renderPagination() {
        if (!this.elements.pagination) return;

        const totalPages = Math.ceil(this.state.filteredData.length / this.config.perPage);
        const currentPage = this.state.currentPage;

        this.elements.pagination.innerHTML = `
            <div class="admin-pagination-info">
                Showing ${(currentPage - 1) * this.config.perPage + 1} to 
                ${Math.min(currentPage * this.config.perPage, this.state.filteredData.length)} 
                of ${this.state.filteredData.length} entries
            </div>
            <div class="admin-pagination-controls">
                ${this.renderPaginationButtons(currentPage, totalPages)}
            </div>
        `;

        // Attach pagination click handlers
        this.elements.pagination.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => this.goToPage(parseInt(btn.dataset.page)));
        });
    }

    /**
     * Render pagination buttons
     */
    renderPaginationButtons(current, total) {
        let buttons = [];
        
        buttons.push(`
            <button class="admin-pagination-btn ${current === 1 ? 'disabled' : ''}" 
                    data-page="${current - 1}" ${current === 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
        `);

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                buttons.push(`
                    <button class="admin-pagination-btn ${i === current ? 'active' : ''}" 
                            data-page="${i}">
                        ${i}
                    </button>
                `);
            } else if (i === current - 2 || i === current + 2) {
                buttons.push(`<span class="admin-pagination-ellipsis">...</span>`);
            }
        }

        buttons.push(`
            <button class="admin-pagination-btn ${current === total ? 'disabled' : ''}" 
                    data-page="${current + 1}" ${current === total ? 'disabled' : ''}>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        `);

        return buttons.join('');
    }

    /**
     * Go to specific page
     */
    goToPage(page) {
        const totalPages = Math.ceil(this.state.filteredData.length / this.config.perPage);
        if (page < 1 || page > totalPages) return;
        
        this.state.currentPage = page;
        this.render();
    }

    // Event callbacks - override in implementation
    onView(item) {}
    onEdit(item) {}
    async onDelete(item) {}
    onCustomAction(action, item) {}
    onSelectionChange(selectedIds) {}
}

export default AdminTable;