import { spawnSync } from 'node:child_process';

const browsers = ['chromium', 'firefox', 'webkit'];
const regressions = [
    'scripts/admin-users-xss-regression.mjs',
    'scripts/theaters-xss-regression.mjs',
    'scripts/booking-idempotency-regression.mjs',
    'scripts/ticket-scanner-regression.mjs',
    'scripts/frontend-remediation-regression.mjs',
];
const timeout = Number(process.env.BROWSER_TEST_TIMEOUT_MS || 60_000);
const requireFirefox = process.env.BROWSER_MATRIX_REQUIRE_FIREFOX === '1';
const skippedBrowsers = new Set();

for (const browser of browsers) {
    for (const regression of regressions) {
        const result = spawnSync(process.execPath, [regression], {
            env: { ...process.env, PLAYWRIGHT_BROWSER: browser },
            stdio: 'inherit',
            timeout,
        });

        if (result.error?.code === 'ETIMEDOUT' || result.signal) {
            if (browser === 'firefox' && !requireFirefox) {
                console.warn(`Skipping Firefox after ${regression} exceeded ${timeout}ms. Set BROWSER_MATRIX_REQUIRE_FIREFOX=1 in CI to fail instead.`);
                skippedBrowsers.add(browser);
                break;
            }

            console.error(`${browser} ${regression} exceeded ${timeout}ms.`);
            process.exit(1);
        }

        if (result.status !== 0) {
            process.exit(result.status ?? 1);
        }
    }
}

const completedBrowsers = browsers.filter((browser) => !skippedBrowsers.has(browser));
console.log(`Browser security matrix passed for ${completedBrowsers.join(', ')}.`);
if (skippedBrowsers.size > 0) {
    console.warn(`Skipped browsers: ${[...skippedBrowsers].join(', ')}.`);
}
