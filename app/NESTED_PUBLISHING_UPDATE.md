# Nested main-site receiver update

The upload API may now live at **public_html/newsletter/newsletter-api/**. The original public_html/newsletter-api location remains supported. Final HTML still goes into **public_html/newsletter/YYYY/Mon/**. No database or mobile APK update is required.

1. Extract **TTW_Nested_Publishing_App_Update.zip**. Upload its **includes/publishing.php** to the newsletter server at **public_html/New/includes/publishing.php**, overwriting that file.
2. Extract **TTW_Main_Website_Upload_API_Nested.zip**. Upload its **newsletter-api/** folder inside the MAIN WEBSITE **public_html/newsletter/** folder. Keep an existing secret config.local.php; the ZIP contains no real configuration or token. Include the .htaccess protection file (Nginx needs equivalent private-file restrictions).
3. In the newsletter server's existing **New/config.local.php**, change the URL to:

```php
define('TTW_PUBLISH_API_URL', 'https://www.travelandtourworld.com/newsletter/newsletter-api/upload.php');
```

Keep the existing generated TTW_PUBLISH_TOKEN unchanged. Edit an existing constant instead of defining it twice.

4. On the MAIN WEBSITE, open **newsletter/newsletter-api/config.local.php**. If it already contains the previous `NEWSLETTER_HTML_ROOT` line, replace that line with:

```php
define('NEWSLETTER_HTML_ROOT', dirname(__DIR__));
```

Alternatively remove that constant and let the updated config.php detect the correct directory. Do not leave the old `dirname(__DIR__).'/newsletter'` value in the nested location: it would create newsletter/newsletter. Keep NEWSLETTER_UPLOAD_TOKEN equal to the generated token on the newsletter server and leave NEWSLETTER_PUBLIC_BASE as https://www.travelandtourworld.com/newsletter. Never overwrite real tokens with example placeholders.

For a first installation, copy config.local.php.example to config.local.php and enter the shared token; the new example lets the HTML root be detected automatically.

5. Approve a newsletter, then use Publish HTML. The returned public URL remains https://www.travelandtourworld.com/newsletter/YYYY/Mon/TTW_newsletter_....html. PHP needs write access to the existing newsletter folder. Do not change wp-config.php, move existing HTML files or reimport SQL.

The nested API folder is an exception to any rule disabling PHP execution inside newsletter/. Permit PHP only in newsletter/newsletter-api/ while keeping PHP execution disabled in the public year/month HTML folders. Protect receiver config/service files from direct requests; only upload.php should be the public PHP endpoint.

Apply this app patch after an older full web ZIP if you reinstall the 0.3.0 web package. This is a server-side change; the installed mobile app uses the backend's configured destination.
