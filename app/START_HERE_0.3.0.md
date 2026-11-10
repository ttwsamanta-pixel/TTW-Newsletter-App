# TTW Newsletter Manager 0.3.0

This update extends the existing PHP/Flutter app into the full mobile manager. It keeps the shared database, original renderer, manual web editor, automation cron, deterministic title/body rules and shared root image folders. The earlier local backup remains under backups/TTW_2026-11-10_14-26-08.

## New mobile screens

Blue/navy TTW theme; branded loading/login and Forgot Password; real dashboard totals and today's status; Home / Newsletters / Create / Reports / More navigation; searchable/filterable library; newsletter details and history; existing HTML preview/approval; explicit creation-mode selection; six freely navigable creation sections; editable hidden text; Header/Pick image previews; manual title/description edits, reorder and removal; generation success; notifications with persistent read state; worker monitor and bounded admin Run Now; monthly donut/daily chart, calendar and employee reports; searchable Category Master and employee lists; category/alias/order/status editing; employee permissions/status; profile/photo/password; server status, help/about/logout; HTML save/copy and publication actions.

Notifications are in-app events derived from the existing newsletter/job records, not push notifications. Worker status reports observed heartbeat time, not a fabricated cron-running state. Run Now processes one article through the existing lease mechanism; it does not replace the cron.

## Deployment order

1. Export a backup of the live shared database. The existing local backup is a project backup, not a live database export.
2. Import **TTW_Manager_Database_Update.sql** (same as migrations/003_full_manager.sql) into the existing database. If you only have FileZilla access, use the temporary admin-only **upgrade-full-manager.php** page instead; follow **TEMPORARY_UPGRADE_README.md**, download its backup, run the upgrade, then remove the PHP page. Use one method; no second SQL import is needed after the page succeeds. The migration adds three metadata tables and a nullable users.email column if absent. It preserves existing data. Do not import tests/fixture-schema.sql or old initial migrations into an already configured deployment.
3. Extract **TTW_Newsletter_Web_Updated.zip inside the existing public_html/New/**, overwriting supplied files. Keep server-only config.local.php, uploads and storage. Alternatively, use the changed-files ZIP only when updating the existing 0.2.3 deployment. Never upload backups/, tests/, mobile/, local credentials or development caches to the public web directory.
4. Deploy **TTW_Main_Website_Upload_API.zip on the MAIN WEBSITE public_html/** and configure both servers using HTML_PUBLISHING.md. Use one private random token in the two server-only configuration files. Do not put it in the app or frontend.
5. Keep `New/automation/cron.php` every minute. Restart a continuously running worker after code updates. Worker heartbeat is stored privately in New/storage. Shared uploads remain root uploads/YYYY/Mon, temporary/YYYY/Mon and profiles; old New images remain fallback.
6. Sign in as admin and check `/New/api/index.php?route=/system/check`. Check server status and test a new draft, edits, preview, approval, download and publication. Test first publication with an unused test date/edition. Verify View HTML on the returned public URL.
7. Install Android **0.3.0 / code 6** over the existing app after backend/migration deployment. Use the existing website account. Old clients retain compatible routes; the new manager screens require this backend update. APK is a debug build for testing; configure production signing before broad distribution.

## Password reset email

Existing accounts often have usernames rather than stored email addresses. The new nullable email field is available in Profile and Employee management. An admin can register an employee's email. Users must enter their current password when changing their own registered email.

Configure `TTW_RESET_FROM` in New/config.local.php to a valid sender and configure the hosting PHP mail transport. Forgot Password sends a 30-minute, single-use link to the account's registered email (or an email-format username). Unknown accounts get the same generic response. An unconfigured mail sender displays a useful setup error. Tokens are stored only as hashes, never returned to Flutter or logged. Resetting/changing a password revokes API tokens. If delivery fails, the token is invalidated. Test delivery on hosting before relying on it.

## Packages

- TTW_Newsletter_Web_Updated.zip: full web runtime and upgrade guide, without data or secrets.
- TTW_Newsletter_Web_Changed_Files.zip: changed runtime files for an existing 0.2.3 installation.
- TTW_Main_Website_Upload_API.zip: separate main-domain receiver.
- TTW_Manager_Database_Update.sql: additive migration; import through phpMyAdmin, not via a public web URL.
- ttw-newsletter-automation-debug.apk: Android 0.3.0 / code 6.
- TTW_Newsletter_Mobile_Source.zip: Flutter Android/iOS source, dependency lockfile and tests, without build caches or signing keys. iOS compilation/signing still requires macOS/Xcode and an Apple development team.

## Acceptance limits

The code and local integration are validated independently of production. Main-domain deployment, production database migration, mail delivery and phone acceptance against the newly deployed backend remain operator steps. No live database credentials, main-domain secret or publishing action was used in development tests. This update does not add AI services, Zoho campaigns or email campaign sending.
