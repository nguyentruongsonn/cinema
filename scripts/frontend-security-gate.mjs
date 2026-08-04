import fs from 'node:fs';
import path from 'node:path';

function collect(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const target = path.join(directory, entry.name);
        return entry.isDirectory() ? collect(target) : (entry.isFile() && /\.(js|blade\.php)$/.test(entry.name) ? [target] : []);
    });
}

const files = [...collect('public/js'), ...collect('resources/js'), ...collect('resources/views')];
const failures = [];
for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');
    const normalizedFile = file.replaceAll('\\', '/');
    if (/\bon(?:error|click|load)\s*=/.test(content)) failures.push(`${file}: inline event handler`);
    if (/\b(?:eval|Function)\s*\(/.test(content)) failures.push(`${file}: dynamic code execution`);
    const isDialogComponent = normalizedFile.endsWith('components/dialog.js') || normalizedFile.endsWith('components/modal.js');
    if (!isDialogComponent && /(^|[^\w.])(?:alert|confirm)\s*\(/m.test(content)) failures.push(`${file}: native blocking dialog`);
}

if (failures.length) {
    console.error(failures.join('\n'));
    process.exit(1);
}
console.log('Frontend security gate passed.');
