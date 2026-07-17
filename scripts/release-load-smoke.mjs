import { spawn, spawnSync } from 'node:child_process';
import { performance } from 'node:perf_hooks';
import { setTimeout as delay } from 'node:timers/promises';

const host = process.env.LOAD_SMOKE_HOST ?? '127.0.0.1';
const port = process.env.LOAD_SMOKE_PORT ?? '8766';
const baseUrl = process.env.LOAD_SMOKE_BASE_URL ?? `http://${host}:${port}`;
const concurrency = Number.parseInt(process.env.LOAD_SMOKE_CONCURRENCY ?? '4', 10);
const durationMs = Number.parseInt(process.env.LOAD_SMOKE_DURATION_MS ?? '15000', 10);
const p95ThresholdMs = Number.parseInt(process.env.LOAD_SMOKE_P95_THRESHOLD_MS ?? '5000', 10);

const endpoints = [
    '/',
    '/movies',
    '/theaters',
    '/prices',
    '/api/v1/home',
    '/api/v1/movies',
    '/api/v1/theaters/cities',
    '/api/v1/prices',
];

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

async function fetchWithTimeout(url, timeoutMs = 10_000) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);

    try {
        return await fetch(url, {
            headers: { Accept: 'text/html,application/json' },
            signal: controller.signal,
        });
    } finally {
        clearTimeout(timeout);
    }
}

async function waitForHealthyServer(timeoutMs = 45_000) {
    const deadline = Date.now() + timeoutMs;
    let lastError;

    while (Date.now() < deadline) {
        try {
            const response = await fetchWithTimeout(new URL('/up', baseUrl), 3_000);
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

async function hit(endpoint, timings, failures) {
    const startedAt = performance.now();

    try {
        const response = await fetchWithTimeout(new URL(endpoint, baseUrl), 10_000);
        const duration = performance.now() - startedAt;
        timings.push(duration);

        if (response.status >= 500) {
            failures.push(`${endpoint} returned HTTP ${response.status}`);
        }
    } catch (error) {
        const duration = performance.now() - startedAt;
        timings.push(duration);
        failures.push(`${endpoint} failed: ${error.message}`);
    }
}

async function worker(workerId, deadline, timings, failures) {
    let index = workerId;

    while (performance.now() < deadline) {
        await hit(endpoints[index % endpoints.length], timings, failures);
        index += concurrency;
    }
}

function percentile(values, percentileRank) {
    if (values.length === 0) {
        return 0;
    }

    const sorted = [...values].sort((left, right) => left - right);
    const index = Math.min(sorted.length - 1, Math.ceil((percentileRank / 100) * sorted.length) - 1);

    return sorted[index];
}

function stopServer(server) {
    if (!server || server.killed) {
        return;
    }

    if (process.platform === 'win32') {
        spawnSync('taskkill', ['/pid', String(server.pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
        server.kill();
    }
}

const server = process.env.LOAD_SMOKE_BASE_URL ? null : startServer();

try {
    if (server) {
        await waitForHealthyServer();
    }

    const timings = [];
    const failures = [];
    const deadline = performance.now() + durationMs;

    await Promise.all(Array.from({ length: concurrency }, (_, index) => worker(index, deadline, timings, failures)));

    const p50 = percentile(timings, 50);
    const p95 = percentile(timings, 95);
    const max = Math.max(...timings);
    const requestsPerSecond = timings.length / (durationMs / 1000);

    console.log(`Load smoke completed: ${timings.length} requests, ${requestsPerSecond.toFixed(1)} req/s`);
    console.log(`Latency: p50=${p50.toFixed(0)}ms p95=${p95.toFixed(0)}ms max=${max.toFixed(0)}ms`);

    if (failures.length > 0) {
        throw new Error(`Load smoke had failures:\n${failures.slice(0, 25).map((failure) => `- ${failure}`).join('\n')}`);
    }

    if (p95 > p95ThresholdMs) {
        throw new Error(`Load smoke p95 ${p95.toFixed(0)}ms exceeded threshold ${p95ThresholdMs}ms`);
    }
} finally {
    stopServer(server);
}
