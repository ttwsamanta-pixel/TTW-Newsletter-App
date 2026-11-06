# Next steps

1. Back up the existing hosted PHP app and newsletter database.
2. Download this repository. Upload root PHP/assets plus api/, automation/, includes/ and migrations/ to the existing newsletter application directory. Preserve existing uploads and server-only credentials. Do not upload mobile/, tests/, source archives or caches into the public web directory.
3. On the server, copy config.local.php.example to config.local.php and enter the existing database host/name/user/password. Configure a long random TTW_API_SECRET. Never upload the example placeholders as real credentials.
4. Check actual database tables/indexes against DATABASE_MIGRATION.md, then run migrations/001_automation.sql in the newsletter database. Resolve any existing duplicate date/edition rows first; skip an equivalent unique index if already present.
5. Run php automation/worker.php continuously using the hosting process manager. It handles user-submitted jobs only. Follow DEPLOYMENT.md for PHP extensions, writable folders and access restrictions.
6. Fix the current HTTPS API access issue. On 6 November 2026, GET https://newsletter.travelandtourworld.com/api/index.php?route=/auth/me returned HTTP 403 with HTML. Ask the host to check file permissions, WAF/security rules, API deployment and Authorization forwarding. A correctly deployed unauthenticated request should return HTTP 401 with JSON, not an HTML page. Do not disable authentication or HTTPS.
7. Open the installed TTW Newsletter app and sign in with an existing permitted web-admin username/password. Test one real header article, manual subject/description, processing, preview and approval before using full daily batches.
8. Configure Android production signing before distributing beyond testing. Build/sign iOS on a Mac with Xcode and an Apple team.

## Already checked on the connected Android phone

The new debug APK installed successfully on model CPH2661. The login screen opened, both empty credential checks worked, and no recent fatal app exception was found. Full authenticated phone testing awaits a reachable deployed API. The installed app points to the production HTTPS domain; installing the APK does not deploy its PHP backend.

## Files

- Android APK: repository Releases / v0.1.0.
- Setup: README.md, DEPLOYMENT.md and MOBILE_SETUP.md.
- Database: DATABASE_MIGRATION.md.
- API: API_DOCUMENTATION.md.
