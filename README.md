# TTW Newsletter Manager

The existing PHP/MySQL newsletter editor now has a deterministic automation backend, category master, mobile REST API and a Flutter Android/iOS application styled after the supplied TTW reference. The six-step editor and original `newsletter_renderer.php` remain the manual creation and rendering surfaces. No AI service or email sending is included.

Start with [the source inspection](ARCHITECTURE.md), [database migration](DATABASE_MIGRATION.md), [deployment](DEPLOYMENT.md), [API reference](API_DOCUMENTATION.md) and [mobile setup](MOBILE_SETUP.md).

## Run

1. Back up the deployment and database, compare the actual newsletter schema with `ARCHITECTURE.md`, and apply `migrations/001_automation.sql` once.
2. Configure database environment variables and HTTPS as described in `DEPLOYMENT.md`.
3. Run `php automation/worker.php` under a process supervisor. A worker processes only user-created jobs.
4. Sign into the existing web admin and open **Automation**. Admins can open **Categories**.
5. In `mobile/`, run `flutter pub get`, then `flutter run --dart-define=API_URL=https://your-host/api/index.php` on Android or iOS.

Workflow: paste links → validate/reorder → optional promotion and manual subject/description → queue article processing → review results/warnings → generate draft → original renderer preview → approve. Drafts appear in the existing library. Approval does not send mail. Missing titles block generation; image failures show a warning and a local placeholder that requires acknowledgment.

## Layout

- `includes/newsletter-service.php`: shared save logic extracted from `save.php`.
- `automation/`: parser, safe HTTP/HTML extraction, image processing, MySQL jobs, CLI worker.
- `api/index.php`: REST controller; query routing works without rewrite rules.
- `automation.php`, `categories.php`, `assets/automation.*`: new web features.
- `mobile/`: complete generated Flutter project with Android/iOS runners and feature screens.
- `tests/`: parser/extraction/image tests and isolated MySQL/API integration tests.

`invoice/`, dated backups, uploaded ZIPs, and `app/app-release.apk` predate these changes and are not part of the new mobile source. Do not publish backup directories or test fixtures to the web server.

The current test APK is [app/ttw-newsletter-automation-debug.apk](https://github.com/ttwsamanta-pixel/TTW-Newsletter-App/releases/download/v0.1.1/ttw-newsletter-automation-debug.apk), with an adjacent SHA-256 checksum. Version 0.1.1 uses the deployed `/New/api/index.php` endpoint; update older installations before testing login. Use an existing active website username/password. The original APK in app/app-release.apk remains intact.

## Validation

Run `php tests/unit.php`. For the isolated database integration suite see `DEPLOYMENT.md`. In `mobile/`, run `flutter analyze` and `flutter test`.

PHP 8.1+ is required by the original code; this implementation was checked with PHP 8.2 and MariaDB 10.4. Flutter dependencies are pinned in `mobile/pubspec.lock`. Production database migration, physical-device acceptance, iOS signing/building and production Android signing remain deployment tasks. See [validation notes](docs/VALIDATION.md) for measured results and limitations.

## GitHub distribution

Download the Android test APK from this repository's Releases page. APK binaries, uploaded media, local credentials, test databases, build caches, dated backups and the separate invoice app are excluded from source control.

For shared hosting, copy `config.local.php.example` to `config.local.php` on the server and enter your existing database credentials. That file is ignored by Git. Environment variables are also supported. Never commit production passwords. Apply the migration only after checking the actual schema and taking a backup.
