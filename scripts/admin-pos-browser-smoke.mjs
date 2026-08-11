import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const host = '127.0.0.1';
const port = Number(process.env.ADMIN_BROWSER_TEST_PORT || 8011);
const baseUrl = `http://${host}:${port}`;
const localEdge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const sqlitePath = path.resolve('database/browser-smoke.sqlite');
const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_URL: baseUrl,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: sqlitePath,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    SESSION_SECURE: 'false',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    BROADCAST_CONNECTION: 'log',
    REVERB_ENABLED: 'false',
    VITE_REVERB_ENABLED: 'false',
};

function run(command, args) {
    const result = spawnSync(command, args, {
        env,
        encoding: 'utf8',
        stdio: 'pipe',
        shell: false,
        windowsHide: true,
    });

    if (result.status !== 0) {
        throw new Error([
            `${command} ${args.join(' ')} failed with exit code ${result.status}`,
            result.stdout?.trim(),
            result.stderr?.trim(),
        ].filter(Boolean).join('\n'));
    }
}

function ensureSqliteFile() {
    fs.mkdirSync(path.dirname(sqlitePath), { recursive: true });
    fs.closeSync(fs.openSync(sqlitePath, 'w'));
}

async function waitForServer() {
    for (let attempt = 0; attempt < 50; attempt += 1) {
        try {
            const response = await fetch(baseUrl);
            if (response.ok) return;
        } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    throw new Error('Admin browser smoke server did not become ready.');
}

async function login(context) {
    const response = await context.request.post(`${baseUrl}/api/v1/auth/login`, {
        data: {
            login: 'admin@example.com',
            password: 'password',
            remember: false,
        },
        headers: { Accept: 'application/json' },
    });

    if (!response.ok()) {
        throw new Error(`Admin login failed: HTTP ${response.status()} ${await response.text()}`);
    }
}

async function assertStablePage(page, route, failures) {
    const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await page.waitForFunction(
        () => !document.querySelector('[aria-busy="true"], .admin-skeleton, .skeleton'),
        { timeout: 12000 }
    ).catch(() => failures.push(`${route} still shows loading skeletons`));
    await page.waitForTimeout(300);

    if (!response?.ok()) failures.push(`${route} returned ${response?.status()}`);
    if (page.url().includes('/login')) failures.push(`${route} redirected to login`);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) failures.push(`${route} has horizontal overflow`);

    const unlabeledButtons = await page.evaluate(() => [...document.querySelectorAll('button')]
        .filter(button => button.offsetParent !== null)
        .filter(button => !button.textContent.trim() && !button.getAttribute('aria-label') && !button.getAttribute('title'))
        .length);
    if (unlabeledButtons > 0) failures.push(`${route} has ${unlabeledButtons} visible unlabeled buttons`);
}

ensureSqliteFile();
run('php', ['artisan', 'migrate:fresh', '--seed', '--force']);

const server = spawn('php', ['-S', `${host}:${port}`, '-t', 'public', 'scripts/php-dev-router.php'], {
    env,
    stdio: 'ignore',
    shell: false,
    windowsHide: true,
});

try {
    await waitForServer();

    const browser = await chromium.launch(fs.existsSync(localEdge)
        ? { executablePath: localEdge, headless: true }
        : { headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    await login(context);

    const page = await context.newPage();
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    page.on('console', message => {
        const text = message.text();
        if (message.type() === 'error' && !text.includes('WebSocket connection') && !text.includes('ERR_BLOCKED_BY_CLIENT')) {
            failures.push(text);
        }
    });

    for (const route of [
        '/admin/dashboard',
        '/admin/revenue',
        '/admin/orders',
        '/admin/roles-permissions',
        '/pos',
    ]) {
        await assertStablePage(page, route, failures);
    }

    await browser.close();

    if (failures.length) {
        throw new Error([...new Set(failures)].join('\n'));
    }

    console.log('Admin/POS browser smoke passed.');
} finally {
    if (server.exitCode === null) {
        server.kill();
    }
}
