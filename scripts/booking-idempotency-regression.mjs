import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium, firefox, webkit } from 'playwright';

const browserCandidates = [
    process.env.EDGE_PATH,
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);

const executablePath = browserCandidates.find((candidate) => fs.existsSync(candidate));
const source = fs.readFileSync(path.resolve('public/js/users/pages/booking.js'), 'utf8');
const browserName = process.env.PLAYWRIGHT_BROWSER ?? 'chromium';
const browserType = { chromium, firefox, webkit }[browserName];
const browser = await browserType.launch({ headless: true, ...(browserName === 'chromium' && executablePath ? { executablePath } : {}) });

try {
    const page = await browser.newPage();
    await page.setContent('<main></main>');
    await page.addScriptTag({ content: `${source}\nwindow.__BookingManager = BookingManager;` });

    const result = await page.evaluate(() => {
        const manager = Object.create(window.__BookingManager.prototype);
        const keys = ['00000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000000002'];
        manager.checkoutIntent = null;
        manager.getCurrentHoldId = () => 55;
        manager.createIdempotencyKey = () => keys.shift();

        const basePayload = {
            showtime_id: 12,
            items: [{ type: 'seat', id: 8, quantity: 1 }],
            voucher_code: null,
            points_used: 0,
        };
        const first = manager.getOrCreateCheckoutIntent(basePayload);
        const retry = manager.getOrCreateCheckoutIntent({ ...basePayload, items: [...basePayload.items] });
        const changed = manager.getOrCreateCheckoutIntent({ ...basePayload, points_used: 100 });

        return {
            firstKey: first.key,
            retryKey: retry.key,
            changedKey: changed.key,
            sameIntent: first === retry,
            changedIntent: retry !== changed,
        };
    });

    assert.equal(result.sameIntent, true);
    assert.equal(result.firstKey, result.retryKey);
    assert.equal(result.changedIntent, true);
    assert.notEqual(result.retryKey, result.changedKey);
    console.log('Booking idempotency browser regression passed.');
} finally {
    await browser.close();
}
