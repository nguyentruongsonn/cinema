/**
 * Screens Management - screens.js
 * Pattern: IIFE, SPA Architecture
 */
(function () {
    'use strict';

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

        // Selects
        screenTheater: document.getElementById('screenTheater'),
        screenFormat: document.getElementById('screenFormat'),
        screenSound: document.getElementById('screenSound'),
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

            const res = await window.AdminCore.apiFetch(url.toString());
            if (res && res.ok) {
                const data = await res.json();
                
                // Render UI
                renderScreens(data.screens.data, data.screens.from);
                renderPagination(data.screens);
                renderFormats(data.formats);
                renderSounds(data.sounds);
                
                // Populate Dropdowns
                populateDropdowns(data.theaters, data.formats, data.sounds, data.templates);

                // Update Badges
                if (els.countScreens) els.countScreens.textContent = data.screens.total;
                if (els.countFormats) els.countFormats.textContent = data.formats.length;
                if (els.countSounds) els.countSounds.textContent = data.sounds.length;
            }
        } catch (error) {
            console.error('Error loading data:', error);
            els.screensTableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    /* ── Rendering Functions ───────────────────────────────────────── */
    function renderScreens(screens, startIndex) {
        if (!screens || screens.length === 0) {
            els.screensTableBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
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
            const soundName = screen.sound?.name || '—';
            
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="fw-medium text-white">${screen.name}</div>
                    <span class="small text-white-50">Mã: ${screen.code}</span>
                    <div class="mt-1">
                        <a href="/admin/screens/${screen.id}/seats"
                            class="small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1"
                            style="color: var(--accent-color);">
                            <i class="bi bi-grid-3x3"></i> Xem &amp; chỉnh sơ đồ ghế
                        </a>
                    </div>
                </td>
                <td class="text-white-50 small">${theaterName}</td>
                <td>
                    <span class="badge" style="background: rgba(96,165,250,0.12); color:#60a5fa;">${formatName}</span>
                </td>
                <td>
                    <span class="badge" style="background: rgba(167,139,250,0.12); color:#a78bfa;">${soundName}</span>
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
                            data-name="${screen.name}"
                            data-code="${screen.code}"
                            data-theater-id="${screen.theater_id}"
                            data-format-id="${screen.format_id}"
                            data-sound-id="${screen.sound_id}"
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
                    <td class="fw-medium text-white">${f.name}</td>
                    <td class="text-white-50 small">+${parseInt(f.surcharge).toLocaleString('vi-VN')} đ</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-format"
                                style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                                data-id="${f.id}" data-name="${f.name}" data-surcharge="${f.surcharge}">
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

    function renderSounds(sounds) {
        if (!sounds || sounds.length === 0) {
            els.soundsTableBody.innerHTML = `<tr><td colspan="3" class="text-center py-5 text-muted">Chưa có định dạng âm thanh nào.</td></tr>`;
            return;
        }
        els.soundsTableBody.innerHTML = '';
        sounds.forEach((s, i) => {
            els.soundsTableBody.innerHTML += `
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td class="text-center text-white-50">${i + 1}</td>
                    <td class="fw-medium text-white">${s.name}</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-sound"
                                style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                                data-id="${s.id}" data-name="${s.name}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm ms-1 btn-delete-sound"
                                style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${s.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }

    function populateDropdowns(theaters, formats, sounds, templates) {
        if (els.screenTheater) {
            els.screenTheater.innerHTML = '<option value="">-- Chọn rạp --</option>';
            theaters.forEach(t => els.screenTheater.innerHTML += `<option value="${t.id}">${t.name}</option>`);
        }
        if (els.screenFormat) {
            els.screenFormat.innerHTML = '<option value="">-- Chọn định dạng --</option>';
            formats.forEach(f => els.screenFormat.innerHTML += `<option value="${f.id}">${f.name} (+${parseInt(f.surcharge).toLocaleString()}đ)</option>`);
        }
        if (els.screenSound) {
            els.screenSound.innerHTML = '<option value="">-- Chọn âm thanh --</option>';
            sounds.forEach(s => els.screenSound.innerHTML += `<option value="${s.id}">${s.name}</option>`);
        }
        if (els.screenTemplate) {
            els.screenTemplate.innerHTML = '<option value="">-- Chọn mẫu --</option>';
            templates.forEach(t => {
                els.screenTemplate.innerHTML += `<option value="${t.id}" data-matrix="${t.seat_matrix}" data-regular="${t.regular_seat_rows}" data-vip="${t.vip_seat_rows}" data-couple="${t.couple_seat_rows}">${t.template_name} (${t.seat_matrix})</option>`;
            });
        }
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

    function resetSoundForm() {
        const form = document.getElementById('soundForm');
        if (!form) return;
        form.reset();
        document.getElementById('soundFormMethod').value = 'POST';
        document.getElementById('soundModalLabel').innerHTML = '<i class="bi bi-volume-up me-2" style="color:var(--accent-color);"></i>Thêm định dạng âm thanh';
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
            document.getElementById('screenModalLabel').innerHTML = '<i class="bi bi-display me-2" style="color:var(--accent-color);"></i>Cập nhật phòng chiếu';
            
            document.getElementById('screenName').value       = editScreen.dataset.name    || '';
            document.getElementById('screenCode').value       = editScreen.dataset.code    || '';
            document.getElementById('screenTheater').value    = editScreen.dataset.theaterId || '';
            document.getElementById('screenFormat').value     = editScreen.dataset.formatId  || '';
            document.getElementById('screenSound').value      = editScreen.dataset.soundId   || '';
            
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

        // --- Edit Sound ---
        const editSound = e.target.closest('.btn-edit-sound');
        if (editSound) {
            resetSoundForm();
            document.getElementById('soundFormMethod').value = 'PUT';
            document.getElementById('soundForm').dataset.id = editSound.dataset.id;
            document.getElementById('soundModalLabel').innerHTML = '<i class="bi bi-volume-up me-2" style="color:var(--accent-color);"></i>Cập nhật định dạng âm thanh';
            document.getElementById('soundName').value = editSound.dataset.name || '';
            getModal('soundModal')?.show();
            return;
        }

        // --- Delete Sound ---
        const delSound = e.target.closest('.btn-delete-sound');
        if (delSound) {
            if(!confirm('Bạn có chắc muốn xóa định dạng này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/sounds/${delSound.dataset.id}`, { method: 'DELETE' });
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

    /* ── Form Submissions via Fetch ─────────────────────────────── */
    async function handleFormSubmit(e, formId, urlBase) {
        e.preventDefault();
        const form = document.getElementById(formId);
        if (!form) return;
        
        const isEdit = document.getElementById(formId + 'Method').value === 'PUT';
        let id = '';
        if (formId === 'screenForm') id = document.getElementById('screenIdInput').value;
        else id = form.dataset.id || '';

        const url = isEdit ? `/api/v1/admin/${urlBase}/${id}` : `/api/v1/admin/${urlBase}`;
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        if (formId === 'screenForm') {
            data.status = document.getElementById('screenStatus').checked ? 1 : 0;
        }

        try {
            const res = await window.AdminCore.apiFetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                body: JSON.stringify(data)
            });
            
            if (res && res.ok) {
                getModal(formId.replace('Form', 'Modal'))?.hide();
                window.showAdminToast?.(isEdit ? 'Cập nhật thành công!' : 'Thêm mới thành công!', 'success');
                loadData(currentPage, currentSearch);
            } else {
                const errData = await res.json();
                alert('Lỗi: ' + JSON.stringify(errData.errors || errData.message));
            }
        } catch (error) {
            console.error('Submit form error', error);
        }
    }

    document.getElementById('screenForm')?.addEventListener('submit', (e) => handleFormSubmit(e, 'screenForm', 'screens'));
    document.getElementById('formatForm')?.addEventListener('submit', (e) => handleFormSubmit(e, 'formatForm', 'formats'));
    document.getElementById('soundForm')?.addEventListener('submit', (e) => handleFormSubmit(e, 'soundForm', 'sounds'));

    /* ── Toggle screen active ─────────────────────────────── */
    document.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-screen-active');
        if (toggle) {
            const id = toggle.getAttribute('data-id');
            const isActive = toggle.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/screens/${id}/toggle-active`, { method: 'POST' });
                if (!res || !res.ok) throw new Error();
            } catch (error) {
                window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
                toggle.checked = !isActive;
            }
        }
        
        if (e.target.id === 'screenTemplate') {
            updateTemplateInfo(e.target);
        }
    });

    /* ── Search Form ────────────────────────────────────────── */
    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            currentSearch = els.searchInput.value.trim();
            currentPage = 1;
            loadData(currentPage, currentSearch);
        });
    }

    /* ── Tab change: inject header button ────────────────────────── */
    function initTabs() {
        const tabs = document.querySelectorAll('#screenTabs .nav-link');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const paneId = e.target.getAttribute('data-bs-target')?.replace('#', '');
                injectHeaderBtn(paneId);
            });
        });
        const activeTab = document.querySelector('#screenTabs .nav-link.active');
        if (activeTab) {
            const paneId = activeTab.getAttribute('data-bs-target')?.replace('#', '');
            injectHeaderBtn(paneId);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        loadData();
    });

})();
