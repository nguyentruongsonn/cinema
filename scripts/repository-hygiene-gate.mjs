import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

const tracked = execFileSync('git', ['ls-files'], { encoding: 'utf8' }).trim().split(/\r?\n/).filter(Boolean);
const failures = [];

for (const file of ['.env', 'storage/logs/laravel.log']) {
    if (tracked.includes(file)) failures.push(`Sensitive/runtime file is tracked: ${file}`);
}

for (const file of tracked.filter(file => /\.(php|js|mjs|css|blade\.php|md|json|ya?ml)$/i.test(file))) {
    if (!fs.existsSync(file)) continue;
    const content = fs.readFileSync(file, 'utf8');
    if (/^(<{7}|={7}|>{7})/m.test(content)) failures.push(`Merge marker found: ${file}`);
}

for (const command of Object.values(JSON.parse(fs.readFileSync('package.json', 'utf8')).scripts || {})) {
    const match = String(command).match(/(?:node|php)\s+([^\s]+)/);
    if (match && match[1].startsWith('scripts/') && !fs.existsSync(match[1])) failures.push(`Missing script: ${match[1]}`);
}

if (failures.length) {
    console.error(failures.join('\n'));
    process.exit(1);
}
console.log('Repository hygiene passed.');
