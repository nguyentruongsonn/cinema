import { spawn } from 'node:child_process';
import fs from 'node:fs';
import { chromium } from 'playwright';

const host = '127.0.0.1';
const port = Number(process.env.BROWSER_TEST_PORT || 8010);
const baseUrl = `http://${host}:${port}`;
const localEdge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const server = spawn('php', ['-S', `${host}:${port}`, '-t', 'public', 'scripts/php-dev-router.php'], {
    stdio: 'ignore',
    shell: false,
    windowsHide: true,
});

async function waitForServer() {
    for (let attempt = 0; attempt < 40; attempt += 1) {
        try {
            const response = await fetch(baseUrl);
            if (response.ok) return;
        } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    throw new Error('Application server did not become ready.');
}

try {
    await waitForServer();
    const browser = await chromium.launch(fs.existsSync(localEdge) ? { executablePath: localEdge, headless: true } : { headless: true });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    page.on('console', message => {
        if (message.type() === 'error' && !message.text().includes('WebSocket connection')) failures.push(message.text());
    });
    for (const route of ['/', '/movies', '/posts', '/theaters', '/prices']) {
        const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
        await page.waitForFunction(
            () => !document.querySelector('[aria-busy="true"]'),
            { timeout: 10000 }
        ).catch(() => {});
        await page.waitForTimeout(200);
        if (!response?.ok()) failures.push(`${route} returned ${response?.status()}`);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        if (overflow) failures.push(`${route} has horizontal overflow`);
    }
    await browser.close();
    if (failures.length) throw new Error([...new Set(failures)].join('\n'));
    console.log('Release browser smoke passed.');
} finally {
    if (server.exitCode === null) {
        server.kill();
    }
}
