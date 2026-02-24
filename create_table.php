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

$conn->select_db($dbname);

echo "<hr>";

// 3. Define Table SQL (Order matters for Foreign Keys!)

$tables = [];

// 0. User Role (Moved up for Foreign Key dependency)
$tables['user_role'] = "
CREATE TABLE IF NOT EXISTS user_role (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  name_cn VARCHAR(255) NOT NULL COMMENT 'Chinese Name',
  name_en VARCHAR(255) NOT NULL COMMENT 'English Name',
  description VARCHAR(500) DEFAULT NULL COMMENT 'Role Description',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'Active / Deleted (A/D)',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  created_by VARCHAR(100) DEFAULT NULL COMMENT 'User who created',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last updated timestamp',
  updated_by VARCHAR(100) DEFAULT NULL COMMENT 'User who updated',
  PRIMARY KEY (id),
  UNIQUE KEY unique_role_name (name_en, name_cn),
  INDEX idx_role_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 1. Users (Updated with user_role_id)
$tables['users'] = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'User full name',
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'User email, must be unique',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    gender ENUM('M','F','O') DEFAULT NULL COMMENT 'Gender: M=Male, F=Female, O=Other',
    birthday DATE DEFAULT NULL COMMENT 'User birthday',
    user_role_id BIGINT DEFAULT NULL COMMENT 'Foreign Key to user_role',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Registration timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    CONSTRAINT fk_users_role FOREIGN KEY (user_role_id) REFERENCES user_role(id) ON DELETE SET NULL
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
    PRIMARY KEY (id),
    UNIQUE KEY unique_public_url (public_url),
    UNIQUE KEY unique_page_name_en (name_en),
    UNIQUE KEY unique_page_name_cn (name_cn)
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
    UNIQUE KEY unique_page_action_bind (page_id, action_id),
    KEY idx_action_master_action_id (action_id),
    CONSTRAINT fk_action_master_page
        FOREIGN KEY (page_id) REFERENCES page_information_list (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_action_master_action
        FOREIGN KEY (action_id) REFERENCES page_action (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['user_role_permission'] = "
CREATE TABLE IF NOT EXISTS user_role_permission (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  user_role_id BIGINT NOT NULL COMMENT 'FK to user_role',
  page_id BIGINT NOT NULL COMMENT 'FK to page_information_list',
  action_id BIGINT NOT NULL COMMENT 'FK to page_action',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  created_by VARCHAR(100) DEFAULT NULL COMMENT 'User who created',
  PRIMARY KEY (id),
  UNIQUE KEY unique_role_page_action (user_role_id, page_id, action_id),
  KEY idx_role_id (user_role_id),
  KEY idx_page_id (page_id),
  KEY idx_action_id (action_id),
  CONSTRAINT fk_urp_role
    FOREIGN KEY (user_role_id) REFERENCES user_role (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_urp_page
    FOREIGN KEY (page_id) REFERENCES page_information_list (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_urp_action
    FOREIGN KEY (action_id) REFERENCES page_action (id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables["author_profile"] = "
CREATE TABLE IF NOT EXISTS author_profile (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  user_id INT NOT NULL COMMENT 'FK to users table',
  real_name VARCHAR(255) NOT NULL COMMENT 'Required',
  id_number VARCHAR(50) NOT NULL COMMENT 'Required',
  id_photo_front VARCHAR(255) NOT NULL COMMENT 'Required',
  id_photo_back VARCHAR(255) NOT NULL COMMENT 'Required',
  contact_phone VARCHAR(50) NOT NULL COMMENT 'Required',
  contact_email VARCHAR(255) NOT NULL COMMENT 'Required',
  bank_account_name VARCHAR(255) DEFAULT NULL COMMENT 'Optional',
  bank_name VARCHAR(255) DEFAULT NULL COMMENT 'Optional',
  bank_country VARCHAR(100) DEFAULT NULL COMMENT 'Optional',
  bank_swift_code VARCHAR(100) DEFAULT NULL COMMENT 'Optional',
  bank_account_number VARCHAR(100) DEFAULT NULL COMMENT 'Optional',
  pen_name VARCHAR(255) NOT NULL COMMENT 'Unique nickname',
  avatar VARCHAR(255) DEFAULT NULL COMMENT 'Avatar image',
  bio TEXT DEFAULT NULL COMMENT 'Author bio',
  verification_status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending / approved / rejected',
  reject_reason TEXT DEFAULT NULL COMMENT 'Mandatory when status is rejected',
  email_notified_at DATETIME DEFAULT NULL COMMENT 'Last notification timestamp',
  email_notify_count INT DEFAULT 0 COMMENT 'Total number of notifications sent',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Deleted',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated timestamp',
  PRIMARY KEY (id),
  UNIQUE KEY unique_author_user (user_id),
  UNIQUE KEY unique_pen_name (pen_name),
  CONSTRAINT fk_author_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['email_template'] = "
CREATE TABLE IF NOT EXISTS email_template (
  id INT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  template_code VARCHAR(50) NOT NULL COMMENT 'Unique identifier (e.g., AUTHOR_APPROVED)',
  template_name VARCHAR(100) NOT NULL COMMENT 'Descriptive name for admin view',
  subject VARCHAR(255) NOT NULL COMMENT 'Email subject line',
  content TEXT NOT NULL COMMENT 'Email body content with {{variables}}',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Disabled',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated timestamp',
  created_by INT DEFAULT 0 COMMENT 'Admin user ID who created the record',
  updated_by INT DEFAULT 0 COMMENT 'Admin user ID who last updated the record',
  PRIMARY KEY (id),
  UNIQUE KEY unique_template_code (template_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['email_log'] = "
CREATE TABLE IF NOT EXISTS email_log (
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  user_id INT NOT NULL COMMENT 'Recipient user ID',
  template_code VARCHAR(50) NOT NULL COMMENT 'Template used for this email',
  sent_status VARCHAR(20) NOT NULL COMMENT 'success / failed',
  error_message TEXT DEFAULT NULL COMMENT 'Stores SMTP or system errors if failed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Sent timestamp',
  PRIMARY KEY (id),
  KEY idx_email_log_user (user_id),
  KEY idx_email_log_template (template_code)
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

// 5. Alter Table for author_profile (Adds new fields if they don't exist, idempotently)
// Helper to add a column if it does not already exist
$authorProfileColumns = [
    'reject_reason' => "ALTER TABLE author_profile 
        ADD COLUMN reject_reason TEXT DEFAULT NULL COMMENT 'Mandatory when status is rejected' AFTER verification_status",
    'email_notified_at' => "ALTER TABLE author_profile 
        ADD COLUMN email_notified_at DATETIME DEFAULT NULL COMMENT 'Last notification timestamp' AFTER reject_reason",
    'email_notify_count' => "ALTER TABLE author_profile 
        ADD COLUMN email_notify_count INT DEFAULT 0 COMMENT 'Total number of notifications sent' AFTER email_notified_at",
];
foreach ($authorProfileColumns as $columnName => $alterSql) {
    // Check if the column already exists
    $checkSql = "SHOW COLUMNS FROM author_profile LIKE '" . $conn->real_escape_string($columnName) . "'";
    if ($result = $conn->query($checkSql)) {
        if ($result->num_rows > 0) {
            // Column already exists; skip adding it
            echo "Column '<strong>" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "</strong>' in 'author_profile' already exists.<br>";
            $result->free();
            continue;
        }
        $result->free();
    } else {
        echo "Error checking column '<strong>" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "</strong>' in 'author_profile': " . $conn->error . "<br>";
        continue;
    }
    // Column does not exist; attempt to add it
    if ($conn->query($alterSql) === TRUE) {
        echo "Column '<strong>" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "</strong>' added to 'author_profile'.<br>";
    } else {
        // Error code 1060 means "Duplicate column name" (possible race condition)
        if ($conn->errno == 1060) {
            echo "Column '<strong>" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "</strong>' in 'author_profile' already up to date.<br>";
        } else {
            echo "Error altering table '<strong>author_profile</strong>' when adding column '<strong>" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "</strong>': " . $conn->error . "<br>";
        }
    }
}

// 6. Insert Default Email Templates (Secured with Prepared Statements)
$defaultTemplates = [
    [
        'code' => 'AUTHOR_APPROVED',
        'name' => '作者通过审核通知',
        'subject' => '恭喜！您的作者认证已通过审核',
        'content' => "尊敬的 {{real_name}}（笔名：{{pen_name}}），您好！\n\n我们非常高兴地通知您，您的作者实名认证申请已成功通过审核！\n\n欢迎加入我们的创作者平台。从现在起，您可以登录您的作者后台，开始创作并发布您的优秀作品了。\n\n点击下方链接进入作者后台：\n{{dashboard_url}}\n\n期待您的精彩创作！\n网站运营团队 敬上"
    ],
    [
        'code' => 'AUTHOR_REJECTED',
        'name' => '作者审核驳回通知',
        'subject' => '关于您的作者认证申请结果通知',
        'content' => "尊敬的 {{real_name}}，您好！\n\n感谢您申请成为我们的认证作者。非常遗憾地通知您，经过我们的初步审核，您的申请暂未通过。\n\n驳回原因如下：\n{{reject_reason}}\n\n您可以登录您的账号，根据上述驳回原因修改您的申请资料，并重新提交审核。\n\n修改资料链接：\n{{dashboard_url}}\n\n如果您有任何疑问，请随时联系我们的客服团队。\n网站运营团队 敬上"
    ]
];

// PREPARE the existence check statement ONCE outside the loop for efficiency
$checkStmt = $conn->prepare("SELECT id FROM email_template WHERE template_code = ? LIMIT 1");

foreach ($defaultTemplates as $tpl) {
    // REFACTORED: Use the prepared statement for the existence check
    $checkStmt->bind_param("s", $tpl['code']);
    $checkStmt->execute();
    $checkStmt->store_result();
    $exists = ($checkStmt->num_rows > 0);
    
    if (!$exists) {
        $stmt = $conn->prepare("INSERT INTO email_template (template_code, template_name, subject, content, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'A', NOW(), NOW())");
        $stmt->bind_param("ssss", $tpl['code'], $tpl['name'], $tpl['subject'], $tpl['content']);
        
        if ($stmt->execute()) {
            echo "Default template '<strong>" . $tpl['code'] . "</strong>' inserted successfully.<br>";
        } else {
            echo "Error inserting template '" . $tpl['code'] . "': " . $conn->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "Template '<strong>" . $tpl['code'] . "</strong>' already exists, skipping insert.<br>";
    }
}
$checkStmt->close();
echo "<hr><strong>Environment Build Complete!</strong>";
?>