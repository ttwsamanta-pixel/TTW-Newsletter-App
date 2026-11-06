# Existing application inspection

The source of truth is the root PHP application. `invoice/` is a separate invoice application; `uploads/Newsletter App Updated(29.06.2026)/` and dated PHP files are backups. `app/app-release.apk` has no accompanying mobile source. No root newsletter schema or repository metadata was supplied. The live schema must be verified before migration; column types and foreign-key rules cannot be proven from PHP alone.

## Existing architecture and data

Plain PHP entry points use PDO/MySQL through `config.php`. `auth.php` provides sessions, password verification, admin/employee roles and configurable view/create/edit/delete/download permissions. No approval permission/state exists. Employees with create/edit permissions serve as editors; retain this identity system.

Observed tables and columns from SQL in source:

- `users`: id, username, password_hash, role, permissions, status, first_name, last_name, contact_no, address, photo, timestamps.
- `newsletters`: id, newsletter_date, edition (M/E), subject_title, hidden_text, header_title, header_description, header_url, header_image, promotion_enabled, promotion_text, promotion_url, promotion_image, promotion_width, promotion_height, created_by, updated_by, timestamps.
- `pick_news`: id, newsletter_id, sort_order, title, description, url, image.
- `more_travel_news`: id, newsletter_id, sort_order, title, url.
- `newsletter_categories`: id, newsletter_id, sort_order, category_name, category_url. These are issue sections, not a reusable category master.
- `category_news`: id, category_id, sort_order, title, url.

`editor.php` has Basic, Header, Pick News, More News, Categories and Promotion steps. `assets/app.js` autosaves via `save.php` between steps, handles ordering, clipboard uploads and canvas cropping. `save.php` uses a transaction and replaces child collections on update. Existing duplicate date/edition behavior opens/updates an issue; automation must require explicit draft replacement.

## Images and rendering

`editor.php::cropper()` and `assets/app.js::initCroppers()` produce header 610x380 and pick 350x291 JPEG data URLs. Promotions use `original_uploader()`/`initOriginalUploads()` and default display size 200x170. `config.php::save_cropped_image()` stores under uploads/YYYY/Mon and returns a relative path. More News/category items have no image columns and render as links.

`newsletter_renderer.php::load_newsletter()` loads all collections; `render_newsletter()` is used by `view.php` and `download.php`. Preserve this file and its HTML design. API previews call the same renderer. `newsletters.php` lists the shared table; reports use created_by/updated_by.

## Implementation plan

Modify `save.php` to call a shared service while retaining form/autosave contracts; extend `auth.php` with navigation and shared CSRF helpers. Add standalone automation/category pages without redesigning the editor. Add API infrastructure, deterministic parser, safe fetch/extraction/image services, MySQL queue, CLI worker, migration, tests and deployment guides. Approval becomes a nullable timestamp and approver on the existing newsletter row; edit invalidates approval.

Security implementation also adds shared CSRF helpers to auth.php and hidden tokens to login, profile, employee and bulk forms. Existing GET logout/delete links become equivalent POST actions so reading a URL cannot mutate data. Newsletter library displays draft/approved state. Config accepts environment overrides and validates saved image bytes and dates. The editor's six-step structure and renderer HTML are unchanged.

New tables: category master, hashed expiring/revocable API tokens, persistent rate limits, automation jobs and per-article results/logs. New newsletter columns: approved_at, approved_by. Check duplicate date+edition rows before adding a unique index. New tables avoid foreign keys to unknown existing integer types; application validates users/issues. Existing newsletter tables remain authoritative.

REST: `api/index.php?route=/...`, with optional Apache clean routes. HTTPS, bearer authentication for mobile, session+CSRF for web, existing permissions, bounded JSON/uploads, rate limits, sanitized errors. Routes cover auth, categories, newsletters/preview/approval, parse/jobs/retry/generate and promotion upload. Workers process one bounded article at a time, persist progress and recover expired leases. Generation is a separate action after validation; image warnings are explicitly acknowledged, missing titles block generation. No mail sending or AI integration.

Flutter: generated Android/iOS runners, secure token storage, one API client, ChangeNotifier auth state, feature screens for dashboard/history, four-stage creation/review/details/generation, category browser, article inspection and authenticated WebView preview. Original article metadata comes only from the server. Theme follows supplied navy/white/red reference and uses the existing logo.
