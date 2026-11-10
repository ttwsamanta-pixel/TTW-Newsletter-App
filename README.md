# TTW Newsletter Manager

## Full manager release 0.3.0

The mobile app now includes the blue TTW manager design, real dashboard totals, filtered newsletter library, manual section editing, notifications, worker monitor, reports, Category Master, employee permissions, profile/settings and secure HTML publishing. The existing PHP engine, shared database, uploads, renderer and cron remain in use.

Deploy the additive database migration before the web update, configure the separate main-website upload API, then install the APK. Start with [UPDATE_0.3.0](docs/UPDATE_0.3.0.md), [HTML publishing](HTML_PUBLISHING.md) and [API additions](API.md). Live publishing and reset mail require server configuration. Earlier release instructions below describe previous versions.

Current downloads: [Android 0.3.0 / build 6](https://github.com/ttwsamanta-pixel/TTW-Newsletter-App/releases/download/v0.3.0/ttw-newsletter-automation-debug.apk), [full web ZIP](https://github.com/ttwsamanta-pixel/TTW-Newsletter-App/releases/download/v0.3.0/TTW_Newsletter_Web_Updated.zip). Deploy inside /New and apply migrations/003_full_manager.sql first. Existing website accounts remain in use.

## Shared uploads web update 0.2.3

New /New newsletters and the installed Android app now save final images in the original root app uploads/YYYY/Mon and working images in uploads/temporary/YYYY/Mon. Old New-only files remain readable. Deploy the v0.2.3 web package inside /New; no SQL or APK reinstall is needed. See [deployment instructions](docs/UPDATE_0.2.3.md).


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

The current test APK is [app/ttw-newsletter-automation-debug.apk](https://github.com/ttwsamanta-pixel/TTW-Newsletter-App/releases/download/v0.2.2/ttw-newsletter-automation-debug.apk), with an adjacent SHA-256 checksum. Version 0.2.2 separates working images into uploads/temporary/YYYY/Mon/ and retains New-to-original image fallback, section-by-section creation and content-based snippets, and uses `/New/api/index.php`. Upload the matching [web update ZIP](https://github.com/ttwsamanta-pixel/TTW-Newsletter-App/releases/download/v0.2.2/ttw-web-update-0.2.2.zip) into `/New/` before installing the APK. Your server-only config.local.php and existing data are not included or replaced. Use an existing active website username/password.

## Creating sections as links arrive

Choose **All links at once** or **Section by section**. Web section mode uses the existing six-step editor; mobile has Basic, Header, Pick News, More News, Categories and Promotion tabs. Paste only the selected section's links. Imports replace that section (or named category groups), while other content is kept. More News and Categories can be saved first; Header and Pick News can arrive later. The existing worker/cron processes these jobs.

Titles prefer the article's visible H1 and strip only a trailing `- Travel And Tour World` / equivalent site separator. Header descriptions use the first 35 article-body words; Pick News descriptions and Hidden Preview Text use the first 16, followed by `...`. Metadata excerpts are not used for these fields. Email Title stays manual and optional while drafting/generating; add it before approval. Existing issues are not rewritten automatically: open a draft and use **Import / refresh** to refetch a selected section's prefilled links.

No additional SQL migration is needed for 0.2.2 if the earlier automation setup check is already ready. See [upgrade instructions](docs/UPDATE_0.2.2.md).

## Validation

Run `php tests/unit.php`. For the isolated database integration suite see `DEPLOYMENT.md`. In `mobile/`, run `flutter analyze` and `flutter test`.

PHP 8.1+ is required by the original code; this implementation was checked with PHP 8.2 and MariaDB 10.4. Flutter dependencies are pinned in `mobile/pubspec.lock`. Production database migration, physical-device acceptance, iOS signing/building and production Android signing remain deployment tasks. See [validation notes](docs/VALIDATION.md) for measured results and limitations.

## GitHub distribution

Download the Android test APK from this repository's Releases page. APK binaries, uploaded media, local credentials, test databases, build caches, dated backups and the separate invoice app are excluded from source control.

For shared hosting, copy `config.local.php.example` to `config.local.php` on the server and enter your existing database credentials. That file is ignored by Git. Environment variables are also supported. Never commit production passwords. Apply the migration only after checking the actual schema and taking a backup.
