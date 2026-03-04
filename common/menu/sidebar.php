<?php
/**
 * common/menu/sidebar.php
 * Sidebar Navigation Component - Rendered for logged-in users only
 * Included by common/menu/header.php
 */

// Guard: only render when user is logged in
if (!isset($isLoggedIn)) {
    $isLoggedIn = (hasSession('logged_in') && session('logged_in') === true);
}
if (!$isLoggedIn) return;

// --- Permission checks (keys match PAGE_INFORMATION_LIST.public_url in DB) ---
$_sp = [];
$_dashboardPath = parse_url(URL_USER_DASHBOARD, PHP_URL_PATH) ?: '/dashboard.php';
$_profilePath = parse_url(URL_PROFILE, PHP_URL_PATH) ?: '/profile.php';
$_tagsPath = parse_url(URL_NOVEL_TAGS, PHP_URL_PATH) ?: '/tags.php';
$_catsPath = parse_url(URL_NOVEL_CATS, PHP_URL_PATH) ?: '/category.php';
$_metaPath = parse_url(URL_META_SETTINGS, PHP_URL_PATH) ?: '/meta-setting.php';
$_webPath = parse_url(URL_WEB_SETTINGS, PHP_URL_PATH) ?: '/web-settings.php';
$_adminPath = parse_url(URL_ADMIN_DASHBOARD, PHP_URL_PATH) ?: '/admin/dashboard.php';

$_sp['dashboard']  = hasPagePermission($conn, $_dashboardPath);
$_sp['profile']    = hasPagePermission($conn, $_profilePath);
$_sp['tags']       = hasPagePermission($conn, $_tagsPath);
$_sp['categories'] = hasPagePermission($conn, $_catsPath);
$_sp['meta']       = hasPagePermission($conn, $_metaPath);
$_sp['web']        = hasPagePermission($conn, $_webPath);
$_sp['admin']      = hasPagePermission($conn, $_adminPath);

// --- Active state detection based on current URL ---
$_sbPath  = parse_url(getServer('REQUEST_URI'), PHP_URL_PATH);

$_isTagsActive    = ($_sbPath === $_tagsPath || $_sbPath === '/tags/' || $_sbPath === '/tags.php');
$_isCatsActive    = ($_sbPath === $_catsPath || $_sbPath === '/category/' || $_sbPath === '/category.php');
$_isProfileActive = ($_sbPath === $_profilePath || $_sbPath === '/profile/' || $_sbPath === '/profile.php');
$_isMetaActive    = ($_sbPath === $_metaPath || $_sbPath === '/meta-setting/' || $_sbPath === '/meta-setting.php');
$_isWebActive     = ($_sbPath === $_webPath || $_sbPath === '/web-settings/' || $_sbPath === '/web-settings.php');
$_isAdminActive   = (strpos($_sbPath, '/admin/') === 0);
$_authorDashPath  = parse_url(URL_AUTHOR_DASHBOARD, PHP_URL_PATH) ?: '/author/dashboard.php';
$_authorNovelPath = defined('URL_AUTHOR_NOVEL_MANAGEMENT') ? (parse_url(URL_AUTHOR_NOVEL_MANAGEMENT, PHP_URL_PATH) ?: '/author/novel-management') : '/author/novel-management';
$_isChapterPath   = (bool) preg_match('#^/author/novel/[^/]+/chapters/?$#', (string)$_sbPath)
                    || (strpos((string)$_sbPath, '/author/chapter-management') === 0)
                    || (strpos((string)$_sbPath, '/src/pages/author/chapter-management/') === 0);
$_isWriteNovelActive = ($_sbPath === $_authorDashPath)
                    || (strpos((string)$_sbPath, (string)$_authorNovelPath) === 0)
                    || $_isChapterPath;
$_isDashActive    = ($_sbPath === $_dashboardPath)
                    && !$_isTagsActive && !$_isCatsActive && !$_isProfileActive
                    && !$_isMetaActive && !$_isWebActive && !$_isAdminActive
                    && !$_isWriteNovelActive;

// --- Sidebar items ---
$_sidebarItems = [
    ['label' => '首页',     'url' => URL_HOME,              'icon' => 'fa-solid fa-house-user',    'active' => false,           'permission' => !empty($_sp['dashboard']->view)],
    ['label' => '账号中心', 'url' => URL_USER_DASHBOARD,    'icon' => 'fa-solid fa-id-card',       'active' => $_isDashActive,  'permission' => !empty($_sp['profile']->view)],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD,  'icon' => 'fa-solid fa-pen-nib',       'active' => $_isWriteNovelActive],
    ['label' => '小说分类', 'url' => URL_NOVEL_CATS,        'icon' => 'fa-solid fa-layer-group',   'active' => $_isCatsActive,  'permission' => !empty($_sp['categories']->view)],
    ['label' => '小说标签', 'url' => URL_NOVEL_TAGS,        'icon' => 'fa-solid fa-tags',          'active' => $_isTagsActive,  'permission' => !empty($_sp['tags']->view)],
    ['label' => 'META 设置','url' => URL_META_SETTINGS,     'icon' => 'fa-solid fa-sliders',       'active' => $_isMetaActive,  'permission' => !empty($_sp['meta']->view)],
    ['label' => '网站设置', 'url' => URL_WEB_SETTINGS,      'icon' => 'fa-solid fa-paintbrush',    'active' => $_isWebActive,   'permission' => !empty($_sp['web']->view)],
    ['label' => '管理员',   'url' => URL_ADMIN_DASHBOARD,   'icon' => 'fa-solid fa-user-shield',   'active' => $_isAdminActive, 'permission' => !empty($_sp['admin']->view)],
];
?>
<aside class="dashboard-sidebar" id="dashboardSidebar">
    <ul class="sidebar-menu">
        <?php foreach ($_sidebarItems as $item): ?>
            <?php if (!isset($item['permission']) || $item['permission']): ?>
            <li>
                <a href="<?php echo $item['url']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                    <i class="<?php echo $item['icon']; ?>"></i> <?php echo $item['label']; ?>
                </a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="sidebar-logout-item">
            <a href="<?php echo URL_LOGOUT; ?>" class="logout-btn" data-api-url="<?php echo URL_LOGOUT; ?>">
                <i class="fa-solid fa-right-from-bracket"></i> 登出
            </a>
        </li>
    </ul>
</aside>
<script src="<?php echo URL_ASSETS; ?>/js/sidebar.js"></script>
