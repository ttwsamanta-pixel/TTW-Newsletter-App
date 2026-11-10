# Main-domain HTML publishing

## Receiver inside newsletter/ (also supported)

For `https://www.travelandtourworld.com/newsletter/newsletter-api/upload.php`, use the nested-location update in **app/NESTED_PUBLISHING_UPDATE.md**. It provides the application patch and the complete receiver package. The receiver detects its HTML root for either supported location. Existing explicit NEWSLETTER_HTML_ROOT settings still take precedence: in the nested location use `dirname(__DIR__)`, not `dirname(__DIR__).'/newsletter'`. Keep PHP enabled for the receiver subfolder only while disabling PHP execution in public year/month HTML folders. No SQL or APK update is needed. The instructions below continue to describe the original root-level receiver, which also remains supported.

The newsletter server remains the trusted controller. Web and mobile use the same newsletter database and PHP renderer. The backend sends approved HTML over HTTPS to a separate receiver on the main website. No Flutter/JavaScript client receives the publishing token, and the newsletter server never writes to the other hosting filesystem directly. No AI or Zoho service is used.

## Deploy the main website receiver

1. Extract **TTW_Main_Website_Upload_API.zip into the MAIN WEBSITE public_html/**. It creates `newsletter-api/upload.php`, config.php, service.php and a configuration example. This package belongs on `www.travelandtourworld.com`, not on the newsletter subdomain.
2. Copy `newsletter-api/config.local.php.example` to `newsletter-api/config.local.php` on that server. Generate a random secret with at least 32 characters (recommended PHP: `bin2hex(random_bytes(32))`) and set `NEWSLETTER_UPLOAD_TOKEN` to it. Never commit or share this secret.
3. The default `NEWSLETTER_HTML_ROOT` is the main website's `public_html/newsletter` directory. Keep `NEWSLETTER_PUBLIC_BASE=https://www.travelandtourworld.com/newsletter`. The PHP account needs write access to that directory. Protect config.php, config.local.php and service.php from direct web access. Included .htaccess rules work on Apache; add equivalent deny rules in Nginx. Disable PHP execution in newsletter/ and disable directory listing.
4. Forward `X-Newsletter-Token` to PHP. Enforce HTTPS; when TLS terminates at a trusted proxy, set PHP's HTTPS flag through server configuration. The receiver does not trust arbitrary forwarded-proto headers.
5. Test a POST without a token: it must return HTTP 401. GET returns 405. Never test by publishing a real existing issue with replace enabled.

## Configure the newsletter application

After taking a database backup, apply **migrations/003_full_manager.sql** to the existing shared newsletter database, then extract the full web ZIP inside **public_html/New/**. Preserve config.local.php, uploads and storage. Add these constants inside the existing server-only New/config.local.php:

```php
define('TTW_PUBLISH_API_URL', 'https://www.travelandtourworld.com/newsletter-api/upload.php');
define('TTW_PUBLISH_TOKEN', 'THE_SAME_PRIVATE_RANDOM_TOKEN_AS_THE_MAIN_WEBSITE');
```

Do not duplicate a constant that already exists. The existing APP_BASE_URL stays `https://newsletter.travelandtourworld.com/New`. Existing shared image storage and cron remain unchanged. For proxy/hosting timeouts, allow at least 75 seconds for an explicit publish request; image probes and uploads have bounded timeouts.

The production receiver URL is restricted to the exact main-domain HTTPS upload endpoint. Loopback HTTP is allowed only in the explicitly enabled local test environment. Do not enable `TTW_ALLOW_LOCAL_HTTP` in production.

## User workflow

Create/import → review and save draft → manually enter Email Title → approve → Publish HTML. Header and Email Title are required before approval. Initial publishing requires both existing edit and download permissions. Only admins can explicitly replace an existing published file.

Web: open a newsletter preview. Download HTML remains available. Approve Newsletter and Publish HTML appear beside it. After publication, View HTML and Copy URL use the recorded canonical main-domain URL.

Mobile: open Newsletter Details. Preview and approve the newsletter, then Publish HTML. Details also provides Download HTML, Copy HTML, View Published HTML and Copy URL. Download opens the native file-save dialog. Network and publishing errors are displayed for retry.

The filename is generated from the newsletter date and edition, for example `TTW_newsletter_10Nov2026M.html`. The receiver validates the exact name, matching year/month, approval flag, extension, MIME, size (2 MB), HTML structure, active content and destination containment. It refuses arbitrary filenames, traversal and symlink destinations. Writes are locked and atomic. Identical retries are idempotent; different content at an existing filename returns FILE_EXISTS. Replacement requires an explicit admin action in the application.

Before publishing, the backend requires absolute HTTPS image URLs, restricts hosts, probes public images with DNS/private-address protections, and uses the existing server-side image fallback. This keeps root/New historical images compatible and does not copy them to the main website. All image URLs must remain publicly accessible on their actual hosting domain.

Publication metadata records status, filename, URL, SHA-256, time and user in an additive table. Reopening/editing marks the current version stale; the previously published file stays online until explicitly replaced. Deleting a database newsletter does not delete published HTML or images. A failed republish preserves the prior publication record. Review the error and retry safely.

## Deployment acceptance

Local tests cover valid uploads, authentication, unsafe names, active content, oversize/missing files, duplicate handling, SHA-256 response verification, role permissions and backend-to-receiver publishing. These tests use isolated local hosts and folders. Live main-domain publication and real mail delivery must be verified after deploying/configuring the servers. The provided code does not deploy itself to either host.
