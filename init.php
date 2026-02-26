<?php
session_start();
// $livemode = false; // true = test link, false = live link
// Auto-detect local environment
$isLocalEnvironment = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
$siteOrlocalMode = !$isLocalEnvironment;  //true = live site, false = localhost

date_default_timezone_set('Asia/Singapore');


$dbUser = $siteOrlocalMode ? 'beyourdi_cms' : 'root';

//cms database
define('dbuser', $dbUser);
define('dbpwd', $siteOrlocalMode ? 'Byd1234@Global' : '');
define('dbhost', $siteOrlocalMode ? '127.0.0.1:3306' : 'localhost');
define('dbname', $siteOrlocalMode ? 'beyourdi_dreamshuwu' : 'star_admin');
define('dbFinance', 'beyourdi_financial');

// Calculate SITEURL based on project root directory (where init.php lives)
$localHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$initDir = rtrim(str_replace('\\', '/', __DIR__), '/');
// Get relative path from document root to project root
$projectPath = '';
if ($docRoot !== '' && strpos($initDir, $docRoot) === 0) {
    $projectPath = substr($initDir, strlen($docRoot));
}
define('SITEURL', $siteOrlocalMode ? 'https://dreamshuwu.beyourdiary.com' : ('http://' . $localHost . $projectPath));
$SITEURL = SITEURL;
define('ROOT', dirname(__FILE__));
define('email_cc', "report@beyourdiary.com	");


// //define date time
define('date_dis', date("Y-m-d"));
define('time_dis', date("G:i:s"));
define('yearMonth', strtolower(date('YM')));
define('comYMD', strtolower(date('Ymd')));
define('GlobalPin', isset($_SESSION['usr_pin']) ? $_SESSION['usr_pin'] : '');

// 1. Define the PATTERNS (Rules for formatting )
defined('DATE_FORMAT')     || define('DATE_FORMAT', 'Y-m-d');
defined('TIME_FORMAT')     || define('TIME_FORMAT', 'G:i:s');

// define('memberImportDetail', yearMonth.'_importInfo');

$email_collect = '';
$cdate = date_dis;
$ctime = time_dis;
$comYMD = comYMD;
/* $cby = $_SESSION['userid']; */

$act_1 = 'I'; //Insert/ Add
$act_2 = 'E'; //Edit/ Update
$act_3 = 'D'; //Delete

