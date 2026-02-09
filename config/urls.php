<?php
defined('SITEURL') || die('SITEURL not defined');

// --- 1. Assets ---
defined('URL_ASSETS') || define('URL_ASSETS', SITEURL . '/assets');

// --- 2. Auth Pages ---
defined('URL_LOGIN')      || define('URL_LOGIN', SITEURL . '/src/pages/login/login.php');
defined('URL_REGISTER')   || define('URL_REGISTER', SITEURL . '/src/pages/register/register.php');
defined('URL_FORGOT_PWD') || define('URL_FORGOT_PWD', SITEURL . '/src/pages/forgot-password/forgot-password.php');
defined('URL_RESET_PWD')  || define('URL_RESET_PWD', SITEURL . '/src/pages/forgot-password/reset-password.php');

// --- 3. Main Pages ---
defined('URL_HOME')       || define('URL_HOME', SITEURL . '/src/pages/Home.php');
defined('URL_CATEGORIES') || define('URL_CATEGORIES', SITEURL . '/src/pages/categories.php');
defined('URL_RANKING')    || define('URL_RANKING', SITEURL . '/src/pages/ranking.php');
defined('URL_BOOKSHELF')  || define('URL_BOOKSHELF', SITEURL . '/src/pages/bookshelf.php');
defined('URL_SEARCH')     || define('URL_SEARCH', SITEURL . '/src/pages/search.php');

// --- 4. User Dashboard ---
defined('URL_USER_DASHBOARD') || define('URL_USER_DASHBOARD', SITEURL . '/src/pages/user/dashboard.php');
defined('URL_PROFILE')        || define('URL_PROFILE', SITEURL . '/src/pages/user/profile.php');
defined('URL_USER_SETTING')   || define('URL_USER_SETTING', SITEURL . '/src/pages/user/settings.php');
defined('URL_USER_HISTORY')   || define('URL_USER_HISTORY', SITEURL . '/src/pages/user/history.php');
defined('URL_USER_MESSAGES')  || define('URL_USER_MESSAGES', SITEURL . '/src/pages/user/messages.php');

// --- 5. Author Dashboard ---
defined('URL_AUTHOR_DASHBOARD') || define('URL_AUTHOR_DASHBOARD', SITEURL . '/src/pages/author/dashboard.php');

// --- 6. Novel Management (Tags) ---
defined('URL_NOVEL_TAGS')        || define('URL_NOVEL_TAGS', SITEURL . '/src/pages/user/dashboard.php?view=tags');
defined('URL_NOVEL_TAGS_API')    || define('URL_NOVEL_TAGS_API', SITEURL . '/src/pages/tags/index.php');
defined('PATH_NOVEL_TAGS_INDEX') || define('PATH_NOVEL_TAGS_INDEX', 'src/pages/tags/index.php');
defined('PATH_NOVEL_TAGS_FORM')  || define('PATH_NOVEL_TAGS_FORM', 'src/pages/tags/form.php');

// --- 7. Novel Management (Categories) [NEW] ---
// UI entry for categories (rendered inside user dashboard)
defined('URL_NOVEL_CATS')        || define('URL_NOVEL_CATS', SITEURL . '/src/pages/user/dashboard.php?view=categories');
// Backend API endpoint for categories
defined('URL_NOVEL_CATS_API')    || define('URL_NOVEL_CATS_API', SITEURL . '/src/pages/category/index.php');
// File path constants for requires
defined('PATH_NOVEL_CATS_INDEX') || define('PATH_NOVEL_CATS_INDEX', 'src/pages/category/index.php');
defined('PATH_NOVEL_CATS_FORM')  || define('PATH_NOVEL_CATS_FORM', 'src/pages/category/form.php');

// --  8. Audit Log Page ---
defined('URL_AUDIT_LOG') || define('URL_AUDIT_LOG', SITEURL . '/src/pages/audit-log.php');

// --- 8. Logout ---
// Example in config/urls.php
defined('URL_LOGOUT') || define('URL_LOGOUT', SITEURL . '/src/pages/logout/');
?>