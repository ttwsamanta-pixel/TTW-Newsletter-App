# Full manager implementation plan

The existing users/permissions, newsletters, pick_news, more_travel_news, newsletter_categories, category_news and category master remain the source of truth. Reuse auth.php, newsletter-service.php, automation jobs/cron, shared uploads and newsletter_renderer.php. Existing API routes remain compatible.

Extend APIs for dashboard counts, library filters, details/download/delete, monthly/calendar/employee reports, employee administration, profile/password, notifications/read state, worker observation and publishing. Use existing view/create/edit/delete/download permissions; employee/category management and republishing require admin. First publication requires edit and download plus approval.

Add only publication metadata, notification read receipts and hashed expiring password reset tokens. No new newsletter engine or user database. Deploy an independent token-protected HTTPS receiver on the main domain. Backend publishes the existing renderer HTML; Flutter never receives its secret. Preserve downloads and cron.

Flutter keeps the existing creation/job flow and secure auth. Add the reference blue/navy card theme, five-slot navigation, manager screens and details/publication actions. Section tabs remain freely navigable. Add manual section editing/reordering without refetching unrelated sections.

Validate locally using isolated SQL fixtures and a separate upload receiver; do not use production credentials/data for tests. Deliver separate web/main-domain packages, migration SQL, APK and iOS source. Live main-domain acceptance depends on deploying/configuring both servers. No AI services or Zoho integrations.
