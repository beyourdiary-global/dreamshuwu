<?php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$currentUserId = $_SESSION['user_id'];
$userTable = USR_LOGIN;
$dashTable = USR_DASHBOARD;
$auditPage = 'User Dashboard';

// --- VIEW LOGIC ---
$currentView     = isset($_GET['view']) ? $_GET['view'] : 'home';

// Define View States
$isTagListView    = ($currentView === 'tags');
$isTagFormView    = ($currentView === 'tag_form');
$isTagSection     = $isTagListView || $isTagFormView;

$isCatListView    = ($currentView === 'categories');
$isCatFormView    = ($currentView === 'cat_form');
$isCatSection     = $isCatListView || $isCatFormView;

$isProfileView    = ($currentView === 'profile');
$isMetaView       = ($currentView === 'meta_settings');
$isWebSettingView = ($currentView === 'web_settings');

// [NEW] Admin States
$isAdminHome      = ($currentView === 'admin');
$isPageActionView = ($currentView === 'page_action');
$isPageInfoView   = ($currentView === 'page_info'); // [NEW]
$isUserRoleView   = ($currentView === 'user_role'); // [NEW]
$isAdminSection   = ($isAdminHome || $isPageActionView || $isPageInfoView || $isUserRoleView);

$permDashboard  = hasPagePermission($conn, '/dashboard.php');
$permProfile    = hasPagePermission($conn, '/dashboard.php?view=profile');
$permTags       = hasPagePermission($conn, '/dashboard.php?view=tags');
$permTagForm    = hasPagePermission($conn, '/dashboard.php?view=tag_form');
$permCategories = hasPagePermission($conn, '/dashboard.php?view=categories');
$permCatForm    = hasPagePermission($conn, '/dashboard.php?view=cat_form');
$permMeta       = hasPagePermission($conn, '/dashboard.php?view=meta_settings');
$permWeb        = hasPagePermission($conn, '/dashboard.php?view=web_settings');
$permAdmin      = hasPagePermission($conn, '/dashboard.php?view=admin');
$permPageAction = hasPagePermission($conn, '/dashboard.php?view=page_action');
$permPageInfo   = hasPagePermission($conn, '/dashboard.php?view=page_info');
$permUserRole   = hasPagePermission($conn, '/dashboard.php?view=user_role');

if ($isTagListView && (empty($permTags) || empty($permTags->view))) denyAccess("权限不足：您没有访问小说标签页面的权限。");
if ($isTagFormView && (empty($permTagForm) || empty($permTagForm->view))) denyAccess("权限不足：您没有访问标签表单页面的权限。");
if ($isCatListView && (empty($permCategories) || empty($permCategories->view))) denyAccess("权限不足：您没有访问小说分类页面的权限。");
if ($isCatFormView && (empty($permCatForm) || empty($permCatForm->view))) denyAccess("权限不足：您没有访问分类表单页面的权限。");
if ($isProfileView && (empty($permProfile) || empty($permProfile->view))) denyAccess("权限不足：您没有访问账号中心页面的权限。");
if ($isMetaView && (empty($permMeta) || empty($permMeta->view))) denyAccess("权限不足：您没有访问 Meta 设置页面的权限。");
if ($isWebSettingView && (empty($permWeb) || empty($permWeb->view))) denyAccess("权限不足：您没有访问网站设置页面的权限。");
if ($isAdminHome && (empty($permAdmin) || empty($permAdmin->view))) denyAccess("权限不足：您没有访问管理员面板的权限。");
if ($isPageActionView && (empty($permPageAction) || empty($permPageAction->view))) denyAccess("权限不足：您没有访问页面操作管理的权限。");
if ($isPageInfoView && (empty($permPageInfo) || empty($permPageInfo->view))) denyAccess("权限不足：您没有访问页面信息列表的权限。");
if ($isUserRoleView && (empty($permUserRole) || empty($permUserRole->view))) denyAccess("权限不足：您没有访问用户角色管理的权限。");
if (!$isTagSection && !$isCatSection && !$isProfileView && !$isMetaView && !$isWebSettingView && !$isAdminSection && (empty($permDashboard) || empty($permDashboard->view))) {
    denyAccess("权限不足：您没有访问仪表盘首页的权限。");
}

// Data Fetching
$userQuery = "SELECT name FROM " . $userTable . " WHERE id = ? LIMIT 1";
$dashQuery = "SELECT avatar, level, following_count, followers_count FROM " . $dashTable . " WHERE user_id = ? LIMIT 1";

$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $currentUserId);
$userStmt->execute();
$userRow = []; $meta = $userStmt->result_metadata(); $row = []; $params = [];
while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
call_user_func_array(array($userStmt, 'bind_result'), $params);
if ($userStmt->fetch()) { foreach($row as $key => $val) { $userRow[$key] = $val; } }
$userStmt->close();

