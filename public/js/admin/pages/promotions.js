/**
 * Promotions Management - promotions.js
 * Simple SPA Architecture (following branches.js pattern)
 */
(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('promotionsTableBody'),
        pagination: document.getElementById('paginationContainer'),

        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        categoryFilter: document.getElementById('categoryFilter'),
        statusFilter: document.getElementById('statusFilter'),

        btnCreate: document.getElementById('btnCreatePromotion'),
        modalEl: document.getElementById('promotionModal'),
        form: document.getElementById('promotionForm'),
        modalLabel: document.getElementById('promotionModalLabel'),

        formMethod: document.getElementById('formMethod'),
        idInput: document.getElementById('promotionIdInput'),

        code: document.getElementById('promotionCode'),
        name: document.getElementById('promotionName'),
        category: document.getElementById('promotionCategory'),
        description: document.getElementById('promotionDescription'),
        discountType: document.getElementById('promotionDiscountType'),
        discountValue: document.getElementById('promotionDiscountValue'),
        minOrderValue: document.getElementById('promotionMinOrderValue'),
        maxDiscountAmount: document.getElementById('promotionMaxDiscountAmount'),
        startDate: document.getElementById('promotionStartDate'),
        endDate: document.getElementById('promotionEndDate'),
        usageLimit: document.getElementById('promotionUsageLimit'),
        status: document.getElementById('promotionStatus'),
        statusLabel: document.getElementById('promotionStatusLabel'),
        discountValueHint: document.getElementById('discountValueHint'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = 'all';
    let currentCategory = 'all';

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1) {
        try {
            // Skeleton loading is now handled in HTML blade template
            // els.tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;

            const url = new URL(window.location.origin + '/api/v1/admin/promotions');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);
            if (currentCategory !== 'all') url.searchParams.append('category', currentCategory);

            const res = await window.AdminCore.apiFetch(url.toString());
            if (res && res.ok) {
                const json = await res.json();
                renderTable(json.data, json.pagination?.from || json.from);
                renderPagination(json.pagination || json);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(promotions, startIndex) {
        if (!promotions || promotions.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy mã giảm giá nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        promotions.forEach((promo, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const categoryBadge = {
                'ticket': '<span class="badge-category badge-category-ticket">Vé phim</span>',
                'food': '<span class="badge-category badge-category-food">Đồ ăn</span>',
                'combo': '<span class="badge-category badge-category-combo">Combo</span>',
                'all': '<span class="badge-category badge-category-all">Tất cả</span>'
            }[promo.category] || promo.category;

            const discountText = promo.discount_type === 'percent'
                ? `${promo.discount_value}%`
                : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(promo.discount_value);

            // Format date range
            const formatDate = (dateString) => {
                if (!dateString) return '';
                const date = new Date(dateString);
                return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
            };

            const dateRangeText = promo.start_date || promo.end_date
                ? `${formatDate(promo.start_date) || '?'}<br>${formatDate(promo.end_date) || '?'}`
                : '<span class="text-white-50 fst-italic">Vô thời hạn</span>';

            // Format minimum order value
            const minOrderText = promo.min_order_value && promo.min_order_value > 0
                ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(promo.min_order_value)
                : '<span class="text-white-50">Không</span>';

            const usageText = promo.usage_limit
                ? `${promo.usage_count || 0}/${promo.usage_limit}`
                : `${promo.usage_count || 0}`;

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td><span class="promotion-code">${promo.code}</span></td>
                <td>
                    <div class="fw-medium text-white">${promo.name}</div>
                    ${promo.description ? `<small class="text-white-50">${promo.description.substring(0, 50)}${promo.description.length > 50 ? '...' : ''}</small>` : ''}
                </td>
                <td class="text-center" style="white-space: nowrap;">${categoryBadge}</td>
                <td class="text-center text-warning fw-medium">${discountText}</td>
                <td class="text-center text-light small" style="line-height: 1.3;">${dateRangeText}</td>
                <td class="text-center text-light small">${minOrderText}</td>
                <td class="text-center text-light small">${usageText}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0" type="checkbox" role="switch"
                            data-id="${promo.id}" ${promo.status ? 'checked' : ''} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-promotion"
                            style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                            data-promotion='${JSON.stringify(promo).replace(/'/g, "&#39;")}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-promotion"
                            style="color:#ef4444; background:rgba(239,68,68,0.1);"
                            data-id="${promo.id}"
                            data-used="${promo.usage_count || 0}"
                            title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        if (!meta || meta.last_page <= 1) {
            els.pagination.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination pagination-sm m-0">';
        if (meta.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page - 1}">&laquo;</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">&laquo;</span></li>`;
        }

        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
        }

        if (meta.current_page < meta.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page + 1}">&raquo;</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">&raquo;</span></li>`;
        }
        html += '</ul>';

        els.pagination.innerHTML = html;
        els.pagination.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                currentPage = parseInt(a.getAttribute('data-page'));
                loadData(currentPage);
            });
        });
    }

    /* ── Forms & Interactions ──────────────────────────────────────── */
    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        els.status.checked = true;
        els.statusLabel.textContent = 'Đang hoạt động';
        updateDiscountTypeUI();
    }

    function updateDiscountTypeUI() {
        const type = els.discountType.value;
        if (type === 'percent') {
            els.maxDiscountAmount.disabled = false;
            els.discountValueHint.textContent = 'Nhập % giảm (VD: 20 cho 20%)';
        } else {
            els.maxDiscountAmount.disabled = true;
            els.maxDiscountAmount.value = '';
            els.discountValueHint.textContent = 'Nhập số tiền giảm (VD: 50000)';
        }
    }

    function formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    if (els.btnCreate) {
        els.btnCreate.addEventListener('click', () => {
            resetForm();
            els.modalLabel.innerHTML = '<i class="bi bi-tag me-2"></i>Tạo mã giảm giá mới';
            getModalInstance()?.show();
        });
    }

    if (els.discountType) {
        els.discountType.addEventListener('change', updateDiscountTypeUI);
    }

    if (els.status && els.statusLabel) {
        els.status.addEventListener('change', () => {
            els.statusLabel.textContent = els.status.checked ? 'Đang hoạt động' : 'Tạm dừng';
        });
    }

    els.tableBody.addEventListener('click', async (e) => {
        // Edit
        const btnEdit = e.target.closest('.btn-edit-promotion');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';

            const promo = JSON.parse(btnEdit.dataset.promotion);
            els.idInput.value = promo.id;
            els.modalLabel.innerHTML = '<i class="bi bi-tag me-2"></i>Cập nhật mã giảm giá';

            els.code.value = promo.code || '';
            els.name.value = promo.name || '';
            els.category.value = promo.category || '';
            els.description.value = promo.description || '';
            els.discountType.value = promo.discount_type || 'percent';
            els.discountValue.value = promo.discount_value || '';
            els.minOrderValue.value = promo.min_order_value || '';
            els.maxDiscountAmount.value = promo.max_discount_amount || '';
            els.usageLimit.value = promo.usage_limit || '';
            els.startDate.value = formatDateTimeLocal(promo.start_date);
            els.endDate.value = formatDateTimeLocal(promo.end_date);
            els.status.checked = promo.status === 1 || promo.status === true;
            els.statusLabel.textContent = els.status.checked ? 'Đang hoạt động' : 'Tạm dừng';

            updateDiscountTypeUI();
            getModalInstance()?.show();
            return;
        }

        // Delete
        const btnDel = e.target.closest('.btn-delete-promotion');
        if (btnDel) {
            const usedCount = parseInt(btnDel.dataset.used);
            if (usedCount > 0) {
                alert('Không thể xóa mã giảm giá đã được sử dụng!');
                return;
            }
            if (!confirm('Bạn có chắc muốn xóa mã giảm giá này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/promotions/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa mã giảm giá thành công', 'success');
                    loadData(currentPage);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) {
                window.showAdminToast?.('Xóa thất bại', 'error');
            }
            return;
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-active-btn');
        if (!toggle) return;

        const id = toggle.getAttribute('data-id');
        const isActive = toggle.checked;
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/promotions/${id}/toggle-active`, { method: 'POST' });
            if (!res || !res.ok) throw new Error();
            window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
            loadData(currentPage);
        } catch (error) {
            window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
            toggle.checked = !isActive;
        }
    });

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const isEdit = els.formMethod.value === 'PUT';
            const id = els.idInput.value;
            const url = isEdit ? `/api/v1/admin/promotions/${id}` : '/api/v1/admin/promotions';

            const formData = {
                code: els.code.value.toUpperCase().trim(),
                name: els.name.value.trim(),
                category: els.category.value,
                description: els.description.value.trim() || null,
                discount_type: els.discountType.value,
                discount_value: parseFloat(els.discountValue.value) || 0,
                min_order_value: els.minOrderValue.value ? parseFloat(els.minOrderValue.value) : null,
                max_discount_amount: els.maxDiscountAmount.value ? parseFloat(els.maxDiscountAmount.value) : null,
                start_date: els.startDate.value || null,
                end_date: els.endDate.value || null,
                usage_limit: els.usageLimit.value ? parseInt(els.usageLimit.value) : null,
                status: els.status.checked
            };

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật mã giảm giá thành công!' : 'Tạo mã giảm giá thành công!', 'success');
                    loadData(currentPage);
                } else {
                    const errData = await res.json();
                    alert('Dữ liệu không hợp lệ: ' + JSON.stringify(errData.errors || errData.message));
                }
            } catch (error) {
                console.error('Submit form error', error);
            }
        });
    }

    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            currentSearch = els.searchInput.value.trim();
            currentStatus = els.statusFilter.value;
            currentCategory = els.categoryFilter.value;
            currentPage = 1;
            loadData(currentPage);
        });

        if (els.statusFilter) {
            els.statusFilter.addEventListener('change', () => {
                els.searchForm.dispatchEvent(new Event('submit'));
            });
        }

        if (els.categoryFilter) {
            els.categoryFilter.addEventListener('change', () => {
                els.searchForm.dispatchEvent(new Event('submit'));
            });
        }
    }

    /* ── Load Categories ────────────────────────────────────────────── */
    async function loadCategories() {
        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/promotions/categories');
            if (res && res.ok) {
                const json = await res.json();
                const categories = json.data || [];

                // Populate filter dropdown
                if (els.categoryFilter) {
                    populateCategoryOptions(categories, els.categoryFilter, true);
                }

                // Populate modal dropdown
                if (els.category) {
                    populateCategoryOptions(categories, els.category, false);
                }
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    function populateCategoryOptions(categories, selectElement, includeAll = true) {
        const categoryLabels = {
            'ticket': 'Vé phim',
            'food': 'Đồ ăn',
            'combo': 'Combo',
            'all': 'Tất cả'
        };

        const currentValue = selectElement.value;
        selectElement.innerHTML = '';

        if (includeAll) {
            const allOption = document.createElement('option');
            allOption.value = 'all';
            allOption.textContent = 'Tất cả loại';
            selectElement.appendChild(allOption);
        }

        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = categoryLabels[cat] || cat;
            selectElement.appendChild(option);
        });

        // Restore previous value if it exists
        if (currentValue && [...selectElement.options].some(opt => opt.value === currentValue)) {
            selectElement.value = currentValue;
        }
    }

    /* ── Init ──────────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        loadCategories();
        loadData(1);
    });

})();
