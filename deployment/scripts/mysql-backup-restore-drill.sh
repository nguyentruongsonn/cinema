#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

if [[ "${ALLOW_BACKUP_RESTORE_DRILL:-false}" != "true" ]]; then
    echo "Set ALLOW_BACKUP_RESTORE_DRILL=true to run this drill." >&2
    exit 2
fi

if [[ ! "${DB_DATABASE}" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "DB_DATABASE contains unsupported characters." >&2
    exit 2
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="${BACKUP_DIR:-./storage/backups}"
backup_file="${backup_dir}/${DB_DATABASE}-${timestamp}.sql.gz"
drill_database="${DB_DATABASE}_restore_drill_${timestamp//[^0-9A-Za-z]/}"
mkdir -p "${backup_dir}"
export MYSQL_PWD="${DB_PASSWORD}"

cleanup() {
    mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USERNAME}" \
        --execute="DROP DATABASE IF EXISTS \`${drill_database}\`;" >/dev/null 2>&1 || true
}
trap cleanup EXIT

mysqldump --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USERNAME}" \
    --single-transaction --routines --triggers --events --set-gtid-purged=OFF \
    "${DB_DATABASE}" | gzip -9 > "${backup_file}"

mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USERNAME}" \
    --execute="CREATE DATABASE \`${drill_database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "${backup_file}" | mysql --host="${DB_HOST}" --port="${DB_PORT}" \
    --user="${DB_USERNAME}" "${drill_database}"

source_tables="$(mysql --batch --skip-column-names --host="${DB_HOST}" --port="${DB_PORT}" \
    --user="${DB_USERNAME}" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}';")"
restored_tables="$(mysql --batch --skip-column-names --host="${DB_HOST}" --port="${DB_PORT}" \
    --user="${DB_USERNAME}" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${drill_database}';")"
source_migrations="$(mysql --batch --skip-column-names --host="${DB_HOST}" --port="${DB_PORT}" \
    --user="${DB_USERNAME}" "${DB_DATABASE}" --execute="SELECT COUNT(*) FROM migrations;")"
restored_migrations="$(mysql --batch --skip-column-names --host="${DB_HOST}" --port="${DB_PORT}" \
    --user="${DB_USERNAME}" "${drill_database}" --execute="SELECT COUNT(*) FROM migrations;")"

if [[ "${source_tables}" != "${restored_tables}" || "${source_migrations}" != "${restored_migrations}" ]]; then
    echo "Restore verification failed." >&2
    exit 1
fi

echo "Backup verified: ${backup_file}"
echo "Tables: ${restored_tables}; migrations: ${restored_migrations}"
