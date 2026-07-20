import fs from 'node:fs';

const envFile = process.env.RELEASE_READINESS_ENV_FILE ?? '.env';
const strictExternal = process.argv.includes('--strict-external');

function parseEnv(filePath) {
    const values = {};

    if (!fs.existsSync(filePath)) {
        throw new Error(`Environment file not found: ${filePath}`);
    }

    for (const rawLine of fs.readFileSync(filePath, 'utf8').split(/\r?\n/)) {
        const line = rawLine.trim();

        if (!line || line.startsWith('#')) {
            continue;
        }

        const match = line.match(/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);
        if (!match) {
            continue;
        }

        let value = match[2].trim();
        if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
            value = value.slice(1, -1);
        }

        values[match[1]] = value;
    }

    return values;
}

function present(values, key) {
    const value = values[key];

    return typeof value === 'string'
        && value.trim() !== ''
        && !['null', 'changeme', 'change-me', 'todo', 'placeholder'].includes(value.trim().toLowerCase());
}

function addMissing(missing, values, key, label = key) {
    if (!present(values, key)) {
        missing.push(label);
    }
}

const env = parseEnv(envFile);
const failures = [];
const warnings = [];

for (const key of ['PAYOS_CLIENT_ID', 'PAYOS_API_KEY', 'PAYOS_CHECKSUM_KEY', 'PAYOS_WEBHOOK_SECRET']) {
    addMissing(failures, env, key);
}

if ((env.PAYOS_ENV ?? 'sandbox') !== 'sandbox' && !strictExternal) {
    warnings.push('PAYOS_ENV is not sandbox; local release readiness expects sandbox unless --strict-external is used.');
}

for (const key of ['PAYOS_RETURN_URL', 'PAYOS_CANCEL_URL']) {
    addMissing(failures, env, key);
}

if ((env.APP_DEBUG ?? '').toLowerCase() === 'true') {
    failures.push('APP_DEBUG must be false for release readiness');
}

if (!['info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'].includes((env.LOG_LEVEL ?? '').toLowerCase())) {
    failures.push('LOG_LEVEL should be info or stricter');
}

if ((env.SESSION_SECURE ?? '').toLowerCase() !== 'true') {
    warnings.push('SESSION_SECURE is not true; production HTTPS cookies should be secure.');
}

if (strictExternal) {
    for (const [key, expected] of [
        ['CACHE_STORE', 'redis'],
        ['SESSION_DRIVER', 'redis'],
        ['QUEUE_CONNECTION', 'redis'],
    ]) {
        if ((env[key] ?? '').toLowerCase() !== expected) {
            failures.push(`${key} must be ${expected} for production readiness`);
        }
    }

    addMissing(failures, env, 'SENTRY_LARAVEL_DSN');
    addMissing(failures, env, 'METRICS_TOKEN');

    if (!(env.LOG_STACK ?? '').split(',').map((value) => value.trim()).includes('slack')) {
        failures.push('LOG_STACK should include slack for production alert delivery');
    }

    addMissing(failures, env, 'LOG_SLACK_WEBHOOK_URL', 'LOG_SLACK_WEBHOOK_URL or equivalent alert sink');
}

if (warnings.length > 0) {
    console.warn('Readiness warnings:');
    warnings.forEach((warning) => console.warn(`- ${warning}`));
}

if (failures.length > 0) {
    throw new Error(`Release readiness failed for ${envFile}:\n${failures.map((failure) => `- ${failure}`).join('\n')}`);
}

console.log(`Release readiness passed for ${envFile}.`);
