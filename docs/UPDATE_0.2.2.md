# Update 0.2.2: separate working image folder

New automation working images, including promotion uploads and generated missing-image placeholders, are saved under **uploads/temporary/YYYY/Mon/auto_....jpg**, grouped by the newsletter's issue month. For example: `/New/uploads/temporary/2026/Nov/`.

Final Header, Pick News and promotion images continue to use **uploads/YYYY/Mon/** with their existing newsletter filenames. The temporary-to-final copy remains intentional; only its location changed. The previous New → original application → placeholder image fallback is retained.

## Deploy

1. Back up deployed files. Extract **ttw-web-update-0.2.2.zip inside the existing /New/** directory, overwriting supplied application files.
2. Keep the server's config.local.php, uploads and storage folders. They are not included in the full web ZIP. No SQL import is needed.
3. Keep the existing PHP cron. The worker creates temporary month directories automatically; uploads must be writable by PHP. Restart a continuously running worker so it loads the updated code. For the bounded cron, the next invocation uses it.
4. Hard-refresh the browser, then install the v0.2.2 Android APK over the existing app. Use the same account. This change is server-side; the existing 0.2.1 app also receives new temporary paths without needing a client code change.

## Existing files and later deletion

Existing `auto_` files are not moved or deleted. Old paths remain accepted for pending jobs, imports and signed promotions. New working images use the new folder only after deploying the updated backend.

You can later delete an old **temporary month folder** after all its imports have been applied/saved to newsletters and no pending/running/retry jobs or unsaved promotion uploads need those files. A completed job that has not been applied to a draft still needs its working images. Deleting them early requires refetching/reuploading. Final saved newsletters use separate final files, so deleting their unused working copies does not remove saved newsletter images.

No automated deletion is added. Never delete the corresponding final `uploads/YYYY/Mon` folder as a temporary cleanup. The month is the issue date, not necessarily the processing date, so confirm jobs are finished even for an old month.

## Validation

13 focused checks passed for new folder placement, image dimensions, final copy bytes, old path compatibility, signed promotions, path validation and saved image survival after working-file deletion. The 38 existing PHP unit checks and 25 image fallback checks also passed. Android 0.2.2 / build 5 was built. Phone acceptance follows backend deployment.
