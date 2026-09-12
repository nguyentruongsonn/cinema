let scannerLoadPromise = null;

async function loadTicketScanner() {
    if (window.ticketScanner) {
        return window.ticketScanner;
    }

    if (scannerLoadPromise) {
        return scannerLoadPromise;
    }

    scannerLoadPromise = import('jsqr')
        .then(({ default: jsQR }) => {
            window.__ticketQrDecoder = jsQR;

            return new Promise((resolve, reject) => {
                const scannerScript = document.createElement('script');
                scannerScript.src = `/js/admin/ticket-scanner.js?v=${encodeURIComponent(window.APP_CONFIG?.assetVersion || '1')}`;
                scannerScript.addEventListener('load', () => resolve(window.ticketScanner), { once: true });
                scannerScript.addEventListener('error', () => reject(new Error('Không thể tải công cụ quét vé.')), { once: true });
                document.head.appendChild(scannerScript);
            });
        })
        .catch((error) => {
            scannerLoadPromise = null;
            throw error;
        });

    return scannerLoadPromise;
}

document.addEventListener('click', async (event) => {
    if (!event.target.closest('#scanTicketBtn')) {
        return;
    }

    event.preventDefault();

    try {
        const scanner = await loadTicketScanner();
        scanner?.open?.();
    } catch (error) {
        window.showAdminToast?.(error.message || 'Không thể mở công cụ quét vé.', 'error');
    }
});
