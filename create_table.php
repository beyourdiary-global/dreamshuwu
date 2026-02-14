<?php
// 1. Database Credentials
$dbhost     = '127.0.0.1';
$dbport     = 3306;
$dbUser     = 'beyourdi_cms';
$dbpwd      = 'Byd1234@Global';
$dbname     = 'beyourdi_dreamshuwu';

// 2. Connect to MySQL Server (Create DB if not exists)
$conn = new mysqli($dbhost, $dbUser, $dbpwd, "", $dbport);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database '<strong>$dbname</strong>' checked/created.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select Database
$conn->select_db($dbname);
echo "<hr>";

// 3. Define Table SQL (Order matters for Foreign Keys!)

$tables = [];

// 1. Users
$tables['users'] = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'User full name',
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'User email, must be unique',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    gender ENUM('M','F','O') DEFAULT NULL COMMENT 'Gender: M=Male, F=Female, O=Other',
    birthday DATE DEFAULT NULL COMMENT 'User birthday',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Registration timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 2. Password Resets
$tables['password_resets'] = "
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL COMMENT 'User email address',
    token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique secure reset token',
    expires_at TIMESTAMP NOT NULL COMMENT 'Token expiration timestamp',
    used_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when token was used',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Request creation timestamp',
    INDEX idx_email (email) COMMENT 'Index for fast email lookups'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 3. Audit Log
$tables['audit_log'] = "
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary key',
    page VARCHAR(255) NOT NULL COMMENT 'The module or page where the action occurred',
    action VARCHAR(50) NOT NULL COMMENT 'Action type string',
    action_message VARCHAR(255) DEFAULT NULL COMMENT 'Description of the action',
    query TEXT DEFAULT NULL COMMENT 'Executed SQL or ORM query',
    query_table VARCHAR(255) DEFAULT NULL COMMENT 'Table affected by the action',
    old_value JSON DEFAULT NULL COMMENT 'Value before the action (if applicable)',
    new_value JSON DEFAULT NULL COMMENT 'Value after the action (if applicable)',
    changes JSON DEFAULT NULL COMMENT 'Difference between old_value and new_value',
    user_id BIGINT NOT NULL COMMENT 'ID of the user performing the action',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
    created_by BIGINT DEFAULT NULL COMMENT 'User who created the audit record',
    updated_by BIGINT DEFAULT NULL COMMENT 'User who last updated the audit record',
    INDEX idx_user_page_action (user_id, page, action) COMMENT 'Index for filtering',
    INDEX idx_created_at (created_at) COMMENT 'Index for sorting by date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 4. Users Dashboard (Links to Users)
