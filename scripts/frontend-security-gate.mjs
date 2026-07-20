import assert from 'node:assert/strict';
import fs from 'node:fs';

const legacyChecks = [
    ['public/js/admin/pages/users.js', ['tr.innerHTML', 'data-name="${user.name}']],
    ['public/js/users/pages/theaters.js', ['theatersGrid.innerHTML', 'Math.random()']],
    ['public/js/admin/ticket-scanner.js', ['localStorage.getItem', '/api/admin/tickets/verify', 'resultDiv.innerHTML']],
    ['public/js/admin/pages/orders.js', ['/orders/user/me', "replace(/</g, '<')", 'order.tickets', 'onclick=', 'onerror=']],
    ['public/js/users/pages/booking.js', ["addEventListener('beforeunload'", 'idempotency_key: this.createIdempotencyKey()', 'onclick=', 'onerror=']],
    ['public/js/admin/pages/theaters.js', ['branchId.innerHTML +=']],
    ['public/js/admin/pages/showtimes.js', ['el.innerHTML += `<option']],
    ['public/js/admin/pages/screens.js', ['screenTheater.innerHTML +=', 'screenFormat.innerHTML +=', 'screenTemplate.innerHTML +=']],
];

const reviewedFiles = [
    'public/js/admin/pages/products.js',
    'public/js/admin/pages/branches.js',
    'public/js/admin/pages/movies.js',
    'public/js/admin/pages/posts.js',
    'public/js/admin/pages/banners.js',
    'public/js/admin/pages/promotions.js',
    'public/js/users/pages/profile.js',
    'public/js/admin/pages/orders.js',
    'public/js/admin/ticket-scanner.js',
];

const directTaintedInterpolations = [
    /\$\{\s*prod\.(?:name|description|image_url)\s*\}/,
    /\$\{\s*branch\.name\s*\}/,
    /\$\{\s*movie\.(?:title|director|poster_url|age_rating)\s*\}/,
    /\$\{\s*post\.(?:title|excerpt)\s*\}/,
    /\$\{\s*post\.author\?\.name\s*(?:\|\|[^}]*)?\}/,
    /\$\{\s*banner\.(?:title|description|image_path)\s*\}/,
    /\$\{\s*promo\.(?:code|name|description)\s*\}/,
    /\$\{\s*movieName\s*\}/,
    /\$\{\s*group\.(?:type|seats)\s*\}/,
    /\$\{\s*(?:seatLabel|seatType|ticketCode)\s*\}/,
    /\$\{\s*ticketData\.[^}]+\}/,
];

for (const [file, forbiddenPatterns] of legacyChecks) {
    const source = fs.readFileSync(file, 'utf8');
    for (const pattern of forbiddenPatterns) {
        assert.equal(source.includes(pattern), false, `${file} contains forbidden frontend security pattern: ${pattern}`);
    }
}

for (const file of reviewedFiles) {
    const source = fs.readFileSync(file, 'utf8');
    assert.doesNotMatch(source, /\son[a-z]+\s*=/i, `${file} contains an inline event handler`);
    assert.doesNotMatch(source, /\.on(?:click|error|load|change|submit)\s*=/i, `${file} assigns an event handler property`);
    for (const pattern of directTaintedInterpolations) {
        assert.doesNotMatch(source, pattern, `${file} directly interpolates reviewed API data: ${pattern}`);
    }
}

for (const file of reviewedFiles.filter(file => !file.endsWith('ticket-scanner.js'))) {
    const source = fs.readFileSync(file, 'utf8');
    assert.match(source, /escapeHtml|\besc\(/, `${file} must expose an HTML escaping boundary`);
}

for (const file of ['public/js/admin/pages/products.js', 'public/js/admin/pages/movies.js', 'public/js/admin/pages/banners.js', 'public/js/users/pages/profile.js', 'public/js/admin/pages/orders.js']) {
    assert.match(fs.readFileSync(file, 'utf8'), /safeImageUrl/, `${file} must validate API image URLs`);
}

console.log(`Frontend security static gate passed for ${reviewedFiles.length} reviewed files.`);
