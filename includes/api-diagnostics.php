<?php
declare(strict_types=1);

/** Read-only deployment checks. Expose results to authenticated administrators only. */
function automation_setup_check(PDO $pdo): array
{
    $required = [
        'newsletters' => ['approved_at', 'approved_by'],
        'users' => ['email'],
        'newsletter_publications' => ['status','public_url','html_sha256','published_at'],
        'newsletter_notification_reads' => ['user_id','event_key'],
        'newsletter_password_resets' => ['token_hash','user_id','expires_at','used_at'],
        'newsletter_categories_master' => ['category_name', 'category_slug', 'aliases', 'active', 'display_order'],
        'api_tokens' => ['user_id', 'token_hash', 'password_fingerprint', 'expires_at', 'revoked_at'],
        'api_rate_limits' => ['bucket', 'window_start', 'hits'],
        'automation_jobs' => ['user_id', 'status', 'payload', 'total', 'completed', 'newsletter_id', 'lease_until', 'lease_token'],
        'automation_articles' => ['job_id', 'position', 'section', 'category_id', 'url', 'status', 'result', 'error_message', 'attempts', 'started_at', 'completed_at'],
    ];
    $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . implode(',', array_fill(0, count($required), '?')) . ')');
    $stmt->execute(array_keys($required));
    $found = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $found[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }
    $missingTables = $missingColumns = [];
    foreach ($required as $table => $columns) {
        if (!isset($found[$table])) {
            $missingTables[] = $table;
            continue;
        }
        foreach (array_diff($columns, $found[$table]) as $column) {
            $missingColumns[] = $table . '.' . $column;
        }
    }
    $missingExtensions = [];
    foreach (['pdo_mysql', 'curl', 'dom', 'mbstring', 'fileinfo', 'gd'] as $extension) {
        if (!extension_loaded($extension)) {
            $missingExtensions[] = $extension;
        }
    }
    return [
        'ready' => !$missingTables && !$missingColumns && !$missingExtensions,
        'missing_tables' => $missingTables,
        'missing_columns' => $missingColumns,
        'missing_php_extensions' => $missingExtensions,
        'instruction' => $missingTables || $missingColumns
            ? 'The database upgrade is incomplete. Back up the database, review DATABASE_MIGRATION.md and apply missing automation changes and migrations/003_full_manager.sql. Do not rerun changes that already exist.'
            : ($missingExtensions ? 'Enable the listed PHP extensions in the hosting panel.' : 'Required automation tables, columns and PHP extensions are present. This read-only check does not test worker availability, uploads or every database index.'),
    ];
}

/** Translate known database setup failures without exposing SQL, names or credentials. */
function api_database_failure(PDOException $error): array
{
    $number = (int) ($error->errorInfo[1] ?? 0);
    if (in_array($number, [1146, 1054], true)) {
        return [503, 'Automation database setup is incomplete. Ask an administrator to open api/index.php?route=/system/check and apply the missing migration changes.'];
    }
    if (in_array($number, [1044, 1045, 1049, 2002, 2003], true)) {
        return [503, 'The API could not access its database. Check the server database configuration and database-user permissions.'];
    }
    return [500, 'Request failed. Check server configuration or contact the administrator.'];
}
