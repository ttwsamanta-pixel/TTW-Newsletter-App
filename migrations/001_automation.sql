-- Back up first. Run once after checking DATABASE_MIGRATION.md.
-- Stop and resolve duplicates before creating this index.
SELECT newsletter_date, edition, COUNT(*) FROM newsletters GROUP BY newsletter_date, edition HAVING COUNT(*) > 1;
CREATE UNIQUE INDEX uq_newsletter_issue ON newsletters(newsletter_date, edition);
ALTER TABLE newsletters ADD COLUMN approved_at DATETIME NULL, ADD COLUMN approved_by BIGINT UNSIGNED NULL;

CREATE TABLE newsletter_categories_master (
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
 ('CRUISE NEWS','cruise-news','Cruises',6);

CREATE TABLE api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE, password_fingerprint CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL, revoked_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE api_rate_limits (
 bucket CHAR(64) PRIMARY KEY, window_start BIGINT NOT NULL, hits INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE automation_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'pending', payload LONGTEXT NOT NULL,
 total INT NOT NULL, completed INT NOT NULL DEFAULT 0,
 newsletter_id BIGINT UNSIGNED NULL, error_message VARCHAR(500) NULL,
 lease_until DATETIME NULL, lease_token CHAR(32) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status,lease_until), INDEX(user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE automation_articles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_id BIGINT UNSIGNED NOT NULL,
 position INT NOT NULL, section VARCHAR(20) NOT NULL, category_id BIGINT UNSIGNED NULL,
 url VARCHAR(2048) NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'pending',
 result LONGTEXT NULL, error_message VARCHAR(500) NULL, attempts INT NOT NULL DEFAULT 0,
 started_at DATETIME NULL, completed_at DATETIME NULL,
 INDEX(job_id,status), UNIQUE KEY uq_job_position(job_id,position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
