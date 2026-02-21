<?php
require_once dirname(__DIR__, 3) . '/common.php';

$message = "";
$msgType = "";
$validToken = false;
$email = "";

$token = $_POST['token'] ?? ($_GET['token'] ?? '');

if (empty($token)) {
    $message = "无效的访问链接";
    $msgType = "danger";
} else {
    // Verify Token using Constant PWD_RESET
    $currentDate = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("SELECT email FROM " . PWD_RESET . " WHERE token = ? AND expires_at > ? LIMIT 1");
    $stmt->bind_param("ss", $token, $currentDate);
    $stmt->execute();
    
    // [FIX] Universal Fetch (Replaces get_result)
    // We bind the result to a variable instead of fetching an object
    $stmt->bind_result($fetchedEmail);
    
    if ($stmt->fetch()) {
        $validToken = true;
        $email = $fetchedEmail;
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

<?php $pageMetaKey = '/reset-password.php'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/auth.css?v=<?php echo time(); ?>">
</head>
<body class="auth-page">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="dashboard-main">
    <div class="auth-layout">
        <div class="reset-card">
            <h3>重置密码</h3>
            <p class="subtext">请为您的账号设置新密码</p>

            <form id="resetForm" method="POST">
                <div class="auth-field mb-3 password-field position-relative">
                    <label class="form-label">新密码</label>
                    <input type="password" class="form-control" id="password" name="new_password" required>
                    <button type="button" class="toggle-password" data-target="password">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>

                <div class="auth-field mb-3 password-field position-relative">
                    <label class="form-label">确认新密码</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-password" data-target="confirm_password">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>

                <div class="password-hints mb-4">
                    <div id="strength-meter">密码强度提示: <span id="strength-text">未填写</span></div>
                    <div class="hint-line">* 密码长度至少8位</div>
                    <div class="hint-line">* 包含大写、小写字母、数字和特殊字符</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">保存并登录</button>
            </form>

        </div>
    </div>
</main>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/auth.js"></script>
</body>
</html>