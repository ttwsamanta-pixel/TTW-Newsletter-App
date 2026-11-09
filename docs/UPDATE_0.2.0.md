# Update 0.2.0

## Changes requested

1. Use the original visible article heading; remove only the trailing TTW site-name suffix from all imported sections.
2. Header Description: first **35 words** of article content, then `...` (within the requested 34–36 range).
3. Pick News Description: first **16 words** of article content, then `...` (within the requested 15–17 range).
4. Hidden Preview Text: first **16 words** of the Header article content, then `...`. Email Title is manually entered, optional while saving/generating, and can be added after content is complete. Approval still requires the title and Header.
5. Keep all-links automation, and add separate imports in the six-step web editor and mobile workspace. More News/Categories can be imported before Header/Pick News. A scoped import retains other sections and images. A category import replaces only its matching master category groups.

These are deterministic opening-text snippets, not AI summaries. Metadata descriptions/excerpts are not substituted when article body is unavailable. Missing article body is surfaced as a warning. An invalid URL that redirects to a generic news listing is not imported as that listing's title/content.

## Hosting update

1. Back up the deployed files. Download `ttw-web-update-0.2.0.zip` from Releases.
2. Extract its contents **inside the existing `/New/` directory**, preserving folder structure. The ZIP supplies current public PHP files, api/, automation/, includes/, assets/ and logos. It does not contain config.local.php, uploads, database exports, SQL migrations, storage, mobile source or credentials. Keep the existing server-only config.local.php untouched.
3. Keep the PHP cron running `New/automation/cron.php` every minute. The two input modes use the same queue/worker.
4. Hard-refresh the browser (Ctrl+F5). Open `/New/automation.php` for all-links input or `/New/editor.php` for six-step input.
5. If `/system/check` was already ready before this update, no further SQL import is needed. This feature reuses existing newsletter tables and the job payload field.
6. Install the v0.2.0 Android APK over the existing app after updating the backend. It uses the same account and `/New/` API. iOS source is updated; builds/signing still require macOS/Xcode.

## Existing drafts and saved text

Existing saved issues and approved newsletters are not bulk-modified. To correct an existing draft, open it in the updated editor. In Header, Pick News, More News or Categories, expand **Import / refresh ... from article links**. Its existing links are prefilled; import them to refresh only that section. Review warnings if any. Header refresh also updates Hidden Preview Text. Add Email Title later on Basic. Manual fields remain editable.

Section imports into an approved issue require reopening it as a draft through the explicit Edit Draft flow first. Do not refresh old approved/sent newsletters merely to test the new extraction rules.

## Validation

- 38 PHP unit checks, including H1/SEO suffix precedence, exact body word counts, missing-body behavior and partial parsing.
- 24 API/service section workflow checks, including More/Categories before Header, scoped merges, category preservation, optional title and late approval.
- 32 existing API/editor integration checks.
- Browser acceptance: More → Categories → Header → Pick, real article fetching/images via cron, late Email Title, responsive layout; all-links flow also passed.
- 11 Flutter tests, including More-first mobile navigation/import/save and late title entry; analysis clean.
- Worker CLI regression: empty queue exit, overlap lock, bounded processing and real article drain.

These checks use an isolated fixture database; no production database or existing live issue was modified by the development tests.
