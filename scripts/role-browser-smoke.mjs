import { chromium } from 'playwright';

const baseUrl = process.env.BROWSER_TEST_BASE_URL || 'http://127.0.0.1:8000';
const sessions = JSON.parse(process.env.BROWSER_TEST_SESSIONS || '[]');

if (!sessions.length) {
    console.error('Set BROWSER_TEST_SESSIONS to a JSON array of {role,email,password,path} before running role browser smoke.');
    process.exit(2);
}

const browser = await chromium.launch({
    executablePath: process.env.BROWSER_TEST_BROWSER || undefined,
    headless: true,
});

try {
    for (const session of sessions) {
        const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
        const page = await context.newPage();
        const failures = [];

        page.on('pageerror', (error) => failures.push(`${session.role}: ${error.message}`));
        const login = await context.request.post(`${baseUrl}/api/v1/auth/login`, {
            data: { email: session.email, password: session.password },
            headers: { Accept: 'application/json' },
        });
        if (!login.ok()) {
            throw new Error(`${session.role}: login failed with HTTP ${login.status()}`);
        }

        const response = await page.goto(`${baseUrl}${session.path}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        if (!response?.ok()) failures.push(`${session.role}: ${session.path} returned ${response?.status()}`);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        if (overflow) failures.push(`${session.role}: ${session.path} has horizontal overflow`);
        if (failures.length) throw new Error(failures.join('\n'));

        console.log(`${session.role}: ${session.path} passed`);
        await context.close();
    }
} finally {
    await browser.close();
}
