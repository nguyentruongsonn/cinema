import jsQR from 'jsqr';

window.__ticketQrDecoder = jsQR;

const scannerScript = document.createElement('script');
scannerScript.src = '/js/admin/ticket-scanner.js';
scannerScript.defer = true;
document.head.appendChild(scannerScript);
