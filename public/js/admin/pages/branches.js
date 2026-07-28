/**
 * Branches Management - branches.js
 * SPA Architecture
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    const els = {
        tableBody: document.getElementById('branchesTableBody'),
        pagination: document.getElementById('paginationContainer'),

        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        statusFilter: document.getElementById('statusFilter'),

        btnCreate: document.getElementById('btnCreateBranch'),
        modalEl: document.getElementById('branchModal'),
        form: document.getElementById('branchForm'),
        modalLabel: document.getElementById('branchModalLabel'),

        formMethod: document.getElementById('formMethod'),
        idInput: document.getElementById('branchIdInput'),

        name: document.getElementById('branchName'),
        isActive: document.getElementById('branchIsActive'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = 'all';

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1) {
        try {
            // Skeleton loading is now handled in HTML blade template
            // els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;

            const url = new URL(window.location.origin + '/api/v1/admin/branches');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);

            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'branches:list' });
            if (res && res.ok) {
                const data = await res.json();
                renderTable(data.data, data.pagination?.from || data.from);
                renderPagination(data.pagination || data);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(branches, startIndex) {
        if (!branches || branches.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy chi nhánh nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        branches.forEach((branch, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const dCreated = new Date(branch.created_at);
            const dUpdated = new Date(branch.updated_at);

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td class="fw-medium text-white">${escapeHtml(branch.name)}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0 admin-toggle-pointer" type="checkbox" role="switch"
                            data-id="${escapeHtml(branch.id)}" ${branch.is_active ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-light small">${dCreated.toLocaleDateString('vi-VN')}</td>
                <td class="text-light small">${dUpdated.toLocaleDateString('vi-VN')}</td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-branch admin-table-action-edit"

                            data-branch='${escapeHtml(JSON.stringify(branch))}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-branch admin-table-action-delete"
                            data-id="${escapeHtml(branch.id)}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        window.AdminCore.renderAdminPagination(els.pagination, meta, (page) => {
            currentPage = page; loadData(page);
        });
    }

    /* ── Forms & Interactions ──────────────────────────────────────── */
    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
    }

    if (els.btnCreate) {
        els.btnCreate.addEventListener('click', () => {
            resetForm();
            els.modalLabel.textContent = 'Tạo chi nhánh mới';
            getModalInstance()?.show();
        });
    }

    els.tableBody.addEventListener('click', async (e) => {
        // Edit
        const btnEdit = e.target.closest('.btn-edit-branch');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';

            const branch = JSON.parse(btnEdit.dataset.branch);
            els.idInput.value = branch.id;
            els.modalLabel.textContent = 'Cập nhật chi nhánh';

            els.name.value = branch.name || '';
            els.isActive.checked = branch.is_active === 1 || branch.is_active === true;

            getModalInstance()?.show();
            return;
        }

        // Delete
        const btnDel = e.target.closest('.btn-delete-branch');
        if (btnDel) {
            if(!confirm('Bạn có chắc muốn xóa chi nhánh này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/branches/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa thành công', 'success');
                    loadData(currentPage);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) {}
            return;
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-active-btn');
        if (toggle) {
            const id = toggle.getAttribute('data-id');
            const isActive = toggle.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/branches/${id}/toggle-active`, { method: 'POST' });
                if (!res) throw new Error('Không thể kết nối đến máy chủ.');
                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.message || 'Cập nhật trạng thái thất bại.');
                }
                const result = await res.json();
                window.showAdminToast?.(result.message || 'Cập nhật trạng thái thành công', 'success');
                loadData(currentPage);
            } catch (error) {
                window.showAdminToast?.(error.message || 'Cập nhật trạng thái thất bại', 'error');
                toggle.checked = !isActive;
            }
        }
    });

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.formMethod.value === 'PUT';
            const id = els.idInput.value;
            const url = isEdit ? `/api/v1/admin/branches/${id}` : `/api/v1/admin/branches`;

            const formData = new FormData(els.form);
            const data = Object.fromEntries(formData.entries());
            data.is_active = els.isActive.checked ? 1 : 0;

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(data)
                });

                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật thành công!' : 'Tạo chi nhánh thành công!', 'success');
                    loadData(currentPage);
                } else {
                    const errData = await res.json();
                    alert('Lỗi: ' + JSON.stringify(errData.errors || errData.message));
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
            currentStatus = els.statusFilter?.value || 'all';
            currentPage = 1;
            loadData(currentPage);
        });

        els.statusFilter?.addEventListener('change', () => {
            currentStatus = els.statusFilter.value || 'all';
            currentPage = 1;
            loadData(currentPage);
        });
    }

    /* ── Init ──────────────────────────────────────────────────────── */
    window.onAdminPageLoad(() => {
        loadData(1);
    });

})();
