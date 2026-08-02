(function () {
    'use strict';

    const pageRoot = document.getElementById('holidaysTableBody');
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

    // UI elements selectors
    const els = {
        // Tables
        holidaysTable: document.getElementById('holidaysTableBody'),
        dayRulesTable: document.getElementById('dayRulesTableBody'),
        timeSlotsTable: document.getElementById('timeSlotsTableBody'),

        // Badges
        countHolidays: document.getElementById('count-holidays'),
        countDayRules: document.getElementById('count-dayrules'),
        countTimeSlots: document.getElementById('count-timeslots'),

        // Forms
        holidayForm: document.getElementById('holidayForm'),
        dayRulesForm: document.getElementById('dayRulesForm'),
        timeSlotForm: document.getElementById('timeSlotForm'),

        // Modals
        holidayModalEl: document.getElementById('holidayModal'),
        timeSlotModalEl: document.getElementById('timeSlotModal'),

        // Modal labels
        holidayModalLabel: document.getElementById('holidayModalLabel'),
        timeSlotModalLabel: document.getElementById('timeSlotModalLabel'),

        // Form methods & IDs
        holidayFormMethod: document.getElementById('holidayFormMethod'),
        holidayIdInput: document.getElementById('holidayIdInput'),
        timeSlotFormMethod: document.getElementById('timeSlotFormMethod'),
        timeSlotIdInput: document.getElementById('timeSlotIdInput'),

        // Create buttons
        btnCreateHoliday: document.getElementById('btnCreateHoliday'),
        btnCreateTimeSlot: document.getElementById('btnCreateTimeSlot')
    };

    // Day of week labels mapping
    const dayLabels = {
        0: 'Chủ Nhật',
        1: 'Thứ Hai',
        2: 'Thứ Ba',
        3: 'Thứ Tư',
        4: 'Thứ Năm',
        5: 'Thứ Sáu',
        6: 'Thứ Bảy'
    };

    function getModalInstance(modalEl) {
        if (!modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    // ─── Fetch & Render Data ─────────────────────────────────────────────

    async function loadHolidays() {
        try {
            if (window.renderAdminTableSkeleton && els.holidaysTable) {
                window.renderAdminTableSkeleton(els.holidaysTable, 5, 4, false);
            }
            const res = await window.AdminCore.apiFetch('/api/v1/admin/pricing-rules/holidays');
            if (res && res.ok) {
                const data = await res.json();
                const list = data.data || [];
                renderHolidays(list);
                if (els.countHolidays) els.countHolidays.textContent = list.length;
            }
        } catch (error) {
            console.error('Error loading holidays', error);
        }
    }

    async function loadDayRules() {
        try {
            if (window.renderAdminTableSkeleton && els.dayRulesTable) {
                window.renderAdminTableSkeleton(els.dayRulesTable, 5, 4, false);
            }
            const res = await window.AdminCore.apiFetch('/api/v1/admin/pricing-rules/day-rules');
            if (res && res.ok) {
                const data = await res.json();
                const list = data.data || [];
                renderDayRules(list);
                if (els.countDayRules) els.countDayRules.textContent = list.length;
            }
        } catch (error) {
            console.error('Error loading day rules', error);
        }
    }

    async function loadTimeSlots() {
        try {
            if (window.renderAdminTableSkeleton && els.timeSlotsTable) {
                window.renderAdminTableSkeleton(els.timeSlotsTable, 6, 4, false);
            }
            const res = await window.AdminCore.apiFetch('/api/v1/admin/pricing-rules/time-slots');
            if (res && res.ok) {
                const data = await res.json();
                const list = data.data || [];
                renderTimeSlots(list);
                if (els.countTimeSlots) els.countTimeSlots.textContent = list.length;
            }
        } catch (error) {
            console.error('Error loading time slots', error);
        }
    }

    function renderHolidays(holidays) {
        if (!els.holidaysTable) return;
        els.holidaysTable.innerHTML = '';

        if (holidays.length === 0) {
            els.holidaysTable.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-secondary">Chưa cấu hình ngày lễ nào.</td>
                </tr>
            `;
            return;
        }

        holidays.forEach((h, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'admin-row-divider';
            tr.innerHTML = `
                <td class="text-center text-white-50">${idx + 1}</td>
                <td class="fw-medium text-white">${escapeHtml(h.name)}</td>
                <td class="text-center"><span class="admin-badge admin-badge-blue">${escapeHtml(h.date)}</span></td>
                <td class="text-center">${h.year ? `<span class="admin-badge admin-badge-secondary">${escapeHtml(h.year)}</span>` : '<span class="text-white-50 small">Hàng năm</span>'}</td>
                <td class="text-end fw-medium text-danger">+${parseInt(h.surcharge).toLocaleString('vi-VN')} đ</td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input toggle-holiday-active admin-toggle-pointer" type="checkbox" role="switch"
                            data-id="${h.id}" ${h.status ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <div class="admin-table-actions justify-content-center">
                        <button type="button" class="btn btn-sm btn-edit-holiday admin-table-action-edit" data-holiday='${JSON.stringify(h)}' title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-holiday admin-table-action-delete" data-id="${h.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.holidaysTable.appendChild(tr);
        });
    }

    function renderDayRules(rules) {
        if (!els.dayRulesTable) return;
        els.dayRulesTable.innerHTML = '';

        rules.forEach((r) => {
            const tr = document.createElement('tr');
            tr.className = 'admin-row-divider';
            tr.innerHTML = `
                <td class="fw-medium text-white py-3">${dayLabels[r.day_of_week]}</td>
                <td>
                    <input type="hidden" name="rules[${r.day_of_week}][day_of_week]" value="${r.day_of_week}">
                    <select name="rules[${r.day_of_week}][day_type]" class="admin-filter-select filter-select-md" style="max-width: 250px;">
                        <option value="weekday" ${r.day_type === 'weekday' ? 'selected' : ''}>Ngày thường (weekday)</option>
                        <option value="weekend" ${r.day_type === 'weekend' ? 'selected' : ''}>Cuối tuần (weekend)</option>
                        <option value="happy_day" ${r.day_type === 'happy_day' ? 'selected' : ''}>Ngày hội ngộ (happy_day)</option>
                    </select>
                </td>
                <td>
                    <div class="d-flex justify-content-end align-items-center">
                        <input type="number" name="rules[${r.day_of_week}][surcharge]" class="admin-filter-input text-end" style="max-width: 180px;" min="0" value="${r.surcharge}">
                        <span class="ms-2 text-white-50">đ</span>
                    </div>
                </td>
            `;
            els.dayRulesTable.appendChild(tr);
        });
    }

    function renderTimeSlots(slots) {
        if (!els.timeSlotsTable) return;
        els.timeSlotsTable.innerHTML = '';

        if (slots.length === 0) {
            els.timeSlotsTable.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-secondary">Chưa cấu hình khung giờ nào.</td>
                </tr>
            `;
            return;
        }

        slots.forEach((s, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'admin-row-divider';
            tr.innerHTML = `
                <td class="text-center text-white-50">${idx + 1}</td>
                <td class="fw-medium text-white">${escapeHtml(s.name)}</td>
                <td class="text-center"><span class="admin-badge admin-badge-code">${escapeHtml(s.start_time)}</span></td>
                <td class="text-center"><span class="admin-badge admin-badge-code">${escapeHtml(s.end_time)}</span></td>
                <td class="text-end fw-medium text-danger">+${parseInt(s.surcharge).toLocaleString('vi-VN')} đ</td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input toggle-timeslot-active admin-toggle-pointer" type="checkbox" role="switch"
                            data-id="${s.id}" ${s.status ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <div class="admin-table-actions justify-content-center">
                        <button type="button" class="btn btn-sm btn-edit-timeslot admin-table-action-edit" data-slot='${JSON.stringify(s)}' title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-timeslot admin-table-action-delete" data-id="${s.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.timeSlotsTable.appendChild(tr);
        });
    }

    // ─── Holidays Actions ────────────────────────────────────────────────

    function resetHolidayForm() {
        if (!els.holidayForm) return;
        els.holidayForm.reset();
        els.holidayFormMethod.value = 'POST';
        els.holidayIdInput.value = '';
        els.holidayModalLabel.innerHTML = '<i class="bi bi-calendar-event me-2 admin-accent-icon"></i>Tạo ngày lễ mới';
        const statusCheck = document.getElementById('holidayStatus');
        if (statusCheck) statusCheck.checked = true;
    }



    if (els.holidayForm) {
        els.holidayForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.holidayFormMethod.value === 'PUT';
            const id = els.holidayIdInput.value;
            const url = isEdit ? `/api/v1/admin/pricing-rules/holidays/${id}` : '/api/v1/admin/pricing-rules/holidays';

            const formData = new FormData(els.holidayForm);
            const data = Object.fromEntries(formData.entries());
            data.status = document.getElementById('holidayStatus')?.checked ? 1 : 0;
            if (!data.year) delete data.year;

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(data)
                });

                if (res && res.ok) {
                    getModalInstance(els.holidayModalEl)?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật ngày lễ thành công!' : 'Tạo ngày lễ thành công!', 'success');
                    loadHolidays();
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Lưu thất bại', 'danger');
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    if (els.holidaysTable) {
        els.holidaysTable.addEventListener('click', async (e) => {
            const btnEdit = e.target.closest('.btn-edit-holiday');
            if (btnEdit) {
                resetHolidayForm();
                els.holidayFormMethod.value = 'PUT';
                els.holidayModalLabel.innerHTML = '<i class="bi bi-calendar-event me-2 admin-accent-icon"></i>Cập nhật ngày lễ';

                const h = JSON.parse(btnEdit.dataset.holiday);
                els.holidayIdInput.value = h.id;
                document.getElementById('holidayName').value = h.name;
                document.getElementById('holidayDate').value = h.date;
                document.getElementById('holidayYear').value = h.year || '';
                document.getElementById('holidaySurcharge').value = h.surcharge;
                const statusCheck = document.getElementById('holidayStatus');
                if (statusCheck) statusCheck.checked = h.status === 1;

                getModalInstance(els.holidayModalEl)?.show();
                return;
            }

            const btnDelete = e.target.closest('.btn-delete-holiday');
            if (btnDelete) {
                if (!confirm('Bạn có chắc muốn xóa ngày lễ này?')) return;
                try {
                    const res = await window.AdminCore.apiFetch(`/api/v1/admin/pricing-rules/holidays/${btnDelete.dataset.id}`, {
                        method: 'DELETE'
                    });
                    if (res && res.ok) {
                        window.showAdminToast?.('Xóa ngày lễ thành công!', 'success');
                        loadHolidays();
                    } else {
                        const err = await res.json();
                        window.showAdminToast?.(err.message || 'Xóa thất bại', 'danger');
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        });

        els.holidaysTable.addEventListener('change', async (e) => {
            const toggle = e.target.closest('.toggle-holiday-active');
            if (toggle) {
                const id = toggle.getAttribute('data-id');
                const isActive = toggle.checked;
                try {
                    const res = await window.AdminCore.apiFetch(`/api/v1/admin/pricing-rules/holidays/${id}/toggle-active`, {
                        method: 'POST',
                        body: JSON.stringify({ status: isActive ? 1 : 0 }),
                    });
                    if (!res) throw new Error('Không thể kết nối đến máy chủ.');
                    if (!res.ok) {
                        const errData = await res.json().catch(() => ({}));
                        throw new Error(errData.message || 'Cập nhật trạng thái thất bại.');
                    }
                    const result = await res.json();
                    window.showAdminToast?.(result.message || 'Cập nhật trạng thái thành công', 'success');
                    loadHolidays();
                } catch (error) {
                    window.showAdminToast?.(error.message || 'Cập nhật trạng thái thất bại', 'danger');
                    toggle.checked = !isActive;
                }
            }
        });
    }

    // ─── Day Rules Actions ───────────────────────────────────────────────

    if (els.dayRulesForm) {
        els.dayRulesForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(els.dayRulesForm);
            
            // Reconstruct array structure from form data
            const rules = [];
            for (let i = 0; i <= 6; i++) {
                rules.push({
                    day_of_week: parseInt(formData.get(`rules[${i}][day_of_week]`)),
                    day_type: formData.get(`rules[${i}][day_type]`),
                    surcharge: parseInt(formData.get(`rules[${i}][surcharge]`))
                });
            }

            try {
                const res = await window.AdminCore.apiFetch('/api/v1/admin/pricing-rules/day-rules', {
                    method: 'PUT',
                    body: JSON.stringify({ rules })
                });

                if (res && res.ok) {
                    window.showAdminToast?.('Cập nhật quy tắc ngày thành công!', 'success');
                    loadDayRules();
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Cập nhật thất bại', 'danger');
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    // ─── Time Slots Actions ──────────────────────────────────────────────

    function resetTimeSlotForm() {
        if (!els.timeSlotForm) return;
        els.timeSlotForm.reset();
        els.timeSlotFormMethod.value = 'POST';
        els.timeSlotIdInput.value = '';
        els.timeSlotModalLabel.innerHTML = '<i class="bi bi-clock me-2 admin-accent-icon"></i>Tạo khung giờ mới';
        const statusCheck = document.getElementById('timeSlotStatus');
        if (statusCheck) statusCheck.checked = true;
    }



    if (els.timeSlotForm) {
        els.timeSlotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.timeSlotFormMethod.value === 'PUT';
            const id = els.timeSlotIdInput.value;
            const url = isEdit ? `/api/v1/admin/pricing-rules/time-slots/${id}` : '/api/v1/admin/pricing-rules/time-slots';

            const formData = new FormData(els.timeSlotForm);
            const data = Object.fromEntries(formData.entries());
            data.status = document.getElementById('timeSlotStatus')?.checked ? 1 : 0;

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(data)
                });

                if (res && res.ok) {
                    getModalInstance(els.timeSlotModalEl)?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật khung giờ thành công!' : 'Tạo khung giờ thành công!', 'success');
                    loadTimeSlots();
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Lưu thất bại', 'danger');
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    if (els.timeSlotsTable) {
        els.timeSlotsTable.addEventListener('click', async (e) => {
            const btnEdit = e.target.closest('.btn-edit-timeslot');
            if (btnEdit) {
                resetTimeSlotForm();
                els.timeSlotFormMethod.value = 'PUT';
                els.timeSlotModalLabel.innerHTML = '<i class="bi bi-clock me-2 admin-accent-icon"></i>Cập nhật khung giờ';

                const s = JSON.parse(btnEdit.dataset.slot);
                els.timeSlotIdInput.value = s.id;
                document.getElementById('timeSlotName').value = s.name;
                document.getElementById('timeSlotStart').value = s.start_time;
                document.getElementById('timeSlotEnd').value = s.end_time;
                document.getElementById('timeSlotSurcharge').value = s.surcharge;
                const statusCheck = document.getElementById('timeSlotStatus');
                if (statusCheck) statusCheck.checked = s.status === 1;

                getModalInstance(els.timeSlotModalEl)?.show();
                return;
            }

            const btnDelete = e.target.closest('.btn-delete-timeslot');
            if (btnDelete) {
                if (!confirm('Bạn có chắc muốn xóa khung giờ này?')) return;
                try {
                    const res = await window.AdminCore.apiFetch(`/api/v1/admin/pricing-rules/time-slots/${btnDelete.dataset.id}`, {
                        method: 'DELETE'
                    });
                    if (res && res.ok) {
                        window.showAdminToast?.('Xóa khung giờ thành công!', 'success');
                        loadTimeSlots();
                    } else {
                        const err = await res.json();
                        window.showAdminToast?.(err.message || 'Xóa thất bại', 'danger');
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        });

        els.timeSlotsTable.addEventListener('change', async (e) => {
            const toggle = e.target.closest('.toggle-timeslot-active');
            if (toggle) {
                const id = toggle.getAttribute('data-id');
                const isActive = toggle.checked;
                try {
                    const res = await window.AdminCore.apiFetch(`/api/v1/admin/pricing-rules/time-slots/${id}/toggle-active`, {
                        method: 'POST',
                        body: JSON.stringify({ status: isActive ? 1 : 0 }),
                    });
                    if (!res) throw new Error('Không thể kết nối đến máy chủ.');
                    if (!res.ok) {
                        const errData = await res.json().catch(() => ({}));
                        throw new Error(errData.message || 'Cập nhật trạng thái thất bại.');
                    }
                    const result = await res.json();
                    window.showAdminToast?.(result.message || 'Cập nhật trạng thái thành công', 'success');
                    loadTimeSlots();
                } catch (error) {
                    window.showAdminToast?.(error.message || 'Cập nhật trạng thái thất bại', 'danger');
                    toggle.checked = !isActive;
                }
            }
        });
    }

    // ─── Header Buttons & Modal Openers ──────────────────────────────────

    const HEADER_BTNS = {
        'pane-holidays': `<button type="button" class="btn-primary-custom border-0" id="btnCreateHoliday">
            <i class="bi bi-plus-lg"></i> Thêm ngày lễ
        </button>`,
        'pane-dayrules': ``,
        'pane-timeslots': `<button type="button" class="btn-primary-custom border-0" id="btnCreateTimeSlot">
            <i class="bi bi-plus-lg"></i> Thêm khung giờ
        </button>`,
    };

    function injectHeaderBtn(tabId) {
        const container = document.getElementById('tabHeaderActions');
        if (!container) return;
        container.innerHTML = HEADER_BTNS[tabId] || '';
        bindModalOpeners();
    }

    function bindModalOpeners() {
        document.getElementById('btnCreateHoliday')?.addEventListener('click', () => {
            resetHolidayForm();
            getModalInstance(els.holidayModalEl)?.show();
        });
        document.getElementById('btnCreateTimeSlot')?.addEventListener('click', () => {
            resetTimeSlotForm();
            getModalInstance(els.timeSlotModalEl)?.show();
        });
    }

    // ─── Initialization ──────────────────────────────────────────────────

    window.onAdminPageLoad(() => {
        loadHolidays();
        loadDayRules();
        loadTimeSlots();

        // Bind tab change listener
        const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabEls.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const targetId = e.target.getAttribute('data-bs-target')?.substring(1);
                injectHeaderBtn(targetId);
            });
        });

        // Initial header btn
        const activeTab = document.querySelector('.tab-pane.active')?.id;
        injectHeaderBtn(activeTab || 'pane-holidays');
    });

})();
