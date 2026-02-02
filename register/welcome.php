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
    
    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    
    <p>您现在已自动登录，可以开始探索您的后台面板了。</p>
</div>

</body>
</html>