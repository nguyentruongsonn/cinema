import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium, firefox, webkit } from 'playwright';

const candidates = [
    process.env.EDGE_PATH,
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);
const executablePath = candidates.find((candidate) => fs.existsSync(candidate));
const browserName = process.env.PLAYWRIGHT_BROWSER ?? 'chromium';
const browserType = { chromium, firefox, webkit }[browserName];
const browser = await browserType.launch({ headless: true, ...(browserName === 'chromium' && executablePath ? { executablePath } : {}) });

try {
    const page = await browser.newPage();
    await page.setContent(`
        <div id="ticketScannerModal"></div><button id="scanTicketBtn"></button>
        <button id="cameraScanBtn"></button><button id="manualScanBtn"></button>
        <button id="verifyTicketBtn"></button><input id="ticketCodeInput">
        <div id="manualScanner"></div><div id="cameraScanner"></div>
        <video id="scannerVideo"></video><canvas id="scannerCanvas"></canvas>
        <div id="scanResult"></div>
    `);
    await page.evaluate(() => {
        window.bootstrap = { Modal: class { show() {} } };
        window.__verifyCalls = 0;
        window.fetch = async () => {
            window.__verifyCalls++;
            await new Promise(resolve => setTimeout(resolve, 25));
            return {
                ok: true,
                json: async () => ({
                    success: true,
                    data: {
                        code: '<img id="scanner-xss" src="invalid">',
                        movie: '<svg id="scanner-svg"></svg>',
                        showtime: 'Today',
                        seat: 'A1',
                        status: 'Valid',
                    },
                }),
            };
        };
    });
    await page.addScriptTag({ path: path.resolve('public/js/admin/ticket-scanner.js') });
    const verifyCalls = await page.evaluate(async () => {
        document.getElementById('ticketCodeInput').value = 'TICKET-123';
        await Promise.all([window.ticketScanner.verify(), window.ticketScanner.verify()]);
        return window.__verifyCalls;
    });
    await page.waitForFunction(() => document.querySelector('#scanResult .alert') !== null);

    const result = await page.evaluate(() => ({
        text: document.getElementById('scanResult').textContent,
        images: document.querySelectorAll('#scanResult img').length,
        svgs: document.querySelectorAll('#scanResult svg').length,
    }));
    assert.equal(result.images, 0);
    assert.equal(result.svgs, 0);
    assert.equal(verifyCalls, 1);
    assert.match(result.text, /scanner-xss/);
    assert.match(result.text, /scanner-svg/);

    const cameraCalls = await page.evaluate(async () => {
        let calls = 0;
        const fakeStream = { getTracks: () => [{ stop() {} }] };
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: { getUserMedia: async () => { calls++; await new Promise(resolve => setTimeout(resolve, 20)); return fakeStream; } },
        });
        await Promise.all([window.ticketScanner.startCamera(), window.ticketScanner.startCamera()]);
        window.ticketScanner.stopCamera();
        return calls;
    });
    assert.equal(cameraCalls, 1);
    console.log('Ticket scanner browser regression passed.');
} finally {
    await browser.close();
}
