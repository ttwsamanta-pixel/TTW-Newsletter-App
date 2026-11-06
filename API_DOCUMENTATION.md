# TTW API

Base URL: `https://your-host/api/index.php`. Use `?route=/auth/login` etc. Apache also supports `/api/auth/login` when `api/.htaccess` is enabled. The Flutter client uses query routing. Send JSON with `Content-Type: application/json`; uploads use multipart/form-data. API responses are JSON except HTML preview.

## Authentication and security

`POST /auth/login`: `{ "username": "existing-user", "password": "..." }` returns `{token,expires_in:604800,user:{id,name,role,permissions}}`. Login reuses existing active users/password hashes; username is the available identity field, not an invented email field. Send `Authorization: Bearer <token>` on subsequent calls. Only SHA-256 token hashes are stored; tokens expire in seven days and password changes invalidate them.

`GET /auth/me` returns the current public profile. `POST /auth/logout` revokes the current token. Existing browser sessions may also call the API; all session-authenticated mutations require `X-CSRF-Token` from `csrf_token()`. Tokens are never accepted in URLs. HTTPS is mandatory. Local loopback HTTP requires explicitly setting `TTW_ALLOW_LOCAL_HTTP=1`; this is a server-test exception only, not supported by the mobile client.

Limits: 10 login attempts/IP/minute, 120 authenticated requests/user/minute, five job submissions/user/minute; 429 includes Retry-After. JSON is limited to 128 KB, pasted input to 64 KB, jobs to 50 unique articles. Remote HTML is limited to 3 MB, images to 8 MB/20 megapixels. Article hosts are www.travelandtourworld.com and travelandtourworld.com only. Each redirect is revalidated and DNS is pinned to a public IPv4 address. Configure additional exact image-only hosts through `TTW_IMAGE_HOSTS` if required.

## Endpoints

| Method | Route | Permission / behavior |
|---|---|---|
| GET | /categories | view; active categories for employees, all for admin |
| POST | /categories | admin; create/update by optional id; category_name, category_slug, aliases (comma-separated), active, display_order |
| GET | /newsletters | view; page query, 30 rows/page; includes today's editions and server_date |
| GET | /newsletters/{id} | view; original newsletter and collections |
| POST | /newsletters/{id} | edit; update subject_title, hidden_text, header_title, header_description and optional pick_news [{id,title,description}]; preserves URLs/images/link sections; clears approval |
| GET | /newsletters/{id}/preview | view; authenticated HTML from original renderer, no script execution allowed by CSP |
| POST | /newsletters/{id}/approve | edit; explicit approval; no sending |
| POST | /automation/parse | create; {text}; deterministic parsing, errors/warnings |
| POST | /automation/jobs | create; enqueue and return {id} with HTTP 202 |
| GET | /automation/jobs | create; user's most recent 20 jobs |
| GET | /automation/jobs/{id} | create plus job owner/admin; progress and per-article results |
| POST | /automation/jobs/{id}/retry | create plus owner/admin; retry warning/failed articles before generation |
| POST | /automation/jobs/{id}/generate | create plus owner/admin; {accept_warnings:false,replace_draft:false}; returns newsletter_id |
| POST | /uploads/promotion | create; multipart banner, JPEG/PNG/WebP; returns path,url,signature |

For page 2 use `api/index.php?route=/newsletters&page=2`.

## Parse

Input headings: header, Pick News, More News, Categories, and dynamic category names/aliases. Names are case-insensitive with collapsed whitespace. Colons, full-width colons, heading markers and blank lines are tolerated. Multiple URLs on a line work. URLs are normalized to HTTPS www host, trailing slash, without tracking query/fragment before duplicate checking. Duplicates create warnings; invalid links, unknown categories, multiple/missing headers or more than 50 URLs create errors. New category names must be added by an admin; matching is never guessed.

Response: `{articles:[{section,category_id,category_name,url}],total,warnings,errors,status}`. Parse validates structure only. Metadata/status is returned by the asynchronous job after article processing. Clients may remove/reorder/replace links, rebuild the structured input, and reparse before submission.

## Job submission

```json
{
  "newsletter_date": "2026-11-06",
  "edition": "M",
  "text": "header:\nhttps://www.travelandtourworld.com/news/article/example/",
  "subject_title": "Manually entered subject",
  "hidden_text": "Manually entered email description",
  "promotion_url": "https://www.travelandtourworld.com/ttwapp/",
  "promotion_text": "Optional banner title"
}
```

If uploading a promotion, also pass `promotion_image` = returned path and `promotion_signature` = returned signature. Signatures bind upload paths to users. No banner means no manual promotion; the renderer's existing standard footer/banner remains.

Poll at 2–3 second intervals. Statuses: pending, processing, completed, completed_with_warnings, failed. A processing job may briefly return pending between individual article operations. Response includes `{id,status,progress,total,completed,newsletter_id,articles,payload}`. Each article includes section/category, URL, status, original metadata, local image path when needed, warning/error, attempts and timestamps. More News/categories are link-only because that is the existing renderer/data format.

Missing original titles are critical and block generation; retry or create a corrected job. Missing image/description is a warning. Header/Pick image failures use an actual local placeholder; the caller must explicitly acknowledge warnings before generating. A separate generate request saves the draft through the shared service. HTTP 409 includes `existing_id`; open it, cancel, or explicitly replace its unapproved draft. Approved issues cannot be replaced by automation.

Errors use `{error:"safe message"}`. Codes: 401 authentication, 403 authorization/CSRF, 404 missing resource, 409 duplicate, 413 size, 422 validation, 426 HTTPS required, 429 rate limit, 500 configuration/server failure. Passwords, tokens and SQL details are not included in API errors.
