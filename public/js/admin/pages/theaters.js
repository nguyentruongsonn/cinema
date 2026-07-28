/**
 * Theaters Management - theaters.js
 * SPA Architecture
 */
(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('theatersTableBody'),
        pagination: document.getElementById('paginationContainer'),

        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        branchFilter: document.getElementById('branchFilter'),
        statusFilter: document.getElementById('statusFilter'),

        btnCreate: document.getElementById('btnCreateTheater'),
        modalEl: document.getElementById('theaterModal'),
        form: document.getElementById('theaterForm'),
        modalLabel: document.getElementById('theaterModalLabel'),

        formMethod: document.getElementById('formMethod'),
        idInput: document.getElementById('theaterIdInput'),

        // Form inputs
        name: document.getElementById('theaterName'),
        branchId: document.getElementById('theaterBranch'),
        address: document.getElementById('theaterAddress'),
        description: document.getElementById('theaterDescription'),
        isActive: document.getElementById('theaterStatus'),
        phone: document.getElementById('theaterPhone'),
        email: document.getElementById('theaterEmail'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentBranchId = '';
    let currentStatus = 'all';

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch Prerequisites ───────────────────────────────────────── */
    async function fetchPrerequisites() {
        try {
            // Fetch branches for dropdown
            const res = await window.AdminCore.apiFetch('/api/v1/admin/branches?options=1', { cacheTtl: 300000 });
            if (res && res.ok) {
                const data = await res.json();
                const branches = data.data || [];

                const formFragment = document.createDocumentFragment();
                const formEmptyOption = document.createElement('option');
                formEmptyOption.value = '';
                formEmptyOption.textContent = '-- Chọn chi nhánh --';
                formFragment.appendChild(formEmptyOption);

                const filterFragment = document.createDocumentFragment();
                const filterEmptyOption = document.createElement('option');
                filterEmptyOption.value = '';
                filterEmptyOption.textContent = 'Tất cả chi nhánh';
                filterFragment.appendChild(filterEmptyOption);

                branches.forEach(b => {
                    const formOption = document.createElement('option');
                    formOption.value = String(b.id);
                    formOption.textContent = String(b.name ?? '');
                    formFragment.appendChild(formOption);

                    const filterOption = formOption.cloneNode(true);
                    filterFragment.appendChild(filterOption);
                });

                els.branchId.replaceChildren(formFragment);
                els.branchFilter?.replaceChildren(filterFragment);
            }
        } catch (error) {
            console.error('Error fetching branches:', error);
        }
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1) {
        try {
            // Skeleton loading is now handled in HTML blade template
            // els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;

            const url = new URL(window.location.origin + '/api/v1/admin/theaters');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentBranchId) url.searchParams.append('branch_id', currentBranchId);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);

            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'theaters:list' });
            if (res && res.ok) {
                const data = await res.json();
                renderTable(data.data, data.from);
                renderPagination(data);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(theaters, startIndex) {
        if (!theaters || theaters.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy rạp nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        theaters.forEach((theater, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="fw-medium text-white">${theater.name}</div>
                    <div class="small text-white-50">${theater.phone || ''}</div>
                </td>
                <td class="text-white-50">${theater.branch ? theater.branch.name : '—'}</td>
                <td class="text-white-50">
                    <div class="text-truncate admin-text-truncate-250" title="${theater.address}">
                        ${theater.address}
                    </div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0 admin-toggle-pointer" type="checkbox" role="switch"
                            data-id="${theater.id}" ${theater.status ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-theater admin-table-action-edit"

                            data-theater='${JSON.stringify(theater).replace(/'/g, "&#39;")}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-theater admin-table-action-delete"
                            data-id="${theater.id}" title="Xóa">
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
            els.modalLabel.textContent = 'Thêm rạp chiếu mới';
            getModalInstance()?.show();
        });
    }

    els.tableBody.addEventListener('click', async (e) => {
        // Edit
        const btnEdit = e.target.closest('.btn-edit-theater');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';

            const theater = JSON.parse(btnEdit.dataset.theater);
            els.idInput.value = theater.id;
            els.modalLabel.textContent = 'Cập nhật rạp chiếu';

            els.name.value = theater.name || '';
            els.branchId.value = theater.branch_id || '';
            els.address.value = theater.address || '';
            els.description.value = theater.description || '';
            els.phone.value = theater.phone || '';
            els.email.value = theater.email || '';
            els.isActive.checked = theater.status === 1 || theater.status === true;

            getModalInstance()?.show();
            return;
        }

        // Delete
        const btnDel = e.target.closest('.btn-delete-theater');
        if (btnDel) {
            if(!confirm('Bạn có chắc muốn xóa rạp này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/theaters/${btnDel.dataset.id}`, { method: 'DELETE' });
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
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/theaters/${id}/toggle-active`, { method: 'POST' });
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
            const url = isEdit ? `/api/v1/admin/theaters/${id}` : `/api/v1/admin/theaters`;

            const formData = new FormData(els.form);
            const data = Object.fromEntries(formData.entries());
            data.status = els.isActive.checked ? 1 : 0;

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(data)
                });

                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật thành công!' : 'Tạo rạp chiếu thành công!', 'success');
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

    els.branchFilter?.addEventListener('change', () => {
        currentBranchId = els.branchFilter.value;
        currentPage = 1;
        loadData(currentPage);
    });

    /* ── Init ──────────────────────────────────────────────────────── */
    window.onAdminPageLoad(async () => {
        if (window.location.pathname !== '/admin/theaters' || !els.tableBody) return;
        await fetchPrerequisites();
        loadData(1);
    });

})();
