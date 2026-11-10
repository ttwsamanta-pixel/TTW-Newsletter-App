# Database migration

## Full manager 0.3.0

For the full manager 0.3.0, back up the live database and import migrations/003_full_manager.sql before deploying the new API. It adds newsletter_publications, newsletter_notification_reads, newsletter_password_resets and a nullable users.email field if absent. Existing users, newsletters and image paths are preserved. It is safe to rerun. Older initial migration instructions below apply to earlier installations.


## Completing the reported missing automation setup

If `/system/check` lists all five new automation tables plus `newsletters.approved_at` and `newsletters.approved_by` as missing, back up the existing database, select it in phpMyAdmin, and import **`migrations/002_complete_automation_setup.sql`**. This script can also complete a migration that stopped at an existing unique index. Do not import fixture-schema.sql or create a replacement database.

The completion script adds missing approval columns, creates absent automation tables, seeds missing category slugs without overwriting existing settings, and retains equivalent date/edition unique indexes regardless of name/order. Repeated imports preserve existing rows and approvals. Existing new tables with incompatible column layouts still need inspection; `CREATE TABLE IF NOT EXISTS` does not alter their columns.

If duplicate date/edition rows already exist, the script reports `migration_warning` and leaves them intact rather than forcing a unique index or deleting records. Review those rows and resolve them deliberately before enforcing uniqueness. Reopen the admin setup-check URL after importing; required tables/columns/extensions should report `ready: true`. The checker does not currently validate all indexes or worker availability.

## Original one-time migration

No production newsletter schema was supplied. Tables/columns were traced from the existing PHP queries; integration uses a test-only reconstructed schema. **Verify the actual database before applying the additive migration.** Never import `invoice/schema.sql` or `tests/fixture-schema.sql` into the newsletter production database.

1. Export a database backup and record the deployed PHP version.
2. Inspect `SHOW CREATE TABLE` for users, newsletters, pick_news, more_travel_news, newsletter_categories and category_news. Existing tables must use InnoDB for transactional saves. Ensure promotion_width/promotion_height exist (`ensure_promotion_columns()` in the original editor handles older installations).
3. Check `SHOW INDEX FROM newsletters` for an existing unique index on `(newsletter_date, edition)`. If an equivalent index already exists, omit the migration's `CREATE UNIQUE INDEX` statement. Do not drop existing indexes.
4. Run the duplicate query at the start of `migrations/001_automation.sql`. If rows are returned, resolve them deliberately before proceeding; no migration code deletes duplicates.
5. Check `SHOW COLUMNS FROM newsletters` for approved_at/approved_by. Apply only missing additions if a partial migration was previously run.
6. Apply `migrations/001_automation.sql` to the existing newsletter database. This is a one-time migration, not an every-request schema updater.

New tables: newsletter_categories_master, api_tokens, api_rate_limits, automation_jobs and automation_articles. They store categories, token hashes, throttling buckets, jobs and per-article operation results/timestamps/attempt counts. Existing user and newsletter IDs are referenced logically because their exact production column types were unavailable; no conflicting foreign-key types are invented.

New newsletter fields: approved_at and approved_by. Historical rows have NULL and are shown as drafts; no historical approval state is inferred. Editing through the shared service clears approval. Admin and employees with edit permission can approve; existing permissions remain configurable in Employees.

The new unique index prevents concurrent date/edition duplication. Automation returns HTTP 409 with existing_id and supports explicit replacement only when the issue is unapproved and the user has edit permission. Generated job requests are idempotent: calling generate again returns its existing newsletter ID.

Rollback: stop the worker and restore the previous application files. Existing core tables remain intact. New tables and approval fields may remain while rollback is assessed. Restore the backup if data rollback is required; do not automatically delete generated newsletters.
