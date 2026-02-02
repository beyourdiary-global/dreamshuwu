<?php
session_start();

// Security check: Redirect if they are not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: register.php");
    exit();
}

$pageTitle = "欢迎 - StarAdmin";
?>

<!DOCTYPE html>
<html lang="zh-CN">

<?php require_once __DIR__ . '/header.php'; ?>

<body>

<div class="welcome-card">
    <div class="logo">Star<span>Admin</span></div>
    
    <div class="success-icon">🎉</div>
    <h1>注册成功!</h1>
    
    <p>欢迎加入我们，</p>
    
    <?php $displayUserName = $_SESSION['user_name'] ?? ''; ?>
    <div class="user-name" style="font-size: 1.5rem; font-weight: bold; color: #233dd2; margin: 10px 0;">
        <?php echo htmlspecialchars($displayUserName); ?>
    </div>
    
    <p>您现在已自动登录，可以开始探索您的后台面板了。</p>

    <div class="footer-links" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
        <p style="font-size: 14px; color: #666;">
            <a href="login.php" style="color: #233dd2; text-decoration: none; margin: 0 10px;">返回登录页面</a>
        </p>
    </div>
</div>

</body>
</html>