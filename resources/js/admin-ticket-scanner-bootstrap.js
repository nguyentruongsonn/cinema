import jsQR from 'jsqr';

window.__ticketQrDecoder = jsQR;

const scannerScript = document.createElement('script');
scannerScript.src = `/js/admin/ticket-scanner.js?v=${encodeURIComponent(window.APP_CONFIG?.assetVersion || '1')}`;
scannerScript.defer = true;
document.head.appendChild(scannerScript);
