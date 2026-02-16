<?php
defined('SITEURL') || die('SITEURL not defined');

// --- 1. Assets ---
defined('URL_ASSETS') || define('URL_ASSETS', SITEURL . '/assets');

// --- 2. Auth Pages ---
defined('URL_LOGIN')      || define('URL_LOGIN', SITEURL . '/login.php');
defined('URL_LOGOUT')     || define('URL_LOGOUT', SITEURL . '/logout.php');
defined('URL_REGISTER')   || define('URL_REGISTER', SITEURL . '/register.php');
defined('URL_FORGOT_PWD') || define('URL_FORGOT_PWD', SITEURL . '/forgot-password.php');
defined('URL_RESET_PWD')  || define('URL_RESET_PWD', SITEURL . '/reset-password.php');

// --- 3. Main Pages ---
defined('URL_HOME')       || define('URL_HOME', SITEURL . '/Home.php');
defined('URL_CATEGORIES') || define('URL_CATEGORIES', SITEURL . '/src/pages/categories.php');
defined('URL_RANKING')    || define('URL_RANKING', SITEURL . '/src/pages/ranking.php');
defined('URL_BOOKSHELF')  || define('URL_BOOKSHELF', SITEURL . '/src/pages/bookshelf.php');
defined('URL_SEARCH')     || define('URL_SEARCH', SITEURL . '/src/pages/search.php');

// --- 4. User Dashboard ---
defined('URL_USER_DASHBOARD') || define('URL_USER_DASHBOARD', SITEURL . '/dashboard.php');
defined('URL_PROFILE')        || define('URL_PROFILE', SITEURL . '/profile.php');
defined('PATH_PROFILE')       || define('PATH_PROFILE',  '/src/pages/user/profile.php');
defined('URL_USER_HISTORY')   || define('URL_USER_HISTORY', SITEURL . '/src/pages/user/history.php');
defined('URL_USER_MESSAGES')  || define('URL_USER_MESSAGES', SITEURL . '/src/pages/user/messages.php');

// --- 5. Author Dashboard ---
defined('URL_AUTHOR_DASHBOARD') || define('URL_AUTHOR_DASHBOARD', SITEURL . '/src/pages/author/dashboard.php');

// --- 6. Novel Management (Tags) ---
defined('URL_NOVEL_TAGS')        || define('URL_NOVEL_TAGS', URL_USER_DASHBOARD . '?view=tags');
defined('URL_NOVEL_TAGS_API')    || define('URL_NOVEL_TAGS_API', SITEURL . '/src/pages/tags/index.php');
defined('PATH_NOVEL_TAGS_INDEX') || define('PATH_NOVEL_TAGS_INDEX', 'src/pages/tags/index.php');
defined('PATH_NOVEL_TAGS_FORM')  || define('PATH_NOVEL_TAGS_FORM', 'src/pages/tags/form.php');

// --- 7. Novel Management (Categories) ---
defined('URL_NOVEL_CATS')        || define('URL_NOVEL_CATS', URL_USER_DASHBOARD . '?view=categories');
defined('URL_NOVEL_CATS_API')    || define('URL_NOVEL_CATS_API', SITEURL . '/src/pages/category/index.php');
defined('PATH_NOVEL_CATS_INDEX') || define('PATH_NOVEL_CATS_INDEX', 'src/pages/category/index.php');
defined('PATH_NOVEL_CATS_FORM')  || define('PATH_NOVEL_CATS_FORM', 'src/pages/category/form.php');

// -- 8. Audit Log Page ---
defined('URL_AUDIT_LOG') || define('URL_AUDIT_LOG', SITEURL . '/audit-log.php');

// -- 9. Meta Settings Page ---
defined('URL_META_SETTINGS') || define('URL_META_SETTINGS', SITEURL . '/meta-setting.php');
defined('PATH_META_SETTINGS') || define('PATH_META_SETTINGS', 'src/pages/meta/index.php');

// -- 10. Web Settings Page ---
defined('URL_WEB_SETTINGS')  || define('URL_WEB_SETTINGS', URL_USER_DASHBOARD . '?view=web_settings');
defined('PATH_WEB_SETTINGS') || define('PATH_WEB_SETTINGS', 'src/pages/webSetting/index.php');

// -- 11. Admin Pages [NEW] ---
defined('URL_PAGE_ACTION')     || define('URL_PAGE_ACTION', URL_USER_DASHBOARD . '?view=page_action');
defined('URL_PAGE_ACTION_API') || define('URL_PAGE_ACTION_API', SITEURL . '/src/pages/admin/page-action/index.php');

defined('URL_ADMIN_DASHBOARD') || define('URL_ADMIN_DASHBOARD', URL_USER_DASHBOARD . '?view=admin');
defined('PATH_ADMIN_INDEX')    || define('PATH_ADMIN_INDEX', 'src/pages/admin/index.php');
defined('PATH_PAGE_ACTION')    || define('PATH_PAGE_ACTION', 'src/pages/admin/page-action/index.php');

// -- 12. Page Information List [NEW] ---
defined('URL_PAGE_INFO')       || define('URL_PAGE_INFO', URL_USER_DASHBOARD . '?view=page_info');
defined('URL_PAGE_INFO_API')   || define('URL_PAGE_INFO_API', SITEURL . '/src/pages/admin/page-information-list/index.php');

defined('PATH_PAGE_INFO_INDEX') || define('PATH_PAGE_INFO_INDEX', 'src/pages/admin/page-information-list/index.php');
?>
