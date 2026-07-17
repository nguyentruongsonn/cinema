/**
 * Screens Management - screens.js
 * Pattern: IIFE, SPA Architecture
 * FIXED: Removed all Sound references (sound model/table no longer exists)
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ── DOM cache ──────────────────────────────────────────────────── */
    const els = {
        // Tables
        screensTableBody: document.getElementById('screensTableBody'),
        formatsTableBody: document.getElementById('formatsTableBody'),
        pagination: document.getElementById('paginationContainer'),

        // Badges
        countScreens: document.getElementById('count-screens'),
        countFormats: document.getElementById('count-formats'),

        // Search
        searchForm: document.getElementById('screenSearchForm'),
        searchInput: document.querySelector('input[name="search"]'),

        // Selects
        screenTheater: document.getElementById('screenTheater'),
        screenFormat: document.getElementById('screenFormat'),
        screenTemplate: document.getElementById('screenTemplate'),
    };

    let currentPage = 1;
    let currentSearch = '';

    /* ── Dynamic header button per active tab ────────────────────── */
    const HEADER_BTNS = {
        'pane-screens': `<button type="button" class="btn-primary-custom border-0" id="btnCreateScreen">
            <i class="bi bi-plus-lg"></i> Tạo phòng chiếu
        </button>`,
        'pane-formats': `<button type="button" class="btn-primary-custom border-0" id="btnCreateFormat">
            <i class="bi bi-plus-lg"></i> Thêm định dạng chiếu
        </button>`,
    };

    function injectHeaderBtn(tabId) {
        const container = document.getElementById('tabHeaderActions');
        if (!container) return;
        container.innerHTML = HEADER_BTNS[tabId] || '';
        bindModalOpeners();
    }

    /* ── Modal instances ────────────────────────────────────────── */
    function getModal(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    /* ── Fetch API Data ─────────────────────────────────────────────── */
    async function loadData(page = 1, search = '') {
        try {
            const url = new URL(window.location.origin + '/api/v1/admin/screens');
            url.searchParams.append('page', page);
            if (search) url.searchParams.append('search', search);

            const res = await window.AdminCore.apiFetch(url.toString());
            if (res && res.ok) {
                const data = await res.json();

                // Render UI
                renderScreens(data.screens.data, data.screens.from);
                renderPagination(data.screens);
                renderFormats(data.formats);

                // Populate Dropdowns
                populateDropdowns(data.theaters, data.formats, data.templates);

                // Update Badges
                if (els.countScreens) els.countScreens.textContent = data.screens.total;
                if (els.countFormats) els.countFormats.textContent = data.formats.length;
            }
        } catch (error) {
            console.error('Error loading data:', error);
            els.screensTableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    /* ── Rendering Functions ───────────────────────────────────────── */
    function renderScreens(screens, startIndex) {
        if (!screens || screens.length === 0) {
            els.screensTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        Không tìm thấy phòng chiếu nào.
                    </td>
                </tr>`;
            return;
        }

        els.screensTableBody.innerHTML = '';
        screens.forEach((screen, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const theaterName = screen.theater?.name || '—';
            const formatName = screen.format?.name || '—';

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="fw-medium text-white">${escapeHtml(screen.name)}</div>
                    <span class="small text-white-50">Mã: ${escapeHtml(screen.code)}</span>
                    <div class="mt-1">
                        <a href="/admin/screens/${screen.id}/seats"
                            class="small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1"
                            style="color: var(--accent-color);">
                            <i class="bi bi-grid-3x3"></i> Xem & chỉnh sơ đồ ghế
                        </a>
                    </div>
                </td>
                <td class="text-white-50 small">${escapeHtml(theaterName)}</td>
                <td>
                    <span class="badge" style="background: rgba(96,165,250,0.12); color:#60a5fa;">${escapeHtml(formatName)}</span>
                </td>
                <td class="text-white-50 small">${screen.capacity} chỗ</td>
                <td>
                    ${screen.status ? '<span class="badge bg-success">Hoạt động</span>' : '<span class="badge bg-secondary">Tạm dừng</span>'}
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input toggle-screen-active" type="checkbox" role="switch"
                            data-id="${screen.id}" ${screen.status ? 'checked' : ''} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-screen"
                            style="color: var(--text-secondary); background: rgba(255,255,255,0.05);"
                            data-id="${screen.id}"
                            data-name="${escapeHtml(screen.name)}"
                            data-code="${escapeHtml(screen.code)}"
                            data-theater-id="${screen.theater_id}"
                            data-format-id="${screen.format_id}"
                            data-template-id="${screen.seat_layout_template_id}"
                            data-status="${screen.status ? '1' : '0'}"
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-screen"
                            style="color:#ef4444; background:rgba(239,68,68,0.1);"
                            data-id="${screen.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.screensTableBody.appendChild(tr);
        });
    }

    function renderFormats(formats) {
        if (!formats || formats.length === 0) {
            els.formatsTableBody.innerHTML = `<tr><td colspan="4" class="text-center py-5 text-muted">Chưa có định dạng nào.</td></tr>`;
            return;
        }
        els.formatsTableBody.innerHTML = '';
        formats.forEach((f, i) => {
            els.formatsTableBody.innerHTML += `
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td class="text-center text-white-50">${i + 1}</td>
                    <td class="fw-medium text-white">${escapeHtml(f.name)}</td>
                    <td class="text-white-50 small">+${parseInt(f.surcharge).toLocaleString('vi-VN')} đ</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-format"
                                style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                                data-id="${f.id}" data-name="${escapeHtml(f.name)}" data-surcharge="${escapeHtml(f.surcharge)}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm ms-1 btn-delete-format"
                                style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${f.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }

    function populateDropdowns(theaters, formats, templates) {
        if (els.screenTheater) {
            replaceOptions(els.screenTheater, '-- Chọn rạp --', theaters, 'id', item => item.name);
        }
        if (els.screenFormat) {
            replaceOptions(els.screenFormat, '-- Chọn định dạng --', formats, 'id', item => `${item.name} (+${parseInt(item.surcharge || 0, 10).toLocaleString()}đ)`);
        }
        if (els.screenTemplate) {
            replaceOptions(els.screenTemplate, '-- Chọn mẫu --', templates, 'id', item => `${item.template_name} (${item.seat_matrix})`, item => ({
                matrix: item.seat_matrix,
                regular: item.regular_seat_rows,
                vip: item.vip_seat_rows,
                couple: item.couple_seat_rows,
            }));
        }
    }

    function replaceOptions(select, emptyLabel, items, valueKey, labelFactory, attributesFactory = null) {
        const fragment = document.createDocumentFragment();
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = emptyLabel;
        fragment.appendChild(emptyOption);
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item[valueKey] ?? '');
            option.textContent = String(labelFactory(item) ?? '');
            Object.entries(attributesFactory?.(item) || {}).forEach(([key, value]) => {
                option.dataset[key] = String(value ?? '');
            });
            fragment.appendChild(option);
        });
        select.replaceChildren(fragment);
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
                loadData(currentPage, currentSearch);
            });
        });
    }

    /* ── Reset forms ─────────────────────────────────────────────── */
    function resetScreenForm() {
        const form = document.getElementById('screenForm');
        if (!form) return;
        form.reset();
        document.getElementById('screenFormMethod').value = 'POST';
        document.getElementById('screenIdInput').value = '';
        document.getElementById('screenModalLabel').innerHTML = '<i class="bi bi-display me-2" style="color:var(--accent-color);"></i>Tạo phòng chiếu mới';
        document.getElementById('templateDetailBadges')?.classList.add('d-none');
        document.getElementById('templateEditWarning')?.classList.add('d-none');
        document.getElementById('screenStatus').checked = true;
    }

    function resetFormatForm() {
        const form = document.getElementById('formatForm');
        if (!form) return;
        form.reset();
        document.getElementById('formatFormMethod').value = 'POST';
        document.getElementById('formatModalLabel').innerHTML = '<i class="bi bi-camera-reels me-2" style="color:var(--accent-color);"></i>Thêm định dạng chiếu';
    }

    function updateTemplateInfo(selectEl) {
        const badgesEl  = document.getElementById('templateDetailBadges');
        const option    = selectEl.options[selectEl.selectedIndex];
        if (!option || !option.value) {
            badgesEl?.classList.add('d-none');
            return;
        }
        document.getElementById('tplRegular').textContent = option.getAttribute('data-regular') || '0';
        document.getElementById('tplVip').textContent     = option.getAttribute('data-vip')     || '0';
        document.getElementById('tplCouple').textContent  = option.getAttribute('data-couple')  || '0';
        badgesEl?.classList.remove('d-none');
    }

    function bindModalOpeners() {
        document.getElementById('btnCreateScreen')?.addEventListener('click', () => {
            resetScreenForm();
            getModal('screenModal')?.show();
        });
        document.getElementById('btnCreateFormat')?.addEventListener('click', () => {
            resetFormatForm();
            getModal('formatModal')?.show();
        });
    }

    /* ── Event Delegation for Actions ────────────────────────────── */
    document.addEventListener('click', async (e) => {
        // --- Edit Screen ---
        const editScreen = e.target.closest('.btn-edit-screen');
        if (editScreen) {
            resetScreenForm();
            document.getElementById('screenFormMethod').value = 'PUT';
            document.getElementById('screenIdInput').value   = editScreen.dataset.id;
            document.getElementById('screenModalLabel').innerHTML = '<i class="bi bi-display me-2" style="color:var(--accent-color);"></i>Cập nhật phòng chiếu';

            document.getElementById('screenName').value       = editScreen.dataset.name    || '';
            document.getElementById('screenCode').value       = editScreen.dataset.code    || '';
            document.getElementById('screenTheater').value    = editScreen.dataset.theaterId || '';
            document.getElementById('screenFormat').value     = editScreen.dataset.formatId  || '';

            const tplSelect = document.getElementById('screenTemplate');
            tplSelect.value = editScreen.dataset.templateId || '';
            updateTemplateInfo(tplSelect);

            document.getElementById('screenStatus').checked   = editScreen.dataset.status === '1';
            document.getElementById('templateEditWarning')?.classList.remove('d-none');
            getModal('screenModal')?.show();
            return;
        }

        // --- Delete Screen ---
        const delScreen = e.target.closest('.btn-delete-screen');
        if (delScreen) {
            if(!confirm('Xóa phòng chiếu này sẽ xóa toàn bộ sơ đồ ghế. Tiếp tục?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${delScreen.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa phòng chiếu thành công', 'success');
                    loadData(currentPage, currentSearch);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) { }
            return;
        }

        // --- Edit Format ---
        const editFormat = e.target.closest('.btn-edit-format');
        if (editFormat) {
            resetFormatForm();
            document.getElementById('formatFormMethod').value = 'PUT';
            document.getElementById('formatForm').dataset.id = editFormat.dataset.id;
            document.getElementById('formatModalLabel').innerHTML = '<i class="bi bi-camera-reels me-2" style="color:var(--accent-color);"></i>Cập nhật định dạng chiếu';
            document.getElementById('formatName').value       = editFormat.dataset.name      || '';
            document.getElementById('formatSurcharge').value  = editFormat.dataset.surcharge || '0';
            getModal('formatModal')?.show();
            return;
        }

        // --- Delete Format ---
        const delFormat = e.target.closest('.btn-delete-format');
        if (delFormat) {
            if(!confirm('Bạn có chắc muốn xóa định dạng này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/formats/${delFormat.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) { window.showAdminToast?.('Xóa thành công', 'success'); loadData(currentPage, currentSearch); }
                else { window.showAdminToast?.((await res.json()).message, 'error'); }
            } catch (err) {}
            return;
        }
    });

    els.screensTableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-screen-active');
        if (toggle) {
            const id = toggle.getAttribute('data-id');
            const isActive = toggle.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${id}/toggle-active`, { method: 'POST' });
                if (!res || !res.ok) throw new Error();
                window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
                loadData(currentPage, currentSearch);
            } catch (error) {
                window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
                toggle.checked = !isActive;
            }
        }
    });

    /* ── Form Submissions ────────────────────────────────────────── */
    document.getElementById('screenForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const method = document.getElementById('screenFormMethod').value;
        const id     = document.getElementById('screenIdInput').value;
        const url    = method === 'POST' ? '/api/v1/admin/screens' : `/api/v1/admin/screens/${id}`;

        const formData = new FormData(e.target);
        const body = {};
        formData.forEach((v, k) => body[k] = v);

        try {
            const res = await window.AdminCore.apiFetch(url, { method, body: JSON.stringify(body) });
            const data = await res.json();
            if (res && res.ok) {
                window.showAdminToast?.(data.message || 'Thành công', 'success');
                getModal('screenModal')?.hide();
                loadData(currentPage, currentSearch);
            } else {
                window.showAdminToast?.(data.message || 'Có lỗi xảy ra', 'error');
            }
        } catch (err) {
            console.error(err);
            window.showAdminToast?.('Có lỗi xảy ra', 'error');
        }
    });

    document.getElementById('formatForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const method = document.getElementById('formatFormMethod').value;
        const id     = document.getElementById('formatForm').dataset.id;
        const url    = method === 'POST' ? '/api/v1/admin/formats' : `/api/v1/admin/formats/${id}`;

        const formData = new FormData(e.target);
        const body = {};
        formData.forEach((v, k) => body[k] = v);

        try {
            const res = await window.AdminCore.apiFetch(url, { method, body: JSON.stringify(body) });
            const data = await res.json();
            if (res && res.ok) {
                window.showAdminToast?.(data.message || 'Thành công', 'success');
                getModal('formatModal')?.hide();
                loadData(currentPage, currentSearch);
            } else {
                window.showAdminToast?.(data.message || 'Có lỗi xảy ra', 'error');
            }
        } catch (err) {
            console.error(err);
            window.showAdminToast?.('Có lỗi xảy ra', 'error');
        }
    });

    /* ── Search ────────────────────────────────────────────────── */
    els.searchForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        currentSearch = els.searchInput.value.trim();
        currentPage = 1;
        loadData(currentPage, currentSearch);
    });

    /* ── Template select onChange ────────────────────────────────── */
    document.getElementById('screenTemplate')?.addEventListener('change', function() {
        updateTemplateInfo(this);
    });

    /* ── Tabs change => reload header button ──────────────────────── */
    const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEls.forEach(tab => {
        tab.addEventListener('shown.bs.tab', (e) => {
            const targetId = e.target.getAttribute('data-bs-target')?.substring(1);
            injectHeaderBtn(targetId);
        });
    });

    /* ── Initialize ─────────────────────────────────────────────── */
    loadData();
    const activeTab = document.querySelector('.tab-pane.active')?.id;
    injectHeaderBtn(activeTab || 'pane-screens');

})();
