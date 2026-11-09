# Deployment

## PHP server

Use PHP 8.1+ (tested with 8.2) and MySQL/MariaDB with InnoDB. Extensions: PDO MySQL, cURL, DOM/libxml, mbstring, fileinfo and GD with JPEG/PNG/WebP. Serve through HTTPS. Set the PHP-FPM/Apache environment explicitly; shell-only variables will not automatically reach PHP-FPM.

| Environment variable | Purpose |
|---|---|
| TTW_DB_HOST | Database host; falls back to the existing config |
| TTW_DB_PORT | Default 3306 |
| TTW_DB_NAME | Existing newsletter database |
| TTW_DB_USER | Application database account |
| TTW_DB_PASSWORD | Database password; empty value is supported for isolated tests |
| TTW_APP_BASE_URL | Public HTTPS app origin/base directory for uploaded images |
| TTW_NEWSLETTER_BASE_URL | Existing published-newsletter base URL |
| TTW_API_SECRET | A stable random secret for promotion upload signatures; configure separately from database credentials |
| TTW_IMAGE_HOSTS | Optional comma-separated exact image CDN hosts; empty by default |
| TTW_ALLOW_LOCAL_HTTP | Leave unset in production; `1` enables loopback-only HTTP for local server tests |

The repository contains no production database credentials. Supply environment secrets or copy config.local.php.example to the Git-ignored config.local.php on your hosting server. Do not copy config files into mobile builds. Changing TTW_API_SECRET invalidates outstanding promotion upload signatures; stored bearer tokens use independent random values.

For `/New/` hosting, use `define('APP_BASE_URL', 'https://newsletter.travelandtourworld.com/New');` inside `config.local.php`. A separate `define('TTW_API_SECRET', 'your-random-secret');` configures upload signing. These constants work without `putenv()`, which some hosts disable. The updated `config.php` preserves constants set by the local configuration. Keep the existing DB_HOST, DB_NAME, DB_USER and DB_PASS entries. Do not include a backslash in `https://`.

1. Back up and apply [the database migration](DATABASE_MIGRATION.md) before enabling the new routes.
2. Deploy root PHP files, assets, includes, automation and api. Keep the original newsletter renderer unchanged. Make uploads and storage/sessions writable by the PHP account; prevent executable uploads.
3. Deny public access to includes/, automation/, migrations/, tests/, storage/, mobile/, docs/, all dated backup PHP files and uploaded source archives. Apache deny rules are included for new internal directories. Nginx must supply equivalent rules; `.htaccess` does not apply there. Existing uploads contain source backups, so deploy only intended media files.
4. Enable `AllowOverride`/rewrite if using clean Apache API paths. Query routes need no rewriting. Forward the Authorization header to PHP. Terminate TLS where PHP sees HTTPS enabled; the controller deliberately ignores arbitrary client-supplied forwarded-proto headers. At a trusted reverse proxy, configure FastCGI HTTPS correctly.
5. Set `display_errors=Off`, log_errors=On, upload_max_filesize=8M, post_max_size=12M, memory_limit at least 256M. The existing editor uses base64 images: raise post_max_size as needed for full manual batches while retaining the API's own request limits. Remote image decoding is bounded to 20 megapixels.
6. Run a worker and monitor its process/error logs. No email service or AI key is needed.

## Worker

```sh
php /absolute/path/to/app/automation/worker.php
```

Use systemd/Supervisor or an equivalent service manager with the same environment/database credentials as PHP. Each worker performs one article request at a time. A local file lock prevents overlapping worker/cron invocations in the same app directory. A three-minute database lease permits recovery after a crash. HTTP connect/request timeouts are five/eighteen seconds, redirects at most three, and every hop is host/DNS validated.

`php automation/worker.php --once` processes one queued article and exits. A scheduler may run bounded worker ticks if persistent processes are unavailable; this only services submitted jobs and never schedules newsletter creation. Batches of 30–50 articles run across individual ticks. Users poll status and can leave/reopen a job.

### Cloudways cron setup

Upload the latest `automation/worker.php` and `automation/cron.php`. In the Cloudways application's **Cron Job Management**, add a **PHP** job every minute that executes the absolute filesystem path to `public_html/New/automation/cron.php`. Use your real application directory from hosting Access Details; the HTTPS website URL is not a filesystem path. If the form supplies the public_html prefix itself, enter only `New/automation/cron.php` in its path field. Do not run this CLI-only script as a curl/wget job.

