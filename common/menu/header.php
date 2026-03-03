<?php
/**
 * common/menu/header.php
 * Responsive Navigation Bar UI Component
 */

// 1. Fetch Website Settings if not already loaded
if (!isset($webSettings)) {
    $webSettings = function_exists('getWebSettings') ? getWebSettings($conn) : null;
}

// Prepare display variables
$siteName = !empty($webSettings['website_name']) ? $webSettings['website_name'] : 'Logo';
$siteLogo = !empty($webSettings['website_logo']) ? $webSettings['website_logo'] : '';

$currentPage = getCurrentPage();
$requestPath = parse_url(getServer('REQUEST_URI'), PHP_URL_PATH);
$isLoggedIn = (hasSession('logged_in') && session('logged_in') === true);
$dashboardPath = parse_url(URL_USER_DASHBOARD, PHP_URL_PATH);
$isUserDashboardPage = ($dashboardPath && $requestPath === $dashboardPath);

$authorZoneUrl = URL_LOGIN;
$isAuthorPage = false;

if ($isLoggedIn) {
    $currentUserId = sessionInt('user_id');
    $sessionRoleId = sessionInt('role_id');
    $cacheTtlSeconds = 300;
    
    // 1. Check Session Cache to prevent DB hits on every page load
    $permCache = hasSession('header_author_route_cache') && is_array(session('header_author_route_cache'))
        ? session('header_author_route_cache')
        : [];

    $cacheValid = (
        isset($permCache['user_id'], $permCache['role_id'], $permCache['expires_at'], $permCache['url']) &&
        $permCache['user_id'] === $currentUserId &&
        $permCache['role_id'] === $sessionRoleId &&
        $permCache['expires_at'] > time()
    );

    if ($cacheValid) {
        $authorZoneUrl = $permCache['url'];
    } else {
        // 2. Not cached. Check Admin Rights first
        $permVerify = hasPagePermission($conn, '/author/author-verification.php');
        if (empty($permVerify) || empty($permVerify->view)) {
            $legacyPath = defined('PATH_AUTHOR_VERIFICATION_INDEX') ? ('/' . ltrim(PATH_AUTHOR_VERIFICATION_INDEX, '/')) : '/src/pages/author/author-verification/index.php';
            $permVerify = hasPagePermission($conn, $legacyPath);
        }

        if (!empty($permVerify) && !empty($permVerify->view)) {
            $authorZoneUrl = URL_AUTHOR_VERIFICATION; // Admins go to verification management
        } else {
            // 3. Check Author Profile Status
            $vStatus = function_exists('getAuthorProfileStatus') ? getAuthorProfileStatus($conn, $currentUserId) : false;
            
            if ($vStatus === 'approved') {
                $authorZoneUrl = URL_AUTHOR_DASHBOARD; // Verified authors go to dashboard
            } else {
                $authorZoneUrl = URL_AUTHOR_REGISTER; // Pending/Rejected/New authors go to registration status
            }
        }

        // 4. Update Cache
        setSession('header_author_route_cache', [
            'user_id' => $currentUserId,
            'role_id' => $sessionRoleId,
            'expires_at' => time() + $cacheTtlSeconds,
            'url' => $authorZoneUrl
        ]);
    }

    // Active State Check for the Navigation Item
    $authorVerificationPath = parse_url(URL_AUTHOR_VERIFICATION, PHP_URL_PATH);
    $emailTemplatePath = parse_url(URL_EMAIL_TEMPLATE, PHP_URL_PATH);
    $authorRegisterPath = parse_url(URL_AUTHOR_REGISTER, PHP_URL_PATH);
    $authorDashboardPath = parse_url(URL_AUTHOR_DASHBOARD, PHP_URL_PATH);
    $authorNovelMgmtPath = defined('URL_AUTHOR_NOVEL_MANAGEMENT') ? parse_url(URL_AUTHOR_NOVEL_MANAGEMENT, PHP_URL_PATH) : '';

    $isAuthorPage = (
        ($authorRegisterPath && $requestPath === $authorRegisterPath) ||
        ($authorDashboardPath && $requestPath === $authorDashboardPath) ||
        ($authorVerificationPath && $requestPath === $authorVerificationPath) ||
        ($emailTemplatePath && $requestPath === $emailTemplatePath) ||
        ($authorNovelMgmtPath && strpos($requestPath, $authorNovelMgmtPath) === 0) ||
        (isset($isAuthorDashboard) && $isAuthorDashboard === true)
    );
}

