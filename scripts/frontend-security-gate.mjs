import assert from 'node:assert/strict';
import fs from 'node:fs';

const checks = [
    ['public/js/admin/pages/users.js', ['tr.innerHTML', 'data-name="${user.name}']],
    ['public/js/users/pages/theaters.js', ['theatersGrid.innerHTML', 'Math.random()']],
    ['public/js/admin/ticket-scanner.js', ['localStorage.getItem', '/api/admin/tickets/verify', 'resultDiv.innerHTML']],
    ['public/js/admin/pages/orders.js', ['/orders/user/me', "replace(/</g, '<')"]],
    ['public/js/users/pages/booking.js', ["addEventListener('beforeunload'", 'idempotency_key: this.createIdempotencyKey()']],
    ['public/js/admin/pages/theaters.js', ['branchId.innerHTML +=']],
    ['public/js/admin/pages/showtimes.js', ['el.innerHTML += `<option']],
    ['public/js/admin/pages/screens.js', ['screenTheater.innerHTML +=', 'screenFormat.innerHTML +=', 'screenTemplate.innerHTML +=']],
];

for (const [file, forbiddenPatterns] of checks) {
    const source = fs.readFileSync(file, 'utf8');
    for (const pattern of forbiddenPatterns) {
        assert.equal(source.includes(pattern), false, `${file} contains forbidden frontend security pattern: ${pattern}`);
    }
}

console.log('Frontend security static gate passed.');
