# Delivery map

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

`newsletter_renderer.php`, original assets/app.js/app.css, invoice application, uploaded backups and historical sample HTML were not changed.
