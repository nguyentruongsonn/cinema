(function (window) {
    'use strict';

    function open(orderId, sections = ['invoice', 'tickets', 'concessions'], options = {}) {
        const normalizedId = Number.parseInt(orderId, 10);
        if (!Number.isFinite(normalizedId) || normalizedId < 1) {
            window.showAdminToast?.('Không xác định được đơn hàng cần in.', 'danger');
            return null;
        }

        const selectedSections = [...new Set((Array.isArray(sections) ? sections : [sections])
            .map(value => String(value || '').trim())
            .filter(value => ['invoice', 'tickets', 'concessions'].includes(value)))];

        if (selectedSections.length === 0) {
            window.showAdminToast?.('Đơn hàng không có nội dung phù hợp để in.', 'warning');
            return null;
        }

        const url = new URL(`/staff/orders/${normalizedId}/print`, window.location.origin);
        url.searchParams.set('sections', selectedSections.join(','));
        if (options.reason) url.searchParams.set('reason', String(options.reason).slice(0, 255));

        const printWindow = window.open(url.toString(), `cinema-print-${normalizedId}`, 'noopener,noreferrer');
        if (!printWindow) {
            window.showAdminToast?.('Trình duyệt đang chặn cửa sổ in. Vui lòng cho phép popup và thử lại.', 'warning');
        }

        return printWindow;
    }

    window.OrderPrinting = { open };
})(window);
