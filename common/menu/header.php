<?php
/**
 * common/menu/header.php
 * Responsive Navigation Bar UI Component
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);


?>
<header class="site-header">
    <div class="header-container">
        <a href="<?php echo URL_HOME; ?>" class="logo">
            <span class="logo-text">Logo</span>
        </a>

        <nav class="desktop-nav">
            
            <div class="nav-links-group">
                <a href="<?php echo URL_HOME; ?>" 
                   class="nav-link <?php echo ($currentPage == basename(URL_HOME)) ? 'active' : ''; ?>">
                   首页
                </a>
                
                <a href="<?php echo URL_CATEGORIES; ?>" 
                   class="nav-link <?php echo ($currentPage == basename(URL_CATEGORIES)) ? 'active' : ''; ?>">
                   分类
                </a>
                
                <a href="<?php echo URL_RANKING; ?>" 
                   class="nav-link <?php echo ($currentPage == basename(URL_RANKING)) ? 'active' : ''; ?>">
                   排行榜
                </a>
                
                <a href="<?php echo $isLoggedIn ? URL_BOOKSHELF : URL_LOGIN; ?>" 
                   class="nav-link <?php echo ($currentPage == basename(URL_BOOKSHELF)) ? 'active' : ''; ?>">
                   书架
                </a>
            </div>

            <form action="<?php echo URL_SEARCH; ?>" method="GET" class="search-form">
                <input type="text" name="q" placeholder="请输入书籍名或作者名">
                <button type="submit"><i class="fa-solid fa-search"></i></button>
            </form>

            <a href="<?php echo $isLoggedIn ? URL_AUTHOR_DASHBOARD : URL_LOGIN; ?>" class="author-link">
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
    <a href="<?php echo URL_HOME; ?>" class="bottom-item <?php echo ($currentPage == basename(URL_HOME)) ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i>
        <span>首页</span>
    </a>
    
    <a href="<?php echo $isLoggedIn ? URL_BOOKSHELF : URL_LOGIN; ?>" class="bottom-item <?php echo ($currentPage == basename(URL_BOOKSHELF)) ? 'active' : ''; ?>">
        <i class="fa-solid fa-book-open"></i>
        <span>书架</span>
    </a>
    
    <a href="<?php echo $isLoggedIn ? URL_PROFILE : URL_LOGIN; ?>" class="bottom-item <?php echo ($currentPage == basename(URL_PROFILE)) ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i>
        <span>我的</span>
    </a>
</nav>

<script src="<?php echo URL_ASSETS; ?>/js/header-script.js"></script>