$tables['users_dashboard'] = "
CREATE TABLE IF NOT EXISTS users_dashboard (
    user_id INT NOT NULL PRIMARY KEY COMMENT 'Linked to users.id',
    avatar VARCHAR(255) DEFAULT NULL COMMENT 'Path to user avatar image',
    level INT DEFAULT 1 COMMENT 'User current level',
    following_count INT DEFAULT 0 COMMENT 'Number of users following',
    followers_count INT DEFAULT 0 COMMENT 'Number of followers',
    bio VARCHAR(500) DEFAULT NULL COMMENT 'User biography or introduction',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    CONSTRAINT fk_users_dashboard FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 5. Novel Tags
$tables['novel_tag'] = "
CREATE TABLE IF NOT EXISTS novel_tag (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Tag Name e.g. Modern, Romance',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT,
    updated_by BIGINT,
    INDEX (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['novel_category'] = "
CREATE TABLE IF NOT EXISTS novel_category (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Category name, e.g., Modern Romance',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last updated timestamp',
    created_by BIGINT NOT NULL COMMENT 'User ID who created the category',
    updated_by BIGINT NOT NULL COMMENT 'User ID who last updated the category',
    INDEX idx_cat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 7. Category <-> Tag (Many-to-Many)
$tables['category_tag'] = "
CREATE TABLE IF NOT EXISTS category_tag (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT NOT NULL,
    tag_id BIGINT NOT NULL, 
    CONSTRAINT fk_cat_id FOREIGN KEY (category_id) REFERENCES novel_category(id) ON DELETE CASCADE,
    CONSTRAINT fk_tag_id FOREIGN KEY (tag_id) REFERENCES novel_tag(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cat_tag (category_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['meta_settings'] = "
CREATE TABLE IF NOT EXISTS meta_settings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    page_type VARCHAR(50) NOT NULL,
    page_id BIGINT NOT NULL DEFAULT 0,
    meta_title VARCHAR(255) DEFAULT NULL,      -- Browser Tab Title
    meta_description TEXT DEFAULT NULL,        -- Search Engine Description
    og_title VARCHAR(255) DEFAULT NULL,        -- Social Media Title
    og_description TEXT DEFAULT NULL,          -- Social Media Description
    og_url VARCHAR(255) DEFAULT NULL,          -- Social Media Canonical URL
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_meta_page (page_type, page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['meta_settings_page'] = "
CREATE TABLE IF NOT EXISTS meta_settings_page (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(100) NOT NULL COMMENT 'Page identifier, e.g. home, login, register',
    meta_title VARCHAR(255) DEFAULT NULL COMMENT 'Browser Tab Title',
    meta_description TEXT DEFAULT NULL COMMENT 'Search Engine Description',
    og_title VARCHAR(255) DEFAULT NULL COMMENT 'Social Media Title',
    og_description TEXT DEFAULT NULL COMMENT 'Social Media Description',
    og_url VARCHAR(255) DEFAULT NULL COMMENT 'Social Media Canonical URL',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_page_key (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['web_settings'] = "
CREATE TABLE IF NOT EXISTS web_settings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    website_name VARCHAR(255) DEFAULT NULL COMMENT 'Global Website Name',
    website_logo VARCHAR(255) DEFAULT NULL COMMENT 'Path to Website Logo',
    website_favicon VARCHAR(255) DEFAULT NULL COMMENT 'Path to Website Favicon',
    theme_bg_color VARCHAR(50) DEFAULT '#ffffff' COMMENT 'Main Theme Background Color',
    theme_text_color VARCHAR(50) DEFAULT '#333333' COMMENT 'Main Theme Text Color',
    button_color VARCHAR(50) DEFAULT '#233dd2' COMMENT 'Primary Button Color',
    button_text_color VARCHAR(50) DEFAULT '#ffffff' COMMENT 'Primary Button Text Color',
    background_color VARCHAR(50) DEFAULT '#f4f7f6' COMMENT 'Global Page Background Color',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record Creation Time',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last Update Time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['page_action'] = "
CREATE TABLE IF NOT EXISTS page_action (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  name VARCHAR(255) NOT NULL COMMENT 'Action name',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'Active / Deleted (A/D)',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last updated timestamp',
  created_by BIGINT DEFAULT NULL COMMENT 'User ID who created',
  updated_by BIGINT DEFAULT NULL COMMENT 'User ID who updated',
  PRIMARY KEY (id),
  UNIQUE KEY unique_name_active (name, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['page_information_list'] = "
CREATE TABLE IF NOT EXISTS page_information_list (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  name_en VARCHAR(255) NOT NULL COMMENT 'English Name',
  name_cn VARCHAR(255) NOT NULL COMMENT 'Chinese Name',
  description TEXT DEFAULT NULL COMMENT 'Description',
  public_url VARCHAR(255) NOT NULL COMMENT 'Public URL',
  file_path VARCHAR(255) NOT NULL COMMENT 'File System Path',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'Active / Deleted',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  updated_by BIGINT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['action_master'] = "
CREATE TABLE IF NOT EXISTS action_master (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  page_id BIGINT NOT NULL COMMENT 'FK to page_information_list',
  action_id BIGINT NOT NULL COMMENT 'FK to page_action',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_page_action_bind (page_id, action_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";


// 4. Run Queries

foreach ($tables as $name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table '<strong>$name</strong>' checked/created.<br>";
    } else {
        echo "Error table '<strong>$name</strong>': " . $conn->error . "<br>";
    }
}

// 5. Apply Schema Updates (ALTER Statements)
$alterAuditLog = "ALTER TABLE audit_log MODIFY COLUMN action VARCHAR(50) NOT NULL COMMENT 'Action type string'";
if ($conn->query($alterAuditLog) === TRUE) {
    echo "Audit Log table updated successfully.<br>";
} else {
    echo "Error updating Audit Log table: " . $conn->error . "<br>";
}

$conn->close();
echo "<hr><strong>Environment Build Complete!</strong>";
?>