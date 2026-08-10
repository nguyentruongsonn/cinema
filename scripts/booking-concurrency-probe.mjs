const required = [
    'CONCURRENCY_BASE_URL',
    'CONCURRENCY_USER_A_EMAIL',
    'CONCURRENCY_USER_A_PASSWORD',
    'CONCURRENCY_USER_B_EMAIL',
    'CONCURRENCY_USER_B_PASSWORD',
    'CONCURRENCY_SHOWTIME_ID',
    'CONCURRENCY_SEAT_ID',
];

const missing = required.filter(name => !process.env[name]);
if (missing.length > 0) {
    throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
}

const baseUrl = process.env.CONCURRENCY_BASE_URL.replace(/\/$/, '');
const showtimeId = Number(process.env.CONCURRENCY_SHOWTIME_ID);
const seatId = Number(process.env.CONCURRENCY_SEAT_ID);
if (!Number.isInteger(showtimeId) || !Number.isInteger(seatId)) {
    throw new Error('CONCURRENCY_SHOWTIME_ID and CONCURRENCY_SEAT_ID must be integers.');
}

function responseCookies(response) {
    const values = typeof response.headers.getSetCookie === 'function'
        ? response.headers.getSetCookie()
        : [response.headers.get('set-cookie')].filter(Boolean);

    return values.map(value => value.split(';', 1)[0]).join('; ');
}

async function login(email, password) {
    const response = await fetch(`${baseUrl}/api/v1/auth/login`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ login: email, password, remember: false }),
    });
    const body = await response.json().catch(() => ({}));
    const cookies = responseCookies(response);

    if (!response.ok || !body.success || !cookies) {
        throw new Error(`Login failed for ${email}: HTTP ${response.status}`);
    }

    return cookies;
}

async function lockSeat(cookie) {
    const response = await fetch(`${baseUrl}/api/v1/seats/lock`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Cookie: cookie,
        },
        body: JSON.stringify({ showtime_id: showtimeId, seat_ids: [seatId] }),
    });

    return {
        status: response.status,
        body: await response.json().catch(() => ({})),
        cookie,
    };
}

async function releaseHold(result) {
    const holdId = result.body?.data?.hold_id;
    if (result.status !== 201 || !holdId) return;

    await fetch(`${baseUrl}/api/v1/seats/unlock/${holdId}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', Cookie: result.cookie },
    });
}

const [cookieA, cookieB] = await Promise.all([
    login(process.env.CONCURRENCY_USER_A_EMAIL, process.env.CONCURRENCY_USER_A_PASSWORD),
    login(process.env.CONCURRENCY_USER_B_EMAIL, process.env.CONCURRENCY_USER_B_PASSWORD),
]);

const results = await Promise.all([lockSeat(cookieA), lockSeat(cookieB)]);

try {
    const statuses = results.map(result => result.status).sort((left, right) => left - right);
    if (statuses[0] !== 201 || statuses[1] !== 409) {
        throw new Error(`Expected one HTTP 201 and one HTTP 409, received ${statuses.join(', ')}.`);
    }

    console.log('Booking concurrency probe passed: one winner and one conflict.');
} finally {
    await Promise.all(results.map(releaseHold));
}
