<?php
defined('SITEURL') || die('SITEURL not defined');

// --- 1. Assets ---
defined('URL_ASSETS') || define('URL_ASSETS', SITEURL . '/assets');

// --- 2. Auth Pages (src/pages/login/ & register/) ---
defined('URL_LOGIN')      || define('URL_LOGIN', SITEURL . '/src/pages/login/login.php');
defined('URL_LOGOUT')      || define('URL_LOGOUT', SITEURL . '/src/pages/login/logout.php');
defined('URL_REGISTER')   || define('URL_REGISTER', SITEURL . '/src/pages/register/register.php');
defined('URL_FORGOT_PWD') || define('URL_FORGOT_PWD', SITEURL . '/src/pages/forgot-password/forgot-password.php');
defined('URL_RESET_PWD')  || define('URL_RESET_PWD', SITEURL . '/src/pages/forgot-password/reset-password.php');

// --- 3. Main Pages (Assuming located in src/pages/) ---
defined('URL_HOME')       || define('URL_HOME', SITEURL . '/src/pages/Home.php');
defined('URL_CATEGORIES') || define('URL_CATEGORIES', SITEURL . '/src/pages/categories.php');
defined('URL_RANKING')    || define('URL_RANKING', SITEURL . '/src/pages/ranking.php');
defined('URL_BOOKSHELF')  || define('URL_BOOKSHELF', SITEURL . '/src/pages/bookshelf.php');
defined('URL_SEARCH')     || define('URL_SEARCH', SITEURL . '/src/pages/search.php');


// --- 4. User Dashboard & Sub-pages ---
defined('URL_USER_DASHBOARD') || define('URL_USER_DASHBOARD', SITEURL . '/src/pages/user/dashboard.php');
defined('URL_PROFILE')    || define('URL_PROFILE', SITEURL . '/src/pages/user/profile.php');
defined('URL_USER_SETTING')   || define('URL_USER_SETTING', SITEURL . '/src/pages/user/settings.php');
// Added for Mobile/Desktop Quick Actions
defined('URL_USER_HISTORY')   || define('URL_USER_HISTORY', SITEURL . '/src/pages/user/history.php');
defined('URL_USER_MESSAGES')  || define('URL_USER_MESSAGES', SITEURL . '/src/pages/user/messages.php');

// --- 5. Author Dashboard ---
defined('URL_AUTHOR_DASHBOARD') || define('URL_AUTHOR_DASHBOARD', SITEURL . '/src/pages/author/dashboard.php');
?>