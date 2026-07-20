import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const roots = ['resources/js', 'public/js', 'scripts'];
const extensions = new Set(['.js', '.mjs']);
const files = [];

function collect(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            collect(target);
        } else if (extensions.has(path.extname(entry.name))) {
            files.push(target);
        }
    }
}

roots.filter(fs.existsSync).forEach(collect);

const failures = [];
for (const file of files) {
    const result = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });
    if (result.status !== 0) failures.push(`${file}\n${result.stderr.trim()}`);
}

if (failures.length > 0) {
    console.error(`Frontend syntax gate failed:\n${failures.join('\n\n')}`);
    process.exit(1);
}

console.log(`Frontend syntax gate passed: ${files.length} JavaScript files.`);
