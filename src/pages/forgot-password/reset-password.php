<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . 'config/urls.php'; 
require_once BASE_PATH . 'functions.php';

$pageTitle = "重置密码 - " . WEBSITE_NAME;

$message = "";
$msgType = "";
$validToken = false;
$email = "";

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = "无效的访问链接";
    $msgType = "danger";
} else {
    // Verify Token using Constant PWD_RESET
    $currentDate = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("SELECT email FROM " . PWD_RESET . " WHERE token = ? AND expires_at > ? LIMIT 1");
    $stmt->bind_param("ss", $token, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $validToken = true;
        $email = $row['email'];
    } else {
        $message = "重置链接已失效，请重新申请";
        $msgType = "danger";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $validToken) {
    $newPass = $_POST['new_password'] ?? "";
    $confirmPass = $_POST['confirm_password'] ?? "";

    if (empty($newPass) || empty($confirmPass)) {
        $message = "请输入新密码";
        $msgType = "danger";
    } elseif ($newPass !== $confirmPass) {
        $message = "两次输入的密码不一致";
        $msgType = "danger";
    } elseif (!isStrongPassword($newPass)) {
        $message = "密码不符合安全要求 (最少8位，包含大小写字母、数字和符号)";
        $msgType = "danger";
    } else {
        // Update User Password (Using USR_LOGIN constant)
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE " . USR_LOGIN . " SET password_hash = ? WHERE email = ?");
        $updateStmt->bind_param("ss", $newHash, $email);
        
        if ($updateStmt->execute()) {
            // Cleanup Token (Using PWD_RESET constant)
            $delStmt = $conn->prepare("DELETE FROM " . PWD_RESET . " WHERE email = ?");
            $delStmt->bind_param("s", $email);
            $delStmt->execute();

            $message = "密码重置成功，请使用新密码登录";
            $msgType = "success";
            $validToken = false;
            header("refresh:3;url=" . URL_LOGIN);
        } else {
            $message = "操作失败，请稍后再试";
            $msgType = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<?php require_once BASE_PATH . 'include/header.php'; ?>
<body>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="login-card shadow-lg p-4 bg-white rounded">
                <div class="logo text-center mb-4">Star<span class="text-primary fw-bold">Admin</span></div>
                <h3 class="text-center">重置密码</h3>

                <div id="clientError" class="alert alert-danger" style="display:none;"></div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $msgType; ?> mt-3"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <?php if ($validToken): ?>
                <p class="subtext text-center text-muted">请为您的账号设置一个新的安全密码</p>
                <form id="resetForm" method="POST" autocomplete="off" novalidate>
                    
                    <div class="form-floating mb-3 position-relative">
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="New Password" required style="padding-right: 60px;">
                        <label for="new_password">新密码</label>
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">显示</button>
                    </div>

                    <div class="form-floating mb-3 position-relative">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required style="padding-right: 60px;">
                        <label for="confirm_password">确认新密码</label>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">显示</button>
                    </div>
                    
                    <div id="strength-meter" class="strength-meter mb-2">
                        密码强度提示: <span id="strength-text">未填写</span>
                    </div>
                    
                    <div class="password-requirements mb-3">
                        * 密码长度至少8位<br>
                        * 包含大写、小写字母、数字和特殊字符
                    </div>
                    <button id="resetBtn" type="submit" class="btn btn-primary w-100 py-2 fw-bold">重置密码</button>
                </form>

                <?php elseif ($msgType === 'success'): ?>
                     <div class="text-center mt-4"><a href="<?php echo URL_LOGIN; ?>" class="btn btn-primary w-100">立即登录</a></div>
                <?php else: ?>
                    <div class="text-center mt-4 border-top pt-3">
                        <a href="<?php echo URL_FORGOT_PWD; ?>" class="text-decoration-none">重新申请重置链接</a>
                        <br><br>
                        <a href="<?php echo URL_LOGIN; ?>" class="text-decoration-none small text-muted">返回登录</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/login-script.js"></script>

<script src="<?php echo URL_ASSETS; ?>/js/reset-password-script.js?v=<?php echo $resetScriptVersion; ?>"></script>
</body>
</html>