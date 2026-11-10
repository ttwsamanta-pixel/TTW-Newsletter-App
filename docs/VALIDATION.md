# Validation record

## Full manager 0.3.0 - 10 November 2026

- 19 Flutter tests passed; Flutter analysis clean. Covers existing import flow, More-before-Header drafting, real dashboard values, library filters, publish confirmation, employee permissions, notification acknowledgements and scoped article removal.
- 185 PHP checks passed: 38 extraction/parser, 12 shared uploads, 13 temporary images, 25 image fallback, 23 independent receiver, 55 manager/API integration, 6 reset-token, 3 worker/heartbeat/lock, 3 publish-failure, 2 promotion-month and 5 sanitized diagnostic checks. Migration 003 was applied twice to an isolated fixture database.
- Browser acceptance passed for PHP web login/preview, explicit approval, Publish HTML to a separate local receiver, returned URL, original download and CSRF rejection. Flutter rendered at 390px using authenticated fixture data; login/dashboard/library/reports/settings were inspected.
- Android 0.3.0 / code 6 built successfully with a TTW vector launcher icon derived from the existing logo. Flutter web preview also built. Source dependency versions were preserved. iOS source/configuration is included; no iOS binary was built on Windows.
- Fixtures used loopback servers and isolated MySQL on port 33319. No production database, real account login, publishing secret or live publication was used. Phone acceptance against the upgraded backend, live main-domain deployment and reset-mail delivery remain deployment checks.

See [upgrade guide](UPDATE_0.3.0.md), [dashboard](mobile-manager-dashboard.png), [library](mobile-manager-library.png), [reports](mobile-manager-reports.png) and [web publication](web-html-published.png).

## Shared uploads web update 0.2.3

Passed 12 shared storage checks, 13 temporary image checks, 38 unit checks and 25 image fallback checks, plus PHP syntax validation. No production data used. Mobile code is unchanged; deployed Android 0.2.2 works with the updated backend. See UPDATE_0.2.3.md.


## Temporary image folders - 0.2.2

Passed 13 focused temporary-file checks, 38 existing unit checks and 25 image fallback checks. Coverage includes final byte-preserving copies, old jobs/promotions, new paths and final-image survival after working-file deletion. Android code 5 / version 0.2.2 built successfully. No production database or uploaded files were modified.


## Image fallback update - 9 November 2026 (0.2.1)

Passed 25 PHP fallback checks, 38 existing unit checks, PHP syntax checks, all 12 Flutter tests and clean Flutter analysis. Isolated HTTP/browser tests with JavaScript disabled verified primary/legacy/placeholder images, newsletter/profile rendering, cache recovery, HEAD and unsafe-path rejection. Android version 0.2.1 / build 4 built successfully. No production database was used. Physical acceptance awaits the matching backend upload. See UPDATE_0.2.1.md.


## Section workflow and source-text update — 9 November 2026 (0.2.0)

Implemented original visible-heading extraction, trailing TTW SEO suffix cleanup, Header body prefix 35 words, Pick/Hidden Preview body prefix 16 words, optional manual Email Title during drafting, and both all-links and six-step section imports. Scoped generation preserves unrelated latest draft data and named category groups. Partial previews skip an absent Header; complete newsletter layout remains shared.

Passed 38 PHP unit checks, 24 new section API/service workflow checks, 32 existing API/editor checks, 11 Flutter tests and clean Flutter analysis. Browser tests passed with real TTW pages/images and cron, including More → Categories → Header → Pick, adding Email Title last, returning to editing without applying queued results, and responsive layout. Existing all-links browser flow and worker CLI checks also passed. Android v0.2.0 / code 3 built successfully. Physical testing of this new release awaits deployment of the matching backend; do not confuse prior 0.1.1 login verification with 0.2.0 section-workspace hardware acceptance.

The web update ZIP excludes server credentials, config.local.php, uploads, storage, databases and SQL. No new schema migration is required when the existing automation setup is ready. Tests used isolated local databases; live production issues were not modified. See [upgrade guide](UPDATE_0.2.0.md), [web workflow screenshot](web-section-workflow.png) and [mobile web screenshot](web-section-mobile.png).

## Android API-path correction — 9 November 2026

Version 0.1.1 / build 2 uses `https://newsletter.travelandtourworld.com/New/api/index.php`. The previous 0.1.0 APK used the root API location. Live checks found HTML 404 at the root and the expected JSON 401 at `/New/`. A POST with a deliberately invalid test account reached the `/New/` login handler and returned JSON `Invalid username or password.` No real account credentials were tested.

Flutter analysis passed and all 10 Flutter tests passed, including a new check that the default login route targets `/New/api/index.php`. The phone was reconnected during the build. Version 0.1.1 / code 2 was installed successfully on CPH2661 and its activity launched. The package version was verified through ADB. The user confirmed that an existing website account successfully opens the dashboard in the updated phone app. Earlier physical smoke checks below refer to 0.1.0.

## Original implementation checks

Validation performed on 6 November 2026 in the supplied Windows workspace. Production database and hosting were not modified.

| Check | Result |
|---|---|
| PHP syntax, all root/new PHP files | Pass |
| JavaScript syntax | Pass |
| PHP unit suite | 26 checks passed |
| Isolated MariaDB/API integration | 32 checks passed |
| Real TTW URL worker smoke | Original title, description and image fetched; 610x380 image saved; draft rendered; completed |
| Browser acceptance, desktop and 390px viewport | Login, parse, review, promotion upload, real worker, generate, preview and approve passed; no page errors or horizontal overflow |
| Flutter analyze | No issues found |
| Flutter tests | 9 passed, including credential validation, secure storage/auth restoration/logout, API errors/conflicts and mobile input→review→details→job flow |
| Android debug APK | Built successfully |
| Production migration/deployment | Not run |
| iOS build/signing | Requires macOS/Xcode; not run |
| Android phone smoke check | Installed on connected CPH2661, launched, empty credential validation passed, process remained active with no recent fatal exception. Real sign-in blocked by hosted API returning HTML 403. Camera/upload/WebView acceptance still pending backend availability. |

The database fixture was reconstructed only for testing from SQL observed in the original PHP source. It does not establish the actual production column types, indexes or foreign keys. Production schema inspection remains mandatory before applying migration.

Browser screenshots: [dashboard](web-dashboard.png), [responsive mobile web](web-mobile.png), [newsletter preview](web-preview.png). Dates in 2096–2098 and test account names in screenshots are isolated fixture data. These are browser screenshots, not claims of physical mobile-device testing.

Android fixes applied during verification: quote Gradle wrapper assignments for the workspace ampersand, disable Kotlin incremental compilation for cross-drive Pub/workspace paths, and bound Gradle worker concurrency. Release signing uses a separately supplied keystore; the delivered APK is a debug build for testing. It targets the configured production API URL, whose new endpoints must be deployed first.

New web/API functionality has authentication/authorization, CSRF, throttling, upload/remote-fetch validation and generic API errors. Existing session/remember-cookie lifetimes and the original identity system were preserved. This validation is not a penetration test or a production load test. Fifty-URL parser bounds are tested; live browser/worker runs used small real batches to avoid unnecessary traffic to TTW.

Phone validation screenshot: [installed Android app](android-phone-validation.png).
