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
const browserName = process.env.PLAYWRIGHT_BROWSER ?? 'chromium';
const browserType = { chromium, firefox, webkit }[browserName];
const browser = await browserType.launch({ headless: true, ...(browserName === 'chromium' && executablePath ? { executablePath } : {}) });

try {
    const page = await browser.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    const fixtureHtml = `
        <form id="searchForm"><input id="search"></form>
        <select id="roleFilter"></select>
        <select id="statusFilter"></select>
        <select id="verifiedFilter"></select>
        <button id="btnCreateUser" type="button"></button>
        <div id="userModal"></div>
        <form id="userForm">
            <input id="formMethod"><input id="userIdInput">
            <input id="userName"><input id="userEmail"><input id="userUsername">
            <input id="userPhone"><input id="userPassword"><span id="passwordRequired"></span>
            <input id="userBirthday"><input id="userGender"><input id="userLoyaltyPoints">
            <input id="userAddress"><select id="userRoles" multiple></select>
            <input id="userStatus" type="checkbox">
        </form>
        <span id="userModalLabel"></span>
        <table><tbody id="usersTableBody"></tbody></table>
        <div id="paginationContainer"></div>
        <div id="resetPasswordModal"></div>
        <form id="resetPasswordForm">
            <input id="resetUserId"><span id="resetUserName"></span>
            <input id="newPassword"><input id="newPasswordConfirmation">
        </form>
    `;

    await page.route('http://cinema.test/', (route) => route.fulfill({
        status: 200,
        contentType: 'text/html',
        body: fixtureHtml,
    }));
    await page.goto('http://cinema.test/');

    await page.evaluate(() => {
        const marker = 'admin-users-xss-marker';
        const unsafeName = `Người dùng <img id="${marker}" src="invalid" onerror="window.__adminUsersXss = true">`;
        const unsafeRole = '<svg onload="window.__adminUsersRoleXss = true"></svg>';

        window.bootstrap = {
            Modal: {
                getOrCreateInstance: () => ({ show() {}, hide() {} }),
            },
        };
        window.showAdminToast = () => {};
        window.AdminCore = {
            apiFetch: async (url) => {
                if (String(url).endsWith('/roles')) {
                    return {
                        ok: true,
                        json: async () => ({ data: [{ id: 1, slug: 'member', name: unsafeRole }] }),
                    };
                }

                return {
                    ok: true,
                    json: async () => ({
                        data: [{
                            id: 7,
                            name: unsafeName,
                            email: 'member@example.test',
                            phone: null,
                            roles: [{ id: 1, name: unsafeRole }],
                            status: true,
                            email_verified_at: null,
                            created_at: '2026-07-17T00:00:00Z',
                        }],
                        pagination: { from: 1, current_page: 1, last_page: 1 },
                    }),
                };
            },
        };
    });

    await page.addScriptTag({ path: path.resolve('public/js/admin/pages/users.js') });
    await page.waitForFunction(() => document.querySelectorAll('#usersTableBody tr').length === 1);
    await page.waitForTimeout(100);

    const result = await page.evaluate(() => {
        const resetButton = document.querySelector('.reset-password-btn');
        resetButton.click();

        return {
            nameExecuted: window.__adminUsersXss === true,
            roleExecuted: window.__adminUsersRoleXss === true,
            injectedMarker: Boolean(document.getElementById('admin-users-xss-marker')),
            injectedSvg: Boolean(document.querySelector('#usersTableBody svg')),
            rowText: document.querySelector('#usersTableBody tr').textContent,
            resetName: document.getElementById('resetUserName').textContent,
            resetDatasetName: resetButton.dataset.name,
        };
    });

    assert.equal(result.nameExecuted, false, 'User name executed as active markup');
    assert.equal(result.roleExecuted, false, 'Role name executed as active markup');
    assert.equal(result.injectedMarker, false, 'User name created an injected element');
    assert.equal(result.injectedSvg, false, 'Role name created an injected element');
    assert.match(result.rowText, /<img id=/, 'User name was not rendered as literal text');
    assert.match(result.rowText, /<svg onload=/, 'Role name was not rendered as literal text');
    assert.match(result.resetName, /<img id=/, 'Reset modal did not use the safely stored name');
    assert.equal(result.resetDatasetName, undefined, 'Sensitive display text leaked into a data attribute');
    assert.deepEqual(pageErrors, []);

    console.log('Admin users XSS browser regression passed.');
} finally {
    await browser.close();
}
