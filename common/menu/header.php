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

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);

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
    createNavItem('我的', URL_PROFILE, true, 'fa-solid fa-user'),
];
?>
<header class="site-header">
    <div class="header-container">
        <a href="<?php echo URL_HOME; ?>" class="logo">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?php echo URL_ASSETS . '/uploads/settings/' . $siteLogo; ?>" 
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

            <a href="<?php echo $isLoggedIn ? URL_USER_DASHBOARD : URL_LOGIN; ?>" class="author-link">
                作者专区
            </a>

            <?php if (!$isLoggedIn): ?>
                <div class="auth-buttons">
                    <a href="<?php echo URL_LOGIN; ?>" class="login-btn">登录</a>
                    <span class="divider">|</span>
                    <a href="<?php echo URL_REGISTER; ?>" class="register-btn">注册</a>
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

<script src="<?php echo URL_ASSETS; ?>/js/header-script.js"></script>