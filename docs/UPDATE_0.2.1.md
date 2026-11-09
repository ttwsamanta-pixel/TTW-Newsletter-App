# Update 0.2.1: shared newsletter images

Images now load in this order: **New application uploads → original application uploads → placeholder**. This applies to saved newsletter previews, HTML downloads, editor image previews and mobile newsletter previews. Header, Pick News, promotion and profile images use the same resolver. Existing same-site absolute URLs also follow this order. External image URLs remain unchanged.

The server checks actual image files, including corrupt images, before choosing a source. The fallback works without JavaScript or a signed-in browser session, so email HTML and mobile WebViews can display it. Existing database paths are preserved. No SQL import, newsletter regeneration or copying old images is required.

## Upload the full web ZIP

1. Back up the deployed files.
2. Extract **ttw-web-update-0.2.1.zip inside the existing public_html/New/** directory, keeping its folder structure. Overwrite the supplied application files. Do not create another New folder inside New.
3. Keep the existing **config.local.php**, **uploads/** and **storage/**. They are excluded from this ZIP, along with credentials, SQL, database exports, tests and mobile source. The original application outside New does not need updating.
4. Keep the existing PHP cron `New/automation/cron.php` running every minute. No database migration is needed for an already working automation setup.
5. Reload with Ctrl+F5 and preview a newsletter originally created in the root application. Then install the v0.2.1 Android APK over the existing app and use the same website account.

This is a full web application package, including the previous title/snippet and section workflow changes from 0.2.0. It includes the required new **image.php** and **includes/image-paths.php** files. Upload the backend before installing the matching APK. Previously exported HTML retains its original URLs; download it again to obtain the new resolver URLs.

## Hosting paths

With `APP_BASE_URL=https://newsletter.travelandtourworld.com/New`, the default primary directory is `public_html/New/uploads` and the legacy directory is `public_html/uploads`. PHP must be able to read both directories. This matches the existing Cloudways folder layout.

Only for a different layout, set `LEGACY_IMAGE_UPLOAD_ROOT` in server-only config.local.php to the absolute original uploads directory. `IMAGE_UPLOAD_ROOT` and `LEGACY_APP_BASE_URL` can also be overridden there. No changes to these settings are normally needed. The public image endpoint only serves validated raster images inside the configured uploads directories; it never fetches arbitrary remote URLs.

## Validation

25 PHP image fallback checks and 38 existing unit checks passed. A browser test with JavaScript disabled verified New/legacy/placeholder selection, newsletter and profile rendering, corrupt images, cache recovery, HEAD and unsafe-path rejection. All 12 Flutter tests passed and Flutter analysis was clean. Android 0.2.1 / build 4 was built successfully. Physical acceptance of this version requires the matching backend deployment; it has not been claimed as tested on the phone.
