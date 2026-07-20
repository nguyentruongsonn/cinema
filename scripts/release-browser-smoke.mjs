import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { chromium } from 'playwright';

const host = process.env.BROWSER_SMOKE_HOST ?? '127.0.0.1';
const port = process.env.BROWSER_SMOKE_PORT ?? '8765';
const baseUrl = process.env.BROWSER_SMOKE_BASE_URL ?? `http://${host}:${port}`;
const screenshotDir = path.resolve('storage/app/release-browser-smoke');

const edgeCandidates = [
    process.env.EDGE_PATH,
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);

const publicPages = [
    { path: '/', responses: ['/api/v1/home'] },
    { path: '/movies', responses: ['/api/v1/movies'] },
    { path: '/theaters', responses: ['/api/v1/theaters/cities', '/api/v1/theaters'] },
    { path: '/prices', responses: ['/api/v1/prices'] },
    { path: '/login', responses: [] },
];

const publicApiEndpoints = [
    '/up',
    '/api/v1/home',
    '/api/v1/movies',
    '/api/v1/theaters/cities',
];

function findBrowserExecutable() {
    return edgeCandidates.find((candidate) => fs.existsSync(candidate));
}

function startServer() {
    const child = spawn('php', [
        'artisan',
        'serve',
        `--host=${host}`,
        `--port=${port}`,
    ], {
        cwd: process.cwd(),
        env: {
            ...process.env,
            REVERB_ENABLED: process.env.REVERB_ENABLED ?? 'false',
        },
        stdio: ['ignore', 'pipe', 'pipe'],
    });

    child.stdout.on('data', (chunk) => process.stdout.write(`[server] ${chunk}`));
    child.stderr.on('data', (chunk) => process.stderr.write(`[server] ${chunk}`));

    return child;
}

function stopServer(server) {
    if (!server || server.killed) return;

    if (process.platform === 'win32') {
        spawnSync('taskkill', ['/pid', String(server.pid), '/T', '/F'], {
            stdio: 'ignore',
            timeout: 10_000,
        });
    } else {
        server.kill('SIGTERM');
    }

    server.stdout?.destroy();
    server.stderr?.destroy();
    server.stdin?.destroy();
    server.unref();
}

async function fetchWithTimeout(url, options = {}, timeoutMs = 10_000) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);

    try {
        return await fetch(url, {
            ...options,
            signal: controller.signal,
        });
    } finally {
        clearTimeout(timeout);
    }
}

async function waitForHealthyServer(timeoutMs = 90_000) {
    const deadline = Date.now() + timeoutMs;
    let lastError;

    while (Date.now() < deadline) {
        try {
            const response = await fetchWithTimeout(new URL('/up', baseUrl), {}, 3_000);
            if (response.ok) {
                return;
            }
            lastError = new Error(`Health check returned ${response.status}`);
        } catch (error) {
            lastError = error;
        }

        await delay(500);
    }

    throw new Error(`Laravel server did not become healthy: ${lastError?.message ?? 'unknown error'}`);
}

async function checkApiEndpoint(endpoint) {
    const response = await fetchWithTimeout(new URL(endpoint, baseUrl), {
        headers: { Accept: 'application/json' },
    }, 10_000);

    if (response.status >= 500) {
        throw new Error(`${endpoint} returned ${response.status}`);
    }

    if (response.status >= 400 && endpoint !== '/up') {
        throw new Error(`${endpoint} returned unexpected client error ${response.status}`);
    }
}

async function runBrowserChecks() {
    fs.mkdirSync(screenshotDir, { recursive: true });

    const executablePath = findBrowserExecutable();
    const browser = await chromium.launch({
        headless: true,
        executablePath,
    });

    const context = await browser.newContext({
        baseURL: baseUrl,
        ignoreHTTPSErrors: true,
    });

    const failures = [];
    const warnings = [];

    try {
        const page = await context.newPage();

        page.on('pageerror', (error) => {
            failures.push(`Page error: ${error.message}`);
        });

        page.on('console', (message) => {
            if (message.type() === 'error') {
                warnings.push(`Console error on ${page.url()}: ${message.text()}`);
            }
        });

        page.on('response', (response) => {
            const status = response.status();
            const url = response.url();

            if (status >= 500) {
                failures.push(`HTTP ${status}: ${url}`);
            } else if (status >= 400) {
                warnings.push(`HTTP ${status}: ${url}`);
            }
        });

        for (const pageCheck of publicPages) {
            const expectedResponses = pageCheck.responses.map((fragment) => page
                .waitForResponse((response) => new URL(response.url()).pathname === fragment, { timeout: 15_000 })
                .then((response) => {
                    if (response.status() >= 500) {
                        failures.push(`${fragment} returned ${response.status()}`);
                    }
                })
                .catch((error) => {
                    failures.push(`${fragment} did not complete: ${error.message}`);
                }));

            const response = await page.goto(pageCheck.path, {
                waitUntil: 'domcontentloaded',
                timeout: 30_000,
            });

            if (!response || response.status() >= 400) {
                failures.push(`${pageCheck.path} returned ${response?.status() ?? 'no response'}`);
                continue;
            }

            await page.locator('body').waitFor({ timeout: 10_000 });
            const bodyText = await page.locator('body').innerText({ timeout: 10_000 });

            if (!bodyText.trim()) {
                failures.push(`${pageCheck.path} rendered an empty body`);
            }

            await Promise.all(expectedResponses);

            if (pageCheck.path === '/') {
                await page.screenshot({
                    path: path.join(screenshotDir, 'home.png'),
                    fullPage: true,
                });
            }
        }

        for (const endpoint of publicApiEndpoints) {
            try {
                await checkApiEndpoint(endpoint);
            } catch (error) {
                failures.push(error.message);
            }
        }
    } finally {
        await context.close();
        await browser.close();
    }

    if (warnings.length > 0) {
        console.warn('Browser smoke warnings:');
        warnings.slice(0, 25).forEach((warning) => console.warn(`- ${warning}`));
        if (warnings.length > 25) {
            console.warn(`- ...and ${warnings.length - 25} more warnings`);
        }
    }

    if (failures.length > 0) {
        throw new Error(`Browser smoke failed:\n${failures.map((failure) => `- ${failure}`).join('\n')}`);
    }

    console.log(`Browser smoke passed for ${publicPages.length} pages and ${publicApiEndpoints.length} endpoints.`);
    console.log(`Screenshot saved to ${path.join(screenshotDir, 'home.png')}`);
}

const server = process.env.BROWSER_SMOKE_BASE_URL ? null : startServer();

try {
    if (server) {
        await waitForHealthyServer();
    }

    await runBrowserChecks();
} finally {
    stopServer(server);
}
