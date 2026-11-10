# Web update 0.2.3: shared root uploads

New newsletters created through `/New/`, including the connected mobile app, now save into the original application's shared folder:

- Final images: `/public_html/uploads/YYYY/Mon/`.
- Working images: `/public_html/uploads/temporary/YYYY/Mon/`.
- Profile images: `/public_html/uploads/profiles/`.

The original root application already uses `public_html/uploads` for final images, so both applications now use the same final files. The root manual application needs no code changes. The shared working folder is used by the New automation process. Folder names are lowercase. Months follow the newsletter issue date.

## Upload

1. Back up the deployed application files.
2. Extract **ttw-web-update-0.2.3.zip inside the existing `/New/` folder**, keeping folder structure and overwriting supplied application files. Do not upload this package over the original root application's code.
3. Keep `config.local.php`, `uploads` and `storage`; this package excludes them and contains no database SQL or credentials. No database import or path rewrite is required. Keep `APP_BASE_URL` ending in `/New`.
4. Ensure the PHP account running `/New/` can write to `/public_html/uploads` and its subfolders. The app creates month/temporary folders automatically. A permissions failure produces an error instead of silently writing a second copy under New.
5. Keep the existing PHP cron; restart any continuously running worker. Hard-refresh the browser. Create one new draft and check its images appear in the shared root uploads folder. The installed Android v0.2.2 app works with this server update; no APK reinstall is required.

For a nonstandard layout only, set `IMAGE_UPLOAD_ROOT` to the absolute shared uploads directory in server-only config.local.php. `LEGACY_IMAGE_UPLOAD_ROOT` must point to the old `New/uploads` directory for fallback. If you previously set either constant explicitly, review it: existing explicit constants take precedence over the new defaults. Do not replace your real configuration with example credentials.

## Existing files and cleanup

Old files are not moved, deleted or copied automatically. Old New-only images and queued jobs remain readable through the fallback directory. Shared root images now take precedence when both directories have the same filename, so root application previews and New previews use the same canonical image. Existing newsletter filenames/date-edition rules remain unchanged.

Keep old New/uploads until its remaining references have been reviewed. To clean shared temporary month folders later, first finish/save/apply all related imports and ensure no queued/running/retry jobs or unsaved promotion uploads need them. A completed but unapplied job still needs working images. Do not delete final `uploads/YYYY/Mon` folders as temporary cleanup.

Previously downloaded HTML keeps its URLs; download again if it used old direct URLs. Current previews/downloads use image.php and the shared resolver.

## Changed runtime files

config.php, includes/image-paths.php, automation/fetcher.php, automation/drafts.php and auth.php. The full ZIP also includes the existing web application files. A smaller changed-files ZIP contains these five files and this guide, for an already deployed v0.2.2 application.

## Validation

12 shared-storage checks, 13 temporary-image checks, 38 existing unit checks and 25 image fallback checks passed. Checks cover saving final files at root-app paths, temporary files, old New jobs/promotions, fallback previews, source priority, path rejection and temporary deletion preserving final files. PHP syntax checks passed. Tests used isolated local folders, not production data. Production upload and end-to-end phone acceptance remain deployment steps.
