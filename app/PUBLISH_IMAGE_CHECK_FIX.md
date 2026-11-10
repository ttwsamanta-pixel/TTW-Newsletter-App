# Publish image check fix

The main site rejected requests with an empty User-Agent (HTTP 403), while the same public images returned HTTP 200 using the TTW article-fetcher User-Agent. This patch adds that User-Agent to image checks and the main-site upload request. It preserves HTTPS, DNS/IP restrictions, image checks, token authentication and duplicate protection. Errors now identify the failed public image host/path and HTTP status without exposing query strings or tokens.

1. Extract TTW_Publish_Image_Check_Fix.zip.
2. Upload **includes/publishing.php** into the NEWSLETTER application at **public_html/New/includes/publishing.php**, overwriting that file. Keep config.local.php and its real token unchanged.
3. Reload the approved newsletter preview and click **Publish HTML** again. Do not use Replace Published HTML unless the site reports a different file already exists and you intend to replace it.
4. Success displays **HTML published successfully**, a **View HTML** link and the public URL. Open the link to confirm. FileZilla on the MAIN website should also show the matching HTML file.

For the 10 November 2026 Evening issue, the destination is:

**MAIN WEBSITE filesystem:** public_html/newsletter/2026/Nov/TTW_newsletter_10Nov2026E.html

**Public URL:** https://www.travelandtourworld.com/newsletter/2026/Nov/TTW_newsletter_10Nov2026E.html

Only the generated HTML file is uploaded. Images remain at their existing public HTTPS URLs on the image hosting site. The HTML file is not saved inside newsletter-api/ or the newsletter application's /New/ folder.

No database or APK update is needed. The nested receiver support is included in this replacement publishing.php. Public image probes passed from development, but a successful production upload still requires retrying from your deployed application. If another check fails, share the new error message without sharing passwords or tokens.
