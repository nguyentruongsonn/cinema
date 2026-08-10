# Cinema Production Operations Runbook

## 0. Required production environment

Before starting any process, verify these values are explicitly set in the production environment:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- `QUEUE_CONNECTION=redis` and `BROADCAST_CONNECTION=reverb`.
- `REVERB_ENABLED=true` and `VITE_REVERB_ENABLED=true`, with matching Reverb app credentials.
- `MAIL_MAILER=smtp` with valid credentials; never keep the `.env.example` mail logger in production.
- `SESSION_SECURE=true`, a non-empty `METRICS_TOKEN`, and private alert webhook credentials.

Build frontend assets only after setting every `VITE_REVERB_*` value because Vite embeds them at build time.

## 1. Process manager

1. Copy `deployment/supervisor/cinema.conf.example` to `/etc/supervisor/conf.d/cinema.conf`.
2. Replace `/var/www/cinema`, PHP binary and Linux user for the target server.
3. Run `sudo supervisorctl reread`, `sudo supervisorctl update`, then `sudo supervisorctl status cinema:*`.
4. Keep the `broadcasts` worker separate from business queues so realtime events cannot be starved.
5. After every deployment run `php artisan queue:restart`; Supervisor starts fresh workers automatically.

Expected processes:

- Two workers for `emails,payments,default,cleanup`.
- One worker for `broadcasts`.
- One Laravel scheduler.
- One Reverb server when realtime is enabled.

All four process groups must report `RUNNING`. A healthy web process alone is not a successful deployment.

## 2. Health and alerts

- Liveness: `GET /api/v1/health/live`.
- Readiness: `GET /api/v1/health/ready`.
- Machine-readable health: `php artisan operations:monitor-health --json`.
- Prometheus metrics: `GET /api/v1/internal/metrics` with `Authorization: Bearer <METRICS_TOKEN>`.

Configure `OPERATIONS_ALERT_WEBHOOK_URL` with a private Slack/Teams/incident webhook. Alerts use a cooldown to avoid duplicate notifications. The operations command checks queue depth/age, failed jobs, overdue pending payments and paid orders whose invoice email remains unsent.

## 3. Deployment verification

```bash
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan operations:monitor-health --json
STAGING_BASE_URL=https://staging.example.com npm run test:browser:staging
```

Run the booking race probe only against staging test data:

```bash
CONCURRENCY_BASE_URL=https://staging.example.com \
CONCURRENCY_USER_A_EMAIL=user-a@example.com \
CONCURRENCY_USER_A_PASSWORD='secret' \
CONCURRENCY_USER_B_EMAIL=user-b@example.com \
CONCURRENCY_USER_B_PASSWORD='secret' \
CONCURRENCY_SHOWTIME_ID=123 \
CONCURRENCY_SEAT_ID=456 \
npm run test:booking:concurrency
```

The probe passes only when exactly one request acquires the seat and the other receives HTTP 409. It releases the winning hold before exiting.

## 4. Backup and restore drill

Run `deployment/scripts/mysql-backup-restore-drill.sh` from a secured operations host. The script creates a compressed backup, restores it into a temporary database, compares table and migration counts, then drops the temporary database.

Required environment variables: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `ALLOW_BACKUP_RESTORE_DRILL=true`.

Never run the drill with a database account that can access unrelated databases. Retain backup artifacts according to the organization retention policy and encrypt them at rest.

## 5. Incident response

1. Check `/api/v1/health/ready` and Supervisor status.
2. Inspect `storage/logs/queue-business.log`, `queue-broadcasts.log`, `scheduler.log`, and Laravel logs.
3. Check `php artisan queue:failed` and `php artisan operations:monitor-health --json`.
4. Retry only understood failures; do not blindly run `queue:retry all` for payment jobs.
5. For payment incidents, reconcile gateway state before marking an order paid.
