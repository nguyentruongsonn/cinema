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
    let barcodeDetector = null;
    let barcodeDetecting = false;
    let audioContext = null;

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
        barcodeDetector = createBarcodeDetector();

        // Scanner launch is lazy-loaded by the Vite bootstrap entry.
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
        document.getElementById('manualScanner').classList.add('d-none');
        document.getElementById('cameraScanner').classList.remove('d-none');
        document.getElementById('scanResult').classList.add('d-none');
        startCamera();
    }

    function showManualMode() {
        document.getElementById('cameraScanBtn').classList.remove('active');
        document.getElementById('manualScanBtn').classList.add('active');
        document.getElementById('cameraScanner').classList.add('d-none');
        document.getElementById('manualScanner').classList.remove('d-none');
        document.getElementById('scanResult').classList.add('d-none');
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
                    handleScannedCode(result.data);
                    return;
                }
            }
        }

        if (barcodeDetector && !barcodeDetecting && Date.now() - lastScanAt >= 250) {
            barcodeDetecting = true;
            barcodeDetector.detect(canvas)
                .then((codes) => {
                    if (!scanning || !codes?.length) return;
                    const value = codes[0]?.rawValue || codes[0]?.rawData;
                    if (value) {
                        lastScanAt = Date.now();
                        handleScannedCode(value);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    barcodeDetecting = false;
                });
        }

        animationFrame = requestAnimationFrame(scanForCode);
    }

    function handleScannedCode(value) {
        const code = normalizeScanCode(value);
        if (!code) return;
        scanning = false;
        stopCamera();
        document.getElementById('ticketCodeInput').value = code;
        verifyTicket();
    }


    function normalizeScanCode(rawValue) {
        const value = String(rawValue || '').trim();
        if (!value) return '';

        try {
            const parsed = JSON.parse(value);
            const parsedCode = parsed?.ticket_code || parsed?.booking_id;
            if (parsedCode) return String(parsedCode).trim();
        } catch (_) {}

        try {
            const url = new URL(value);
            const segments = url.pathname.split('/').filter(Boolean);
            const lastSegment = segments.at(-1);
            if (lastSegment) return decodeURIComponent(lastSegment).trim();
        } catch (_) {}

        const prefixedCode = value.match(/\b(TKT-[A-Z0-9_-]+)\b/i)?.[1];
        return (prefixedCode || value).trim().toUpperCase();
    }

    function createBarcodeDetector() {
        if (!('BarcodeDetector' in window)) return null;
        try {
            return new window.BarcodeDetector({
                formats: ['qr_code', 'code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf']
            });
        } catch (_) {
            try {
                return new window.BarcodeDetector();
            } catch (__) {
                return null;
            }
        }
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
        const code = normalizeScanCode(input.value);

        if (!code) {
            showResult('warning', 'Vui lòng nhập mã vé hoặc Booking ID.');
            return;
        }

        // Show loading
        const btn = document.getElementById('verifyTicketBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Đang xử lý...</span>';
        verificationController = new AbortController();

        try {
            const isTicket = /^TKT-/i.test(code);
            if (!isTicket && document.body.dataset.canPrintOrders !== 'true') {
                showResult('warning', 'Tài khoản này chỉ được soát vé, không có quyền tra cứu để in hóa đơn.');
                return;
            }
            const endpoint = isTicket
                ? '/api/v1/staff/tickets/verify'
                : '/api/v1/staff/orders/print-lookup';
            const body = isTicket ? { ticket_code: code } : { identifier: code };
            const response = await window.AdminCore.apiFetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                signal: verificationController.signal,
                body: JSON.stringify(body)
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                if (isTicket) {
                    showResult('success', 'Xác thực vé thành công!', data.data);
                } else {
                    showPrintableOrder(data.data);
                }
                input.value = '';
            } else {
                showResult('error', data.message || 'Mã vé hoặc Booking ID không hợp lệ');
            }
        } catch (error) {
            if (error.name !== 'AbortError') showResult('error', 'Lỗi kết nối: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
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
            const resultRows = [
                [ticketData.type === 'booking' ? 'Booking ID' : 'Mã vé', ticketData.code],
                ['Phim', ticketData.movie],
                ['Suất chiếu', ticketData.showtime],
                ['Ghế', ticketData.seat],
            ];
            if (ticketData.type === 'booking') resultRows.push(['Số vé', ticketData.ticket_count]);
            resultRows.push(['Trạng thái', ticketData.status]);
            resultRows.forEach(([label, value]) => {
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
        resultDiv.classList.remove('d-none');
        playScanSound(type === 'success' ? 'success' : 'error');

        // Auto-hide after 5 seconds (except success)
        if (type !== 'success') {
            setTimeout(() => {
                resultDiv.classList.add('d-none');
            }, 5000);
        }
    }

    function showPrintableOrder(order) {
        const resultDiv = document.getElementById('scanResult');
        resultDiv.textContent = '';

        const panel = document.createElement('section');
        panel.className = 'admin-scanner-print-result';

        const heading = document.createElement('div');
        heading.className = 'admin-scanner-print-heading';
        const icon = document.createElement('i');
        icon.className = 'bi bi-check-circle-fill';
        icon.setAttribute('aria-hidden', 'true');
        const headingCopy = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = 'Đơn hàng hợp lệ';
        const subtitle = document.createElement('span');
        subtitle.textContent = `Booking ID: ${order.code}`;
        headingCopy.append(title, subtitle);
        heading.append(icon, headingCopy);
        panel.appendChild(heading);

        const details = document.createElement('dl');
        details.className = 'admin-scanner-print-details';
        const rows = [
            ['Khách hàng', order.customer?.name || 'Khách vãng lai'],
            ['Phim', order.movie?.title || 'Đơn bắp nước'],
            ['Rạp / Phòng', [order.theater?.name, order.screen].filter(Boolean).join(' · ') || 'N/A'],
            ['Ghế', (order.tickets || []).map(ticket => ticket.seat_label).join(', ') || 'Không có'],
            ['Bắp nước', `${(order.concessions || []).length} loại`],
            ['Tổng tiền', new Intl.NumberFormat('vi-VN').format(Number(order.total_amount || 0)) + 'đ'],
        ];
        rows.forEach(([label, value]) => {
            const term = document.createElement('dt');
            term.textContent = label;
            const description = document.createElement('dd');
            description.textContent = value;
            details.append(term, description);
        });
        panel.appendChild(details);

        if (Number(order.print_count || 0) > 0) {
            const warning = document.createElement('p');
            warning.className = 'admin-scanner-reprint-warning';
            warning.textContent = `Đơn đã được yêu cầu in ${order.print_count} lần. Lần tiếp theo sẽ được ghi nhận là in lại.`;
            panel.appendChild(warning);
        }

        const actions = document.createElement('div');
        actions.className = 'admin-scanner-print-actions';
        const available = Array.isArray(order.available_sections) ? order.available_sections : [];
        const buttonDefinitions = [
            ['In tất cả', available, 'bi-printer-fill', 'primary'],
            ['In vé', ['tickets'], 'bi-ticket-perforated', 'secondary'],
            ['In bắp nước', ['concessions'], 'bi-cup-straw', 'secondary'],
            ['In hóa đơn', ['invoice'], 'bi-receipt', 'secondary'],
        ];

        buttonDefinitions.forEach(([label, sections, iconName, variant], index) => {
            const printableSections = sections.filter(section => available.includes(section));
            if (printableSections.length === 0) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `admin-scanner-print-button is-${variant}`;
            const buttonIcon = document.createElement('i');
            buttonIcon.className = `bi ${iconName}`;
            buttonIcon.setAttribute('aria-hidden', 'true');
            const buttonLabel = document.createElement('span');
            buttonLabel.textContent = label;
            button.append(buttonIcon, buttonLabel);
            if (index === 0) button.classList.add('is-wide');
            button.addEventListener('click', () => {
                if (!window.OrderPrinting) {
                    showResult('error', 'Chức năng in chưa sẵn sàng. Vui lòng tải lại trang.');
                    return;
                }
                window.OrderPrinting.open(order.id, printableSections, {
                    reason: Number(order.print_count || 0) > 0 ? 'In lại sau khi tra cứu QR hóa đơn' : '',
                });
            });
            actions.appendChild(button);
        });
        panel.appendChild(actions);

        resultDiv.appendChild(panel);
        resultDiv.classList.remove('d-none');
        playScanSound('success');
    }


    function playScanSound(type) {
        try {
            const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextCtor) return;
            audioContext ||= new AudioContextCtor();
            if (audioContext.state === 'suspended') audioContext.resume?.();

            const now = audioContext.currentTime;
            const gain = audioContext.createGain();
            gain.connect(audioContext.destination);
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(type === 'success' ? 0.08 : 0.11, now + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + (type === 'success' ? 0.22 : 0.34));

            const playTone = (frequency, start, duration) => {
                const osc = audioContext.createOscillator();
                osc.type = type === 'success' ? 'sine' : 'square';
                osc.frequency.setValueAtTime(frequency, start);
                osc.connect(gain);
                osc.start(start);
                osc.stop(start + duration);
            };

            if (type === 'success') {
                playTone(740, now, 0.08);
                playTone(988, now + 0.09, 0.12);
            } else {
                playTone(220, now, 0.14);
                playTone(165, now + 0.16, 0.16);
            }
        } catch (_) {}
    }

    function cleanup() {
        verificationController?.abort();
        stopCamera();
        document.getElementById('ticketCodeInput').value = '';
        document.getElementById('scanResult').classList.add('d-none');
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
