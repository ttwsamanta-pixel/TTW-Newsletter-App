# TTW API additions for the full manager

All routes use the existing query controller: `https://newsletter.travelandtourworld.com/New/api/index.php?route=/...`. Existing routes remain. Send Bearer authorization; browser session mutations require X-CSRF-Token. Permissions are checked against the active database user on every request, not client-supplied roles. Errors are JSON without credentials or server paths. See API_DOCUMENTATION.md for the original routes.

| Route | Method | Access / purpose |
|---|---|---|
| /auth/login | POST | Existing username or registered email; secure token |
| /auth/forgot-password | POST | Rate limited; identifier; generic response, server email required |
| /auth/password | POST | Current password + new password; revokes tokens |
| /dashboard | GET | view; totals, today's issues, recent records |
| /newsletters | GET | view; q/from/to/edition/status/page; 30 rows/page |
| /newsletters/{id} | GET | view; collections and publication metadata |
| /newsletters/{id}/section | POST | edit; scoped manual edits/reorder/remove |
| /newsletters/{id}/reopen | POST | edit; explicit draft reopening |
| /newsletters/{id}/publish-html | POST | edit + download, approved; replace requires admin |
| /newsletters/{id}/download-html | GET | download; existing renderer HTML attachment |
| /newsletters/{id}/delete | POST | delete; relational rows removed, public HTML retained |
| /reports | GET | view; month=YYYY-MM, optional employee ID; totals/days/employees/details |
| /employees | GET / POST | admin; safe user fields / create or update employee |
| /categories/{id}/delete | POST | admin; referenced categories must be deactivated |
| /notifications | GET | Current user's in-app event/read feed |
| /notifications/read | POST | Optional key, or all current events |
| /automation/status | GET | create; actual recent jobs and observed heartbeat |
| /automation/run | POST | admin + create, rate limited; one existing worker tick |
| /profile | GET / POST | Own safe profile; email changes require current password |
| /profile/photo | POST | Authenticated multipart photo; validated shared upload |
| /system/status | GET | Authenticated; readiness flags without secret values |
| /system/check | GET | Admin; additive schema/extension diagnostics |

Newsletter filters accept edition M/E and status draft/approved/published. Rejected is not fabricated because the existing model has no rejection state. Generated jobs retain existing statuses. No duplicate newsletter or user tables are introduced.

Manual section mutations accept basic (subject_title/hidden_text), header (header_title/header_description), pick/more (ordered items with existing id/title/description) or category (category_id plus ordered existing items). Unknown/foreign/repeated article IDs are rejected. Unmentioned sections remain intact. New URLs continue through the existing scoped article import API and SSRF validation.

Publication success returns publication metadata containing public_url, status, file_name, html_sha256 and published_at. Different existing HTML returns HTTP 409 with code FILE_EXISTS; clients must not silently send replace=true. The backend verifies the receiver filename, canonical URL and SHA-256 before recording success. See HTML_PUBLISHING.md for the receiver's multipart contract and security.

Promotion multipart uploads accept newsletter_date=YYYY-MM-DD so working banners follow the selected issue month. Older clients that omit it retain the existing current-date default. New mobile creation screens pass the selected date.