For an Advanced crontab entry, use the application's PHP binary and real path, for example:

```cron
* * * * * /usr/bin/php8.2 /home/master/applications/YOUR_APP_DIRECTORY/public_html/New/automation/cron.php
```

Replace PHP 8.2 and YOUR_APP_DIRECTORY with the actual hosting values. This is an example, not a confirmed server path. Match the website's PHP version/extensions and use the same database configuration. The config.local.php file supplies credentials when shell environment variables are unavailable.

`cron.php` runs a bounded drain: it starts at most 50 articles, stops starting new articles after 50 seconds, and exits immediately when there is no claimable work. The currently running article is allowed to finish. Overlapping cron ticks exit while the lock is held. The next minute handles any remaining articles. It never creates newsletter jobs or approves/sends newsletters.

Manual commands from the `New` directory:

```sh
php automation/worker.php --drain --max-seconds=50 --max-articles=50
php automation/cron.php
```

First run one command in SSH to confirm progress and permissions, then schedule the cron. Jobs already marked pending will be processed; do not submit the same input again just to restart them. If a crashed job has a live lease, allow up to three minutes before reclamation. Keep cron error output available until the first batch succeeds.

Cloudways references: [create application cron jobs](https://support.cloudways.com/en/articles/5124057-how-to-create-cron-jobs-on-cloudways), [use the application's PHP version for cron](https://support.cloudways.com/en/articles/16145921-how-to-change-php-version-for-your-application-on-cloudways).

Per-article rows record URL, section, status, result/error, attempts, start and completion timestamps. Do not add bearer tokens or passwords to logs. Monitor pending jobs, stale processing leases and worker stderr. Delete expired/revoked tokens and stale rate-limit rows using an operator-controlled retention policy. Keep job/result rows for the retention period needed for review. Unused temporary automation images and uploaded promotions need a retention cleanup policy after verifying they are not referenced by newsletters/jobs.

## Verification

Sign in, create a one-article test issue, check the original renderer, approve, and verify it appears in the existing library. Repeat the existing six-step manual editor including image crop, category reorder and autosave. Check login, token expiration/revocation, permission restrictions, warning acknowledgement, duplicate conflicts, draft replacement, promotion upload, retry and worker restart behavior in staging.

An isolated test setup is supplied. Never use production credentials for these commands. Create an empty database named exactly `ttw_automation_test` on an isolated MySQL instance. Configure TTW_DB_HOST, TTW_DB_PORT, TTW_DB_NAME, TTW_DB_USER and TTW_DB_PASSWORD to that instance. `tests/setup.php` additionally guards the database name and creates its test schema/migration/users.

```sh
php tests/setup.php
php tests/unit.php
# With the test DB environment and TTW_ALLOW_LOCAL_HTTP=1:
php -S 127.0.0.1:8088
# In another terminal with the same test DB environment:
php tests/integration.php
php tests/worker-smoke.php
```

The integration suite is intended for a fresh fixture database, uses dates in 2098 and writes test newsletters/images. The smoke test makes an actual allowed HTTPS article/image request and creates a fixture draft in 2097. Test users are test_admin/test_viewer with fixture-only password ttw_test_password. Exclude all test files and databases from deployment.

## Troubleshooting

- **500 configuration error**: inspect server logs/database connectivity, extensions, and migration. API errors deliberately omit SQL/credentials.
- **Automation database setup incomplete**: sign in as admin, then open `api/index.php?route=/system/check` beneath your app directory (for `/New/`, use `/New/api/index.php?route=/system/check`). This read-only report names missing tables/columns/extensions. Apply only missing migration changes after backup; do not reimport a partially applied migration unchanged.
- **426 HTTPS required**: configure PHP's trusted TLS deployment metadata; do not bypass TLS for mobile.
- **Pending jobs**: ensure worker is running with correct environment and migration. After a crashed worker, allow up to three minutes for lease recovery.
- **Missing image**: verify original featured image/host, image limits, GD and uploads permissions. Add only an approved exact CDN hostname if necessary, retry, and review warnings.
- **Unknown category**: add/activate its master entry or alias, then reparse.
- **401**: token expires after seven days or on password change/logout; sign in again.
- **409**: open the existing issue; only unapproved drafts can be explicitly replaced.
- **429**: wait the Retry-After interval; do not create multiple polling timers.
