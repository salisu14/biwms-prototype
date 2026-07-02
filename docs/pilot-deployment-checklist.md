# BIWMS Pilot Deployment Checklist

Use this checklist for the 3-month pilot deployment. It is written for a Laravel Forge + DigitalOcean style deployment, but the same checks apply to any production-like host.

## Server Requirements

- PHP 8.4 with required extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo_pgsql`, `session`, `tokenizer`, `xml`, `zip`.
- Composer 2.
- Node.js and npm only if assets are built on the server.
- PostgreSQL supported by the application migrations.
- Redis for cache, sessions, queues, and locks where configured.
- Supervisor or systemd for queue workers.
- Cron enabled for Laravel scheduler.
- Sufficient disk for uploads, logs, backups, and database dumps.
- HTTPS-capable domain or subdomain.

## Laravel Forge Setup

- Create the server in Forge and connect the repository.
- Set the site web directory to `public`.
- Configure the deployment branch for the pilot.
- Add production `.env` variables through Forge.
- Configure SSL certificate through Forge/Let’s Encrypt.
- Configure queue workers with Supervisor.
- Add a scheduled task for Laravel scheduler.
- Confirm deployment script runs:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

If assets are built in CI instead of on the server, replace the npm steps with the chosen artifact deployment process.

## DigitalOcean Droplet Setup

- Use an LTS Ubuntu image supported by Forge.
- Choose CPU/RAM based on pilot user count and background posting/report load.
- Enable monitoring.
- Restrict SSH access to authorized keys.
- Configure firewall rules:
  - allow HTTP/HTTPS.
  - allow SSH only from trusted IPs where practical.
  - block direct public database access unless intentionally required.
- Attach backups or snapshots for server-level recovery.
- Confirm time zone and NTP are correct.

## PostgreSQL And Redis Setup

- Use managed PostgreSQL where possible for easier backups and monitoring.
- Create a dedicated pilot database and database user.
- Do not reuse local or staging credentials.
- Enable SSL to the database where available.
- Configure Redis for:
  - cache
  - queue
  - session, if selected
  - application locks
- Confirm database and Redis credentials are present only in secure environment configuration.

## Production/Pilot `.env` Variables

Review at least:

```env
APP_NAME="BIWMS"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://pilot.example.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

REDIS_CLIENT=phpredis
REDIS_HOST=
REDIS_PASSWORD=
REDIS_PORT=6379

MAIL_MAILER=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"

ADMIN_IDLE_TIMEOUT_MINUTES=30
ADMIN_ABSOLUTE_SESSION_LIFETIME_MINUTES=480
```

Confirm no development secrets, debug mail settings, or local URLs remain.

## Queue Worker Setup

- Configure at least one Laravel queue worker through Forge Supervisor.
- Use the production queue connection from `.env`.
- Suggested worker command:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --timeout=120
```

- Confirm workers restart during deployment:

```bash
php artisan queue:restart
```

- Monitor failed jobs after deployment:

```bash
php artisan queue:failed
```

## Scheduler Setup

Add one cron entry through Forge:

```bash
* * * * * cd /home/forge/site.example.com && php artisan schedule:run >> /dev/null 2>&1
```

Confirm scheduled commands do not overlap unexpectedly and that logs are monitored during the first pilot week.

## Storage Permissions

- Ensure these paths are writable by the web/queue user:
  - `storage`
  - `storage/app`
  - `storage/framework`
  - `storage/logs`
  - `bootstrap/cache`
- Link public storage if file uploads are used:

```bash
php artisan storage:link
```

- Confirm uploaded files are not executable.

## Backup Plan

- Database backups:
  - enable managed database backups or scheduled `pg_dump`.
  - keep at least daily backups during pilot.
  - test restore before client go-live.
- File backups:
  - include `storage/app` and user-uploaded documents.
  - confirm backup retention and restore process.
- Application rollback:
  - tag pilot release commits.
  - keep previous deployment available in Forge deployment history.
- Document who can restore backups and who approves restore during pilot.

## SSL And Domain Setup

- Point pilot DNS record to the server/load balancer.
- Issue SSL certificate before first client access.
- Force HTTPS.
- Confirm:
  - `APP_URL` uses `https`.
  - `SESSION_SECURE_COOKIE=true`.
  - health check reports no production-critical session/security failure.

## Migration And Seed Steps

For a new pilot deployment only, after confirming the target database is safe to initialize:

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\PermissionsTableSeeder --force
php artisan db:seed --class=Database\\Seeders\\RolesTableSeeder --force
```

If the pilot database must be fully rebuilt before client use, get explicit approval first, then run the agreed destructive reset command only against the dedicated pilot database.

Never run destructive migration/reset commands against an environment with client-entered pilot data unless a restore point and written approval exist.

## Super Admin And MFA Setup

- Create or confirm the initial Super Admin account.
- Assign the canonical `super_admin` role.
- Confirm Super Admin can access `/admin`.
- Enable and confirm MFA before inviting client users.
- Store recovery codes securely with the client-designated administrator.
- Confirm no shared Super Admin account is used for daily work.

## Health, Security, And Pilot Check Commands

Run after deployment:

```bash
php artisan optimize:clear
php artisan permission:cache-reset
php artisan biwms:security-audit
php artisan biwms:health-check
php artisan biwms:pilot-check
php artisan biwms:finance-reconcile --details
php artisan biwms:inventory-reconcile --details
```

Expected:

- Security audit has no hard-check failures.
- Health check has no critical production failures.
- Pilot check has no errors and any warnings are understood.
- Reconciliation findings, if any, are documented before first live transaction.

## Rollback Plan

- Identify the previous stable release commit/tag.
- Confirm database backup timestamp before deployment.
- If rollback is code-only:

```bash
git checkout <previous-release>
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

- If rollback requires database restore:
  - stop queue workers.
  - put the app in maintenance mode.
  - restore the approved database backup.
  - restore uploaded files if needed.
  - run health/security/pilot checks.
  - bring the app out of maintenance mode.

```bash
php artisan down
php artisan up
```

Document the reason, time, approver, and restored backup identifier.

## First Client Login Checklist

- Confirm domain loads over HTTPS.
- Confirm the client Super Admin logs in successfully.
- Confirm MFA challenge works.
- Confirm ordinary pilot users can access only their assigned panels.
- Confirm menus match assigned roles.
- Confirm company profile, chart of accounts, posting groups, number series, locations, and bank accounts are visible to authorized users.
- Enter one safe test document in each pilot scope before live use:
  - sales quote/order or invoice
  - purchase order/invoice
  - payment or receipt
  - inventory adjustment
  - production order, if in pilot scope
- Confirm print/export works for pilot reports.
- Confirm queue jobs and scheduler logs show no errors.
- Confirm client feedback workflow is shared with pilot users.
