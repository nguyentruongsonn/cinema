/**
 * Seat Layout Templates Management - seat-layout-templates.js
 * SPA Architecture
 */
(function () {
    'use strict';

    const MATRIX_PRESETS = {
        '12x12': { rows: 12, cols: 12, capacity: 144, defaults: { regular: 6, vip: 4, couple: 2 } },
        '13x13': { rows: 13, cols: 13, capacity: 169, defaults: { regular: 7, vip: 4, couple: 2 } },
        '14x14': { rows: 14, cols: 14, capacity: 196, defaults: { regular: 8, vip: 4, couple: 2 } },
        '15x15': { rows: 15, cols: 15, capacity: 225, defaults: { regular: 8, vip: 5, couple: 2 } },
    };

    const els = {
        tableBody: document.getElementById('templatesTableBody'),
        pagination: document.getElementById('paginationContainer'),
        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        statusFilter: document.getElementById('statusFilter'),

        btnCreate: document.getElementById('btnOpenCreateSeatLayoutTemplate'),
        modalEl: document.getElementById('seatLayoutTemplateModal'),
        form: document.getElementById('seatLayoutTemplateForm'),
        modalLabel: document.getElementById('seatLayoutTemplateModalLabel'),

        formMethod: document.getElementById('seatLayoutTemplateFormMethod'),
        idInput: document.getElementById('seatLayoutTemplateIdInput'),
        templateName: document.getElementById('templateName'),
        seatMatrix: document.getElementById('seatMatrix'),
        regularSeatRows: document.getElementById('regularSeatRows'),
        vipSeatRows: document.getElementById('vipSeatRows'),
        coupleSeatRows: document.getElementById('coupleSeatRows'),
        description: document.getElementById('description'),
        status: document.getElementById('templateStatus'),

    };

    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = 'all';

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1, search = '', status = 'all') {
        try {
            if (window.renderAdminTableSkeleton && els.tableBody) {
                window.renderAdminTableSkeleton(els.tableBody, 6, 5, false);
            }

            const url = new URL(window.location.origin + '/api/v1/admin/seat-layout-templates');
            url.searchParams.append('page', page);
            if (search) url.searchParams.append('search', search);
            if (status !== 'all') url.searchParams.append('status', status);

            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'seat-layout-templates:list' });
            if (res && res.ok) {
                const data = await res.json();
                renderTable(data.data, data.pagination?.from || data.from);
                renderPagination(data.pagination || data);

            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(templates, startIndex) {
        if (!templates || templates.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy dữ liệu.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        templates.forEach((tpl, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const escapedDesc = (tpl.description || '').replace(/"/g, '&quot;');
            const statusHtml = tpl.status
                ? '<span class="badge bg-success">Đã xuất bản</span>'
                : '<span class="badge bg-secondary">Bản nháp</span>';

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="fw-medium text-white">${tpl.template_name}</div>
                    ${tpl.description ? `<div class="small text-white-50 mt-1">${tpl.description}</div>` : ''}
                    <div class="mt-2">
                    <a href="/admin/seat-layout-templates/${tpl.id}/seats" class="small text-decoration-none admin-accent-link">
                            <i class="bi bi-grid-3x3 me-1"></i>Xem & chỉnh sơ đồ ghế
                        </a>
                    </div>
                </td>
                <td><span class="badge admin-badge-code">${tpl.seat_matrix}</span></td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge admin-badge-blue">Thường: ${tpl.regular_seat_rows}</span>
                        <span class="badge admin-badge-orange">VIP: ${tpl.vip_seat_rows}</span>
                        <span class="badge admin-badge-pink">Đôi: ${tpl.couple_seat_rows}</span>
                    </div>
                </td>
                <td class="text-center">${statusHtml}</td>
                <td class="text-center">
                    <div class="d-flex justify-content-center align-items-center gap-2">
                        <div class="form-check form-switch mb-0 admin-switch-compact">
                            <input class="form-check-input toggle-active-btn m-0 admin-toggle-pointer" type="checkbox" role="switch"
                                data-id="${tpl.id}" ${tpl.status ? 'checked' : ''} title="Bật/Tắt hoạt động">
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-template admin-table-action-edit"

                                data-id="${tpl.id}"
                                data-name="${tpl.template_name}"
                                data-matrix="${tpl.seat_matrix}"
                                data-regular="${tpl.regular_seat_rows}"
                                data-vip="${tpl.vip_seat_rows}"
                                data-couple="${tpl.couple_seat_rows}"
                                data-desc="${escapedDesc}"
                                data-status="${tpl.status ? '1' : '0'}"
                                title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm ms-1 btn-delete-template admin-table-action-delete"
                                data-id="${tpl.id}" title="Xóa">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
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
        els.status.checked = true;
        els.formMethod.value = 'POST';
        els.idInput.value = '';
    }

    function applyDefaultSeatRows(matrixValue) {
        const preset = MATRIX_PRESETS[matrixValue];
        if (!preset?.defaults || els.formMethod.value === 'PUT') return;
        els.regularSeatRows.value = preset.defaults.regular;
        els.vipSeatRows.value = preset.defaults.vip;
        els.coupleSeatRows.value = preset.defaults.couple;
    }

    if (els.seatMatrix) {
        els.seatMatrix.addEventListener('change', function () {
            applyDefaultSeatRows(this.value);
        });
    }

    if (els.btnCreate) {
        els.btnCreate.addEventListener('click', () => {
            resetForm();
            els.modalLabel.innerHTML = '<i class="bi bi-grid-3x3-gap me-2 admin-accent-icon"></i>Tạo mẫu sơ đồ ghế mới';
            getModalInstance()?.show();
        });
    }

    els.tableBody.addEventListener('click', async (e) => {
        // Edit
        const btnEdit = e.target.closest('.btn-edit-template');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';
            els.idInput.value = btnEdit.dataset.id;
            els.modalLabel.innerHTML = '<i class="bi bi-grid-3x3-gap me-2 admin-accent-icon"></i>Cập nhật mẫu sơ đồ ghế';

            els.templateName.value = btnEdit.dataset.name || '';
            els.seatMatrix.value = btnEdit.dataset.matrix || '';
            els.regularSeatRows.value = btnEdit.dataset.regular || '0';
            els.vipSeatRows.value = btnEdit.dataset.vip || '0';
            els.coupleSeatRows.value = btnEdit.dataset.couple || '0';
            els.description.value = btnEdit.dataset.desc || '';
            els.status.checked = btnEdit.dataset.status === '1';

            getModalInstance()?.show();
            return;
        }

        // Delete
        const btnDel = e.target.closest('.btn-delete-template');
        if (btnDel) {
            if (!await window.AdminDialog.confirm({ message: 'Bạn có chắc muốn xóa mẫu sơ đồ ghế này?', confirmLabel: 'Xóa mẫu', variant: 'danger' })) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/seat-layout-templates/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa thành công', 'success');
                    loadData(currentPage, currentSearch, currentStatus);
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
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/seat-layout-templates/${id}/toggle-active`, { method: 'POST' });
                if (!res || !res.ok) throw new Error();

                // If we are in 'published' or 'draft' tab, we might need to reload or visually move it
                if (currentStatus !== 'all') {
                    loadData(currentPage, currentSearch, currentStatus);
                } else {
                    // Update label inline
                    const td = toggle.closest('tr').children[4];
                    td.innerHTML = isActive ? '<span class="badge bg-success">Đã xuất bản</span>' : '<span class="badge bg-secondary">Bản nháp</span>';
                }
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
            const url = isEdit ? `/api/v1/admin/seat-layout-templates/${id}` : `/api/v1/admin/seat-layout-templates`;

            const formData = new FormData(els.form);
            const data = Object.fromEntries(formData.entries());
            data.status = els.status.checked ? 1 : 0;

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(data)
                });

                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật thành công!' : 'Thêm mới thành công!', 'success');

                    loadData(currentPage, currentSearch, currentStatus);
                } else {
                    const errData = await res.json();
                    window.showAdminToast?.(window.formatAdminErrors?.(errData.errors || errData.message) || 'Dữ liệu không hợp lệ', 'error');
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
            loadData(currentPage, currentSearch, currentStatus);
        });
    }

    els.statusFilter?.addEventListener('change', () => {
        currentStatus = els.statusFilter.value || 'all';
        currentPage = 1;
        loadData(currentPage, currentSearch, currentStatus);
    });

    /* ── Init ──────────────────────────────────────────────────────── */
    window.onAdminPageLoad(() => {
        loadData(1, '', 'all');
    });

})();
