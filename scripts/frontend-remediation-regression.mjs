import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium, firefox, webkit } from 'playwright';

const candidates = [
    process.env.EDGE_PATH,
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);
const executablePath = candidates.find(candidate => fs.existsSync(candidate));
const browserName = process.env.PLAYWRIGHT_BROWSER ?? 'chromium';
const browserType = { chromium, firefox, webkit }[browserName];
const browser = await browserType.launch({ headless: true, ...(browserName === 'chromium' && executablePath ? { executablePath } : {}) });

try {
    const page = await browser.newPage();
    await page.setContent(`
        <div id="order-modal-body"></div>
        <div id="order-grid"></div>
        <div id="order-loading" class="d-none"></div>
        <div id="profile-order-modal">
            <span id="odModalCode"></span><img id="odModalPoster"><span id="odModalMovieTitle"></span>
            <span id="odModalTheater"></span><span id="odModalRoom"></span><span id="odModalShowtime"></span><span id="odModalAddress"></span>
            <div id="odModalTicketsList"></div><div id="odModalProductsList"></div><span id="odModalSeats"></span>
            <span id="odModalSeatType"></span><span id="odModalStatus"></span><span id="odModalPayerName"></span>
            <span id="odModalPaymentMethod"></span><span id="odModalTxDate"></span><div id="odModalBarcodeContainer"></div>
            <div id="odModalVoucherList"></div><span id="odModalVoucherCode"></span><span id="odModalVoucherValue"></span>
            <div id="odModalPointsList"></div><span id="odModalPointsUsed"></span><span id="odModalPointsValue"></span>
            <span id="odModalSubtotal"></span><span id="odModalTotal"></span>
        </div>
    `);
    await page.evaluate(() => {
        window.bootstrap = { Modal: class { static getOrCreateInstance() { return new this(); } show() {} } };
        window.onAdminPageLoad = () => {};
        window.onAdminPageCleanup = () => {};
    });

    const ordersSource = fs.readFileSync(path.resolve('public/js/admin/pages/orders.js'), 'utf8');
    const ordersTestSource = ordersSource.replace(/\}\)\(\);\s*$/, 'window.__AdminOrdersManager = AdminOrdersManager;})();');
    await page.addScriptTag({ content: ordersTestSource });
    const profileSource = fs.readFileSync(path.resolve('public/js/users/pages/profile.js'), 'utf8').replace(/^import\s+.+?;\s*$/m, '');
    await page.addScriptTag({ content: `${profileSource}\nwindow.__ProfilePage = ProfilePage;` });

    const result = await page.evaluate(async () => {
        const payload = '<img id="stored-xss" src="invalid" onerror="window.__xss=true">';
        const unsafeUrl = 'javascript:alert(1)';
        const order = {
            id: 7,
            code: payload,
            payment_status: 'paid',
            total_amount: 120000,
            created_at: '2026-07-17T10:00:00Z',
            user: { name: payload, email: payload },
            showtime: {
                scheduled_at: '2026-07-18T10:00:00Z',
                movie: { title: payload, poster_url: unsafeUrl, age_rating: payload, duration: payload },
                screen: { name: payload, theater: { name: payload } },
            },
            items: [{ type: 'Seat', metadata: { seat_label: payload, seat_type: payload, ticket_code: payload } }],
        };

        const orders = Object.create(window.__AdminOrdersManager.prototype);
        orders.els = { modalBody: document.getElementById('order-modal-body') };
        document.getElementById('order-grid').innerHTML = orders.buildOrderCard(order);
        orders.renderOrderModal(order);

        const profile = Object.create(window.__ProfilePage.prototype);
        profile.user = { name: payload, email: payload };
        profile.renderOrderDetailModal({
            ...order,
            status: 'completed',
            order_items: [
                { item_type: 'Seat', metadata: { seat_label: payload, seat_type: payload }, unit_price: 100000, quantity: 1 },
                { item_type: 'Product', metadata: { product_name: payload, product_description: payload }, unit_price: 20000, quantity: 1 },
            ],
        });

        const race = Object.create(window.__AdminOrdersManager.prototype);
        race.currentPage = 1;
        race.perPage = 10;
        race.filters = { status: 'all', branch_id: '', theater_id: '', movie_id: '', date: '', search: '' };
        race.loadRequest = null;
        race.loadRequestId = 0;
        race.orders = [];
        race.lastPage = 1;
        race.els = { loading: document.getElementById('order-loading'), grid: document.getElementById('order-grid') };
        race.renderOrders = () => {};
        race.renderPagination = () => {};
        race.updateOrderCount = () => {};
        race.showToast = () => {};
        const pending = [];
        race.apiRequest = () => new Promise(resolve => pending.push(resolve));
        const first = race.loadOrders(1);
        const second = race.loadOrders(2);
        pending[0]({ success: true, data: { data: [], meta: {} } });
        await first;
        const loadingAfterStaleRequest = !race.els.loading.classList.contains('d-none');
        pending[1]({ success: true, data: { data: [], meta: {} } });
        await second;

        return {
            injectedElements: document.querySelectorAll('#stored-xss').length,
            executed: Boolean(window.__xss),
            unsafeSources: [...document.images].filter(image => image.src.startsWith('javascript:')).length,
            textIncludesPayload: document.body.textContent.includes('stored-xss'),
            loadingAfterStaleRequest,
            finalLoading: !race.els.loading.classList.contains('d-none'),
        };
    });

    assert.equal(result.injectedElements, 0);
    assert.equal(result.executed, false);
    assert.equal(result.unsafeSources, 0);
    assert.equal(result.textIncludesPayload, true);
    assert.equal(result.loadingAfterStaleRequest, true);
    assert.equal(result.finalLoading, false);
    console.log('Frontend remediation browser regression passed.');
} finally {
    await browser.close();
}