// Helper to create navigation items with login logic
function createNavItem($title, $url, $mobile = false, $icon = null) {
    global $isLoggedIn;
    $loginRequiredUrls = [URL_BOOKSHELF, URL_PROFILE];
    $finalUrl = in_array($url, $loginRequiredUrls) 
        ? ($isLoggedIn ? $url : URL_LOGIN) 
        : $url;
    
    return [
        'title'  => $title,
        'url'    => $finalUrl,
        'active' => $url,
        'icon'   => $icon,
        'mobile' => $mobile
    ];
}

$navLinks = [
    createNavItem('首页', URL_HOME, true, 'fa-solid fa-house'),
    createNavItem('分类', URL_CATEGORIES),
    createNavItem('排行榜', URL_RANKING),
    createNavItem('书架', URL_BOOKSHELF, true, 'fa-solid fa-book-open'),
];
?>
<header class="site-header">
    <div class="header-container">
        <a href="<?php echo URL_HOME; ?>" class="logo">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?php echo htmlspecialchars(URL_ASSETS . '/uploads/settings/' . $siteLogo, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($siteName); ?>" 
                     style="height: 40px; width: auto; object-fit: contain;">
            <?php else: ?>
                <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
            <?php endif; ?>
        </a>

        <nav class="desktop-nav">
            <div class="nav-links-group">
                <?php foreach ($navLinks as $link): ?>
                    <a href="<?php echo $link['url']; ?>" 
                       class="nav-link <?php echo ($currentPage == basename($link['active'])) ? 'active' : ''; ?>">
                       <?php echo $link['title']; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form action="<?php echo URL_SEARCH; ?>" method="GET" class="search-form">
                <input type="text" name="q" placeholder="请输入书籍名或作者名">
                <button type="submit"><i class="fa-solid fa-search"></i></button>
            </form>

            <a href="<?php echo $authorZoneUrl; ?>" 
            class="author-link <?php echo $isAuthorPage ? 'active' : ''; ?>">
            作者专区
            </a>

            <?php if (!$isLoggedIn): ?>
                <div class="auth-buttons">
                    <a href="<?php echo URL_LOGIN; ?>" class="login-btn">登录</a>
                    <span class="divider">|</span>
                    <a href="<?php echo URL_REGISTER; ?>" class="register-btn">注册</a>
                </div>
            <?php else: ?>
                <div class="auth-buttons">
                    <a href="<?php echo URL_USER_DASHBOARD; ?>" class="user-name-link <?php echo $isUserDashboardPage ? 'active' : ''; ?>" title="进入个人后台">
                        <i class="fa-solid fa-circle-user"></i>
                        <span class="user-name-text">
                            <?php echo htmlspecialchars(session('user_name') ?: '用户'); ?>
                        </span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>

        <?php if (!$isLoggedIn): ?>
            <div class="mobile-top-bar">
                <div class="user-trigger" onclick="toggleMobileDropdown()">
                    <i class="fa-solid fa-circle-user"></i>
                </div>
                <div id="mobileAuthDropdown" class="dropdown-menu-custom">
                    <a href="<?php echo URL_LOGIN; ?>">登录</a>
                    <a href="<?php echo URL_REGISTER; ?>">注册</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>

<nav class="mobile-bottom-nav">
    <?php foreach ($navLinks as $link): ?>
        <?php if ($link['mobile']): ?>
            <a href="<?php echo $link['url']; ?>" 
               class="bottom-item <?php echo ($currentPage == basename($link['active'])) ? 'active' : ''; ?>">
                <i class="<?php echo $link['icon']; ?>"></i>
                <span><?php echo $link['title']; ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php require_once BASE_PATH . 'common/menu/sidebar.php'; ?>

<script src="<?php echo URL_ASSETS; ?>/js/header-script.js"></script>