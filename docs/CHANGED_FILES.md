# Delivery map

## Full manager 0.3.0

Version 0.3.0 adds manager/publishing/password-reset services and API routes, a separate newsletter-api receiver, migration 003, web publishing/approval actions, reset form and worker heartbeat. Flutter gains full blue manager navigation and management screens while reusing existing create/job/preview flows. Adds receiver, manager, password-reset and mobile integration tests. See UPDATE_0.3.0.md.


## Version 0.2.2

Changes automation/fetcher.php to write processed images into uploads/temporary/YYYY/Mon and automation/drafts.php to accept both new and legacy working paths. Adds tests/temporary-images.php; final newsletter file handling is preserved. Android version advances to 0.2.2 / build 5.


## Version 0.2.1

Adds image.php and includes/image-paths.php; config helpers, shared newsletter renderer, editor/profile/promotion previews and Flutter image URLs now resolve New uploads, original uploads and placeholders in order. Adds PHP/HTTP/browser/Flutter regression coverage. No database migration or existing image data is changed.


## Existing root files modified

- `config.php`: environment overrides, saved-image validation/write checks.
- `auth.php`: Automation/Categories navigation, shared CSRF helpers, protected profile/logout forms, profile image size bounds.
- `save.php`: existing endpoint delegates to shared persistence, checks CSRF.
- `editor.php`: CSRF token; six-step editor retained.
- `newsletters.php`: draft/approved display and protected POST deletes.
- `login.php`, `logout.php`, `profile_update.php`, `employees.php`, `employee_save.php`, `delete.php`, `bulk-delete.php`: CSRF and POST mutation handling.

## New backend/web files

- `includes/newsletter-service.php`, `includes/security.php` and deny rule.
- `automation/parser.php`, `fetcher.php`, `jobs.php`, `worker.php` and deny rule.
- `api/index.php`, `api/.htaccess`.
- `automation.php`, `categories.php`, `assets/automation.css`, `assets/automation.js`.
- `migrations/001_automation.sql` and deny rule.

## Mobile

- `mobile/`: Flutter project with source, dependency lock, Android/iOS platform configurations, secure auth, dashboard/history/category/settings, creation/review/promotion, jobs and native draft editing/renderer preview/approval.
- `app/ttw-newsletter-automation-debug.apk` and checksum: new debug artifact; existing `app/app-release.apk` retained.

## Tests and documentation

- `tests/unit.php`, `fixture-schema.sql`, `setup.php`, `integration.php`, `worker-smoke.php`, `e2e.cjs` and deny rule.
- `mobile/test/`: API, auth/login and creation workflow tests.
- `README.md`, `ARCHITECTURE.md`, `API_DOCUMENTATION.md`, `DATABASE_MIGRATION.md`, `DEPLOYMENT.md`, `MOBILE_SETUP.md`, `docs/VALIDATION.md`.
- `docs/web-dashboard.png`, `web-mobile.png`, `web-preview.png`: browser acceptance screenshots.

`newsletter_renderer.php` remains the shared renderer; v0.2.0 only skips the Header block when a partial draft has no Header yet. Original assets/app.js/app.css, invoice application, uploaded backups and historical sample HTML were not changed.

Version 0.2.0 adds automation/drafts.php, assets/section-import.js, mobile/lib/features/create_newsletter/sections.dart and scoped-import tests. See UPDATE_0.2.0.md for deployment and the exact text rules.
