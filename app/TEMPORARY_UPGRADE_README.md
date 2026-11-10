# Temporary database upgrade page

1. In FileZilla, upload **upgrade-full-manager.php only** into the existing `/public_html/New/` folder, beside auth.php and config.php. Do not upload it into the root application's folder. No SQL file or new configuration file needs uploading for this method.
2. Sign in through the existing website login as an **admin**. This uses the existing users table and works before the new email column/manager tables are added. You do not need the mobile API to log in.
3. Open **https://newsletter.travelandtourworld.com/New/upgrade-full-manager.php**.
4. Review the missing changes. Click **Download Database Tables Backup**. Keep the downloaded SQL private on your computer. Confirm its final line says `TTW BACKUP COMPLETED`. This exports table definitions and rows, not uploaded image files, views, triggers or routines.
5. Refresh the upgrade page using its link. Check the backup confirmation, then click **Run Database Upgrade**. It adds only the missing users.email column and the three tables from migration 003. Existing users/newsletters/images are preserved. It does not apply old initial migrations.
6. When it says **Already up to date / Database upgrade completed**, delete **`/New/upgrade-full-manager.php`** using FileZilla. Further changes are already disabled once the required schema is present. Keep the downloaded backup.
7. Continue with the full v0.3.0 web ZIP inside /New, then the separate main-site API setup and the new Android APK. Do not import the same SQL separately if this page already completed it.

The page is self-contained apart from the existing auth.php/config.php. It uses HTTPS, the existing active admin session, CSRF checks, a fixed allowlist of additive schema operations and a database upgrade lock. It accepts no arbitrary SQL or user-supplied database credentials. The public page cannot be used by employees or signed-out visitors.

If the database user lacks CREATE/ALTER permissions, the hosting administrator must grant them or apply the migration. PHP upload access cannot override MySQL permissions. Unexpected partial existing manager tables are reported for review rather than rebuilt. Do not delete existing tables to resolve an error.
