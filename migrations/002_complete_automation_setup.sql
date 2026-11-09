-- Complete missing automation setup in the SELECTED existing newsletter database.
-- Back up first. No existing newsletters, users, images or category settings are deleted.
-- Safe to repeat for this schema: existing tables, approval columns and issue indexes are retained.
-- Existing new tables with a different structure require inspection; CREATE IF NOT EXISTS does not repair them.

SET @ttw_has_approved_at = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletters' AND COLUMN_NAME='approved_at');
SET @ttw_sql = IF(@ttw_has_approved_at=0, 'ALTER TABLE newsletters ADD COLUMN approved_at DATETIME NULL', 'SELECT ''approved_at already exists'' AS migration_info');
PREPARE ttw_repair FROM @ttw_sql;
EXECUTE ttw_repair;
DEALLOCATE PREPARE ttw_repair;

SET @ttw_has_approved_by = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletters' AND COLUMN_NAME='approved_by');
SET @ttw_sql = IF(@ttw_has_approved_by=0, 'ALTER TABLE newsletters ADD COLUMN approved_by BIGINT UNSIGNED NULL', 'SELECT ''approved_by already exists'' AS migration_info');
PREPARE ttw_repair FROM @ttw_sql;
EXECUTE ttw_repair;
DEALLOCATE PREPARE ttw_repair;

CREATE TABLE IF NOT EXISTS newsletter_categories_master (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_name VARCHAR(190) NOT NULL,
 category_slug VARCHAR(190) NOT NULL UNIQUE,
 aliases TEXT NOT NULL,
 active TINYINT NOT NULL DEFAULT 1,
 display_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO newsletter_categories_master(category_name,category_slug,aliases,display_order) VALUES
 ('MEETING AND EVENT INDUSTRY NEWS','meeting-and-event-industry-news','MICE,Meeting and Events',1),
 ('TOURISM NEWS','tourism-news','Tourism',2), ('AIRLINE NEWS','airline-news','Airlines',3),
 ('HOTEL NEWS','hotel-news','Hotels',4), ('DESTINATION NEWS','destination-news','Destinations',5),
 ('CRUISE NEWS','cruise-news','Cruises',6)
ON DUPLICATE KEY UPDATE category_slug=VALUES(category_slug);

CREATE TABLE IF NOT EXISTS api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE, password_fingerprint CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL, revoked_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_rate_limits (
 bucket CHAR(64) PRIMARY KEY, window_start BIGINT NOT NULL, hits INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS automation_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'pending', payload LONGTEXT NOT NULL,
 total INT NOT NULL, completed INT NOT NULL DEFAULT 0,
 newsletter_id BIGINT UNSIGNED NULL, error_message VARCHAR(500) NULL,
 lease_until DATETIME NULL, lease_token CHAR(32) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status,lease_until), INDEX(user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS automation_articles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_id BIGINT UNSIGNED NOT NULL,
 position INT NOT NULL, section VARCHAR(20) NOT NULL, category_id BIGINT UNSIGNED NULL,
 url VARCHAR(2048) NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'pending',
 result LONGTEXT NULL, error_message VARCHAR(500) NULL, attempts INT NOT NULL DEFAULT 0,
 started_at DATETIME NULL, completed_at DATETIME NULL,
 INDEX(job_id,status), UNIQUE KEY uq_job_position(job_id,position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Check equivalent unique indexes by their columns rather than assuming an index name.
SET @ttw_has_issue_index = (
 SELECT COUNT(*) FROM (
  SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletters' AND NON_UNIQUE=0
  GROUP BY INDEX_NAME
  HAVING COUNT(*)=2 AND GROUP_CONCAT(COLUMN_NAME ORDER BY COLUMN_NAME SEPARATOR ',')='edition,newsletter_date'
 ) AS matching_issue_indexes
);
SET @ttw_duplicate_issues = (
 SELECT COUNT(*) FROM (
  SELECT newsletter_date,edition FROM newsletters GROUP BY newsletter_date,edition HAVING COUNT(*)>1
 ) AS duplicate_issues
);
SET @ttw_index_name_used = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletters' AND INDEX_NAME='uq_ttw_issue_date_edition');
SET @ttw_sql = CASE
 WHEN @ttw_has_issue_index>0 THEN 'SELECT ''Existing date/edition unique index retained'' AS migration_info'
 WHEN @ttw_duplicate_issues>0 THEN 'SELECT ''ACTION REQUIRED: duplicate date/edition issues exist. Review them before adding a unique index. No records were deleted.'' AS migration_warning'
 WHEN @ttw_index_name_used>0 THEN 'SELECT ''ACTION REQUIRED: inspect the existing uq_ttw_issue_date_edition index before adding date/edition uniqueness.'' AS migration_warning'
 ELSE 'ALTER TABLE newsletters ADD UNIQUE INDEX uq_ttw_issue_date_edition (newsletter_date,edition)'
END;
PREPARE ttw_repair FROM @ttw_sql;
EXECUTE ttw_repair;
DEALLOCATE PREPARE ttw_repair;

-- Review any duplicate rows returned below, then reopen the admin setup-check URL.
SELECT newsletter_date,edition,COUNT(*) AS issue_count FROM newsletters GROUP BY newsletter_date,edition HAVING COUNT(*)>1;
