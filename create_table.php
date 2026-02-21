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

// 2. DROP THE TABLES SAFELY
// Turn off foreign key checks temporarily so we can drop tables that are linked to each other
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tablesToDrop = [
    'user_role_permission',
    'action_master',
    'page_information_list',
    'page_action',
    'user_role' // Added user_role to the drop list
];

foreach ($tablesToDrop as $table) {
    if ($conn->query("DROP TABLE IF EXISTS $table") === TRUE) {
        echo "Dropped table: <strong>$table</strong><br>";
    } else {
        echo "Error dropping <strong>$table</strong>: " . $conn->error . "<br>";
    }
}

// Turn foreign key checks back on
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
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

// 4. Run Queries

foreach ($tables as $name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table '<strong>$name</strong>' checked/created.<br>";
    } else {
        echo "Error table '<strong>$name</strong>': " . $conn->error . "<br>";
    }
}

// 5. INSERT THE FRESH DATA
$insertQueries = [];

// Insert default roles
$insertQueries['user_role'] = "
INSERT INTO user_role (id, name_en, name_cn, description, status) VALUES
(1, 'Admin', '管理员', 'System Administrator', 'A'),
(2, 'Member', '会员', 'Default Registered User', 'A');
";

// Insert page actions
$insertQueries['page_action'] = "
INSERT INTO page_action (id, name, status) VALUES
(1, 'View', 'A'),
(2, 'Add', 'A'),
(3, 'Edit', 'A'),
(4, 'Delete', 'A');
";

// Insert ALL Pages perfectly matched to your PHP code and URL list
$insertQueries['page_information_list'] = "
INSERT INTO page_information_list (id, name_en, name_cn, description, public_url, file_path, status) VALUES
(1, 'Dashboard', '仪表盘', 'Main Dashboard', '/dashboard.php', 'dashboard.php', 'A'),
(2, 'Profile', '个人主页', 'User Profile', '/dashboard.php?view=profile', 'dashboard.php', 'A'),
(3, 'Novel Tags', '小说标签', 'Manage Novel Tags', '/dashboard.php?view=tags', 'dashboard.php', 'A'),
(4, 'Tag Form', '标签表单', 'Tag Create/Edit Form', '/dashboard.php?view=tag_form', 'dashboard.php', 'A'),
(5, 'Novel Categories', '小说分类', 'Manage Categories', '/dashboard.php?view=categories', 'dashboard.php', 'A'),
(6, 'Category Form', '分类表单', 'Category Create/Edit Form', '/dashboard.php?view=cat_form', 'dashboard.php', 'A'),
(7, 'Meta Settings', 'SEO设置', 'SEO Meta Settings', '/dashboard.php?view=meta_settings', 'dashboard.php', 'A'),
(8, 'Web Settings', '网站设置', 'Global Web Settings', '/dashboard.php?view=web_settings', 'dashboard.php', 'A'),
(9, 'Admin Management', '管理员管理', 'Manage Admin Users', '/dashboard.php?view=admin', 'dashboard.php', 'A'),
(10, 'Page Actions', '页面动作', 'Manage Page Actions', '/dashboard.php?view=page_action', 'dashboard.php', 'A'),
(11, 'Page Information', '页面信息', 'Manage Page URLs', '/dashboard.php?view=page_info', 'dashboard.php', 'A'),
(12, 'User Roles', '用户角色', 'Manage Roles', '/dashboard.php?view=user_role', 'dashboard.php', 'A'),
(13, 'Login', '登录', 'User Login Page', '/login.php', 'login.php', 'A'),
(14, 'Register', '注册', 'User Registration', '/register.php', 'register.php', 'A'),
(15, 'Forgot Password', '忘记密码', 'Forgot Password', '/forgot-password.php', 'forgot-password.php', 'A'),
(16, 'Reset Password', '重置密码', 'Reset Password', '/reset-password.php', 'reset-password.php', 'A'),
(17, 'Home', '首页', 'Public Homepage', '/Home.php', 'Home.php', 'A'),
(18, 'Audit Log', '审计日志', 'System Audit Log', '/audit-log.php', 'audit-log.php', 'A');
";

// Bind Actions to Pages
$insertQueries['action_master'] = "
INSERT INTO action_master (page_id, action_id) VALUES
(1, 1), 
(2, 1), (2, 3), 
(3, 1), (3, 2), (3, 3), (3, 4), 
(4, 1), 
(5, 1), (5, 2), (5, 3), (5, 4), 
(6, 1), 
(7, 1), (7, 3), 
(8, 1), (8, 3), 
(9, 1), (9, 2), (9, 3), (9, 4), 
(10, 1), (10, 2), (10, 3), (10, 4), 
(11, 1), (11, 2), (11, 3), (11, 4), 
(12, 1), (12, 2), (12, 3), (12, 4), 
(13, 1), 
(14, 1), 
(15, 1), 
(16, 1), 
(17, 1), 
(18, 1);
";

// Grant Admin (Role ID 1) access to EVERY defined page and action above
$insertQueries['user_role_permission'] = "
INSERT INTO user_role_permission (user_role_id, page_id, action_id) VALUES
(1, 1, 1),
(1, 2, 1), (1, 2, 3),
(1, 3, 1), (1, 3, 2), (1, 3, 3), (1, 3, 4),
(1, 4, 1),
(1, 5, 1), (1, 5, 2), (1, 5, 3), (1, 5, 4),
(1, 6, 1),
(1, 7, 1), (1, 7, 3),
(1, 8, 1), (1, 8, 3),
(1, 9, 1), (1, 9, 2), (1, 9, 3), (1, 9, 4),
(1, 10, 1), (1, 10, 2), (1, 10, 3), (1, 10, 4),
(1, 11, 1), (1, 11, 2), (1, 11, 3), (1, 11, 4),
(1, 12, 1), (1, 12, 2), (1, 12, 3), (1, 12, 4),
(1, 13, 1),
(1, 14, 1),
(1, 15, 1),
(1, 16, 1),
(1, 17, 1),
(1, 18, 1);
";

// Execute the queries
foreach ($insertQueries as $tableName => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Fresh data for '<strong>$tableName</strong>' inserted successfully.<br>";
    } else {
        echo "Error inserting data for '<strong>$tableName</strong>': " . $conn->error . "<br>";
    }
}

echo "<hr><strong>Tables Reset and Seeded Successfully!</strong>";

// 5. Apply Schema Updates (ALTER TABLE)

// --- UPDATE 1: users table (user_role_id) ---
$checkColUsers = $conn->query("SHOW COLUMNS FROM users LIKE 'user_role_id'");
if ($checkColUsers && $checkColUsers->num_rows === 0) {
    $alterSql = "ALTER TABLE users ADD COLUMN user_role_id BIGINT DEFAULT NULL COMMENT 'Foreign Key to user_role', ADD CONSTRAINT fk_users_role FOREIGN KEY (user_role_id) REFERENCES user_role(id) ON DELETE SET NULL";
    if ($conn->query($alterSql) === TRUE) {
        echo "Table '<strong>users</strong>' altered successfully (added user_role_id).<br>";
    } else {
        echo "Error altering table '<strong>users</strong>': " . $conn->error . "<br>";
    }
}

// Update all current users to be Admins (Role ID = 1)
$updateUsersSql = "UPDATE users SET user_role_id = 1";
if ($conn->query($updateUsersSql) === TRUE) {
    echo "Successfully updated all existing users to Admin (Role ID 1).<br>";
} else {
    echo "Error updating existing users: " . $conn->error . "<br>";
}

$conn->close();
echo "<hr><strong>Environment Build Complete!</strong>";
?>