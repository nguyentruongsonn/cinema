(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('auditLogsTableBody'),
        pagination: document.getElementById('auditPagination'),
        searchForm: document.getElementById('auditSearchForm'),
        search: document.getElementById('auditSearch'),
        typeFilter: document.getElementById('auditTypeFilter'),
        dateFrom: document.getElementById('auditDateFrom'),
        dateTo: document.getElementById('auditDateTo'),
        detailModal: document.getElementById('auditDetailModal'),
        detailMeta: document.getElementById('auditDetailMeta'),
        oldValues: document.getElementById('auditOldValues'),
        newValues: document.getElementById('auditNewValues'),
    };

    let currentPage = 1;
    let auditableTypesLoaded = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '---';
        return new Intl.DateTimeFormat('vi-VN', {
            dateStyle: 'short',
            timeStyle: 'medium',
        }).format(new Date(value));
    }

    function formatJson(value) {
        return JSON.stringify(value || {}, null, 2);
    }

    async function loadLogs(page = 1) {
        currentPage = page;

        try {
            if (window.renderAdminTableSkeleton) {
                window.renderAdminTableSkeleton(els.tableBody, 6, 6, false);
            }

            const url = new URL(window.location.origin + '/api/v1/admin/audit-logs');
            url.searchParams.set('page', page);
            url.searchParams.set('per_page', 20);
            if (els.search.value.trim()) url.searchParams.set('search', els.search.value.trim());
            if (els.typeFilter.value) url.searchParams.set('auditable_type', els.typeFilter.value);
            if (els.dateFrom.value) url.searchParams.set('date_from', els.dateFrom.value);
            if (els.dateTo.value) url.searchParams.set('date_to', els.dateTo.value);

            const response = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'audit-logs:list' });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(window.formatAdminErrors?.(payload.errors || payload.message) || 'Không thể tải audit logs.');
            }

            populateTypes(payload.meta?.auditable_types || []);
            renderRows(payload.data || []);
            window.AdminCore.renderAdminPagination(els.pagination, payload.pagination || {}, loadLogs);
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Audit log load error:', error);
            els.tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">Không thể tải audit logs.</td></tr>';
        }
    }

    function populateTypes(types) {
        if (auditableTypesLoaded) return;

        const options = [
            '<option value="">Tất cả đối tượng</option>',
            ...types.map((type) => `<option value="${escapeHtml(type)}">${escapeHtml(type)}</option>`),
        ];

        els.typeFilter.innerHTML = options.join('');
        auditableTypesLoaded = true;
    }

    function renderRows(logs) {
        if (!logs.length) {
            els.tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-secondary">Chưa có audit log phù hợp.</td></tr>';
            return;
        }

        els.tableBody.innerHTML = logs.map((log) => {
            const actor = log.actor ? `${escapeHtml(log.actor.name)}<div class="small text-secondary">${escapeHtml(log.actor.email)}</div>` : '<span class="text-secondary">System</span>';
            const target = `${escapeHtml(log.auditable_type)} #${escapeHtml(log.auditable_id)}`;

            return `
                <tr>
                    <td>${formatDate(log.created_at)}</td>
                    <td>${actor}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(log.action)}</span></td>
                    <td>${target}</td>
                    <td><code>${escapeHtml(log.request_id || '---')}</code></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-light btn-audit-detail" data-id="${log.id}">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function showDetail(id) {
        try {
            const response = await window.AdminCore.apiFetch(`/api/v1/admin/audit-logs/${id}`);
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Không thể tải chi tiết audit log.');
            }

            const log = payload.data;
            const actor = log.actor ? `${log.actor.name} (${log.actor.email})` : 'System';
            els.detailMeta.textContent = `${log.action} · ${log.auditable_type} #${log.auditable_id} · ${actor} · ${formatDate(log.created_at)}`;
            els.oldValues.textContent = formatJson(log.old_values);
            els.newValues.textContent = formatJson(log.new_values);
            bootstrap.Modal.getOrCreateInstance(els.detailModal).show();
        } catch (error) {
            window.showAdminToast?.(error.message || 'Không thể tải chi tiết audit log.', 'error');
        }
    }

    function bindEvents() {
        els.searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            loadLogs(1);
        });

        [els.typeFilter, els.dateFrom, els.dateTo].forEach((input) => {
            input.addEventListener('change', () => loadLogs(1));
        });

        els.tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-audit-detail');
            if (!button) return;
            showDetail(button.dataset.id);
        });
    }

    window.onAdminPageLoad(() => {
        bindEvents();
        loadLogs(currentPage);
    });
})();
