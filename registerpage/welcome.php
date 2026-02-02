<?php
session_start();

// Security check: Redirect if they are not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: register.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>欢迎 - StarAdmin</title>
    <link rel="stylesheet" href="register-style.css">
    <style>
        .welcome-card {
            text-align: center;
            background: #fff;
            padding: 50px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
        }
        .user-name { 
            color: #233dd2; 
            font-weight: bold; 
            font-size: 1.8em; 
            display: block;
            margin: 15px 0;
        }
        .success-icon { font-size: 50px; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="welcome-card">
    <div class="logo">Star<span>Admin</span></div>
    <div class="success-icon">🎉</div>
    <h1>注册成功!</h1>
    <p>欢迎加入我们，</p>
    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    <p style="color: #666;">您现在已自动登录，可以开始探索您的后台面板了。</p>
</div>

</body>
</html>