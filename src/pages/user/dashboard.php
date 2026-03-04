<?php
require_once dirname(__DIR__, 3) . '/common.php';
// Auth Check
requireLogin();

$currentUserId = sessionInt('user_id');
$userTable = USR_LOGIN;
$dashTable = USR_DASHBOARD;
$auditPage = 'User Dashboard';

// --- LEGACY VIEW REDIRECTS ---
// Redirect old dashboard.php?view=X URLs to their new standalone pages
$currentView = input('view') ?: '';

$legacyRedirects = [
    'profile'       => URL_PROFILE,
    'tags'          => URL_NOVEL_TAGS,
    'tag_form'      => URL_NOVEL_TAGS_FORM,
    'categories'    => URL_NOVEL_CATS,
    'cat_form'      => URL_NOVEL_CATS_FORM,
    'meta_settings' => URL_META_SETTINGS,
    'web_settings'  => URL_WEB_SETTINGS,
    'admin'         => URL_ADMIN_DASHBOARD,
    'page_action'   => URL_PAGE_ACTION,
    'page_info'     => URL_PAGE_INFO,
    'user_role'     => URL_USER_ROLE,
];

if ($currentView !== '' && $currentView !== 'home' && isset($legacyRedirects[$currentView])) {
    $redirectUrl = $legacyRedirects[$currentView];
    // Preserve extra query params (except 'view')
    $extraParams = $_GET;
    unset($extraParams['view']);
    if (!empty($extraParams)) {
        $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
        $redirectUrl .= $separator . http_build_query($extraParams);
    }
    header('Location: ' . $redirectUrl);
    exit();
}

// --- DASHBOARD HOME ---
$currentUrl = parse_url(URL_USER_DASHBOARD, PHP_URL_PATH) ?: '/dashboard.php';
$permDashboard = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $permDashboard, $currentUrl);
checkPermissionError('view', $permDashboard);

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
if (function_exists('logAudit')) {
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
$rawName   = $userRow['name'] ?? session('user_name');
$rawLevel  = 'Lv' . ($dashRow['level'] ?? 1);

$statsArray = [
    ['label' => '关注', 'value' => intval($dashRow['following_count'] ?? 0)],
    ['label' => '粉丝', 'value' => intval($dashRow['followers_count'] ?? 0)]
];

$profileComponents = [
    ['type' => 'avatar', 'url' => URL_PROFILE, 'src' => $rawAvatar],
    ['type' => 'info', 'url' => URL_PROFILE, 'name' => $rawName, 'level' => $rawLevel, 'stats' => $statsArray]
];

$quickActions = [
    ['label' => '浏览历史', 'url' => URL_USER_HISTORY,     'icon' => 'fa-solid fa-clock-rotate-left',    'style' => ''],
    ['label' => '我的消息', 'url' => URL_USER_MESSAGES,    'icon' => 'fa-solid fa-comment-dots',         'style' => ''],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-feather-pointed',      'style' => '']
];

$customCSS[] = 'src/pages/user/css/dashboard.css';

$pageMetaKey = $currentUrl;
?>

<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="dashboard-container">
    <main class="dashboard-main">
        <div class="mb-3">
            <?php echo generateBreadcrumb($conn, $currentUrl); ?>
        </div>
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

            <a href="<?php echo URL_PROFILE; ?>" class="settings-btn">
                <i class="fa-solid fa-gear desktop-icon"></i>
                <i class="fa-solid fa-chevron-right mobile-icon"></i>
            </a>
        </div>

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
    </main>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/auth.js"></script>
</body>
</html>