<?php

// 1. Skip DB Check to prevent infinite loops
define('SKIP_DB_CHECK', true);

// 2. Load Init
require_once __DIR__ . '/init.php'; 

// Fallback: If init failed to load URL constants
if (!defined('URL_ASSETS')) {
    require_once __DIR__ . '/config/urls.php';
}

$pageTitle = "Page Not Found - " . WEBSITE_NAME;
http_response_code(404);
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    
</head>
<body class="d-flex flex-column min-vh-100">

    <?php 
    require_once BASE_PATH . 'common/menu/header.php'; 
    ?>

    <div class="container flex-grow-1 d-flex justify-content-center align-items-center">
        <div class="text-center">
            <img src="<?php echo URL_ASSETS; ?>/images/404 image.png" alt="404 Error" class="img-fluid" style="max-width: 500px;">
        <h3 class="mt-4 text-secondary">哎呀！出错了。</h3>
        <p class="text-muted">您访问的页面不存在，或者系统暂时不可用。</p>    
        </div>
    </div>

    <footer class="text-center py-4 text-muted border-top mt-auto">
        &copy; <?php echo date('Y'); ?> <?php echo defined('WEBSITE_NAME') ? WEBSITE_NAME : 'DreamShuWu'; ?>. All rights reserved.
    </footer>

</body>
</html>