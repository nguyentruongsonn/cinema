(function () {
    'use strict';

    const body = document.getElementById('concessionOrdersBody');
    const empty = document.getElementById('concessionOrdersEmpty');
    const refresh = document.getElementById('refreshConcessionOrders');

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
    }

    async function load() {
        if (!body) return;
        body.innerHTML = '<tr><td colspan="5" class="text-center py-5">Đang tải dữ liệu...</td></tr>';
        try {
            const response = await window.AdminCore.apiFetch('/api/v1/staff/concessions/orders/pending?per_page=50', { requestKey: 'staff:concessions:pending' });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Không thể tải đơn hàng.');
            const items = Array.isArray(payload.data) ? payload.data : [];
            empty?.classList.toggle('d-none', items.length > 0);
            body.innerHTML = items.length ? items.map((item) => {
                const name = item.item?.name || item.metadata?.name || 'Mặt hàng';
                return `<tr><td><strong>${escapeHtml(item.order?.code)}</strong></td><td>${escapeHtml(name)}<div class="staff-concession-status">Chờ giao</div></td><td>${escapeHtml(item.quantity)}</td><td>${escapeHtml(item.order?.created_at || '')}</td><td class="text-end"><button class="btn btn-sm btn-primary fulfill-concession" data-item-id="${escapeHtml(item.id)}">Đã giao</button></td></tr>`;
            }).join('') : '<tr><td colspan="5" class="text-center py-5 text-secondary">Không có mặt hàng đang chờ giao.</td></tr>';
        } catch (error) {
            body.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-danger">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    body?.addEventListener('click', async (event) => {
        const button = event.target.closest('.fulfill-concession');
        if (!button || button.disabled) return;
        button.disabled = true;
        try {
            const response = await window.AdminCore.apiFetch(`/api/v1/staff/concessions/items/${button.dataset.itemId}/fulfill`, { method: 'POST' });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Không thể xác nhận giao hàng.');
            window.showAdminToast?.('Đã xác nhận giao concession.', 'success');
            await load();
        } catch (error) {
            button.disabled = false;
            window.showAdminToast?.(error.message, 'error');
        }
    });

    refresh?.addEventListener('click', load);
    load();
})();
