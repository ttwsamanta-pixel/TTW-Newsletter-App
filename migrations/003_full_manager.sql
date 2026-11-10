-- Back up first. Additive migration for the full mobile manager and publishing.
-- Does not alter existing newsletter/image/user rows. Safe to rerun.
SET @ttw_email_exists=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email');
SET @ttw_email_sql=IF(@ttw_email_exists=0,'ALTER TABLE users ADD COLUMN email VARCHAR(254) NULL','SELECT 1');
PREPARE ttw_email_stmt FROM @ttw_email_sql;
EXECUTE ttw_email_stmt;
DEALLOCATE PREPARE ttw_email_stmt;
CREATE TABLE IF NOT EXISTS newsletter_publications (
 newsletter_id BIGINT UNSIGNED PRIMARY KEY,
 status VARCHAR(24) NOT NULL DEFAULT 'unpublished',
 file_name VARCHAR(100) NULL, public_url VARCHAR(2048) NULL,
 html_sha256 CHAR(64) NULL, published_at DATETIME NULL,
 published_by BIGINT UNSIGNED NULL, error_message VARCHAR(250) NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newsletter_notification_reads (
 user_id BIGINT UNSIGNED NOT NULL, event_key VARCHAR(100) NOT NULL,
 read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newsletter_password_resets (
 token_hash CHAR(64) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 expires_at DATETIME NOT NULL, used_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
