<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . 'config/urls.php'; 
require_once BASE_PATH . 'functions.php';

// 1. Auth Check: Redirect if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$currentUserId = $_SESSION['user_id'];

// 2. Fetch User Stats from Database
$query = "SELECT u.name, d.avatar, d.level, d.following_count, d.followers_count 
          FROM " . USR_LOGIN . " u 
          LEFT JOIN " . USR_DASHBOARD . " d ON u.id = d.user_id 
          WHERE u.id = ? LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

// 3. Prepare Display Data
$userStats = [
    'name'      => $userData['name'] ?? $_SESSION['user_name'],
    'following' => $userData['following_count'] ?? 0,
    'followers' => $userData['followers_count'] ?? 0,
    'level'     => 'Lv' . ($userData['level'] ?? 1),
    'avatar'    => !empty($userData['avatar']) 
                   ? URL_ASSETS . '/uploads/avatars/' . $userData['avatar'] 
                   : URL_ASSETS . '/images/default-avatar.png'
];

$pageTitle = "个人中心 - " . WEBSITE_NAME;
$customCSS = "dashboard.css"; 
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>

<div class="dashboard-container">
    
    <aside class="dashboard-sidebar">
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo URL_USER_DASHBOARD; ?>" class="active">
                    <i class="fa-solid fa-house-user"></i> 首页
                </a>
            </li>
            <li>
                <a href="<?php echo URL_HOME; ?>"> 
                    <i class="fa-solid fa-id-card"></i> 账号中心
                </a>
            </li>
            <li>
                <a href="<?php echo URL_AUTHOR_DASHBOARD; ?>">
                    <i class="fa-solid fa-pen-nib"></i> 写小说
                </a>
            </li>
            <li style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
                <a href="<?php echo URL_LOGOUT; ?>" style="color: #d9534f;">
                    <i class="fa-solid fa-right-from-bracket"></i> 登出
                </a>
            </li>
        </ul>
    </aside>

    <main class="dashboard-main">
        
        <div class="profile-card">
            
            <a href="<?php echo URL_PROFILE; ?>" style="text-decoration:none; display:block;">
                <img src="<?php echo htmlspecialchars($userStats['avatar']); ?>" alt="Avatar" class="profile-avatar">
            </a>

            <div class="profile-info">
                <h2>
                    <a href="<?php echo URL_PROFILE; ?>" style="color:white; text-decoration:none;">
                        <?php echo htmlspecialchars($userStats['name']); ?>
                    </a>
                    <span class="level-badge"><?php echo htmlspecialchars($userStats['level']); ?></span>
                </h2>
                <div class="profile-stats">
                    <span>关注 <strong><?php echo intval($userStats['following']); ?></strong></span>
                    <span>粉丝 <strong><?php echo intval($userStats['followers']); ?></strong></span>
                </div>
            </div>

            <a href="<?php echo URL_USER_SETTING; ?>" class="settings-btn">
                <i class="fa-solid fa-gear desktop-icon"></i>
                <i class="fa-solid fa-chevron-right mobile-icon"></i>
            </a>
        </div>

        <div class="quick-actions-grid">
            
            <a href="<?php echo URL_USER_HISTORY; ?>" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h4>浏览历史</h4>
            </a>

            <a href="<?php echo URL_USER_MESSAGES; ?>" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <h4>我的消息</h4>
            </a>

            <a href="<?php echo URL_AUTHOR_DASHBOARD; ?>" class="action-card">
                <div class="action-icon-wrapper" style="background: #eef2ff; color: #233dd2;">
                    <i class="fa-solid fa-feather-pointed"></i>
                </div>
                <h4>写小说</h4>
            </a>
            
        </div>

    </main>
</div>

</body>
</html>