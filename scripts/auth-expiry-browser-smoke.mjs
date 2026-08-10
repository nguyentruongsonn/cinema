import fs from 'node:fs';
import { chromium } from 'playwright';

const localEdge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch(fs.existsSync(localEdge)
    ? { executablePath: localEdge, headless: true }
    : { headless: true });

try {
    const page = await browser.newPage();
    await page.setContent(`
        <body>
            <button data-auth-action="login">Login</button>
            <div id="userDropdown"><span class="user-name"></span></div>
            <div id="authModal">
                <div class="auth-required-section"></div>
                <div class="auth-forms-section d-none"></div>
                <button id="login-tab"></button>
                <div id="loginPanel"></div>
                <div id="forgotPasswordPanel" class="d-none"></div>
            </div>
        </body>
    `);

    await page.evaluate(() => {
        window.APP_CONFIG = {
            apiUrl: '/api/v1',
            auth: {
                checked: true,
                authenticated: true,
                user: { id: 99, name: 'Browser Test User' },
            },
        };
        window.Toast = { info() {}, warning() {}, error() {}, success() {} };
        window.__sessionExpiredEvents = 0;
        window.__authModalShows = 0;

        class ModalStub {
            static getOrCreateInstance(element) {
                return new ModalStub(element);
            }

            constructor(element) {
                this.element = element;
            }

            show() {
                window.__authModalShows += 1;
                this.element.dataset.open = 'true';
            }

            hide() {}
        }

        window.bootstrap = { Modal: ModalStub };
        window.apiClient = {
            async request() {
                throw Object.assign(new Error('Unauthorized'), { status: 401 });
            },
            async post() {
                throw Object.assign(new Error('Refresh rejected'), { status: 401 });
            },
        };
        window.addEventListener('cinema:session-expired', () => {
            window.__sessionExpiredEvents += 1;
            window.authManager.showAuthRequired();
        });
    });

    await page.addScriptTag({ path: 'public/js/users/auth.js' });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

    const result = await page.evaluate(async () => {
        const errors = [];
        for (let attempt = 0; attempt < 2; attempt += 1) {
            try {
                await window.authManager.fetchAPI('/seats/lock', { method: 'POST' });
            } catch (error) {
                errors.push({ code: error.code, status: error.status });
            }
        }

        return {
            errors,
            authenticated: window.authManager.isAuthenticated(),
            sessionExpired: window.authManager.sessionExpired,
            events: window.__sessionExpiredEvents,
            modalShows: window.__authModalShows,
            modalOpen: document.getElementById('authModal').dataset.open === 'true',
        };
    });

    if (result.errors.some(error => error.code !== 'SESSION_EXPIRED' || error.status !== 401)) {
        throw new Error(`Unexpected auth error shape: ${JSON.stringify(result.errors)}`);
    }
    if (result.authenticated || !result.sessionExpired || result.events !== 1 || result.modalShows !== 1 || !result.modalOpen) {
        throw new Error(`Auth expiry state mismatch: ${JSON.stringify(result)}`);
    }

    console.log('Auth expiry browser smoke passed.');
} finally {
    await browser.close();
}
