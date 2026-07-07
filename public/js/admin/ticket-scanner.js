/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TICKET SCANNER MODULE
 * QR Code / Barcode scanner for ticket verification with html5-qrcode
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    let html5QrcodeScanner = null;
    let scannerModal = null;
    let isScanning = false;

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
        document.getElementById('scanTicketBtn')?.addEventListener('click', openScanner);
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
        if (isScanning) return;

        // Clear previous scanner if exists
        const readerElement = document.getElementById('scannerVideo');
        readerElement.innerHTML = '';

        // Config for html5-qrcode
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            disableFlip: false,
            videoConstraints: {
                facingMode: { ideal: "environment" }
            }
        };

        html5QrcodeScanner = new Html5Qrcode("scannerVideo");

        html5QrcodeScanner.start(
            { facingMode: "environment" }, // Use back camera
            config,
            onScanSuccess,
            onScanFailure
        ).then(() => {
            isScanning = true;
        }).catch(err => {
            console.error('Camera start error:', err);
            showResult('error', 'Không thể truy cập camera: ' + err);
            // Fallback to manual mode
            setTimeout(() => showManualMode(), 2000);
        });
    }

    function stopCamera() {
        if (html5QrcodeScanner && isScanning) {
            html5QrcodeScanner.stop().then(() => {
                isScanning = false;
                html5QrcodeScanner = null;
            }).catch(err => {
                console.error('Camera stop error:', err);
                isScanning = false;
                html5QrcodeScanner = null;
            });
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        // QR code detected successfully
        console.log('QR Code detected:', decodedText);

        // Stop scanner
        stopCamera();

        // Show manual mode with detected code
        showManualMode();
        document.getElementById('ticketCodeInput').value = decodedText;

        // Auto verify
        verifyTicket();
    }

    function onScanFailure(error) {
        // Handle scan failure silently (camera is continuously scanning)
        // console.warn('QR scan error:', error);
    }

    async function verifyTicket() {
        const input = document.getElementById('ticketCodeInput');
        const code = input.value.trim();

        if (!code) {
            showResult('warning', 'Vui lòng nhập mã vé');
            return;
        }

        // Show loading
        const btn = document.getElementById('verifyTicketBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xác thực...';

        try {
            console.log('[Ticket Scanner] Verifying ticket:', code);

            const token = localStorage.getItem('access_token');
            console.log('[Ticket Scanner] Token exists:', !!token);

            const response = await fetch('/api/v1/admin/tickets/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Authorization': `Bearer ${token || ''}`
                },
                body: JSON.stringify({ ticket_code: code })
            });

            console.log('[Ticket Scanner] Response status:', response.status);

            const data = await response.json();
            console.log('[Ticket Scanner] Response data:', data);

            if (response.ok && data.success) {
                showResult('success', 'Xác thực thành công!', data.data);
                input.value = '';

                // Play success sound (optional)
                playSound('success');
            } else {
                const errorMsg = data.message || data.error || 'Vé không hợp lệ';
                console.error('[Ticket Scanner] Error:', errorMsg);
                showResult('error', errorMsg);
                playSound('error');
            }
        } catch (error) {
            console.error('[Ticket Scanner] Exception:', error);
            showResult('error', 'Lỗi kết nối: ' + error.message);
            playSound('error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function showResult(type, message, ticketData = null) {
        const resultDiv = document.getElementById('scanResult');
        let html = '';

        if (type === 'success' && ticketData) {
            html = `
                <div class="alert alert-success">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
                        <div>
                            <h6 class="mb-0 text-success">✓ Vé Hợp Lệ</h6>
                            <small class="text-muted">${message}</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-2 small">
                        <div class="col-4"><strong>Mã vé:</strong></div>
                        <div class="col-8"><code class="text-success">${ticketData.code || 'N/A'}</code></div>

                        <div class="col-4"><strong>Phim:</strong></div>
                        <div class="col-8">${ticketData.movie || 'N/A'}</div>

                        <div class="col-4"><strong>Suất chiếu:</strong></div>
                        <div class="col-8"><i class="bi bi-calendar3 me-1"></i>${ticketData.showtime || 'N/A'}</div>

                        <div class="col-4"><strong>Ghế:</strong></div>
                        <div class="col-8"><i class="bi bi-chair me-1"></i>${ticketData.seat || 'N/A'}</div>

                        <div class="col-4"><strong>Phòng:</strong></div>
                        <div class="col-8">${ticketData.screen || 'N/A'}</div>

                        <div class="col-4"><strong>Rạp:</strong></div>
                        <div class="col-8">${ticketData.theater || 'N/A'}</div>

                        <div class="col-4"><strong>Chi nhánh:</strong></div>
                        <div class="col-8">${ticketData.branch || 'N/A'}</div>

                        <div class="col-4"><strong>Xác thực:</strong></div>
                        <div class="col-8"><span class="badge bg-success">${ticketData.verified_at || 'Vừa xong'}</span></div>
                    </div>
                </div>
            `;
        } else if (type === 'error') {
            html = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle-fill me-2"></i><strong>Lỗi:</strong> ${message}
                </div>
            `;
        } else if (type === 'warning') {
            html = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>${message}
                </div>
            `;
        }

        resultDiv.innerHTML = html;
        resultDiv.style.display = 'block';

        // Auto-hide warnings/errors after 5 seconds
        if (type !== 'success') {
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 5000);
        }
    }

    function playSound(type) {
        // Optional: Add audio feedback
        try {
            const audio = new Audio();
            if (type === 'success') {
                // Use system beep or custom sound
                audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZSA0PVKzn77BdGAg+ltryxnMpBSh+zPLaizsIGGS57OihUBELTKXh8bllHAU2jdXzzn0vBSF1xe/eizQHGme47+OZSA0PVKzn77BdGAg+ltryxnMpBSh+zPLaizsIGGS57OihUBELTKXh8bllHAU2jdXzzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU2jdXyzn0vBSF1xe/eizQHGme47+OgPxwMIGS37O6jVRQLR5zg77phHAU=';
            } else {
                audio.src = 'data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQ==';
            }
            audio.play().catch(e => console.log('Sound play failed:', e));
        } catch (e) {
            // Ignore sound errors
        }
    }

    function cleanup() {
        stopCamera();
        document.getElementById('ticketCodeInput').value = '';
        document.getElementById('scanResult').style.display = 'none';
        showManualMode();
    }

    // Expose globally for debugging
    window.ticketScanner = {
        open: openScanner,
        verify: verifyTicket,
        showCamera: showCameraMode,
        showManual: showManualMode
    };
})();