import { firefox } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots', 'posts-refactor');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const BASE_URL = 'http://127.0.0.1:8000';

const viewports = [
    { name: 'desktop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 375, height: 812 }
];

async function runTests() {
    console.log('=== STARTING PLAYWRIGHT RESPONSIVE AUDIT FOR /POSTS & /POSTS/{SLUG} ===');
    const browser = await firefox.launch({ headless: true });
    let totalErrors = 0;

    for (const vp of viewports) {
        console.log(`\n---> Testing Viewport: ${vp.name.toUpperCase()} (${vp.width}x${vp.height})`);
        const context = await browser.newContext({
            viewport: { width: vp.width, height: vp.height },
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CinemaJournalTest/1.0'
        });

        const page = await context.newPage();
        const consoleErrors = [];

        page.on('console', msg => {
            if (msg.type() === 'error') {
                const text = msg.text();
                // Ignore external websocket connection attempts when reverb/pusher server isn't running locally
                if (!text.includes('pusher.min.js') && !text.includes('ws://localhost:8080') && !text.includes('wss://localhost:8080')) {
                    consoleErrors.push(text);
                }
            }
        });

        // Test 1: Posts Index Page (/posts)
        console.log(`[1/2] Navigating to ${BASE_URL}/posts ...`);
        await page.goto(`${BASE_URL}/posts`, { waitUntil: 'networkidle' });

        // Wait for dynamic JS content to render
        await page.waitForSelector('.cinema-news-card', { timeout: 10000 });
        const postCardsCount = await page.locator('.cinema-news-card').count();
        console.log(`      ✓ Dynamic news cards rendered: ${postCardsCount} cards found.`);

        const indexPath = path.join(SCREENSHOT_DIR, `posts-index-${vp.name}.png`);
        await page.screenshot({ path: indexPath, fullPage: true });
        console.log(`      ✓ Captured screenshot: ${indexPath}`);

        if (consoleErrors.length > 0) {
            console.error(`      ✕ Console Errors found on /posts (${vp.name}):`, consoleErrors);
            totalErrors += consoleErrors.length;
        } else {
            console.log(`      ✓ 0 Console Errors on /posts.`);
        }

        // Test 2: Post Detail Page (/posts/{slug})
        console.log(`[2/2] Navigating to ${BASE_URL}/posts/review-phim-moi-su-tro-lai-cua-nhung-huyen-thoai ...`);
        await page.goto(`${BASE_URL}/posts/review-phim-moi-su-tro-lai-cua-nhung-huyen-thoai`, { waitUntil: 'networkidle' });

        await page.waitForSelector('.article-hero-section', { timeout: 10000 });
        const heroTitle = await page.locator('.article-hero-title').innerText();
        console.log(`      ✓ Article hero header rendered: "${heroTitle}"`);

        const detailPath = path.join(SCREENSHOT_DIR, `posts-detail-${vp.name}.png`);
        await page.screenshot({ path: detailPath, fullPage: true });
        console.log(`      ✓ Captured screenshot: ${detailPath}`);

        await context.close();
    }

    await browser.close();

    console.log('\n===================================================================');
    if (totalErrors === 0) {
        console.log('SUCCESS! ALL RESPONSIVE VIEWPORTS TESTED WITHOUT A SINGLE ERROR.');
        process.exit(0);
    } else {
        console.error(`FAILED with ${totalErrors} errors.`);
        process.exit(1);
    }
}

runTests().catch(err => {
    console.error('Fatal Test Error:', err);
    process.exit(1);
});
