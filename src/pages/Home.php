<?php
require_once __DIR__ . '/../../init.php';
require_once BASE_PATH . 'config/urls.php';


// Set page variables
$pageTitle = "首页 - " . WEBSITE_NAME; 
?>

<!DOCTYPE html>
<html lang="zh-CN">

<?php require_once BASE_PATH . 'include/header.php'; ?>

<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>


<div class="container main-content" style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
    
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        <div class="alert alert-success">
            欢迎回来, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></strong>!
        </div>
    <?php endif; ?>

    <h3>首页内容区域</h3>
    <p>这里是公开内容，任何人都可以看到 (Banner, Rank, Categories)。</p>

</div>

</body>
</html>