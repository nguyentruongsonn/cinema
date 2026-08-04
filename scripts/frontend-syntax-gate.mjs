import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

function collect(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const target = path.join(directory, entry.name);
        return entry.isDirectory() ? collect(target) : (entry.isFile() && entry.name.endsWith('.js') ? [target] : []);
    });
}

const files = [...collect('public/js'), ...collect('resources/js')];
for (const file of files) execFileSync(process.execPath, ['--check', file], { stdio: 'inherit' });
console.log(`Frontend syntax passed for ${files.length} files.`);
