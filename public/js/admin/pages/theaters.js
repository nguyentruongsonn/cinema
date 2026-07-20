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
                
                const fragment = document.createDocumentFragment();
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = '-- Chọn chi nhánh --';
                fragment.appendChild(emptyOption);
                branches.forEach(b => {
                    const option = document.createElement('option');
                    option.value = String(b.id);
                    option.textContent = String(b.name ?? '');
                    fragment.appendChild(option);
                });
                els.branchId.replaceChildren(fragment);
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
                    <div class="text-truncate" style="max-width:250px;" title="${theater.address}">
                        ${theater.address}
                    </div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0" type="checkbox" role="switch"
                            data-id="${theater.id}" ${theater.is_active ? 'checked' : ''} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-theater"
                            style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                            data-theater='${JSON.stringify(theater).replace(/'/g, "&#39;")}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-theater"
                            style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${theater.id}" title="Xóa">
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

        for (const i of window.AdminCore.paginationPages(meta)) {
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
            els.isActive.checked = theater.is_active === 1 || theater.is_active === true;

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
                if (!res || !res.ok) throw new Error();
                window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
                loadData(currentPage);
            } catch (error) {
                window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
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
            data.is_active = els.isActive.checked ? 1 : 0;

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
            currentPage = 1;
            loadData(currentPage);
        });
    }

    /* ── Init ──────────────────────────────────────────────────────── */
    window.onAdminPageLoad(async () => {
        await fetchPrerequisites();
        loadData(1);
    });

})();
