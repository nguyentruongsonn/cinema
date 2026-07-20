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
const productRendererSource = fs.readFileSync(path.resolve('resources/js/pages/booking-products.js'), 'utf8');
const source = fs.readFileSync(path.resolve('public/js/users/pages/booking.js'), 'utf8');
const browserName = process.env.PLAYWRIGHT_BROWSER ?? 'chromium';
const browserType = { chromium, firefox, webkit }[browserName];
const browser = await browserType.launch({ headless: true, ...(browserName === 'chromium' && executablePath ? { executablePath } : {}) });

try {
    const page = await browser.newPage();
    await page.setContent('<main></main>');
    await page.addScriptTag({ content: productRendererSource });
    await page.addScriptTag({ content: `${source}\nwindow.__BookingManager = BookingManager;` });

    const result = await page.evaluate(() => {
        const manager = Object.create(window.__BookingManager.prototype);
        const keys = ['00000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000000002'];
        manager.checkoutIntent = null;
        manager.getCurrentHoldId = () => 55;
        manager.createIdempotencyKey = () => keys.shift();

        const basePayload = {
            showtime_id: 12,
            items: [{ type: 'seat', id: 8, quantity: 1 }],
            voucher_code: null,
            points_used: 0,
        };
        const first = manager.getOrCreateCheckoutIntent(basePayload);
        const retry = manager.getOrCreateCheckoutIntent({ ...basePayload, items: [...basePayload.items] });
        const changed = manager.getOrCreateCheckoutIntent({ ...basePayload, points_used: 100 });

        return {
            firstKey: first.key,
            retryKey: retry.key,
            changedKey: changed.key,
            sameIntent: first === retry,
            changedIntent: retry !== changed,
        };
    });

    assert.equal(result.sameIntent, true);
    assert.equal(result.firstKey, result.retryKey);
    assert.equal(result.changedIntent, true);
    assert.notEqual(result.retryKey, result.changedKey);

    const interactionResult = await page.evaluate(async () => {
        const manager = Object.create(window.__BookingManager.prototype);
        manager.isLockingSeats = false;
        manager.selectedSeats = new Map();
        manager.currentHold = null;
        manager.getSeatStatus = () => 'available';
        let activations = 0;
        manager.handleSeatClick = () => { activations++; };
        const seat = manager.createSeat({ id: 1, row: 'A', number: 1, label: 'A1', seat_type: { name: 'Standard' } });
        seat.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        seat.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));

        let pollCallback;
        const originalSetInterval = window.setInterval;
        window.setInterval = (callback) => { pollCallback = callback; return 99; };
        manager.config = { encryptedShowtimeId: 'showtime' };
        manager.seats = [];
        let fetchCalls = 0;
        let resolveFetch;
        manager.fetchAPI = () => {
            fetchCalls++;
            return new Promise(resolve => { resolveFetch = resolve; });
        };
        manager.startSeatPolling();
        const firstPoll = pollCallback();
        const overlappingPoll = pollCallback();
        await Promise.resolve();
        resolveFetch({ success: true, data: { seats: [] } });
        await Promise.all([firstPoll, overlappingPoll]);
        window.setInterval = originalSetInterval;
        return { activations, fetchCalls };
    });

    assert.equal(interactionResult.activations, 2);
    assert.equal(interactionResult.fetchCalls, 1);

    const productResult = await page.evaluate(async () => {
        const manager = Object.create(window.__BookingManager.prototype);
        manager.productsContainer = document.querySelector('main');
        manager.selectedProducts = new Map();
        let calculatedTotal = 0;
        manager.updateSummary = () => {
            calculatedTotal = manager.calculateProductsTotal();
        };
        manager.fetchAPI = async () => ({
            success: true,
            data: {
                current_page: 1,
                data: [{
                    id: 7,
                    name: 'Bắp rang',
                    description: 'Combo thử nghiệm',
                    price: '45000.00',
                    max_quantity: 3,
                    image_url: null,
                }],
            },
        });

        await manager.loadProducts();
        manager.productsContainer.querySelector('[data-action="increase"]').click();

        return {
            count: manager.products.length,
            renderedName: manager.productsContainer.querySelector('.product-name')?.textContent,
            quantity: manager.selectedProducts.get(7),
            calculatedTotal,
        };
    });

    assert.equal(productResult.count, 1);
    assert.equal(productResult.renderedName, 'Bắp rang');
    assert.equal(productResult.quantity, 1);
    assert.equal(productResult.calculatedTotal, 45000);

    const summaryResult = await page.evaluate(() => {
        const manager = Object.create(window.__BookingManager.prototype);
        manager.selectedSeats = new Set([1]);
        manager.seats = [{ id: 1, row: 'A', number: 1, price: '120000.00' }];
        manager.products = window.BookingProductRenderer.normalize([{ id: 7, price: '45000.00', max_quantity: 3 }]);
        manager.selectedProducts = new Map([[7, 2]]);
        manager.appliedPromotion = null;
        manager.appliedPoints = 0;
        manager.seatQuantity = { textContent: '' };
        manager.seatSurcharge = { textContent: '' };
        manager.productTotal = { textContent: '' };
        manager.discountAmount = { textContent: '' };
        manager.totalPrice = { textContent: '' };
        manager.renderSelectedProducts = () => {};
        manager.updateSidebarSummary = () => {};
        manager.updateStepButtons = () => {};
        manager.bottomSheet = null;
        manager.updateSummary();

        return {
            seatTotal: manager.seatSurcharge.textContent,
            productTotal: manager.productTotal.textContent,
            discount: manager.discountAmount.textContent,
            total: manager.totalPrice.textContent,
        };
    });

    assert.equal(Object.values(summaryResult).some((value) => value.includes('NaN')), false);
    assert.match(summaryResult.seatTotal, /120[.\s]000/);
    assert.match(summaryResult.productTotal, /90[.\s]000/);
    assert.match(summaryResult.total, /210[.\s]000/);

    const holdTimingResult = await page.evaluate(async () => {
        const manager = Object.create(window.__BookingManager.prototype);
        manager.selectedSeats = new Set([1]);
        manager.currentHold = null;
        manager.lockPromise = null;
        manager.isLockingSeats = false;
        manager.updateStepButtons = () => {};
        manager.renderSeatMap = () => {};
        manager.showToast = () => {};
        let lockCalls = 0;
        manager.lockSeats = async () => {
            lockCalls++;
            manager.currentHold = { seat_ids: [1] };
            return true;
        };

        const beforeContinue = lockCalls;
        const held = await manager.ensureSeatsHeldBeforeContinue();
        return { beforeContinue, afterContinue: lockCalls, held };
    });

    assert.deepEqual(holdTimingResult, { beforeContinue: 0, afterContinue: 1, held: true });

    const resultScreen = await page.evaluate(() => {
        document.body.innerHTML = `
            <span id="successStatusTitle"></span><span id="successStatusMessage"></span><i id="successStatusIcon"></i>
            <span id="successOrderCode"></span><span id="successMovieTitle"></span><span id="successMovieFormat"></span>
            <span id="successShowtime"></span><span id="successTheater"></span><span id="successTheaterAddress"></span>
            <span id="successSeatsInfo"></span><div id="successProductsList"></div><span id="successSubtotal"></span>
            <div id="successVoucherRow" class="d-none"><span id="successVoucherLabel"></span><span id="successVoucherDiscount"></span></div>
            <div id="successPointsRow" class="d-none"><span id="successPointsLabel"></span><span id="successPointsDiscount"></span></div>
            <span id="successTotalAmount"></span><span id="successDate"></span><a id="viewTicketBtn"></a>
        `;
        const manager = Object.create(window.__BookingManager.prototype);
        manager.renderSuccessResult({
            code: 'ORD-RESULT-1',
            order_code: 'ORD-RESULT-1',
            payment_status: 'paid',
            total_amount: 150000,
            created_at: '2026-07-20T10:00:00+07:00',
            movie_title: 'Phim kiểm thử',
            branch_name: 'Chi nhánh trung tâm',
            screen_name: 'Phòng 2',
            theater_address: '123 Đường Cinema',
            showtime: { scheduled_at: '2026-07-21T19:30:00+07:00', format: { name: 'IMAX' } },
            invoice: {
                tickets: [{ metadata: { seat_label: 'A1' } }, { metadata: { seat_label: 'A2' } }],
                products: [{ quantity: 2, total_price: 90000, metadata: { product_name: 'Bắp rang' } }],
                subtotal: 165000,
                voucher_discount: 10000,
                point_discount: 5000,
                points_used: 5,
                promotion: { code: 'SAVE10' },
            },
        });

        return {
            title: document.getElementById('successStatusTitle').textContent,
            seats: document.getElementById('successSeatsInfo').textContent,
            product: document.getElementById('successProductsList').textContent,
            voucher: document.getElementById('successVoucherRow').textContent,
            points: document.getElementById('successPointsRow').textContent,
            total: document.getElementById('successTotalAmount').textContent,
            ticketHref: document.getElementById('viewTicketBtn').getAttribute('href'),
        };
    });

    assert.equal(resultScreen.title, 'Thanh toán thành công');
    assert.equal(resultScreen.seats, 'A1, A2');
    assert.match(resultScreen.product, /Bắp rang × 2/);
    assert.match(resultScreen.voucher, /SAVE10/);
    assert.match(resultScreen.points, /5 điểm/);
    assert.match(resultScreen.total, /150[.\s]000/);
    assert.equal(resultScreen.ticketHref, '/tickets/order/ORD-RESULT-1');
    console.log('Booking idempotency browser regression passed.');
} finally {
    await browser.close();
}
