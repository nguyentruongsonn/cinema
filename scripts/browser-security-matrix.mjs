import { spawnSync } from 'node:child_process';

const browsers = ['chromium', 'firefox', 'webkit'];
const regressions = [
    'scripts/admin-users-xss-regression.mjs',
    'scripts/theaters-xss-regression.mjs',
    'scripts/booking-idempotency-regression.mjs',
    'scripts/ticket-scanner-regression.mjs',
];

for (const browser of browsers) {
    for (const regression of regressions) {
        const result = spawnSync(process.execPath, [regression], {
            env: { ...process.env, PLAYWRIGHT_BROWSER: browser },
            stdio: 'inherit',
        });

        if (result.status !== 0) {
            process.exit(result.status ?? 1);
        }
    }
}

console.log('Browser security matrix passed for Chromium, Firefox, and WebKit.');