$dashStmt = $conn->prepare($dashQuery);
$dashStmt->bind_param("i", $currentUserId);
$dashStmt->execute();
$dashRow = []; $meta = $dashStmt->result_metadata(); $row = []; $params = [];
while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
call_user_func_array(array($dashStmt, 'bind_result'), $params);
if ($dashStmt->fetch()) { foreach($row as $key => $val) { $dashRow[$key] = $val; } }
$dashStmt->close();

// Audit Logging
if (!$isTagSection && !$isCatSection && !$isMetaView && !$isProfileView && !$isWebSettingView && !$isAdminSection && !$isUserRoleView && function_exists('logAudit')) {
    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => 'User viewed dashboard',
        'query'          => $dashQuery,
        'query_table'    => $dashTable,
        'user_id'        => $currentUserId
    ]);
}

// Data Prep
$rawAvatar = !empty($dashRow['avatar']) ? URL_ASSETS . '/uploads/avatars/' . $dashRow['avatar'] : URL_ASSETS . '/images/default-avatar.png';
$rawName   = $userRow['name'] ?? $_SESSION['user_name'];
$rawLevel  = 'Lv' . ($dashRow['level'] ?? 1);

$statsArray = [
    ['label' => '关注', 'value' => intval($dashRow['following_count'] ?? 0)],
    ['label' => '粉丝', 'value' => intval($dashRow['followers_count'] ?? 0)]
];

$profileComponents = [
    ['type' => 'avatar', 'url' => URL_USER_DASHBOARD . '?view=profile', 'src' => $rawAvatar],
    ['type' => 'info', 'url' => URL_USER_DASHBOARD . '?view=profile', 'name' => $rawName, 'level' => $rawLevel, 'stats' => $statsArray]
];

// --- SIDEBAR ITEMS ---
$sidebarItems = [
    ['label' => '首页',     'url' => URL_USER_DASHBOARD, 'icon' => 'fa-solid fa-house-user', 'active' => (!$isTagSection && !$isCatSection && !$isProfileView && !$isMetaView && !$isWebSettingView && !$isAdminSection), 'permission' => !empty($permDashboard->view)],
    ['label' => '账号中心', 'url' => URL_HOME,           'icon' => 'fa-solid fa-id-card',   'active' => false, 'permission' => !empty($permProfile->view)],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-pen-nib',  'active' => false],
    ['label' => '小说分类', 'url' => URL_NOVEL_CATS,     'icon' => 'fa-solid fa-layer-group','active' => $isCatSection, 'permission' => !empty($permCategories->view)],
    ['label' => '小说标签', 'url' => URL_NOVEL_TAGS,     'icon' => 'fa-solid fa-tags',      'active' => $isTagSection, 'permission' => !empty($permTags->view)],
    ['label' => 'META 设置',  'url' => URL_USER_DASHBOARD . '?view=meta_settings', 'icon' => 'fa-solid fa-sliders', 'active' => $isMetaView, 'permission' => !empty($permMeta->view)],
    ['label' => '网站设置',   'url' => URL_USER_DASHBOARD . '?view=web_settings', 'icon' => 'fa-solid fa-paintbrush', 'active' => $isWebSettingView, 'permission' => !empty($permWeb->view)],
    
    // [UPDATED] Admin Dashboard Link
    ['label' => '管理员',     'url' => URL_ADMIN_DASHBOARD, 'icon' => 'fa-solid fa-user-shield', 'active' => $isAdminSection, 'permission' => !empty($permAdmin->view)]
];

$quickActions = [
    ['label' => '浏览历史', 'url' => URL_USER_HISTORY,     'icon' => 'fa-solid fa-clock-rotate-left',    'style' => ''],
    ['label' => '我的消息', 'url' => URL_USER_MESSAGES,    'icon' => 'fa-solid fa-comment-dots',         'style' => ''],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-feather-pointed',      'style' => '']
];

if ($isTagListView || $isCatListView) $customCSS[] = 'dataTables.bootstrap.min.css';
if ($isMetaView) $customCSS[] = 'meta.css';
$customCSS[] = 'dashboard.css';

