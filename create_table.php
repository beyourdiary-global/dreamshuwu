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
    status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Deleted',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by BIGINT,
    updated_by BIGINT,
    INDEX (name),
    INDEX idx_tag_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['novel_category'] = "
CREATE TABLE IF NOT EXISTS novel_category (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Category name, e.g., Modern Romance',
    status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Deleted',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last updated timestamp',
    created_by BIGINT NOT NULL COMMENT 'User ID who created the category',
    updated_by BIGINT NOT NULL COMMENT 'User ID who last updated the category',
    INDEX idx_cat_name (name),
    INDEX idx_category_status (status)
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

$tables['novel'] = "
CREATE TABLE IF NOT EXISTS novel (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL COMMENT 'FK to users table (author)',
  title VARCHAR(100) NOT NULL COMMENT 'Book Title',
  category_id BIGINT NOT NULL COMMENT 'FK to novel_category',
  tags TEXT DEFAULT NULL COMMENT 'Comma separated tag names',
  introduction VARCHAR(2000) DEFAULT NULL COMMENT 'Introduction',
  cover_image VARCHAR(255) DEFAULT NULL COMMENT 'Cover image path',
  completion_status VARCHAR(20) DEFAULT 'ongoing' COMMENT 'ongoing / completed',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'active / deleted',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_title_per_author (author_id, title),
  CONSTRAINT fk_novel_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_novel_category_link FOREIGN KEY (category_id) REFERENCES novel_category (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['chapter'] = "
CREATE TABLE IF NOT EXISTS chapter (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  novel_id BIGINT NOT NULL COMMENT 'FK to novel table',
  author_id INT NOT NULL COMMENT 'FK to users table',
  chapter_number INT NOT NULL COMMENT 'Used for manual ordering/sorting',
  title VARCHAR(255) NOT NULL COMMENT 'Chapter Title',
  content MEDIUMTEXT NOT NULL COMMENT 'Strict plain-text only content',
  word_count INT DEFAULT 0 COMMENT 'Auto-calculated word count',
  publish_status ENUM('draft', 'scheduled', 'published') DEFAULT 'draft',
  scheduled_publish_at DATETIME NULL COMMENT 'Future timestamp for automated publishing',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Deleted (Soft Delete)',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_novel_chapter (novel_id, chapter_number),
  CONSTRAINT fk_chapter_novel FOREIGN KEY (novel_id) REFERENCES novel (id) ON DELETE CASCADE,
  CONSTRAINT fk_chapter_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// --- 22. Chapter Version History Table ---
$tables['chapter_version'] = "
CREATE TABLE IF NOT EXISTS chapter_version (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chapter_id BIGINT NOT NULL COMMENT 'FK to chapter table',
  version_number INT NOT NULL COMMENT 'Incremental version count per chapter',
  title VARCHAR(255) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  word_count INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT NOT NULL COMMENT 'Author user_id',
  INDEX idx_version_lookup (chapter_id, version_number),
  CONSTRAINT fk_version_chapter FOREIGN KEY (chapter_id) REFERENCES chapter (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['sensitive_word'] = "
CREATE TABLE IF NOT EXISTS sensitive_word (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  word VARCHAR(100) NOT NULL COMMENT 'The forbidden word',
  replacement VARCHAR(100) DEFAULT '***' COMMENT 'The safe string to replace with',
  severity_level TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1:Auto-replace, 2:Replace+Warn, 3:Block',
  status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, I = Inactive',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_sensitive_word (word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$tables['sensitive_word_log'] = "
CREATE TABLE IF NOT EXISTS sensitive_word_log (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL COMMENT 'User who triggered the violation',
  chapter_id BIGINT DEFAULT NULL COMMENT 'Target chapter (if applicable)',
  detected_word VARCHAR(100) NOT NULL,
  severity_level TINYINT(1) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_violation_author (author_id),
  CONSTRAINT fk_sw_log_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE
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

// 5. Check Schema Status Before Updating
echo "<h3>Checking Schema Updates...</h3>";

// Condition 1: Check if Tag Table needs an update
$checkTagCol = $conn->query("SHOW COLUMNS FROM novel_tag LIKE 'status'");
$tagNeedsUpdate = ($checkTagCol && $checkTagCol->num_rows === 0);

// Condition 2: Check if Category Table needs an update
$checkCatCol = $conn->query("SHOW COLUMNS FROM novel_category LIKE 'status'");
$catNeedsUpdate = ($checkCatCol && $checkCatCol->num_rows === 0);

// 6. Initialize Base Data (Run BEFORE Altering)
echo "<h3>Initializing Base Data...</h3>";

// TRIGGER CONDITION: Only run if your 2 conditions are met
if (!$tagNeedsUpdate && !$catNeedsUpdate) {
    echo "<strong style='color:green;'>No schema updates are needed. Skipping data initialization.</strong><br>";
} else {
    $initDataSql = "
    -- 1. Disable foreign key checks temporarily
    SET FOREIGN_KEY_CHECKS = 0;

    -- 2. Safely empty child tables first, then parent tables, and reset Auto-Increments
    DELETE FROM `user_role_permission`;
    DELETE FROM `action_master`;

    DELETE FROM `page_action`;
    ALTER TABLE `page_action` AUTO_INCREMENT = 1;

    DELETE FROM `page_information_list`;
    ALTER TABLE `page_information_list` AUTO_INCREMENT = 1;

    -- 3. Insert fresh Page Actions
    INSERT INTO `page_action` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
    (1, 'View', 'A', NOW(), NOW()),
    (2, 'Add', 'A', NOW(), NOW()),
    (3, 'Edit', 'A', NOW(), NOW()),
    (4, 'Delete', 'A', NOW(), NOW()),
    (5, 'Save', 'A', NOW(), NOW()),
    (6, 'Approve', 'A', NOW(), NOW()),
    (7, 'Reject', 'A', NOW(), NOW()),
    (8, 'Resend Email', 'A', NOW(), NOW()),
    (9, 'Reset_defaults', 'A', NOW(), NOW()),
    (10, 'Remove_favicon', 'A', NOW(), NOW()),
    (11, 'Remove_logo', 'A', NOW(), NOW());

    -- 4. Insert clean Page Information (IDs strictly 1 to 24, no skips)
    INSERT INTO `page_information_list` (`id`, `name_en`, `name_cn`, `description`, `public_url`, `file_path`, `status`, `created_at`, `updated_at`) VALUES
    (1, 'Dashboard', '仪表盘', 'Main Dashboard', '/dashboard/', '/dashboard.php', 'A', NOW(), NOW()),
    (2, 'Profile', '个人主页', 'User Profile', '/profile/', '/src/pages/user/profile.php', 'A', NOW(), NOW()),
    (3, 'Novel Tags', '小说标签', 'Manage Novel Tags', '/tags/', '/src/pages/tags/index.php', 'A', NOW(), NOW()),
    (4, 'Tag Form', '标签表单', 'Tag Create/Edit Form', '/tags/?tag_mode=form', '/src/pages/tags/form.php', 'D', NOW(), NOW()),
    (5, 'Novel Categories', '小说分类', 'Manage Categories', '/category/', '/src/pages/category/index.php', 'A', NOW(), NOW()),
    (6, 'Category Form', '分类表单', 'Category Create/Edit Form', '/category/?cat_mode=form', '/src/pages/category/form.php', 'D', NOW(), NOW()),
    (7, 'Meta Settings', 'SEO设置', 'SEO Meta Settings', '/meta-setting/', '/src/pages/meta/index.php', 'A', NOW(), NOW()),
    (8, 'Web Settings', '网站设置', 'Global Web Settings', '/web-settings/', '/src/pages/webSetting/index.php', 'A', NOW(), NOW()),
    (9, 'Admin Management', '管理员管理', 'Manage Admin Users', '/admin/', '/src/pages/admin/index.php', 'A', NOW(), NOW()),
    (10, 'Page Actions', '页面动作', 'Manage Page Actions', '/admin/page-action/', '/src/pages/admin/page-action/index.php', 'A', NOW(), NOW()),
    (11, 'Page Information', '页面信息', 'Manage Page URLs', '/admin/page-information-list/', '/src/pages/admin/page-information-list/index.php', 'A', NOW(), NOW()),
    (12, 'User Roles', '用户角色', 'Manage Roles', '/admin/user-role/', '/src/pages/admin/user-role/index.php', 'A', NOW(), NOW()),
    (13, 'Login', '登录', 'User Login Page', '/login/', '/login.php', 'A', NOW(), NOW()),
    (14, 'Register', '注册', 'User Registration', '/register/', '/register.php', 'A', NOW(), NOW()),
    (15, 'Forgot Password', '忘记密码', 'Forgot Password', '/forgot-password/', '/forgot-password.php', 'A', NOW(), NOW()),
    (16, 'Reset Password', '重置密码', 'Reset Password', '/reset-password/', '/reset-password.php', 'A', NOW(), NOW()),
    (17, 'Home', '首页', 'Public Homepage', '/', '/index.php', 'A', NOW(), NOW()),
    (18, 'Audit Log', '审计日志', 'System Audit Log', '/audit-log', '/audit-log.php', 'A', NOW(), NOW()),
    (19, 'Author Register Page', 'Author Register', '', '/author/author-register', '/src/pages/author/author-register.php', 'A', NOW(), NOW()),
    (20, 'Author Verification', 'Author Verification', '', '/author/author-verification', '/src/pages/author/author-verification/index.php', 'A', NOW(), NOW()),
    (21, 'Email Template', 'Email Template', '', '/author/email-template', '/src/pages/author/email-template/index.php', 'A', NOW(), NOW()),
    (22, 'Author Dashboard', '作者首页', '', '/author/dashboard', '/src/pages/author/dashboard.php', 'A', NOW(), NOW()),
    (23, 'Novel Management', 'Novel Management', '', '/author/novel-management', '/src/pages/author/novel-management/index.php', 'A', NOW(), NOW()),
    (24, 'Chapters', 'Chapter', '', '/author/novel/chapters/', '/src/pages/author/chapter-management/index.php', 'A', NOW(), NOW());

    -- 5. Bind ALL Active Actions to ALL Active Pages
    INSERT INTO `action_master` (`page_id`, `action_id`)
    SELECT p.id, a.id 
    FROM `page_information_list` p
    CROSS JOIN `page_action` a
    WHERE p.status = 'A' AND a.status = 'A';

    -- 6. Grant Admin (ID 1) ALL Actions on ALL Pages
    INSERT INTO `user_role_permission` (`user_role_id`, `page_id`, `action_id`)
    SELECT 1, p.id, a.id
    FROM `page_information_list` p
    CROSS JOIN `page_action` a
    WHERE p.status = 'A' AND a.status = 'A';

    -- 7. Grant Member (ID 2) Limited Access 
    -- (Dashboard, Profile, Public Pages, Author Register)
    INSERT INTO `user_role_permission` (`user_role_id`, `page_id`, `action_id`)
    SELECT 2, p.id, a.id
    FROM `page_information_list` p
    CROSS JOIN `page_action` a
    WHERE p.status = 'A' AND a.status = 'A'
    AND p.id IN (1, 2, 13, 14, 15, 16, 17, 19);

    -- 8. Grant Author (ID 3) Same as Member + Author Features
    -- (Author Dashboard, Novel Management [23], Chapters [24])
    INSERT INTO `user_role_permission` (`user_role_id`, `page_id`, `action_id`)
    SELECT 3, p.id, a.id
    FROM `page_information_list` p
    CROSS JOIN `page_action` a
    WHERE p.status = 'A' AND a.status = 'A'
    AND p.id IN (1, 2, 13, 14, 15, 16, 17, 19, 22, 23, 24);

    -- 9. Re-enable foreign key checks
    SET FOREIGN_KEY_CHECKS = 1;
    ";

    // Execute the massive multi-query block
    if ($conn->multi_query($initDataSql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        echo "<strong style='color:blue;'>Successfully wiped old data and initialized new base data (Pages, Roles, Actions).</strong><br>";
    } else {
        echo "<strong style='color:red;'>Error initializing base data: " . $conn->error . "</strong><br>";
    }
}


// 7. Apply the Schema Updates (ALTER TABLES)
echo "<h3>Applying Schema Updates...</h3>";

if ($tagNeedsUpdate) {
    if ($conn->query("ALTER TABLE novel_tag ADD COLUMN status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Deleted' AFTER name")) {
        $conn->query("CREATE INDEX idx_tag_status ON novel_tag (status)");
        echo "Successfully added 'status' column to novel_tag.<br>";
    }
} else {
    echo "novel_tag table is already up to date.<br>";
}

if ($catNeedsUpdate) {
    if ($conn->query("ALTER TABLE novel_category ADD COLUMN status CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A = Active, D = Deleted' AFTER name")) {
        $conn->query("CREATE INDEX idx_category_status ON novel_category (status)");
        echo "Successfully added 'status' column to novel_category.<br>";
    }
} else {
    echo "novel_category table is already up to date.<br>";
}

$conn->close();
echo "<hr><strong>Environment Build Complete!</strong>";
?>