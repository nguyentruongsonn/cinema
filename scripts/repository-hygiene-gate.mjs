import fs from 'node:fs';
import path from 'node:path';

const failures = [];

function walk(directory) {
    if (!fs.existsSync(directory)) return [];
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const target = path.join(directory, entry.name);
        return entry.isDirectory() ? walk(target) : [target];
    });
}

const publicPhp = walk('public')
    .filter((file) => path.extname(file) === '.php')
    .filter((file) => path.normalize(file) !== path.normalize('public/index.php'));
if (publicPhp.length > 0) failures.push(`Unexpected public PHP files: ${publicPhp.join(', ')}`);

if (fs.existsSync('scratch')) {
    const scratchFiles = walk('scratch');
    if (scratchFiles.length > 0) failures.push(`Scratch files must not be tracked: ${scratchFiles.join(', ')}`);
}

const bladeFiles = walk('resources/views').filter((file) => file.endsWith('.blade.php'));
const timeVersionedAssets = bladeFiles.filter((file) => /(?:css|js)[^\n]+time\(\)/.test(fs.readFileSync(file, 'utf8')));
if (timeVersionedAssets.length > 0) failures.push(`time() asset versions found: ${timeVersionedAssets.join(', ')}`);

const forbiddenLegacyFiles = [
    'public/js/admin/pages/branches-refactored.js',
    'public/js/admin/base/AdminBasePage.js',
    'public/js/admin/base/AdminForm.js',
    'public/js/admin/base/AdminModal.js',
    'public/js/admin/base/AdminTable.js',
].filter(fs.existsSync);
if (forbiddenLegacyFiles.length > 0) failures.push(`Legacy admin experiments found: ${forbiddenLegacyFiles.join(', ')}`);

if (!fs.existsSync('resources/views/components/admin/modal.blade.php')) {
    failures.push('Shared admin modal component is missing.');
}

if (failures.length > 0) {
    console.error(`Repository hygiene gate failed:\n- ${failures.join('\n- ')}`);
    process.exit(1);
}

console.log('Repository hygiene gate passed.');
