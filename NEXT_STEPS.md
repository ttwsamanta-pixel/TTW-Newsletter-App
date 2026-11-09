# Next steps

1. Back up the existing hosted PHP app and newsletter database.
2. Download this repository. Upload root PHP/assets plus api/, automation/, includes/ and migrations/ beneath public_html/New/ for the current subfolder deployment. Preserve existing uploads and server-only credentials. Do not upload mobile/, tests/, source archives or caches into the public web directory.
3. On the server, copy config.local.php.example to config.local.php and enter the existing database host/name/user/password. Configure a long random TTW_API_SECRET. Never upload the example placeholders as real credentials.
4. Check actual database tables/indexes against DATABASE_MIGRATION.md, then run migrations/001_automation.sql in the newsletter database. Resolve any existing duplicate date/edition rows first; skip an equivalent unique index if already present.
5. Run php automation/worker.php continuously using the hosting process manager. It handles user-submitted jobs only. Follow DEPLOYMENT.md for PHP extensions, writable folders and access restrictions.
6. Verify the deployed HTTPS API at https://newsletter.travelandtourworld.com/New/api/index.php?route=/auth/me. On 9 November 2026 it returned the expected HTTP 401 JSON response without credentials. The older root `/api/index.php` returned HTML 404. Use the version 0.1.1 APK configured for `/New/`; the 0.1.0 APK points at the old root location.
7. Open the installed TTW Newsletter app and sign in with an existing permitted web-admin username/password. Test one real header article, manual subject/description, processing, preview and approval before using full daily batches.
8. Configure Android production signing before distributing beyond testing. Build/sign iOS on a Mac with Xcode and an Apple team.

## Already checked on the connected Android phone

The new debug APK installed successfully on model CPH2661. The login screen opened, both empty credential checks worked, and no recent fatal app exception was found. Full authenticated phone testing awaits a reachable deployed API. The original installed 0.1.0 app points to the root API. Update to 0.1.1 for /New/; installing an APK does not deploy its PHP backend.

## Files

If jobs stay at `0/N pending` after setup reports ready, run the background worker. Upload the latest `automation/worker.php` and `automation/cron.php`, then add a Cloudways PHP cron for `New/automation/cron.php` every minute. Supply the real filesystem path/prefix shown by your hosting panel and the application's PHP version. The bounded cron handles already queued jobs and prevents overlapping invocations. See DEPLOYMENT.md / Cloudways cron setup; do not schedule the unlimited worker.php loop every minute.

If Automation shows a configuration error, upload the latest `api/index.php` and `includes/api-diagnostics.php`, stay signed in as admin, and open `https://newsletter.travelandtourworld.com/New/api/index.php?route=/system/check`. Its read-only JSON report lists missing automation tables/columns/PHP extensions. The existing database login can work while new API tables such as api_rate_limits are still absent. Follow DATABASE_MIGRATION.md and apply only missing changes after backup.

For the report listing all five automation tables and both approval columns as missing, import `migrations/002_complete_automation_setup.sql` into the existing newsletter database through phpMyAdmin after taking an export backup. The completion script preserves existing issues/users and handles existing date/edition unique indexes. Review any migration_warning result before proceeding; duplicate issues are reported and retained. Reload the setup check, then the Automation page.

- Android APK: repository Releases / v0.1.1.
- Setup: README.md, DEPLOYMENT.md and MOBILE_SETUP.md.
- Database: DATABASE_MIGRATION.md.
- API: API_DOCUMENTATION.md.
