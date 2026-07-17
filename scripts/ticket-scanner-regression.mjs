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
        window.fetch = async () => ({
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
        });
    });
    await page.addScriptTag({ path: path.resolve('public/js/admin/ticket-scanner.js') });
    await page.evaluate(() => {
        document.getElementById('ticketCodeInput').value = 'TICKET-123';
        window.ticketScanner.verify();
    });
    await page.waitForFunction(() => document.querySelector('#scanResult .alert') !== null);

    const result = await page.evaluate(() => ({
        text: document.getElementById('scanResult').textContent,
        images: document.querySelectorAll('#scanResult img').length,
        svgs: document.querySelectorAll('#scanResult svg').length,
    }));
    assert.equal(result.images, 0);
    assert.equal(result.svgs, 0);
    assert.match(result.text, /scanner-xss/);
    assert.match(result.text, /scanner-svg/);
    console.log('Ticket scanner browser regression passed.');
} finally {
    await browser.close();
}
