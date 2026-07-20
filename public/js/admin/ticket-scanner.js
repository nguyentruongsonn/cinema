/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TICKET SCANNER MODULE
 * QR Code / Barcode scanner for ticket verification
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    let stream = null;
    let scanning = false;
    let scannerModal = null;
    let animationFrame = null;
    let lastScanAt = 0;
    let cameraStartPromise = null;
    let cameraGeneration = 0;
    let verificationPromise = null;
    let verificationController = null;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Get modal instance
        const modalEl = document.getElementById('ticketScannerModal');
        if (!modalEl) return;

        scannerModal = new bootstrap.Modal(modalEl);

        // Button event listeners
        document.addEventListener('click', (event) => {
            if (event.target.closest('#scanTicketBtn')) openScanner();
        });
        document.getElementById('cameraScanBtn')?.addEventListener('click', showCameraMode);
        document.getElementById('manualScanBtn')?.addEventListener('click', showManualMode);
        document.getElementById('verifyTicketBtn')?.addEventListener('click', verifyTicket);

        // Enter key on input
        document.getElementById('ticketCodeInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyTicket();
            }
        });

        // Cleanup on modal close
        modalEl.addEventListener('hidden.bs.modal', cleanup);
    }

    function openScanner() {
        scannerModal.show();
        // Focus input when modal opens
        setTimeout(() => {
            document.getElementById('ticketCodeInput')?.focus();
        }, 500);
    }

    function showCameraMode() {
        document.getElementById('manualScanBtn').classList.remove('active');
        document.getElementById('cameraScanBtn').classList.add('active');
        document.getElementById('manualScanner').style.display = 'none';
        document.getElementById('cameraScanner').style.display = 'block';
        document.getElementById('scanResult').style.display = 'none';
        startCamera();
    }

    function showManualMode() {
        document.getElementById('cameraScanBtn').classList.remove('active');
        document.getElementById('manualScanBtn').classList.add('active');
        document.getElementById('cameraScanner').style.display = 'none';
        document.getElementById('manualScanner').style.display = 'block';
        document.getElementById('scanResult').style.display = 'none';
        stopCamera();
        document.getElementById('ticketCodeInput')?.focus();
    }

    function startCamera() {
        if (stream || scanning) return Promise.resolve(stream);
        if (cameraStartPromise) return cameraStartPromise;

        const generation = ++cameraGeneration;
        cameraStartPromise = navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        }).then((newStream) => {
            if (generation !== cameraGeneration) {
                newStream.getTracks().forEach(track => track.stop());
                return null;
            }
            stream = newStream;
            const video = document.getElementById('scannerVideo');
            video.srcObject = stream;
            scanning = true;
            scanForCode();
            return stream;
        }).catch((err) => {
            if (generation === cameraGeneration) {
                showResult('error', 'Không thể truy cập camera: ' + err.message);
                showManualMode();
            }
            return null;
        }).finally(() => {
            cameraStartPromise = null;
        });

        return cameraStartPromise;
    }

    function stopCamera() {
        cameraGeneration++;
        scanning = false;
        if (animationFrame) cancelAnimationFrame(animationFrame);
        animationFrame = null;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    }

    function scanForCode() {
        if (!scanning) return;

        const video = document.getElementById('scannerVideo');
        const canvas = document.getElementById('scannerCanvas');
        const context = canvas.getContext('2d');

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.height = video.videoHeight;
            canvas.width = video.videoWidth;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            if (window.__ticketQrDecoder && Date.now() - lastScanAt >= 150) {
                lastScanAt = Date.now();
                const result = window.__ticketQrDecoder(imageData.data, imageData.width, imageData.height);
                if (result?.data) {
                    scanning = false;
                    stopCamera();
                    document.getElementById('ticketCodeInput').value = result.data.trim();
                    verifyTicket();
                    return;
                }
            }
        }

        animationFrame = requestAnimationFrame(scanForCode);
    }

    function verifyTicket() {
        if (verificationPromise) return verificationPromise;
        verificationPromise = verifyTicketRequest().finally(() => {
            verificationPromise = null;
            verificationController = null;
        });
        return verificationPromise;
    }

    async function verifyTicketRequest() {
        const input = document.getElementById('ticketCodeInput');
        const code = input.value.trim();

        if (!code) {
            showResult('warning', 'Vui lòng nhập mã vé');
            return;
        }

        // Show loading
        const btn = document.getElementById('verifyTicketBtn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Đang xác thực...';
        verificationController = new AbortController();

        try {
            const response = await fetch('/api/v1/admin/tickets/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include',
                signal: verificationController.signal,
                body: JSON.stringify({ ticket_code: code })
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                showResult('success', 'Xác thực thành công!', data.data);
                input.value = '';
            } else {
                showResult('error', data.message || 'Vé không hợp lệ');
            }
        } catch (error) {
            if (error.name !== 'AbortError') showResult('error', 'Lỗi kết nối: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    function showResult(type, message, ticketData = null) {
        const resultDiv = document.getElementById('scanResult');
        resultDiv.textContent = '';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'}`;
        const icon = document.createElement('i');
        icon.className = `bi ${type === 'success' ? 'bi-check-circle-fill' : type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill'} me-2`;
        alert.appendChild(icon);
        const text = document.createElement('span');
        text.textContent = String(message || '');
        alert.appendChild(text);
        if (type === 'success' && ticketData) {
            const details = document.createElement('div');
            details.className = 'row g-2 small mt-2';
            [['Mã vé', ticketData.code], ['Phim', ticketData.movie], ['Suất chiếu', ticketData.showtime], ['Ghế', ticketData.seat], ['Trạng thái', ticketData.status]].forEach(([label, value]) => {
                const labelNode = document.createElement('strong');
                labelNode.className = 'col-6';
                labelNode.textContent = label;
                const valueNode = document.createElement('span');
                valueNode.className = 'col-6';
                valueNode.textContent = String(value || 'N/A');
                details.append(labelNode, valueNode);
            });
            alert.appendChild(details);
        }
        resultDiv.appendChild(alert);
        resultDiv.style.display = 'block';

        // Auto-hide after 5 seconds (except success)
        if (type !== 'success') {
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 5000);
        }
    }

    function cleanup() {
        verificationController?.abort();
        stopCamera();
        document.getElementById('ticketCodeInput').value = '';
        document.getElementById('scanResult').style.display = 'none';
        showManualMode();
    }

    // Expose globally for debugging
    window.ticketScanner = {
        open: openScanner,
        verify: verifyTicket,
        startCamera,
        stopCamera,
        cleanup
    };
})();
