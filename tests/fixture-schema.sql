-- Test-only reconstruction from observed PHP queries; NOT a production migration.
CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL,
 password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'employee',
 first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) DEFAULT '',
 permissions TEXT, status VARCHAR(20) DEFAULT 'active', photo VARCHAR(255) NULL,
 contact_no VARCHAR(50), address TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE newsletters (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, newsletter_date DATE NOT NULL, edition CHAR(1) NOT NULL,
 subject_title VARCHAR(255), hidden_text TEXT, header_title TEXT, header_description TEXT, header_url TEXT,header_image TEXT,
 promotion_enabled TINYINT DEFAULT 0, promotion_text TEXT, promotion_url TEXT,promotion_image TEXT,
 promotion_width INT DEFAULT 200,promotion_height INT DEFAULT 170,created_by INT,updated_by INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE pick_news(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,newsletter_id INT UNSIGNED,sort_order INT,title TEXT,description TEXT,url TEXT,image TEXT) ENGINE=InnoDB;
CREATE TABLE more_travel_news(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,newsletter_id INT UNSIGNED,sort_order INT,title TEXT,url TEXT) ENGINE=InnoDB;
CREATE TABLE newsletter_categories(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,newsletter_id INT UNSIGNED,sort_order INT,category_name VARCHAR(255),category_url TEXT) ENGINE=InnoDB;
CREATE TABLE category_news(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category_id INT UNSIGNED,sort_order INT,title TEXT,url TEXT) ENGINE=InnoDB;