// Page Meta Key Setting
switch ($currentView) {
    case 'categories': $pageMetaKey = '/dashboard.php?view=categories'; break;
    case 'cat_form':   $pageMetaKey = '/dashboard.php?view=cat_form'; break;
    case 'tags':       $pageMetaKey = '/dashboard.php?view=tags'; break;
    case 'tag_form':   $pageMetaKey = 'dashboard.php?view=tag_form'; break;
    case 'profile':    $pageMetaKey = '/dashboard.php?view=profile'; break;
    case 'meta_settings': $pageMetaKey = '/dashboard.php?view=meta_settings'; break;
    case 'web_settings':  $pageMetaKey = '/dashboard.php?view=web_settings'; break;
    case 'admin':         $pageMetaKey = '/dashboard.php?view=admin'; break;
    case 'page_action':   $pageMetaKey = '/dashboard.php?view=page_action'; break; // [NEW]
    case 'page_info':     $pageMetaKey = '/dashboard.php?view=page_info'; break; // [NEW]
    case 'user_role':     $pageMetaKey = '/dashboard.php?view=user_role'; break; // [NEW]
    default:              $pageMetaKey = '/dashboard.php'; break;
}
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="dashboard-container">
    <aside class="dashboard-sidebar">
        <ul class="sidebar-menu">
            <?php foreach ($sidebarItems as $item): ?>
                <?php if (!isset($item['permission']) || $item['permission']): ?>
                <li>
                    <a href="<?php echo $item['url']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i> <?php echo $item['label']; ?>
                    </a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <li style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
            <a href="<?php echo URL_LOGOUT; ?>" class="logout-btn" style="color: #d9534f;" data-api-url="<?php echo URL_LOGOUT; ?>"> 
                <i class="fa-solid fa-right-from-bracket"></i> 登出
             </a>
            </li>
        </ul>
    </aside>

    <main class="dashboard-main">
        <?php if (!$isTagSection && !$isCatSection && !$isProfileView && !$isMetaView && !$isWebSettingView && !$isAdminSection): ?>
        <div class="profile-card">
            <?php foreach ($profileComponents as $component): ?>
                <?php if ($component['type'] === 'avatar'): ?>
                    <a href="<?php echo $component['url']; ?>" style="text-decoration:none; display:block;">
                        <img src="<?php echo htmlspecialchars($component['src']); ?>" alt="Avatar" class="profile-avatar">
                    </a>
                <?php elseif ($component['type'] === 'info'): ?>
                    <div class="profile-info">
                        <h2>
                            <a href="<?php echo $component['url']; ?>" style="color:white; text-decoration:none;">
                                <?php echo htmlspecialchars($component['name']); ?>
                            </a>
                            <span class="level-badge"><?php echo htmlspecialchars($component['level']); ?></span>
                        </h2>
                        <div class="profile-stats">
                            <?php foreach ($component['stats'] as $stat): ?>
                                <span><?php echo $stat['label']; ?> <strong><?php echo $stat['value']; ?></strong></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <a href="<?php echo URL_USER_DASHBOARD; ?>?view=profile" class="settings-btn">
                <i class="fa-solid fa-gear desktop-icon"></i>
                <i class="fa-solid fa-chevron-right mobile-icon"></i>
            </a>
        </div>
        <?php endif; ?>

        <?php 
        if ($isProfileView):
            if (!defined('PROFILE_EMBEDDED')) define('PROFILE_EMBEDDED', true);
            require BASE_PATH . PATH_PROFILE;

        elseif ($isTagListView):
            $EMBED_TAGS_PAGE = true;
            require BASE_PATH . PATH_NOVEL_TAGS_INDEX;
        
        elseif ($isTagFormView):
            $EMBED_TAG_FORM_PAGE = true;
            require BASE_PATH . PATH_NOVEL_TAGS_FORM;

        elseif ($isCatListView):
            $EMBED_CATS_PAGE = true;
            require BASE_PATH . PATH_NOVEL_CATS_INDEX;
        
        elseif ($isCatFormView):
            $EMBED_CAT_FORM_PAGE = true;
            require BASE_PATH . PATH_NOVEL_CATS_FORM;
        
        elseif ($isMetaView):
            $EMBED_META_PAGE = true;
            require BASE_PATH . PATH_META_SETTINGS;
        
        elseif ($isWebSettingView):
            $EMBED_WEB_SETTING_PAGE = true;
            require BASE_PATH . PATH_WEB_SETTINGS;

        // [NEW] Embedded Admin View
        elseif ($isAdminHome):
            $EMBED_ADMIN_PAGE = true;
            require BASE_PATH . PATH_ADMIN_INDEX;

        // [NEW] Embedded Page Action Feature
        elseif ($isPageActionView):
            $EMBED_PAGE_ACTION = true;
            require BASE_PATH . PATH_PAGE_ACTION;

        // [NEW] Page Information List Feature
        elseif ($isPageInfoView):
            $EMBED_PAGE_INFO = true;
            require BASE_PATH . PATH_PAGE_INFO_INDEX;

        // [NEW] User Role Management Feature
        elseif ($isUserRoleView):
            $EMBED_USER_ROLE = true;
            require BASE_PATH . PATH_USER_ROLE_INDEX;
        
        else: ?>
            <div class="quick-actions-grid">
                <?php foreach ($quickActions as $action): ?>
                    <a href="<?php echo $action['url']; ?>" class="action-card">
                        <div class="action-icon-wrapper" style="<?php echo $action['style']; ?>">
                            <i class="<?php echo $action['icon']; ?>"></i>
                        </div>
                        <h4><?php echo $action['label']; ?></h4>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>

<?php if ($isProfileView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/user-profile.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/user-profile.js'); ?>"></script>
<?php endif; ?>

<?php if ($isTagListView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/tag.js"></script>
<?php elseif ($isCatListView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/category.js"></script>
<?php elseif ($isMetaView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/meta.js"></script>
<?php elseif ($isAdminHome || $isPageActionView || $isPageInfoView || $isUserRoleView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/admin.js"></script>
<?php endif; ?>
<script src="<?php echo URL_ASSETS; ?>/js/auth.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/logout-handler.js"></script>
</body>
</html>