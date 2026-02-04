<?php
defined('SITEURL') || die('SITEURL not defined');

// --- 1. Assets ---
defined('URL_ASSETS') || define('URL_ASSETS', SITEURL . '/assets');

// --- 2. Auth Pages (src/pages/login/ & register/) ---
defined('URL_LOGIN')      || define('URL_LOGIN', SITEURL . '/src/pages/login/login.php');
defined('URL_REGISTER')   || define('URL_REGISTER', SITEURL . '/src/pages/register/register.php');
defined('URL_FORGOT_PWD') || define('URL_FORGOT_PWD', SITEURL . '/src/pages/forgot-password/forgot-password.php');
defined('URL_RESET_PWD')  || define('URL_RESET_PWD', SITEURL . '/src/pages/forgot-password/reset-password.php');

// --- 3. Main Pages (Assuming located in src/pages/) ---
defined('URL_HOME')       || define('URL_HOME', SITEURL . '/src/pages/Home.php');
defined('URL_CATEGORIES') || define('URL_CATEGORIES', SITEURL . '/src/pages/categories.php');
defined('URL_RANKING')    || define('URL_RANKING', SITEURL . '/src/pages/ranking.php');
defined('URL_BOOKSHELF')  || define('URL_BOOKSHELF', SITEURL . '/src/pages/bookshelf.php');
defined('URL_SEARCH')     || define('URL_SEARCH', SITEURL . '/src/pages/search.php');
defined('URL_PROFILE')    || define('URL_PROFILE', SITEURL . '/src/pages/profile.php');

// --- 4. Author Pages ---
defined('URL_AUTHOR_DASHBOARD') || define('URL_AUTHOR_DASHBOARD', SITEURL . '/src/pages/author_dashboard.php');
?>