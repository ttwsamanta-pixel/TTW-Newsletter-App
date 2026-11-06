# Validation record

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
