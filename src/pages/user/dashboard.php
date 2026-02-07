<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . 'config/urls.php'; 
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$currentUserId = $_SESSION['user_id'];

$userTable = USR_LOGIN;
$dashTable = USR_DASHBOARD;
$auditPage = 'User Dashboard';

// Determine which section to show in the main area
$currentView     = isset($_GET['view']) ? $_GET['view'] : 'home';
$isTagListView   = ($currentView === 'tags');
$isTagFormView   = ($currentView === 'tag_form');
$isTagSection    = $isTagListView || $isTagFormView;

$userQuery = "SELECT name FROM " . $userTable . " WHERE id = ? LIMIT 1";
$dashQuery = "SELECT avatar, level, following_count, followers_count FROM " . $dashTable . " WHERE user_id = ? LIMIT 1";

// --- DATA FETCHING (COMPATIBILITY FIXED) ---

// Query 1: Basic Info
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $currentUserId);
$userStmt->execute();

$userRow = [];
$meta = $userStmt->result_metadata();
$row = []; $params = [];
while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
call_user_func_array(array($userStmt, 'bind_result'), $params);
if ($userStmt->fetch()) {
    foreach($row as $key => $val) { $userRow[$key] = $val; }
}
$userStmt->close();

// Query 2: Dashboard Stats
$dashStmt = $conn->prepare($dashQuery);
$dashStmt->bind_param("i", $currentUserId);
$dashStmt->execute();

$dashRow = [];
$meta = $dashStmt->result_metadata();
$row = []; $params = [];
while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
call_user_func_array(array($dashStmt, 'bind_result'), $params);
if ($dashStmt->fetch()) {
    foreach($row as $key => $val) { $dashRow[$key] = $val; }
}
$dashStmt->close();

// Audit Log: View Dashboard
if (function_exists('logAudit')) {
    logAudit([
        'page' => $auditPage, 'action' => 'V',
        'action_message' => 'User viewed dashboard',
        'query' => $dashQuery, 'query_table' => $dashTable,      
        'user_id' => $currentUserId
    ]);
}

// --- DATA PREPARATION ---
$rawAvatar = !empty($dashRow['avatar']) ? URL_ASSETS . '/uploads/avatars/' . $dashRow['avatar'] : URL_ASSETS . '/images/default-avatar.png';
$rawName   = $userRow['name'] ?? $_SESSION['user_name'];
$rawLevel  = 'Lv' . ($dashRow['level'] ?? 1);

$statsArray = [
    ['label' => '关注', 'value' => intval($dashRow['following_count'] ?? 0)],
    ['label' => '粉丝', 'value' => intval($dashRow['followers_count'] ?? 0)]
];

$profileComponents = [
    [
        'type' => 'avatar',
        'url'  => URL_PROFILE,
        'src'  => $rawAvatar
    ],
    [
        'type'  => 'info',
        'url'   => URL_PROFILE,
        'name'  => $rawName,
        'level' => $rawLevel,
        'stats' => $statsArray 
    ]
];

$sidebarItems = [
    ['label' => '首页',     'url' => URL_USER_DASHBOARD, 'icon' => 'fa-solid fa-house-user', 'active' => !$isTagSection],
    ['label' => '账号中心', 'url' => URL_HOME,           'icon' => 'fa-solid fa-id-card',   'active' => false],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-pen-nib',  'active' => false],
    ['label' => '小说标签', 'url' => URL_NOVEL_TAGS,     'icon' => 'fa-solid fa-tags',      'active' => $isTagSection]
];

$quickActions = [
    ['label' => '浏览历史', 'url' => URL_USER_HISTORY,     'icon' => 'fa-solid fa-clock-rotate-left',    'style' => ''],
    ['label' => '我的消息', 'url' => URL_USER_MESSAGES,    'icon' => 'fa-solid fa-comment-dots',         'style' => ''],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-feather-pointed',      'style' => 'background: #eef2ff; color: #233dd2;']
];

$pageTitle = "个人中心 - " . WEBSITE_NAME;
$customCSS = $isTagSection ? ['dashboard.css', 'dataTables.bootstrap.min.css', 'tag.css'] : 'dashboard.css';
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
                <a href="<?php echo URL_LOGOUT; ?>" class="logout-btn" style="color: #d9534f;">
                    <i class="fa-solid fa-right-from-bracket"></i> 登出
                </a>
            </li>
        </ul>
    </aside>

    <main class="dashboard-main">
        
        <?php if (!$isTagSection): ?>
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

            <a href="<?php echo URL_USER_SETTING; ?>" class="settings-btn">
                <i class="fa-solid fa-gear desktop-icon"></i>
                <i class="fa-solid fa-chevron-right mobile-icon"></i>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isTagListView): ?>
            <div class="dashboard-tag-section">
                <?php
                $EMBED_TAGS_PAGE = true;
                require BASE_PATH . PATH_NOVEL_TAGS_INDEX;
                ?>
            </div>
        <?php elseif ($isTagFormView): ?>
            <div class="dashboard-tag-section">
                <?php
                $EMBED_TAG_FORM_PAGE = true;
                require BASE_PATH . PATH_NOVEL_TAGS_FORM;
                ?>
            </div>
        <?php else: ?>
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

<?php if ($isTagListView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/tag.js"></script>
<?php elseif ($isTagFormView): ?>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<?php else: ?>
    <script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<script src="<?php echo URL_ASSETS; ?>/js/login-script.js"></script>

</body>
</html>