// //session define
// $displayName = $_SESSION['login_name'];
define('USER_ID', isset($_SESSION['userid']) ? $_SESSION['userid'] : '');
define('USER_NAME', isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '');
define('USER_EMAIL', isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');
define('USER_GROUP', isset($_SESSION['user_group']) ? $_SESSION['user_group'] : '');


$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

//-- Error Page URL Constant --
defined('URL_ERROR') || define('URL_ERROR', SITEURL . '/error404.php');


// This gets the absolute path to the directory containing init.php
define('BASE_PATH', __DIR__ . '/');

// --- Gender Options Mapping ---
// Key = Database Value, Value = Display Text (Chinese)
$GENDER_OPTIONS = [
    ""  => "选择性别 (可选)",
    "M" => "男",
    "F" => "女",
    "O" => "其他"
];

// Define Avatar Upload Size (2MB)
// 2 * 1024 * 1024 = 2097152 bytes
defined('AVATAR_UPLOAD_SIZE')  || define('AVATAR_UPLOAD_SIZE', 2097152);

// --- User Table Constant ---
define('USR_LOGIN', 'users');

// --- Password Reset Table Constant ---
defined('PWD_RESET') || define('PWD_RESET', 'password_resets');

// -- User Dashboard Table Constant ---
defined('USR_DASHBOARD') || define('USR_DASHBOARD', 'users_dashboard');

// --- Audit Log Table Constant ---
defined('AUDIT_LOG') || define('AUDIT_LOG', 'audit_log');

// --- Novel Tags Table Constant ---
defined('NOVEL_TAGS') || define('NOVEL_TAGS', 'novel_tag');

// --- Novel Category Tables ---
defined('NOVEL_CATEGORY') || define('NOVEL_CATEGORY', 'novel_category');
defined('CATEGORY_TAG')   || define('CATEGORY_TAG', 'category_tag');

// --- Meta Settings Table ---
defined('META_SETTINGS') || define('META_SETTINGS', 'meta_settings');
defined('META_SETTINGS_PAGE') || define('META_SETTINGS_PAGE', 'meta_settings_page');

// --- Web Settings Table ---
defined('WEB_SETTINGS') || define('WEB_SETTINGS', 'web_settings');

// --- Page Action Table ---
defined('PAGE_ACTION') || define('PAGE_ACTION', 'page_action');

// -- Page Information List Table ---
defined('PAGE_INFO_LIST') || define('PAGE_INFO_LIST', 'page_information_list');
defined('ACTION_MASTER') || define('ACTION_MASTER', 'action_master');

// --- User Role Tables ---
defined('USER_ROLE') || define('USER_ROLE', 'user_role');
defined('USER_ROLE_PERMISSION') || define('USER_ROLE_PERMISSION', 'user_role_permission');

// --- Author Profile Table Constant ---
defined('AUTHOR_PROFILE') || define('AUTHOR_PROFILE', 'author_profile');
defined('EMAIL_TEMPLATE') || define('EMAIL_TEMPLATE', 'email_template');
defined('EMAIL_LOG') || define('EMAIL_LOG', 'email_log');

// -- Novel Table Constants ---
defined('NOVEL') || define('NOVEL', 'novel');

// --- Chapter Management Table Constants ---
defined('CHAPTER')            || define('CHAPTER', 'chapter');
defined('CHAPTER_VERSION')    || define('CHAPTER_VERSION', 'chapter_version');
defined('SENSITIVE_WORD')     || define('SENSITIVE_WORD', 'sensitive_word');
defined('SENSITIVE_WORD_LOG') || define('SENSITIVE_WORD_LOG', 'sensitive_word_log');
 
// --- Application Constants ---
defined('MIN_AGE_REQUIREMENT') || define('MIN_AGE_REQUIREMENT', 13);
defined('MIN_PWD_LENGTH')      || define('MIN_PWD_LENGTH', 8);

// --- Email Configuration ---
defined('MAIL_FROM')      || define('MAIL_FROM', 'report@beyourdiary.com');
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', 'Dreamshuwu Support');

// --- Regex Patterns for Validation ---
defined('EMAIL_REGEX_PATTERN') || define('EMAIL_REGEX_PATTERN', '^[^\s@]+@[^\s@]+\.[^\s@]+$');
defined('PWD_REGEX_PATTERN')   || define('PWD_REGEX_PATTERN', '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$');

// --- Environment Configuration ---
// List of hostnames/IPs considered as local development environments
// Using comma-separated string for PHP 5.6 compatibility
defined('LOCAL_WHITELIST') || define('LOCAL_WHITELIST', '127.0.0.1,::1,localhost');
// Site language setting
defined('SITE_LANG') || define('SITE_LANG', 'zh-CN');

// Use the constants already defined above
$connHost = dbhost;
$connUser = dbuser;
$connPass = dbpwd;
$connDb   = dbname;

// Parse host:port format (e.g. 127.0.0.1:3306)
$connPort = 3306;
if (strpos($connHost, ':') !== false) {
    $connParts = explode(':', $connHost, 2);
    $connHost = $connParts[0];
    $connPort = (int) $connParts[1];
}

// 2. Establish connection
try {
    $conn = @mysqli_connect($connHost, $connUser, $connPass, $connDb, $connPort);
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

// 3. Check connection & Debug
if (!$conn || mysqli_connect_errno()) {
    if (!defined('SKIP_DB_CHECK')) {
        header("Location: " . URL_ERROR);
        exit();
    }
}

mysqli_set_charset($conn, 'utf8mb4');