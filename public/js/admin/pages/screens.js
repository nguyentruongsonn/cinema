/**
 * Screens Management - screens.js
 * Pattern: IIFE, SPA Architecture
 * Manages screens, projection formats, and sound formats.
 */
(function () {
    'use strict';

    const pageRoot = document.getElementById('screensTableBody');
    if (!pageRoot || pageRoot.dataset.adminPageInitialized === 'true') return;
    pageRoot.dataset.adminPageInitialized = 'true';

    const lifecycleController = new AbortController();
    window.onAdminPageCleanup(() => {
        lifecycleController.abort();
        delete pageRoot.dataset.adminPageInitialized;
    });

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
        soundsTableBody: document.getElementById('soundsTableBody'),
        pagination: document.getElementById('paginationContainer'),

        // Badges
        countScreens: document.getElementById('count-screens'),
        countFormats: document.getElementById('count-formats'),
        countSounds: document.getElementById('count-sounds'),

        // Search
        searchForm: document.getElementById('screenSearchForm'),
        searchInput: document.querySelector('input[name="search"]'),

        // Filters
        branchFilter: document.getElementById('branchFilter'),
        theaterFilter: document.getElementById('theaterFilter'),
        statusFilter: document.getElementById('statusFilter'),

        // Selects
        screenTheater: document.getElementById('screenTheater'),
        screenFormat: document.getElementById('screenFormat'),
        screenSound: document.getElementById('screenSound'),
        screenTemplate: document.getElementById('screenTemplate'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentBranchId = '';
    let currentTheaterId = '';
    let currentStatus = 'all';
    let referencesLoaded = false;
    let branchOptions = [];
    let theaterOptions = [];

    /* ── Dynamic header button per active tab ────────────────────── */
    const HEADER_BTNS = {
        'pane-screens': `<button type="button" class="btn-primary-custom border-0" id="btnCreateScreen">
            <i class="bi bi-plus-lg"></i> Tạo phòng chiếu
        </button>`,
        'pane-formats': `<button type="button" class="btn-primary-custom border-0" id="btnCreateFormat">
            <i class="bi bi-plus-lg"></i> Thêm định dạng chiếu
        </button>`,
        'pane-sounds': `<button type="button" class="btn-primary-custom border-0" id="btnCreateSound">
            <i class="bi bi-plus-lg"></i> Thêm định dạng âm thanh
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
            if (currentBranchId) url.searchParams.append('branch_id', currentBranchId);
            if (currentTheaterId) url.searchParams.append('theater_id', currentTheaterId);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);
            if (!referencesLoaded) url.searchParams.append('include_references', '1');

            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'screens:list' });
            if (res && res.ok) {
                const data = await res.json();

                // Render UI
                renderScreens(data.screens.data, data.screens.from);
                renderPagination(data.screens);
                if (data.formats && data.sounds && data.theaters && data.templates) {
                    renderFormats(data.formats);
                    renderSounds(data.sounds);
                    populateDropdowns(data.branches || [], data.theaters, data.formats, data.sounds, data.templates);
                    referencesLoaded = true;
                }

                // Update Badges
                if (els.countScreens) els.countScreens.textContent = data.screens.total;
                if (els.countFormats && data.formats) els.countFormats.textContent = data.formats.length;
                if (els.countSounds && data.sounds) els.countSounds.textContent = data.sounds.length;
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
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
                            class="small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 admin-accent-icon">
                            <i class="bi bi-grid-3x3"></i> Xem & chỉnh sơ đồ ghế
                        </a>
                    </div>
                </td>
                <td class="text-white-50 small">${escapeHtml(theaterName)}</td>
                <td>
                    <span class="badge admin-badge-format">${escapeHtml(formatName)}</span>
                </td>
                <td class="text-white-50 small">${screen.capacity} chỗ</td>
                <td>
                    ${screen.status ? '<span class="badge bg-success">Hoạt động</span>' : '<span class="badge bg-secondary">Tạm dừng</span>'}
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input toggle-screen-active admin-toggle-pointer" type="checkbox" role="switch"
                            data-id="${screen.id}" ${screen.status ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-screen admin-table-action-edit"

                            data-id="${screen.id}"
                            data-name="${escapeHtml(screen.name)}"
                            data-code="${escapeHtml(screen.code)}"
                            data-theater-id="${screen.theater_id}"
                            data-format-id="${screen.format_id}"
                            data-sound-id="${screen.sound_id}"
                            data-template-id="${screen.seat_layout_template_id}"
                            data-status="${screen.status ? '1' : '0'}"
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-screen admin-table-action-delete"

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
                <tr class="admin-row-divider">
                    <td class="text-center text-white-50">${i + 1}</td>
                    <td class="fw-medium text-white">${escapeHtml(f.name)}</td>
                    <td class="text-white-50 small">+${parseInt(f.surcharge).toLocaleString('vi-VN')} đ</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-format admin-table-action-edit"

                                data-id="${f.id}" data-name="${escapeHtml(f.name)}" data-surcharge="${escapeHtml(f.surcharge)}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm ms-1 btn-delete-format admin-table-action-delete"
                                data-id="${f.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }

    function renderSounds(sounds) {
        if (!els.soundsTableBody) return;
        if (!sounds || sounds.length === 0) {
            els.soundsTableBody.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted">Chưa có định dạng âm thanh nào.</td></tr>';
            return;
        }

        els.soundsTableBody.innerHTML = sounds.map((sound, index) => `
            <tr class="admin-row-divider">
                <td class="text-center text-white-50">${index + 1}</td>
                <td class="fw-medium text-white">${escapeHtml(sound.name)}</td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-sound admin-table-action-edit"

                            data-id="${sound.id}" data-name="${escapeHtml(sound.name)}" title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-sound admin-table-action-delete"
                            data-id="${sound.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function populateDropdowns(branches, theaters, formats, sounds, templates) {
        branchOptions = branches;
        theaterOptions = theaters;

        if (els.branchFilter) {
            replaceOptions(els.branchFilter, 'Tất cả chi nhánh', branchOptions, 'id', item => item.name);
        }

        populateTheaterFilter();

        if (els.screenTheater) {
            replaceOptions(els.screenTheater, '-- Chọn rạp --', theaterOptions, 'id', item => item.name, item => ({
                branchId: item.branch_id,
            }));
        }
        if (els.screenFormat) {
            replaceOptions(els.screenFormat, '-- Chọn định dạng --', formats, 'id', item => `${item.name} (+${parseInt(item.surcharge || 0, 10).toLocaleString()}đ)`);
        }
        if (els.screenSound) {
            replaceOptions(els.screenSound, '-- Chọn âm thanh --', sounds, 'id', item => item.name);
        }
        if (els.screenTemplate) {
            replaceOptions(els.screenTemplate, '-- Chọn mẫu --', templates, 'id', item => item.template_name);
        }
    }

    function populateTheaterFilter() {
        if (!els.theaterFilter) return;

        const filteredTheaters = currentBranchId
            ? theaterOptions.filter(theater => String(theater.branch_id) === String(currentBranchId))
            : theaterOptions;

        replaceOptions(els.theaterFilter, 'Tất cả rạp', filteredTheaters, 'id', item => item.name);

        if (currentTheaterId && !filteredTheaters.some(theater => String(theater.id) === String(currentTheaterId))) {
            currentTheaterId = '';
            els.theaterFilter.value = '';
        } else {
            els.theaterFilter.value = currentTheaterId;
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
        window.AdminCore.renderAdminPagination(els.pagination, meta, (page) => {
            currentPage = page; loadData(page, currentSearch);
        });
    }

    /* ── Reset forms ─────────────────────────────────────────────── */
    function resetScreenForm() {
        const form = document.getElementById('screenForm');
        if (!form) return;
        form.reset();
        document.getElementById('screenFormMethod').value = 'POST';
        document.getElementById('screenIdInput').value = '';
        document.getElementById('screenModalLabel').innerHTML = '<i class="bi bi-display me-2 admin-accent-icon"></i>Tạo phòng chiếu mới';
        document.getElementById('templateEditWarning')?.classList.add('d-none');
        document.getElementById('screenStatus').checked = true;
    }

    function resetFormatForm() {
        const form = document.getElementById('formatForm');
        if (!form) return;
        form.reset();
        document.getElementById('formatFormMethod').value = 'POST';
        document.getElementById('formatModalLabel').innerHTML = '<i class="bi bi-camera-reels me-2 admin-accent-icon"></i>Thêm định dạng chiếu';
    }

    function resetSoundForm() {
        const form = document.getElementById('soundForm');
        if (!form) return;
        form.reset();
        form.dataset.id = '';
        document.getElementById('soundFormMethod').value = 'POST';
        document.getElementById('soundModalLabel').innerHTML = '<i class="bi bi-volume-up me-2"></i>Thêm định dạng âm thanh';
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
        document.getElementById('btnCreateSound')?.addEventListener('click', () => {
            resetSoundForm();
            getModal('soundModal')?.show();
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
            document.getElementById('screenModalLabel').innerHTML = '<i class="bi bi-display me-2 admin-accent-icon"></i>Cập nhật phòng chiếu';

            document.getElementById('screenName').value       = editScreen.dataset.name    || '';
            document.getElementById('screenCode').value       = editScreen.dataset.code    || '';
            document.getElementById('screenTheater').value    = editScreen.dataset.theaterId || '';
            document.getElementById('screenFormat').value     = editScreen.dataset.formatId  || '';
            document.getElementById('screenSound').value      = editScreen.dataset.soundId || '';

            const tplSelect = document.getElementById('screenTemplate');
            tplSelect.value = editScreen.dataset.templateId || '';

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
            document.getElementById('formatModalLabel').innerHTML = '<i class="bi bi-camera-reels me-2 admin-accent-icon"></i>Cập nhật định dạng chiếu';
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
                referencesLoaded = false;
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/formats/${delFormat.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) { window.showAdminToast?.('Xóa thành công', 'success'); loadData(currentPage, currentSearch); }
                else { window.showAdminToast?.((await res.json()).message, 'error'); }
            } catch (err) {}
            return;
        }

        const editSound = e.target.closest('.btn-edit-sound');
        if (editSound) {
            resetSoundForm();
            document.getElementById('soundFormMethod').value = 'PUT';
            document.getElementById('soundForm').dataset.id = editSound.dataset.id;
            document.getElementById('soundName').value = editSound.dataset.name || '';
            document.getElementById('soundModalLabel').innerHTML = '<i class="bi bi-volume-up me-2"></i>Cập nhật định dạng âm thanh';
            getModal('soundModal')?.show();
            return;
        }

        const deleteSound = e.target.closest('.btn-delete-sound');
        if (deleteSound) {
            if (!confirm('Bạn có chắc muốn xóa định dạng âm thanh này?')) return;
            try {
                referencesLoaded = false;
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/sounds/${deleteSound.dataset.id}`, { method: 'DELETE' });
                const data = await res?.json();
                if (res?.ok) {
                    window.showAdminToast?.(data.message || 'Xóa thành công', 'success');
                    loadData(currentPage, currentSearch);
                } else {
                    window.showAdminToast?.(data?.message || 'Xóa thất bại', 'error');
                }
            } catch (error) {
                window.showAdminToast?.('Xóa thất bại', 'error');
            }
        }
    }, { signal: lifecycleController.signal });

    els.screensTableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-screen-active');
        if (toggle) {
            const id = toggle.getAttribute('data-id');
            const isActive = toggle.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${id}/toggle-active`, {
                    method: 'POST',
                    body: JSON.stringify({ status: isActive }),
                });
                if (!res) throw new Error('Không thể kết nối đến máy chủ.');
                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.message || 'Cập nhật trạng thái thất bại.');
                }
                const result = await res.json();
                window.showAdminToast?.(result.message || 'Cập nhật trạng thái thành công', 'success');
                loadData(currentPage, currentSearch);
            } catch (error) {
                window.showAdminToast?.(error.message || 'Cập nhật trạng thái thất bại', 'error');
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
        referencesLoaded = false;

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

    document.getElementById('soundForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const method = document.getElementById('soundFormMethod').value;
        const id = e.currentTarget.dataset.id;
        const url = method === 'POST' ? '/api/v1/admin/sounds' : `/api/v1/admin/sounds/${id}`;
        const body = { name: document.getElementById('soundName').value.trim() };
        referencesLoaded = false;

        try {
            const res = await window.AdminCore.apiFetch(url, { method, body: JSON.stringify(body) });
            const data = await res?.json();
            if (res?.ok) {
                window.showAdminToast?.(data.message || 'Thành công', 'success');
                getModal('soundModal')?.hide();
                loadData(currentPage, currentSearch);
            } else {
                window.showAdminToast?.(data?.message || 'Có lỗi xảy ra', 'error');
            }
        } catch (error) {
            window.showAdminToast?.('Có lỗi xảy ra', 'error');
        }
    });

    /* ── Search & Filters ───────────────────────────────────────── */
    els.searchForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        currentSearch = els.searchInput.value.trim();
        currentStatus = els.statusFilter?.value || 'all';
        currentPage = 1;
        loadData(currentPage, currentSearch);
    });

    els.statusFilter?.addEventListener('change', () => {
        currentStatus = els.statusFilter.value || 'all';
        currentPage = 1;
        loadData(currentPage, currentSearch);
    });

    els.branchFilter?.addEventListener('change', () => {
        currentBranchId = els.branchFilter.value;
        currentPage = 1;
        populateTheaterFilter();
        loadData(currentPage, currentSearch);
    });

    els.theaterFilter?.addEventListener('change', () => {
        currentTheaterId = els.theaterFilter.value;
        currentPage = 1;
        loadData(currentPage, currentSearch);
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
