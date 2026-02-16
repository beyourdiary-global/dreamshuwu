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
    ['label' => '首页',     'url' => URL_USER_DASHBOARD, 'icon' => 'fa-solid fa-house-user', 'active' => (!$isTagSection && !$isCatSection && !$isProfileView && !$isMetaView && !$isWebSettingView && !$isAdminSection)],
    ['label' => '账号中心', 'url' => URL_HOME,           'icon' => 'fa-solid fa-id-card',   'active' => false],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-pen-nib',  'active' => false],
    ['label' => '小说分类', 'url' => URL_NOVEL_CATS,     'icon' => 'fa-solid fa-layer-group','active' => $isCatSection],
    ['label' => '小说标签', 'url' => URL_NOVEL_TAGS,     'icon' => 'fa-solid fa-tags',      'active' => $isTagSection],
    ['label' => 'META 设置',  'url' => URL_USER_DASHBOARD . '?view=meta_settings', 'icon' => 'fa-solid fa-sliders', 'active' => $isMetaView],
    ['label' => '网站设置',   'url' => URL_USER_DASHBOARD . '?view=web_settings', 'icon' => 'fa-solid fa-paintbrush', 'active' => $isWebSettingView],
    
    // [UPDATED] Admin Dashboard Link
    ['label' => '管理员',     'url' => URL_ADMIN_DASHBOARD, 'icon' => 'fa-solid fa-user-shield', 'active' => $isAdminSection]
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
    case 'categories': $pageMetaKey = 'categories'; break;
    case 'cat_form':   $pageMetaKey = 'category_form'; break;
    case 'tags':       $pageMetaKey = 'tags'; break;
    case 'tag_form':   $pageMetaKey = 'tag_form'; break;
    case 'profile':    $pageMetaKey = 'profile'; break;
    case 'meta_settings': $pageMetaKey = 'meta_settings'; break;
    case 'web_settings':  $pageMetaKey = 'web_settings'; break;
    case 'admin':         $pageMetaKey = 'admin'; break;
    case 'page_action':   $pageMetaKey = 'page_action'; break; // [NEW]
    case 'page_info':     $pageMetaKey = 'page_information_list'; break; // [NEW]
    case 'user_role':     $pageMetaKey = 'user_role'; break; // [NEW]
    default:              $pageMetaKey = 'dashboard'; break;
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
                <li>
                    <a href="<?php echo $item['url']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i> <?php echo $item['label']; ?>
                    </a>
                </li>
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
<script src="<?php echo URL_ASSETS; ?>/js/login-script.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/logout-handler.js"></script>
</body>
</html>