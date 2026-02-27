<?php
require_once dirname(__DIR__, 3) . '/common.php';

$message = "";
$msgType = "";
$validToken = false;
$email = "";

// Capture token from GET (email link) or POST (form submission)
$token = post('token') !== '' ? post('token') : input('token');

if ($token === '') {
    $message = "无效的访问链接，请重新申请重置密码。";
    $msgType = "danger";
} else {
    // Verify Token
    $currentDate = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("SELECT email FROM " . PWD_RESET . " WHERE token = ? AND expires_at > ? LIMIT 1");
    $stmt->bind_param("ss", $token, $currentDate);
    $stmt->execute();
    
    $stmt->bind_result($fetchedEmail);
    
    if ($stmt->fetch()) {
        $validToken = true;
        $email = $fetchedEmail;
    } else {
        $message = "重置链接已失效或不存在，请重新申请。";
        $msgType = "danger";
    }
    $stmt->close();
}

if (isPostRequest() && $validToken) {
    $newPass = post('new_password');
    $confirmPass = post('confirm_password');

    if ($newPass === '' || $confirmPass === '') {
        $message = "请输入新密码";
        $msgType = "danger";
    } elseif ($newPass !== $confirmPass) {
        $message = "两次输入的密码不一致";
        $msgType = "danger";
    } elseif (!isStrongPassword($newPass)) {
        $message = "密码不符合安全要求 (最少8位，包含大小写字母、数字和符号)";
        $msgType = "danger";
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE " . USR_LOGIN . " SET password_hash = ? WHERE email = ?");
        $updateStmt->bind_param("ss", $newHash, $email);
        
        if ($updateStmt->execute()) {
            $delStmt = $conn->prepare("DELETE FROM " . PWD_RESET . " WHERE email = ?");
            $delStmt->bind_param("s", $email);
            $delStmt->execute();

            $message = "密码重置成功，请使用新密码登录";
            $msgType = "success";
            $validToken = false; // Hide form upon success
            header("refresh:3;url=" . URL_LOGIN);
        } else {
            $message = "操作失败，请稍后再试";
            $msgType = "danger";
        }
    }
}
?>

<?php $pageMetaKey = '/reset-password.php'; ?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/auth.css?v=<?php echo time(); ?>">
</head>
<body class="auth-page">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="auth-main">
    <div class="auth-layout">
        <div class="reset-card">
            <h3>重置密码</h3>
            <p class="subtext">请为您的账号设置新密码</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($msgType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form id="resetForm" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="auth-field mb-3">
                        <label class="form-label">新密码</label>
                        <div class="password-field">
                            <input type="password" class="form-control" id="password" name="new_password" required>
                            <button type="button" class="toggle-password" data-target="password">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-field mb-3">
                        <label class="form-label">确认新密码</label>
                        <div class="password-field">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="toggle-password" data-target="confirm_password">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-hints mb-4">
                        <div id="strength-meter">密码强度提示: <span id="strength-text">未填写</span></div>
                        <div class="hint-line">* 密码长度至少8位</div>
                        <div class="hint-line">* 包含大写、小写字母、数字和特殊字符</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">保存并登录</button>
                </form>
            <?php else: ?>
                <div class="mt-4">
                    <a href="<?php echo URL_FORGOT_PWD; ?>" class="btn btn-outline-primary w-100">返回重置申请页</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/auth.js"></script>
</body>
</html>